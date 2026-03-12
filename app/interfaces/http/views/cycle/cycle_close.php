<?php
/**
 * app/interfaces/http/views/cycle/cycle_close.php
 *
 * Form đóng cycle — nhập kết quả cuối cùng.
 * final_quantity tự tính từ current_quantity.
 */

$title = 'Đóng cycle — ' . e($cycle->code);
ob_start();
?>

<div class="max-w-lg mx-auto">

    <div class="flex items-center gap-3 mb-6">
        <a href="/cycles/<?= e($cycle->id) ?>" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">← Quay lại</a>
        <h1 class="text-2xl font-bold">Đóng cycle — <?= e($cycle->code) ?></h1>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-lg text-sm">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- tóm tắt cycle -->
    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl p-4 mb-4">
        <div class="text-sm font-medium text-yellow-800 dark:text-yellow-300 mb-2">⚠️ Xác nhận đóng cycle này</div>
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <div class="text-xs text-gray-400">Số con hiện tại</div>
                <div class="font-bold text-lg"><?= e(number_format($cycle->current_quantity)) ?></div>
            </div>
            <div>
                <div class="text-xs text-gray-400">Ngày tuổi</div>
                <div class="font-bold text-lg"><?= e($cycle->age_in_days()) ?> ngày</div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <form method="POST" action="/cycles/<?= e($cycle->id) ?>/close">

            <!-- ngày kết thúc -->
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Ngày kết thúc <span class="text-red-500">*</span></label>
                <input type="date" name="end_date"
                       value="<?= e($_POST['end_date'] ?? date('Y-m-d')) ?>"
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <!-- lý do đóng -->
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Lý do đóng <span class="text-red-500">*</span></label>
                <select name="close_reason"
                        class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">— Chọn lý do —</option>
                    <option value="sold"      <?= ($_POST['close_reason'] ?? '') === 'sold'      ? 'selected' : '' ?>>Đã bán</option>
                    <option value="mortality" <?= ($_POST['close_reason'] ?? '') === 'mortality' ? 'selected' : '' ?>>Chết hàng loạt</option>
                    <option value="other"     <?= ($_POST['close_reason'] ?? '') === 'other'     ? 'selected' : '' ?>>Lý do khác</option>
                </select>
            </div>

            <!-- tổng cân nặng đã bán -->
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Tổng cân nặng đã bán (kg)</label>
                <input type="number" name="total_sold_weight_kg" step="0.1" min="0"
                       value="<?= e($_POST['total_sold_weight_kg'] ?? '') ?>"
                       placeholder="0.0"
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <!-- tổng doanh thu -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-1">Tổng doanh thu (đ)</label>
                <input type="number" name="total_revenue" step="1000" min="0"
                       value="<?= e($_POST['total_revenue'] ?? '') ?>"
                       placeholder="0"
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <button type="submit"
                    onclick="return confirm('Xác nhận đóng cycle <?= e($cycle->code) ?>? Hành động này không thể hoàn tác!')"
                    class="w-full bg-red-600 hover:bg-red-700 text-white font-medium py-2.5 rounded-lg text-sm">
                Xác nhận đóng cycle
            </button>

        </form>
    </div>

</div>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
