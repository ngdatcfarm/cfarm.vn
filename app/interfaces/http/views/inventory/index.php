<?php
/**
 * index.php
 */
$title = 'Kho Vật Tư';
ob_start();
?>
<div class="max-w-lg mx-auto space-y-4">
    <h1 class="text-xl font-bold">📦 Kho Vật Tư</h1>
    <div class="grid grid-cols-2 gap-3">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 border border-gray-100 dark:border-gray-700">
            <div class="text-2xl font-bold text-blue-600"><?= (int)($stats['total_items']??0) ?></div>
            <div class="text-xs text-gray-400 mt-0.5">Loại vật tư</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 border border-gray-100 dark:border-gray-700">
            <div class="text-2xl font-bold text-green-600"><?= (int)($stats['installed_assets']??0) ?></div>
            <div class="text-xs text-gray-400 mt-0.5">Thiết bị đang lắp</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 border border-gray-100 dark:border-gray-700">
            <div class="text-2xl font-bold text-red-500"><?= (int)($stats['broken_assets']??0) ?></div>
            <div class="text-xs text-gray-400 mt-0.5">Thiết bị hỏng</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 border border-gray-100 dark:border-gray-700">
            <div class="text-2xl font-bold text-purple-600"><?= (int)($stats['recent_purchases']??0) ?></div>
            <div class="text-xs text-gray-400 mt-0.5">Nhập kho (30 ngày)</div>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <a href="/inventory/production" class="bg-green-600 text-white rounded-2xl p-4 flex items-center gap-3">
            <span class="text-2xl">🌾</span>
            <div><div class="font-semibold text-sm">Vật tư sản xuất</div><div class="text-xs text-green-100">Cám · Thuốc · Trấu</div></div>
        </a>
        <a href="/inventory/stock" class="bg-yellow-600 text-white rounded-2xl p-4 flex items-center gap-3">
            <span class="text-2xl">📦</span>
            <div><div class="font-semibold text-sm">Tồn kho theo chuồng</div><div class="text-xs text-yellow-100">Xem cám từng chuồng</div></div>
        </a>
        <a href="/inventory/consumable" class="bg-indigo-600 text-white rounded-2xl p-4 flex items-center gap-3">
            <span class="text-2xl">🔧</span>
            <div><div class="font-semibold text-sm">Vật tư tiêu hao</div><div class="text-xs text-indigo-100">ESP32 · Cảm biến · Đèn</div></div>
        </a>
        <a href="/inventory/transactions" class="bg-blue-600 text-white rounded-2xl p-4 flex items-center gap-3">
            <span class="text-2xl">📋</span>
            <div><div class="font-semibold text-sm">Lịch sử giao dịch</div><div class="text-xs text-blue-100">Nhập · Xuất · Điều chỉnh</div></div>
        </a>
    </div>
    <?php if (!empty($low_stock)): ?>
    <div class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-700 rounded-2xl p-4">
        <div class="flex items-center gap-2 mb-3"><span class="text-lg">⚠️</span>
            <span class="font-semibold text-orange-700 dark:text-orange-400 text-sm">Tồn kho thấp (<?= count($low_stock) ?> vật tư)</span>
        </div>
        <?php foreach ($low_stock as $ls): ?>
        <div class="flex items-center justify-between text-sm mt-1">
            <span class="text-gray-700 dark:text-gray-300"><?= e($ls['name']) ?></span>
            <span class="text-orange-600 font-semibold"><?= number_format((float)$ls['central_stock'],1) ?> / <?= number_format((float)$ls['min_stock_alert'],1) ?> <?= e($ls['unit']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($expiring)): ?>
    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-2xl p-4">
        <div class="flex items-center gap-2 mb-3"><span class="text-lg">🔔</span>
            <span class="font-semibold text-yellow-700 dark:text-yellow-400 text-sm">Bảo hành sắp hết (<?= count($expiring) ?> thiết bị)</span>
        </div>
        <?php foreach ($expiring as $exp): ?>
        <div class="flex items-center justify-between text-sm mt-1">
            <span class="text-gray-700 dark:text-gray-300"><?= e($exp['item_name']) ?> #<?= $exp['id'] ?></span>
            <span class="text-yellow-600 font-semibold">hết <?= $exp['warranty_until'] ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
            <span class="font-semibold text-sm">Giao dịch gần đây</span>
        </div>
        <?php if (empty($recent_txns)): ?>
        <div class="text-center py-8 text-gray-400 text-sm">Chưa có giao dịch nào</div>
        <?php else: ?>
        <div class="divide-y divide-gray-50 dark:divide-gray-700">
        <?php foreach ($recent_txns as $txn):
            $tl = match($txn['txn_type']) {
                'purchase'=>['🟢','Nhập kho'],'transfer_out'=>['🔵','Xuất barn'],
                'use_feed'=>['🟡','Dùng cám'],'use_medicine'=>['🟠','Dùng thuốc'],
                'use_litter'=>['⚪','Dùng trấu'],'sell'=>['🔴','Bán ra'],
                'adjust'=>['⚫','Điều chỉnh'],default=>['⚪',$txn['txn_type']]
            }; ?>
        <div class="px-4 py-3 flex items-center gap-3">
            <span><?= $tl[0] ?></span>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate"><?= e($txn['item_name']) ?></div>
                <div class="text-xs text-gray-400"><?= $tl[1] ?> · <?= date('d/m H:i',strtotime($txn['recorded_at'])) ?></div>
            </div>
            <span class="text-sm font-semibold text-gray-600 dark:text-gray-300"><?= number_format((float)$txn['quantity'],1) ?> <?= e($txn['unit']) ?></span>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden divide-y divide-gray-100 dark:divide-gray-700">
        <a href="/settings/inventory-items" class="flex items-center gap-4 p-4 hover:bg-gray-50 dark:hover:bg-gray-700">
            <div class="w-10 h-10 bg-blue-50 dark:bg-blue-900/30 rounded-xl flex items-center justify-center">📋</div>
            <div class="flex-1"><div class="text-sm font-semibold">Danh mục vật tư</div><div class="text-xs text-gray-400">Thêm · sửa · phân loại</div></div>
            <span class="text-gray-300">›</span>
        </a>
        <a href="/settings/suppliers" class="flex items-center gap-4 p-4 hover:bg-gray-50 dark:hover:bg-gray-700">
            <div class="w-10 h-10 bg-green-50 dark:bg-green-900/30 rounded-xl flex items-center justify-center">🏪</div>
            <div class="flex-1"><div class="text-sm font-semibold">Nhà cung cấp</div><div class="text-xs text-gray-400">Tên · SĐT · địa chỉ</div></div>
            <span class="text-gray-300">›</span>
        </a>
    </div>
</div>
<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
