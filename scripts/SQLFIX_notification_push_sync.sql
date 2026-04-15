-- =====================================================
-- Cloud Sync Fix - Notification Settings & Push Subscriptions
-- Run on cloud MySQL to enable sync of notification settings and push subscriptions
-- from local server to cloud so cloud can send push notifications to remote devices.
-- =====================================================

-- Drop existing tables if they have old schema (without `key` column)
-- This handles the case where table existed but with different structure
DROP TABLE IF EXISTS notification_settings;
DROP TABLE IF EXISTS push_subscriptions;

-- notification_settings: key-value store for notification toggles (feed/weight/vaccine/medication)
CREATE TABLE notification_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notification_settings_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- push_subscriptions: Web Push subscription storage for remote notification delivery
CREATE TABLE push_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    endpoint TEXT NOT NULL UNIQUE,
    p256dh TEXT NOT NULL DEFAULT '',
    auth TEXT NOT NULL DEFAULT '',
    user_label VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_push_subscriptions_endpoint (endpoint(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default vaccine notification setting
INSERT INTO notification_settings (`key`, `value`)
VALUES ('vaccine_notifications_enabled', 'true')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

SELECT 'Notification settings & push subscriptions sync tables created!' AS status;
