<?php
/**
 * Nightly Snapshot & Reconcile
 *
 * 1. Recalculate snapshot cho tất cả cycles active (đảm bảo data luôn chính xác)
 * 2. Reconcile current_quantity cho tất cả cycles active (chống drift)
 *
 * Crontab: 0 2 * * * php /var/www/app.cfarm.vn/app/domains/snapshot/nightly_snapshot.php >> /var/log/cfarm-nightly.log 2>&1
 */

declare(strict_types=1);

require_once __DIR__ . '/../../shared/database/mysql.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Domains\Snapshot\SnapshotService;
use App\Infrastructure\Persistence\Mysql\Repositories\CycleRepository;

$snapshot_svc = new SnapshotService($pdo);
$cycle_repo   = new CycleRepository($pdo);

echo "=== Nightly Snapshot & Reconcile: " . date('Y-m-d H:i:s') . " ===\n";

// 1. Lấy tất cả cycles active
$active_cycles = $pdo->query("SELECT id, code, current_quantity FROM cycles WHERE status = 'active'")->fetchAll();

if (empty($active_cycles)) {
    echo "No active cycles.\n";
    exit;
}

echo count($active_cycles) . " active cycle(s) found.\n\n";

foreach ($active_cycles as $c) {
    $cycle_id = (int)$c['id'];
    $code     = $c['code'];
    $old_qty  = (int)$c['current_quantity'];

    // Recalculate snapshot từ đầu
    echo "[{$code}] Recalculating snapshots... ";
    $start = microtime(true);
    $snapshot_svc->recalculate_cycle($cycle_id);
    $elapsed = round((microtime(true) - $start) * 1000);
    echo "done ({$elapsed}ms)\n";

    // Reconcile quantity
    $new_qty = $cycle_repo->reconcile_quantity($cycle_id);
    if ($old_qty !== $new_qty) {
        echo "[{$code}] ⚠ QUANTITY DRIFT FIXED: {$old_qty} → {$new_qty}\n";
    } else {
        echo "[{$code}] Quantity OK: {$new_qty}\n";
    }

    echo "\n";
}

echo "=== Done: " . date('Y-m-d H:i:s') . " ===\n";
