-- ============================================================
-- ENV WEATHER - Du lieu thoi tiet ngoai troi
-- Thiet bi rieng: tram thoi tiet (anemometer, rain gauge, ...)
-- Tach rieng vi khong cung ESP32 voi env_readings
-- ============================================================

USE cfarm_app_raw;

CREATE TABLE IF NOT EXISTS env_weather (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL COMMENT 'Thiet bi thoi tiet',
    barn_id BIGINT UNSIGNED NULL COMMENT 'Khu vuc / chuong gan nhat',
    cycle_id BIGINT UNSIGNED NULL COMMENT 'Chu ky nuoi (neu co)',
    day_age SMALLINT NULL COMMENT 'Ngay tuoi ga',

    -- Gio
    wind_speed_ms DECIMAL(5,2) NULL COMMENT 'Toc do gio (m/s)',
    wind_direction_deg SMALLINT NULL COMMENT 'Huong gio (0-360 do)',

    -- Mua
    is_raining TINYINT(1) NULL COMMENT 'Dang mua (0/1)',
    rainfall_mm DECIMAL(6,2) NULL COMMENT 'Luong mua tich luy (mm)',

    -- Nhiet do / do am ngoai troi (neu tram thoi tiet co)
    outdoor_temp DECIMAL(5,2) NULL COMMENT 'Nhiet do ngoai troi (°C)',
    outdoor_humidity DECIMAL(5,2) NULL COMMENT 'Do am ngoai troi (%)',

    -- Metadata
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Thoi diem ghi nhan',

    FOREIGN KEY (device_id) REFERENCES devices(id),
    FOREIGN KEY (barn_id) REFERENCES barns(id) ON DELETE SET NULL,
    FOREIGN KEY (cycle_id) REFERENCES cycles(id) ON DELETE SET NULL,
    INDEX idx_device (device_id),
    INDEX idx_barn (barn_id),
    INDEX idx_recorded (recorded_at),
    INDEX idx_barn_recorded (barn_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Du lieu thoi tiet ngoai troi (tram thoi tiet rieng)';
