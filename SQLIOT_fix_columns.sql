-- Fix missing columns
ALTER TABLE device_firmwares ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
ALTER TABLE devices ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE device_channels ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE device_commands ADD COLUMN acknowledged_at DATETIME;
ALTER TABLE device_commands ADD COLUMN completed_at DATETIME;
ALTER TABLE curtain_configs ADD COLUMN last_moved_at DATETIME;
ALTER TABLE curtain_configs ADD COLUMN moving_target_pct TINYINT;
ALTER TABLE curtain_configs ADD COLUMN moving_started_at DATETIME;
ALTER TABLE curtain_configs ADD COLUMN moving_duration_seconds DECIMAL(5,1);
ALTER TABLE device_states ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Done
SELECT 'Columns added!' as result;
