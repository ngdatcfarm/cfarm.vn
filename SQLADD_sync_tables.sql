-- ═══════════════════════════════════════════════════════════
-- Cloud Sync Tables cho cfarm.vn
-- Chạy: mysql -u cfarm_user -p cfarm_app_raw < SQLADD_sync_tables.sql
-- ═══════════════════════════════════════════════════════════

-- A) sync_config - Cấu hình sync (key-value)
CREATE TABLE IF NOT EXISTS sync_config (
    `key`       VARCHAR(100) PRIMARY KEY,
    `value`     TEXT,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Giá trị mặc định
INSERT IGNORE INTO sync_config (`key`, `value`) VALUES
    ('local_token', ''),
    ('api_token', ''),
    ('local_ip', ''),
    ('enabled', 'false');

-- B) sync_queue - Hàng đợi thay đổi (cloud → local)
CREATE TABLE IF NOT EXISTS sync_queue (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    table_name  VARCHAR(100) NOT NULL,
    record_id   VARCHAR(100) NOT NULL,
    action      VARCHAR(20) NOT NULL DEFAULT 'insert',
    payload     JSON,
    synced      TINYINT(1) DEFAULT 0,
    synced_at   DATETIME NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sync_queue_pending (synced, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- C) sync_log - Nhật ký sync
CREATE TABLE IF NOT EXISTS sync_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    direction   VARCHAR(20) NOT NULL,
    items_count INT DEFAULT 0,
    status      VARCHAR(20) DEFAULT 'ok',
    error_msg   TEXT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- D) sensor_data_summary - Dữ liệu sensor tổng hợp từ local
CREATE TABLE IF NOT EXISTS sensor_data_summary (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    device_code  VARCHAR(50) NOT NULL,
    sensor_type  VARCHAR(50) NOT NULL,
    hour         DATETIME NOT NULL,
    avg_value    DECIMAL(10,2),
    min_value    DECIMAL(10,2),
    max_value    DECIMAL(10,2),
    sample_count INT DEFAULT 0,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sensor_summary (device_code, sensor_type, hour),
    INDEX idx_sensor_hour (hour),
    INDEX idx_sensor_device (device_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- E) Thêm cột updated_at cho các bảng config nếu chưa có
-- (để local pull thay đổi theo thời gian)

-- feed_brands
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'feed_brands' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE feed_brands ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- feed_types
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'feed_types' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE feed_types ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- medications
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medications' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE medications ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- suppliers
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE suppliers ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- vaccine_programs
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vaccine_programs' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE vaccine_programs ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- vaccine_program_items
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vaccine_program_items' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE vaccine_program_items ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- notification_rules
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_rules' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE notification_rules ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Sync tables created successfully!' AS status;
