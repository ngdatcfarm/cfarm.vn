<?php
/**
 * Sync Controller Fix Patch
 *
 * This file contains the fixes needed for sync_controller.php
 * Run: php sync_fix.patch.php to apply or manually apply the changes below
 *
 * Fix 1: Add missing tables to allowed_tables (CLOUD <- LOCAL push)
 * Fix 2: Add bats and bat_logs to config_tables (CLOUD -> LOCAL pull)
 */

// FIX 1: Add to allowed_tables in apply_change() method
// Add these tables to the $allowed_tables array around line 648:
// After: 'bats', 'bat_logs',
// Add:
//     'weight_sessions', 'weight_details',  -- Weight tracking (Local PRIMARY)
//     'alerts',                             -- Alerts (Local PRIMARY)
//     'care_water_logs',                    -- Water logs (Local PRIMARY)
//     'barn_default_warehouses',            -- Barn-warehouse links (Local PRIMARY)
//     'inventory_alert_rules',             -- Inventory alert rules (Local PRIMARY)

// FIX 2: Add to config_tables in changes() method
// Add to the Tier 3: Operational config section around line 155:
//     'bats', 'bat_logs',

/*
=== MANUAL FIXES TO APPLY TO sync_controller.php ===

FILE: C:\dev\cfarm.vn\app\interfaces\http\controllers\web\sync\sync_controller.php

=== FIX 1: Add missing tables to allowed_tables (around line 648) ===

BEFORE:
            // Bats (Local primary → Cloud)
            'bats', 'bat_logs',
        ];

AFTER:
            // Bats (Local primary → Cloud)
            'bats', 'bat_logs',
            // Weight tracking (Local PRIMARY → Cloud)
            'weight_sessions', 'weight_details',
            // Alerts (Local PRIMARY → Cloud)
            'alerts',
            // Water logs (Local PRIMARY → Cloud)
            'care_water_logs',
            // Barn-warehouse links (Local PRIMARY → Cloud)
            'barn_default_warehouses',
            // Inventory alert rules (Local PRIMARY → Cloud)
            'inventory_alert_rules',
        ];

=== FIX 2: Add bats/bat_logs to config_tables (around line 155) ===

BEFORE:
            'care_litters'           => 'updated_at',
            'care_expenses'          => 'updated_at',
            'vaccine_schedules'     => 'updated_at',
            'feed_trough_checks'     => 'updated_at',
            'weight_reminders'       => 'updated_at',
            // Legacy

AFTER:
            'care_litters'           => 'updated_at',
            'care_expenses'          => 'updated_at',
            'vaccine_schedules'     => 'updated_at',
            'feed_trough_checks'     => 'updated_at',
            'weight_reminders'       => 'updated_at',
            // Bats (Cloud <- Local primary sync)
            'bats'                   => 'updated_at',
            'bat_logs'               => 'created_at',
            // Legacy

*/

echo "Sync Controller Fix Patch\n";
echo "==========================\n\n";
echo "This file documents the fixes needed for sync_controller.php\n";
echo "Please manually apply the fixes as described above.\n";
