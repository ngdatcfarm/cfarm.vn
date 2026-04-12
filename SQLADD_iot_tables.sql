-- IoT tables for cloud DB (curtain control requires device_channels)
-- Run on cloud: mysql -u cfarm_user -pcfarm_pass cfarm_app_raw

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

CREATE TABLE IF NOT EXISTS curtain_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barn_id BIGINT UNSIGNED NOT NULL,
    device_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    up_channel_id INT,
    down_channel_id INT,
    current_position_pct TINYINT NOT NULL DEFAULT 0,
    moving_state ENUM('idle','moving_up','moving_down') DEFAULT 'idle',
    moving_target_pct TINYINT,
    moving_started_at DATETIME,
    moving_duration_seconds FLOAT,
    full_up_seconds INT DEFAULT 45,
    full_down_seconds INT DEFAULT 45,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (barn_id) REFERENCES barns(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    FOREIGN KEY (up_channel_id) REFERENCES device_channels(id),
    FOREIGN KEY (down_channel_id) REFERENCES device_channels(id)
);

CREATE TABLE IF NOT EXISTS device_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    relay_count TINYINT DEFAULT 4,
    has_curtain_support TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS device_commands (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    channel_id INT,
    command_type ENUM('on','off','stop','set_position') NOT NULL,
    payload JSON,
    source ENUM('manual','schedule','automation','ai','cloud_direct') DEFAULT 'manual',
    status ENUM('pending','sent','acknowledged','completed','failed','timeout') DEFAULT 'pending',
    sent_at DATETIME,
    acknowledged_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- Insert default device types if not exist
INSERT IGNORE INTO device_types (id, name, relay_count, has_curtain_support) VALUES
(1, 'Relay 4CH', 4, 0),
(2, 'Relay 8CH', 8, 0),
(3, 'Relay 4CH + Curtain', 4, 1),
(4, 'Relay 8CH + Curtain', 8, 1);
