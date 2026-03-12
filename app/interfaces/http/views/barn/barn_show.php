<?php
/**
 * app/interfaces/http/views/barn/barn_show.php
 *
 * Hiển thị chi tiết một barn gồm thông tin cơ bản,
 * cycle đang active và lịch sử các cycle đã qua.
 */

$title = 'Chi tiết — ' . e($barn->name);
ob_start();

$stage_labels = ['chick' => 'Gà con', 'grower' => 'Gà choai', 'adult' => 'Gà trưởng thành'];
$stage_colors = [
    'chick'  => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
    'grower' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    'adult'  => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
];
?>

<div class="max-w-2xl mx-auto">

    <!-- header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="/barns" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">← Quay lại</a>
            <h1 class="text-2xl font-bold"><?= e($barn->name) ?></h1>
            <span class="text-xs px-2 py-1 rounded-full
                <?= $barn->status === 'active'
                    ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                    : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' ?>">
                <?= $barn->status === 'active' ? 'Hoạt động' : 'Ngừng' ?>
            </span>
        </div>
        <a href="/barns/<?= e($barn->id) ?>/edit"
           class="text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 px-3 py-1.5 rounded-lg">
            Sửa chuồng
        </a>
    </div>

    <!-- thông tin chuồng -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 mb-6">
        <div class="grid grid-cols-3 gap-4 text-center text-sm mb-4">
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                <div class="text-gray-400 text-xs mb-1">Dài</div>
                <div class="font-bold"><?= e($barn->length_m) ?> m</div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                <div class="text-gray-400 text-xs mb-1">Rộng</div>
                <div class="font-bold"><?= e($barn->width_m) ?> m</div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                <div class="text-gray-400 text-xs mb-1">Cao</div>
                <div class="font-bold"><?= e($barn->height_m) ?> m</div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4 text-center text-sm">
            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
                <div class="text-gray-400 text-xs mb-1">Diện tích</div>
                <div class="font-bold text-green-700 dark:text-green-400">
                    <?= e(number_format($barn->area(), 1)) ?> m²
                </div>
            </div>
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                <div class="text-gray-400 text-xs mb-1">Thể tích</div>
                <div class="font-bold text-blue-700 dark:text-blue-400">
                    <?= e(number_format($barn->volume(), 1)) ?> m³
                </div>
            </div>
        </div>
        <?php if ($barn->note): ?>
        <div class="border-t border-gray-100 dark:border-gray-700 mt-4 pt-4 text-sm text-gray-500">
            <?= e($barn->note) ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- cycle đang active -->
    <?php if ($active_cycle): ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-green-300 dark:border-green-700 p-5 mb-4">
        <div class="flex justify-between items-start mb-4">
            <div>
                <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Cycle đang nuôi</div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-bold"><?= e($active_cycle->code) ?></h2>
                    <span class="text-xs px-2 py-0.5 rounded-full <?= $stage_colors[$active_cycle->stage] ?>">
                        <?= $stage_labels[$active_cycle->stage] ?>
                    </span>
                </div>
            </div>
            <a href="/cycles/<?= e($active_cycle->id) ?>"
               class="text-sm text-green-600 hover:underline">
                Chi tiết →
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center text-sm">
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                <div class="text-2xl font-bold text-green-600"><?= e(number_format($active_cycle->current_quantity)) ?></div>
                <div class="text-xs text-gray-400 mt-1">Con hiện tại</div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                <div class="text-2xl font-bold"><?= e($active_cycle->age_in_days()) ?></div>
                <div class="text-xs text-gray-400 mt-1">Ngày tuổi</div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                <div class="text-2xl font-bold text-red-500"><?= e($active_cycle->mortality_rate()) ?>%</div>
                <div class="text-xs text-gray-400 mt-1">Tỷ lệ chết</div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                <div class="text-2xl font-bold"><?= e($active_cycle->start_date) ?></div>
                <div class="text-xs text-gray-400 mt-1">Ngày bắt đầu</div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-6 mb-4 text-center">
        <div class="text-gray-400 mb-3">Chưa có cycle nào đang hoạt động</div>
        <a href="/barns/<?= e($barn->id) ?>/cycles/create"
           class="inline-block bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
            + Tạo cycle mới
        </a>
    </div>
    <?php endif; ?>

    <!-- lịch sử cycle -->
    <?php if (!empty($past_cycles)): ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="font-semibold mb-4 text-sm text-gray-500 uppercase tracking-wide">Lịch sử cycle</h2>
        <div class="space-y-2">
            <?php foreach ($past_cycles as $c): ?>
            <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700 last:border-0 text-sm">
                <div class="flex items-center gap-2">
                    <a href="/cycles/<?= e($c->id) ?>" class="font-medium text-blue-600 hover:underline">
                        <?= e($c->code) ?>
                    </a>
                    <span class="text-xs text-gray-400"><?= e($c->start_date) ?> → <?= e($c->end_date ?? '...') ?></span>
                </div>
                <div class="text-xs text-gray-400">
                    <?= e(number_format($c->initial_quantity)) ?> con
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- action tạo cycle mới nếu đang có active -->
    <?php if ($active_cycle): ?>
    <div class="mt-4 text-center">
        <a href="/barns/<?= e($barn->id) ?>/cycles/create"
           class="text-sm text-gray-400 hover:text-gray-600">
            + Tạo cycle mới (cần close cycle hiện tại trước)
        </a>
    </div>
    <?php endif; ?>

    <!-- IoT section -->
    <div class="mt-4">
        <div class="flex items-center justify-between mb-2">
            <div class="text-sm font-semibold">📡 Thiết bị IoT</div>
            <div class="flex gap-2">
                <?php if (!empty($barn_devices)): ?>
                <a href="/iot/control/<?= $barn->id ?>"
                   class="text-xs bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 font-semibold px-3 py-1.5 rounded-full">
                    🎛️ Điều khiển
                </a>
                <?php endif; ?>
                <a href="/settings/iot?tab=devices"
                   class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 font-semibold px-3 py-1.5 rounded-full">
                    + Thêm ESP32
                </a>
            </div>
        </div>

        <?php if (empty($barn_devices)): ?>
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-2xl border border-dashed border-gray-300 dark:border-gray-600 p-5 text-center">
            <div class="text-2xl mb-2">📡</div>
            <div class="text-sm text-gray-400 mb-3">Chưa có ESP32 nào gán cho chuồng này</div>
            <div class="text-xs text-gray-400 space-y-1 text-left max-w-xs mx-auto">
                <div>1. <a href="/settings/iot/types" class="text-blue-500 hover:underline">Tạo loại thiết bị</a> (nếu chưa có)</div>
                <div>2. <a href="/settings/iot?tab=devices" class="text-blue-500 hover:underline">Thêm ESP32</a> → chọn chuồng này</div>
                <div>3. Thêm cấu hình bạt tại <a href="/settings/iot" class="text-blue-500 hover:underline">Settings IoT</a></div>
                <div>4. Lấy firmware tại <a href="/iot/devices" class="text-blue-500 hover:underline">Dashboard IoT</a></div>
            </div>
        </div>
        <?php else: ?>
        <div class="space-y-2">
            <?php foreach ($barn_devices as $d): ?>
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl <?= $d->is_online ? 'bg-green-100 dark:bg-green-900/30' : 'bg-gray-100 dark:bg-gray-700' ?> flex items-center justify-center text-lg">
                        <?= $d->device_class === 'sensor' ? '🌡️' : '🔌' ?>
                    </div>
                    <div>
                        <div class="text-sm font-semibold"><?= e($d->device_code) ?></div>
                        <div class="text-xs text-gray-400"><?= e($d->type_name ?? $d->device_class) ?></div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs <?= $d->is_online ? 'text-green-500' : 'text-gray-400' ?>">
                        <?= $d->is_online ? '● Online' : '○ Offline' ?>
                    </span>
                    <?php if ($d->device_class === 'sensor'): ?>
                    <a href="/iot/sensor/<?= $d->id ?>" class="text-xs text-teal-500 hover:underline">📊</a>
                    <?php endif; ?>
                    <a href="/settings/iot/firmware/<?= $d->id ?>" class="text-xs text-blue-500 hover:underline">💾</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($barn_curtains)): ?>
        <div class="mt-3 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-3">
            <div class="text-xs font-semibold text-gray-500 mb-2">🪟 Bạt (<?= count($barn_curtains) ?>)</div>
            <?php foreach ($barn_curtains as $c): ?>
            <div class="text-xs text-gray-400 py-1 border-t border-gray-50 dark:border-gray-700 first:border-0">
                <?= e($c->name) ?> — <?= e($c->up_device) ?> ↑CH<?= $c->up_ch ?> / ↓CH<?= $c->down_ch ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>


</div>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
