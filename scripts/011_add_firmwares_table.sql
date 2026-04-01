-- ============================================
-- 011: Add firmwares table for local→cloud sync
-- Purpose: Mirror local's firmwares table on cloud
-- ============================================

-- Create firmwares table
CREATE TABLE IF NOT EXISTS firmwares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_type_code VARCHAR(50) NOT NULL,
    version VARCHAR(50) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    checksum VARCHAR(64) NOT NULL,
    changelog TEXT,
    is_latest BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_type_version (device_type_code, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add firmware_id column (ignore error if already exists)
ALTER TABLE devices ADD COLUMN firmware_id INT AFTER firmware_version;

-- Add index
CREATE INDEX idx_devices_firmware ON devices (firmware_id);
