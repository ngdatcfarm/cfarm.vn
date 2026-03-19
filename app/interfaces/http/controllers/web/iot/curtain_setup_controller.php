<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\IoT;

use PDO;

/**
 * Curtain Setup Controller - Cấu hình bạt
 */
class CurtainSetupController
{
    public function __construct(private PDO $pdo) {}

    /**
     * GET /settings/iot/curtain/setup
     */
    public function setup(array $vars): void
    {
        $barn_id = (int)($_GET['barn_id'] ?? 0);
        $saved = $_GET['saved'] ?? null;
        $error = $_GET['error'] ?? null;

        // Lấy tất cả barns (đã có curtain vẫn hiển thị để xem/chỉnh sửa)
        $barns = $this->pdo->query("
            SELECT b.* FROM barns b
            ORDER BY b.number
        ")->fetchAll(PDO::FETCH_OBJ);

        // Lấy devices relay cho mỗi barn
        $relay_devices = [];
        $stmt = $this->pdo->query("
            SELECT d.id, d.name, d.barn_id, dt.name as type_name
            FROM devices d
            JOIN device_types dt ON dt.id = d.device_type_id
            WHERE dt.device_class = 'relay'
            ORDER BY d.name
        ");
        while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            $relay_devices[$row->barn_id][] = $row;
        }

        // Nếu chọn barn, lấy devices và channels
        $selected_barn = null;
        $device_channels = [];
        $curtains = [];
        
        if ($barn_id) {
            // Lấy thông tin barn
            $stmt = $this->pdo->prepare("SELECT * FROM barns WHERE id = ?");
            $stmt->execute([$barn_id]);
            $selected_barn = $stmt->fetch(PDO::FETCH_OBJ);

            // Lấy relay devices cho barn này
            $stmt = $this->pdo->prepare("
                SELECT d.* FROM devices d
                JOIN device_types dt ON dt.id = d.device_type_id
                WHERE d.barn_id = ? AND dt.device_class = 'relay'
            ");
            $stmt->execute([$barn_id]);
            $devices = $stmt->fetchAll(PDO::FETCH_OBJ);

            // Lấy channels cho mỗi device
            foreach ($devices as $dev) {
                $ch_stmt = $this->pdo->prepare("
                    SELECT * FROM device_channels 
                    WHERE device_id = ? ORDER BY channel_number
                ");
                $ch_stmt->execute([$dev->id]);
                $device_channels[$dev->id] = $ch_stmt->fetchAll(PDO::FETCH_OBJ);
            }

            // Lấy curtains đã cấu hình
            $curtain_stmt = $this->pdo->prepare("
                SELECT cc.*, 
                       uc.channel_number as up_ch, dc.channel_number as down_ch
                FROM curtain_configs cc
                LEFT JOIN device_channels uc ON uc.id = cc.up_channel_id
                LEFT JOIN device_channels dc ON dc.id = cc.down_channel_id
                WHERE cc.barn_id = ?
            ");
            $curtain_stmt->execute([$barn_id]);
            $curtains = $curtain_stmt->fetchAll(PDO::FETCH_OBJ);
        }

        require view_path('iot/curtain_setup.php');
    }

    /**
     * POST /settings/iot/curtain/store
     */
    public function store(array $vars): void
    {
        $barn_id = (int)($_POST['barn_id'] ?? 0);
        $device_id = (int)($_POST['device_id'] ?? 0);
        $curtain_name = trim($_POST['curtain_name'] ?? '');
        $up_channel_id = (int)($_POST['up_channel_id'] ?? 0);
        $down_channel_id = (int)($_POST['down_channel_id'] ?? 0);
        $up_seconds = (float)($_POST['up_seconds'] ?? 30);
        $down_seconds = (float)($_POST['down_seconds'] ?? 30);

        if (!$barn_id || !$device_id || !$curtain_name || !$up_channel_id || !$down_channel_id) {
            header('Location: /settings/iot/curtain/setup?barn_id=' . $barn_id . '&error=missing_fields');
            exit;
        }

        $this->pdo->prepare("
            INSERT INTO curtain_configs 
            (name, barn_id, device_id, up_channel_id, down_channel_id, full_up_seconds, full_down_seconds, current_position_pct, moving_state, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'idle', NOW())
        ")->execute([
            $curtain_name, $barn_id, $device_id, $up_channel_id, $down_channel_id, $up_seconds, $down_seconds
        ]);

        header('Location: /settings/iot/curtain/setup?barn_id=' . $barn_id . '&saved=1');
        exit;
    }

    /**
     * POST /settings/iot/curtain/{id}/delete
     */
    public function delete(array $vars): void
    {
        $id = (int)$vars['id'];

        // Lấy barn_id trước
        $stmt = $this->pdo->prepare("SELECT barn_id FROM curtain_configs WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $barn_id = $row ? $row['barn_id'] : 0;

        $this->pdo->prepare("DELETE FROM curtain_configs WHERE id = ?")->execute([$id]);

        header('Location: /settings/iot/curtain/setup?barn_id=' . $barn_id);
        exit;
    }

    /**
     * POST /settings/iot/curtain/visual-save - Lưu cấu hình bạt visual
     */
    public function visual_save(array $vars): void
    {
        $barn_id = (int)($_POST['barn_id'] ?? 0);
        $curtains = json_decode($_POST['curtains'] ?? '[]', true);

        if (!$barn_id || empty($curtains)) {
            header('Location: /settings/iot/curtain/setup?barn_id=' . $barn_id . '&error=invalid_data');
            exit;
        }

        // Xóa cấu hình cũ của barn này
        $this->pdo->prepare("DELETE FROM curtain_configs WHERE barn_id = ?")->execute([$barn_id]);

        // Thêm cấu hình mới
        $stmt = $this->pdo->prepare("
            INSERT INTO curtain_configs
            (name, barn_id, device_id, up_channel_id, down_channel_id, full_up_seconds, full_down_seconds, current_position_pct, moving_state, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'idle', NOW())
        ");

        foreach ($curtains as $c) {
            if (empty($c['name']) || empty($c['device_id']) || empty($c['up_channel']) || empty($c['down_channel'])) {
                continue;
            }

            $stmt->execute([
                $c['name'],
                $barn_id,
                (int)$c['device_id'],
                (int)$c['up_channel'],
                (int)$c['down_channel'],
                (float)($c['up_seconds'] ?? 30),
                (float)($c['down_seconds'] ?? 30),
            ]);
        }

        header('Location: /settings/iot/curtain/setup?barn_id=' . $barn_id . '&saved=1');
        exit;
    }
}
