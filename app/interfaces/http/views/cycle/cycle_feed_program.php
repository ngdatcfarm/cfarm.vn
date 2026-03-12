<?php
/**
 * app/interfaces/http/views/cycle/cycle_feed_program.php
 *
 * Form đổi hãng cám đang dùng trong cycle.
 */
$title = 'Đổi hãng cám — ' . e($cycle->code);
ob_start();
?>

<div class="max-w-lg mx-auto">

    <div class="flex items-center gap-3 mb-6">
        <a href="/cycles/<?= e($cycle->id) ?>" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">← Quay lại</a>
        <h1 class="text-xl font-bold">Đổi hãng cám — <?= e($cycle->code) ?></h1>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded-xl text-sm">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <form method="POST" action="/cycles/<?= e($cycle->id) ?>/feed-program">

            <div class="mb-4">
                <label class="block text-sm font-medium mb-3">Chọn hãng cám mới <span class="text-red-500">*</span></label>
                <div class="space-y-2">
                    <?php foreach ($feed_brands as $brand): ?>
                    <div class="flex items-center justify-between p-3 rounded-xl border cursor-pointer transition-all
                                <?= ($_POST['feed_brand_id'] ?? '') == $brand->id
                                    ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/20'
                                    : 'border-gray-200 dark:border-gray-600 hover:border-blue-300' ?>"
                         onclick="document.getElementById('brand_<?= e($brand->id) ?>').click()">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="feed_brand_id"
                                   id="brand_<?= e($brand->id) ?>"
                                   value="<?= e($brand->id) ?>"
                                   <?= ($_POST['feed_brand_id'] ?? '') == $brand->id ? 'checked' : '' ?>
                                   class="accent-blue-600">
                            <div>
                                <div class="text-sm font-semibold"><?= e($brand->name) ?></div>
                                <div class="text-xs text-gray-400"><?= e($brand->kg_per_bag) ?> kg/bao</div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Ngày bắt đầu dùng hãng mới <span class="text-red-500">*</span></label>
                <input type="date" name="start_date"
                       value="<?= e($_POST['start_date'] ?? date('Y-m-d')) ?>"
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium mb-1">Ghi chú lý do đổi</label>
                <textarea name="note" rows="2"
                          placeholder="VD: Hết hàng C-P, chuyển sang Dabaco..."
                          class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                ><?= e($_POST['note'] ?? '') ?></textarea>
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl text-sm transition-colors">
                Xác nhận đổi hãng cám
            </button>

        </form>
    </div>

</div>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
