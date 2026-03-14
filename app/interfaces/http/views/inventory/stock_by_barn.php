<?php
/**
 * stock_by_barn.php - Tồn kho theo chuồng
 */
$title = 'Tồn Kho Theo Chuồng';
ob_start();
?>

<div class="max-w-6xl mx-auto space-y-4">
    <div class="flex justify-between items-center">
        <h1 class="text-xl font-bold">📦 Tồn Kho Theo Chuồng</h1>
        <a href="/inventory" class="text-blue-600 hover:underline text-sm">← Quay lại</a>
    </div>

    <!-- Tồn kho trung tâm -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-100 dark:border-gray-600">
            <h2 class="font-semibold text-sm">🏭 Kho Trung Tâm</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-3 py-2 text-left">Mã cám</th>
                        <th class="px-3 py-2 text-left">Tên</th>
                        <th class="px-3 py-2 text-right">Tồn kho</th>
                        <th class="px-3 py-2 text-center">Đơn vị</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feed_items as $item): ?>
                        <?php $qty = $central_stock[$item['id']] ?? 0; ?>
                        <?php if ($qty > 0): ?>
                        <tr class="<?= $qty < 10 ? 'bg-red-50 dark:bg-red-900/20' : '' ?>">
                            <td class="px-3 py-2"><?= e($item['feed_code'] ?? '') ?></td>
                            <td class="px-3 py-2"><?= e($item['name']) ?></td>
                            <td class="px-3 py-2 text-right font-medium <?= $qty < 10 ? 'text-red-600' : '' ?>">
                                <?= number_format($qty, 1) ?>
                            </td>
                            <td class="px-3 py-2 text-center"><?= e($item['unit']) ?></td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (empty(array_filter($central_stock))): ?>
                    <tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">Không có tồn kho trung tâm</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tồn kho theo chuồng -->
    <?php foreach ($barns as $barn): ?>
    <?php $has_stock = false; foreach ($feed_items as $item) { if (($stock_by_barn[$barn['id']][$item['id']] ?? 0) > 0) { $has_stock = true; break; } } ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-100 dark:border-gray-600">
            <h2 class="font-semibold text-sm">🏠 <?= e($barn['name']) ?></h2>
        </div>
        <?php if (!$has_stock): ?>
            <div class="px-4 py-4 text-center text-gray-500 italic">Chưa có tồn kho</div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-3 py-2 text-left">Mã cám</th>
                        <th class="px-3 py-2 text-left">Tên</th>
                        <th class="px-3 py-2 text-right">Tồn kho</th>
                        <th class="px-3 py-2 text-center">Đơn vị</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feed_items as $item): ?>
                        <?php $qty = $stock_by_barn[$barn['id']][$item['id']] ?? 0; ?>
                        <?php if ($qty > 0): ?>
                        <tr class="<?= $qty < 10 ? 'bg-red-50 dark:bg-red-900/20' : '' ?>">
                            <td class="px-3 py-2"><?= e($item['feed_code'] ?? '') ?></td>
                            <td class="px-3 py-2"><?= e($item['name']) ?></td>
                            <td class="px-3 py-2 text-right font-medium <?= $qty < 10 ? 'text-red-600' : '' ?>">
                                <?= number_format($qty, 1) ?>
                            </td>
                            <td class="px-3 py-2 text-center"><?= e($item['unit']) ?></td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- Tồn kho trấu -->
    <?php if (!empty($litter_items)): ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-100 dark:border-gray-600">
            <h2 class="font-semibold text-sm">🌾 Trấu / Litter</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-3 py-2 text-left">Tên vật tư</th>
                        <th class="px-3 py-2 text-right">Kho trung tâm</th>
                        <?php foreach ($barns as $barn): ?>
                        <th class="px-3 py-2 text-right"><?= e($barn['name']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($litter_items as $item): ?>
                    <tr>
                        <td class="px-3 py-2"><?= e($item['name']) ?></td>
                        <td class="px-3 py-2 text-right"><?= number_format($central_stock_litter[$item['id']] ?? 0, 1) ?></td>
                        <?php foreach ($barns as $barn): ?>
                        <td class="px-3 py-2 text-right"><?= number_format($stock_by_barn_litter[$barn['id']][$item['id']] ?? 0, 1) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($barns)): ?>
    <div class="text-center py-8 text-gray-500">
        <p>Chưa có chuồng nào. <a href="/barns/create" class="text-blue-600 hover:underline">Tạo chuồng mới</a></p>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
