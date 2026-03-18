-- ============================================================
-- Create IoT Tables First
-- ============================================================

-- 1. Device Types
CREATE TABLE IF NOT EXISTS device_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    device_class ENUM('relay', 'sensor', 'mixed') DEFAULT 'relay',
    total_channels INT DEFAULT 8,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Devices
CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    barn_id BIGINT UNSIGNED,
    device_type_id INT NOT NULL,
    mqtt_topic VARCHAR(100) NOT NULL,
    is_online TINYINT(1) DEFAULT 0,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barn_id) REFERENCES barns(id) ON DELETE SET NULL,
    FOREIGN KEY (device_type_id) REFERENCES device_types(id)
);

-- 3. Device Channels
CREATE TABLE IF NOT EXISTS device_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    channel_number TINYINT NOT NULL,
    name VARCHAR(100),
    channel_type ENUM('curtain_up','curtain_down','fan','light','heater','water','other') DEFAULT 'other',
    gpio_pin INT,
    max_on_seconds INT DEFAULT 120,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- 4. Device Commands
CREATE TABLE IF NOT EXISTS device_commands (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    channel_id INT,
    command_type ENUM('on','off','stop','set_position') NOT NULL,
    payload JSON,
    source ENUM('manual','schedule','automation','ai') DEFAULT 'manual',
    status ENUM('pending','sent','acknowledged','completed','failed','timeout') DEFAULT 'pending',
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id)
);

-- 5. Device States
CREATE TABLE IF NOT EXISTS device_states (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    channel_id INT,
    state VARCHAR(20),
    position_pct TINYINT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- 6. Curtain Configs
CREATE TABLE IF NOT EXISTS curtain_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    barn_id BIGINT UNSIGNED NOT NULL,
    device_id INT NOT NULL,
    up_channel_id INT NOT NULL,
    down_channel_id INT NOT NULL,
    full_up_seconds DECIMAL(5,1) DEFAULT 30,
    full_down_seconds DECIMAL(5,1) DEFAULT 30,
    current_position_pct TINYINT DEFAULT 0,
    moving_state ENUM('idle','moving_up','moving_down') DEFAULT 'idle',
    FOREIGN KEY (barn_id) REFERENCES barns(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES devices(id),
    FOREIGN KEY (up_channel_id) REFERENCES device_channels(id),
    FOREIGN KEY (down_channel_id) REFERENCES device_channels(id)
);

-- 7. Device State Log
CREATE TABLE IF NOT EXISTS device_state_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT,
    channel_id INT,
    curtain_config_id INT,
    state VARCHAR(20),
    position_pct TINYINT,
    logged_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 8. Device Firmwares
CREATE TABLE IF NOT EXISTS device_firmwares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    version VARCHAR(20) NOT NULL,
    description TEXT,
    device_type_id INT NOT NULL,
    code TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_latest TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_type_id) REFERENCES device_types(id) ON DELETE CASCADE
);

-- Done! Tables created.
SELECT 'Tables created!' as result;
