<?php
/**
 * Env Daily Aggregation - Tổng hợp hourly → daily (env + weather)
 *
 * Chạy 1 lần/ngày lúc 2:30 (sau nightly_snapshot 2:00):
 * Crontab: 30 2 * * * php /var/www/app.cfarm.vn/app/domains/iot/env_daily_aggregate.php >> /var/log/cfarm-env-daily.log 2>&1
 */

declare(strict_types=1);

require_once __DIR__ . '/../../shared/database/mysql.php';

// Tổng hợp ngày hôm qua (hoặc ngày chỉ định qua argument)
$target_date = isset($argv[1])
    ? $argv[1]
    : date('Y-m-d', strtotime('-1 day'));

echo "[" . date('Y-m-d H:i:s') . "] Daily aggregation for: {$target_date}\n";

// ── ENV readings daily ─────────────────────────────────────
$stmt = $pdo->prepare("
    INSERT INTO env_readings_daily
        (device_id, barn_id, cycle_id, day_date, day_age,
         temp_avg, temp_min, temp_max,
         humidity_avg, humidity_min, humidity_max,
         heat_index_avg,
         nh3_avg, nh3_max, co2_avg, co2_max,
         wind_speed_avg, fan_rpm_avg, light_lux_avg,
         outdoor_temp_avg, outdoor_humidity_avg,
         sample_count, hour_count, computed_at)
    SELECT
        h.device_id,
        h.barn_id,
        h.cycle_id,
        :day_date,
        -- Lấy day_age từ cycle nếu có
        CASE WHEN h.cycle_id IS NOT NULL
             THEN DATEDIFF(:day_date2, c.start_date)
             ELSE NULL END,
        -- Temperature: weighted avg by sample_count, absolute min/max
        ROUND(SUM(h.temp_avg * h.sample_count) / SUM(h.sample_count), 2),
        MIN(h.temp_min),
        MAX(h.temp_max),
        -- Humidity
        ROUND(SUM(h.humidity_avg * h.sample_count) / SUM(h.sample_count), 2),
        MIN(h.humidity_min),
        MAX(h.humidity_max),
        ROUND(SUM(h.heat_index_avg * h.sample_count) / SUM(h.sample_count), 2),
        -- Gas
        ROUND(SUM(h.nh3_avg * h.sample_count) / SUM(h.sample_count), 2),
        MAX(h.nh3_max),
        ROUND(SUM(h.co2_avg * h.sample_count) / SUM(h.sample_count), 1),
        MAX(h.co2_max),
        -- Wind & Fan
        ROUND(SUM(h.wind_speed_avg * h.sample_count) / SUM(h.sample_count), 2),
        ROUND(SUM(h.fan_rpm_avg * h.sample_count) / SUM(h.sample_count)),
        -- Light
        ROUND(SUM(h.light_lux_avg * h.sample_count) / SUM(h.sample_count)),
        -- Outdoor
        ROUND(SUM(h.outdoor_temp_avg * h.sample_count) / SUM(h.sample_count), 2),
        ROUND(SUM(h.outdoor_humidity_avg * h.sample_count) / SUM(h.sample_count), 2),
        -- Meta
        SUM(h.sample_count),
        COUNT(*),
        NOW()
    FROM env_readings_hourly h
    LEFT JOIN cycles c ON h.cycle_id = c.id
    WHERE DATE(h.hour_start) = :day_filter
    GROUP BY h.device_id, h.barn_id, h.cycle_id
    ON DUPLICATE KEY UPDATE
        day_age = VALUES(day_age),
        temp_avg = VALUES(temp_avg), temp_min = VALUES(temp_min), temp_max = VALUES(temp_max),
        humidity_avg = VALUES(humidity_avg), humidity_min = VALUES(humidity_min), humidity_max = VALUES(humidity_max),
        heat_index_avg = VALUES(heat_index_avg),
        nh3_avg = VALUES(nh3_avg), nh3_max = VALUES(nh3_max),
        co2_avg = VALUES(co2_avg), co2_max = VALUES(co2_max),
        wind_speed_avg = VALUES(wind_speed_avg), fan_rpm_avg = VALUES(fan_rpm_avg),
        light_lux_avg = VALUES(light_lux_avg),
        outdoor_temp_avg = VALUES(outdoor_temp_avg), outdoor_humidity_avg = VALUES(outdoor_humidity_avg),
        sample_count = VALUES(sample_count), hour_count = VALUES(hour_count), computed_at = NOW()
");

$stmt->execute([
    ':day_date'   => $target_date,
    ':day_date2'  => $target_date,
    ':day_filter' => $target_date,
]);

$env_count = $stmt->rowCount();
echo "[" . date('Y-m-d H:i:s') . "] ENV daily: {$env_count} device-day(s)\n";

// ── Weather daily ──────────────────────────────────────────
$w_stmt = $pdo->prepare("
    INSERT INTO env_weather_daily
        (device_id, barn_id, cycle_id, day_date, day_age,
         wind_speed_avg, wind_speed_max,
         rain_total_mm, rain_minutes, had_rain,
         outdoor_temp_avg, outdoor_temp_min, outdoor_temp_max,
         outdoor_humidity_avg,
         sample_count, hour_count, computed_at)
    SELECT
        w.device_id,
        w.barn_id,
        w.cycle_id,
        :day_date,
        CASE WHEN w.cycle_id IS NOT NULL
             THEN DATEDIFF(:day_date2, c.start_date)
             ELSE NULL END,
        -- Wind
        ROUND(SUM(w.wind_speed_avg * w.sample_count) / SUM(w.sample_count), 2),
        MAX(w.wind_speed_max),
        -- Rain: tổng lượng mưa và tổng phút mưa
        SUM(COALESCE(w.rain_total_mm, 0)),
        SUM(COALESCE(w.rain_minutes, 0)),
        CASE WHEN SUM(COALESCE(w.rain_total_mm, 0)) > 0 THEN 1 ELSE 0 END,
        -- Outdoor
        ROUND(SUM(w.outdoor_temp_avg * w.sample_count) / SUM(w.sample_count), 2),
        MIN(w.outdoor_temp_min),
        MAX(w.outdoor_temp_max),
        ROUND(SUM(w.outdoor_humidity_avg * w.sample_count) / SUM(w.sample_count), 2),
        -- Meta
        SUM(w.sample_count),
        COUNT(*),
        NOW()
    FROM env_weather_hourly w
    LEFT JOIN cycles c ON w.cycle_id = c.id
    WHERE DATE(w.hour_start) = :day_filter
    GROUP BY w.device_id, w.barn_id, w.cycle_id
    ON DUPLICATE KEY UPDATE
        day_age = VALUES(day_age),
        wind_speed_avg = VALUES(wind_speed_avg), wind_speed_max = VALUES(wind_speed_max),
        rain_total_mm = VALUES(rain_total_mm), rain_minutes = VALUES(rain_minutes),
        had_rain = VALUES(had_rain),
        outdoor_temp_avg = VALUES(outdoor_temp_avg), outdoor_temp_min = VALUES(outdoor_temp_min),
        outdoor_temp_max = VALUES(outdoor_temp_max),
        outdoor_humidity_avg = VALUES(outdoor_humidity_avg),
        sample_count = VALUES(sample_count), hour_count = VALUES(hour_count), computed_at = NOW()
");

$w_stmt->execute([
    ':day_date'   => $target_date,
    ':day_date2'  => $target_date,
    ':day_filter' => $target_date,
]);

$w_count = $w_stmt->rowCount();
if ($w_count > 0) {
    echo "[" . date('Y-m-d H:i:s') . "] Weather daily: {$w_count} device-day(s)\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Daily aggregation complete.\n";
