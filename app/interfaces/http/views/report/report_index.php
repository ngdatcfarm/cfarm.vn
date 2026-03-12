<?php
$title = 'Báo cáo';
ob_start();
?>

<div class="max-w-lg mx-auto">
    <div class="flex items-center gap-3 mb-5">
        <a href="/barns" class="text-gray-400 hover:text-gray-600">←</a>
        <h1 class="text-xl font-bold">📊 Báo cáo</h1>
    </div>

    <div class="space-y-3">
        <?php foreach ($cycles as $c): ?>
        <a href="/reports/<?= e($c['id']) ?>"
           class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm px-4 py-3 hover:border-blue-400 transition-colors">
            <div>
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-sm"><?= e($c['code']) ?></span>
                    <span class="text-xs px-2 py-0.5 rounded-full <?= $c['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                        <?= $c['status'] === 'active' ? 'Đang nuôi' : 'Đã đóng' ?>
                    </span>
                </div>
                <div class="text-xs text-gray-400 mt-0.5">
                    <?= e($c['barn_name']) ?> · Bắt đầu <?= e($c['start_date']) ?>
                </div>
            </div>
            <div class="text-right">
                <div class="text-sm font-medium"><?= number_format($c['current_quantity']) ?> con</div>
                <div class="text-xs text-gray-400">→</div>
            </div>
        </a>
        <?php endforeach; ?>

        <?php if (empty($cycles)): ?>
        <div class="text-center py-16 text-gray-400 text-sm">Chưa có vòng nuôi nào</div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
