<?php
/**
 * app/interfaces/http/views/settings/feed_brands.php
 *
 * Danh sách hãng cám trong phần Cài đặt.
 */
$title = 'Cài đặt — Hãng cám';
ob_start();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-xl font-bold">⚙️ Cài đặt</h1>
        <p class="text-sm text-gray-400 mt-0.5">Quản lý hãng cám & mã cám</p>
    </div>
    <a href="/settings/feed-brands/create"
       class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors">
        + Thêm hãng
    </a>
</div>

<?php if (empty($brands)): ?>
    <div class="text-center py-16 text-gray-400">
        <div class="text-5xl mb-4">🌾</div>
        <p class="text-base">Chưa có hãng cám nào</p>
        <a href="/settings/feed-brands/create"
           class="mt-3 inline-block text-blue-600 hover:underline text-sm">
            Thêm hãng cám đầu tiên
        </a>
    </div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($brands as $brand): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center text-xl">🌾</div>
                    <div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100"><?= e($brand->name) ?></div>
                        <div class="text-xs text-gray-400 mt-0.5">
                            <?= e($brand->kg_per_bag) ?> kg/bao
                            <?php if ($brand->note): ?>
                                · <?= e($brand->note) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs px-2.5 py-1 rounded-full font-medium
                        <?= $brand->status === 'active'
                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                            : 'bg-gray-100 text-gray-400 dark:bg-gray-700' ?>">
                        <?= $brand->status === 'active' ? 'Đang dùng' : 'Ngừng' ?>
                    </span>
                    <a href="/settings/feed-brands/<?= e($brand->id) ?>"
                       class="text-sm text-blue-600 hover:underline font-medium">
                        Chi tiết →
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
