-- ============================================
-- 011: Add firmwares table for local→cloud sync
-- Purpose: Mirror local's firmwares table on cloud
--          for firmware version tracking and OTA
-- ============================================

-- Create firmwares table if not exists (local→cloud sync target)
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

-- Add firmware_id column to devices if not exists
-- This links devices to their assigned firmware
ALTER TABLE devices ADD COLUMN IF NOT EXISTS firmware_id INT AFTER firmware_version;

-- Add index for faster firmware lookups
CREATE INDEX IF NOT EXISTS idx_devices_firmware ON devices (firmware_id);

-- Sync existing devices that have firmware_version but no firmware_id
-- Try to match by device_type_id to device_types.code = device_type_code
UPDATE devices d
JOIN device_types dt ON dt.id = d.device_type_id
JOIN (
    SELECT device_type_code, id as fw_id FROM firmwares WHERE is_latest = 1
) f ON f.device_type_code = dt.code
SET d.firmware_id = f.fw_id
WHERE d.firmware_id IS NULL AND d.firmware_version IS NOT NULL;

DO $$ BEGIN SELECT '=== 011: firmwares table added to cloud ===' as result; END $$;
