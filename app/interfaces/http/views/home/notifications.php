<?php
$title = 'Thông báo';
ob_start();

$type_icons = [
    'device_offline' => '⚠️',
    'default'        => '📳',
];
$type_colors = [
    'device_offline' => 'bg-red-100 dark:bg-red-900/30',
    'default'        => 'bg-blue-100 dark:bg-blue-900/30',
];

// Group theo ngày
$grouped = [];
foreach ($notifications as $n) {
    $day = date('d/m/Y', strtotime($n->sent_at));
    $grouped[$day][] = $n;
}
?>

<div class="mb-4 flex items-center justify-between">
    <a href="/" class="text-sm text-blue-600 hover:underline">← Trang chủ</a>
    <div class="text-xs text-gray-400"><?= count($notifications) ?> thông báo gần đây</div>
</div>

<!-- Header -->
<div class="bg-gray-800 rounded-2xl p-4 mb-4">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-xl">📳</div>
        <div>
            <div class="font-bold text-white">Lịch sử thông báo</div>
            <div class="text-xs text-gray-400">100 thông báo gần nhất</div>
        </div>
    </div>
</div>

<?php if (empty($notifications)): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl p-8 text-center border border-gray-100 dark:border-gray-700">
    <div class="text-4xl mb-3">🔕</div>
    <div class="text-sm text-gray-500">Chưa có thông báo nào</div>
</div>
<?php else: ?>

<?php foreach ($grouped as $day => $items): ?>
<div class="mb-4">
    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2 px-1">
        <?= $day === date('d/m/Y') ? 'Hôm nay' : ($day === date('d/m/Y', strtotime('-1 day')) ? 'Hôm qua' : $day) ?>
        <span class="font-normal normal-case">(<?= count($items) ?>)</span>
    </div>
    <div class="space-y-2">
    <?php foreach ($items as $n): ?>
    <?php
        $icon  = $type_icons[$n->type]  ?? $type_icons['default'];
        $color = $type_colors[$n->type] ?? $type_colors['default'];
    ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl <?= $color ?> flex items-center justify-center text-lg shrink-0">
                <?= $icon ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold"><?= e($n->title) ?></div>
                <div class="text-xs text-gray-500 mt-0.5"><?= e($n->body) ?></div>
                <div class="flex items-center gap-3 mt-2 text-xs text-gray-300">
                    <span><?= date('H:i:s', strtotime($n->sent_at)) ?></span>
                    <span>·</span>
                    <span><?= $n->sent_count ?> thiết bị nhận</span>
                    <?php if ($n->failed_count > 0): ?>
                    <span class="text-red-400"><?= $n->failed_count ?> lỗi</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
