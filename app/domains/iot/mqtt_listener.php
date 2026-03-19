<?php
/**
 * MQTT Listener - Nhận messages từ ESP32
 * Xử lý: heartbeat, status (LWT), state, ping responses
 */

// Load dependencies
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../shared/database/mysql.php';
require_once __DIR__ . '/services/mqtt_service.php';

// Force output immediately
ob_implicit_flush(true);
ob_end_flush();

echo "[" . date('Y-m-d H:i:s') . "] Starting MQTT Listener...\n";

// Connect to MQTT broker
$cmd = "mosquitto_sub -h 103.166.183.215 -u cfarm_device -P Abc@@123 -t 'cfarm/#' -v --id cfarm_listener_php";

$handle = popen($cmd . " 2>&1", 'r');

if (!$handle) {
    echo "Failed to start mosquitto_sub\n";
    exit(1);
}

echo "Listening for MQTT messages...\n";

stream_set_blocking($handle, false);

$lastMessage = time();
$lastCleanup = time();
$lastPingCheck = time();
$mqttService = new MqttService();

while (!feof($handle)) {
    $line = fgets($handle);

    // Process received message
    if ($line) {
        $lastMessage = time();
        $line = trim($line);
        if ($line && strpos($line, 'cfarm/') === 0) {
            echo "RX: $line\n";
            processLine($pdo, $line);
        }
    }

    // Cleanup offline devices every 30 seconds
    if (time() - $lastCleanup > 30) {
        $lastCleanup = time();
        cleanupOffline($pdo);
    }

    // Send active pings every 60 seconds
    if (time() - $lastPingCheck > 60) {
        $lastPingCheck = time();
        sendActivePings($pdo, $mqttService);
    }

    // Check for ping timeouts
    checkPingTimeouts($pdo);

    // If no messages for 5 minutes, sleep a bit
    if (time() - $lastMessage > 300) {
        sleep(5);
    }

    usleep(100000); // 100ms
}

pclose($handle);

/**
 * Process incoming MQTT message
 */
function processLine($pdo, $line) {
    $pos = strpos($line, ' ');
    if ($pos === false) return;

    $topic = trim(substr($line, 0, $pos));
    $message = trim(substr($line, $pos + 1));

    $parts = explode('/', $topic);
    if (count($parts) < 3) return;

    // Extract message type (last part of topic)
    $msgType = $parts[count($parts)-1] ?? '';

    // Extract base MQTT topic (everything except msg type)
    $baseParts = array_slice($parts, 0, count($parts) - 1);

    // Handle duplicate cfarm/ prefix (e.g., cfarm/cfarm/barn1 -> cfarm/barn1)
    if (count($baseParts) >= 2 && $baseParts[0] === 'cfarm' && $baseParts[1] === 'cfarm') {
        $mqttTopic = 'cfarm/' . $baseParts[2];
    } else {
        $mqttTopic = implode('/', $baseParts);
    }

    // Find device by MQTT topic
    $stmt = $pdo->prepare("SELECT id FROM devices WHERE mqtt_topic = ?");
    $stmt->execute([$mqttTopic]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        // Check if device_code matches (for messages without proper topic)
        $data = json_decode($message, true);
        if (!empty($data['device'])) {
            $stmt = $pdo->prepare("SELECT id, mqtt_topic FROM devices WHERE device_code = ?");
            $stmt->execute([$data['device']]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($device) {
                $mqttTopic = $device['mqtt_topic'];
            }
        }
    }

    if (!$device) {
        // Also try to find by any matching topic pattern
        $stmt = $pdo->query("SELECT id, mqtt_topic FROM devices");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (strpos($mqttTopic, $row['mqtt_topic']) !== false ||
                strpos($mqttTopic, $row['mqtt_topic'] . '/') !== false) {
                $device = $row;
                $mqttTopic = $row['mqtt_topic'];
                break;
            }
        }
    }

    if (!$device) {
        echo "Device not found for topic: $mqttTopic\n";
        return;
    }

    $deviceId = $device['id'];

    // Process based on message type
    switch ($msgType) {
        case 'heartbeat':
            handleHeartbeat($pdo, $deviceId, $message);
            break;

        case 'status':
            handleStatus($pdo, $deviceId, $message);
            break;

        case 'state':
            handleState($pdo, $deviceId, $message);
            break;

        case 'pong':
        case 'ping_response':
            handlePingResponse($pdo, $deviceId);
            break;

        default:
            // Try to detect message type from content
            $data = json_decode($message, true);
            if ($data) {
                if (isset($data['status']) && ($data['status'] === 'online' || $data['status'] === 'offline')) {
                    handleStatus($pdo, $deviceId, $message);
                } elseif (isset($data['relays']) || isset($data['channel'])) {
                    handleHeartbeat($pdo, $deviceId, $message);
                }
            }
            break;
    }
}

/**
 * Handle heartbeat message
 */
function handleHeartbeat($pdo, $deviceId, $message) {
    $data = json_decode($message, true);
    if (!$data) return;

    $pdo->prepare("
        UPDATE devices SET
            is_online = 1,
            last_heartbeat_at = NOW(),
            wifi_rssi = ?,
            ip_address = ?,
            uptime_seconds = ?,
            free_heap_bytes = ?,
            last_ping_response_at = NOW(),
            ping_fail_count = 0,
            updated_at = NOW()
        WHERE id = ?
    ")->execute([
        $data['wifi_rssi'] ?? null,
        $data['ip'] ?? null,
        $data['uptime'] ?? null,
        $data['heap'] ?? null,
        $deviceId
    ]);

    echo "Heartbeat processed for device $deviceId\n";
}

/**
 * Handle status message (LWT - Last Will and Testament)
 * When ESP32 disconnects unexpectedly, broker sends this
 */
function handleStatus($pdo, $deviceId, $message) {
    $data = json_decode($message, true);
    if (!$data) return;

    if (isset($data['status'])) {
        if ($data['status'] === 'offline') {
            // LWT message - device disconnected unexpectedly
            $pdo->prepare("
                UPDATE devices SET
                    is_online = 0,
                    ping_fail_count = ping_fail_count + 1,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$deviceId]);

            echo "LWT: Device $deviceId marked as OFFLINE (client disconnected)\n";
        } elseif ($data['status'] === 'online') {
            // Reconnection heartbeat
            handleHeartbeat($pdo, $deviceId, $message);
        }
    }
}

/**
 * Handle state change message
 */
function handleState($pdo, $deviceId, $message) {
    $data = json_decode($message, true);
    if (!$data) return;

    // Log relay state changes
    echo "State change for device $deviceId: " . json_encode($data) . "\n";
}

/**
 * Handle ping response (pong)
 */
function handlePingResponse($pdo, $deviceId) {
    // Mark latest pending ping as success
    $stmt = $pdo->prepare("
        UPDATE device_pings
        SET status = 'success', ping_response_at = NOW()
        WHERE device_id = ? AND status = 'pending'
        ORDER BY ping_sent_at DESC
        LIMIT 1
    ");
    $stmt->execute([$deviceId]);

    // Update device
    $pdo->prepare("
        UPDATE devices SET
            is_online = 1,
            last_ping_response_at = NOW(),
            ping_fail_count = 0,
            updated_at = NOW()
        WHERE id = ?
    ")->execute([$deviceId]);

    echo "Ping response received from device $deviceId\n";
}

/**
 * Send active pings to online devices that haven't responded in a while
 */
function sendActivePings($pdo, $mqttService) {
    echo "[" . date('Y-m-d H:i:s') . "] Sending active pings...\n";

    // Get online devices that haven't been pinged in 60 seconds
    $stmt = $pdo->query("
        SELECT id, mqtt_topic
        FROM devices
        WHERE is_online = 1
        AND (
            last_ping_sent_at IS NULL
            OR last_ping_sent_at < DATE_SUB(NOW(), INTERVAL 60 SECOND)
            OR last_heartbeat_at < DATE_SUB(NOW(), INTERVAL 90 SECOND)
        )
    ");

    while ($device = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Send ping command
        $sent = $mqttService->publish($device['mqtt_topic'] . '/cmd', [
            'action' => 'ping',
            'ts' => time(),
            'type' => 'active_check'
        ]);

        if ($sent) {
            // Record ping in database
            $pdo->prepare("
                INSERT INTO device_pings (device_id, status)
                VALUES (?, 'pending')
            ")->execute([$device['id']]);

            // Update device
            $pdo->prepare("
                UPDATE devices SET
                    last_ping_sent_at = NOW()
                WHERE id = ?
            ")->execute([$device['id']]);

            echo "Sent ping to device {$device['id']} ({$device['mqtt_topic']})\n";
        }
    }
}

/**
 * Check for ping timeouts
 */
function checkPingTimeouts($pdo) {
    // Mark pending pings as timeout after 30 seconds
    $stmt = $pdo->prepare("
        UPDATE device_pings
        SET status = 'timeout'
        WHERE status = 'pending'
        AND ping_sent_at < DATE_SUB(NOW(), INTERVAL 30 SECOND)
    ");
    $stmt->execute();

    $timeoutCount = $stmt->rowCount();
    if ($timeoutCount > 0) {
        echo "Found $timeoutCount ping timeouts\n";

        // Increase fail count for devices with timeout
        $pdo->prepare("
            UPDATE devices d
            SET ping_fail_count = ping_fail_count + 1
            WHERE d.id IN (
                SELECT DISTINCT device_id FROM device_pings
                WHERE status = 'timeout'
                AND ping_sent_at < DATE_SUB(NOW(), INTERVAL 30 SECOND)
            )
            AND d.is_online = 1
        ")->execute();

        // Mark devices as offline if too many failures
        $pdo->prepare("
            UPDATE devices
            SET is_online = 0,
                last_heartbeat_at = NULL,
                updated_at = NOW()
            WHERE ping_fail_count >= 3
            AND is_online = 1
        ")->execute();

        $offlineCount = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
        if ($offlineCount > 0) {
            echo "Marked $offlineCount devices as OFFLINE due to ping failures\n";
        }
    }
}

/**
 * Cleanup offline devices based on heartbeat timeout
 */
function cleanupOffline($pdo) {
    // Devices with no heartbeat for 90 seconds AND no ping response
    $stmt = $pdo->prepare("
        UPDATE devices
        SET is_online = 0,
            ping_fail_count = 0
        WHERE is_online = 1
        AND (
            (last_heartbeat_at IS NULL AND last_ping_sent_at < DATE_SUB(NOW(), INTERVAL 90 SECOND))
            OR (last_heartbeat_at < DATE_SUB(NOW(), INTERVAL 90 SECOND)
                AND (last_ping_response_at IS NULL OR last_ping_response_at < DATE_SUB(NOW(), INTERVAL 90 SECOND)))
        )
    ");
    $stmt->execute();

    $count = $stmt->rowCount();
    if ($count > 0) {
        echo "Cleaned up $count offline devices\n";
    }

    // Show current status
    $devices = $pdo->query("
        SELECT id, name, mqtt_topic, is_online, last_heartbeat_at,
               ping_fail_count, last_ping_sent_at
        FROM devices
        ORDER BY is_online DESC, name
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "\n--- Device Status ---\n";
    foreach ($devices as $d) {
        $status = $d['is_online'] ? '🟢 ONLINE' : '⚪ OFFLINE';
        $heartbeat = $d['last_heartbeat_at'] ?? 'never';
        $pingFails = $d['ping_fail_count'] ?? 0;
        echo "  {$d['name']} ({$d['mqtt_topic']}) - $status | HB: $heartbeat | Ping fails: $pingFails\n";
    }
    echo "----------------------\n";
}
