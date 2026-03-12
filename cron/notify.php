<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

$pdo = new PDO('mysql:host=localhost;dbname=cfarm_app_raw;charset=utf8mb4', 'cfarm_user', 'cfarm_pass');
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$push  = new App\Domains\Intelligence\PushService($pdo);
$alert = new App\Domains\Intelligence\AlertService($pdo);
$hour  = (int)date('H');

// Load notification settings
$notif_settings = [];
foreach ($pdo->query("SELECT * FROM notification_settings WHERE enabled=1")->fetchAll() as $ns) {
    $notif_settings[$ns['code']] = $ns;
}

function should_send(PDO $pdo, string $code, ?int $cycle_id, int $interval_min, ?int $send_at_hour, int $hour): bool {
    if ($send_at_hour !== null) {
        // Gửi đúng giờ cố định, 1 lần/ngày
        if ($hour !== $send_at_hour) return false;
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM push_notifications_log
            WHERE type=:type AND (cycle_id=:cid OR :cid IS NULL)
              AND sent_at > DATE_SUB(NOW(), INTERVAL 23 HOUR)
        ");
        $stmt->execute([':type' => $code, ':cid' => $cycle_id]);
        return (int)$stmt->fetchColumn() === 0;
    }
    // Gửi theo interval (phút)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM push_notifications_log
        WHERE type=:type AND (cycle_id=:cid OR :cid IS NULL)
          AND sent_at > DATE_SUB(NOW(), INTERVAL :mins MINUTE)
    ");
    $stmt->execute([':type' => $code, ':cid' => $cycle_id, ':mins' => $interval_min]);
    return (int)$stmt->fetchColumn() === 0;
}

$cycles = $pdo->query("
    SELECT c.*, b.name AS barn_name
    FROM cycles c JOIN barns b ON c.barn_id = b.id
    WHERE c.status = 'active'
")->fetchAll();

foreach ($cycles as $c) {
    // Cảnh báo bất thường
    foreach ($alert->get_alerts((int)$c['id']) as $a) {
        if ($a['severity'] === 'info') continue;
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM push_notifications_log
            WHERE type=:type AND cycle_id=:cid
              AND sent_at > DATE_SUB(NOW(), INTERVAL 6 HOUR)
        ");
        $stmt->execute([':type' => $a['code'], ':cid' => $c['id']]);
        if ((int)$stmt->fetchColumn() > 0) continue;

        $push->send_all(
            $a['code'],
            '🚨 ' . $c['barn_name'] . ' · ' . $c['code'],
            $a['message'] . ' — ' . $a['detail'],
            (int)$c['id'],
            '/cycles/' . $c['id']
        );
        echo "[" . date('H:i') . "] Sent {$a['code']} cycle {$c['id']}\n";
    }

    // Nhắc cân gà lúc 8h
    if ($hour === 8) {
        $day_age = (int)((strtotime('today') - strtotime($c['start_date'])) / 86400) + 1;
        $stmt = $pdo->prepare("SELECT MAX(day_age) FROM weight_sessions WHERE cycle_id=:id");
        $stmt->execute([':id' => $c['id']]);
        $last = (int)$stmt->fetchColumn();
        if ($day_age > 7 && ($day_age - $last) >= 7) {
            $push->send_all(
                'REMIND_WEIGH',
                '⚖️ Nhắc cân gà — ' . $c['barn_name'],
                'Chưa cân ' . ($day_age - $last) . ' ngày. Hãy cân mẫu hôm nay!',
                (int)$c['id'],
                '/events/create?cycle_id=' . $c['id']
            );
            echo "[" . date('H:i') . "] Sent REMIND_WEIGH cycle {$c['id']}\n";
        }
    }
}

// Báo cáo cuối ngày lúc 20h
if ($hour === 20 && !empty($cycles)) {
    $lines = [];
    foreach ($cycles as $c) {
        $snap = $pdo->prepare("SELECT * FROM cycle_daily_snapshots WHERE cycle_id=:id ORDER BY day_age DESC LIMIT 1");
        $snap->execute([':id' => $c['id']]);
        $s = $snap->fetch();
        if ($s) {
            $lines[] = $c['barn_name'] . ': ' . number_format($s['alive_total']) . ' con'
                . ($s['fcr_cumulative'] ? ', FCR ' . $s['fcr_cumulative'] : '');
        }
    }
    if ($lines) {
        $push->send_all('DAILY_REPORT', '📊 Báo cáo ' . date('d/m'), implode(' | ', $lines), null, '/');
        echo "[" . date('H:i') . "] Sent daily report\n";
    }
}
echo "Done\n";
