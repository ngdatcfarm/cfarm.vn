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
        // Latest reading mỗi barn — chỉ lấy 1 device/barn để tránh duplicate card
        $barns = $this->pdo->query("
            SELECT
                b.id, b.name,
                e.temperature, e.humidity, e.heat_index,
                e.nh3_ppm, e.co2_ppm, e.light_lux,
                w.wind_speed_ms, w.is_raining,
                e.recorded_at,
                c.code as cycle_code, c.id as cycle_id,
                e.day_age,
                d.name as device_name, d.is_online,
                TIMESTAMPDIFF(MINUTE, e.recorded_at, NOW()) as minutes_ago,
                (SELECT COUNT(*) FROM devices dx
                 JOIN device_types dtx ON dtx.id = dx.device_type_id
                 WHERE dx.barn_id = b.id AND dtx.device_class = 'sensor') as sensor_count
            FROM barns b
            LEFT JOIN devices d ON d.id = (
                SELECT d2.id FROM devices d2
                JOIN device_types dt2 ON dt2.id = d2.device_type_id
                WHERE d2.barn_id = b.id AND dt2.device_class = 'sensor'
                ORDER BY d2.id DESC LIMIT 1
            )
            LEFT JOIN env_readings e ON e.id = (
                SELECT id FROM env_readings
                WHERE device_id = d.id
                ORDER BY recorded_at DESC LIMIT 1
            )
            LEFT JOIN env_weather w ON w.id = (
                SELECT id FROM env_weather
                WHERE barn_id = b.id
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

        // ALL ENV sensor devices for this barn
        $devices_stmt = $this->pdo->prepare("
            SELECT d.* FROM devices d
            JOIN device_types dt ON dt.id = d.device_type_id
            WHERE d.barn_id=:id AND dt.device_class='sensor'
            ORDER BY d.name, d.id
        ");
        $devices_stmt->execute([':id' => $barn_id]);
        $devices = $devices_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Sensor filter: ?sensor=all (default/trung bình) hoặc ?sensor={device_id}
        $device_ids = array_map('intval', array_column($devices, 'id'));
        $selected_sensor = $_GET['sensor'] ?? 'all';
        if ($selected_sensor !== 'all') {
            $selected_sensor = (int)$selected_sensor;
            if (!in_array($selected_sensor, $device_ids, true)) $selected_sensor = 'all';
        }
        $active_ids = ($selected_sensor === 'all') ? $device_ids : [(int)$selected_sensor];

        // Compat: $device = first device (for interval config UI)
        $device = !empty($devices) ? $devices[0] : null;

        // Helper: PDO IN clause
        $in_sql = !empty($active_ids)
            ? implode(',', array_fill(0, count($active_ids), '?'))
            : '0';
        $in_params = array_map('intval', $active_ids);

        // 24h ENV data
        $env_24h = [];
        if (!empty($in_params)) {
            if (count($in_params) === 1) {
                $stmt = $this->pdo->prepare("
                    SELECT DATE_FORMAT(recorded_at, '%H:%i') as time_label,
                           recorded_at, temperature, humidity, heat_index,
                           nh3_ppm, co2_ppm, light_lux, day_age
                    FROM env_readings
                    WHERE device_id = ? AND recorded_at >= NOW() - INTERVAL 24 HOUR
                    ORDER BY recorded_at ASC
                ");
            } else {
                // Nhiều sensor → trung bình mỗi 5 phút
                $stmt = $this->pdo->prepare("
                    SELECT DATE_FORMAT(MIN(recorded_at), '%H:%i') as time_label,
                           MIN(recorded_at) as recorded_at,
                           ROUND(AVG(temperature),1) as temperature,
                           ROUND(AVG(humidity),1) as humidity,
                           ROUND(AVG(heat_index),1) as heat_index,
                           ROUND(AVG(nh3_ppm),1) as nh3_ppm,
                           ROUND(AVG(co2_ppm),1) as co2_ppm,
                           ROUND(AVG(light_lux),1) as light_lux,
                           MAX(day_age) as day_age
                    FROM env_readings
                    WHERE device_id IN ($in_sql)
                      AND recorded_at >= NOW() - INTERVAL 24 HOUR
                    GROUP BY FLOOR(UNIX_TIMESTAMP(recorded_at) / 300)
                    ORDER BY MIN(recorded_at) ASC
                ");
            }
            $stmt->execute($in_params);
            $env_24h = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // ENV vs FCR — nếu có cycle
        $env_fcr = [];
        if ($cycle && !empty($in_params)) {
            $stmt = $this->pdo->prepare("
                SELECT
                    s.snapshot_date, s.day_age, s.fcr_cumulative,
                    s.avg_weight_g, s.feed_consumed_kg,
                    AVG(e.temperature) as avg_temp,
                    AVG(e.humidity)    as avg_humidity,
                    AVG(e.nh3_ppm)     as avg_nh3,
                    AVG(e.co2_ppm)     as avg_co2,
                    AVG(e.light_lux)   as avg_lux,
                    MIN(e.temperature) as min_temp,
                    MAX(e.temperature) as max_temp
                FROM cycle_daily_snapshots s
                LEFT JOIN env_readings e
                    ON e.device_id IN ($in_sql)
                    AND DATE(e.recorded_at) = s.snapshot_date
                WHERE s.cycle_id = ?
                GROUP BY s.snapshot_date, s.day_age, s.fcr_cumulative, s.avg_weight_g, s.feed_consumed_kg
                ORDER BY s.day_age
            ");
            $stmt->execute(array_merge($in_params, [$cycle['id']]));
            $env_fcr = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Latest reading
        $latest = null;
        if (!empty($in_params)) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM env_readings WHERE device_id IN ($in_sql) ORDER BY recorded_at DESC LIMIT 1
            ");
            $stmt->execute($in_params);
            $latest = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Latest weather
        $weather = $this->pdo->prepare("
            SELECT * FROM env_weather WHERE barn_id=:bid ORDER BY recorded_at DESC LIMIT 1
        ");
        $weather->execute([':bid' => $barn_id]);
        $weather = $weather->fetch(PDO::FETCH_ASSOC) ?: null;

        // 7 ngày: thống kê ngày (min, max, avg)
        $daily_7d = [];
        if (!empty($in_params)) {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(recorded_at) as date_label,
                    AVG(temperature) as avg_temp,  MIN(temperature) as min_temp,  MAX(temperature) as max_temp,
                    AVG(humidity)    as avg_hum,    MIN(humidity)    as min_hum,   MAX(humidity)    as max_hum,
                    AVG(nh3_ppm)     as avg_nh3,    MAX(nh3_ppm)     as max_nh3,
                    AVG(co2_ppm)     as avg_co2,    MAX(co2_ppm)     as max_co2,
                    AVG(light_lux)   as avg_lux,
                    COUNT(*)         as reading_count
                FROM env_readings
                WHERE device_id IN ($in_sql)
                  AND recorded_at >= CURDATE() - INTERVAL 6 DAY
                  AND temperature IS NOT NULL
                GROUP BY DATE(recorded_at)
                ORDER BY DATE(recorded_at) ASC
            ");
            $stmt->execute($in_params);
            $daily_7d = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($daily_7d as &$row) {
                $row['short_date'] = date('d/m', strtotime($row['date_label']));
            }
            unset($row);
        }

        // Phân bố theo giờ (7 ngày)
        $hourly_dist = [];
        if (!empty($in_params)) {
            $stmt = $this->pdo->prepare("
                SELECT
                    HOUR(recorded_at) as hour_slot,
                    AVG(temperature)  as avg_temp,
                    AVG(humidity)     as avg_hum,
                    AVG(nh3_ppm)      as avg_nh3,
                    AVG(co2_ppm)      as avg_co2,
                    AVG(light_lux)    as avg_lux
                FROM env_readings
                WHERE device_id IN ($in_sql)
                  AND recorded_at >= NOW() - INTERVAL 7 DAY
                  AND temperature IS NOT NULL
                GROUP BY HOUR(recorded_at)
                ORDER BY HOUR(recorded_at)
            ");
            $stmt->execute($in_params);
            $hourly_dist = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Thống kê tổng quát (7 ngày)
        $stats_7d = null;
        if (!empty($in_params)) {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_readings,
                    COUNT(temperature) as temp_readings,
                    COUNT(nh3_ppm) as gas_readings,
                    ROUND(AVG(temperature), 1) as avg_temp,
                    ROUND(MIN(temperature), 1) as min_temp,
                    ROUND(MAX(temperature), 1) as max_temp,
                    ROUND(AVG(humidity), 1)    as avg_hum,
                    ROUND(AVG(nh3_ppm), 1)     as avg_nh3,
                    ROUND(MAX(nh3_ppm), 1)     as max_nh3,
                    ROUND(AVG(co2_ppm), 1)     as avg_co2,
                    ROUND(MAX(co2_ppm), 1)     as max_co2,
                    SUM(CASE WHEN nh3_ppm > 25 THEN 1 ELSE 0 END) as nh3_over_count,
                    SUM(CASE WHEN temperature > 35 THEN 1 ELSE 0 END) as temp_over_count,
                    SUM(CASE WHEN humidity > 85 THEN 1 ELSE 0 END) as hum_over_count
                FROM env_readings
                WHERE device_id IN ($in_sql)
                  AND recorded_at >= NOW() - INTERVAL 7 DAY
            ");
            $stmt->execute($in_params);
            $stats_7d = $stmt->fetch(PDO::FETCH_ASSOC);
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
    // GET /env/api/barn/{id} — JSON cho auto-refresh (trả latest mỗi sensor + trung bình)
    public function api_latest(array $vars): void
    {
        $barn_id = (int)($vars['id'] ?? 0);
        $devices = $this->pdo->prepare("
            SELECT d.id, d.name FROM devices d
            JOIN device_types dt ON dt.id = d.device_type_id
            WHERE d.barn_id=:id AND dt.device_class='sensor'
            ORDER BY d.name, d.id
        ");
        $devices->execute([':id' => $barn_id]);
        $devices = $devices->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        if (!$devices) { echo json_encode(['error'=>'no sensor']); return; }

        $result = ['sensors' => []];
        foreach ($devices as $d) {
            $row = $this->pdo->prepare("SELECT * FROM env_readings WHERE device_id=:did ORDER BY recorded_at DESC LIMIT 1");
            $row->execute([':did' => $d['id']]);
            $reading = $row->fetch(PDO::FETCH_ASSOC);
            if ($reading) {
                $reading['device_name'] = $d['name'];
                $result['sensors'][] = $reading;
            }
        }
        // Trả latest gần nhất (cho backward compat)
        if (!empty($result['sensors'])) {
            usort($result['sensors'], fn($a, $b) => strcmp($b['recorded_at'], $a['recorded_at']));
            $result = array_merge($result['sensors'][0], $result);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
