-- Fix double-encoded Vietnamese bat names in cloud MySQL
-- The bats names were corrupted during INSERT (UTF-8 bytes → Latin-1 → stored as-is)
-- Run: mysql -u cfarm_user -pcfarm_pass cfarm_app_raw < scripts/fix_bats_utf8.sql

UPDATE bats
SET
    -- Reverse double-encoding: Latin-1 interpretation of corrupted UTF-8 bytes
    -- back to the original UTF-8 Vietnamese characters
    name = CONVERT(
        CAST(CONVERT(name USING latin1) AS BINARY)
        USING utf8mb4
    )
WHERE id IN (1, 2, 3, 4);

-- Verify
SELECT id, name, HEX(name) FROM bats;
