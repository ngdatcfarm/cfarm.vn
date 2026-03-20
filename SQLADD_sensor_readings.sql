-- ============================================================
-- SENSOR READINGS - Du lieu cam bien moi truong
-- Luu tru du lieu tu ESP32 ENV Sensor (SHT40, BH1750, MQ137, MQ135)
-- ============================================================

USE cfarm_app_raw;

CREATE TABLE IF NOT EXISTS sensor_readings (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL COMMENT 'Thiet bi gui du lieu',
    barn_id BIGINT UNSIGNED NULL COMMENT 'Chuong',
    cycle_id BIGINT UNSIGNED NULL COMMENT 'Chu ky nuoi (neu co)',

    -- SHT40
    temperature DECIMAL(5,2) NULL COMMENT 'Nhiet do (°C)',
    humidity DECIMAL(5,2) NULL COMMENT 'Do am (%)',

    -- BH1750 / GY30
    lux DECIMAL(10,2) NULL COMMENT 'Cuong do anh sang (lux)',

    -- MQ137
    nh3_ppm DECIMAL(8,2) NULL COMMENT 'NH3 amoniac (ppm)',
    mq137_raw INT NULL COMMENT 'MQ137 ADC raw (0-4095)',

    -- MQ135
    co2_ppm DECIMAL(8,2) NULL COMMENT 'CO2 (ppm)',
    mq135_raw INT NULL COMMENT 'MQ135 ADC raw (0-4095)',

    -- Metadata
    mq_warmup TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'MQ sensor da warm-up chua',
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Thoi diem ghi nhan',

    FOREIGN KEY (device_id) REFERENCES devices(id),
    FOREIGN KEY (barn_id) REFERENCES barns(id) ON DELETE SET NULL,
    FOREIGN KEY (cycle_id) REFERENCES cycles(id) ON DELETE SET NULL,
    INDEX idx_device (device_id),
    INDEX idx_barn (barn_id),
    INDEX idx_recorded (recorded_at),
    INDEX idx_barn_recorded (barn_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Du lieu cam bien moi truong';
