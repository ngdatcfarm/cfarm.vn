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
        $error   = $_GET['error']  ?? null;
        $saved   = $_GET['saved']  ?? null;
        $barn_id = (int)($_GET['barn_id'] ?? 0) ?: null;
        $device_id = (int)($_GET['device_id'] ?? 0) ?: null;

        // Step 1: Get barns that have 8-channel relay devices
        $stmt = $this->pdo->query("
            SELECT b.id, b.name
            FROM barns b
            WHERE EXISTS (
                SELECT 1 FROM devices d
                JOIN device_channels dc ON dc.device_id = d.id
                WHERE d.barn_id = b.id AND d.device_type = 'relay_board'
                GROUP BY d.id
                HAVING COUNT(dc.id) >= 8
            )
            ORDER BY b.name
        ");
        $barns_with_relays = $stmt->fetchAll(PDO::FETCH_OBJ);

        // Get device info for each barn
        foreach ($barns_with_relays as $b) {
            $dev_stmt = $this->pdo->prepare("
                SELECT d.id, d.name as device_name,
                       COUNT(dc.id) as total_ch,
                       (SELECT COUNT(*) FROM curtain_configs cc WHERE cc.barn_id = :barn_id) as curtain_count,
                       (SELECT COUNT(*) FROM device_channels dc2
                        JOIN curtain_configs cc ON cc.up_channel_id = dc2.id OR cc.down_channel_id = dc2.id
                        WHERE dc2.device_id = d.id) as used_ch
                FROM devices d
                LEFT JOIN device_channels dc ON dc.device_id = d.id
                WHERE d.barn_id = :barn_id AND d.device_type = 'relay_board'
                GROUP BY d.id
                HAVING COUNT(dc.id) >= 8
                LIMIT 1
            ");
            $dev_stmt->execute(array(':barn_id' => $b->id));
            $dev = $dev_stmt->fetch(PDO::FETCH_OBJ);
            if ($dev) {
                $b->device_id = $dev->id;
                $b->device_name = $dev->device_name;
                $b->relay_name = $dev->device_name;
                $b->total_ch = $dev->total_ch;
                $b->used_ch = $dev->used_ch;
                $b->curtain_count = $dev->curtain_count;
            }
        }

        // Get relay devices for dropdown
        $relay_devices = $this->pdo->query("
            SELECT d.*, b.name as barn_name,
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

        // Get device channels with pins if device selected
        $device_channels = array();
        $selected_up = array();
        $selected_down = array();
        $barn_name = '';

        if ($device_id) {
            // Get channels
            $stmt = $this->pdo->prepare("
                SELECT dc.*, d.device_code, d.name as device_name
                FROM device_channels dc
                JOIN devices d ON d.id = dc.device_id
                WHERE dc.device_id = :device_id
                ORDER BY dc.channel_number
            ");
            $stmt->execute([':device_id' => $device_id]);
            $device_channels = $stmt->fetchAll(PDO::FETCH_OBJ);

            // Get barn_name
            $stmt = $this->pdo->prepare("SELECT b.name FROM barns b JOIN devices d ON d.barn_id = b.id WHERE d.id = :id");
            $stmt->execute([':id' => $device_id]);
            $barn_name = $stmt->fetchColumn();

            // Get existing curtain assignments
            if ($barn_id) {
                $curtain_stmt = $this->pdo->prepare("
                    SELECT cc.up_channel_id, cc.down_channel_id
                    FROM curtain_configs cc
                    WHERE cc.barn_id = :barn_id
                ");
                $curtain_stmt->execute([':barn_id' => $barn_id]);
                $existing = $curtain_stmt->fetchAll(PDO::FETCH_OBJ);
                foreach ($existing as $c) {
                    $selected_up[] = $c->up_channel_id;
                    $selected_down[] = $c->down_channel_id;
                }
            }
        }

        // Get all barns for displaying current curtains
        $barns = $this->pdo->query("
            SELECT b.*,
                   (SELECT code FROM cycles WHERE barn_id = b.id AND status = 'active' LIMIT 1) as active_cycle
            FROM barns b
            ORDER BY b.name
        ")->fetchAll(PDO::FETCH_OBJ);

        // Pre-load curtains cho từng barn
        $curtains_by_barn = array();
        foreach ($barns as $b) {
            $stmt = $this->pdo->prepare("
                SELECT cc.*, dcu.channel_number as up_ch, dcd.channel_number as dn_ch
                FROM curtain_configs cc
                LEFT JOIN device_channels dcu ON dcu.id = cc.up_channel_id
                LEFT JOIN device_channels dcd ON dcd.id = cc.down_channel_id
                WHERE cc.barn_id = :bid ORDER BY cc.id
            ");
            $stmt->execute([':bid' => $b->id]);
            $curtains_by_barn[$b->id] = $stmt->fetchAll(PDO::FETCH_OBJ);
        }

        // Get used channels count per device
        $device_used_ch = array();
        foreach ($relay_devices as $d) {
            $device_used_ch[$d->id] = $d->used_ch;
        }

        // Pass variables to view
        extract(get_defined_vars());
        require view_path('iot/curtain_setup.php');
    }

    // POST /iot/curtains/visual-save
    public function visual_save(array $vars): void
    {
        $barn_id = (int)($_POST['barn_id'] ?? 0);
        $device_id = (int)($_POST['device_id'] ?? 0);
        $pairs = $_POST['pairs'] ?? array();

        if (!$barn_id || !$device_id || empty($pairs)) {
            header('Location: /iot/curtains/setup?error=missing_fields');
            exit;
        }

        // Xóa các bạt cũ của barn này
        $this->pdo->prepare("
            DELETE cc FROM curtain_configs cc
            WHERE cc.barn_id = :barn_id
        ")->execute([':barn_id' => $barn_id]);

        // Thêm các cặp mới
        foreach ($pairs as $i => $pair) {
            $up_id = (int)($pair['up'] ?? 0);
            $dn_id = (int)($pair['down'] ?? 0);
            if ($up_id && $dn_id) {
                $this->pdo->prepare("
                    INSERT INTO curtain_configs (name, barn_id, up_channel_id, down_channel_id, full_up_seconds, full_down_seconds)
                    VALUES (:name, :barn_id, :up, :down, 30, 30)
                ")->execute([
                    ':name' => 'Bạt ' . ($i + 1),
                    ':barn_id' => $barn_id,
                    ':up' => $up_id,
                    ':down' => $dn_id
                ]);
            }
        }

        header('Location: /iot/curtains/setup?barn_id=' . $barn_id . '&saved=1');
        exit;
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
