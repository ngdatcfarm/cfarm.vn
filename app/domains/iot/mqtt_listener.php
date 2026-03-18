<?php
/**
 * MQTT Listener - Lắng nghe heartbeat từ ESP32 và cập nhật trạng thái
 * 
 * Cách chạy:
 * 1. composer install (đã có php-mqtt/client)
 * 2. Chạy: php app/domains/iot/mqtt_listener.php
 * 3. Hoặc thêm vào supervisor/systemd để chạy liên tục
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

echo "[MQTT Listener] Starting...\n";

$host = '103.166.183.215';
$port = 1883;
$user = 'cfarm_server';
$pass = 'Abc@@123';

// Database connection
$pdo = require __DIR__ . '/../../app/shared/database/mysql.php';

try {
    $mqtt = new MqttClient($host, $port, 'cfarm_listener_' . getmypid());
    
    $mqtt->connect(
        (new ConnectionSettings)
            ->setUsername($user)
            ->setPassword($pass)
            ->setKeepAliveInterval(60)
    );
    
    echo "[MQTT] Connected to broker!\n";
    
    // Subscribe to all device topics
    $mqtt->subscribe('+/heartbeat', function($topic, $message) use ($pdo) {
        handleMessage($pdo, $topic, $message, 'heartbeat');
    }, 0);
    
    $mqtt->subscribe('+/status', function($topic, $message) use ($pdo) {
        handleMessage($pdo, $topic, $message, 'status');
    }, 0);
    
    $mqtt->subscribe('+/state', function($topic, $message) use ($pdo) {
        handleMessage($pdo, $topic, $message, 'state');
    }, 0);
    
    echo "[MQTT] Subscribed to +/heartbeat, +/status, +/state\n";
    echo "[MQTT] Listening for messages...\n";
    
    $mqtt->loop(true);
    
} catch (\Exception $e) {
    echo "[MQTT] Error: " . $e->getMessage() . "\n";
}

function handleMessage($pdo, $topic, $message, $msgType) {
    echo "[" . date('H:i:s') . "] Received: $topic\n";
    
    // Parse topic: cfarm/esp-barn1-relay-001/heartbeat
    $parts = explode('/', $topic);
    if (count($parts) < 3) return;
    
    $mqttTopic = $parts[0] . '/' . $parts[1];
    
    // Find device by mqtt_topic
    $stmt = $pdo->prepare("SELECT id FROM devices WHERE mqtt_topic = ?");
    $stmt->execute([$mqttTopic]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        // Auto-create device if not found (first time connecting)
        echo "  -> Auto-creating new device: $mqttTopic\n";
        
        // Get device_code from heartbeat message
        $data = json_decode($message, true);
        $deviceCode = $data['device'] ?? basename($mqttTopic);
        
        // Check if device_type_id 1 exists (ESP32 Relay 8CH)
        $typeCheck = $pdo->query("SELECT id FROM device_types WHERE id = 1")->fetchColumn();
        if (!$typeCheck) {
            echo "  -> ERROR: No device type found!\n";
            return;
        }
        
        // Insert new device
        $pdo->prepare("
            INSERT INTO devices (device_code, name, device_type_id, mqtt_topic, is_online, created_at)
            VALUES (?, ?, 1, ?, 1, NOW())
        ")->execute([$deviceCode, $deviceCode, $mqttTopic]);
        
        $deviceId = (int)$pdo->lastInsertId();
        
        // Auto-create 8 channels for this device
        $defaultPins = [32, 33, 25, 26, 27, 14, 12, 13];
        for ($ch = 1; $ch <= 8; $ch++) {
            $pdo->prepare("
                INSERT INTO device_channels (device_id, channel_number, name, channel_type, gpio_pin, is_active)
                VALUES (?, ?, ?, 'other', ?, 1)
            ")->execute([$deviceId, $ch, 'Kênh ' . $ch, $defaultPins[$ch-1]]);
        }
        
        echo "  -> Created new device ID: $deviceId with 8 channels\n";
    } else {
        $deviceId = $device['id'];
    }
    
    if ($msgType === 'heartbeat' || $msgType === 'status') {
        $data = json_decode($message, true);
        if (!$data) return;
        
        // Update device status
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
        
        // Update relay states if available
        if (isset($data['relays']) && is_array($data['relays'])) {
            $channelStmt = $pdo->prepare("
                SELECT id FROM device_channels WHERE device_id = ? ORDER BY channel_number
            ");
            $channelStmt->execute([$deviceId]);
            $channels = $channelStmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($data['relays'] as $index => $state) {
                if (isset($channels[$index])) {
                    $pdo->prepare("
                        INSERT INTO device_states (device_id, channel_id, state, updated_at)
                        VALUES (?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE state = VALUES(state), updated_at = NOW()
                    ")->execute([$deviceId, $channels[$index], $state ? 'on' : 'off']);
                }
            }
        }
        
        echo "  -> Device $deviceId updated as ONLINE\n";
    }
    elseif ($msgType === 'state') {
        $data = json_decode($message, true);
        if (!$data || !isset($data['channel'])) return;
        
        $stmt = $pdo->prepare("
            SELECT id FROM device_channels 
            WHERE device_id = ? AND channel_number = ?
        ");
        $stmt->execute([$deviceId, $data['channel']]);
        $channel = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($channel) {
            $pdo->prepare("
                INSERT INTO device_states (device_id, channel_id, state, updated_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE state = VALUES(state), updated_at = NOW()
            ")->execute([$deviceId, $channel['id'], $data['state'] ?? 'off']);
            
            echo "  -> Channel {$data['channel']} state updated\n";
        }
    }
}

// Cleanup function for offline devices
function cleanupOfflineDevices($pdo) {
    $pdo->exec("
        UPDATE devices 
        SET is_online = 0 
        WHERE is_online = 1 
        AND (last_heartbeat_at IS NULL OR last_heartbeat_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE))
    ");
    echo "[" . date('H:i:s') . "] Cleaned up offline devices\n";
}
