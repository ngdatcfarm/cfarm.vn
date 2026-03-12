<?php
$title = 'Cài đặt bộ bạt';
ob_start();
?>

<div class="mb-4 flex items-center gap-2">
    <a href="/settings/iot" class="text-sm text-blue-600">← IoT Settings</a>
    <span class="text-gray-300">/</span>
    <span class="text-sm font-semibold">Cài đặt bạt</span>
</div>

<?php if ($error === 'missing_fields'): ?>
<div class="mb-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-600">❌ Vui lòng điền đầy đủ thông tin</div>
<?php elseif ($error === 'not_enough_channels'): ?>
<div class="mb-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-600">❌ Relay không đủ kênh trống — cần 8 kênh chưa dùng</div>
<?php elseif ($error === 'db_error'): ?>
<div class="mb-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-600">❌ Lỗi DB — thử lại</div>
<?php endif; ?>

<?php if ($saved): ?>
<div class="mb-3 p-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-600">✅ Đã tạo 4 bạt thành công!</div>
<?php endif; ?>

<!-- Bạt hiện tại theo barn -->
<?php foreach ($barns as $b): ?>
<?php $curtains = $curtains_by_barn[$b->id] ?? []; ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-3">
    <div class="flex items-center justify-between mb-3">
        <div>
            <div class="font-semibold text-sm"><?= htmlspecialchars($b->name) ?></div>
            <?php if ($b->active_cycle): ?>
            <div class="text-xs text-gray-400">Chu kỳ: <?= htmlspecialchars($b->active_cycle) ?></div>
            <?php endif; ?>
        </div>
        <span class="text-xs <?= count($curtains) >= 4 ? 'text-green-500' : 'text-gray-400' ?>">
            <?= count($curtains) ?>/4 bạt
        </span>
    </div>

    <?php if (empty($curtains)): ?>
    <div class="text-center py-4 text-sm text-gray-400">Chưa có bạt nào</div>
    <?php else: ?>
    <div class="space-y-2 mb-3">
        <?php foreach ($curtains as $cc): ?>
        <div class="flex items-center justify-between text-sm py-2 border-t border-gray-100 dark:border-gray-700">
            <div>
                <span class="font-medium">🪟 <?= htmlspecialchars($cc->name) ?></span>
                <span class="text-xs text-gray-400 ml-2">
                    <?= $cc->relay_code ?> · CH<?= $cc->up_ch ?>↑ CH<?= $cc->dn_ch ?>↓
                </span>
                <span class="text-xs ml-2 <?= $cc->moving_state !== 'idle' ? 'text-blue-500' : 'text-gray-400' ?>">
                    <?= $cc->current_position_pct ?>%
                </span>
            </div>
            <div class="flex gap-2">
                <a href="/iot/curtains/<?= $cc->id ?>/edit"
                   class="text-xs text-blue-500 hover:underline">✏️ Sửa</a>
                <form method="POST" action="/iot/curtains/<?= $cc->id ?>/delete"
                      onsubmit="return confirm('Xóa bạt <?= htmlspecialchars($cc->name) ?>?')">
                    <button type="submit" class="text-xs text-red-400 hover:text-red-600">🗑️</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (count($curtains) < 4): ?>
    <button onclick="openForm(<?= $b->id ?>)"
            class="w-full text-center text-xs font-semibold py-2 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-600">
        + Thêm bộ <?= 4 - count($curtains) ?> bạt còn lại
    </button>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- Form thêm bộ bạt -->
<div id="addForm" class="<?= $barn_id ? '' : 'hidden' ?>">
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-blue-200 dark:border-blue-800 p-4 mb-3">
    <div class="text-sm font-semibold mb-3">➕ Thêm bộ 4 bạt</div>
    <form method="POST" action="/iot/curtains/store">

        <div class="mb-3">
            <label class="text-xs font-medium text-gray-500 block mb-1">Chuồng *</label>
            <select name="barn_id" id="barnSelect" required onchange="updateRelays()"
                    class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-700">
                <option value="">— Chọn chuồng —</option>
                <?php foreach ($barns as $b): ?>
                <option value="<?= $b->id ?>" <?= $barn_id == $b->id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($b->name) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="text-xs font-medium text-gray-500 block mb-1">Relay device *</label>
            <select name="device_id" id="deviceSelect" required
                    class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-700">
                <option value="">— Chọn relay —</option>
                <?php foreach ($relay_devices as $d): ?>
                <option value="<?= $d->id ?>"
                        data-barn="<?= $d->barn_id ?>"
                        data-free="<?= $d->total_ch - $d->used_ch ?>">
                    <?= htmlspecialchars($d->name) ?> · <?= $d->barn_name ?? 'Chưa gán' ?>
                    · <?= $d->total_ch - $d->used_ch ?> kênh trống
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="text-xs font-medium text-gray-500 block mb-2">Tên 4 bạt *</label>
            <div class="grid grid-cols-2 gap-2">
                <?php
                $default_names = ['Trái dưới', 'Trái trên', 'Phải dưới', 'Phải trên'];
                for ($i = 0; $i < 4; $i++):
                ?>
                <input type="text" name="curtain_names[]"
                       value="<?= $default_names[$i] ?>" required
                       placeholder="Bạt <?= $i+1 ?>"
                       class="border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-700">
                <?php endfor; ?>
            </div>
            <div class="text-xs text-gray-400 mt-1">CH1-2→Bạt1, CH3-4→Bạt2, CH5-6→Bạt3, CH7-8→Bạt4</div>
        </div>

        <div class="grid grid-cols-2 gap-2 mb-4">
            <div>
                <label class="text-xs font-medium text-gray-500 block mb-1">Thời gian lên (giây)</label>
                <input type="number" name="full_up_seconds" value="60" min="10" max="300" step="0.5"
                       class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-700">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-500 block mb-1">Thời gian xuống (giây)</label>
                <input type="number" name="full_down_seconds" value="55" min="10" max="300" step="0.5"
                       class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-700">
            </div>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 text-white font-semibold py-3 rounded-xl text-sm">
            ✅ Tạo 4 bạt tự động
        </button>
    </form>
</div>
</div>

<button onclick="openForm(0)"
        class="w-full text-center text-sm font-semibold py-3 rounded-2xl bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 mb-4">
    ➕ Thêm bộ bạt mới cho chuồng khác
</button>

<script>
function openForm(barnId) {
    document.getElementById('addForm').classList.remove('hidden');
    if (barnId) document.getElementById('barnSelect').value = barnId;
    document.getElementById('addForm').scrollIntoView({behavior:'smooth'});
    updateRelays();
}

function updateRelays() {
    const barnId = document.getElementById('barnSelect').value;
    const opts = document.querySelectorAll('#deviceSelect option[data-barn]');
    opts.forEach(o => {
        o.style.display = (!barnId || o.dataset.barn == barnId || o.dataset.barn == '') ? '' : 'none';
    });
}
</script>

<?php $content = ob_get_clean(); require view_path('layouts/main.php'); ?>
