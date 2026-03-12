<?php
$title = 'Cài đặt IoT';
ob_start();
?>

<div class="mb-4">
    <a href="/settings/iot" class="text-sm text-blue-600 hover:underline">← IoT Settings</a>
</div>

<div class="bg-blue-600 rounded-2xl p-4 mb-4">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-2xl">⚙️</div>
        <div>
            <div class="text-lg font-bold text-white">Cài đặt IoT</div>
            <div class="text-sm text-blue-200">Cấu hình bạt & thiết bị</div>
        </div>
    </div>
</div>

<!-- Danh sách devices -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">📡 Thiết bị đã đăng ký</div>
    <?php if (empty($devices)): ?>
        <div class="text-xs text-gray-400">Chưa có thiết bị nào.</div>
    <?php else: ?>
        <?php foreach ($devices as $dev): ?>
        <div class="flex items-center justify-between text-sm py-2 border-t border-gray-100 dark:border-gray-700">
            <div>
                <span class="font-medium"><?= e($dev->device_code) ?></span>
                <span class="text-xs text-gray-400 ml-1"><?= e($dev->name) ?> · <?= e($dev->barn_name ?? 'Chưa gán barn') ?></span>
            </div>
            <div class="text-xs">
                <span class="<?= $dev->is_online ? 'text-green-500' : 'text-red-500' ?>">● <?= $dev->is_online ? 'Online' : 'Offline' ?></span>
                <span class="text-gray-400 ml-1"><?= e($dev->total_channels) ?> kênh</span>
                <a href="/settings/iot/firmware/<?= e($dev->id) ?>" class="text-blue-500 hover:underline ml-2">💾 Firmware</a>
                <?php if (($dev->device_class ?? '') === 'sensor'): ?>
                <span class="ml-2 text-gray-300">|</span>
                <form method="POST" action="/env/barn/<?= e($dev->barn_id) ?>/interval" class="inline ml-1">
                    <select name="interval_seconds" onchange="this.form.submit()"
                            class="text-xs border border-gray-200 dark:border-gray-600 rounded-lg px-1.5 py-0.5 bg-white dark:bg-gray-700">
                        <?php foreach ([30=>'30s',60=>'1p',120=>'2p',300=>'5p',600=>'10p',900=>'15p'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= ($dev->env_interval_seconds??300)==$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php endif; ?>
                <button onclick="toggleAlert(<?= $dev->id ?>, this)"
                        class="ml-2 text-xs px-2 py-0.5 rounded-full font-semibold
                               <?= $dev->alert_offline ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-gray-400' ?>">
                    <?= $dev->alert_offline ? '🔔' : '🔕' ?>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Cấu hình bạt -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="flex items-center justify-between mb-3">
        <div class="text-sm font-semibold">🪟 Cấu hình bạt</div>
        <button onclick="openCurtainModal()"
                class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-2 rounded-full transition-colors">
            + Thêm bạt
        </button>
    </div>

    <?php if (empty($curtains)): ?>
        <div class="text-xs text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-700">Chưa có bạt nào.</div>
    <?php else: ?>
        <?php foreach ($curtains as $cur): ?>
        <div class="border-t border-gray-100 dark:border-gray-700 pt-3 mt-3" id="curtain_row_<?= e($cur->id) ?>">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <div class="text-sm font-semibold"><?= e($cur->name) ?></div>
                    <div class="text-xs text-gray-400"><?= e($cur->barn_name) ?></div>
                </div>
                <div class="flex gap-1.5">
                    <button onclick="editCurtain(<?= e($cur->id) ?>, <?= e(htmlspecialchars(json_encode([
                        'name' => $cur->name,
                        'barn_id' => $cur->barn_id,
                        'up_channel_id' => $cur->up_channel_id,
                        'down_channel_id' => $cur->down_channel_id,
                        'full_up_seconds' => $cur->full_up_seconds,
                        'full_down_seconds' => $cur->full_down_seconds,
                    ]), ENT_QUOTES)) ?>)"
                            class="text-blue-400 hover:text-blue-600 p-1">✏️</button>
                    <button onclick="deleteCurtain(<?= e($cur->id) ?>, this)"
                            class="text-red-400 hover:text-red-600 p-1">🗑️</button>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg px-3 py-2">
                    <div class="text-gray-400 mb-0.5">▲ Relay lên</div>
                    <div class="font-medium"><?= e($cur->up_device_code) ?> · CH<?= e($cur->up_ch) ?></div>
                    <div class="text-gray-400"><?= e($cur->full_up_seconds) ?>s = 100%</div>
                </div>
                <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg px-3 py-2">
                    <div class="text-gray-400 mb-0.5">▼ Relay xuống</div>
                    <div class="font-medium"><?= e($cur->down_device_code) ?> · CH<?= e($cur->down_ch) ?></div>
                    <div class="text-gray-400"><?= e($cur->full_down_seconds) ?>s = 100%</div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Link tới trang điều khiển -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
    <div class="text-sm font-semibold mb-3">🎮 Điều khiển</div>
    <?php foreach ($barns as $barn): ?>
    <a href="/iot/control/<?= e($barn->id) ?>"
       class="flex items-center justify-between py-2 border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg px-2 transition-colors">
        <span class="text-sm"><?= e($barn->name) ?></span>
        <span class="text-xs text-blue-600">Mở →</span>
    </a>
    <?php endforeach; ?>
</div>

<!-- MODAL: Thêm/Sửa bạt -->
<div id="modal_backdrop_iot" onclick="closeIoTModal()" class="hidden fixed inset-0 bg-black/40 z-30"></div>
<div id="modal_curtain" class="hidden fixed inset-0 z-40 flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-2xl px-5 pt-5 pb-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <div class="text-base font-bold" id="curtain_modal_title">🪟 Thêm bạt mới</div>
            <button onclick="closeIoTModal()" class="text-gray-400 text-2xl leading-none">×</button>
        </div>
        <div id="curtain_modal_error" class="hidden mb-3 p-3 bg-red-50 text-red-600 rounded-xl text-sm"></div>
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Tên bạt <span class="text-red-500">*</span></label>
                    <input type="text" id="cc_name" placeholder="VD: Trái dưới"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Chuồng <span class="text-red-500">*</span></label>
                    <select id="cc_barn_id"
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Chọn —</option>
                        <?php foreach ($barns as $b): ?>
                        <option value="<?= e($b->id) ?>"><?= e($b->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1">▲ Relay lên <span class="text-red-500">*</span></label>
                    <select id="cc_up_channel"
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Chọn relay —</option>
                        <?php foreach ($channels as $ch): ?>
                        <option value="<?= e($ch->id) ?>">[<?= e($ch->device_code) ?>] CH<?= e($ch->channel_number) ?> · <?= e($ch->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">▼ Relay xuống <span class="text-red-500">*</span></label>
                    <select id="cc_down_channel"
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Chọn relay —</option>
                        <?php foreach ($channels as $ch): ?>
                        <option value="<?= e($ch->id) ?>">[<?= e($ch->device_code) ?>] CH<?= e($ch->channel_number) ?> · <?= e($ch->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1">⏱ Thời gian lên 100% (giây)</label>
                    <input type="number" id="cc_up_seconds" step="0.5" min="1" value="60" placeholder="60"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">⏱ Thời gian xuống 100% (giây)</label>
                    <input type="number" id="cc_down_seconds" step="0.5" min="1" value="55" placeholder="55"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <input type="hidden" id="cc_edit_id" value="">
            <button onclick="submitCurtainConfig()"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl text-sm transition-colors"
                    id="cc_submit_btn">Lưu bạt</button>
        </div>
    </div>
</div>

<script>
function openCurtainModal() {
    document.getElementById('cc_edit_id').value = '';
    document.getElementById('cc_name').value = '';
    document.getElementById('cc_barn_id').value = '';
    document.getElementById('cc_up_channel').value = '';
    document.getElementById('cc_down_channel').value = '';
    document.getElementById('cc_up_seconds').value = '60';
    document.getElementById('cc_down_seconds').value = '55';
    document.getElementById('curtain_modal_title').textContent = '🪟 Thêm bạt mới';
    document.getElementById('curtain_modal_error').classList.add('hidden');
    document.getElementById('modal_backdrop_iot').classList.remove('hidden');
    document.getElementById('modal_curtain').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function editCurtain(id, data) {
    document.getElementById('cc_edit_id').value = id;
    document.getElementById('cc_name').value = data.name;
    document.getElementById('cc_barn_id').value = data.barn_id;
    document.getElementById('cc_up_channel').value = data.up_channel_id;
    document.getElementById('cc_down_channel').value = data.down_channel_id;
    document.getElementById('cc_up_seconds').value = data.full_up_seconds;
    document.getElementById('cc_down_seconds').value = data.full_down_seconds;
    document.getElementById('curtain_modal_title').textContent = '✏️ Sửa bạt: ' + data.name;
    document.getElementById('curtain_modal_error').classList.add('hidden');
    document.getElementById('modal_backdrop_iot').classList.remove('hidden');
    document.getElementById('modal_curtain').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeIoTModal() {
    document.getElementById('modal_curtain').classList.add('hidden');
    document.getElementById('modal_backdrop_iot').classList.add('hidden');
    document.body.style.overflow = '';
}

async function submitCurtainConfig() {
    const editId = document.getElementById('cc_edit_id').value;
    const url = editId
        ? '/settings/iot/curtain/' + editId + '/update'
        : '/settings/iot/curtain/store';

    const err = document.getElementById('curtain_modal_error');
    err.classList.add('hidden');

    const body = new URLSearchParams({
        name: document.getElementById('cc_name').value,
        barn_id: document.getElementById('cc_barn_id').value,
        up_channel_id: document.getElementById('cc_up_channel').value,
        down_channel_id: document.getElementById('cc_down_channel').value,
        full_up_seconds: document.getElementById('cc_up_seconds').value,
        full_down_seconds: document.getElementById('cc_down_seconds').value,
    });

    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body
        });
        const json = await res.json();
        if (json.ok) { closeIoTModal(); location.reload(); }
        else { err.textContent = json.message; err.classList.remove('hidden'); }
    } catch(e) {
        err.textContent = 'Lỗi kết nối';
        err.classList.remove('hidden');
    }
}

async function deleteCurtain(id, btn) {
    if (!confirm('Xóa cấu hình bạt này?')) return;
    const res = await fetch('/settings/iot/curtain/' + id + '/delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({})
    });
    const json = await res.json();
    if (json.ok) {
        document.getElementById('curtain_row_' + id)?.remove();
    } else { alert(json.message || 'Lỗi'); }
}

async function toggleAlert(id, btn) {
    const r = await fetch('/settings/iot/device/' + id + '/toggle-alert', {
        method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}
    });
    const d = await r.json();
    if (d.ok) {
        const on = d.alert_offline === 1;
        btn.textContent = on ? '🔔' : '🔕';
        btn.className = btn.className
            .replace('bg-orange-100', '').replace('text-orange-600', '')
            .replace('bg-gray-100', '').replace('text-gray-400', '').trim();
        btn.classList.add(...(on ? ['bg-orange-100','text-orange-600'] : ['bg-gray-100','text-gray-400']));
    }
}
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
