<?php
/**
 * Device Status Check - Safety net chạy qua cron
 *
 * 1. Đánh dấu devices offline khi quá timeout (backup cho mqtt_listener)
 * 2. Gửi push notification DEVICE_OFFLINE mỗi 1 phút cho đến khi user xác nhận đã biết
 *
 * Crontab: * * * * * php /var/www/app.cfarm.vn/app/domains/iot/device_status_check.php >> /var/log/cfarm-device-check.log 2>&1
 */

declare(strict_types=1);

require_once __DIR__ . '/../../shared/database/mysql.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Domains\Intelligence\PushService;

const OFFLINE_TIMEOUT = 90; // seconds - giống listener

// 1. Đánh dấu offline nếu không heartbeat quá timeout
$stmt = $pdo->prepare("
    UPDATE devices SET is_online = 0
    WHERE is_online = 1
    AND (last_heartbeat_at IS NULL OR last_heartbeat_at < DATE_SUB(NOW(), INTERVAL :timeout SECOND))
");
$stmt->execute([':timeout' => OFFLINE_TIMEOUT]);

$count = $stmt->rowCount();
if ($count > 0) {
    echo "[" . date('Y-m-d H:i:s') . "] Marked {$count} device(s) as OFFLINE\n";
}

// 2. Gửi push notification cho devices đang offline mà chưa acknowledged
$offline_devices = $pdo->query("
    SELECT d.id, d.device_code, d.name, d.last_heartbeat_at,
           d.last_offline_alert_at,
           b.name AS barn_name
    FROM devices d
    LEFT JOIN barns b ON d.barn_id = b.id
    WHERE d.is_online = 0
      AND d.alert_offline = 1
      AND d.last_heartbeat_at IS NOT NULL
      AND d.last_offline_alert_at IS NULL
")->fetchAll();

if (empty($offline_devices)) exit;

$push = new PushService($pdo);

foreach ($offline_devices as $dev) {
    // Tính thời gian offline
    $offline_secs = time() - strtotime($dev['last_heartbeat_at']);
    if ($offline_secs < OFFLINE_TIMEOUT) continue;

    $offline_min = (int)($offline_secs / 60);
    $offline_str = $offline_min < 60
        ? $offline_min . ' phút'
        : number_format($offline_min / 60, 1) . ' giờ';

    $barn_label = $dev['barn_name'] ? ' · ' . $dev['barn_name'] : '';
    $title = '⚠️ Thiết bị mất kết nối' . $barn_label;
    $body  = $dev['name'] . ' (' . $dev['device_code'] . ') offline ' . $offline_str;

    $push->send_all(
        'DEVICE_OFFLINE',
        $title,
        $body,
        null,
        '/settings/devices'
    );

    echo "[" . date('Y-m-d H:i:s') . "] Sent DEVICE_OFFLINE push for {$dev['device_code']}\n";
}
