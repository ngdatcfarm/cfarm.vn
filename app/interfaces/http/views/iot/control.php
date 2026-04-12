<?php
$title = 'Điều khiển bạt - ' . e($barn->name);
ob_start();
?>

<div class="mb-4">
    <a href="/iot/control" class="text-sm text-blue-600 hover:underline">← Tất cả chuồng</a>
</div>

<!-- Header -->
<div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl p-4 mb-4">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-2xl">🪟</div>
        <div>
            <div class="text-lg font-bold text-white"><?= e($barn->name) ?></div>
            <div class="text-sm text-blue-200">Điều khiển 4 bạt thông gió</div>
        </div>
    </div>
</div>

<!-- ESP32 Device Selector -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="flex items-center gap-3 mb-3">
        <span class="text-xl">🎛️</span>
        <div>
            <div class="text-sm font-semibold">ESP32 Relay 8 kênh</div>
            <div class="text-xs text-gray-400">1 ESP32 điều khiển tất cả 4 bạt</div>
        </div>
    </div>
    <select id="esp_device_select" class="w-full px-3 py-2 border rounded-lg text-sm"
            onchange="setEspDevice(this.value)">
        <option value="">-- Chọn ESP32 --</option>
        <?php foreach ($devices as $d): ?>
        <option value="<?= e($d->id) ?>" <?= ($d->id == $esp_device_id) ? 'selected' : '' ?>>
            <?= e($d->name) ?> (<?= e($d->device_code) ?>) <?= $d->is_online ? '🟢' : '🔴' ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Bat Grid 2x2 -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    <?php foreach ($bats as $bat): ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border transition-all overflow-hidden
                <?= $bat['moving_state'] === 'up' ? 'border-green-400 ring-2 ring-green-200' : '' ?>
                <?= $bat['moving_state'] === 'down' ? 'border-red-400 ring-2 ring-red-200' : '' ?>
                <?= $bat['moving_state'] === 'stopped' ? 'border-gray-200 dark:border-gray-700' : '' ?>"
         id="bat_card_<?= e($bat['code']) ?>">

        <!-- Moving indicator -->
        <?php if ($bat['moving_state'] !== 'stopped'): ?>
        <div class="h-1 w-full <?= $bat['moving_state'] === 'up' ? 'bg-green-500' : 'bg-red-500' ?> animate-pulse"></div>
        <?php endif; ?>

        <div class="p-4">
            <!-- Bat Header -->
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <span class="text-3xl"><?= $bat['icon'] ?></span>
                    <div>
                        <div class="font-bold text-gray-800 dark:text-gray-100"><?= e($bat['name']) ?></div>
                        <div class="flex items-center gap-2 mt-1">
                            <?php if ($bat['device_id']): ?>
                            <span class="text-xs px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-full">
                                K<?= e($bat['up_channel']) ?> ↑ K<?= e($bat['down_channel']) ?> ↓
                            </span>
                            <?php else: ?>
                            <span class="text-xs px-2 py-0.5 bg-orange-100 text-orange-600 rounded-full">⚠️ Chưa gắn kênh</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-bold <?= $bat['moving_state'] === 'up' ? 'text-green-600' : ($bat['moving_state'] === 'down' ? 'text-red-600' : 'text-gray-400') ?>">
                        <?= $bat['moving_state'] === 'up' ? '↑ LÊN' : ($bat['moving_state'] === 'down' ? '↓ XUỐNG' : '■ DỪNG') ?>
                    </div>
                    <?php if ($bat['moving_state'] !== 'stopped'): ?>
                    <div class="text-xs text-gray-400 mt-0.5" id="elapsed_<?= e($bat['code']) ?>">0s</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Position Display -->
            <div class="mb-3">
                <div class="flex justify-between text-xs text-gray-400 mb-1">
                    <span>0%</span>
                    <span>Vị trí: <?= e($bat['position']) ?>%</span>
                    <span>100%</span>
                </div>
                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                    <div class="h-full bg-indigo-500 rounded-full transition-all duration-500"
                         style="width: <?= e($bat['position']) ?>%"></div>
                </div>
            </div>

            <!-- Control Buttons -->
            <div class="flex gap-2">
                <button onclick="moveBat('<?= e($bat['code']) ?>', 'up')"
                        <?= $bat['moving_state'] !== 'stopped' || !$bat['device_id'] ? 'disabled' : '' ?>
                        class="flex-1 py-3 px-3 rounded-xl font-bold text-white transition-all
                               <?= $bat['moving_state'] === 'up' ? 'bg-green-600' : 'bg-green-500 hover:bg-green-600' ?>
                               disabled:opacity-50 disabled:cursor-not-allowed">
                    ↑
                </button>
                <button onclick="moveBat('<?= e($bat['code']) ?>', 'down')"
                        <?= $bat['moving_state'] !== 'stopped' || !$bat['device_id'] ? 'disabled' : '' ?>
                        class="flex-1 py-3 px-3 rounded-xl font-bold text-white transition-all
                               <?= $bat['moving_state'] === 'down' ? 'bg-red-600' : 'bg-red-500 hover:bg-red-600' ?>
                               disabled:opacity-50 disabled:cursor-not-allowed">
                    ↓
                </button>
                <button onclick="stopBat('<?= e($bat['code']) ?>')"
                        <?= $bat['moving_state'] === 'stopped' ? 'disabled' : '' ?>
                        class="px-4 py-3 rounded-xl font-bold bg-amber-500 hover:bg-amber-600 text-white transition-all
                               disabled:opacity-50 disabled:cursor-not-allowed">
                    ■
                </button>
            </div>

            <!-- Channel Config (inline) -->
            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-gray-400">Kênh Lên / Xuống</span>
                    <div class="flex items-center gap-1">
                        <select class="w-14 px-2 py-1 border rounded text-center text-xs"
                                onchange="updateBatChannel('<?= e($bat['code']) ?>', 'up', this.value)">
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?= $i ?>" <?= ($bat['up_channel'] == $i) ? 'selected' : '' ?>>K<?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                        <span class="text-gray-300">/</span>
                        <select class="w-14 px-2 py-1 border rounded text-center text-xs"
                                onchange="updateBatChannel('<?= e($bat['code']) ?>', 'down', this.value)">
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?= $i ?>" <?= ($bat['down_channel'] == $i) ? 'selected' : '' ?>>K<?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Recent Activity -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
        <div class="text-sm font-semibold">📋 Hoạt động gần đây</div>
    </div>
    <?php if (empty($logs)): ?>
    <div class="p-8 text-center text-gray-400">
        <div class="text-3xl mb-2">📭</div>
        <div class="text-sm">Chưa có hoạt động nào</div>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-2 text-left text-xs text-gray-500">Thời gian</th>
                    <th class="px-4 py-2 text-left text-xs text-gray-500">Bạt</th>
                    <th class="px-4 py-2 text-center text-xs text-gray-500">Hành động</th>
                    <th class="px-4 py-2 text-center text-xs text-gray-500">Thời gian</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300"><?= e($log['time']) ?></td>
                    <td class="px-4 py-3 font-medium"><?= e($log['bat_name']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                    <?= $log['action'] === 'up' ? 'bg-green-100 text-green-700' : '' ?>
                                    <?= $log['action'] === 'down' ? 'bg-red-100 text-red-700' : '' ?>
                                    <?= $log['action'] === 'stop' ? 'bg-amber-100 text-amber-700' : '' ?>">
                            <?= $log['action'] === 'up' ? '↑ LÊN' : ($log['action'] === 'down' ? '↓ XUỐNG' : '■ DỪNG') ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center text-gray-400"><?= e($log['duration']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
const BARN_ID = '<?= e($barn_id) ?>';
const ESP_DEVICE_ID = <?= $esp_device_id ? $esp_device_id : 'null' ?>;
const BATS = <?= json_encode(array_values($bats), JSON_UNESCAPED_UNICODE) ?>;
const BAT_CODES = ['left_top', 'left_bottom', 'right_top', 'right_bottom'];
const BAT_NAMES = {
    'left_top': 'Bạt trái trên',
    'left_bottom': 'Bạt trái dưới',
    'right_top': 'Bạt phải trên',
    'right_bottom': 'Bạt phải dưới'
};
const BAT_ICONS = {
    'left_top': '↖️',
    'left_bottom': '↙️',
    'right_top': '↗️',
    'right_bottom': '↘️'
};

function setEspDevice(deviceId) {
    // Update all bats with same device
    fetch('/settings/iot/bat/set-device', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ barn_id: BARN_ID, device_id: deviceId })
    }).then(r => r.json()).then(data => {
        if (data.ok) location.reload();
        else showToast('Lỗi: ' + (data.message || 'Unknown'));
    });
}

function updateBatChannel(code, direction, channel) {
    const bat = BATS.find(b => b.code === code);
    if (!bat) return;
    const field = direction === 'up' ? 'up_channel' : 'down_channel';
    fetch('/settings/iot/bat/update-channel', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ bat_id: bat.id, [field]: channel })
    }).then(r => r.json()).then(data => {
        if (data.ok) showToast('Đã cập nhật kênh');
        else showToast('Lỗi: ' + (data.message || 'Unknown'));
    });
}

let moveTimers = {};

function moveBat(code, direction) {
    const bat = BATS.find(b => b.code === code);
    if (!bat || !bat.id) { alert('Bạt chưa được cấu hình'); return; }

    const card = document.getElementById('bat_card_' + code);
    card.classList.add('opacity-70');

    fetch('/iot/bat/' + bat.id + '/' + direction, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showToast(BAT_NAMES[code] + ': ' + (direction === 'up' ? '↑ Đang lên' : '↓ Đang xuống'));
            startMoveTimer(code, direction, data.timeout || 60);
        } else {
            alert(data.message || 'Lỗi gửi lệnh');
        }
    })
    .catch(e => { alert('Lỗi: ' + e.message); })
    .finally(() => card.classList.remove('opacity-70'));
}

function stopBat(code) {
    const bat = BATS.find(b => b.code === code);
    if (!bat || !bat.id) return;

    fetch('/iot/bat/' + bat.id + '/stop', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showToast(BAT_NAMES[code] + ': Đã dừng');
            stopMoveTimer(code);
        } else {
            alert(data.message || 'Lỗi');
        }
    });
}

function startMoveTimer(code, direction, timeout) {
    stopMoveTimer(code);
    let elapsed = 0;
    moveTimers[code] = setInterval(() => {
        elapsed++;
        const el = document.getElementById('elapsed_' + code);
        if (el) el.textContent = elapsed + 's';
        if (elapsed >= timeout) stopMoveTimer(code);
    }, 1000);
}

function stopMoveTimer(code) {
    if (moveTimers[code]) {
        clearInterval(moveTimers[code]);
        delete moveTimers[code];
    }
}

function showToast(msg) {
    const t = document.createElement('div');
    t.className = 'fixed bottom-20 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-sm px-4 py-2.5 rounded-xl shadow-lg z-50';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
