-- =====================================================
-- Bats System - For controlling barn curtains/ventilation
-- Each barn has 4 default bats: left_top, left_bottom, right_top, right_bottom
-- Run on cloud: mysql -u cfarm_user -pcfarm_pass cfarm_app_raw
-- =====================================================

-- Bats table
CREATE TABLE IF NOT EXISTS bats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    barn_id VARCHAR(50) NOT NULL,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    device_id BIGINT UNSIGNED,
    up_relay_channel TINYINT NOT NULL,
    down_relay_channel TINYINT NOT NULL,
    auto_enabled TINYINT(1) DEFAULT 0,
    timeout_seconds INT DEFAULT 210,
    position VARCHAR(20) DEFAULT 'stopped',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_barn_code (barn_id, code)
);

-- Bat logs table
CREATE TABLE IF NOT EXISTS bat_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bat_id BIGINT UNSIGNED NOT NULL,
    cycle_id BIGINT UNSIGNED,
    action VARCHAR(20) NOT NULL,
    duration_seconds INT,
    started_at DATETIME NOT NULL,
    ended_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bat_logs_bat_id (bat_id),
    INDEX idx_bat_logs_started_at (started_at DESC)
);

-- Insert default bats for test-barn-1
INSERT IGNORE INTO bats (barn_id, code, name, up_relay_channel, down_relay_channel) VALUES
('test-barn-1', 'left_top', 'Bạt trái trên', 1, 2),
('test-barn-1', 'left_bottom', 'Bạt trái dưới', 3, 4),
('test-barn-1', 'right_top', 'Bạt phải trên', 5, 6),
('test-barn-1', 'right_bottom', 'Bạt phải dưới', 7, 8);
