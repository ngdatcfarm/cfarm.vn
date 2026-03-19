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

$lastCleanup = time();

while (!feof($handle)) {
    $line = fgets($handle);
    if ($line) {
        $line = trim($line);
        if ($line && strpos($line, 'cfarm/') === 0) {
            echo "RX: $line\n";
            processLine($pdo, $line);
        }
    }
    
    // Cleanup every 60 seconds
    if (time() - $lastCleanup > 60) {
        $lastCleanup = time();
        cleanupOffline($pdo);
    }
    
    usleep(100000);
}

pclose($handle);

function processLine($pdo, $line) {
    $pos = strpos($line, ' ');
    if ($pos === false) return;
    
    $topic = trim(substr($line, 0, $pos));
    $message = trim(substr($line, $pos + 1));
    
    $parts = explode('/', $topic);
    if (count($parts) < 3) return;
    
    // Handle duplicate cfarm/ in topic (e.g., cfarm/cfarm/barn1)
    $mqttTopic = $parts[0] . '/' . $parts[1];
    if ($parts[0] === 'cfarm' && $parts[1] === 'cfarm') {
        $mqttTopic = $parts[0] . '/' . $parts[2];
    }
    
    $msgType = $parts[count($parts)-1] ?? '';
    
    // Find device
    $stmt = $pdo->prepare("SELECT id FROM devices WHERE mqtt_topic = ?");
    $stmt->execute([$mqttTopic]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        $data = json_decode($message, true);
        $deviceCode = $data['device'] ?? basename($mqttTopic);
        
        $pdo->prepare("
            INSERT INTO devices (device_code, name, device_type_id, mqtt_topic, is_online, created_at)
            VALUES (?, ?, 1, ?, 1, NOW())
        ")->execute([$deviceCode, $deviceCode, $mqttTopic]);
        
        $deviceId = (int)$pdo->lastInsertId();
        
        $pins = [32, 33, 25, 26, 27, 14, 12, 13];
        for ($ch = 1; $ch <= 8; $ch++) {
            $pdo->prepare("
                INSERT INTO device_channels (device_id, channel_number, name, channel_type, gpio_pin, is_active)
                VALUES (?, ?, ?, 'other', ?, 1)
            ")->execute([$deviceId, $ch, 'Kênh ' . $ch, $pins[$ch-1]]);
        }
        
        echo "Created: $deviceCode\n";
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
    // Mark devices as offline if no heartbeat in 2 minutes
    $stmt = $pdo->prepare("
        UPDATE devices 
        SET is_online = 0 
        WHERE is_online = 1 
        AND (last_heartbeat_at IS NULL OR last_heartbeat_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE))
    ");
    $stmt->execute();
    
    $count = $stmt->rowCount();
    if ($count > 0) {
        echo "Cleaned up $count offline devices\n";
    }
}
