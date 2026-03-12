<?php require view_path('layouts/main.php'); ?>

<div class="px-4 pt-4 pb-24">
    <div class="flex items-center gap-3 mb-5">
        <a href="/settings" class="text-gray-400 hover:text-gray-600">←</a>
        <div class="text-lg font-bold">🔔 Cài đặt thông báo</div>
    </div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 rounded-xl px-4 py-3 mb-4 text-sm text-green-700">
        ✅ Đã lưu cài đặt
    </div>
    <?php endif; ?>

    <!-- Legend -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <div class="text-xs font-semibold text-gray-500 mb-3">Mức độ ưu tiên</div>
        <div class="space-y-2">
            <div class="flex items-center gap-3">
                <span class="w-3 h-3 rounded-full bg-red-500 flex-shrink-0"></span>
                <div>
                    <div class="text-xs font-semibold">🔴 Khẩn cấp</div>
                    <div class="text-xs text-gray-400">Gửi mỗi 1 phút cho đến khi xử lý</div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="w-3 h-3 rounded-full bg-orange-500 flex-shrink-0"></span>
                <div>
                    <div class="text-xs font-semibold">🟠 Cần chú ý</div>
                    <div class="text-xs text-gray-400">Gửi theo chu kỳ (mặc định 1 giờ)</div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="w-3 h-3 rounded-full bg-blue-500 flex-shrink-0"></span>
                <div>
                    <div class="text-xs font-semibold">🔵 Thông báo</div>
                    <div class="text-xs text-gray-400">Gửi 1 lần/ngày vào giờ cố định</div>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="/settings/notifications/update">
        <div class="space-y-3 mb-5">
        <?php foreach ($settings as $s): ?>
        <?php
        $level_color = [
            'red'    => 'border-red-200 dark:border-red-800',
            'orange' => 'border-orange-200 dark:border-orange-800',
            'blue'   => 'border-blue-200 dark:border-blue-800',
        ][$s['level']] ?? 'border-gray-200';
        $level_dot = [
            'red'    => 'bg-red-500',
            'orange' => 'bg-orange-500',
            'blue'   => 'bg-blue-500',
        ][$s['level']] ?? 'bg-gray-400';
        ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border <?= $level_color ?> p-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full <?= $level_dot ?>"></span>
                    <div class="text-sm font-semibold"><?= e($s['label']) ?></div>
                </div>
                <!-- Toggle enabled -->
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="settings[<?= $s['id'] ?>][enabled]" value="1"
                           <?= $s['enabled'] ? 'checked' : '' ?>
                           class="sr-only peer">
                    <div class="w-10 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer
                                peer-checked:after:translate-x-full peer-checked:bg-blue-600
                                after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                </label>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <!-- Level -->
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Mức độ</label>
                    <select name="settings[<?= $s['id'] ?>][level]"
                            class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="red"    <?= $s['level']==='red'    ? 'selected' : '' ?>>🔴 Khẩn cấp</option>
                        <option value="orange" <?= $s['level']==='orange' ? 'selected' : '' ?>>🟠 Cần chú ý</option>
                        <option value="blue"   <?= $s['level']==='blue'   ? 'selected' : '' ?>>🔵 Thông báo</option>
                    </select>
                    <input type="hidden" name="settings[<?= $s['id'] ?>][interval_min]"
                           value="<?= e($s['interval_min']) ?>">
                </div>

                <!-- Giờ gửi hoặc interval -->
                <div>
                    <?php if ($s['send_at_hour'] !== null): ?>
                    <label class="text-xs text-gray-400 mb-1 block">Gửi lúc</label>
                    <select name="settings[<?= $s['id'] ?>][send_at_hour]"
                            class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <?php for ($h = 0; $h < 24; $h++): ?>
                        <option value="<?= $h ?>" <?= (int)$s['send_at_hour'] === $h ? 'selected' : '' ?>>
                            <?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00
                        </option>
                        <?php endfor; ?>
                    </select>
                    <?php else: ?>
                    <label class="text-xs text-gray-400 mb-1 block">Mỗi (phút)</label>
                    <input type="hidden" name="settings[<?= $s['id'] ?>][send_at_hour]" value="">
                    <select name="settings[<?= $s['id'] ?>][interval_min]"
                            class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="1"    <?= $s['interval_min']==1    ? 'selected' : '' ?>>1 phút</option>
                        <option value="15"   <?= $s['interval_min']==15   ? 'selected' : '' ?>>15 phút</option>
                        <option value="30"   <?= $s['interval_min']==30   ? 'selected' : '' ?>>30 phút</option>
                        <option value="60"   <?= $s['interval_min']==60   ? 'selected' : '' ?>>1 giờ</option>
                        <option value="120"  <?= $s['interval_min']==120  ? 'selected' : '' ?>>2 giờ</option>
                        <option value="360"  <?= $s['interval_min']==360  ? 'selected' : '' ?>>6 giờ</option>
                        <option value="1440" <?= $s['interval_min']==1440 ? 'selected' : '' ?>>1 ngày</option>
                    </select>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-2xl transition-colors">
            💾 Lưu cài đặt
        </button>
    </form>
</div>
