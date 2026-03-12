<?php
/**
 * app/interfaces/http/views/cycle/cycle_feed_stages.php
 *
 * Cấu hình mã cám theo từng stage trong cycle.
 * Hỗ trợ mix 2 mã khi chuyển dần.
 */
$title = 'Cấu hình mã cám — ' . e($cycle->code);
ob_start();

$stage_labels = ['chick' => '🐣 Gà con', 'grower' => '🐔 Gà choai', 'adult' => '🦆 Gà trưởng thành'];
?>

<div class="max-w-lg mx-auto">

    <div class="flex items-center gap-3 mb-6">
        <a href="/cycles/<?= e($cycle->id) ?>" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">← Quay lại</a>
        <div>
            <h1 class="text-xl font-bold">Mã cám theo stage</h1>
            <div class="text-xs text-gray-400"><?= e($cycle->code) ?> · Hãng: <?= e($current_brand['name'] ?? '—') ?></div>
        </div>
    </div>

    <?php if (empty($feed_types)): ?>
        <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-xl text-sm text-yellow-700 dark:text-yellow-300">
            ⚠️ Cycle chưa có hãng cám —
            <a href="/cycles/<?= e($cycle->id) ?>/feed-program" class="underline font-medium">Cài đặt hãng cám trước</a>
        </div>
    <?php else: ?>

    <?php if (!empty($error)): ?>
        <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded-xl text-sm">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-xl text-sm">
            ✅ <?= e($success) ?>
        </div>
    <?php endif; ?>

    <!-- 3 cards, mỗi card 1 stage -->
    <div class="space-y-4">
        <?php foreach (['chick', 'grower', 'adult'] as $stage): ?>
        <?php $existing = $stage_configs[$stage] ?? null; ?>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
            <!-- stage header -->
            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                <div class="text-sm font-semibold"><?= $stage_labels[$stage] ?></div>
                <?php if ($existing): ?>
                    <span class="text-xs px-2 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-full">Đã cấu hình</span>
                <?php else: ?>
                    <span class="text-xs px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-400 rounded-full">Chưa cấu hình</span>
                <?php endif; ?>
            </div>

            <div class="p-4">
                <form method="POST" action="/cycles/<?= e($cycle->id) ?>/feed-stages">
                    <input type="hidden" name="stage" value="<?= e($stage) ?>">

                    <!-- mã cám chính -->
                    <div class="mb-3">
                        <label class="block text-xs font-medium mb-1">Mã cám chính <span class="text-red-500">*</span></label>
                        <select name="primary_feed_type_id"
                                onchange="toggleMix('<?= $stage ?>', this)"
                                class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">— Chọn mã cám —</option>
                            <?php foreach ($feed_types as $ft): ?>
                                <option value="<?= e($ft['id']) ?>"
                                        <?= ($existing['primary_feed_type_id'] ?? '') == $ft['id'] ? 'selected' : '' ?>>
                                    <?= e($ft['code']) ?>
                                    <?php if ($ft['name']): ?>(<?= e($ft['name']) ?>)<?php endif; ?>
                                    · Gợi ý: <?= e($ft['suggested_stage_label'] ?? $ft['suggested_stage']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- toggle mix -->
                    <div class="mb-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox"
                                   id="mix_toggle_<?= $stage ?>"
                                   onchange="toggleMixSection('<?= $stage ?>')"
                                   <?= !empty($existing['mix_feed_type_id']) ? 'checked' : '' ?>
                                   class="w-4 h-4 accent-blue-600">
                            <span class="text-xs text-gray-500">Đang mix 2 mã (chuyển dần)</span>
                        </label>
                    </div>

                    <!-- mix section -->
                    <div id="mix_section_<?= $stage ?>"
                         class="<?= empty($existing['mix_feed_type_id']) ? 'hidden' : '' ?> mb-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl space-y-2">
                        <div class="text-xs font-medium text-blue-700 dark:text-blue-300 mb-2">Mã cám mix (mã mới đang chuyển dần vào)</div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Mã cám mix</label>
                                <select name="mix_feed_type_id"
                                        class="w-full border border-blue-200 dark:border-blue-700 bg-white dark:bg-gray-700 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">— Chọn —</option>
                                    <?php foreach ($feed_types as $ft): ?>
                                        <option value="<?= e($ft['id']) ?>"
                                                <?= ($existing['mix_feed_type_id'] ?? '') == $ft['id'] ? 'selected' : '' ?>>
                                            <?= e($ft['code']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Tỷ lệ mã mới (%)</label>
                                <input type="number" name="mix_ratio" min="5" max="95" step="5"
                                       value="<?= e($existing['mix_ratio'] ?? 25) ?>"
                                       placeholder="25"
                                       class="w-full border border-blue-200 dark:border-blue-700 bg-white dark:bg-gray-700 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- ngày áp dụng -->
                    <div class="mb-3">
                        <label class="block text-xs font-medium mb-1">Áp dụng từ ngày</label>
                        <input type="date" name="effective_date"
                               value="<?= e($existing['effective_date'] ?? date('Y-m-d')) ?>"
                               class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- ghi chú -->
                    <div class="mb-4">
                        <label class="block text-xs font-medium mb-1">Ghi chú</label>
                        <input type="text" name="note"
                               value="<?= e($existing['note'] ?? '') ?>"
                               placeholder="Tùy chọn..."
                               class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-xl text-sm transition-colors">
                        <?= $existing ? 'Cập nhật' : 'Lưu cấu hình' ?> <?= $stage_labels[$stage] ?>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<script>
function toggleMixSection(stage) {
    const cb  = document.getElementById('mix_toggle_' + stage);
    const sec = document.getElementById('mix_section_' + stage);
    sec.classList.toggle('hidden', !cb.checked);
}
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
