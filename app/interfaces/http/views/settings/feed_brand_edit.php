<?php
/**
 * app/interfaces/http/views/settings/feed_brand_edit.php
 *
 * Form chỉnh sửa hãng cám.
 */
$title = 'Sửa hãng cám — ' . e($brand->name);
ob_start();
?>

<div class="max-w-lg mx-auto">

    <div class="flex items-center gap-3 mb-6">
        <a href="/settings/feed-brands/<?= e($brand->id) ?>" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">← Quay lại</a>
        <h1 class="text-xl font-bold">Sửa — <?= e($brand->name) ?></h1>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-xl text-sm">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <form method="POST" action="/settings/feed-brands/<?= e($brand->id) ?>">

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Tên hãng cám <span class="text-red-500">*</span></label>
                <input type="text" name="name"
                       value="<?= e($_POST['name'] ?? $brand->name) ?>"
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Trọng lượng mỗi bao (kg) <span class="text-red-500">*</span></label>
                <input type="number" name="kg_per_bag" step="0.5" min="0"
                       value="<?= e($_POST['kg_per_bag'] ?? $brand->kg_per_bag) ?>"
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Trạng thái</label>
                <select name="status"
                        class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="active"   <?= ($brand->status === 'active')   ? 'selected' : '' ?>>Đang dùng</option>
                    <option value="inactive" <?= ($brand->status === 'inactive') ? 'selected' : '' ?>>Ngừng</option>
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium mb-1">Ghi chú</label>
                <textarea name="note" rows="2"
                          class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                ><?= e($_POST['note'] ?? $brand->note) ?></textarea>
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                Cập nhật hãng cám
            </button>

        </form>
    </div>

</div>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
