<?php
/**
 * MQTT Listener - Full version
 * Receives messages from ESP32 devices
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Loading autoload...\n";
require_once __DIR__ . '/../../../vendor/autoload.php';
echo "Autoload OK\n";

echo "Loading mysql...\n";
require_once __DIR__ . '/../../../app/shared/database/mysql.php';
echo "MySQL OK\n";

echo "Loading MqttService...\n";
require_once __DIR__ . '/services/mqtt_service.php';
echo "MqttService OK\n";

// Use fully qualified name since it has namespace
use App\Domains\IoT\Services\MqttService;

echo "[" . date('Y-m-d H:i:s') . "] Starting MQTT Listener...\n";

$cmd = '/usr/bin/mosquitto_sub -h 103.166.183.215 -u cfarm_device -P Abc@@123 -t "cfarm/#" -v --id cfarm_listener_v3 --keepalive 60';

$desc = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$proc = proc_open($cmd, $desc, $pipes);

if (!$proc) {
    echo "FAIL to start mosquitto_sub\n";
    exit(1);
}

echo "Listening for messages...\n";

$mqttService = new MqttService();
$lastCleanup = time();
$lastPingCheck = time();

while (true) {
    $line = fgets($pipes[1]);

    if ($line) {
        $line = trim($line);
        echo "RX: " . substr($line, 0, 60) . "\n";

        // Process the message
        processLine($pdo, $line);
    }

    // Cleanup every 30 seconds
    if (time() - $lastCleanup > 30) {
        $lastCleanup = time();
        cleanupOffline($pdo);
    }

    // Send pings every 60 seconds
    if (time() - $lastPingCheck > 60) {
        $lastPingCheck = time();
        sendActivePings($pdo, $mqttService);
    }

    usleep(100000); // 100ms
}

proc_close($proc);

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

    // Find device by mqtt_topic
    $stmt = $pdo->prepare("SELECT id FROM devices WHERE mqtt_topic = ?");
    $stmt->execute([$mqttTopic]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        // Try device_code
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
            echo "Device $deviceId OFFLINE (LWT)\n";
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
            echo "Device $deviceId heartbeat OK\n";
        }
    }
}

function cleanupOffline($pdo) {
    echo "[Cleanup] Checking offline devices...\n";

    // Mark offline if no heartbeat for 90 seconds
    $stmt = $pdo->prepare("
        UPDATE devices SET is_online = 0
        WHERE is_online = 1
        AND (last_heartbeat_at IS NULL OR last_heartbeat_at < DATE_SUB(NOW(), INTERVAL 90 SECOND))
    ");
    $stmt->execute();

    $count = $stmt->rowCount();
    if ($count > 0) {
        echo "[Cleanup] Marked $count devices offline\n";
    }
}

function sendActivePings($pdo, $mqttService) {
    echo "[Ping] Sending active pings...\n";

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
            echo "[Ping] Sent to device {$device['id']}\n";
        }
    }
}
