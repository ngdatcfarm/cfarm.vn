-- ============================================================
-- ENV WEATHER HOURLY + DAILY - Tổng hợp thời tiết ngoài trời
-- Cùng pipeline: raw (30 ngày) → hourly → daily (vĩnh viễn)
-- ============================================================

USE cfarm_app_raw;

-- Hourly weather
CREATE TABLE IF NOT EXISTS env_weather_hourly (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    barn_id BIGINT UNSIGNED NULL,
    cycle_id BIGINT UNSIGNED NULL,
    hour_start DATETIME NOT NULL,

    -- Wind
    wind_speed_avg DECIMAL(5,2) NULL,
    wind_speed_max DECIMAL(5,2) NULL,
    wind_direction_avg SMALLINT NULL COMMENT 'Hướng gió trung bình (0-360)',

    -- Rain
    rain_total_mm DECIMAL(6,2) NULL COMMENT 'Tổng lượng mưa trong giờ',
    rain_minutes TINYINT NULL COMMENT 'Số phút có mưa trong giờ',

    -- Outdoor temp/humidity
    outdoor_temp_avg DECIMAL(5,2) NULL,
    outdoor_temp_min DECIMAL(5,2) NULL,
    outdoor_temp_max DECIMAL(5,2) NULL,
    outdoor_humidity_avg DECIMAL(5,2) NULL,

    -- Meta
    sample_count INT NOT NULL DEFAULT 0,
    computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_device_hour (device_id, hour_start),
    INDEX idx_barn_hour (barn_id, hour_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tổng hợp thời tiết theo giờ';

-- Daily weather
CREATE TABLE IF NOT EXISTS env_weather_daily (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    barn_id BIGINT UNSIGNED NULL,
    cycle_id BIGINT UNSIGNED NULL,
    day_date DATE NOT NULL,
    day_age SMALLINT UNSIGNED NULL,

    -- Wind
    wind_speed_avg DECIMAL(5,2) NULL,
    wind_speed_max DECIMAL(5,2) NULL,

    -- Rain
    rain_total_mm DECIMAL(6,2) NULL COMMENT 'Tổng lượng mưa cả ngày',
    rain_minutes SMALLINT NULL COMMENT 'Tổng phút mưa cả ngày',
    had_rain TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Có mưa trong ngày?',

    -- Outdoor temp/humidity
    outdoor_temp_avg DECIMAL(5,2) NULL,
    outdoor_temp_min DECIMAL(5,2) NULL,
    outdoor_temp_max DECIMAL(5,2) NULL,
    outdoor_humidity_avg DECIMAL(5,2) NULL,

    -- Meta
    sample_count INT NOT NULL DEFAULT 0,
    hour_count TINYINT NOT NULL DEFAULT 0,
    computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_device_day (device_id, day_date),
    INDEX idx_barn_day (barn_id, day_date),
    INDEX idx_cycle_day (cycle_id, day_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tổng hợp thời tiết theo ngày - lưu vĩnh viễn';
