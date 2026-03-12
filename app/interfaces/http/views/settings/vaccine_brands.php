<?php require view_path('layouts/main.php'); ?>
<div class="px-4 pt-4 pb-24">
    <div class="flex items-center gap-3 mb-5">
        <a href="/settings" class="text-gray-400 hover:text-gray-600">←</a>
        <div class="text-lg font-bold">💉 Hãng vaccine</div>
    </div>

    <!-- Form thêm -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <form method="POST" action="/settings/vaccine-brands/store" class="flex gap-2">
            <input type="text" name="name" required placeholder="Tên hãng vaccine..."
                   class="flex-1 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <button type="submit" class="bg-blue-600 text-white text-sm font-semibold px-4 py-2.5 rounded-xl">+ Thêm</button>
        </form>
    </div>

    <!-- Danh sách -->
    <div class="space-y-2">
        <?php foreach ($brands as $b): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 px-4 py-3 flex justify-between items-center">
            <span class="text-sm font-medium"><?= e($b['name']) ?></span>
            <form method="POST" action="/settings/vaccine-brands/<?= $b['id'] ?>/delete"
                  onsubmit="return confirm('Xóa hãng này?')">
                <button class="text-red-400 text-xs hover:underline">Xóa</button>
            </form>
        </div>
        <?php endforeach; ?>
        <?php if (empty($brands)): ?>
        <div class="text-center py-8 text-gray-400 text-sm">Chưa có hãng vaccine nào</div>
        <?php endif; ?>
    </div>
</div>
