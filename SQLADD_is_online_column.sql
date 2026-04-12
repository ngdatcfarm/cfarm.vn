-- Add is_online column to devices table (cloud DB)
ALTER TABLE devices
    ADD COLUMN is_online TINYINT(1) DEFAULT 0 AFTER device_type_id;
