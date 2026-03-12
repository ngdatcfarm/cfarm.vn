<?php
/**
 * app/interfaces/http/views/cycle/cycle_create.php
 *
 * Form tạo mới một cycle cho một barn.
 * Bao gồm chọn hãng cám ngay khi tạo.
 */

$title = 'Tạo cycle mới — ' . e($barn->name);
ob_start();
?>

<div class="max-w-lg mx-auto">

    <div class="flex items-center gap-3 mb-6">
        <a href="/barns/<?= e($barn->id) ?>" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">← Quay lại</a>
        <h1 class="text-xl font-bold">Tạo cycle — <?= e($barn->name) ?></h1>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-xl text-sm">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <form method="POST" action="/barns/<?= e($barn->id) ?>/cycles">

            <!-- giống gà & ngày bắt đầu -->
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Giống gà</label>
                    <input type="text" name="breed"
                           value="<?= e($_POST['breed'] ?? '') ?>"
                           placeholder="VD: Tam hoàng, Broiler..."
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Nguồn giống</label>
                    <select name="flock_source"
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Không rõ --</option>
                        <option value="hatchery"  <?= ($_POST['flock_source']??'')==='hatchery'  ? 'selected':'' ?>>🏭 Trại ấp</option>
                        <option value="local"     <?= ($_POST['flock_source']??'')==='local'     ? 'selected':'' ?>>🏘️ Địa phương</option>
                        <option value="imported"  <?= ($_POST['flock_source']??'')==='imported'  ? 'selected':'' ?>>✈️ Nhập khẩu</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Ngày bắt đầu <span class="text-red-500">*</span></label>
                    <input type="date" name="start_date"
                           value="<?= e($_POST['start_date'] ?? date('Y-m-d')) ?>"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- số lượng -->
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Tổng số con <span class="text-red-500">*</span></label>
                    <input type="number" name="initial_quantity" min="1"
                           value="<?= e($_POST['initial_quantity'] ?? '') ?>"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Trống <span class="text-red-500">*</span></label>
                    <input type="number" name="male_quantity" min="0"
                           value="<?= e($_POST['male_quantity'] ?? '') ?>"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Mái <span class="text-red-500">*</span></label>
                    <input type="number" name="female_quantity" min="0"
                           value="<?= e($_POST['female_quantity'] ?? '') ?>"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- giá nhập & stage -->
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Giá nhập (đ/con) <span class="text-red-500">*</span></label>
                    <input type="number" name="purchase_price" min="0" step="100"
                           value="<?= e($_POST['purchase_price'] ?? '') ?>"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Giai đoạn</label>
                    <select name="stage"
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="chick"  <?= ($_POST['stage'] ?? 'chick') === 'chick'  ? 'selected' : '' ?>>Gà con</option>
                        <option value="grower" <?= ($_POST['stage'] ?? '') === 'grower' ? 'selected' : '' ?>>Gà choai</option>
                        <option value="adult"  <?= ($_POST['stage'] ?? '') === 'adult'  ? 'selected' : '' ?>>Gà trưởng thành</option>
                    </select>
                </div>
            </div>

            <!-- ngày dự kiến kết thúc -->
            <div class="mb-5">
                <label class="block text-sm font-medium mb-1">Ngày dự kiến kết thúc</label>
                <input type="date" name="expected_end_date"
                       value="<?= e($_POST['expected_end_date'] ?? '') ?>"
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- thất thoát cám -->
            <div class="mb-5">
                <label class="block text-sm font-medium mb-1">
                    Tỷ lệ thất thoát cám
                    <span class="text-xs text-gray-400 font-normal ml-1">(rơi vãi, bới ra ngoài)</span>
                </label>
                <div class="flex items-center gap-2">
                    <input type="number" name="feed_waste_pct" min="0" max="20" step="0.5"
                           value="<?= e($_POST['feed_waste_pct'] ?? '3') ?>"
                           class="w-32 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <span class="text-sm text-gray-400">% (thường 2–5%)</span>
                </div>
            </div>
            <!-- divider -->
            <div class="border-t border-gray-100 dark:border-gray-700 my-5"></div>

            <!-- hãng cám -->
            <div class="mb-5">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-base">🌾</span>
                    <div class="text-sm font-semibold">Hãng cám sử dụng</div>
                </div>

                <?php if (empty($feed_brands)): ?>
                    <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-xl text-sm text-yellow-700 dark:text-yellow-300">
                        ⚠️ Chưa có hãng cám nào —
                        <a href="/settings/feed-brands/create" class="underline font-medium">Thêm hãng cám</a>
                        rồi quay lại tạo cycle
                    </div>
                <?php else: ?>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 p-3 rounded-xl border border-dashed border-gray-200 dark:border-gray-600 cursor-pointer"
                             onclick="document.getElementById('no_brand').click()">
                            <input type="radio" name="feed_brand_id" id="no_brand" value=""
                                   <?= empty($_POST['feed_brand_id']) ? 'checked' : '' ?>
                                   class="accent-blue-600">
                            <label for="no_brand" class="text-sm text-gray-400 cursor-pointer">Chọn sau</label>
                        </div>
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
                            <?php if ($brand->note): ?>
                            <div class="text-xs text-gray-400"><?= e($brand->note) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl text-sm transition-colors">
                Tạo cycle
            </button>

        </form>
    </div>

</div>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
