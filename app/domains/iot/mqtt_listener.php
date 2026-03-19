<?php
/**
 * MQTT Listener - Simple shell approach
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

$pdo = require __DIR__ . '/../../../app/shared/database/mysql.php';

echo "Starting...\n";

// Keep reading from mosquitto_sub
$cmd = "mosquitto_sub -h 103.166.183.215 -u cfarm_device -P Abc@@123 -t 'cfarm/#' -v --id cfarm_listener_php";

$handle = popen($cmd . " 2>&1", 'r');

if (!$handle) {
    echo "Failed to start\n";
    exit(1);
}

echo "Listening...\n";

stream_set_blocking($handle, false);

$lastMessage = time();
$lastCleanup = time();

while (!feof($handle)) {
    $line = fgets($handle);
    
    // Check if we got a line (message)
    if ($line) {
        $lastMessage = time();
        $line = trim($line);
        if ($line && strpos($line, 'cfarm/') === 0) {
            echo "RX: $line\n";
            processLine($pdo, $line);
        }
    }
    
    // Cleanup every 30 seconds
    if (time() - $lastCleanup > 30) {
        $lastCleanup = time();
        cleanupOffline($pdo);
    }
    
    // If no messages for 5 minutes, just sleep a bit
    if (time() - $lastMessage > 300) {
        sleep(5);
    }
    
    usleep(100000); // 100ms
}

pclose($handle);

function processLine($pdo, $line) {
    $pos = strpos($line, ' ');
    if ($pos === false) return;

    $topic = trim(substr($line, 0, $pos));
    $message = trim(substr($line, $pos + 1));

    $parts = explode('/', $topic);
    if (count($parts) < 3) return;

    // Extract MQTT topic - handle various formats:
    // cfarm/barn1/heartbeat -> cfarm/barn1
    // cfarm/cfarm/barn1/heartbeat -> cfarm/barn1 (duplicate cfarm/)
    $msgType = $parts[count($parts)-1] ?? '';

    // Find base MQTT topic (everything except the last part which is msg type)
    $baseParts = array_slice($parts, 0, count($parts) - 1);

    // Handle duplicate cfarm/ prefix
    if (count($baseParts) >= 2 && $baseParts[0] === 'cfarm' && $baseParts[1] === 'cfarm') {
        $mqttTopic = 'cfarm/' . $baseParts[2];
    } else {
        $mqttTopic = implode('/', $baseParts);
    }

    // Find device - SKIP auto-create, only update existing
    $stmt = $pdo->prepare("SELECT id FROM devices WHERE mqtt_topic = ?");
    $stmt->execute([$mqttTopic]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        echo "Device not found: $mqttTopic\n";
        return;
    } else {
        $deviceId = $device['id'];
    }
    
    if ($msgType === 'heartbeat' || $msgType === 'status') {
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
                updated_at = NOW()
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

function cleanupOffline($pdo) {
    // Mark devices as offline if no heartbeat in 60 seconds
    $stmt = $pdo->prepare("
        UPDATE devices 
        SET is_online = 0 
        WHERE is_online = 1 
        AND (last_heartbeat_at IS NULL OR last_heartbeat_at < DATE_SUB(NOW(), INTERVAL 1 MINUTE))
    ");
    $stmt->execute();
    
    $count = $stmt->rowCount();
    if ($count > 0) {
        echo "Cleaned up $count offline devices\n";
    }
}
