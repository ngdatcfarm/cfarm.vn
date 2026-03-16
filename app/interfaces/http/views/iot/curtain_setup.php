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

<!-- Visual GPIO Selection (shown when device is selected) -->
<?php if (!empty($device_channels) && $barn_id): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-green-200 dark:border-green-800 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">🔌 Chọn cặp LÊN/XUỐNG cho 4 bạt</div>
    <div class="text-xs text-gray-400 mb-3">
        Click vào GPIO để chọn cặp: Click LÊN trước → Click XUỐNG
    </div>

    <!-- Pin Configuration -->
    <div class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-xl">
        <div class="flex items-center justify-between mb-2">
            <div class="text-xs font-semibold text-yellow-700 dark:text-yellow-300">
                🔌 Cấu hình GPIO Pins
            </div>
            <button onclick="togglePinEdit()" id="pinEditBtn" class="text-xs text-blue-500">✏️ Sửa</button>
        </div>
        <div id="pinDisplay" class="grid grid-cols-4 gap-2 text-xs">
            <?php foreach ($device_channels as $ch): ?>
            <div class="text-center py-1 bg-white dark:bg-gray-800 rounded">
                CH<?php echo $ch->channel_number; ?> → <span class="font-mono font-bold">GPIO <?php echo (isset($ch->gpio_pin) ? $ch->gpio_pin : '—'); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div id="pinEditor" class="hidden grid grid-cols-4 gap-2">
            <?php foreach ($device_channels as $ch): ?>
            <div class="text-center">
                <div class="text-xs text-gray-400 mb-1">CH<?php echo $ch->channel_number; ?></div>
                <input type="number" id="pin_<?php echo $ch->id; ?>" value="<?php echo (isset($ch->gpio_pin) ? $ch->gpio_pin : ''); ?>"
                       min="0" max="39" placeholder="GPIO"
                       class="w-full text-center border rounded py-1 text-xs font-mono">
            </div>
            <?php endforeach; ?>
            <button onclick="savePins(<?php echo $device_id; ?>)"
                    class="col-span-4 bg-blue-600 text-white text-xs py-2 rounded mt-2">
                💾 Lưu pins
            </button>
        </div>
    </div>

    <!-- 8 Relay Boxes -->
    <div class="grid grid-cols-4 gap-2 mb-4">
        <?php foreach ($device_channels as $ch): ?>
        <?php
            $is_up = in_array($ch->id, (isset($selected_up) ? $selected_up : []));
            $is_down = in_array($ch->id, (isset($selected_down) ? $selected_down : []));
            $box_classes = '';
            if ($is_up) {
                $box_classes = 'border-green-500 bg-green-50 dark:bg-green-900/20';
            } elseif ($is_down) {
                $box_classes = 'border-red-500 bg-red-50 dark:bg-red-900/20';
            }
        ?>
        <div class="relay-box text-center p-3 rounded-xl border-2 cursor-pointer transition-all
                    <?php echo $box_classes; ?>
                    bg-gray-50 dark:bg-gray-700 border-gray-200 dark:border-gray-600 hover:border-blue-400"
             onclick="toggleRelay(<?php echo $ch->id; ?>, '<?php echo $ch->channel_number; ?>')">
            <div class="text-lg font-bold">CH<?php echo $ch->channel_number; ?></div>
            <div class="text-xs text-gray-400">GPIO <?php echo (isset($ch->gpio_pin) ? $ch->gpio_pin : '—'); ?></div>
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
let selectedUp = [];
let selectedDown = [];
const channelData = {};

<?php foreach ($device_channels as $ch): ?>
channelData[<?php echo $ch->id; ?>] = {ch: <?php echo $ch->channel_number; ?>, gpio: <?php echo (isset($ch->gpio_pin) ? $ch->gpio_pin : '0'); ?>};
<?php endforeach; ?>

function toggleRelay(id, ch) {
    if (selectedUp.includes(id)) {
        selectedUp = selectedUp.filter(x => x !== id);
    } else if (selectedDown.includes(id)) {
        selectedDown = selectedDown.filter(x => x !== id);
    } else if (selectedUp.length > selectedDown.length) {
        selectedDown.push(id);
    } else {
        selectedUp.push(id);
    }
    renderSelection();
}

function togglePinEdit() {
    const display = document.getElementById('pinDisplay');
    const editor = document.getElementById('pinEditor');
    const btn = document.getElementById('pinEditBtn');
    if (editor.classList.contains('hidden')) {
        editor.classList.remove('hidden');
        display.classList.add('hidden');
        btn.textContent = '✖ Đóng';
    } else {
        editor.classList.add('hidden');
        display.classList.remove('hidden');
        btn.textContent = '✏️ Sửa';
    }
}

async function savePins(deviceId) {
    const pins = {};
    <?php foreach ($device_channels as $ch): ?>
    pins[<?php echo $ch->id; ?>] = document.getElementById('pin_<?php echo $ch->id; ?>').value;
    <?php endforeach; ?>

    try {
        const r = await fetch('/settings/iot/device/' + deviceId + '/pins', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify(pins)
        });
        const d = await r.json();
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
    const pairs = [];
    const minLen = Math.min(selectedUp.length, selectedDown.length);

    for (let i = 0; i < minLen; i++) {
        const up = channelData[selectedUp[i]];
        const down = channelData[selectedDown[i]];
        pairs.push(`Bạt ${i+1}: CH${up?.ch} (GPIO ${up?.gpio}) ↑ → CH${down?.ch} (GPIO ${down?.gpio}) ↓`);
    }

    document.getElementById('selectedPairs').innerHTML = pairs.map(p =>
        `<div class="text-gray-600 dark:text-gray-300">${p}</div>`
    ).join('') || '<div class="text-gray-400">Click LÊN trước, rồi đến XUỐNG cho 4 cặp bạt</div>';

    // Update visual boxes
    document.querySelectorAll('.relay-box').forEach(box => {
        const id = parseInt(box.getAttribute('onclick').match(/\d+/)[0]);
        box.classList.remove('border-green-500', 'bg-green-50', 'dark:bg-green-900/20',
                           'border-red-500', 'bg-red-50', 'dark:bg-red-900/20');
        if (selectedUp.includes(id)) {
            box.classList.add('border-green-500', 'bg-green-50', 'dark:bg-green-900/20');
        } else if (selectedDown.includes(id)) {
            box.classList.add('border-red-500', 'bg-red-50', 'dark:bg-red-900/20');
        }
    });

    // Enable save if we have 4 pairs
    document.getElementById('saveBtn').disabled = minLen < 4;

    // Prepare JSON
    const pairsArray = [];
    for (let i = 0; i < minLen; i++) {
        pairsArray.push({up: selectedUp[i], down: selectedDown[i]});
    }
    document.getElementById('pairsJson').value = JSON.stringify(pairsArray);
}

// Initialize
renderSelection();
</script>
<?php endif; ?>

<!-- Bạt hiện tại theo barn -->
<?php foreach ($barns as $b): ?>
<?php $curtains = (isset($curtains_by_barn[$b->id]) ? $curtains_by_barn[$b->id] : []); ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-3">
    <div class="flex items-center justify-between mb-3">
        <div>
            <div class="font-semibold text-sm"><?php echo htmlspecialchars($b->name); ?></div>
            <?php if ($b->active_cycle): ?>
            <div class="text-xs text-gray-400">Chu kỳ: <?php echo htmlspecialchars($b->active_cycle); ?></div>
            <?php endif; ?>
        </div>
        <span class="text-xs <?php echo count($curtains) >= 4 ? 'text-green-500' : 'text-gray-400'; ?>">
            <?php echo count($curtains); ?>/4 bạt
        </span>
    </div>

    <?php if (empty($curtains)): ?>
    <div class="text-center py-4 text-sm text-gray-400">Chưa có bạt nào</div>
    <?php else: ?>
    <div class="space-y-2 mb-3">
        <?php foreach ($curtains as $cc): ?>
        <div class="flex items-center justify-between text-sm py-2 border-t border-gray-100 dark:border-gray-700">
            <div>
                <span class="font-medium">🪟 <?php echo htmlspecialchars($cc->name); ?></span>
                <span class="text-xs text-gray-400 ml-2">
                    <?php echo $cc->relay_code; ?> · CH<?php echo $cc->up_ch; ?>↑ CH<?php echo $cc->dn_ch; ?>↓
                </span>
                <span class="text-xs ml-2 <?php echo $cc->moving_state !== 'idle' ? 'text-blue-500' : 'text-gray-400'; ?>">
                    <?php echo $cc->current_position_pct; ?>%
                </span>
            </div>
            <div class="flex gap-2">
                <a href="/iot/curtains/<?php echo $cc->id; ?>/edit"
                   class="text-xs text-blue-500 hover:underline">✏️ Sửa</a>
                <form method="POST" action="/iot/curtains/<?php echo $cc->id; ?>/delete"
                      onsubmit="return confirm('Xóa bạt <?php echo htmlspecialchars($cc->name); ?>?')">
                    <button type="submit" class="text-xs text-red-400 hover:text-red-600">🗑️</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (count($curtains) < 4): ?>
    <button onclick="openForm(<?php echo $b->id; ?>)"
            class="w-full text-center text-xs font-semibold py-2 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-600">
        + Thêm bộ <?php echo 4 - count($curtains); ?> bạt còn lại
    </button>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- Form thêm bộ bạt (chỉ hiện khi KHÔNG chọn device) -->
<?php if (!$device_id): ?>
<div id="addForm">
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-blue-200 dark:border-blue-800 p-4 mb-3">
    <div class="text-sm font-semibold mb-3">➕ Thêm bộ 4 bạt</div>
    <form method="POST" action="/iot/curtains/store">

        <div class="mb-3">
            <label class="text-xs font-medium text-gray-500 block mb-1">Chuồng *</label>
            <select name="barn_id" id="barnSelect" required onchange="updateRelays()"
                    class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-700">
                <option value="">— Chọn chuồng —</option>
                <?php foreach ($barns as $b): ?>
                <option value="<?php echo $b->id; ?>" <?php echo $barn_id == $b->id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($b->name); ?>
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
                <option value="<?php echo $d->id; ?>"
                        data-barn="<?php echo $d->barn_id; ?>"
                        data-free="<?php echo $d->total_ch - $d->used_ch; ?>">
                    <?php echo htmlspecialchars($d->name); ?> · <?php echo (isset($d->barn_name) ? $d->barn_name : 'Chưa gán'); ?>
                    · <?php echo $d->total_ch - $d->used_ch; ?> kênh trống
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
                       value="<?php echo $default_names[$i]; ?>" required
                       placeholder="Bạt <?php echo $i + 1; ?>"
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
<?php endif; ?>

<button onclick="openForm(0)"
        class="w-full text-center text-sm font-semibold py-3 rounded-2xl bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 mb-4">
    ➕ Thêm bộ bạt mới cho chuồng khác
</button>

<script>
function openForm(barnId) {
    const form = document.getElementById('addForm');
    if (!form) return;
    form.classList.remove('hidden');
    if (barnId) {
        const barnSelect = document.getElementById('barnSelect');
        if (barnSelect) barnSelect.value = barnId;
    }
    form.scrollIntoView({behavior: 'smooth'});
    updateRelays();
}

function updateRelays() {
    const barnSelect = document.getElementById('barnSelect');
    if (!barnSelect) return;
    const barnId = barnSelect.value;
    const opts = document.querySelectorAll('#deviceSelect option[data-barn]');
    opts.forEach(o => {
        o.style.display = (!barnId || o.dataset.barn == barnId || o.dataset.barn == '') ? '' : 'none';
    });
}
</script>

<?php $content = ob_get_clean(); require view_path('layouts/main.php'); ?>