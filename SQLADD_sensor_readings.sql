-- ============================================================
-- ENV READINGS - Du lieu cam bien moi truong (trong chuong)
-- ESP32 ENV Sensor: SHT40, BH1750, MQ137, MQ135
-- Khop voi schema thuc te tren production DB
-- ============================================================

USE cfarm_app_raw;

CREATE TABLE IF NOT EXISTS env_readings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL COMMENT 'Thiet bi gui du lieu',
    barn_id BIGINT UNSIGNED NULL COMMENT 'Chuong',
    cycle_id BIGINT UNSIGNED NULL COMMENT 'Chu ky nuoi (neu co)',
    day_age SMALLINT UNSIGNED NULL COMMENT 'Ngay tuoi ga (tu dong tinh tu cycle.start_date)',

    -- SHT40
    temperature DECIMAL(5,2) NULL COMMENT 'Nhiet do (°C)',
    humidity DECIMAL(5,2) NULL COMMENT 'Do am (%)',
    heat_index DECIMAL(5,2) NULL COMMENT 'Chi so nhiet cam nhan (°C)',

    -- Gas sensors
    nh3_ppm DECIMAL(7,2) NULL COMMENT 'NH3 amoniac (ppm)',
    co2_ppm DECIMAL(7,1) NULL COMMENT 'CO2 (ppm)',

    -- Wind
    wind_speed_ms DECIMAL(5,2) NULL COMMENT 'Toc do gio (m/s)',
    fan_rpm INT NULL COMMENT 'Toc do quat (RPM)',

    -- Light
    light_lux INT NULL COMMENT 'Cuong do anh sang (lux)',

    -- Outdoor (mo rong sau)
    outdoor_temp DECIMAL(5,2) NULL COMMENT 'Nhiet do ngoai troi (°C)',
    outdoor_humidity DECIMAL(5,2) NULL COMMENT 'Do am ngoai troi (%)',
    is_raining TINYINT(1) NULL COMMENT 'Dang mua?',
    rain_mm DECIMAL(6,2) NULL COMMENT 'Luong mua (mm)',

    -- Metadata
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Thoi diem ghi nhan',

    FOREIGN KEY (device_id) REFERENCES devices(id),
    FOREIGN KEY (barn_id) REFERENCES barns(id) ON DELETE SET NULL,
    FOREIGN KEY (cycle_id) REFERENCES cycles(id) ON DELETE SET NULL,
    INDEX idx_device (device_id),
    INDEX idx_barn (barn_id),
    INDEX idx_recorded (recorded_at),
    INDEX idx_barn_recorded (barn_id, recorded_at),
    INDEX idx_cycle (cycle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Du lieu cam bien moi truong trong chuong';
