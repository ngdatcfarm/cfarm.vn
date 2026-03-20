-- Thêm cột env_interval_seconds vào bảng devices (nếu chưa có)
-- Chạy trên production: mysql -u root cfarm_app_raw < SQL_add_env_interval.sql

ALTER TABLE devices
    ADD COLUMN env_interval_seconds INT NOT NULL DEFAULT 300
    COMMENT 'Tần suất gửi ENV (giây)'
    AFTER free_heap_bytes;
