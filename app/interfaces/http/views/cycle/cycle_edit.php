<?php
/**
 * app/interfaces/http/views/cycle/cycle_edit.php
 *
 * Form chỉnh sửa cycle đang active.
 * Các field nhạy cảm (số lượng, giá) yêu cầu xác nhận trước khi lưu.
 */

$title = 'Sửa cycle — ' . e($cycle->code);
ob_start();
?>

<div class="max-w-lg mx-auto">

    <div class="flex items-center gap-3 mb-6">
        <a href="/cycles/<?= e($cycle->id) ?>" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">← Quay lại</a>
        <h1 class="text-2xl font-bold">Sửa — <?= e($cycle->code) ?></h1>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-lg text-sm">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <form method="POST" action="/cycles/<?= e($cycle->id) ?>" id="edit_form">
            <input type="hidden" name="confirmed" id="confirmed" value="">

            <!-- giống gà -->
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Giống gà</label>
                <input type="text" name="breed"
                       value="<?= e($_POST['breed'] ?? $cycle->breed) ?>"
                       placeholder="VD: tam hoàng, broiler..."
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <!-- stage -->
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Giai đoạn</label>
                <select name="stage"
                        class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="chick"  <?= ($_POST['stage'] ?? $cycle->stage) === 'chick'  ? 'selected' : '' ?>>Gà con (chick)</option>
                    <option value="grower" <?= ($_POST['stage'] ?? $cycle->stage) === 'grower' ? 'selected' : '' ?>>Gà choai (grower)</option>
                    <option value="adult"  <?= ($_POST['stage'] ?? $cycle->stage) === 'adult'  ? 'selected' : '' ?>>Gà trưởng thành (adult)</option>
                </select>
                <p class="text-xs text-gray-400 mt-1">⚠️ Thay đổi giai đoạn sẽ ảnh hưởng đến gợi ý cám</p>
            </div>

            <!-- ngày dự kiến kết thúc -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-1">Ngày dự kiến kết thúc</label>
                <input type="date" name="expected_end_date"
                       value="<?= e($_POST['expected_end_date'] ?? $cycle->expected_end_date) ?>"
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <!-- divider -->
            <div class="border-t border-gray-200 dark:border-gray-600 my-6"></div>

            <!-- section nhạy cảm -->
            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-xl p-4 mb-4">
                <div class="text-sm font-medium text-yellow-800 dark:text-yellow-300 mb-3">
                    ⚠️ Thông tin nhạy cảm — yêu cầu xác nhận khi lưu
                </div>

                <!-- số lượng -->
                <div class="grid grid-cols-3 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium mb-1">Tổng số con</label>
                        <input type="number" name="initial_quantity" min="1"
                               value="<?= e($_POST['initial_quantity'] ?? $cycle->initial_quantity) ?>"
                               class="w-full border border-yellow-300 dark:border-yellow-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">Trống</label>
                        <input type="number" name="male_quantity" min="0"
                               value="<?= e($_POST['male_quantity'] ?? $cycle->male_quantity) ?>"
                               class="w-full border border-yellow-300 dark:border-yellow-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">Mái</label>
                        <input type="number" name="female_quantity" min="0"
                               value="<?= e($_POST['female_quantity'] ?? $cycle->female_quantity) ?>"
                               class="w-full border border-yellow-300 dark:border-yellow-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-500">
                    </div>
                </div>

                <!-- giá nhập -->
                <div>
                    <label class="block text-xs font-medium mb-1">Giá nhập (đ/con)</label>
                    <input type="number" name="purchase_price" min="0" step="100"
                           value="<?= e($_POST['purchase_price'] ?? $cycle->purchase_price) ?>"
                           class="w-full border border-yellow-300 dark:border-yellow-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-500">
                </div>
            </div>

            <button type="button" onclick="submitForm()"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg text-sm">
                Cập nhật cycle
            </button>

        </form>
    </div>

</div>

<script>
function submitForm() {
    const original = {
        initial_quantity: <?= e($cycle->initial_quantity) ?>,
        male_quantity:    <?= e($cycle->male_quantity) ?>,
        female_quantity:  <?= e($cycle->female_quantity) ?>,
        purchase_price:   <?= e($cycle->purchase_price) ?>,
    };

    const current = {
        initial_quantity: parseInt(document.querySelector('[name=initial_quantity]').value),
        male_quantity:    parseInt(document.querySelector('[name=male_quantity]').value),
        female_quantity:  parseInt(document.querySelector('[name=female_quantity]').value),
        purchase_price:   parseFloat(document.querySelector('[name=purchase_price]').value),
    };

    const is_sensitive =
        current.initial_quantity !== original.initial_quantity ||
        current.male_quantity    !== original.male_quantity    ||
        current.female_quantity  !== original.female_quantity  ||
        current.purchase_price   !== original.purchase_price;

    if (is_sensitive) {
        if (!confirm('Bạn đang thay đổi số lượng hoặc giá nhập — đây là thông tin quan trọng.\n\nXác nhận tiếp tục?')) {
            return;
        }
        document.getElementById('confirmed').value = '1';
    }

    document.getElementById('edit_form').submit();
}
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
