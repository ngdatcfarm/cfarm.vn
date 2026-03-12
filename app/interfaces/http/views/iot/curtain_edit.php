<?php
$title = 'Sửa bạt — ' . ($curtain->name ?? '');
ob_start();
?>

<div class="mb-4 flex items-center gap-2">
    <a href="/iot/curtains/setup" class="text-sm text-blue-600">← Cài đặt bạt</a>
    <span class="text-gray-300">/</span>
    <span class="text-sm font-semibold"><?= htmlspecialchars($curtain->name) ?></span>
</div>

<?php if ($saved): ?>
<div class="mb-3 p-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-600">✅ Đã lưu</div>
<?php endif; ?>

<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-3">
    <div class="text-xs text-gray-400 mb-3">
        Relay: <strong><?= htmlspecialchars($curtain->up_device_code ?? '—') ?></strong> ·
        CH<?= $curtain->up_ch_num ?>↑ CH<?= $curtain->dn_ch_num ?>↓ ·
        Chuồng: <strong><?= htmlspecialchars($curtain->barn_name) ?></strong>
    </div>

    <form method="POST" action="/iot/curtains/<?= $curtain->id ?>/update">
        <div class="space-y-3">
            <div>
                <label class="text-xs font-medium text-gray-500 block mb-1">Tên bạt</label>
                <input type="text" name="name" value="<?= htmlspecialchars($curtain->name) ?>" required
                       class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-xs font-medium text-gray-500 block mb-1">Thời gian lên (giây)</label>
                    <input type="number" name="full_up_seconds"
                           value="<?= $curtain->full_up_seconds ?>" min="10" max="300" step="0.5"
                           class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-700">
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 block mb-1">Thời gian xuống (giây)</label>
                    <input type="number" name="full_down_seconds"
                           value="<?= $curtain->full_down_seconds ?>" min="10" max="300" step="0.5"
                           class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-700">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 text-center text-xs text-gray-400 bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3">
                <div>Vị trí hiện tại<br><strong class="text-base text-gray-700 dark:text-gray-200"><?= $curtain->current_position_pct ?>%</strong></div>
                <div>Trạng thái<br><strong class="text-base text-gray-700 dark:text-gray-200"><?= $curtain->moving_state ?></strong></div>
            </div>
        </div>
        <button type="submit"
                class="w-full mt-4 bg-blue-600 text-white font-semibold py-3 rounded-xl text-sm">
            💾 Lưu thay đổi
        </button>
    </form>
</div>

<form method="POST" action="/iot/curtains/<?= $curtain->id ?>/delete"
      onsubmit="return confirm('Xóa bạt <?= htmlspecialchars($curtain->name) ?>? Không thể hoàn tác!')">
    <button type="submit"
            class="w-full py-3 rounded-xl text-sm font-semibold text-red-500 border border-red-200 dark:border-red-800">
        🗑️ Xóa bạt này
    </button>
</form>

<?php $content = ob_get_clean(); require view_path('layouts/main.php'); ?>
