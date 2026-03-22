-- ============================================================
-- ENV READINGS DAILY - Tổng hợp theo ngày từ env_readings_hourly
-- Phục vụ báo cáo dài hạn: 90 ngày, 6 tháng, so sánh giữa các lứa
-- Raw (30 ngày) → Hourly (vĩnh viễn) → Daily (vĩnh viễn)
-- ============================================================

USE cfarm_app_raw;

CREATE TABLE IF NOT EXISTS env_readings_daily (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    barn_id BIGINT UNSIGNED NULL,
    cycle_id BIGINT UNSIGNED NULL,
    day_date DATE NOT NULL COMMENT 'Ngày: 2025-03-22',
    day_age SMALLINT UNSIGNED NULL COMMENT 'Ngày tuổi gà (tiện so sánh giữa lứa)',

    -- Temperature
    temp_avg DECIMAL(5,2) NULL,
    temp_min DECIMAL(5,2) NULL,
    temp_max DECIMAL(5,2) NULL,

    -- Humidity
    humidity_avg DECIMAL(5,2) NULL,
    humidity_min DECIMAL(5,2) NULL,
    humidity_max DECIMAL(5,2) NULL,
    heat_index_avg DECIMAL(5,2) NULL,

    -- Gas
    nh3_avg DECIMAL(7,2) NULL,
    nh3_max DECIMAL(7,2) NULL,
    co2_avg DECIMAL(7,1) NULL,
    co2_max DECIMAL(7,1) NULL,

    -- Wind & Fan
    wind_speed_avg DECIMAL(5,2) NULL,
    fan_rpm_avg INT NULL,

    -- Light
    light_lux_avg INT NULL,

    -- Outdoor
    outdoor_temp_avg DECIMAL(5,2) NULL,
    outdoor_humidity_avg DECIMAL(5,2) NULL,

    -- Meta
    sample_count INT NOT NULL DEFAULT 0 COMMENT 'Tổng raw readings trong ngày',
    hour_count TINYINT NOT NULL DEFAULT 0 COMMENT 'Số giờ có dữ liệu (max 24)',
    computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_device_day (device_id, day_date),
    INDEX idx_barn_day (barn_id, day_date),
    INDEX idx_cycle_day (cycle_id, day_date),
    INDEX idx_cycle_age (cycle_id, day_age)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tổng hợp môi trường theo ngày - lưu vĩnh viễn';
