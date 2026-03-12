<?php require view_path('layouts/main.php'); ?>
<?php
$method_labels = ['drink'=>'💧 Uống','inject'=>'💉 Tiêm','eye_drop'=>'👁️ Nhỏ mắt','spray'=>'🌫️ Phun'];
?>
<div class="px-4 pt-4 pb-24">
    <div class="flex items-center gap-3 mb-5">
        <a href="/settings/vaccine-programs" class="text-gray-400 hover:text-gray-600">←</a>
        <div class="text-lg font-bold">📋 <?= e($program['name']) ?></div>
    </div>

    <!-- Edit tên -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <form method="POST" action="/settings/vaccine-programs/<?= $program['id'] ?>/update" class="space-y-3">
            <input type="text" name="name" value="<?= e($program['name']) ?>" required
                   class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <input type="text" name="note" value="<?= e($program['note'] ?? '') ?>" placeholder="Ghi chú..."
                   class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-blue-600 text-white text-sm font-semibold py-2.5 rounded-xl">💾 Lưu</button>
                <form method="POST" action="/settings/vaccine-programs/<?= $program['id'] ?>/delete"
                      onsubmit="return confirm('Xóa bộ lịch này?')" class="flex-1">
                    <button type="submit" class="w-full bg-red-50 text-red-600 text-sm font-semibold py-2.5 rounded-xl">🗑️ Xóa</button>
                </form>
            </div>
        </form>
    </div>

    <!-- Danh sách vaccines -->
    <div class="text-sm font-semibold mb-3">Lịch tiêm (<?= count($items) ?> vaccine)</div>
    <div class="space-y-2 mb-4">
        <?php foreach ($items as $item): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="text-sm font-semibold"><?= e($item['vaccine_name']) ?></div>
                    <div class="flex flex-wrap gap-2 text-xs text-gray-400 mt-1">
                        <span>📅 Ngày tuổi <?= e($item['day_age']) ?></span>
                        <span>· <?= $method_labels[$item['method']] ?? $item['method'] ?></span>
                        <?php if ($item['brand_name']): ?>
                        <span>· <?= e($item['brand_name']) ?></span>
                        <?php endif; ?>
                        <span>· Nhắc <?= $item['remind_days'] ?> ngày trước</span>
                    </div>
                </div>
                <form method="POST" action="/settings/vaccine-programs/item/<?= $item['id'] ?>/delete"
                      onsubmit="return confirm('Xóa vaccine này?')" class="ml-3">
                    <button class="text-red-400 text-xs hover:underline">Xóa</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($items)): ?>
        <div class="text-center py-4 text-gray-400 text-sm">Chưa có vaccine nào trong bộ lịch</div>
        <?php endif; ?>
    </div>

    <!-- Form thêm vaccine -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
        <div class="text-sm font-semibold mb-3">➕ Thêm vaccine</div>
        <form method="POST" action="/settings/vaccine-programs/<?= $program['id'] ?>/item/store" class="space-y-3">
            <input type="text" name="vaccine_name" required placeholder="Tên vaccine..."
                   class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Hãng</label>
                    <select name="vaccine_brand_id" class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none">
                        <option value="">-- Không chọn --</option>
                        <?php foreach ($brands as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Ngày tuổi</label>
                    <input type="number" name="day_age" min="1" max="365" required value="1"
                           class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Phương pháp</label>
                    <select name="method" class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none">
                        <option value="drink">💧 Uống nước</option>
                        <option value="inject">💉 Tiêm</option>
                        <option value="eye_drop">👁️ Nhỏ mắt</option>
                        <option value="spray">🌫️ Phun sương</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Nhắc trước (ngày)</label>
                    <input type="number" name="remind_days" min="0" max="14" value="1"
                           class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white text-sm font-semibold py-2.5 rounded-xl">+ Thêm vaccine</button>
        </form>
    </div>
</div>
