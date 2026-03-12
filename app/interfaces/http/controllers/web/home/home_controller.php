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
