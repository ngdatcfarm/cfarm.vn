<?php
$title = 'Danh mục thuốc';
$category_labels = [
    'vaccine'    => '💉 Vaccine',
    'antibiotic' => '🔬 Kháng sinh',
    'vitamin'    => '🌿 Vitamin',
    'other'      => '💊 Khác',
];
$category_colors = [
    'vaccine'    => 'bg-blue-100 text-blue-700',
    'antibiotic' => 'bg-red-100 text-red-700',
    'vitamin'    => 'bg-green-100 text-green-700',
    'other'      => 'bg-gray-100 text-gray-600',
];
ob_start();
?>

<div class="max-w-lg mx-auto">
    <div class="flex items-center gap-3 mb-4">
        <a href="/settings" class="text-gray-400 hover:text-gray-600">←</a>
        <h1 class="text-xl font-bold">💊 Danh mục thuốc</h1>
    </div>

    <?php if (!empty($error)): ?>
    <div class="mb-4 p-3 bg-red-50 text-red-600 rounded-xl text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- FORM: Thêm mới hoặc sửa -->
    <?php
    $editing    = !empty($medication);
    $form_action = $editing
        ? '/settings/medications/' . $medication['id'] . '/update'
        : '/settings/medications';
    $m = $medication ?? [];
    ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-4 mb-5">
        <div class="text-sm font-semibold mb-3"><?= $editing ? '✏️ Sửa thuốc' : '+ Thêm thuốc mới' ?></div>
        <form method="POST" action="<?= e($form_action) ?>" class="space-y-3">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Tên thuốc <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="<?= e($m['name'] ?? '') ?>" required
                           placeholder="VD: Amoxicillin"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Đơn vị <span class="text-red-500">*</span></label>
                    <input type="text" name="unit" value="<?= e($m['unit'] ?? '') ?>" required
                           placeholder="VD: ml, g, viên"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Loại thuốc</label>
                    <select name="category"
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($category_labels as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($m['category'] ?? 'other') === $val ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Nhà sản xuất</label>
                    <input type="text" name="manufacturer" value="<?= e($m['manufacturer'] ?? '') ?>"
                           placeholder="VD: Hanvet, Marphavet"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Giá / đơn vị (đ)</label>
                    <input type="number" name="price_per_unit" value="<?= e($m['price_per_unit'] ?? '') ?>"
                           min="0" step="500" placeholder="VD: 15000"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Liều khuyến nghị</label>
                    <input type="text" name="recommended_dose" value="<?= e($m['recommended_dose'] ?? '') ?>"
                           placeholder="VD: 1ml/2L nước"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium mb-1">Ghi chú</label>
                <textarea name="note" rows="2" placeholder="Tùy chọn..."
                          class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= e($m['note'] ?? '') ?></textarea>
            </div>

            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                    <?= $editing ? 'Lưu thay đổi' : '+ Thêm thuốc' ?>
                </button>
                <?php if ($editing): ?>
                <a href="/settings/medications"
                   class="px-4 py-2.5 border border-gray-300 rounded-xl text-sm text-gray-500 hover:bg-gray-50">
                    Hủy
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- DANH SÁCH -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
            <div class="text-sm font-semibold">Danh sách thuốc</div>
            <div class="text-xs text-gray-400"><?= count($medications) ?> loại</div>
        </div>

        <?php if (empty($medications)): ?>
        <div class="text-center py-10 text-gray-400 text-sm">Chưa có thuốc nào</div>
        <?php else: ?>

        <!-- filter by category -->
        <div class="flex gap-2 px-4 py-2 overflow-x-auto border-b border-gray-100 dark:border-gray-700"
             style="scrollbar-width:none;">
            <button onclick="filterCat('all', this)"
                    class="cat-btn flex-shrink-0 text-xs px-3 py-1.5 rounded-full bg-blue-600 text-white font-medium">
                Tất cả
            </button>
            <?php foreach ($category_labels as $val => $label): ?>
            <button onclick="filterCat('<?= $val ?>', this)"
                    class="cat-btn flex-shrink-0 text-xs px-3 py-1.5 rounded-full border border-gray-200 text-gray-500 hover:border-blue-400">
                <?= $label ?>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="divide-y divide-gray-100 dark:divide-gray-700" id="med_list">
            <?php foreach ($medications as $med): ?>
            <div class="med-item px-4 py-3" data-category="<?= e($med['category']) ?>">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-semibold"><?= e($med['name']) ?></span>
                            <span class="text-xs px-2 py-0.5 rounded-full <?= $category_colors[$med['category']] ?? 'bg-gray-100 text-gray-600' ?>">
                                <?= $category_labels[$med['category']] ?? $med['category'] ?>
                            </span>
                            <?php if ($med['status'] === 'inactive'): ?>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-400">Ẩn</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-gray-400 mt-1 space-y-0.5">
                            <div>
                                <span class="font-medium text-gray-500">Đơn vị:</span> <?= e($med['unit']) ?>
                                <?php if ($med['manufacturer']): ?>
                                · <span class="font-medium text-gray-500">Hãng:</span> <?= e($med['manufacturer']) ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($med['price_per_unit']): ?>
                            <div>
                                <span class="font-medium text-gray-500">Giá:</span>
                                <span class="text-green-600 font-medium"><?= number_format($med['price_per_unit']) ?>đ</span>
                                / <?= e($med['unit']) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($med['recommended_dose']): ?>
                            <div>
                                <span class="font-medium text-gray-500">Liều:</span> <?= e($med['recommended_dose']) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($med['note']): ?>
                            <div class="text-gray-400 italic"><?= e($med['note']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- actions -->
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <a href="/settings/medications/<?= e($med['id']) ?>/edit"
                           class="p-1.5 text-blue-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">✏️</a>
                        <form method="POST" action="/settings/medications/<?= e($med['id']) ?>/toggle" class="inline">
                            <button type="submit"
                                    class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-50 rounded-lg transition-colors"
                                    title="<?= $med['status'] === 'active' ? 'Ẩn' : 'Hiện' ?>">
                                <?= $med['status'] === 'active' ? '👁️' : '🙈' ?>
                            </button>
                        </form>
                        <form method="POST" action="/settings/medications/<?= e($med['id']) ?>/delete" class="inline"
                              onsubmit="return confirm('Xóa thuốc này?')">
                            <button type="submit" class="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">🗑️</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function filterCat(cat, btn) {
    document.querySelectorAll('.cat-btn').forEach(b => {
        b.classList.remove('bg-blue-600','text-white','font-medium');
        b.classList.add('border','border-gray-200','text-gray-500');
    });
    btn.classList.add('bg-blue-600','text-white','font-medium');
    btn.classList.remove('border','border-gray-200','text-gray-500');

    document.querySelectorAll('.med-item').forEach(item => {
        item.style.display = (cat === 'all' || item.dataset.category === cat) ? '' : 'none';
    });
}
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
