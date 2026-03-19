<?php
/**
 * MQTT Listener - Nhận messages từ ESP32
 * Xử lý: heartbeat, status (LWT), state, ping responses
 */

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/shared/database/mysql.php';
require_once __DIR__ . '/services/mqtt_service.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting MQTT Listener...\n";

// Check mosquitto_sub
$mosquittoPath = trim(shell_exec('which mosquitto_sub'));
echo "Mosquitto: $mosquittoPath\n";

$cmd = "mosquitto_sub -h 103.166.183.215 -u cfarm_device -P Abc@@123 -t 'cfarm/#' -v --id cfarm_listener_php --keepalive 60 2>&1";

$descriptors = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"],
];

$process = proc_open($cmd, $descriptors, $pipes);

if (!is_resource($process)) {
    echo "Failed to start\n";
    exit(1);
}

stream_set_blocking($pipes[1], true);

echo "Listening for messages...\n";

$lastCleanup = time();
$lastPingCheck = time();
$mqttService = new MqttService();
$running = true;
$loopCount = 0;

while ($running) {
    $loopCount++;

    if ($loopCount % 5 == 0) {
        echo "Loop $loopCount\n";
    }

    $line = fgets($pipes[1]);

    if ($line === false) {
        // No data, sleep and continue
        usleep(100000);
        continue;
    }

    if ($line) {
        echo "RX: " . substr($line, 0, 80) . "\n";

        $line = trim($line);
        if (strpos($line, 'cfarm/') === 0) {
            try {
                processLine($pdo, $line);
            } catch (Exception $e) {
                echo "ERROR: " . $e->getMessage() . "\n";
            }
        }
    }

    // Cleanup every 30 seconds
    if (time() - $lastCleanup > 30) {
        $lastCleanup = time();
        try {
            cleanupOffline($pdo);
        } catch (Exception $e) {
            echo "Cleanup error: " . $e->getMessage() . "\n";
        }
    }

    // Send pings every 60 seconds
    if (time() - $lastPingCheck > 60) {
        $lastPingCheck = time();
        try {
            sendActivePings($pdo, $mqttService);
        } catch (Exception $e) {
            echo "Ping error: " . $e->getMessage() . "\n";
        }
    }

    usleep(100000);
}

proc_close($process);
echo "Done\n";

// ============== FUNCTIONS ==============

function processLine($pdo, $line) {
    $pos = strpos($line, ' ');
    if ($pos === false) return;

    $topic = trim(substr($line, 0, $pos));
    $message = trim(substr($line, $pos + 1));

    $parts = explode('/', $topic);
    if (count($parts) < 3) return;

    $msgType = $parts[count($parts)-1] ?? '';
    $baseParts = array_slice($parts, 0, count($parts) - 1);

    // Handle duplicate cfarm/
    if (count($baseParts) >= 2 && $baseParts[0] === 'cfarm' && $baseParts[1] === 'cfarm') {
        $mqttTopic = 'cfarm/' . $baseParts[2];
    } else {
        $mqttTopic = implode('/', $baseParts);
    }

    // Find device
    $stmt = $pdo->prepare("SELECT id FROM devices WHERE mqtt_topic = ?");
    $stmt->execute([$mqttTopic]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        // Try device_code
        $data = json_decode($message, true);
        if (!empty($data['device'])) {
            $stmt = $pdo->prepare("SELECT id FROM devices WHERE device_code = ?");
            $stmt->execute([$data['device']]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (!$device) {
        echo "Device not found: $mqttTopic\n";
        return;
    }

    $deviceId = $device['id'];

    // Handle message types
    if ($msgType === 'heartbeat' || $msgType === 'status') {
        $data = json_decode($message, true);
        if (!$data) return;

        $status = $data['status'] ?? 'online';

        if ($status === 'offline') {
            // LWT - device disconnected
            $pdo->prepare("UPDATE devices SET is_online = 0, ping_fail_count = ping_fail_count + 1 WHERE id = ?")
                ->execute([$deviceId]);
            echo "Device $deviceId marked OFFLINE (LWT)\n";
        } else {
            // Heartbeat
            $pdo->prepare("
                UPDATE devices SET
                    is_online = 1,
                    last_heartbeat_at = NOW(),
                    wifi_rssi = ?,
                    ip_address = ?,
                    uptime_seconds = ?,
                    free_heap_bytes = ?,
                    last_ping_response_at = NOW(),
                    ping_fail_count = 0
                WHERE id = ?
            ")->execute([
                $data['wifi_rssi'] ?? null,
                $data['ip'] ?? null,
                $data['uptime'] ?? null,
                $data['heap'] ?? null,
                $deviceId
            ]);
            echo "Updated device $deviceId\n";
        }
    }
}

function cleanupOffline($pdo) {
    // Mark offline if no heartbeat for 90 seconds
    $stmt = $pdo->prepare("
        UPDATE devices SET is_online = 0
        WHERE is_online = 1
        AND last_heartbeat_at < DATE_SUB(NOW(), INTERVAL 90 SECOND)
    ");
    $stmt->execute();

    $count = $stmt->rowCount();
    if ($count > 0) {
        echo "Marked $count devices offline\n";
    }
}

function sendActivePings($pdo, $mqttService) {
    echo "Sending active pings...\n";

    $stmt = $pdo->query("
        SELECT id, mqtt_topic FROM devices
        WHERE is_online = 1
        AND (last_ping_sent_at IS NULL OR last_ping_sent_at < DATE_SUB(NOW(), INTERVAL 60 SECOND))
    ");

    while ($device = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sent = $mqttService->publish($device['mqtt_topic'] . '/cmd', [
            'action' => 'ping',
            'ts' => time()
        ]);

        if ($sent) {
            $pdo->prepare("INSERT INTO device_pings (device_id, status) VALUES (?, 'pending')")
                ->execute([$device['id']]);
            $pdo->prepare("UPDATE devices SET last_ping_sent_at = NOW() WHERE id = ?")
                ->execute([$device['id']]);
            echo "Sent ping to {$device['id']}\n";
        }
    }
}
