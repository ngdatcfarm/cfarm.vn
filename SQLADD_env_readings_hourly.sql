-- Bảng tổng hợp env_readings theo giờ
-- Giảm tải query khi xem biểu đồ môi trường dài hạn
-- Raw data: 2,880 rows/ngày/sensor → Hourly: 24 rows/ngày/sensor

USE cfarm_app_raw;

CREATE TABLE IF NOT EXISTS env_readings_hourly (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    barn_id BIGINT UNSIGNED NULL,
    cycle_id BIGINT UNSIGNED NULL,
    hour_start DATETIME NOT NULL COMMENT 'Đầu giờ: 2025-03-22 14:00:00',

    -- Temperature & Humidity
    temp_avg DECIMAL(5,2) NULL,
    temp_min DECIMAL(5,2) NULL,
    temp_max DECIMAL(5,2) NULL,
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
    sample_count INT NOT NULL DEFAULT 0 COMMENT 'Số readings trong giờ',
    computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_device_hour (device_id, hour_start),
    INDEX idx_barn_hour (barn_id, hour_start),
    INDEX idx_cycle_hour (cycle_id, hour_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
