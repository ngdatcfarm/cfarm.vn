<?php
/**
 * Env Hourly Aggregation - Tổng hợp env_readings + env_weather thành hourly
 *
 * Chạy mỗi giờ (phút 5 để đảm bảo data giờ trước đã đầy đủ):
 * Crontab: 5 * * * * php /var/www/app.cfarm.vn/app/domains/iot/env_hourly_aggregate.php >> /var/log/cfarm-env-hourly.log 2>&1
 */

declare(strict_types=1);

require_once __DIR__ . '/../../shared/database/mysql.php';

// Tổng hợp giờ vừa qua (hoặc giờ chỉ định qua argument)
$target_hour = isset($argv[1])
    ? $argv[1]
    : date('Y-m-d H:00:00', strtotime('-1 hour'));

$next_hour = date('Y-m-d H:00:00', strtotime($target_hour . ' +1 hour'));

echo "[" . date('Y-m-d H:i:s') . "] Aggregating env_readings for hour: {$target_hour}\n";

$stmt = $pdo->prepare("
    INSERT INTO env_readings_hourly
        (device_id, barn_id, cycle_id, hour_start,
         temp_avg, temp_min, temp_max,
         humidity_avg, humidity_min, humidity_max,
         heat_index_avg,
         nh3_avg, nh3_max, co2_avg, co2_max,
         wind_speed_avg, fan_rpm_avg, light_lux_avg,
         outdoor_temp_avg, outdoor_humidity_avg,
         sample_count, computed_at)
    SELECT
        device_id,
        barn_id,
        cycle_id,
        :hour_start,
        ROUND(AVG(temperature), 2),
        ROUND(MIN(temperature), 2),
        ROUND(MAX(temperature), 2),
        ROUND(AVG(humidity), 2),
        ROUND(MIN(humidity), 2),
        ROUND(MAX(humidity), 2),
        ROUND(AVG(heat_index), 2),
        ROUND(AVG(nh3_ppm), 2),
        ROUND(MAX(nh3_ppm), 2),
        ROUND(AVG(co2_ppm), 1),
        ROUND(MAX(co2_ppm), 1),
        ROUND(AVG(wind_speed_ms), 2),
        ROUND(AVG(fan_rpm)),
        ROUND(AVG(light_lux)),
        ROUND(AVG(outdoor_temp), 2),
        ROUND(AVG(outdoor_humidity), 2),
        COUNT(*),
        NOW()
    FROM env_readings
    WHERE recorded_at >= :from_time
      AND recorded_at < :to_time
    GROUP BY device_id, barn_id, cycle_id
    ON DUPLICATE KEY UPDATE
        temp_avg = VALUES(temp_avg), temp_min = VALUES(temp_min), temp_max = VALUES(temp_max),
        humidity_avg = VALUES(humidity_avg), humidity_min = VALUES(humidity_min), humidity_max = VALUES(humidity_max),
        heat_index_avg = VALUES(heat_index_avg),
        nh3_avg = VALUES(nh3_avg), nh3_max = VALUES(nh3_max),
        co2_avg = VALUES(co2_avg), co2_max = VALUES(co2_max),
        wind_speed_avg = VALUES(wind_speed_avg), fan_rpm_avg = VALUES(fan_rpm_avg),
        light_lux_avg = VALUES(light_lux_avg),
        outdoor_temp_avg = VALUES(outdoor_temp_avg), outdoor_humidity_avg = VALUES(outdoor_humidity_avg),
        sample_count = VALUES(sample_count), computed_at = NOW()
");

$stmt->execute([
    ':hour_start' => $target_hour,
    ':from_time'  => $target_hour,
    ':to_time'    => $next_hour,
]);

$count = $stmt->rowCount();
echo "[" . date('Y-m-d H:i:s') . "] Aggregated {$count} device-hour(s)\n";

// ── Weather hourly ──────────────────────────────────────────
echo "[" . date('Y-m-d H:i:s') . "] Aggregating env_weather for hour: {$target_hour}\n";

$weather_stmt = $pdo->prepare("
    INSERT INTO env_weather_hourly
        (device_id, barn_id, cycle_id, hour_start,
         wind_speed_avg, wind_speed_max, wind_direction_avg,
         rain_total_mm, rain_minutes,
         outdoor_temp_avg, outdoor_temp_min, outdoor_temp_max,
         outdoor_humidity_avg,
         sample_count, computed_at)
    SELECT
        device_id,
        barn_id,
        cycle_id,
        :hour_start,
        ROUND(AVG(wind_speed_ms), 2),
        ROUND(MAX(wind_speed_ms), 2),
        ROUND(AVG(wind_direction_deg)),
        ROUND(MAX(rainfall_mm), 2),
        SUM(CASE WHEN is_raining = 1 THEN 5 ELSE 0 END),
        ROUND(AVG(outdoor_temp), 2),
        ROUND(MIN(outdoor_temp), 2),
        ROUND(MAX(outdoor_temp), 2),
        ROUND(AVG(outdoor_humidity), 2),
        COUNT(*),
        NOW()
    FROM env_weather
    WHERE recorded_at >= :from_time
      AND recorded_at < :to_time
    GROUP BY device_id, barn_id, cycle_id
    ON DUPLICATE KEY UPDATE
        wind_speed_avg = VALUES(wind_speed_avg), wind_speed_max = VALUES(wind_speed_max),
        wind_direction_avg = VALUES(wind_direction_avg),
        rain_total_mm = VALUES(rain_total_mm), rain_minutes = VALUES(rain_minutes),
        outdoor_temp_avg = VALUES(outdoor_temp_avg), outdoor_temp_min = VALUES(outdoor_temp_min),
        outdoor_temp_max = VALUES(outdoor_temp_max),
        outdoor_humidity_avg = VALUES(outdoor_humidity_avg),
        sample_count = VALUES(sample_count), computed_at = NOW()
");

$weather_stmt->execute([
    ':hour_start' => $target_hour,
    ':from_time'  => $target_hour,
    ':to_time'    => $next_hour,
]);

$w_count = $weather_stmt->rowCount();
if ($w_count > 0) {
    echo "[" . date('Y-m-d H:i:s') . "] Aggregated {$w_count} weather device-hour(s)\n";
}

// ── Retention: xóa raw data cũ hơn 30 ngày ────────────────
$retention_date = date('Y-m-d H:i:s', strtotime('-30 days'));

$del = $pdo->prepare("DELETE FROM env_readings WHERE recorded_at < :cutoff LIMIT 50000");
$del->execute([':cutoff' => $retention_date]);
$deleted = $del->rowCount();
if ($deleted > 0) {
    echo "[" . date('Y-m-d H:i:s') . "] Cleaned {$deleted} old env_readings (before {$retention_date})\n";
}

$del_w = $pdo->prepare("DELETE FROM env_weather WHERE recorded_at < :cutoff LIMIT 50000");
$del_w->execute([':cutoff' => $retention_date]);
$deleted_w = $del_w->rowCount();
if ($deleted_w > 0) {
    echo "[" . date('Y-m-d H:i:s') . "] Cleaned {$deleted_w} old env_weather (before {$retention_date})\n";
}
