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

<!-- Step 2: Cài đặt bạt cho barn đã chọn -->
<?php if ($barn_id && $device_id): ?>
<div class="mb-4 flex items-center gap-2">
    <a href="/iot/curtains/setup" class="text-sm text-blue-600">← Chọn chuồng khác</a>
</div>

<div class="bg-white dark:bg-gray-800 rounded-2xl border border-green-200 dark:border-green-800 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">
        🔌 Cài đặt GPIO cho 4 bạt - <?php echo htmlspecialchars($barn_name); ?>
    </div>
    <div class="text-xs text-gray-400 mb-3">
        Click vào GPIO để chọn cặp: Click LÊN trước → Click XUỐNG
    </div>

    <!-- 8 Relay Boxes -->
    <div class="grid grid-cols-4 gap-2 mb-4">
        <?php foreach ($device_channels as $ch):
            $is_up = in_array($ch->id, (isset($selected_up) ? $selected_up : array()));
            $is_down = in_array($ch->id, (isset($selected_down) ? $selected_down : array()));
            $box_class = 'bg-gray-50 dark:bg-gray-700 border-gray-200 dark:border-gray-600';
            if ($is_up) {
                $box_class .= ' border-green-500 bg-green-50 dark:bg-green-900/20';
            } elseif ($is_down) {
                $box_class .= ' border-red-500 bg-red-50 dark:bg-red-900/20';
            }
        ?>
        <div class="relay-box text-center p-3 rounded-xl border-2 cursor-pointer transition-all <?php echo $box_class; ?>"
             onclick="toggleRelay(<?php echo $ch->id; ?>, '<?php echo $ch->channel_number; ?>')">
            <div class="text-lg font-bold">CH<?php echo $ch->channel_number; ?></div>
            <div class="text-xs text-gray-400">GPIO <?php echo isset($ch->gpio_pin) ? $ch->gpio_pin : '—'; ?></div>
            <div class="text-xs mt-1 status-indicator">
                <?php if ($is_up): ?>
                <span class="text-green-600 font-bold">↑ LÊN</span>
                <?php elseif ($is_down): ?>
                <span class="text-red-600 font-bold">↓ XUỐNG</span>
                <?php else: ?>
                <span class="text-gray-400">—</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Selected Pairs Display -->
    <div class="mb-3 p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-xl">
        <div class="text-xs font-semibold text-yellow-700 dark:text-yellow-300 mb-2">Các cặp đã chọn:</div>
        <div id="selectedPairs" class="space-y-1 text-xs">
            <!-- Dynamic content -->
        </div>
    </div>

    <form method="POST" action="/iot/curtains/visual-save">
        <input type="hidden" name="barn_id" value="<?php echo $barn_id; ?>">
        <input type="hidden" name="device_id" value="<?php echo $device_id; ?>">
        <input type="hidden" name="pairs" id="pairsJson">
        <button type="submit" id="saveBtn" disabled
                class="w-full py-2 text-sm font-semibold bg-green-600 text-white rounded-xl disabled:bg-gray-400 disabled:cursor-not-allowed">
            💾 Lưu cấu hình 4 bạt
        </button>
    </form>
</div>

<script>
var selectedUp = [];
var selectedDown = [];
var channelData = {};

<?php foreach ($device_channels as $ch): ?>
channelData[<?php echo $ch->id; ?>] = {ch: <?php echo $ch->channel_number; ?>, gpio: <?php echo isset($ch->gpio_pin) ? $ch->gpio_pin : 0; ?>};
<?php endforeach; ?>

function toggleRelay(id, ch) {
    if (selectedUp.indexOf(id) !== -1) {
        selectedUp = selectedUp.filter(function(x) { return x !== id; });
    } else if (selectedDown.indexOf(id) !== -1) {
        selectedDown = selectedDown.filter(function(x) { return x !== id; });
    } else if (selectedUp.length > selectedDown.length) {
        selectedDown.push(id);
    } else {
        selectedUp.push(id);
    }
    renderSelection();
}

async function savePins(deviceId) {
    var pins = {};
    <?php foreach ($device_channels as $ch): ?>
    pins[<?php echo $ch->id; ?>] = document.getElementById('pin_<?php echo $ch->id; ?>').value;
    <?php endforeach; ?>

    try {
        var r = await fetch('/settings/iot/device/' + deviceId + '/pins', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify(pins)
        });
        var d = await r.json();
        if (d.ok) {
            alert('✅ Đã lưu GPIO pins!');
            location.reload();
        } else {
            alert('❌ ' + d.message);
        }
    } catch(e) {
        alert('❌ Lỗi kết nối');
    }
}

function renderSelection() {
    var pairs = [];
    var minLen = Math.min(selectedUp.length, selectedDown.length);

    for (var i = 0; i < minLen; i++) {
        var up = channelData[selectedUp[i]];
        var down = channelData[selectedDown[i]];
        pairs.push('Bạt ' + (i+1) + ': CH' + up.ch + ' (GPIO ' + up.gpio + ') ↑ → CH' + down.ch + ' (GPIO ' + down.gpio + ') ↓');
    }

    document.getElementById('selectedPairs').innerHTML = pairs.map(function(p) {
        return '<div class="text-gray-600 dark:text-gray-300">' + p + '</div>';
    }).join('') || '<div class="text-gray-400">Click LÊN trước, rồi đến XUỐNG cho 4 cặp bạt</div>';

    document.querySelectorAll('.relay-box').forEach(function(box) {
        var match = box.getAttribute('onclick').match(/\d+/);
        if (!match) return;
        var id = parseInt(match[0]);
        box.classList.remove('border-green-500', 'bg-green-50', 'dark:bg-green-900/20',
                           'border-red-500', 'bg-red-50', 'dark:bg-red-900/20');
        if (selectedUp.indexOf(id) !== -1) {
            box.classList.add('border-green-500', 'bg-green-50', 'dark:bg-green-900/20');
        } else if (selectedDown.indexOf(id) !== -1) {
            box.classList.add('border-red-500', 'bg-red-50', 'dark:bg-red-900/20');
        }
    });

    document.getElementById('saveBtn').disabled = minLen < 4;

    var pairsArray = [];
    for (var i = 0; i < minLen; i++) {
        pairsArray.push({up: selectedUp[i], down: selectedDown[i]});
    }
    document.getElementById('pairsJson').value = JSON.stringify(pairsArray);
}

renderSelection();
</script>
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
