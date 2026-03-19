<?php
/**
 * MQTT Device Status Checker - Chạy mỗi phút qua cron
 * Kiểm tra và cập nhật trạng thái online/offline
 * 
 * Crontab: * * * * * php /var/www/app.cfarm.vn/app/domains/iot/device_status_check.php
 */

require_once __DIR__ . '/../../shared/database/mysql.php';

echo "[" . date('Y-m-d H:i:s') . "] Checking device statuses...\n";

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
    echo "Marked $count devices as OFFLINE\n";
}

// Show current status
$devices = $pdo->query("
    SELECT id, name, mqtt_topic, is_online, last_heartbeat_at,
           TIMESTAMPDIFF(SECOND, last_heartbeat_at, NOW()) as seconds_ago
    FROM devices
    ORDER BY is_online DESC, name
")->fetchAll(PDO::FETCH_ASSOC);

echo "\nCurrent device status:\n";
foreach ($devices as $d) {
    $status = $d['is_online'] ? '🟢 ONLINE' : '⚪ OFFLINE';
    $last = $d['last_heartbeat_at'] ? $d['seconds_ago'] . 's ago' : 'never';
    echo "  {$d['name']} ({$d['mqtt_topic']}) - $status - Last: $last\n";
}

echo "\nDone!\n";
