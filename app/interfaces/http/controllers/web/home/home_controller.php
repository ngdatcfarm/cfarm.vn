<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Home;
use PDO;
use App\Domains\Intelligence\AlertService;
class HomeController
{
    public function __construct(private PDO $pdo) {}
    public function index(array $vars): void
    {
        $total_barns = (int)$this->pdo->query("SELECT COUNT(*) FROM barns")->fetchColumn();
        $stmt = $this->pdo->query("
            SELECT c.*, b.name AS barn_name,
                   DATEDIFF(CURDATE(), c.start_date) + 1 AS day_age,
                   (SELECT SUM(quantity) FROM care_deaths WHERE cycle_id = c.id) AS total_deaths,
                   (SELECT fcr_cumulative FROM cycle_daily_snapshots
                    WHERE cycle_id = c.id ORDER BY day_age DESC LIMIT 1) AS latest_fcr,
                   (SELECT avg_weight_g FROM cycle_daily_snapshots
                    WHERE cycle_id = c.id AND avg_weight_g IS NOT NULL
                    ORDER BY day_age DESC LIMIT 1) AS latest_avg_weight
            FROM cycles c
            JOIN barns b ON c.barn_id = b.id
            WHERE c.status = 'active'
            ORDER BY c.start_date DESC
        ");
        $active_cycles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $alert_svc = new AlertService($this->pdo);
        $alerts = [];
        foreach ($active_cycles as $c) {
            $cycle_alerts = $alert_svc->get_alerts((int)$c['id']);
            foreach ($cycle_alerts as $a) {
                $alerts[] = array_merge($a, [
                    'cycle_id'   => $c['id'],
                    'cycle_code' => $c['code'],
                    'barn_name'  => $c['barn_name'],
                    'type'       => $a['severity'],
                ]);
            }
        }

        // Daily checklist: trạng thái ghi chép hôm nay cho mỗi cycle active
        $daily_checklist = [];
        $today = date('Y-m-d');
        foreach ($active_cycles as $c) {
            $cid = (int)$c['id'];

            // Feeds sáng/chiều
            $feed_stmt = $this->pdo->prepare("
                SELECT HOUR(recorded_at) AS h FROM care_feeds
                WHERE cycle_id = :cid AND DATE(recorded_at) = :today
            ");
            $feed_stmt->execute([':cid' => $cid, ':today' => $today]);
            $feed_hours = $feed_stmt->fetchAll(PDO::FETCH_COLUMN);
            $has_morning_feed = !empty(array_filter($feed_hours, fn($h) => (int)$h < 12));
            $has_evening_feed = !empty(array_filter($feed_hours, fn($h) => (int)$h >= 12));

            // Deaths
            $death_count = (int)$this->pdo->prepare("
                SELECT COALESCE(SUM(quantity),0) FROM care_deaths WHERE cycle_id = :cid AND DATE(recorded_at) = :today
            ")->execute([':cid' => $cid, ':today' => $today]) ? 0 : 0;
            $death_stmt = $this->pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM care_deaths WHERE cycle_id = :cid AND DATE(recorded_at) = :today");
            $death_stmt->execute([':cid' => $cid, ':today' => $today]);
            $death_count = (int)$death_stmt->fetchColumn();

            // Trough checks (cho feeds hôm nay)
            $trough_stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM feed_trough_checks ftc
                JOIN care_feeds cf ON ftc.ref_feed_id = cf.id
                WHERE cf.cycle_id = :cid AND DATE(cf.recorded_at) = :today
            ");
            $trough_stmt->execute([':cid' => $cid, ':today' => $today]);
            $trough_count = (int)$trough_stmt->fetchColumn();
            $feed_count = count($feed_hours);

            // Medications
            $med_stmt = $this->pdo->prepare("SELECT COUNT(*) FROM care_medications WHERE cycle_id = :cid AND DATE(recorded_at) = :today");
            $med_stmt->execute([':cid' => $cid, ':today' => $today]);
            $med_count = (int)$med_stmt->fetchColumn();

            $daily_checklist[$cid] = [
                'has_morning_feed' => $has_morning_feed,
                'has_evening_feed' => $has_evening_feed,
                'feed_count'       => $feed_count,
                'trough_count'     => $trough_count,
                'trough_pending'   => max(0, $feed_count - $trough_count),
                'death_count'      => $death_count,
                'med_count'        => $med_count,
            ];
        }

        // Thông báo thiết bị gần đây (5 cái mới nhất)
        $device_notifications = $this->pdo->query("
            SELECT * FROM push_notifications_log
            ORDER BY sent_at DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_OBJ);

        // Tổng số thông báo chưa đọc hôm nay
        $notif_today = (int)$this->pdo->query("
            SELECT COUNT(*) FROM push_notifications_log
            WHERE DATE(sent_at) = CURDATE()
        ")->fetchColumn();

        require view_path('home/home_index.php');
    }

    public function notifications(array $vars): void
    {
        $notifications = $this->pdo->query("
            SELECT * FROM push_notifications_log
            ORDER BY sent_at DESC
            LIMIT 100
        ")->fetchAll(PDO::FETCH_OBJ);

        require view_path('home/notifications.php');
    }
}
