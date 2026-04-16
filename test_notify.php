<?php
/**
 * Test notification endpoint - git push lên cloud rồi chạy:
 * php test_notify.php
 */

require __DIR__ . '/vendor/autoload.php';

echo "=== Testing Cloud Push Notification ===\n\n";

try {
    // Connect to database (same as app/shared/database/mysql.php)
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=cfarm_app_raw;charset=utf8mb4',
        'cfarm_user',
        'cfarm_pass',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "[OK] Database connected\n";

    // Check push_subscriptions
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM push_subscriptions WHERE active=1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "[INFO] Active push_subscriptions: {$row['cnt']}\n";

    // Check VAPID config
    $cfg = require __DIR__ . '/app/config.php';
    echo "[INFO] VAPID configured: " . (empty($cfg['vapid_public']) ? 'NO' : 'YES') . "\n";
    echo "[INFO] VAPID subject: {$cfg['vapid_subject']}\n";

    // Create PushService
    $push = new \App\Domains\Intelligence\PushService($pdo);
    echo "[OK] PushService created\n";

    // Send test notification
    echo "\n[SENDING] Test notification...\n";
    $push->send_all('TEST', 'Test Title từ Cloud', 'Nội dung test notification', null, '/');
    echo "[OK] send_all() completed\n";

} catch (\Throwable $e) {
    echo "[ERROR] {$e->getMessage()}\n";
    echo "[FILE] {$e->getFile()}:{$e->getLine()}\n";
    echo "[TRACE]\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Done ===\n";
