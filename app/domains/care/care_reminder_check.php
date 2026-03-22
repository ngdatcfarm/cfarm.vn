<?php
/**
 * Care Reminder Check — Push notification nhắc ghi chép
 *
 * Gửi push nếu cycle active chưa ghi cho ăn buổi sáng (chạy lúc 11h)
 * hoặc chưa ghi cho ăn buổi chiều (chạy lúc 18h).
 *
 * Crontab:
 *   0 11 * * * php /var/www/app.cfarm.vn/app/domains/care/care_reminder_check.php >> /var/log/cfarm-care-reminder.log 2>&1
 *   0 18 * * * php /var/www/app.cfarm.vn/app/domains/care/care_reminder_check.php >> /var/log/cfarm-care-reminder.log 2>&1
 */

declare(strict_types=1);

require_once __DIR__ . '/../../shared/database/mysql.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Domains\Intelligence\PushService;

$now_hour = (int)date('H');
$session  = $now_hour < 14 ? 'morning' : 'evening';
$session_label = $session === 'morning' ? 'sáng' : 'chiều';
$today = date('Y-m-d');

$hour_start = $session === 'morning' ? 0 : 12;
$hour_end   = $session === 'morning' ? 12 : 24;

echo "[" . date('Y-m-d H:i:s') . "] Checking {$session_label} feed reminders...\n";

// Lấy cycles active chưa ghi cho ăn buổi này
$stmt = $pdo->prepare("
    SELECT c.id, c.code, b.name AS barn_name
    FROM cycles c
    JOIN barns b ON c.barn_id = b.id
    WHERE c.status = 'active'
      AND NOT EXISTS (
          SELECT 1 FROM care_feeds cf
          WHERE cf.cycle_id = c.id
            AND DATE(cf.recorded_at) = :today
            AND HOUR(cf.recorded_at) >= :h_start
            AND HOUR(cf.recorded_at) < :h_end
      )
");
$stmt->execute([':today' => $today, ':h_start' => $hour_start, ':h_end' => $hour_end]);
$missing = $stmt->fetchAll();

if (empty($missing)) {
    echo "All cycles have {$session_label} feed recorded.\n";
    exit;
}

$push = new PushService($pdo);

foreach ($missing as $c) {
    $title = "Nhắc ghi chép buổi {$session_label}";
    $body  = $c['barn_name'] . ' · ' . $c['code'] . ' — chưa ghi cho ăn buổi ' . $session_label;

    $push->send_all(
        'CARE_REMINDER',
        $title,
        $body,
        (int)$c['id'],
        '/events/create?cycle_id=' . $c['id']
    );

    echo "[" . date('Y-m-d H:i:s') . "] Sent reminder for {$c['code']} ({$c['barn_name']})\n";
}
