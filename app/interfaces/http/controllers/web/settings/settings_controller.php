<?php
/**
 * Settings Controller - Cloud Notifications + Bat config
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

    /**
     * POST /settings/iot/bat/set-device - Assign ESP32 device to all bats in a barn
     * Body: barn_id, device_id
     */
    public function bat_set_device(array $vars): void
    {
        $barnId = $_POST['barn_id'] ?? null;
        $deviceId = $_POST['device_id'] ?? null;

        if (!$barnId || !$deviceId) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Missing barn_id or device_id']);
            exit;
        }

        $stmt = $this->pdo->prepare("UPDATE bats SET device_id = :dev_id WHERE barn_id = :barn_id");
        $stmt->execute([':dev_id' => (int)$deviceId, ':barn_id' => $barnId]);

        http_response_code(200);
        echo json_encode(['ok' => true, 'message' => 'Device assigned to all bats in barn']);
        exit;
    }

    /**
     * POST /settings/iot/bat/update-channel - Update up or down relay channel for a bat
     * Body: bat_id, up_channel or down_channel
     */
    public function bat_update_channel(array $vars): void
    {
        $batId = $_POST['bat_id'] ?? null;
        $upChannel = isset($_POST['up_channel']) ? (int)$_POST['up_channel'] : null;
        $downChannel = isset($_POST['down_channel']) ? (int)$_POST['down_channel'] : null;

        if (!$batId) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Missing bat_id']);
            exit;
        }

        $fields = [];
        $params = [':bat_id' => (int)$batId];
        if ($upChannel !== null) {
            $fields[] = 'up_relay_channel = :up_channel';
            $params[':up_channel'] = $upChannel;
        }
        if ($downChannel !== null) {
            $fields[] = 'down_relay_channel = :down_channel';
            $params[':down_channel'] = $downChannel;
        }

        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'No channel fields to update']);
            exit;
        }

        $sql = "UPDATE bats SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :bat_id";
        $this->pdo->prepare($sql)->execute($params);

        http_response_code(200);
        echo json_encode(['ok' => true, 'message' => 'Channel updated']);
        exit;
    }
}
