<?php
$title = 'Điều khiển IoT';
ob_start();
?>

<div class="mb-4">
    <a href="/" class="text-sm text-blue-600">← Trang chủ</a>
</div>

<!-- Header -->
<div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl p-4 mb-4">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-2xl">⚡</div>
        <div>
            <div class="text-lg font-bold text-white">Điều khiển IoT</div>
            <div class="text-sm text-blue-200">Quản lý bạt và thiết bị</div>
        </div>
    </div>
</div>

<?php if (empty($barns)): ?>
<div class="text-center py-16 text-gray-400">
    <div class="text-5xl mb-4">🏠</div>
    <p>Chưa có chuồng nào</p>
</div>
<?php else: ?>

<!-- Danh sách chuồng -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
<?php foreach ($barns as $barn): ?>
    <?php
    $bats = $all_bats[$barn->id] ?? [];
    $has_bats = !empty($bats);
    ?>
    <a href="/iot/control/<?= e($barn->id) ?>"
       class="block bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 hover:border-blue-400 transition-colors">
        <div class="flex items-center justify-between mb-2">
            <div class="font-semibold"><?= e($barn->name) ?></div>
            <?php if ($has_bats): ?>
            <span class="text-xs bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300 px-2 py-1 rounded-full">
                <?= count($bats) ?> bạt
            </span>
            <?php else: ?>
            <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 px-2 py-1 rounded-full">
                Chưa cấu hình
            </span>
            <?php endif; ?>
        </div>

        <?php if ($has_bats): ?>
        <div class="space-y-2 mt-3">
            <?php foreach ($bats as $bat): ?>
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-300"><?= e($bat->name) ?></span>
                <div class="flex items-center gap-2">
                    <?php if ($bat->is_online): ?>
                    <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                    <?php else: ?>
                    <span class="w-2 h-2 bg-gray-300 rounded-full"></span>
                    <?php endif; ?>
                    <span class="font-semibold"><?= e($bat->position) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </a>
<?php endforeach; ?>
</div>

<?php endif; ?>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
