<?php
/**
 * app/interfaces/http/views/barn/barn_create.php
 *
 * Form tạo mới một barn.
 */

$title = 'Thêm chuồng mới';
ob_start();
?>

<div class="max-w-lg mx-auto">

    <div class="flex items-center gap-3 mb-6">
        <a href="/barns" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">← Quay lại</a>
        <h1 class="text-2xl font-bold">Thêm chuồng mới</h1>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-lg text-sm">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <form method="POST" action="/barns">

            <!-- số thứ tự & tên -->
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Số thứ tự <span class="text-red-500">*</span></label>
                    <input type="number" name="number" min="1" max="9"
                           value="<?= e($_POST['number'] ?? '') ?>"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Tên chuồng <span class="text-red-500">*</span></label>
                    <input type="text" name="name"
                           value="<?= e($_POST['name'] ?? '') ?>"
                           placeholder="VD: Chuồng A1"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
            </div>

            <!-- kích thước -->
            <div class="grid grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Dài (m) <span class="text-red-500">*</span></label>
                    <input type="number" name="length_m" step="0.01" min="0"
                           value="<?= e($_POST['length_m'] ?? '') ?>"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Rộng (m) <span class="text-red-500">*</span></label>
                    <input type="number" name="width_m" step="0.01" min="0"
                           value="<?= e($_POST['width_m'] ?? '') ?>"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Cao (m) <span class="text-red-500">*</span></label>
                    <input type="number" name="height_m" step="0.01" min="0"
                           value="<?= e($_POST['height_m'] ?? '') ?>"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
            </div>

            <!-- ghi chú -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-1">Ghi chú</label>
                <textarea name="note" rows="3"
                          placeholder="Ghi chú thêm về chuồng..."
                          class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                ><?= e($_POST['note'] ?? '') ?></textarea>
            </div>

            <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2.5 rounded-lg text-sm">
                Tạo chuồng
            </button>

        </form>
    </div>

</div>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
