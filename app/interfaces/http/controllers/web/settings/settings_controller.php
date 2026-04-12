<?php
/**
 * Settings Controller - Cloud Notifications only
 */
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Settings;

use PDO;

class SettingsController
{
    public function __construct(private PDO $pdo) {}

    /**
     * GET /settings/notifications - Push notification settings
     */
    public function notifications(array $vars): void
    {
        $settings = $this->pdo->query("SELECT * FROM notification_settings LIMIT 1")->fetch(PDO::FETCH_OBJ);
        require view_path('settings/notifications.php');
    }

    /**
     * POST /settings/notifications/update - Update notification settings
     */
    public function notifications_update(array $vars): void
    {
        $data = $_POST;
        $enabled = (int)(!empty($data['enabled']));
        $push_enabled = (int)(!empty($data['push_enabled']));

        $stmt = $this->pdo->prepare("
            INSERT INTO notification_settings (id, enabled, push_enabled, updated_at)
            VALUES (1, :enabled, :push_enabled, NOW())
            ON DUPLICATE KEY UPDATE
                enabled = VALUES(enabled),
                push_enabled = VALUES(push_enabled),
                updated_at = NOW()
        ");
        $stmt->execute([
            ':enabled' => $enabled,
            ':push_enabled' => $push_enabled,
        ]);

        header('Location: /settings/notifications?saved=1');
        exit;
    }
}
