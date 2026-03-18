<?php
/**
 * MQTT Listener - Using proc_open with mosquitto_sub
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

// Database
$pdo = require __DIR__ . '/../../../app/shared/database/mysql.php';

echo "Starting MQTT listener...\n";

// Open mosquitto_sub process
$descriptorspec = [
    0 => ["pipe", "r"],  // stdin
    1 => ["pipe", "w"],  // stdout
    2 => ["pipe", "w"],   // stderr
];

$process = proc_open(
    "mosquitto_sub -h 103.166.183.215 -u cfarm_device -P Abc@@123 -t 'cfarm/#' -v --id cfarm_listener",
    $descriptorspec,
    $pipes,
    null,
    null,
    ["bypass_shell" => true]
);

if (!is_resource($process)) {
    echo "Failed to start mosquitto_sub\n";
    exit(1);
}

echo "mosquitto_sub started! Listening...\n";

stream_set_blocking($pipes[1], false);

$lastCheck = time();

while (true) {
    // Read from stdout
    $data = fgets($pipes[1]);
    if ($data) {
        $line = trim($data);
        if ($line) {
            processLine($pdo, $line);
        }
    }
    
    // Check stderr
    $err = fgets($pipes[2]);
    if ($err) {
        echo "STDERR: " . trim($err) . "\n";
    }
    
    // Check if process died
    $status = proc_get_status($process);
    if (!$status['running']) {
        echo "Process died, restarting...\n";
        break;
    }
    
    // Cleanup every 60 seconds
    if (time() - $lastCheck > 60) {
        $lastCheck = time();
        cleanupOffline($pdo);
        echo "[" . date('H:i:s') . "] Cleaned up offline devices\n";
    }
    
    usleep(100000); // 100ms
}

proc_close($process);

function processLine($pdo, $line) {
    // Parse: topic payload
    $pos = strpos($line, ' ');
    if ($pos === false) return;
    
    $topic = substr($line, 0, $pos);
    $message = substr($line, $pos + 1);
    
    $parts = explode('/', $topic);
    if (count($parts) < 3) return;
    
    $mqttTopic = $parts[0] . '/' . $parts[1];
    $msgType = $parts[2];
    
    // Find device
    $stmt = $pdo->prepare("SELECT id FROM devices WHERE mqtt_topic = ?");
    $stmt->execute([$mqttTopic]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        // Create new device
        $data = json_decode($message, true);
        $deviceCode = $data['device'] ?? basename($mqttTopic);
        
        $pdo->prepare("
            INSERT INTO devices (device_code, name, device_type_id, mqtt_topic, is_online, created_at)
            VALUES (?, ?, 1, ?, 1, NOW())
        ")->execute([$deviceCode, $deviceCode, $mqttTopic]);
        
        $deviceId = (int)$pdo->lastInsertId();
        
        // Create channels
        $pins = [32, 33, 25, 26, 27, 14, 12, 13];
        for ($ch = 1; $ch <= 8; $ch++) {
            $pdo->prepare("
                INSERT INTO device_channels (device_id, channel_number, name, channel_type, gpio_pin, is_active)
                VALUES (?, ?, ?, 'other', ?, 1)
            ")->execute([$deviceId, $ch, 'Kênh ' . $ch, $pins[$ch-1]]);
        }
        
        echo "Created new device: $deviceCode\n";
    } else {
        $deviceId = $device['id'];
    }
    
    // Update status
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
    $pdo->exec("
        UPDATE devices 
        SET is_online = 0 
        WHERE is_online = 1 
        AND (last_heartbeat_at IS NULL OR last_heartbeat_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE))
    ");
}
