<?php require view_path('layouts/main.php'); ?>
<div class="px-4 pt-4 pb-24">
    <div class="flex items-center gap-3 mb-5">
        <a href="/settings" class="text-gray-400 hover:text-gray-600">←</a>
        <div class="text-lg font-bold">📋 Bộ lịch vaccine</div>
    </div>

    <!-- Form thêm bộ lịch -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <div class="text-sm font-semibold mb-3">➕ Tạo bộ lịch mới</div>
        <form method="POST" action="/settings/vaccine-programs/store" class="space-y-3">
            <input type="text" name="name" required placeholder="Tên bộ lịch (VD: Gà thịt 45 ngày)..."
                   class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <input type="text" name="note" placeholder="Ghi chú (tùy chọn)..."
                   class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <button type="submit" class="w-full bg-blue-600 text-white text-sm font-semibold py-2.5 rounded-xl">+ Tạo bộ lịch</button>
        </form>
    </div>

    <!-- Danh sách -->
    <div class="space-y-3">
        <?php foreach ($programs as $p): ?>
        <a href="/settings/vaccine-programs/<?= $p['id'] ?>"
           class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 active:scale-[0.98] transition-transform">
            <div>
                <div class="text-sm font-semibold"><?= e($p['name']) ?></div>
                <div class="text-xs text-gray-400 mt-0.5">
                    <?= $p['item_count'] ?> vaccine
                    <?= $p['note'] ? ' · ' . e($p['note']) : '' ?>
                </div>
            </div>
            <span class="text-gray-400">›</span>
        </a>
        <?php endforeach; ?>
        <?php if (empty($programs)): ?>
        <div class="text-center py-8 text-gray-400 text-sm">Chưa có bộ lịch nào</div>
        <?php endif; ?>
    </div>
</div>
