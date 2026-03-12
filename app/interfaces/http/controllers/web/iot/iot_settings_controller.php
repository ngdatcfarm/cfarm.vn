<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\IoT;

use PDO;

class IoTSettingsController
{
    public function __construct(private PDO $pdo) {}

    private function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    // ================================================================
    // GET /settings/iot  — trang chính, tab: curtains | devices | types
    // ================================================================
    public function index(array $vars): void
    {
        $tab = $_GET['tab'] ?? 'curtains';

        // Curtain configs
        $curtains = $this->pdo->query("
            SELECT cc.*,
                   b.name as barn_name,
                   uc.channel_number as up_ch,
                   dc.channel_number as down_ch,
                   ud.device_code as up_device_code,
                   dd.device_code as down_device_code
            FROM curtain_configs cc
            LEFT JOIN barns b ON b.id = cc.barn_id
            LEFT JOIN device_channels uc ON uc.id = cc.up_channel_id
            LEFT JOIN device_channels dc ON dc.id = cc.down_channel_id
            LEFT JOIN devices ud ON ud.id = uc.device_id
            LEFT JOIN devices dd ON dd.id = dc.device_id
            ORDER BY b.name, cc.name
        ")->fetchAll(PDO::FETCH_OBJ);

        // Devices
        $devices = $this->pdo->query("
            SELECT d.*, b.name as barn_name, dt.name as type_name, dt.device_class, d.env_interval_seconds
            FROM devices d
            LEFT JOIN barns b ON b.id = d.barn_id
            LEFT JOIN device_types dt ON dt.id = d.device_type_id
            ORDER BY b.name, d.name
        ")->fetchAll(PDO::FETCH_OBJ);

        // Device types
        $device_types = $this->pdo->query("
            SELECT * FROM device_types ORDER BY name
        ")->fetchAll(PDO::FETCH_OBJ);

        // Barns & channels (cho form)
        $barns    = $this->pdo->query("SELECT * FROM barns ORDER BY number")->fetchAll(PDO::FETCH_OBJ);
        $channels = $this->pdo->query("
            SELECT dc.*, d.device_code, d.barn_id, b.name as barn_name
            FROM device_channels dc
            JOIN devices d ON d.id = dc.device_id
            LEFT JOIN barns b ON b.id = d.barn_id
            ORDER BY b.name, d.device_code, dc.channel_number
        ")->fetchAll(PDO::FETCH_OBJ);

        require view_path('iot/settings_hub.php');
    }

    // ================================================================
    // CURTAIN CRUD
    // ================================================================
    public function curtain_store(array $vars): void
    {
        $this->pdo->prepare("
            INSERT INTO curtain_configs (name, barn_id, up_channel_id, down_channel_id, full_up_seconds, full_down_seconds)
            VALUES (:name, :barn_id, :up, :down, :up_s, :down_s)
        ")->execute([
            ':name'    => trim($_POST['name'] ?? ''),
            ':barn_id' => (int)($_POST['barn_id'] ?? 0),
            ':up'      => (int)($_POST['up_channel_id'] ?? 0),
            ':down'    => (int)($_POST['down_channel_id'] ?? 0),
            ':up_s'    => (float)($_POST['full_up_seconds'] ?? 30),
            ':down_s'  => (float)($_POST['full_down_seconds'] ?? 30),
        ]);
        if ($this->isAjax()) $this->json(['ok' => true, 'id' => (int)$this->pdo->lastInsertId()]);
        header('Location: /settings/iot?tab=curtains'); exit;
    }

    public function curtain_update(array $vars): void
    {
        $this->pdo->prepare("
            UPDATE curtain_configs
            SET name=:name, barn_id=:barn_id, up_channel_id=:up, down_channel_id=:down,
                full_up_seconds=:up_s, full_down_seconds=:down_s
            WHERE id=:id
        ")->execute([
            ':name'    => trim($_POST['name'] ?? ''),
            ':barn_id' => (int)($_POST['barn_id'] ?? 0),
            ':up'      => (int)($_POST['up_channel_id'] ?? 0),
            ':down'    => (int)($_POST['down_channel_id'] ?? 0),
            ':up_s'    => (float)($_POST['full_up_seconds'] ?? 30),
            ':down_s'  => (float)($_POST['full_down_seconds'] ?? 30),
            ':id'      => (int)$vars['id'],
        ]);
        if ($this->isAjax()) $this->json(['ok' => true]);
        header('Location: /settings/iot?tab=curtains'); exit;
    }

    public function curtain_delete(array $vars): void
    {
        $id = (int)$vars['id'];
        $row = $this->pdo->prepare("SELECT barn_id FROM curtain_configs WHERE id=:id");
        $row->execute([':id' => $id]);
        $barn_id = (int)($row->fetchColumn() ?: 0);
        $this->pdo->prepare("DELETE FROM curtain_configs WHERE id=:id")->execute([':id' => $id]);
        $affected = $barn_id ? $this->rebuildFirmwareInterlocks($barn_id) : [];
        $firmware_links = array_map(fn($d) => '/settings/iot/firmware/' . $d->id, $affected);
        if ($this->isAjax()) $this->json(['ok' => true, 'firmware_updated' => !empty($affected), 'firmware_links' => $firmware_links]);
        $flash = !empty($affected) ? '?firmware_updated=1' : '';
        header('Location: /settings/iot?tab=curtains' . $flash); exit;
    }

    // ================================================================
    // DEVICE CRUD
    // ================================================================
    public function device_store(array $vars): void
    {
        $type_id = (int)($_POST['device_type_id'] ?? 0);
        $type = $type_id ? $this->pdo->prepare("SELECT * FROM device_types WHERE id=:id") : null;
        if ($type) { $type->execute([':id' => $type_id]); $type = $type->fetch(PDO::FETCH_OBJ); }

        $total_ch = $type ? (int)$type->total_channels : (int)($_POST['total_channels'] ?? 8);
        $barn_id  = (int)($_POST['barn_id'] ?? 0);

        // Tạo mqtt_topic tự động nếu không nhập
        $mqtt_topic = trim($_POST['mqtt_topic'] ?? '');
        if (!$mqtt_topic && $barn_id) {
            $barn = $this->pdo->prepare("SELECT number FROM barns WHERE id=:id");
            $barn->execute([':id' => $barn_id]);
            $b = $barn->fetch();
            $mqtt_topic = $b ? 'cfarm/barn' . $b['number'] : 'cfarm/device';
        }

        $this->pdo->prepare("
            INSERT INTO devices (device_code, name, barn_id, device_type, device_type_id, total_channels, mqtt_topic, notes, is_online)
            VALUES (:code, :name, :barn_id, :dtype, :dtype_id, :total_ch, :mqtt, :notes, 0)
        ")->execute([
            ':code'     => trim($_POST['device_code'] ?? ''),
            ':name'     => trim($_POST['name'] ?? ''),
            ':barn_id'  => $barn_id ?: null,
            ':dtype'    => $type ? ($type->device_class === 'relay' ? 'relay_board' : ($type->device_class === 'sensor' ? 'sensor' : 'relay_board')) : 'relay_board',
            ':dtype_id' => $type_id ?: null,
            ':total_ch' => $total_ch,
            ':mqtt'     => $mqtt_topic,
            ':notes'    => trim($_POST['notes'] ?? ''),
        ]);

        $device_id = (int)$this->pdo->lastInsertId();

        // Tự động tạo channels theo số kênh
        if ($total_ch > 0) {
            for ($i = 1; $i <= $total_ch; $i++) {
                $this->pdo->prepare("
                    INSERT INTO device_channels (device_id, channel_number, name, channel_type, is_active, sort_order)
                    VALUES (:did, :ch, :name, 'other', 1, :sort)
                ")->execute([
                    ':did'  => $device_id,
                    ':ch'   => $i,
                    ':name' => 'Kênh ' . $i,
                    ':sort' => $i,
                ]);
            }
        }

        if ($this->isAjax()) $this->json(['ok' => true, 'id' => $device_id]);
        header('Location: /settings/iot?tab=devices'); exit;
    }

    public function device_update(array $vars): void
    {
        $this->pdo->prepare("
            UPDATE devices
            SET device_code=:code, name=:name, barn_id=:barn_id,
                device_type_id=:dtype_id, mqtt_topic=:mqtt, notes=:notes
            WHERE id=:id
        ")->execute([
            ':code'    => trim($_POST['device_code'] ?? ''),
            ':name'    => trim($_POST['name'] ?? ''),
            ':barn_id' => (int)($_POST['barn_id'] ?? 0) ?: null,
            ':dtype_id'=> (int)($_POST['device_type_id'] ?? 0) ?: null,
            ':mqtt'    => trim($_POST['mqtt_topic'] ?? ''),
            ':notes'   => trim($_POST['notes'] ?? ''),
            ':id'      => (int)$vars['id'],
        ]);
        if ($this->isAjax()) $this->json(['ok' => true]);
        header('Location: /settings/iot?tab=devices'); exit;
    }

    public function device_delete(array $vars): void
    {
        $id = (int)$vars['id'];
        $this->pdo->prepare("DELETE FROM device_channels WHERE device_id=:id")->execute([':id' => $id]);
        $this->pdo->prepare("DELETE FROM devices WHERE id=:id")->execute([':id' => $id]);
        if ($this->isAjax()) $this->json(['ok' => true]);
        header('Location: /settings/iot?tab=devices'); exit;
    }

    // ================================================================
    // DEVICE TYPE CRUD
    // ================================================================
    public function type_store(array $vars): void
    {
        $this->pdo->prepare("
            INSERT INTO device_types (name, description, device_class, total_channels, firmware_template, mqtt_protocol)
            VALUES (:name, :desc, :class, :total_ch, :fw, :proto)
        ")->execute([
            ':name'     => trim($_POST['name'] ?? ''),
            ':desc'     => trim($_POST['description'] ?? ''),
            ':class'    => $_POST['device_class'] ?? 'relay',
            ':total_ch' => (int)($_POST['total_channels'] ?? 0),
            ':fw'       => $_POST['firmware_template'] ?? '',
            ':proto'    => $_POST['mqtt_protocol'] ?? '',
        ]);
        if ($this->isAjax()) $this->json(['ok' => true, 'id' => (int)$this->pdo->lastInsertId()]);
        header('Location: /settings/iot?tab=types'); exit;
    }

    public function type_update(array $vars): void
    {
        $this->pdo->prepare("
            UPDATE device_types
            SET name=:name, description=:desc, device_class=:class,
                total_channels=:total_ch, firmware_template=:fw, mqtt_protocol=:proto
            WHERE id=:id
        ")->execute([
            ':name'     => trim($_POST['name'] ?? ''),
            ':desc'     => trim($_POST['description'] ?? ''),
            ':class'    => $_POST['device_class'] ?? 'relay',
            ':total_ch' => (int)($_POST['total_channels'] ?? 0),
            ':fw'       => $_POST['firmware_template'] ?? '',
            ':proto'    => $_POST['mqtt_protocol'] ?? '',
            ':id'       => (int)$vars['id'],
        ]);
        if ($this->isAjax()) $this->json(['ok' => true]);
        header('Location: /settings/iot?tab=types'); exit;
    }

    public function type_delete(array $vars): void
    {
        $this->pdo->prepare("DELETE FROM device_types WHERE id=:id")->execute([':id' => (int)$vars['id']]);
        if ($this->isAjax()) $this->json(['ok' => true]);
        header('Location: /settings/iot?tab=types'); exit;
    }

    // ================================================================
    // FIRMWARE — GET /settings/iot/firmware/{device_id}
    // ================================================================
    public function firmware_code(array $vars): void
    {
        $device_id = (int)$vars['device_id'];
        $device = $this->pdo->prepare("
            SELECT d.*, b.name as barn_name, dt.firmware_template, dt.mqtt_protocol, dt.name as type_name
            FROM devices d
            LEFT JOIN barns b ON b.id = d.barn_id
            LEFT JOIN device_types dt ON dt.id = d.device_type_id
            WHERE d.id = :id
        ");
        $device->execute([':id' => $device_id]);
        $device = $device->fetch(PDO::FETCH_OBJ);
        if (!$device) { http_response_code(404); echo 'Device not found'; exit; }

        $channels = $this->pdo->prepare("
            SELECT * FROM device_channels WHERE device_id=:id ORDER BY channel_number
        ");
        $channels->execute([':id' => $device_id]);
        $channels = $channels->fetchAll(PDO::FETCH_OBJ);

        $curtains = $this->pdo->prepare("
            SELECT cc.name,
                   uc.channel_number as up_ch, dc.channel_number as down_ch
            FROM curtain_configs cc
            JOIN device_channels uc ON uc.id = cc.up_channel_id
            JOIN device_channels dc ON dc.id = cc.down_channel_id
            WHERE uc.device_id = :id OR dc.device_id = :id2
        ");
        $curtains->execute([':id' => $device_id, ':id2' => $device_id]);
        $curtains = $curtains->fetchAll(PDO::FETCH_OBJ);

        require view_path('iot/firmware.php');
    }

    // ================================================================
    // FIRMWARE INTERLOCK AUTO-REBUILD
    // Gọi sau mỗi curtain store/update/delete
    // Trả về array devices bị ảnh hưởng (để hiện toast)
    // ================================================================
    private function rebuildFirmwareInterlocks(int $barn_id): array
    {
        // Lấy tất cả curtain pairs của barn này
        $pairs = $this->pdo->prepare("
            SELECT cc.name,
                   uc.channel_number as up_ch,
                   dc.channel_number as down_ch,
                   uc.device_id as up_device_id,
                   dc.device_id as down_device_id
            FROM curtain_configs cc
            JOIN device_channels uc ON uc.id = cc.up_channel_id
            JOIN device_channels dc ON dc.id = cc.down_channel_id
            WHERE cc.barn_id = :barn_id
            ORDER BY cc.id
        ");
        $pairs->execute([':barn_id' => $barn_id]);
        $curtains = $pairs->fetchAll(\PDO::FETCH_OBJ);

        if (empty($curtains)) return [];

        // Build interlock string: {{1,2},{3,4},...}
        $pairs_arr = array_map(fn($c) => '{' . $c->up_ch . ',' . $c->down_ch . '}', $curtains);
        $pairs_str  = implode(',', $pairs_arr);
        $pairs_count = count($curtains);

        // Lấy tất cả devices của barn này có firmware_template
        $devices = $this->pdo->prepare("
            SELECT d.id, d.device_code, d.mqtt_topic, dt.firmware_template, dt.id as type_id
            FROM devices d
            JOIN device_types dt ON dt.id = d.device_type_id
            WHERE d.barn_id = :barn_id
              AND dt.firmware_template IS NOT NULL
              AND dt.firmware_template != ''
        ");
        $devices->execute([':barn_id' => $barn_id]);
        $affected_devices = $devices->fetchAll(\PDO::FETCH_OBJ);

                // Không ghi DB — interlock thay runtime trong firmware.php
        return $affected_devices;
    }

    // ================================================================
    // DEVICE TYPE EDITOR PAGES
    // ================================================================

    // GET /settings/iot/types
    public function types_index(array $vars): void
    {
        $device_types = $this->pdo->query("
            SELECT dt.*,
                   COUNT(d.id) as device_count
            FROM device_types dt
            LEFT JOIN devices d ON d.device_type_id = dt.id
            GROUP BY dt.id
            ORDER BY dt.name
        ")->fetchAll(\PDO::FETCH_OBJ);

        // Mặc định chọn type đầu tiên
        $selected_id = (int)($_GET['id'] ?? ($device_types[0]->id ?? 0));
        $selected = null;
        foreach ($device_types as $dt) {
            if ($dt->id === $selected_id) { $selected = $dt; break; }
        }

        require view_path('iot/types_editor.php');
    }

    // GET /settings/iot/types/{id}  — AJAX lấy data type
    public function type_show(array $vars): void
    {
        $dt = $this->pdo->prepare("SELECT * FROM device_types WHERE id=:id");
        $dt->execute([':id' => (int)$vars['id']]);
        $dt = $dt->fetch(\PDO::FETCH_OBJ);
        if (!$dt) { $this->json(['ok' => false], 404); }
        $this->json(['ok' => true, 'type' => $dt]);
    }

    // POST /settings/iot/types/{id}/save — AJAX lưu firmware + protocol
    public function type_save(array $vars): void
    {
        $id = (int)$vars['id'];
        $field = $_POST['field'] ?? 'firmware_template';

        if ($field === 'firmware_template') {
            $this->pdo->prepare("UPDATE device_types SET firmware_template=:val WHERE id=:id")
                ->execute([':val' => $_POST['value'] ?? '', ':id' => $id]);
        } elseif ($field === 'mqtt_protocol') {
            $this->pdo->prepare("UPDATE device_types SET mqtt_protocol=:val WHERE id=:id")
                ->execute([':val' => $_POST['value'] ?? '', ':id' => $id]);
        } elseif ($field === 'meta') {
            $this->pdo->prepare("
                UPDATE device_types SET name=:name, description=:desc,
                device_class=:class, total_channels=:ch WHERE id=:id
            ")->execute([
                ':name' => trim($_POST['name'] ?? ''),
                ':desc' => trim($_POST['description'] ?? ''),
                ':class'=> $_POST['device_class'] ?? 'relay',
                ':ch'   => (int)($_POST['total_channels'] ?? 0),
                ':id'   => $id,
            ]);
        }

        $this->json(['ok' => true, 'saved_at' => date('H:i:s')]);
    }

    // POST /settings/iot/device/{id}/toggle-alert
    public function device_toggle_alert(array $vars): void
    {
        $id = (int)$vars['id'];
        $this->pdo->prepare("
            UPDATE devices SET alert_offline = NOT alert_offline WHERE id = :id
        ")->execute([':id' => $id]);

        $row = $this->pdo->prepare("SELECT alert_offline FROM devices WHERE id=:id");
        $row->execute([':id' => $id]);
        $val = (int)$row->fetchColumn();

        if ($this->isAjax()) $this->json(['ok' => true, 'alert_offline' => $val]);
        header('Location: /settings/iot?tab=devices'); exit;
    }

    // GET /iot/nodes/create — form tạo node mới
    public function node_create(array $vars): void
    {
        $device_types = $this->pdo->query("
            SELECT * FROM device_types ORDER BY device_class, name
        ")->fetchAll(PDO::FETCH_OBJ);

        $barns = $this->pdo->query("
            SELECT b.*, c.code as active_cycle, c.id as cycle_id
            FROM barns b
            LEFT JOIN cycles c ON c.barn_id = b.id AND c.status = 'active'
            ORDER BY b.name
        ")->fetchAll(PDO::FETCH_OBJ);

        $saved         = $_GET['saved']     ?? null;
        $new_device_id = $_GET['device_id'] ?? null;
        $error         = $_GET['error']     ?? null;
        $dup_code      = $_GET['code']      ?? null;

        require view_path('iot/node_create.php');
    }

    // POST /iot/nodes/store — lưu node mới
    public function node_store(array $vars): void
    {
        $type_id  = (int)($_POST['device_type_id'] ?? 0);
        $barn_id  = (int)($_POST['barn_id'] ?? 0) ?: null;
        $name     = trim($_POST['name'] ?? '');
        $code     = trim($_POST['device_code'] ?? '');
        $chip     = $_POST['chip_type'] ?? 'esp8266';
        $location = trim($_POST['location_note'] ?? '');
        $notes    = trim($_POST['notes'] ?? '');
        $interval = (int)($_POST['env_interval_seconds'] ?? 300);

        if (!$code || !$name || !$type_id) {
            header('Location: /iot/nodes/create?error=missing_fields');
            return;
        }

        $exists = $this->pdo->prepare("SELECT id FROM devices WHERE device_code=:code");
        $exists->execute([':code' => $code]);
        if ($exists->fetch()) {
            header('Location: /iot/nodes/create?error=duplicate_code&code=' . urlencode($code));
            return;
        }

        $type = $this->pdo->prepare("SELECT * FROM device_types WHERE id=:id");
        $type->execute([':id' => $type_id]);
        $type = $type->fetch(PDO::FETCH_OBJ);

        $mqtt_topic = 'cfarm/' . $code;
        if ($barn_id) {
            $mqtt_topic = 'cfarm/barn' . $barn_id;
        }

        $sensor_fields = null;
        if ($type && $type->mqtt_protocol) {
            $proto = json_decode($type->mqtt_protocol, true);
            if (isset($proto['fields'])) {
                $sensor_fields = json_encode($proto['fields']);
            }
        }

        $device_type_enum = match($type->device_class ?? '') {
            'relay'  => 'relay_board',
            'sensor' => 'sensor',
            default  => 'other',
        };

        $this->pdo->prepare("
            INSERT INTO devices (
                name, device_code, barn_id, device_type_id, chip_type,
                device_type, total_channels, mqtt_topic,
                env_interval_seconds, sensor_fields,
                location_note, notes, is_online, created_at
            ) VALUES (
                :name, :code, :barn_id, :type_id, :chip,
                :dtype, :channels, :topic,
                :interval, :fields,
                :location, :notes, 0, NOW()
            )
        ")->execute([
            ':name'     => $name,
            ':code'     => $code,
            ':barn_id'  => $barn_id,
            ':type_id'  => $type_id,
            ':chip'     => $chip,
            ':dtype'    => $device_type_enum,
            ':channels' => $type->total_channels ?? 0,
            ':topic'    => $mqtt_topic,
            ':interval' => $interval,
            ':fields'   => $sensor_fields,
            ':location' => $location ?: null,
            ':notes'    => $notes ?: null,
        ]);

        $new_id = $this->pdo->lastInsertId();
        header('Location: /iot/nodes/create?saved=1&device_id=' . $new_id);
    }


    // GET /iot/nodes/{id}/edit
    public function node_edit(array $vars): void
    {
        $id = (int)$vars['id'];
        $device = $this->pdo->prepare("
            SELECT d.*, b.name as barn_name, dt.name as type_name, dt.device_class
            FROM devices d
            LEFT JOIN barns b ON b.id = d.barn_id
            LEFT JOIN device_types dt ON dt.id = d.device_type_id
            WHERE d.id = :id
        ");
        $device->execute([':id' => $id]);
        $device = $device->fetch(PDO::FETCH_OBJ);
        if (!$device) { http_response_code(404); echo 'Not found'; return; }

        $barns = $this->pdo->query("
            SELECT b.*, 
                (SELECT code FROM cycles WHERE barn_id=b.id AND status='active' LIMIT 1) as active_cycle
            FROM barns b ORDER BY b.name
        ")->fetchAll(PDO::FETCH_OBJ);

        $device_types = $this->pdo->query("
            SELECT * FROM device_types ORDER BY device_class, name
        ")->fetchAll(PDO::FETCH_OBJ);

        $saved = $_GET['saved'] ?? null;
        $error = $_GET['error'] ?? null;

        require view_path('iot/node_edit.php');
    }

    // POST /iot/nodes/{id}/update
    public function node_update(array $vars): void
    {
        $id       = (int)$vars['id'];
        $name     = trim($_POST['name'] ?? '');
        $barn_id  = (int)($_POST['barn_id'] ?? 0) ?: null;
        $type_id  = (int)($_POST['device_type_id'] ?? 0) ?: null;
        $chip     = $_POST['chip_type'] ?? 'esp8266';
        $location = trim($_POST['location_note'] ?? '');
        $notes    = trim($_POST['notes'] ?? '');
        $interval = (int)($_POST['env_interval_seconds'] ?? 300);
        $code     = trim($_POST['device_code'] ?? '');

        if (!$name || !$code) {
            header('Location: /iot/nodes/' . $id . '/edit?error=missing_fields');
            return;
        }

        // Kiểm tra device_code unique (trừ chính nó)
        $dup = $this->pdo->prepare("SELECT id FROM devices WHERE device_code=:code AND id!=:id");
        $dup->execute([':code' => $code, ':id' => $id]);
        if ($dup->fetch()) {
            header('Location: /iot/nodes/' . $id . '/edit?error=duplicate_code');
            return;
        }

        // Tính lại mqtt_topic nếu barn thay đổi
        $mqtt_topic = 'cfarm/' . $code;
        if ($barn_id) {
            $mqtt_topic = 'cfarm/barn' . $barn_id;
        }

        // Lấy device_class từ type
        $type = null;
        if ($type_id) {
            $t = $this->pdo->prepare("SELECT * FROM device_types WHERE id=:id");
            $t->execute([':id' => $type_id]);
            $type = $t->fetch(PDO::FETCH_OBJ);
        }
        $device_type_enum = match($type->device_class ?? '') {
            'relay'  => 'relay_board',
            'sensor' => 'sensor',
            default  => 'other',
        };

        $this->pdo->prepare("
            UPDATE devices SET
                name                 = :name,
                device_code          = :code,
                barn_id              = :barn_id,
                device_type_id       = :type_id,
                device_type          = :dtype,
                chip_type            = :chip,
                mqtt_topic           = :mqtt,
                env_interval_seconds = :interval,
                location_note        = :location,
                notes                = :notes
            WHERE id = :id
        ")->execute([
            ':name'     => $name,
            ':code'     => $code,
            ':barn_id'  => $barn_id,
            ':type_id'  => $type_id,
            ':dtype'    => $device_type_enum,
            ':chip'     => $chip,
            ':mqtt'     => $mqtt_topic,
            ':interval' => $interval,
            ':location' => $location ?: null,
            ':notes'    => $notes ?: null,
            ':id'       => $id,
        ]);

        header('Location: /iot/nodes/' . $id . '/edit?saved=1');
    }

    // POST /iot/nodes/{id}/delete
    public function node_delete(array $vars): void
    {
        $id = (int)$vars['id'];
        // Kiểm tra có curtain configs dùng device này không
        $used = $this->pdo->prepare("
            SELECT COUNT(*) FROM device_channels dc
            JOIN curtain_configs cc ON cc.up_channel_id=dc.id OR cc.down_channel_id=dc.id
            WHERE dc.device_id=:id
        ");
        $used->execute([':id' => $id]);
        if ($used->fetchColumn() > 0) {
            header('Location: /iot/nodes/' . $id . '/edit?error=has_curtains');
            return;
        }
        $this->pdo->prepare("DELETE FROM device_channels WHERE device_id=:id")->execute([':id' => $id]);
        $this->pdo->prepare("DELETE FROM sensor_readings WHERE device_id=:id")->execute([':id' => $id]);
        $this->pdo->prepare("DELETE FROM devices WHERE id=:id")->execute([':id' => $id]);
        header('Location: /iot/devices');
    }

}
