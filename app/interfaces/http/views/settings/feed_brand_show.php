<?php
/**
 * app/interfaces/http/views/settings/feed_brand_show.php
 *
 * Chi tiết hãng cám + danh sách mã cám + form thêm mã cám.
 */
$title = 'Hãng cám — ' . e($brand->name);
ob_start();

$stage_labels = ['chick' => 'Gà con', 'grower' => 'Gà choai', 'adult' => 'Gà trưởng thành', 'all' => 'Tất cả'];
$stage_colors = [
    'chick'  => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
    'grower' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    'adult'  => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    'all'    => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
];
?>

<div class="max-w-lg mx-auto">

    <!-- header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="/settings/feed-brands" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">←</a>
            <div>
                <h1 class="text-xl font-bold"><?= e($brand->name) ?></h1>
                <div class="text-xs text-gray-400">
                    <?= e($brand->kg_per_bag) ?> kg/bao
                    <?php if (!empty($brand->price_per_bag)): ?>
                    · <span class="text-green-600 font-medium"><?= number_format($brand->price_per_bag) ?>đ/bao</span>
                    <?php else: ?>
                    · <span class="text-orange-400">Chưa có giá</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <a href="/settings/feed-brands/<?= e($brand->id) ?>/edit"
           class="text-sm text-blue-600 hover:underline font-medium">Sửa</a>
    </div>

    <!-- danh sách mã cám -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm mb-4">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
            <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">Mã cám (<?= count($brand->feed_types ?? []) ?>)</div>
        </div>

        <?php if (empty($brand->feed_types)): ?>
            <div class="text-center py-8 text-gray-400 text-sm">
                Chưa có mã cám nào — thêm bên dưới
            </div>
        <?php else: ?>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php foreach ($brand->feed_types as $type): ?>
                <div class="px-4 py-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 bg-green-50 dark:bg-green-900/20 rounded-lg flex items-center justify-center text-sm font-bold text-green-700 dark:text-green-400">
                                <?= e(substr($type->code, 0, 2)) ?>
                            </div>
                            <div>
                                <div class="font-semibold text-sm"><?= e($type->code) ?>
                                    <?php if ($type->name): ?>
                                        <span class="font-normal text-gray-400">— <?= e($type->name) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-xs px-2 py-0.5 rounded-full <?= $stage_colors[$type->suggested_stage] ?>">
                                        <?= $stage_labels[$type->suggested_stage] ?>
                                    </span>
                                    <?php if ($type->price_per_bag): ?>
                                    <span class="text-xs text-green-600 font-medium"><?= number_format($type->price_per_bag) ?>đ/bao</span>
                                    <?php else: ?>
                                    <span class="text-xs text-orange-400">Chưa có giá</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button onclick="toggleEditType(<?= e($type->id) ?>)"
                                    class="text-xs text-blue-400 hover:text-blue-600 px-2 py-1 rounded-lg hover:bg-blue-50">
                                ✏️
                            </button>
                            <form method="POST" action="/settings/feed-types/<?= e($type->id) ?>/delete"
                                  onsubmit="return confirm('Xóa mã <?= e($type->code) ?>?')">
                                <button type="submit"
                                        class="text-xs text-red-400 hover:text-red-600 px-2 py-1 rounded-lg hover:bg-red-50">
                                    🗑️
                                </button>
                            </form>
                        </div>
                    </div>
                    <!-- Form sửa inline -->
                    <div id="edit_type_<?= e($type->id) ?>" class="hidden mt-3">
                        <form method="POST" action="/settings/feed-types/<?= e($type->id) ?>/update" class="space-y-2">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-medium mb-1">Mã cám</label>
                                    <input type="text" name="code" value="<?= e($type->code) ?>"
                                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">Giá / bao (đ)</label>
                                    <input type="number" name="price_per_bag" value="<?= e($type->price_per_bag ?? '') ?>"
                                           min="0" step="500" placeholder="VD: 350000"
                                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-medium mb-1">Tên đầy đủ</label>
                                    <input type="text" name="name" value="<?= e($type->name ?? '') ?>"
                                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">Stage</label>
                                    <select name="suggested_stage"
                                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="all"    <?= $type->suggested_stage==='all'    ? 'selected':'' ?>>Tất cả</option>
                                        <option value="chick"  <?= $type->suggested_stage==='chick'  ? 'selected':'' ?>>Gà con</option>
                                        <option value="grower" <?= $type->suggested_stage==='grower' ? 'selected':'' ?>>Gà choai</option>
                                        <option value="adult"  <?= $type->suggested_stage==='adult'  ? 'selected':'' ?>>Gà trưởng thành</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit"
                                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-xl text-sm">
                                    Lưu
                                </button>
                                <button type="button" onclick="toggleEditType(<?= e($type->id) ?>)"
                                        class="px-4 py-2 border border-gray-300 rounded-xl text-sm text-gray-500">
                                    Hủy
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>


    <!-- form thêm mã cám -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-4">
        <div class="text-sm font-semibold mb-3 text-gray-700 dark:text-gray-300">+ Thêm mã cám mới</div>

        <?php if (!empty($error)): ?>
            <div class="mb-3 p-3 bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded-xl text-sm">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/settings/feed-brands/<?= e($brand->id) ?>/types">

            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Mã cám <span class="text-red-500">*</span></label>
                    <input type="text" name="code"
                           value="<?= e($_POST['code'] ?? '') ?>"
                           placeholder="VD: 311H"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Stage gợi ý</label>
                    <select name="suggested_stage"
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="all"    <?= ($_POST['suggested_stage'] ?? 'all') === 'all'    ? 'selected' : '' ?>>Tất cả</option>
                        <option value="chick"  <?= ($_POST['suggested_stage'] ?? '') === 'chick'  ? 'selected' : '' ?>>Gà con</option>
                        <option value="grower" <?= ($_POST['suggested_stage'] ?? '') === 'grower' ? 'selected' : '' ?>>Gà choai</option>
                        <option value="adult"  <?= ($_POST['suggested_stage'] ?? '') === 'adult'  ? 'selected' : '' ?>>Gà trưởng thành</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Tên đầy đủ (tùy chọn)</label>
                    <input type="text" name="name"
                           value="<?= e($_POST['name'] ?? '') ?>"
                           placeholder="VD: Cám khởi động..."
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Giá / bao (đ)</label>
                    <input type="number" name="price_per_bag"
                           value="<?= e($_POST['price_per_bag'] ?? '') ?>"
                           min="0" step="500" placeholder="VD: 350000"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-xl text-sm transition-colors">
                Thêm mã cám
            </button>

        </form>
    </div>

</div>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>

<script>
function toggleEditType(id) {
    const el = document.getElementById('edit_type_' + id);
    el.classList.toggle('hidden');
}
</script>
