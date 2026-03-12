<?php
/**
 * CFarm — Device Offline Alert
 * Chạy mỗi 2 phút qua systemd timer
 * Gửi push notification khi device không heartbeat > 5 phút
 * Chỉ gửi cho device có alert_offline = 1
 * Không gửi lại nếu đã alert trong vòng 30 phút
 */

require_once '/var/www/app.cfarm.vn/vendor/autoload.php';
$pdo = require '/var/www/app.cfarm.vn/app/shared/database/mysql.php';

// Load PushService
require_once '/var/www/app.cfarm.vn/app/domains/intelligence/push_service.php';
$push = new App\Domains\Intelligence\PushService($pdo);

// Tìm devices offline > 5 phút, có alert_offline=1
// Không gửi lại nếu đã alert trong 30 phút qua
$devices = $pdo->query("
    SELECT d.*, b.name as barn_name
    FROM devices d
    LEFT JOIN barns b ON b.id = d.barn_id
    WHERE d.alert_offline = 1
      AND d.is_online = 0
      AND d.last_heartbeat_at IS NOT NULL
      AND d.last_heartbeat_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
      AND (
          d.last_offline_alert_at IS NULL
          OR d.last_offline_alert_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
      )
")->fetchAll(PDO::FETCH_OBJ);

if (empty($devices)) {
    exit(0);
}

foreach ($devices as $d) {
    $minutes = (int)round((time() - strtotime($d->last_heartbeat_at)) / 60);
    $barn    = $d->barn_name ?? 'Không rõ chuồng';

    echo '[' . date('H:i:s') . "] ALERT: {$d->device_code} offline {$minutes} phút\n";

    try {
        $push->send_all(
            type:     'device_offline',
            title:    '⚠️ Thiết bị mất kết nối',
            body:     "{$d->device_code} ({$barn}) không phản hồi {$minutes} phút",
            url:      '/iot/devices'
        );

        // Cập nhật last_offline_alert_at
        $pdo->prepare("
            UPDATE devices SET last_offline_alert_at = NOW() WHERE id = :id
        ")->execute([':id' => $d->id]);

    } catch (\Throwable $e) {
        echo '[ERROR] ' . $e->getMessage() . "\n";
    }
}

echo '[' . date('H:i:s') . "] Sent alerts for " . count($devices) . " device(s)\n";
