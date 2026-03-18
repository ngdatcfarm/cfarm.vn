<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\IoT;

use App\Domains\IoT\Services\MqttService;
use PDO;

/**
 * IoT Device Controller
 */
class DeviceController
{
    private MqttService $mqtt;

    public function __construct(private PDO $pdo)
    {
        $this->mqtt = new MqttService();
    }

    private function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * GET /iot/devices - Danh sách thiết bị
     */
    public function index(array $vars): void
    {
        $devices = $this->pdo->query("
            SELECT d.*, b.name as barn_name, dt.name as type_name, dt.device_class
            FROM devices d
            LEFT JOIN barns b ON b.id = d.barn_id
            LEFT JOIN device_types dt ON dt.id = d.device_type_id
            ORDER BY b.name, d.name
        ")->fetchAll(PDO::FETCH_OBJ);

        // Lấy channels cho mỗi device
        foreach ($devices as $device) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM device_channels 
                WHERE device_id = :id ORDER BY channel_number
            ");
            $stmt->execute([':id' => $device->id]);
            $device->channels = $stmt->fetchAll(PDO::FETCH_OBJ);
        }

        $device_types = $this->pdo->query("SELECT * FROM device_types ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
        $barns = $this->pdo->query("SELECT * FROM barns ORDER BY number")->fetchAll(PDO::FETCH_OBJ);

        require view_path('iot/devices.php');
    }

    /**
     * GET /settings/iot - Settings page
     */
    public function settings(array $vars): void
    {
        $device_types = $this->pdo->query("SELECT * FROM device_types ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
        $barns = $this->pdo->query("SELECT * FROM barns ORDER BY number")->fetchAll(PDO::FETCH_OBJ);

        require view_path('iot/settings.php');
    }

    /**
     * POST /settings/iot/device/store - Thêm thiết bị mới
     */
    public function device_store(array $vars): void
    {
        $device_code = trim($_POST['device_code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $barn_id = (int)($_POST['barn_id'] ?? 0) ?: null;
        $device_type_id = (int)($_POST['device_type_id'] ?? 1);
        $mqtt_topic = trim($_POST['mqtt_topic'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!$device_code || !$name || !$mqtt_topic) {
            header('Location: /settings/iot?error=missing_fields');
            exit;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO devices (device_code, name, barn_id, device_type_id, mqtt_topic, notes, created_at)
            VALUES (:code, :name, :barn_id, :type_id, :mqtt, :notes, NOW())
        ");
        $stmt->execute([
            ':code'    => $device_code,
            ':name'    => $name,
            ':barn_id' => $barn_id,
            ':type_id' => $device_type_id,
            ':mqtt'    => $mqtt_topic,
            ':notes'   => $notes,
        ]);

        $device_id = (int)$this->pdo->lastInsertId();

        // Tự động tạo channels
        $type_stmt = $this->pdo->prepare("SELECT total_channels FROM device_types WHERE id = ?");
        $type_stmt->execute([$device_type_id]);
        $total_channels = (int)$type_stmt->fetchColumn();

        if ($total_channels > 0) {
            $default_gpio = [32, 33, 25, 26, 27, 14, 12, 13];
            for ($ch = 1; $ch <= $total_channels; $ch++) {
                $this->pdo->prepare("
                    INSERT INTO device_channels (device_id, channel_number, name, channel_type, gpio_pin, is_active, sort_order)
                    VALUES (:did, :ch, :name, 'other', :gpio, 1, :sort)
                ")->execute([
                    ':did'  => $device_id,
                    ':ch'   => $ch,
                    ':name' => 'Kênh ' . $ch,
                    ':gpio' => $default_gpio[$ch - 1] ?? null,
                    ':sort' => $ch,
                ]);
            }
        }

        header('Location: /settings/iot?tab=devices&saved=1');
        exit;
    }

    /**
     * POST /settings/iot/device/{id}/update
     */
    public function device_update(array $vars): void
    {
        $id = (int)$vars['id'];
        
        $stmt = $this->pdo->prepare("
            UPDATE devices SET
                device_code = :code,
                name = :name,
                barn_id = :barn_id,
                device_type_id = :type_id,
                mqtt_topic = :mqtt,
                notes = :notes
            WHERE id = :id
        ");
        $stmt->execute([
            ':code'    => trim($_POST['device_code'] ?? ''),
            ':name'    => trim($_POST['name'] ?? ''),
            ':barn_id' => (int)($_POST['barn_id'] ?? 0) ?: null,
            ':type_id' => (int)($_POST['device_type_id'] ?? 1),
            ':mqtt'    => trim($_POST['mqtt_topic'] ?? ''),
            ':notes'   => trim($_POST['notes'] ?? ''),
            ':id'      => $id,
        ]);

        header('Location: /settings/iot?tab=devices&saved=1');
        exit;
    }

    /**
     * POST /settings/iot/device/{id}/delete - Delete device and all related data
     */
    public function device_delete(array $vars): void
    {
        $id = (int)$vars['id'];
        
        // Get related IDs first
        $stmt = $this->pdo->prepare("SELECT id FROM device_channels WHERE device_id = ?");
        $stmt->execute([$id]);
        $channel_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $this->pdo->prepare("SELECT id FROM curtain_configs WHERE device_id = ?");
        $stmt->execute([$id]);
        $curtain_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Delete in correct order (child tables first)
        // 1. device_state_log
        if (!empty($channel_ids)) {
            $placeholders = implode(',', array_fill(0, count($channel_ids), '?'));
            $this->pdo->prepare("DELETE FROM device_state_log WHERE channel_id IN ($placeholders)")->execute($channel_ids);
        }
        if (!empty($curtain_ids)) {
            $placeholders = implode(',', array_fill(0, count($curtain_ids), '?'));
            $this->pdo->prepare("DELETE FROM device_state_log WHERE curtain_config_id IN ($placeholders)")->execute($curtain_ids);
        }
        
        // 2. device_states
        if (!empty($channel_ids)) {
            $placeholders = implode(',', array_fill(0, count($channel_ids), '?'));
            $this->pdo->prepare("DELETE FROM device_states WHERE channel_id IN ($placeholders)")->execute($channel_ids);
        }
        
        // 3. device_commands
        if (!empty($channel_ids)) {
            $placeholders = implode(',', array_fill(0, count($channel_ids), '?'));
            $this->pdo->prepare("DELETE FROM device_commands WHERE channel_id IN ($placeholders)")->execute($channel_ids);
        }
        
        // 4. curtain_configs (will cascade via FK but being explicit)
        $this->pdo->prepare("DELETE FROM curtain_configs WHERE device_id = ?")->execute([$id]);
        
        // 5. device_channels
        $this->pdo->prepare("DELETE FROM device_channels WHERE device_id = ?")->execute([$id]);
        
        // 6. device itself
        $this->pdo->prepare("DELETE FROM devices WHERE id = ?")->execute([':id' => $id]);
        
        // Use execute with array
        $this->pdo->prepare("DELETE FROM devices WHERE id = ?")->execute([$id]);
        
        header('Location: /settings/iot?tab=devices');
        exit;
    }

    /**
     * POST /settings/iot/type/store - Add new device type
     */
    public function type_store(array $vars): void
    {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $device_class = $_POST['device_class'] ?? 'relay';
        $total_channels = (int)($_POST['total_channels'] ?? 8);

        if (!$name) {
            header('Location: /settings/iot/firmwares?error=missing_fields');
            exit;
        }

        $this->pdo->prepare("
            INSERT INTO device_types (name, description, device_class, total_channels, is_active, created_at)
            VALUES (?, ?, ?, ?, 1, NOW())
        ")->execute([$name, $description, $device_class, $total_channels]);

        header('Location: /settings/iot/firmwares?saved=1');
        exit;
    }

    /**
     * POST /settings/iot/type/{id}/toggle - Toggle device type active status
     */
    public function type_toggle(array $vars): void
    {
        $id = (int)$vars['id'];
        
        $stmt = $this->pdo->prepare("UPDATE device_types SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);

        header('Location: /settings/iot/firmwares');
        exit;
    }

    /**
     * POST /settings/iot/type/{id}/delete - Delete device type
     */
    public function type_delete(array $vars): void
    {
        $id = (int)$vars['id'];
        
        // Check if there are devices using this type
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM devices WHERE device_type_id = ?");
        $stmt->execute([$id]);
        $device_count = (int)$stmt->fetchColumn();
        
        if ($device_count > 0) {
            header('Location: /settings/iot/firmwares?error=type_in_use');
            exit;
        }
        
        // Check if there are firmwares for this type
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM device_firmwares WHERE device_type_id = ?");
        $stmt->execute([$id]);
        $firmware_count = (int)$stmt->fetchColumn();
        
        if ($firmware_count > 0) {
            header('Location: /settings/iot/firmwares?error=type_has_firmware');
            exit;
        }
        
        // Delete the device type
        $stmt = $this->pdo->prepare("DELETE FROM device_types WHERE id = ?");
        $stmt->execute([$id]);

        header('Location: /settings/iot/firmwares');
        exit;
    }

    /**
     * POST /settings/iot/device/{id}/pins - Lưu GPIO pins
     */
    public function device_pins_save(array $vars): void
    {
        $device_id = (int)$vars['id'];
        $pins = json_decode(file_get_contents('php://input'), true);

        if (!is_array($pins)) {
            $this->json(['ok' => false, 'message' => 'Invalid data']);
        }

        $stmt = $this->pdo->prepare("
            UPDATE device_channels 
            SET gpio_pin = :pin, name = :name 
            WHERE id = :id AND device_id = :device_id
        ");

        foreach ($pins as $channel_id => $data) {
            $stmt->execute([
                ':pin'      => $data['gpio'] ?? null,
                ':name'     => $data['name'] ?? 'Kênh',
                ':id'       => (int)$channel_id,
                ':device_id' => $device_id,
            ]);
        }

        $this->json(['ok' => true, 'message' => 'Đã lưu GPIO pins']);
    }

    /**
     * POST /settings/iot/device/{id}/channels - Lưu channel config
     */
    public function device_channels_save(array $vars): void
    {
        $device_id = (int)$vars['id'];
        $channels = $_POST['channels'] ?? [];

        $stmt = $this->pdo->prepare("
            UPDATE device_channels 
            SET name = :name, channel_type = :type, max_on_seconds = :max_on, is_active = :active
            WHERE id = :id AND device_id = :device_id
        ");

        foreach ($channels as $ch_id => $ch_data) {
            $stmt->execute([
                ':name'    => $ch_data['name'] ?? 'Kênh',
                ':type'    => $ch_data['type'] ?? 'other',
                ':max_on'  => (int)($ch_data['max_on'] ?? 120),
                ':active'  => isset($ch_data['active']) ? 1 : 0,
                ':id'      => (int)$ch_id,
                ':device_id' => $device_id,
            ]);
        }

        $this->json(['ok' => true]);
    }

    /**
     * POST /settings/iot/device/{id}/test - Test gửi lệnh
     */
    public function device_test(array $vars): void
    {
        $id = (int)$vars['id'];
        
        $stmt = $this->pdo->prepare("SELECT * FROM devices WHERE id = ?");
        $stmt->execute([$id]);
        $device = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$device) {
            $this->json(['ok' => false, 'message' => 'Device not found'], 404);
        }

        // Gửi heartbeat request
        $result = $this->mqtt->publish($device->mqtt_topic . '/cmd', [
            'action' => 'ping',
            'ts' => time(),
        ]);

        $this->json(['ok' => $result, 'message' => $result ? 'Đã gửi lệnh test' : 'Gửi thất bại']);
    }
}
