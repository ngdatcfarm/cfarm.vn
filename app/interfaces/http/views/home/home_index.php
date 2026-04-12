<?php
$title = 'Dashboard - Cloud Control';
ob_start();
?>

<div class="px-4 pt-4 pb-24">

    <!-- Header -->
    <div class="mb-6">
        <div class="text-xl font-bold">🖥️ Cloud Control</div>
        <div class="text-xs text-gray-400">Remote relay & curtain control</div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 gap-3 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 border border-gray-100 dark:border-gray-700">
            <div class="text-3xl font-bold text-blue-600"><?= $device_count ?></div>
            <div class="text-xs text-gray-500">Thiết bị IoT</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 border border-gray-100 dark:border-gray-700">
            <div class="text-3xl font-bold text-green-600"><?= $online_count ?></div>
            <div class="text-xs text-gray-500">Online</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mb-6">
        <div class="text-sm font-semibold mb-3">⚡ Điều khiển nhanh</div>
        <div class="grid grid-cols-2 gap-3">
            <a href="/iot/devices"
               class="bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl p-4 text-center active:scale-95 transition-transform">
                <div class="text-2xl mb-1">📟</div>
                <div class="text-xs font-semibold text-indigo-700 dark:text-indigo-300">Thiết bị</div>
            </a>
            <a href="/iot/control"
               class="bg-emerald-50 dark:bg-emerald-900/30 rounded-2xl p-4 text-center active:scale-95 transition-transform">
                <div class="text-2xl mb-1">🎛️</div>
                <div class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">Điều khiển</div>
            </a>
        </div>
    </div>

    <!-- Recent Notifications -->
    <div class="mb-6">
        <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-semibold">🔔 Thông báo gần đây</div>
            <a href="/notifications" class="text-xs text-blue-500 hover:underline">Xem tất cả</a>
        </div>
        <?php if (empty($recent_notifications)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700 text-center text-gray-400">
            <div class="text-3xl mb-2">🔕</div>
            <div class="text-sm">Chưa có thông báo nào</div>
        </div>
        <?php else: ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <?php foreach (array_slice($recent_notifications, 0, 5) as $n): ?>
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                <div class="text-sm font-medium"><?= htmlspecialchars($n->title ?? 'Notification') ?></div>
                <div class="text-xs text-gray-400 mt-1"><?= time_ago($n->sent_at) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Online Devices -->
    <div class="mb-6">
        <div class="text-sm font-semibold mb-3">📡 Trạng thái thiết bị</div>
        <?php if (empty($devices)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700 text-center text-gray-400">
            <div class="text-3xl mb-2">📡</div>
            <div class="text-sm">Chưa có thiết bị nào</div>
            <a href="/settings/iot" class="text-xs text-blue-500 hover:underline mt-2 inline-block">Thêm thiết bị</a>
        </div>
        <?php else: ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <?php foreach ($devices as $d): ?>
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-0 flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium"><?= htmlspecialchars($d->name) ?></div>
                    <div class="text-xs text-gray-400"><?= htmlspecialchars($d->device_code) ?></div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full <?= $d->is_online ? 'bg-green-500' : 'bg-gray-300' ?>"></span>
                    <span class="text-xs <?= $d->is_online ? 'text-green-600' : 'text-gray-400' ?>">
                        <?= $d->is_online ? 'Online' : 'Offline' ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');

// Helper function
function time_ago(string $datetime): string {
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) return 'Vừa xong';
    if ($diff < 3600) return floor($diff / 60) . ' phút trước';
    if ($diff < 86400) return floor($diff / 3600) . ' giờ trước';
    return floor($diff / 86400) . ' ngày trước';
}
