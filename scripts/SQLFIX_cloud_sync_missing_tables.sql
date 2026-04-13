-- =====================================================
-- Cloud Sync Fix - Missing Tables and Columns
-- Run on cloud MySQL: mysql -u cfarm_user -pcfarm_pass cfarm_app_raw < SQLFIX_cloud_sync_missing_tables.sql
-- =====================================================

-- A) Add missing tables that Local pushes but Cloud doesn't accept
-- These tables are queued by Local's sync triggers but missing from Cloud's allowed_tables

-- weight_sessions table (Local PRIMARY → Cloud)
CREATE TABLE IF NOT EXISTS weight_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cycle_id BIGINT UNSIGNED,
    session_date DATE NOT NULL,
    total_birds INT NOT NULL DEFAULT 0,
    total_weight_kg DECIMAL(10,2) NOT NULL DEFAULT 0,
    avg_weight_g DECIMAL(10,2) NOT NULL DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_weight_session_cycle (cycle_id),
    INDEX idx_weight_session_date (session_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- weight_details table (Local PRIMARY → Cloud)
CREATE TABLE IF NOT EXISTS weight_details (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    bird_number INT NOT NULL,
    weight_g INT NOT NULL,
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES weight_sessions(id) ON DELETE CASCADE,
    INDEX idx_weight_detail_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- alerts table (Local PRIMARY → Cloud)
CREATE TABLE IF NOT EXISTS alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    barn_id VARCHAR(50),
    cycle_id BIGINT UNSIGNED,
    alert_type VARCHAR(50) NOT NULL,
    severity ENUM('info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'warning',
    message TEXT NOT NULL,
    is_acknowledged TINYINT(1) NOT NULL DEFAULT 0,
    acknowledged_at DATETIME,
    acknowledged_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_alert_barn (barn_id),
    INDEX idx_alert_cycle (cycle_id),
    INDEX idx_alert_type (alert_type),
    INDEX idx_alert_severity (severity),
    INDEX idx_alert_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- care_water_logs table (Local PRIMARY → Cloud) - MISSING
CREATE TABLE IF NOT EXISTS care_water_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cycle_id BIGINT UNSIGNED NOT NULL,
    log_date DATE NOT NULL,
    water_consumed_liters DECIMAL(10,2),
    temperature_celsius DECIMAL(5,2),
    ph_level DECIMAL(4,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_care_water_cycle (cycle_id),
    INDEX idx_care_water_date (log_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- barn_default_warehouses table (Local PRIMARY → Cloud)
CREATE TABLE IF NOT EXISTS barn_default_warehouses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    barn_id VARCHAR(50) NOT NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_barn_warehouse (barn_id, warehouse_id),
    INDEX idx_barn_default_barn (barn_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- inventory_alert_rules table (Local PRIMARY → Cloud)
CREATE TABLE IF NOT EXISTS inventory_alert_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    warehouse_id BIGINT UNSIGNED,
    product_id BIGINT UNSIGNED,
    alert_type VARCHAR(50) NOT NULL,
    threshold_min DECIMAL(10,2),
    threshold_max DECIMAL(10,2),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_inv_alert_warehouse (warehouse_id),
    INDEX idx_inv_alert_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- B) Add FK constraints to existing bats-related tables
-- bat_logs FK constraints (SQLADD_bats.sql created table without FKs)

-- Check if bat_logs.bat_id index exists before adding FK
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bat_logs' AND COLUMN_NAME = 'bat_id');
SET @sql = IF(@col_exists > 0,
    'ALTER TABLE bat_logs ADD CONSTRAINT fk_bat_logs_bat FOREIGN KEY (bat_id) REFERENCES bats(id) ON DELETE CASCADE',
    'SELECT ''bat_id column not found, skipping FK''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check if bat_logs.cycle_id column exists before adding FK
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bat_logs' AND COLUMN_NAME = 'cycle_id');
SET @sql = IF(@col_exists > 0,
    'ALTER TABLE bat_logs ADD CONSTRAINT fk_bat_logs_cycle FOREIGN KEY (cycle_id) REFERENCES cycles(id) ON DELETE SET NULL',
    'SELECT ''cycle_id column not found, skipping FK''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- bats FK constraints (SQLADD_bats.sql created table without FKs)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bats' AND COLUMN_NAME = 'barn_id');
SET @sql = IF(@col_exists > 0,
    'ALTER TABLE bats ADD CONSTRAINT fk_bats_barn FOREIGN KEY (barn_id) REFERENCES barns(id) ON DELETE CASCADE',
    'SELECT ''barn_id column not found, skipping FK''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bats' AND COLUMN_NAME = 'device_id');
SET @sql = IF(@col_exists > 0,
    'ALTER TABLE bats ADD CONSTRAINT fk_bats_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL',
    'SELECT ''device_id column not found, skipping FK''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- C) Add updated_at to tables that need it for sync but missing it
-- sensor_data table for Cloud ← Local push (sensor sync uses ON DUPLICATE KEY)
CREATE TABLE IF NOT EXISTS sensor_data (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_code VARCHAR(50) NOT NULL,
    sensor_type VARCHAR(50) NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    recorded_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sensor_device (device_code),
    INDEX idx_sensor_type (sensor_type),
    INDEX idx_sensor_recorded (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add updated_at to weight_sessions if not exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'weight_sessions' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE weight_sessions ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT ''updated_at already exists in weight_sessions''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add updated_at to alerts if not exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alerts' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE alerts ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT ''updated_at already exists in alerts''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Cloud sync fix migration completed!' AS status;
