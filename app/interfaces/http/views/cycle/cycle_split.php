<?php
/**
 * app/interfaces/http/views/cycle/cycle_split.php
 *
 * Form tách đàn — chuyển một phần gà sang barn khác.
 */

$title = 'Tách đàn — ' . e($cycle->code);
ob_start();
?>

<div class="max-w-lg mx-auto">

    <div class="flex items-center gap-3 mb-6">
        <a href="/cycles/<?= e($cycle->id) ?>" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">← Quay lại</a>
        <h1 class="text-2xl font-bold">Tách đàn — <?= e($cycle->code) ?></h1>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-lg text-sm">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- tóm tắt cycle gốc -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4 mb-4">
        <div class="text-sm font-medium text-blue-800 dark:text-blue-300 mb-2">Cycle gốc: <?= e($cycle->code) ?></div>
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <div class="text-xs text-gray-400">Số con hiện tại</div>
                <div class="font-bold text-lg"><?= e(number_format($cycle->current_quantity)) ?></div>
            </div>
            <div>
                <div class="text-xs text-gray-400">Chuồng</div>
                <div class="font-bold text-lg"><?= e($barn->name) ?></div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <form method="POST" action="/cycles/<?= e($cycle->id) ?>/split">

            <!-- chuồng đích -->
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Chuyển sang chuồng <span class="text-red-500">*</span></label>
                <select name="to_barn_id"
                        class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">— Chọn chuồng —</option>
                    <?php foreach ($all_barns as $b): ?>
                        <?php if ($b->id === $barn->id) continue; ?>
                        <option value="<?= e($b->id) ?>" <?= ($_POST['to_barn_id'] ?? '') == $b->id ? 'selected' : '' ?>>
                            <?= e($b->name) ?> (Chuồng số <?= e($b->number) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- số con tách -->
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Số con tách <span class="text-red-500">*</span></label>
                <input type="number" name="quantity" min="1" max="<?= e($cycle->current_quantity - 1) ?>"
                       value="<?= e($_POST['quantity'] ?? '') ?>"
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                <p class="text-xs text-gray-400 mt-1">Tối đa <?= e($cycle->current_quantity - 1) ?> con</p>
            </div>

            <!-- ngày tách -->
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Ngày tách <span class="text-red-500">*</span></label>
                <input type="date" name="split_date"
                       value="<?= e($_POST['split_date'] ?? date('Y-m-d')) ?>"
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <!-- ghi chú -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-1">Ghi chú</label>
                <textarea name="note" rows="2"
                          placeholder="Lý do tách đàn..."
                          class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                ><?= e($_POST['note'] ?? '') ?></textarea>
            </div>

            <button type="submit"
                    class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-2.5 rounded-lg text-sm">
                Xác nhận tách đàn
            </button>

        </form>
    </div>

</div>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
