<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Home;

use PDO;

/**
 * Home Dashboard Controller - Cloud Remote Control
 *
 * Simplified to show only IoT device status and quick controls.
 */
class HomeController
{
    public function __construct(private PDO $pdo) {}

    public function index(array $vars): void
    {
        // Device stats
        $device_count = (int)$this->pdo->query("SELECT COUNT(*) FROM devices")->fetchColumn();
        $online_count = (int)$this->pdo->query("SELECT COUNT(*) FROM devices WHERE is_online = 1")->fetchColumn();

        // Recent notifications (last 10)
        $recent_notifications = $this->pdo->query("
            SELECT * FROM push_notifications_log
            ORDER BY sent_at DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_OBJ);

        // All devices with status
        $devices = $this->pdo->query("
            SELECT d.*, b.name as barn_name, dt.name as type_name
            FROM devices d
            LEFT JOIN barns b ON b.id = d.barn_id
            LEFT JOIN device_types dt ON dt.id = d.device_type_id
            ORDER BY d.is_online DESC, d.name ASC
        ")->fetchAll(PDO::FETCH_OBJ);

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
