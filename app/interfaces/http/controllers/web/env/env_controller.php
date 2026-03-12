<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Env;
use PDO;

class EnvController
{
    public function __construct(private PDO $pdo) {}

    // GET /env — dashboard overview tất cả barn
    public function index(array $vars): void
    {
        // Latest reading mỗi barn
        $barns = $this->pdo->query("
            SELECT
                b.id, b.name,
                e.temperature, e.humidity, e.heat_index,
                e.nh3_ppm, e.co2_ppm, e.light_lux,
                e.wind_speed_ms, e.is_raining,
                e.recorded_at,
                c.code as cycle_code, c.id as cycle_id,
                e.day_age,
                d.name as device_name, d.is_online,
                TIMESTAMPDIFF(MINUTE, e.recorded_at, NOW()) as minutes_ago
            FROM barns b
            LEFT JOIN (
                SELECT d2.* FROM devices d2
                JOIN device_types dt2 ON dt2.id = d2.device_type_id
                WHERE dt2.device_class = 'sensor'
                ORDER BY d2.id DESC
            ) d ON d.barn_id = b.id
            LEFT JOIN env_readings e ON e.id = (
                SELECT id FROM env_readings
                WHERE device_id = d.id
                ORDER BY recorded_at DESC LIMIT 1
            )
            LEFT JOIN cycles c ON c.barn_id = b.id AND c.status = 'active'
            ORDER BY b.name
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Cảnh báo ngưỡng hiện tại
        $alerts = [];
        foreach ($barns as $barn) {
            if (!$barn['recorded_at']) continue;
            $age = $barn['minutes_ago'] ?? 999;
            if ($age > 15) continue; // Chỉ alert nếu data mới trong 15 phút
            if ($barn['nh3_ppm']     > 25)   $alerts[] = ['barn'=>$barn['name'], 'type'=>'danger',  'msg'=>"NH3 {$barn['nh3_ppm']}ppm (ngưỡng >25ppm)"];
            if ($barn['co2_ppm']     > 3000) $alerts[] = ['barn'=>$barn['name'], 'type'=>'danger',  'msg'=>"CO2 {$barn['co2_ppm']}ppm (ngưỡng >3000ppm)"];
            if ($barn['temperature'] > 35)   $alerts[] = ['barn'=>$barn['name'], 'type'=>'warning', 'msg'=>"Nhiệt độ {$barn['temperature']}°C (ngưỡng >35°C)"];
            if ($barn['humidity']    > 85)   $alerts[] = ['barn'=>$barn['name'], 'type'=>'warning', 'msg'=>"Độ ẩm {$barn['humidity']}% (ngưỡng >85%)"];
            if ($barn['temperature'] < 20 && $barn['day_age'] <= 7)
                $alerts[] = ['barn'=>$barn['name'], 'type'=>'warning', 'msg'=>"Nhiệt thấp {$barn['temperature']}°C — gà con dưới 7 ngày tuổi"];
        }

        require view_path('env/env_index.php');
    }

    // GET /env/barn/{id} — chi tiết 1 barn: biểu đồ 24h + ENV vs FCR
    public function barn_show(array $vars): void
    {
        $barn_id = (int)($vars['id'] ?? 0);

        $barn = $this->pdo->prepare("SELECT * FROM barns WHERE id=:id");
        $barn->execute([':id' => $barn_id]);
        $barn = $barn->fetch(PDO::FETCH_ASSOC);
        if (!$barn) { http_response_code(404); echo 'Barn not found'; return; }

        // Active cycle
        $cycle = $this->pdo->prepare("
            SELECT * FROM cycles WHERE barn_id=:id AND status='active' ORDER BY start_date DESC LIMIT 1
        ");
        $cycle->execute([':id' => $barn_id]);
        $cycle = $cycle->fetch(PDO::FETCH_ASSOC);

        // ENV sensor device
        $device = $this->pdo->prepare("
            SELECT d.* FROM devices d
            JOIN device_types dt ON dt.id = d.device_type_id
            WHERE d.barn_id=:id AND dt.device_class='sensor'
            ORDER BY d.id DESC LIMIT 1
        ");
        $device->execute([':id' => $barn_id]);
        $device = $device->fetch(PDO::FETCH_ASSOC);

        // 24h ENV data (mỗi 5 phút → max 288 points)
        $env_24h = [];
        if ($device) {
            $env_24h = $this->pdo->prepare("
                SELECT
                    DATE_FORMAT(recorded_at, '%H:%i') as time_label,
                    recorded_at,
                    temperature, humidity, heat_index,
                    nh3_ppm, co2_ppm, light_lux,
                    wind_speed_ms, is_raining, day_age
                FROM env_readings
                WHERE device_id = :did
                  AND recorded_at >= NOW() - INTERVAL 24 HOUR
                ORDER BY recorded_at ASC
            ");
            $env_24h->execute([':did' => $device['id']]);
            $env_24h = $env_24h->fetchAll(PDO::FETCH_ASSOC);
        }

        // ENV vs FCR — nếu có cycle: join daily_snapshots với avg ENV cùng ngày
        $env_fcr = [];
        if ($cycle) {
            $env_fcr = $this->pdo->prepare("
                SELECT
                    s.snapshot_date,
                    s.day_age,
                    s.fcr_cumulative,
                    s.avg_weight_g,
                    s.feed_consumed_kg,
                    AVG(e.temperature) as avg_temp,
                    AVG(e.humidity)    as avg_humidity,
                    AVG(e.nh3_ppm)     as avg_nh3,
                    AVG(e.co2_ppm)     as avg_co2,
                    AVG(e.light_lux)   as avg_lux,
                    MIN(e.temperature) as min_temp,
                    MAX(e.temperature) as max_temp
                FROM cycle_daily_snapshots s
                LEFT JOIN env_readings e
                    ON e.cycle_id = :cid
                    AND DATE(e.recorded_at) = s.snapshot_date
                WHERE s.cycle_id = :cid2
                GROUP BY s.snapshot_date, s.day_age, s.fcr_cumulative, s.avg_weight_g, s.feed_consumed_kg
                ORDER BY s.day_age
            ");
            $env_fcr->execute([':cid' => $cycle['id'], ':cid2' => $cycle['id']]);
            $env_fcr = $env_fcr->fetchAll(PDO::FETCH_ASSOC);
        }

        // Latest reading
        $latest = null;
        if ($device) {
            $latest = $this->pdo->prepare("
                SELECT * FROM env_readings WHERE device_id=:did ORDER BY recorded_at DESC LIMIT 1
            ");
            $latest->execute([':did' => $device['id']]);
            $latest = $latest->fetch(PDO::FETCH_ASSOC);
        }

        require view_path('env/env_barn.php');
    }


    // POST /env/barn/{id}/interval — cập nhật tần suất ENV
    public function update_interval(array $vars): void
    {
        $barn_id  = (int)($vars['id'] ?? 0);
        $seconds  = (int)($_POST['interval_seconds'] ?? 300);
        $allowed  = [30, 60, 120, 300, 600, 900];
        if (!in_array($seconds, $allowed)) $seconds = 300;

        // Cập nhật tất cả ENV sensor của barn này
        $this->pdo->prepare("
            UPDATE devices
            SET env_interval_seconds = :sec
            WHERE barn_id = :barn_id
              AND device_type_id IN (SELECT id FROM device_types WHERE device_class = 'sensor')
        ")->execute([':sec' => $seconds, ':barn_id' => $barn_id]);

        // Gửi MQTT config message để ESP32 tự cập nhật interval (nếu đang online)
        $devices = $this->pdo->prepare("
            SELECT d.mqtt_topic FROM devices d
            JOIN device_types dt ON dt.id = d.device_type_id
            WHERE d.barn_id = :barn_id AND dt.device_class = 'sensor'
        ");
        $devices->execute([':barn_id' => $barn_id]);
        foreach ($devices->fetchAll(\PDO::FETCH_OBJ) as $dev) {
            // Publish config tới ESP32 qua MQTT retained
            $payload = json_encode(['env_interval' => $seconds]);
            shell_exec("mosquitto_pub -h 127.0.0.1 -u cfarm_device -P 'Abc@@123' -t '{$dev->mqtt_topic}/config' -m '{$payload}' -r 2>/dev/null");
        }

        header('Location: /env/barn/' . $barn_id . '?saved=1');
    }
    // GET /env/api/barn/{id} — JSON cho auto-refresh
    public function api_latest(array $vars): void
    {
        $barn_id = (int)($vars['id'] ?? 0);
        $device = $this->pdo->prepare("
            SELECT d.id FROM devices d
            JOIN device_types dt ON dt.id = d.device_type_id
            WHERE d.barn_id=:id AND dt.device_class='sensor'
            ORDER BY d.id DESC LIMIT 1
        ");
        $device->execute([':id' => $barn_id]);
        $device = $device->fetch(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        if (!$device) { echo json_encode(['error'=>'no sensor']); return; }

        $row = $this->pdo->prepare("SELECT * FROM env_readings WHERE device_id=:did ORDER BY recorded_at DESC LIMIT 1");
        $row->execute([':did' => $device['id']]);
        echo json_encode($row->fetch(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
    }
}
