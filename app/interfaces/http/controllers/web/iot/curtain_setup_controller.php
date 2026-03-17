<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\IoT;
use PDO;
use stdClass;

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

        // Step 1: Get ALL barns that have relay_board devices (any channel count)
        $all_devices = $this->pdo->query("
            SELECT d.id, d.name, d.barn_id, d.device_type,
                   COUNT(dc.id) as channel_count
            FROM devices d
            LEFT JOIN device_channels dc ON dc.device_id = d.id
            WHERE d.device_type = 'relay_board' AND d.barn_id IS NOT NULL
            GROUP BY d.id
            ORDER BY d.name
        ")->fetchAll(PDO::FETCH_OBJ);

        // Get barns with relay_board devices (any channel count)
        $barns_with_relays = array();
        foreach ($all_devices as $d) {
            // Check if barn already added
            $found = false;
            foreach ($barns_with_relays as $b) {
                if ($b->id == $d->barn_id) {
                    $found = true;
                    break;
                }
            }
            if (!$found && $d->barn_id) {
                $barn_stmt = $this->pdo->prepare("SELECT name FROM barns WHERE id = :id");
                $barn_stmt->execute(array(':id' => $d->barn_id));
                $barn_name = $barn_stmt->fetchColumn();

                $b = new stdClass();
                $b->id = $d->barn_id;
                $b->name = $barn_name;
                $b->device_id = $d->id;
                $b->device_name = $d->name;
                $b->relay_name = $d->name;
                $b->total_ch = $d->channel_count;
                $b->used_ch = 0;
                $b->curtain_count = 0;
                $barns_with_relays[] = $b;
            }
        }

        // Get all relay_board devices
        $relay_devices = $this->pdo->query("
            SELECT d.*, b.name as barn_name,
                   COUNT(dc.id) as total_ch,
                   COALESCE(SUM(CASE WHEN cc_up.id IS NOT NULL OR cc_dn.id IS NOT NULL THEN 1 ELSE 0 END), 0) as used_ch
            FROM devices d
            LEFT JOIN barns b ON b.id = d.barn_id
            LEFT JOIN device_channels dc ON dc.device_id = d.id
            LEFT JOIN curtain_configs cc_up ON cc_up.up_channel_id = dc.id
            LEFT JOIN curtain_configs cc_dn ON cc_dn.down_channel_id = dc.id
            WHERE d.device_type = 'relay_board'
            GROUP BY d.id
            ORDER BY b.name, d.name
        ")->fetchAll(PDO::FETCH_OBJ);

        // Get device channels with pins
        $device_channels = array();
        $selected_up = array();
        $selected_down = array();
        $barn_name = '';

        // If barn_id is selected, get the relay device and its channels
        if ($barn_id && !$device_id) {
            // Find relay_board device for this barn
            $dev_stmt = $this->pdo->prepare("
                SELECT id, name FROM devices
                WHERE barn_id = :barn_id AND device_type = 'relay_board'
                LIMIT 1
            ");
            $dev_stmt->execute(array(':barn_id' => $barn_id));
            $relay_device = $dev_stmt->fetch(PDO::FETCH_OBJ);
            if ($relay_device) {
                $device_id = $relay_device->id;
            }
        }

        if ($device_id) {
            // Get channels for this device
            $stmt = $this->pdo->prepare("
                SELECT dc.*, d.device_code, d.name as device_name
                FROM device_channels dc
                JOIN devices d ON d.id = dc.device_id
                WHERE dc.device_id = :device_id
                ORDER BY dc.channel_number
            ");
            $stmt->execute([':device_id' => $device_id]);
            $device_channels = $stmt->fetchAll(PDO::FETCH_OBJ);

            // If no channels in DB, create default 8 channels with GPIO pins
            $default_gpio_pins = [32, 33, 25, 26, 27, 14, 12, 13];
            if (empty($device_channels)) {
                for ($i = 1; $i <= 8; $i++) {
                    $ch = new stdClass();
                    $ch->id = $i;
                    $ch->device_id = $device_id;
                    $ch->channel_number = $i;
                    $ch->gpio_pin = $default_gpio_pins[$i - 1] ?? null;
                    $device_channels[] = $ch;
                }
            } else {
                // Add GPIO pins to existing channels
                foreach ($device_channels as $ch) {
                    $idx = $ch->channel_number - 1;
                    $ch->gpio_pin = isset($default_gpio_pins[$idx]) ? $default_gpio_pins[$idx] : null;
                }
            }

            // Get barn_name
            $stmt = $this->pdo->prepare("SELECT b.name FROM barns b JOIN devices d ON d.barn_id = b.id WHERE d.id = :id");
            $stmt->execute([':id' => $device_id]);
            $barn_name = $stmt->fetchColumn();

            // Get existing curtain assignments
            if ($barn_id) {
                $curtain_stmt = $this->pdo->prepare("
                    SELECT cc.up_channel_id, cc.down_channel_id,
                           cc.full_up_seconds, cc.full_down_seconds
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
        $pdo = $this->pdo;
        extract(get_defined_vars());
        require view_path('iot/curtain_setup.php');
    }

    // POST /iot/curtains/visual-save
    public function visual_save(array $vars): void
    {
        $barn_id = (int)($_POST['barn_id'] ?? 0);
        $device_id = (int)($_POST['device_id'] ?? 0);
        $curtain_names = $_POST['curtain_names'] ?? array();
        $up_channel_ids = $_POST['up_channel_id'] ?? array();
        $down_channel_ids = $_POST['down_channel_id'] ?? array();
        $up_seconds = $_POST['up_seconds'] ?? array();
        $down_seconds = $_POST['down_seconds'] ?? array();

        if (!$barn_id || !$device_id || empty($curtain_names)) {
            header('Location: /iot/curtains/setup?error=missing_fields');
            exit;
        }

        // Xóa các bạt cũ của barn này
        $this->pdo->prepare("
            DELETE cc FROM curtain_configs cc
            WHERE cc.barn_id = :barn_id
        ")->execute([':barn_id' => $barn_id]);

        // Thêm mới 4 bạt
        for ($i = 0; $i < 4; $i++) {
            $name = isset($curtain_names[$i]) ? trim($curtain_names[$i]) : ('Bạt ' . ($i + 1));
            $up_id = isset($up_channel_ids[$i]) ? (int)$up_channel_ids[$i] : 0;
            $dn_id = isset($down_channel_ids[$i]) ? (int)$down_channel_ids[$i] : 0;
            $up_sec = isset($up_seconds[$i]) ? (float)$up_seconds[$i] : 30;
            $dn_sec = isset($down_seconds[$i]) ? (float)$down_seconds[$i] : 30;

            if ($up_id && $dn_id) {
                $this->pdo->prepare("
                    INSERT INTO curtain_configs (name, barn_id, up_channel_id, down_channel_id, full_up_seconds, full_down_seconds, current_position_pct, moving_state, created_at)
                    VALUES (:name, :barn_id, :up, :down, :up_s, :down_s, 0, 'idle', NOW())
                ")->execute([
                    ':name' => $name,
                    ':barn_id' => $barn_id,
                    ':up' => $up_id,
                    ':down' => $dn_id,
                    ':up_s' => $up_sec,
                    ':down_s' => $dn_sec
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
