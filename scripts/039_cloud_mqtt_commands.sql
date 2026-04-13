-- Pending commands queue for ESP32 MQTT publishing
CREATE TABLE IF NOT EXISTS pending_commands (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_code VARCHAR(50) NOT NULL,
    command_json TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed', 'expired') NOT NULL DEFAULT 'pending',
    priority TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME,
    error_message TEXT,
    INDEX idx_pending_status (status, created_at),
    INDEX idx_device_code (device_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Command log for history
CREATE TABLE IF NOT EXISTS command_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_code VARCHAR(50) NOT NULL,
    command_type VARCHAR(50) NOT NULL,
    command_json TEXT NOT NULL,
    status ENUM('success', 'failed') NOT NULL,
    error_message TEXT,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_command_log_device (device_code, executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
