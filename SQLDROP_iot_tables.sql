-- ============================================================
-- DROP ALL IOT TABLES
-- Run this SQL on cloud database to clean IoT data
-- ============================================================

-- Disable FK checks
SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables in correct order (child tables first)
DROP TABLE IF EXISTS device_firmware_allocations;
DROP TABLE IF EXISTS device_firmwares;
DROP TABLE IF EXISTS device_state_log;
DROP TABLE IF EXISTS device_states;
DROP TABLE IF EXISTS device_commands;
DROP TABLE IF EXISTS device_channels;
DROP TABLE IF EXISTS curtain_configs;
DROP TABLE IF EXISTS device_types;
DROP TABLE IF EXISTS devices;

-- Re-enable FK checks
SET FOREIGN_KEY_CHECKS = 1;

-- Verify
SHOW TABLES LIKE 'device%';
SHOW TABLES LIKE 'curtain%';
