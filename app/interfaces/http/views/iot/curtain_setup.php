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
<div class="mb-3 p-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-600">✅ Đã lưu cấu hình bạt!</div>
<?php endif; ?>

<!-- Step 1: Chọn Barn có relay 8 kênh -->
<?php if (!$barn_id): ?>
<div class="mb-4">
    <div class="text-sm font-semibold mb-3">🔌 Chọn chuồng có relay 8 kênh để cài đặt:</div>
    <div class="grid grid-cols-2 gap-3">
        <?php foreach ($barns_with_relays as $b): ?>
        <a href="/iot/curtains/setup?barn_id=<?php echo $b->id; ?>"
           class="block p-4 rounded-xl border-2 border-green-200 dark:border-green-700 hover:border-green-500 bg-white dark:bg-gray-800">
            <div class="font-semibold"><?php echo htmlspecialchars($b->name); ?></div>
            <div class="text-xs text-gray-500 mt-1">
                <?php echo htmlspecialchars($b->device_name); ?> ·
                <?php echo $b->relay_name; ?> ·
                <?php echo $b->used_ch; ?>/8 kênh dùng
            </div>
            <div class="text-xs mt-2 <?php echo ($b->curtain_count >= 4) ? 'text-green-600' : 'text-orange-500'; ?>">
                <?php echo $b->curtain_count; ?>/4 bạt đã cài
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($barns_with_relays)): ?>
    <div class="text-center py-8 text-gray-500">
        <div class="text-4xl mb-2">🔌</div>
        <div>Chưa có chuồng nào có relay 8 kênh</div>
        <a href="/settings/iot" class="text-blue-500 text-sm mt-2 inline-block">→ Thêm thiết bị relay</a>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Step 2: Hiển thị 4 bạt để cấu hình GPIO -->
<?php if ($barn_id && $device_id): ?>
<div class="mb-4 flex items-center gap-2">
    <a href="/iot/curtains/setup" class="text-sm text-blue-600">← Chọn chuồng khác</a>
</div>

<!-- Hiển thị 8 GPIO Channels để reference -->
<div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl p-3 mb-4">
    <div class="text-xs font-semibold text-yellow-700 dark:text-yellow-300 mb-2">📌 8 GPIO Channels của Relay:</div>
    <div class="grid grid-cols-4 gap-2 text-xs">
        <?php foreach ($device_channels as $ch): ?>
        <div class="bg-white dark:bg-gray-800 rounded p-2 text-center">
            <span class="font-bold">CH<?php echo $ch->channel_number; ?></span> →
            <span class="font-mono">CH<?php echo $ch->channel_number; ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Form cấu hình 4 bạt -->
<form method="POST" action="/iot/curtains/visual-save">
    <input type="hidden" name="barn_id" value="<?php echo $barn_id; ?>">
    <input type="hidden" name="device_id" value="<?php echo $device_id; ?>">

    <div class="space-y-3 mb-4">
        <?php
        // Get existing curtains for this barn
        // Note: $pdo is available from controller via extract()
        $curtains_stmt = $pdo->prepare("
            SELECT cc.*,
                   dcu.channel_number as up_ch,
                   dcd.channel_number as dn_ch
            FROM curtain_configs cc
            LEFT JOIN device_channels dcu ON dcu.id = cc.up_channel_id
            LEFT JOIN device_channels dcd ON dcd.id = cc.down_channel_id
            WHERE cc.barn_id = :barn_id
            ORDER BY cc.id
        ");
        $curtains_stmt->execute(array(':barn_id' => $barn_id));
        $curtains = $curtains_stmt->fetchAll(PDO::FETCH_OBJ);

        // Ensure we have 4 curtains (create placeholders if less)
        for ($i = 0; $i < 4; $i++):
            $curtain = isset($curtains[$i]) ? $curtains[$i] : null;
        ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-sm font-semibold mb-3">🪟 Bạt <?php echo $i + 1; ?></div>

            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Tên bạt</label>
                    <input type="text" name="curtain_names[]"
                           value="<?php echo $curtain ? htmlspecialchars($curtain->name) : 'Bạt ' . ($i + 1); ?>"
                           class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Channel UP (LÊN)</label>
                    <select name="up_channel_id[]" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="">— Chọn Channel —</option>
                        <?php foreach ($device_channels as $ch): ?>
                        <option value="<?php echo $ch->id; ?>"
                                <?php echo ($curtain && $curtain->up_channel_id == $ch->id) ? 'selected' : ''; ?>>
                            CH<?php echo $ch->channel_number; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Channel DOWN (XUỐNG)</label>
                    <select name="down_channel_id[]" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="">— Chọn Channel —</option>
                        <?php foreach ($device_channels as $ch): ?>
                        <option value="<?php echo $ch->id; ?>"
                                <?php echo ($curtain && $curtain->down_channel_id == $ch->id) ? 'selected' : ''; ?>>
                            CH<?php echo $ch->channel_number; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Thời gian (giây)</label>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <input type="number" name="up_seconds[]"
                                   value="<?php echo $curtain ? $curtain->full_up_seconds : 30; ?>"
                                   min="5" max="300" class="w-full border rounded-lg px-2 py-2 text-sm" placeholder="Lên">
                            <div class="text-xs text-gray-400 text-center">Lên</div>
                        </div>
                        <div>
                            <input type="number" name="down_seconds[]"
                                   value="<?php echo $curtain ? $curtain->full_down_seconds : 30; ?>"
                                   min="5" max="300" class="w-full border rounded-lg px-2 py-2 text-sm" placeholder="Xuống">
                            <div class="text-xs text-gray-400 text-center">Xuống</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <button type="submit"
            class="w-full py-3 bg-green-600 text-white font-semibold rounded-xl">
        💾 Lưu cấu hình 4 bạt
    </button>
</form>
<?php endif; ?>

<!-- Hiển thị bạt hiện tại theo barn (khi đã chọn barn nhưng chưa có device) -->
<?php if ($barn_id && !$device_id): ?>
<div class="mb-4">
    <a href="/iot/curtains/setup" class="text-sm text-blue-600">← Chọn chuồng khác</a>
</div>

<?php
$curtains = isset($curtains_by_barn[$barn_id]) ? $curtains_by_barn[$barn_id] : array();
$barn_name = '';
foreach ($barns as $b) {
    if ($b->id == $barn_id) {
        $barn_name = $b->name;
        break;
    }
}
?>

<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-3">
    <div class="flex items-center justify-between mb-3">
        <div>
            <div class="font-semibold text-sm"><?php echo htmlspecialchars($barn_name); ?></div>
        </div>
        <span class="text-xs <?php echo (count($curtains) >= 4) ? 'text-green-500' : 'text-gray-400'; ?>">
            <?php echo count($curtains); ?>/4 bạt
        </span>
    </div>

    <?php if (empty($curtains)): ?>
    <div class="text-center py-4 text-sm text-gray-400">Chưa có bạt nào - cần chọn relay device</div>
    <?php else: ?>
    <div class="space-y-2 mb-3">
        <?php foreach ($curtains as $cc): ?>
        <div class="flex items-center justify-between text-sm py-2 border-t border-gray-100 dark:border-gray-700">
            <div>
                <span class="font-medium">🪟 <?php echo htmlspecialchars($cc->name); ?></span>
                <span class="text-xs text-gray-400 ml-2">
                    CH<?php echo $cc->up_ch; ?>↑ CH<?php echo $cc->dn_ch; ?>↓
                </span>
                <span class="text-xs ml-2 <?php echo ($cc->moving_state !== 'idle') ? 'text-blue-500' : 'text-gray-400'; ?>">
                    <?php echo $cc->current_position_pct; ?>%
                </span>
            </div>
            <div class="flex gap-2">
                <a href="/iot/curtains/<?php echo $cc->id; ?>/edit" class="text-xs text-blue-500 hover:underline">✏️ Sửa</a>
                <form method="POST" action="/iot/curtains/<?php echo $cc->id; ?>/delete"
                      onsubmit="return confirm('Xóa bạt <?php echo htmlspecialchars($cc->name); ?>?')">
                    <button type="submit" class="text-xs text-red-400 hover:text-red-600">🗑️</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Chọn relay device cho barn này -->
<?php
$available_devices = array();
foreach ($relay_devices as $d) {
    if ($d->barn_id == $barn_id || !isset($d->barn_id)) {
        $available_devices[] = $d;
    }
}
?>

<?php if (!empty($available_devices)): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-blue-200 dark:border-blue-800 p-4 mb-3">
    <div class="text-sm font-semibold mb-3">Chọn Relay Device:</div>
    <?php foreach ($available_devices as $d): ?>
    <a href="/iot/curtains/setup?barn_id=<?php echo $barn_id; ?>&device_id=<?php echo $d->id; ?>"
       class="block p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-blue-500 mb-2">
        <div class="font-medium"><?php echo htmlspecialchars($d->name); ?></div>
        <div class="text-xs text-gray-400">
            <?php echo ($d->total_ch - $d->used_ch); ?> kênh trống
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php $content = ob_get_clean(); require view_path('layouts/main.php'); ?>
