-- =====================================================
-- Cloud Sync Fix - Notification Settings & Push Subscriptions
-- Run on cloud MySQL to enable sync of notification settings and push subscriptions
-- from local server to cloud so cloud can send push notifications to remote devices.
-- =====================================================

-- notification_settings: key-value store for notification toggles (feed/weight/vaccine/medication)
-- Local table: id, key (unique), value, updated_at
CREATE TABLE IF NOT EXISTS notification_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notification_settings_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default vaccine notification setting if not exists
INSERT IGNORE INTO notification_settings (`key`, `value`)
VALUES ('vaccine_notifications_enabled', 'true');

-- push_subscriptions: Web Push subscription storage for remote notification delivery
-- Local table: id, endpoint (unique), p256dh, auth, user_label, created_at
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    endpoint TEXT NOT NULL UNIQUE,
    p256dh TEXT NOT NULL DEFAULT '',
    auth TEXT NOT NULL DEFAULT '',
    user_label VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_push_subscriptions_endpoint (endpoint(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SELECT 'Notification settings & push subscriptions sync tables created!' AS status;
