-- Device Ping Tracking Table
-- Track active pings sent to devices and their responses
CREATE TABLE IF NOT EXISTS device_pings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    ping_sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ping_response_at DATETIME NULL,
    status ENUM('pending', 'success', 'timeout') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_device_status (device_id, status),
    INDEX idx_sent_at (ping_sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Track ping requests to devices';

-- Add columns to devices table for more accurate status tracking
ALTER TABLE devices
    ADD COLUMN last_ping_sent_at DATETIME NULL AFTER last_heartbeat_at,
    ADD COLUMN last_ping_response_at DATETIME NULL AFTER last_ping_sent_at,
    ADD COLUMN ping_fail_count TINYINT(3) NOT NULL DEFAULT 0 AFTER last_ping_response_at,
    ADD INDEX idx_ping_status (is_online, last_ping_sent_at);
