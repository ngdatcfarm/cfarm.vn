-- Fix: Add missing 'active' column to push_subscriptions table
-- This column is expected by push_service.php but was missing from the CREATE TABLE script

ALTER TABLE push_subscriptions
ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER user_label;

-- Also add last_used_at column for tracking subscription activity
ALTER TABLE push_subscriptions
ADD COLUMN last_used_at TIMESTAMP NULL AFTER active;

SELECT 'Added active and last_used_at columns to push_subscriptions' AS status;
