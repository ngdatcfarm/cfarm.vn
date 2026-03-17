<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\IoT;

use PDO;
use App\Domains\IoT\MqttService;

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
     * Tính vị trí thực tế của bạt dựa trên thời gian đã chạy
     */
    private function calculateRealPosition(object|array $c): int
    {
        $c = (object)$c;
        if ($c->moving_state === 'idle' || !$c->moving_started_at) {
            return (int)$c->current_position_pct;
        }

        $elapsed = time() - strtotime($c->moving_started_at);
        $duration = (float)$c->moving_duration_seconds;

        if ($duration <= 0) return (int)$c->current_position_pct;

        // Tỷ lệ đã chạy (0.0 - 1.0)
        $ratio = min(1.0, $elapsed / $duration);

        $from = (int)$c->current_position_pct;
        $to   = (int)$c->moving_target_pct;
        $diff = $to - $from;

        $real = $from + (int)round($diff * $ratio);
        return max(0, min(100, $real));
    }

    /**
    /**
     * GET /iot/devices
     */
    public function index(array $vars): void
    {
        $devices = $this->pdo->query("
            SELECT d.*, b.name as barn_name, dt.name as type_name, dt.device_class
            FROM devices d
            LEFT JOIN barns b ON b.id = d.barn_id
            LEFT JOIN device_types dt ON dt.id = d.device_type_id
            ORDER BY b.name, d.name
        ")->fetchAll(\PDO::FETCH_OBJ);

        // Gắn channels + state cho mỗi device
        foreach ($devices as $device) {
            $ch_stmt = $this->pdo->prepare("
                SELECT dc.*, ds.state
                FROM device_channels dc
                LEFT JOIN device_states ds ON ds.channel_id = dc.id
                WHERE dc.device_id = :id
                ORDER BY dc.channel_number
            ");
            $ch_stmt->execute([':id' => $device->id]);
            $device->channels = $ch_stmt->fetchAll(\PDO::FETCH_OBJ);
        }

        $device_types = $this->pdo->query("SELECT * FROM device_types ORDER BY name")->fetchAll(\PDO::FETCH_OBJ);
        $barns = $this->pdo->query("SELECT * FROM barns ORDER BY number")->fetchAll(\PDO::FETCH_OBJ);

        require view_path('iot/devices.php');
    }

    /**
     * GET /iot/barn/{barn_id}/curtains
     */
    public function barn_curtains(array $vars): void
    {
        $barn_id = (int)$vars['barn_id'];
        $curtains = $this->pdo->prepare("
            SELECT cc.*,
                   uc.device_id as up_device_id, uc.channel_number as up_channel,
                   dc.device_id as down_device_id, dc.channel_number as down_channel,
                   ud.mqtt_topic as up_mqtt_topic, dd.mqtt_topic as down_mqtt_topic,
                   ud.is_online as up_device_online, dd.is_online as down_device_online
            FROM curtain_configs cc
            JOIN device_channels uc ON uc.id = cc.up_channel_id
            JOIN device_channels dc ON dc.id = cc.down_channel_id
            JOIN devices ud ON ud.id = uc.device_id
            JOIN devices dd ON dd.id = dc.device_id
            WHERE cc.barn_id = :barn_id
            ORDER BY cc.name
        ");
        $curtains->execute([':barn_id' => $barn_id]);
        $this->json(['ok' => true, 'curtains' => $curtains->fetchAll()]);
    }

    /**
     * Lấy curtain config đầy đủ
     */
    private function getCurtainFull(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT cc.*,
                   COALESCE(uc.channel_number, 1) as up_channel,
                   COALESCE(dc.channel_number, 2) as down_channel,
                   COALESCE(ud.mqtt_topic, '') as up_mqtt_topic,
                   COALESCE(dd.mqtt_topic, '') as down_mqtt_topic,
                   COALESCE(uc.device_id, 0) as up_device_id,
                   COALESCE(dc.device_id, 0) as down_device_id,
                   COALESCE(uc.id, 0) as up_channel_id,
                   COALESCE(dc.id, 0) as down_channel_id
            FROM curtain_configs cc
            LEFT JOIN device_channels uc ON uc.id = cc.up_channel_id
            LEFT JOIN device_channels dc ON dc.id = cc.down_channel_id
            LEFT JOIN devices ud ON ud.id = uc.device_id
            LEFT JOIN devices dd ON dd.id = dc.device_id
            WHERE cc.id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Dừng relay đang chạy (nếu có) và cập nhật position thực tế
     */
    private function stopCurrentMovement(array $c): int
    {
        $realPos = $this->calculateRealPosition($c);

        if ($c['moving_state'] !== 'idle') {
            // Stop relay đang chạy
            if ($c['moving_state'] === 'moving_up') {
                $this->mqtt->sendStop($c['up_mqtt_topic'], (int)$c['up_channel']);
            } else {
                $this->mqtt->sendStop($c['down_mqtt_topic'], (int)$c['down_channel']);
            }
        }

        // Cập nhật position thực tế và reset moving state
        $this->pdo->prepare("
            UPDATE curtain_configs
            SET current_position_pct = :pos,
                moving_state = 'idle',
                moving_target_pct = NULL,
                moving_started_at = NULL,
                moving_duration_seconds = NULL
            WHERE id = :id
        ")->execute([':pos' => $realPos, ':id' => $c['id']]);

        return $realPos;
    }

    /**
     * POST /iot/curtain/{id}/move
     */
    public function curtain_move(array $vars): void
    {
        $id = (int)$vars['id'];
        $target_pct = max(0, min(100, (int)($_POST['target_pct'] ?? 0)));

        $c = $this->getCurtainFull($id);
        if (!$c) $this->json(['ok' => false, 'message' => 'Curtain not found'], 404);

        // 1. Dừng lệnh cũ (nếu đang chạy) và lấy position thực tế
        $currentPos = $this->stopCurrentMovement($c);

        // 2. Nếu đã ở đúng vị trí
        $diff = $target_pct - $currentPos;
        if ($diff == 0) {
            $this->json(['ok' => true, 'message' => 'Already at position', 'position' => $currentPos, 'duration' => 0]);
        }

        // 3. Tính hướng và thời gian
        if ($diff > 0) {
            // Xuống (mở bạt) — position tăng
            $duration = abs($diff) / 100 * (float)$c['full_down_seconds'];
            $channel  = (int)$c['down_channel'];
            $mqtt     = $c['down_mqtt_topic'];
            $dir      = 'down';
            $mstate   = 'moving_down';
        } else {
            // Lên (đóng bạt) — position giảm
            $duration = abs($diff) / 100 * (float)$c['full_up_seconds'];
            $channel  = (int)$c['up_channel'];
            $mqtt     = $c['up_mqtt_topic'];
            $dir      = 'up';
            $mstate   = 'moving_up';
        }

        // 4. Gửi lệnh MQTT
        $sent = $this->mqtt->sendCurtainMove($mqtt, $channel, $dir, $duration);
        if (!$sent) {
            $this->json(['ok' => false, 'message' => 'MQTT send failed']);
        }

        // 5. Lưu trạng thái moving (CHƯA cập nhật position — đợi hoàn thành)
        $this->pdo->prepare("
            UPDATE curtain_configs
            SET moving_state = :mstate,
                moving_target_pct = :target,
                moving_started_at = NOW(),
                moving_duration_seconds = :dur
            WHERE id = :id
        ")->execute([
            ':mstate' => $mstate,
            ':target' => $target_pct,
            ':dur'    => round($duration, 1),
            ':id'     => $id,
        ]);

        // 6. Log command
        $cycle_id = $this->getActiveCycleId((int)$c['barn_id']);
        $this->pdo->prepare("
            INSERT INTO device_commands (device_id, channel_id, curtain_config_id, command_type, payload_json, source, barn_id, cycle_id)
            VALUES (:did, :chid, :cid, 'curtain_move', :payload, 'manual', :barn_id, :cycle_id)
        ")->execute([
            ':did'      => $dir === 'up' ? $c['up_device_id'] : $c['down_device_id'],
            ':chid'     => $dir === 'up' ? $c['up_channel_id'] : $c['down_channel_id'],
            ':cid'      => $id,
            ':payload'  => json_encode(['from' => $currentPos, 'to' => $target_pct, 'dir' => $dir, 'duration' => round($duration, 1)]),
            ':barn_id'  => $c['barn_id'],
            ':cycle_id' => $cycle_id,
        ]);

        // 7. Log state: bắt đầu di chuyển
        $c["moving_state_new"] = $mstate;
        $this->logState($c, "on", $currentPos, $id);

        $this->json([
            'ok'        => true,
            'position'  => $currentPos,
            'target'    => $target_pct,
            'direction' => $dir,
            'duration'  => round($duration, 1),
        ]);
    }

    /**
     * POST /iot/curtain/{id}/stop
     */
    public function curtain_stop(array $vars): void
    {
        $id = (int)$vars['id'];
        $c = $this->getCurtainFull($id);
        if (!$c) $this->json(['ok' => false, 'message' => 'Not found'], 404);

        $realPos = $this->stopCurrentMovement($c);


        // Log state: dừng
        $c["moving_state_new"] = "idle";
        $this->logState($c, "off", $realPos, $id);
        $this->json(['ok' => true, 'message' => 'Stopped', 'position' => $realPos]);
    }

    /**
     * GET /iot/control/{barn_id}
     */
    /**
     * GET /iot/control — dashboard tất cả barns
     */
    public function control_all(array $vars): void
    {
        $barns = $this->pdo->query("SELECT * FROM barns ORDER BY number")->fetchAll(\PDO::FETCH_OBJ);

        $all_curtains = [];
        foreach ($barns as $barn) {
            $stmt = $this->pdo->prepare("
                SELECT cc.*,
                       COALESCE(uc.channel_number, 1) as up_channel,
                       COALESCE(dc.channel_number, 2) as down_channel,
                       COALESCE(ud.is_online, 0) as up_online,
                       COALESCE(dd.is_online, 0) as down_online,
                       COALESCE(ud.device_code, '') as up_device_code,
                       COALESCE(dd.device_code, '') as down_device_code,
                FROM curtain_configs cc
                LEFT JOIN device_channels uc ON uc.id = cc.up_channel_id
                LEFT JOIN device_channels dc ON dc.id = cc.down_channel_id
                LEFT JOIN devices ud ON ud.id = uc.device_id
                LEFT JOIN devices dd ON dd.id = dc.device_id
                WHERE cc.barn_id = :barn_id
                ORDER BY cc.id
            ");
            $stmt->execute([':barn_id' => $barn->id]);
            $curtains = $stmt->fetchAll(\PDO::FETCH_OBJ);
            foreach ($curtains as $cur) {
                $cur->real_position = $this->calculateRealPosition($cur);
                $cur->barn_name = $barn->name;
            }
            $all_curtains[$barn->id] = $curtains;
        }

        require view_path('iot/control_all.php');
    }

    public function control_page(array $vars): void
    {
        $barn_id = (int)$vars['barn_id'];
        $barn = $this->pdo->prepare("SELECT * FROM barns WHERE id=:id");
        $barn->execute([':id' => $barn_id]);
        $barn = $barn->fetch(\PDO::FETCH_OBJ);
        if (!$barn) { http_response_code(404); echo 'Barn not found'; exit; }

        $stmt = $this->pdo->prepare("
            SELECT cc.*,
                   COALESCE(uc.channel_number, 1) as up_channel,
                   COALESCE(dc.channel_number, 2) as down_channel,
                   COALESCE(ud.is_online, 0) as up_online,
                   COALESCE(dd.is_online, 0) as down_online,
                   COALESCE(ud.device_code, '') as up_device_code,
                   COALESCE(dd.device_code, '') as down_device_code
            FROM curtain_configs cc
            LEFT JOIN device_channels uc ON uc.id = cc.up_channel_id
            LEFT JOIN device_channels dc ON dc.id = cc.down_channel_id
            LEFT JOIN devices ud ON ud.id = uc.device_id
            LEFT JOIN devices dd ON dd.id = dc.device_id
            WHERE cc.barn_id = :barn_id
            ORDER BY cc.id
        ");
        $stmt->execute([':barn_id' => $barn_id]);
        $curtains = $stmt->fetchAll(\PDO::FETCH_OBJ);

        // Tính real position cho mỗi curtain
        foreach ($curtains as $cur) {
            $cur->real_position = $this->calculateRealPosition($cur);
        }

        require view_path('iot/control.php');
    }

    /**
     * Ghi log trạng thái vào device_state_log
     */
    private function logState(array $c, string $state, int $position_pct, ?int $curtain_config_id = null): void
    {
        $barn_id = (int)($c['barn_id'] ?? 0);
        $cycle_id = $this->getActiveCycleId($barn_id);
        $device_id = (int)($c['up_device_id'] ?? $c['down_device_id'] ?? 0);
        $channel_id = null;
        if ($state === 'on' && isset($c['up_channel_id'])) {
            $channel_id = $c['moving_state_new'] === 'moving_up' ? (int)$c['up_channel_id'] : (int)$c['down_channel_id'];
        }

        $this->pdo->prepare("
            INSERT INTO device_state_log (device_id, channel_id, curtain_config_id, state, position_pct, barn_id, cycle_id)
            VALUES (:did, :chid, :cid, :state, :pos, :barn_id, :cycle_id)
        ")->execute([
            ':did'      => $device_id,
            ':chid'     => $channel_id,
            ':cid'      => $curtain_config_id,
            ':state'    => $state,
            ':pos'      => $position_pct,
            ':barn_id'  => $barn_id,
            ':cycle_id' => $cycle_id,
        ]);
    }

    private function getActiveCycleId(int $barn_id): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM cycles WHERE barn_id=:bid AND status='active' LIMIT 1");
        $stmt->execute([':bid' => $barn_id]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }

    // GET /iot/sensor/{id}
    public function sensor_show(array $vars): void
    {
        $id = (int)$vars['id'];

        // Device info
        $device = $this->pdo->prepare("
            SELECT d.*, b.name as barn_name, dt.name as type_name, dt.device_class
            FROM devices d
            LEFT JOIN barns b ON b.id = d.barn_id
            LEFT JOIN device_types dt ON dt.id = d.device_type_id
            WHERE d.id = :id
        ");
        $device->execute([':id' => $id]);
        $device = $device->fetch(\PDO::FETCH_OBJ);
        if (!$device) { http_response_code(404); echo 'Not found'; return; }

        // Reading mới nhất
        $latest = $this->pdo->prepare("
            SELECT * FROM sensor_readings WHERE device_id = :id
            ORDER BY recorded_at DESC LIMIT 1
        ");
        $latest->execute([':id' => $id]);
        $latest = $latest->fetch(\PDO::FETCH_OBJ);

        // 24h gần nhất — group theo giờ
        $hourly = $this->pdo->prepare("
            SELECT
                DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00:00') as hour,
                ROUND(AVG(temperature), 1) as avg_temp,
                ROUND(AVG(humidity), 1) as avg_hum,
                ROUND(MIN(temperature), 1) as min_temp,
                ROUND(MAX(temperature), 1) as max_temp,
                COUNT(*) as readings
            FROM sensor_readings
            WHERE device_id = :id
              AND recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY hour
            ORDER BY hour ASC
        ");
        $hourly->execute([':id' => $id]);
        $hourly = $hourly->fetchAll(\PDO::FETCH_OBJ);

        // 7 ngày gần nhất — group theo ngày
        $daily = $this->pdo->prepare("
            SELECT
                DATE(recorded_at) as day,
                ROUND(AVG(temperature), 1) as avg_temp,
                ROUND(AVG(humidity), 1) as avg_hum,
                ROUND(MIN(temperature), 1) as min_temp,
                ROUND(MAX(temperature), 1) as max_temp,
                COUNT(*) as readings
            FROM sensor_readings
            WHERE device_id = :id
              AND recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY day
            ORDER BY day ASC
        ");
        $daily->execute([':id' => $id]);
        $daily = $daily->fetchAll(\PDO::FETCH_OBJ);

        // Tổng số readings
        $total_readings = (int)$this->pdo->prepare("
            SELECT COUNT(*) FROM sensor_readings WHERE device_id = :id
        ")->execute([':id' => $id]) ? $this->pdo->prepare("
            SELECT COUNT(*) FROM sensor_readings WHERE device_id = :id
        ")->execute([':id' => $id]) : 0;
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sensor_readings WHERE device_id = :id");
        $stmt->execute([':id' => $id]);
        $total_readings = (int)$stmt->fetchColumn();

        require view_path('iot/sensor_show.php');
    }
}
