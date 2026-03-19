<?php
/**
 * Device Status Check - Safety net chạy qua cron
 *
 * Backup cho mqtt_listener: nếu listener bị crash, cron này vẫn đánh dấu
 * devices offline khi quá timeout.
 *
 * Dùng cùng OFFLINE_TIMEOUT = 90s như listener để tránh conflict.
 *
 * Crontab: * * * * * php /var/www/app.cfarm.vn/app/domains/iot/device_status_check.php >> /var/log/cfarm-device-check.log 2>&1
 */

declare(strict_types=1);

require_once __DIR__ . '/../../shared/database/mysql.php';

const OFFLINE_TIMEOUT = 90; // seconds - giống listener

// Đánh dấu offline nếu không heartbeat quá timeout
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
