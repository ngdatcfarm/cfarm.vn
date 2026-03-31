-- ============================================================
-- CREATE DATABASE AND USER (if not exists)
-- Run this SQL first on MySQL server
-- ============================================================

-- Create database (if not exists)
CREATE DATABASE IF NOT EXISTS cfarm_app_raw CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user (if not exists)
CREATE USER IF NOT EXISTS 'cfarm_user'@'localhost' IDENTIFIED BY 'cfarm_pass';
CREATE USER IF NOT EXISTS 'cfarm_user'@'%' IDENTIFIED BY 'cfarm_pass';

-- Grant privileges
GRANT ALL PRIVILEGES ON cfarm_app_raw.* TO 'cfarm_user'@'localhost';
GRANT ALL PRIVILEGES ON cfarm_app_raw.* TO 'cfarm_user'@'%';

FLUSH PRIVILEGES;

-- Use the database
USE cfarm_app_raw;

-- ============================================================
-- NEW IOT SCHEMA - Clean & Logical
-- ============================================================

-- ============================================================
-- 1. DEVICE TYPES - Loại thiết bị
-- ============================================================
CREATE TABLE IF NOT EXISTS device_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'Tên loại: ESP32 Relay 8CH, DHT22 Sensor...',
    description VARCHAR(255) COMMENT 'Mô tả',
    device_class ENUM('relay', 'sensor', 'mixed') NOT NULL DEFAULT 'relay' COMMENT 'Loại: relay/sensor/mixed',
    total_channels INT NOT NULL DEFAULT 8 COMMENT 'Số kênh',
    mqtt_protocol JSON COMMENT 'Protocol JSON config',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_device_class (device_class)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Loại thiết bị ESP';

-- Insert default device types
INSERT INTO device_types (id, name, description, device_class, total_channels, mqtt_protocol) VALUES
(1, 'ESP32 Relay 8 kênh', 'Board relay 8 kênh điều khiển thiết bị', 'relay', 8, 
 '{"heartbeat":{"topic":"{device}/heartbeat","interval_s":30},"status":{"topic":"{device}/status"},"command":{"topic":"{device}/cmd"}}'),
(2, 'ESP32 DHT22 Sensor', 'Cảm biến nhiệt độ/độ ẩm DHT22', 'sensor', 0,
 '{"telemetry":{"topic":"{device}/telemetry","interval_s":60}}'),
(3, 'ESP32 ENV Sensor', 'Cảm biến môi trường đầy đủ', 'sensor', 0,
 '{"env":{"topic":"{device}/env","interval_s":300}}');

-- ============================================================
-- 2. DEVICES - Thiết bị
-- ============================================================
CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_code VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã thiết bị: esp-barn1-relay-001',
    name VARCHAR(100) NOT NULL COMMENT 'Tên hiển thị',
    barn_id BIGINT UNSIGNED NULL COMMENT 'Chuồng gắn với',
    device_type_id INT NOT NULL COMMENT 'Loại thiết bị',
    mqtt_topic VARCHAR(100) NOT NULL COMMENT 'MQTT topic: cfarm/barn1',
    is_online TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Online status',
    last_heartbeat_at DATETIME NULL COMMENT 'Heartbeat cuối',
    wifi_rssi INT NULL COMMENT 'WiFi signal strength',
    ip_address VARCHAR(45) NULL COMMENT 'IP address',
    uptime_seconds BIGINT UNSIGNED NULL COMMENT 'Thời gian chạy',
    free_heap_bytes INT NULL COMMENT 'Bộ nhớ trống',
    alert_offline TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Cảnh báo offline',
    last_offline_alert_at DATETIME NULL COMMENT 'Lần cuối báo offline',
    env_interval_seconds INT NOT NULL DEFAULT 300 COMMENT 'Tần suất gửi ENV (giây)',
    notes TEXT COMMENT 'Ghi chú',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (barn_id) REFERENCES barns(id) ON DELETE SET NULL,
    FOREIGN KEY (device_type_id) REFERENCES device_types(id),
    INDEX idx_barn (barn_id),
    INDEX idx_device_code (device_code),
    INDEX idx_mqtt_topic (mqtt_topic),
    INDEX idx_is_online (is_online)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Thiết bị IoT';

-- ============================================================
-- 3. DEVICE CHANNELS - Kênh thiết bị
-- ============================================================
CREATE TABLE IF NOT EXISTS device_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL COMMENT 'Thiết bị cha',
    channel_number TINYINT NOT NULL COMMENT 'Số kênh 1-8',
    name VARCHAR(100) NOT NULL COMMENT 'Tên: Quạt 1, Bạt Lên 1...',
    channel_type ENUM('curtain_up', 'curtain_down', 'fan', 'light', 'heater', 'water', 'other') NOT NULL DEFAULT 'other' COMMENT 'Loại kênh',
    gpio_pin INT NULL COMMENT 'GPIO pin trên ESP32',
    max_on_seconds INT NOT NULL DEFAULT 120 COMMENT 'Thời gian on tối đa',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Kích hoạt',
    sort_order TINYINT NOT NULL DEFAULT 0 COMMENT 'Thứ tự hiển thị',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    UNIQUE KEY uk_device_channel (device_id, channel_number),
    INDEX idx_channel_type (channel_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Kênh của thiết bị';

-- ============================================================
-- 4. DEVICE COMMANDS - Lệnh điều khiển
-- ============================================================
CREATE TABLE IF NOT EXISTS device_commands (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL COMMENT 'Thiết bị nhận lệnh',
    channel_id INT NULL COMMENT 'Kênh (nếu có)',
    command_type ENUM('on', 'off', 'stop', 'set_position') NOT NULL COMMENT 'Loại lệnh',
    payload JSON NULL COMMENT 'Dữ liệu lệnh',
    source ENUM('manual', 'schedule', 'automation', 'ai') NOT NULL DEFAULT 'manual' COMMENT 'Nguồn lệnh',
    status ENUM('pending', 'sent', 'acknowledged', 'completed', 'failed', 'timeout') NOT NULL DEFAULT 'pending' COMMENT 'Trạng thái',
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Thời điểm gửi',
    acknowledged_at DATETIME NULL COMMENT 'ESP32 xác nhận',
    completed_at DATETIME NULL COMMENT 'Hoàn thành',
    response_payload JSON NULL COMMENT 'Phản hồi từ ESP32',
    barn_id BIGINT UNSIGNED NULL COMMENT 'Chuồng',
    cycle_id BIGINT UNSIGNED NULL COMMENT 'Chu kỳ nuôi',
    FOREIGN KEY (device_id) REFERENCES devices(id),
    FOREIGN KEY (channel_id) REFERENCES device_channels(id),
    FOREIGN KEY (barn_id) REFERENCES barns(id),
    FOREIGN KEY (cycle_id) REFERENCES cycles(id),
    INDEX idx_device (device_id),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at),
    INDEX idx_barn (barn_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Lịch sử lệnh điều khiển';

-- ============================================================
-- 5. DEVICE STATES - Trạng thái hiện tại
-- ============================================================
CREATE TABLE IF NOT EXISTS device_states (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    channel_id INT NULL,
    state VARCHAR(20) NOT NULL COMMENT 'on/off/position',
    position_pct TINYINT NULL COMMENT 'Vị trí % (cho curtain)',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES device_channels(id),
    UNIQUE KEY uk_device_channel_state (device_id, channel_id),
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Trạng thái hiện tại của thiết bị';

-- ============================================================
-- 6. CURTAIN CONFIGS - Cấu hình bạt
-- ============================================================
CREATE TABLE IF NOT EXISTS curtain_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'Tên bạt: Bạt 1, Bạt 2...',
    barn_id BIGINT UNSIGNED NOT NULL COMMENT 'Chuồng',
    device_id INT NOT NULL COMMENT 'Thiết bị relay',
    up_channel_id INT NOT NULL COMMENT 'Kênh lên',
    down_channel_id INT NOT NULL COMMENT 'Kênh xuống',
    full_up_seconds DECIMAL(5,1) NOT NULL DEFAULT 60.0 COMMENT 'Thời gian lên hoàn toàn (giây)',
    full_down_seconds DECIMAL(5,1) NOT NULL DEFAULT 60.0 COMMENT 'Thời gian xuống hoàn toàn (giây)',
    current_position_pct TINYINT NOT NULL DEFAULT 0 COMMENT 'Vị trí hiện tại %',
    moving_state ENUM('idle', 'moving_up', 'moving_down') NOT NULL DEFAULT 'idle' COMMENT 'Trạng thái di chuyển',
    moving_target_pct TINYINT NULL COMMENT 'Vị trí mục tiêu',
    moving_started_at DATETIME NULL COMMENT 'Bắt đầu di chuyển',
    moving_duration_seconds DECIMAL(5,1) NULL COMMENT 'Thời gian di chuyển',
    last_moved_at DATETIME NULL COMMENT 'Lần cuối di chuyển',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (barn_id) REFERENCES barns(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES devices(id),
    FOREIGN KEY (up_channel_id) REFERENCES device_channels(id),
    FOREIGN KEY (down_channel_id) REFERENCES device_channels(id),
    INDEX idx_barn (barn_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Cấu hình bạt';

-- ============================================================
-- 7. DEVICE STATE LOG - Log trạng thái
-- ============================================================
CREATE TABLE IF NOT EXISTS device_state_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    channel_id INT NULL,
    curtain_config_id INT NULL,
    state VARCHAR(20) NOT NULL,
    position_pct TINYINT NULL,
    barn_id BIGINT UNSIGNED NULL,
    cycle_id BIGINT UNSIGNED NULL,
    logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id),
    FOREIGN KEY (channel_id) REFERENCES device_channels(id),
    FOREIGN KEY (curtain_config_id) REFERENCES curtain_configs(id),
    FOREIGN KEY (barn_id) REFERENCES barns(id),
    INDEX idx_device (device_id),
    INDEX idx_logged (logged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Log trạng thái thiết bị';

-- ============================================================
-- Verify tables created
-- ============================================================
SHOW TABLES LIKE 'device%';
SHOW TABLES LIKE 'curtain%';
