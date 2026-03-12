<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\IoT;
use PDO;

class CurtainSetupController
{
    public function __construct(private PDO $pdo) {}

    // GET /iot/curtains/setup
    public function setup(array $vars): void
    {
        $barns = $this->pdo->query("
            SELECT b.*,
                   COUNT(cc.id) as curtain_count,
                   (SELECT code FROM cycles WHERE barn_id = b.id AND status = 'active' LIMIT 1) as active_cycle
            FROM barns b
            LEFT JOIN curtain_configs cc ON cc.barn_id = b.id
            GROUP BY b.id ORDER BY b.name
        ")->fetchAll(PDO::FETCH_OBJ);

        // Relay devices chưa dùng hết kênh
        $relay_devices = $this->pdo->query("
            SELECT d.*,  b.name as barn_name,
                   COUNT(dc.id) as total_ch,
                   SUM(CASE WHEN cc_up.id IS NOT NULL OR cc_dn.id IS NOT NULL THEN 1 ELSE 0 END) as used_ch
            FROM devices d
            LEFT JOIN barns b ON b.id = d.barn_id
            LEFT JOIN device_channels dc ON dc.device_id = d.id
            LEFT JOIN curtain_configs cc_up ON cc_up.up_channel_id = dc.id
            LEFT JOIN curtain_configs cc_dn ON cc_dn.down_channel_id = dc.id
            WHERE d.device_type = 'relay_board'
            GROUP BY d.id
            ORDER BY b.name, d.name
        ")->fetchAll(PDO::FETCH_OBJ);

        $error   = $_GET['error']  ?? null;
        $saved   = $_GET['saved']  ?? null;
        $barn_id = (int)($_GET['barn_id'] ?? 0) ?: null;

        // Pre-load curtains cho từng barn
        $curtains_by_barn = [];
        foreach ($barns as $b) {
            $stmt = $this->pdo->prepare("
                SELECT cc.*, dcu.channel_number as up_ch, dcd.channel_number as dn_ch,
                       dup.device_code as relay_code
                FROM curtain_configs cc
                LEFT JOIN device_channels dcu ON dcu.id = cc.up_channel_id
                LEFT JOIN device_channels dcd ON dcd.id = cc.down_channel_id
                LEFT JOIN devices dup ON dup.id = dcu.device_id
                WHERE cc.barn_id = :bid ORDER BY cc.id
            ");
            $stmt->execute([':bid' => $b->id]);
            $curtains_by_barn[$b->id] = $stmt->fetchAll(PDO::FETCH_OBJ);
        }

        require view_path('iot/curtain_setup.php');
    }

    // POST /iot/curtains/store — tạo 4 bạt tự động
    public function store(array $vars): void
    {
        $barn_id   = (int)($_POST['barn_id']   ?? 0);
        $device_id = (int)($_POST['device_id'] ?? 0);
        $names     = $_POST['curtain_names'] ?? [];
        $full_up   = (float)($_POST['full_up_seconds']   ?? 60);
        $full_dn   = (float)($_POST['full_down_seconds'] ?? 55);

        if (!$barn_id || !$device_id || count($names) !== 4) {
            header('Location: /iot/curtains/setup?error=missing_fields');
            return;
        }

        // Lấy channels của device theo thứ tự
        $channels = $this->pdo->prepare("
            SELECT * FROM device_channels
            WHERE device_id = :did
            AND id NOT IN (
                SELECT up_channel_id FROM curtain_configs WHERE up_channel_id IS NOT NULL
                UNION
                SELECT down_channel_id FROM curtain_configs WHERE down_channel_id IS NOT NULL
            )
            ORDER BY channel_number ASC
        ");
        $channels->execute([':did' => $device_id]);
        $channels = $channels->fetchAll(PDO::FETCH_OBJ);

        if (count($channels) < 8) {
            header('Location: /iot/curtains/setup?error=not_enough_channels&device_id=' . $device_id);
            return;
        }

        // Tạo 4 bạt — mỗi bạt dùng 2 channel liên tiếp
        $this->pdo->beginTransaction();
        try {
            // Cập nhật tên channels cho đúng
            for ($i = 0; $i < 4; $i++) {
                $up_ch = $channels[$i * 2];
                $dn_ch = $channels[$i * 2 + 1];
                $name  = $names[$i];

                // Update channel names
                $this->pdo->prepare("
                    UPDATE device_channels SET 
                        name = :name, channel_type = 'curtain_up'
                    WHERE id = :id
                ")->execute([':name' => $name . ' - Lên', ':id' => $up_ch->id]);

                $this->pdo->prepare("
                    UPDATE device_channels SET 
                        name = :name, channel_type = 'curtain_down'
                    WHERE id = :id
                ")->execute([':name' => $name . ' - Xuống', ':id' => $dn_ch->id]);

                // Tạo curtain_config
                $this->pdo->prepare("
                    INSERT INTO curtain_configs (
                        name, barn_id, up_channel_id, down_channel_id,
                        full_up_seconds, full_down_seconds,
                        current_position_pct, moving_state, created_at
                    ) VALUES (
                        :name, :barn_id, :up_ch, :dn_ch,
                        :full_up, :full_dn,
                        0, 'idle', NOW()
                    )
                ")->execute([
                    ':name'    => $name,
                    ':barn_id' => $barn_id,
                    ':up_ch'   => $up_ch->id,
                    ':dn_ch'   => $dn_ch->id,
                    ':full_up' => $full_up,
                    ':full_dn' => $full_dn,
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            header('Location: /iot/curtains/setup?error=db_error');
            return;
        }

        header('Location: /iot/curtains/setup?saved=1&barn_id=' . $barn_id);
    }

    // GET /iot/curtains/{id}/edit
    public function edit(array $vars): void
    {
        $id = (int)($vars['id'] ?? 0);
        $curtain = $this->pdo->prepare("
            SELECT cc.*, b.name as barn_name,
                   dcu.device_id as up_device_id,
                   dup.device_code as up_device_code,
                   dcu.channel_number as up_ch_num,
                   dcd.channel_number as dn_ch_num
            FROM curtain_configs cc
            JOIN barns b ON b.id = cc.barn_id
            LEFT JOIN device_channels dcu ON dcu.id = cc.up_channel_id
            LEFT JOIN device_channels dcd ON dcd.id = cc.down_channel_id
            LEFT JOIN devices dup ON dup.id = dcu.device_id
            WHERE cc.id = :id
        ");
        $curtain->execute([':id' => $id]);
        $curtain = $curtain->fetch(PDO::FETCH_OBJ);
        if (!$curtain) { http_response_code(404); echo 'Not found'; return; }

        $saved = $_GET['saved'] ?? null;
        require view_path('iot/curtain_edit.php');
    }

    // POST /iot/curtains/{id}/update
    public function update(array $vars): void
    {
        $id = (int)($vars['id'] ?? 0);
        $this->pdo->prepare("
            UPDATE curtain_configs SET
                name             = :name,
                full_up_seconds  = :up,
                full_down_seconds= :dn
            WHERE id = :id
        ")->execute([
            ':name' => trim($_POST['name'] ?? ''),
            ':up'   => (float)($_POST['full_up_seconds']   ?? 60),
            ':dn'   => (float)($_POST['full_down_seconds'] ?? 55),
            ':id'   => $id,
        ]);
        header('Location: /iot/curtains/' . $id . '/edit?saved=1');
    }

    // POST /iot/curtains/{id}/delete
    public function delete(array $vars): void
    {
        $id = (int)($vars['id'] ?? 0);
        $c = $this->pdo->prepare("SELECT barn_id FROM curtain_configs WHERE id=:id");
        $c->execute([':id' => $id]);
        $c = $c->fetch(PDO::FETCH_OBJ);
        $this->pdo->prepare("DELETE FROM curtain_configs WHERE id=:id")->execute([':id' => $id]);
        header('Location: /iot/curtains/setup?barn_id=' . ($c->barn_id ?? ''));
    }
}
