<?php
$title = 'Thiết bị IoT';
ob_start();

$by_barn = [];
foreach ($devices as $d) {
    $by_barn[$d->barn_name ?? 'Chưa gán chuồng'][] = $d;
}
$online  = count(array_filter($devices, fn($d) => $d->is_online));
$offline = count($devices) - $online;
$total   = count($devices);
?>

<div class="mb-4 flex items-center justify-between">
    <a href="/settings/iot" class="text-sm text-blue-600 hover:underline">← IoT Settings</a>
    <a href="/iot/nodes/create" class="bg-blue-600 text-white text-xs font-semibold px-3 py-1.5 rounded-full">+ Thêm node</a>
</div>

<!-- Stats -->
<div class="grid grid-cols-3 gap-2 mb-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 text-center border border-gray-100 dark:border-gray-700">
        <div class="text-xl font-bold text-green-500"><?= $online ?></div>
        <div class="text-xs text-gray-400">Online</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 text-center border border-gray-100 dark:border-gray-700">
        <div class="text-xl font-bold text-red-400"><?= $offline ?></div>
        <div class="text-xs text-gray-400">Offline</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 text-center border border-gray-100 dark:border-gray-700">
        <div class="text-xl font-bold text-blue-500"><?= $total ?></div>
        <div class="text-xs text-gray-400">Tổng</div>
    </div>
</div>

<?php if (empty($devices)): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl p-8 text-center border border-gray-100 dark:border-gray-700">
    <div class="text-4xl mb-3">📡</div>
    <div class="text-sm font-semibold text-gray-500 mb-2">Chưa có thiết bị nào</div>
    <a href="/iot/nodes/create" class="text-xs text-blue-600">+ Thêm node mới</a>
</div>
<?php endif; ?>

<?php foreach ($by_barn as $barn_name => $barn_devices): ?>
<div class="mb-4">
    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2 px-1">
        🏠 <?= e($barn_name) ?>
        <span class="font-normal normal-case ml-1">
            (<?= count(array_filter($barn_devices, fn($d) => $d->is_online)) ?>/<?= count($barn_devices) ?> online)
        </span>
    </div>

    <div class="space-y-2">
    <?php foreach ($barn_devices as $d): ?>
    <?php
        $is_online  = (bool)$d->is_online;
        $is_sensor  = ($d->device_class ?? '') === 'sensor';
        $is_relay   = ($d->device_class ?? '') === 'relay';
        $icon       = $is_sensor ? '🌡️' : ($is_relay ? '🔌' : '📡');
        $dot_color  = $is_online ? 'bg-green-400' : 'bg-gray-300 dark:bg-gray-600';
        $icon_bg    = $is_online ? 'bg-green-50 dark:bg-green-900/20' : 'bg-gray-100 dark:bg-gray-700';
    ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4"
         id="device-<?= $d->id ?>">

        <!-- Row 1: icon + info + status -->
        <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-3">
                <div class="relative shrink-0">
                    <div class="w-10 h-10 rounded-xl <?= $icon_bg ?> flex items-center justify-center text-xl"><?= $icon ?></div>
                    <div class="absolute -top-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-white dark:border-gray-800 <?= $dot_color ?>"></div>
                </div>
                <div>
                    <div class="font-semibold text-sm"><?= e($d->device_code) ?></div>
                    <div class="text-xs text-gray-400"><?= e($d->name) ?></div>
                    <?php if ($d->type_name): ?>
                    <div class="text-xs text-blue-400 mt-0.5"><?= e($d->type_name) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-right shrink-0">
                <div class="text-xs font-semibold <?= $is_online ? 'text-green-500' : 'text-gray-400' ?>">
                    <?= $is_online ? '● Online' : '○ Offline' ?>
                </div>
                <?php if ($d->last_heartbeat_at): ?>
                <div class="text-xs text-gray-300 dark:text-gray-600 mt-0.5 max-w-[90px] truncate">
                    <?= e(date('H:i d/m', strtotime($d->last_heartbeat_at))) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_online): ?>
        <!-- Row 2: metrics (chỉ khi online) -->
        <div class="grid grid-cols-3 gap-1.5 mb-3">
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-2 text-center">
                <div class="text-xs font-bold"><?= $d->wifi_rssi ? $d->wifi_rssi . 'dB' : '—' ?></div>
                <div class="text-xs text-gray-400">WiFi</div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-2 text-center">
                <div class="text-xs font-bold truncate"><?= $d->ip_address ? e($d->ip_address) : '—' ?></div>
                <div class="text-xs text-gray-400">IP</div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-2 text-center">
                <div class="text-xs font-bold"><?= $d->uptime_seconds ? gmdate('H:i', (int)$d->uptime_seconds) : '—' ?></div>
                <div class="text-xs text-gray-400">Uptime</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($d->channels)): ?>
        <!-- Row 3: relay channels -->
        <div class="flex flex-wrap gap-1 mb-3">
            <?php foreach ($d->channels as $ch): ?>
            <?php $on = ($ch->state ?? 'off') === 'on'; ?>
            <div class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs
                        <?= $on ? 'bg-green-100 dark:bg-green-900/30 text-green-700' : 'bg-gray-100 dark:bg-gray-700 text-gray-400' ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $on ? 'bg-green-500' : 'bg-gray-400' ?>"></span>
                CH<?= $ch->channel_number ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Row 4: action buttons -->
        <div class="flex gap-2">
            <?php if ($is_sensor): ?>
            <a href="/iot/sensor/<?= $d->id ?>"
               class="flex-1 text-center text-xs font-semibold py-2 rounded-xl bg-teal-50 dark:bg-teal-900/20 text-teal-600">
                📊 Dữ liệu
            </a>
            <?php elseif ($is_relay && $d->barn_id): ?>
            <a href="/iot/control/<?= $d->barn_id ?>"
               class="flex-1 text-center text-xs font-semibold py-2 rounded-xl bg-purple-50 dark:bg-purple-900/20 text-purple-600">
                🕹️ Điều khiển
            </a>
            <?php else: ?>
            <div class="flex-1"></div>
            <?php endif; ?>

            <a href="/iot/nodes/<?= $d->id ?>/edit"
               class="flex-1 text-center text-xs font-semibold py-2 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-500">
                ✏️ Sửa
            </a>
            <a href="/settings/iot/firmware/<?= $d->id ?>"
               class="flex-1 text-center text-xs font-semibold py-2 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600">
                💾 Firmware
            </a>
        </div>

    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<script>
// Auto-refresh mỗi 60 giây
let refreshTimer = setTimeout(() => location.reload(), 60000);

// Reset timer khi user đang dùng
document.addEventListener('touchstart', () => {
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(() => location.reload(), 60000);
});
</script>

<?php $content = ob_get_clean(); require view_path('layouts/main.php'); ?>
