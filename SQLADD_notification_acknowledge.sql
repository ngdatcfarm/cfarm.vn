-- Thêm cột acknowledged cho push notifications
-- Device offline notifications sẽ lặp mỗi 1 phút cho đến khi user xác nhận đã biết

USE cfarm_app_raw;

ALTER TABLE push_notifications_log
    ADD COLUMN acknowledged_at DATETIME NULL COMMENT 'Thời điểm user xác nhận đã biết' AFTER failed_count;
