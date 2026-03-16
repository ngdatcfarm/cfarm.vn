<?php
$title = 'Quản lý firmware template';
ob_start();
?>
<div class="mb-3 flex items-center justify-between">
    <a href="/settings/iot" class="text-sm text-blue-600 hover:underline">← IoT Settings</a>
    <button onclick="openModal('modal-type-new')"
            class="bg-blue-600 text-white text-xs font-semibold px-3 py-1.5 rounded-full">+ Loại mới</button>
</div>

<!-- Header -->
<div class="bg-gray-800 rounded-2xl p-4 mb-4">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-xl">🗂️</div>
        <div>
            <div class="font-bold text-white">Firmware Templates</div>
            <div class="text-xs text-gray-400"><?= count($device_types) ?> loại thiết bị</div>
        </div>
    </div>
</div>

<!-- Type list -->
<div class="flex flex-col gap-2 mb-4" id="type-list">
<?php foreach ($device_types as $dt): ?>
<div class="type-card bg-white dark:bg-gray-800 rounded-2xl border-2 cursor-pointer transition-all
            <?= $dt->id === $selected_id ? 'border-blue-500' : 'border-gray-100 dark:border-gray-700' ?>"
     onclick="selectType(<?= $dt->id ?>)"
     id="card-<?= $dt->id ?>">
    <div class="p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-lg"><?= $dt->device_class === 'sensor' ? '🌡️' : '🔌' ?></span>
                <div>
                    <div class="text-sm font-semibold"><?= e($dt->name) ?></div>
                    <div class="text-xs text-gray-400">
                        <?= e($dt->device_class) ?> · <?= $dt->total_channels ?> kênh
                        · <?= $dt->device_count ?> thiết bị
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <?php if ($dt->firmware_template): ?>
                <span class="text-xs bg-green-100 dark:bg-green-900/30 text-green-600 px-2 py-0.5 rounded-full">✅ fw</span>
                <?php else: ?>
                <span class="text-xs bg-red-100 dark:bg-red-900/30 text-red-500 px-2 py-0.5 rounded-full">❌ fw</span>
                <?php endif; ?>
                <span class="text-gray-300 ml-1"><?= $dt->id === $selected_id ? '▼' : '›' ?></span>
            </div>
        </div>
    </div>

    <!-- Editor — chỉ hiện khi selected -->
    <div id="editor-<?= $dt->id ?>" class="<?= $dt->id === $selected_id ? '' : 'hidden' ?> border-t border-gray-100 dark:border-gray-700">

        <!-- Meta info -->
        <div class="p-4 bg-gray-50 dark:bg-gray-900/50">
            <div class="text-xs font-semibold text-gray-500 mb-2">ℹ️ Thông tin</div>
            <div class="grid grid-cols-2 gap-2">
                <input id="meta-name-<?= $dt->id ?>" value="<?= e($dt->name) ?>"
                       placeholder="Tên loại"
                       class="col-span-2 border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-800">
                <input id="meta-desc-<?= $dt->id ?>" value="<?= e($dt->description ?? '') ?>"
                       placeholder="Mô tả"
                       class="col-span-2 border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-800">
                <select id="meta-class-<?= $dt->id ?>"
                        class="border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-800">
                    <option value="relay"  <?= $dt->device_class==='relay'  ?'selected':'' ?>>relay</option>
                    <option value="sensor" <?= $dt->device_class==='sensor' ?'selected':'' ?>>sensor</option>
                    <option value="mixed"  <?= $dt->device_class==='mixed'  ?'selected':'' ?>>mixed</option>
                </select>
                <input id="meta-ch-<?= $dt->id ?>" type="number" value="<?= $dt->total_channels ?>"
                       placeholder="Số kênh"
                       class="border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-800">
            </div>
            <button onclick="saveMeta(<?= $dt->id ?>)"
                    class="mt-2 w-full bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold py-2 rounded-lg">
                💾 Lưu thông tin
            </button>
        </div>

        <!-- Firmware version -->
        <div class="p-4 border-t border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-semibold text-gray-500">🏷️ Firmware Version</div>
                <div class="flex gap-2 items-center">
                    <input id="fw-ver-<?= $dt->id ?>"
                           value="<?= e($dt->firmware_version ?? '1.0.0') ?>"
                           class="border border-gray-200 dark:border-gray-600 rounded-lg px-2 py-1 text-xs w-20 text-center font-mono"
                           placeholder="1.0.0">
                    <button onclick="saveFirmwareVersion(<?= $dt->id ?>)"
                            class="bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold px-2 py-1 rounded-lg">
                        💾
                    </button>
                </div>
            </div>
            <div class="text-xs text-gray-400">
                Version dùng để tracking khi cấp phát firmware cho thiết bị
            </div>
        </div>

        <!-- Base Firmware (có thể tái sử dụng) -->
        <div class="p-4 border-t border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-semibold text-gray-500">📦 Base Firmware (Template gốc)</div>
                <div class="flex gap-2">
                    <span id="basefw-status-<?= $dt->id ?>" class="text-xs text-gray-400"></span>
                    <button onclick="saveBaseFirmware(<?= $dt->id ?>)"
                            class="bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-3 py-1 rounded-lg">
                        💾 Lưu
                    </button>
                </div>
            </div>
            <textarea id="basefw-<?= $dt->id ?>"
                      class="w-full font-mono text-xs bg-gray-900 text-blue-400 p-3 rounded-xl resize-y border-0 outline-none"
                      style="min-height:160px; line-height:1.5"
                      placeholder="// Base firmware code - dùng làm template cho firmware mới..."
                      spellcheck="false"><?= e($dt->base_firmware ?? '') ?></textarea>
        </div>

        <!-- Firmware template editor -->
        <div class="p-4 border-t border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-semibold text-gray-500">📝 Firmware Template (Arduino C++)</div>
                <div class="flex gap-2">
                    <span id="fw-status-<?= $dt->id ?>" class="text-xs text-gray-400"></span>
                    <button onclick="saveFirmware(<?= $dt->id ?>)"
                            class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1 rounded-lg">
                        💾 Lưu
                    </button>
                    <button onclick="copyCode('fw-<?= $dt->id ?>')"
                            class="bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-semibold px-3 py-1 rounded-lg">
                        📋 Copy
                    </button>
                </div>
            </div>
            <textarea id="fw-<?= $dt->id ?>"
                      class="w-full font-mono text-xs bg-gray-900 text-green-400 p-3 rounded-xl resize-y border-0 outline-none"
                      style="min-height:320px; line-height:1.5"
                      placeholder="// Paste Arduino code tại đây..."
                      spellcheck="false"><?= e($dt->firmware_template ?? '') ?></textarea>
            <div class="text-xs text-gray-400 mt-1">
                💡 Dùng placeholder: <code>YOUR_DEVICE_CODE</code>, <code>cfarm/barnX</code>,
                <code>YOUR_WIFI_SSID</code>, <code>YOUR_WIFI_PASS</code>
                — hệ thống tự thay khi render firmware cho từng device.
            </div>
        </div>

        <!-- MQTT Protocol editor -->
        <div class="p-4 border-t border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-semibold text-gray-500">📡 MQTT Protocol (JSON mô tả)</div>
                <div class="flex gap-2">
                    <span id="proto-status-<?= $dt->id ?>" class="text-xs text-gray-400"></span>
                    <button onclick="saveProto(<?= $dt->id ?>)"
                            class="bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold px-3 py-1 rounded-lg">
                        💾 Lưu
                    </button>
                </div>
            </div>
            <textarea id="proto-<?= $dt->id ?>"
                      class="w-full font-mono text-xs bg-gray-900 text-teal-400 p-3 rounded-xl resize-y border-0 outline-none"
                      style="min-height:120px; line-height:1.5"
                      placeholder='{"heartbeat":{"topic":"{barn}/heartbeat",...}}'
                      spellcheck="false"><?= e($dt->mqtt_protocol ?? '') ?></textarea>
        </div>

        <!-- Xóa type -->
        <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div class="text-xs text-gray-400"><?= $dt->device_count ?> thiết bị đang dùng loại này</div>
            <?php if ($dt->device_count == 0): ?>
            <button onclick="deleteType(<?= $dt->id ?>, '<?= e($dt->name) ?>')"
                    class="text-xs text-red-500 hover:text-red-700 font-semibold px-3 py-1.5 border border-red-200 rounded-lg">
                🗑️ Xóa loại
            </button>
            <?php else: ?>
            <span class="text-xs text-gray-300">Không thể xóa khi còn thiết bị</span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Modal thêm type mới -->
<div id="modal-type-new" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-sm p-5 shadow-2xl">
        <div class="flex items-center justify-between mb-4">
            <div class="font-bold">+ Loại thiết bị mới</div>
            <button onclick="closeModal('modal-type-new')" class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 flex items-center justify-center">✕</button>
        </div>
        <form method="POST" action="/settings/iot/type/store" class="space-y-3">
            <input name="name" placeholder="Tên loại (VD: ESP32 Relay 4 kênh)" required
                   class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-transparent">
            <input name="description" placeholder="Mô tả ngắn"
                   class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-transparent">
            <div class="grid grid-cols-2 gap-2">
                <select name="device_class" class="border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-800">
                    <option value="relay">relay</option>
                    <option value="sensor">sensor</option>
                    <option value="mixed">mixed</option>
                </select>
                <input name="total_channels" type="number" value="8" placeholder="Số kênh"
                       class="border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-transparent">
            </div>
            <!-- Redirect về types page sau khi tạo -->
            <input type="hidden" name="_redirect" value="/settings/iot/types">
            <div class="flex gap-2 pt-1">
                <button type="button" onclick="closeModal('modal-type-new')"
                        class="flex-1 border border-gray-200 dark:border-gray-600 rounded-xl py-2.5 text-sm">Hủy</button>
                <button type="submit"
                        class="flex-1 bg-blue-600 text-white rounded-xl py-2.5 text-sm font-semibold">Tạo</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

function selectType(id) {
    // Ẩn tất cả editors
    document.querySelectorAll('[id^="editor-"]').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.type-card').forEach(el => {
        el.classList.remove('border-blue-500');
        el.classList.add('border-gray-100', 'dark:border-gray-700');
        el.querySelector('span:last-child').textContent = '›';
    });
    // Hiện editor được chọn
    document.getElementById('editor-' + id)?.classList.remove('hidden');
    const card = document.getElementById('card-' + id);
    if (card) {
        card.classList.add('border-blue-500');
        card.classList.remove('border-gray-100');
        card.querySelector('span:last-child').textContent = '▼';
    }
    // Cập nhật URL
    history.replaceState(null, '', '/settings/iot/types?id=' + id);
}

async function saveFirmware(id) {
    const val = document.getElementById('fw-' + id).value;
    const st  = document.getElementById('fw-status-' + id);
    st.textContent = 'Đang lưu...';
    try {
        const r = await fetch('/settings/iot/types/' + id + '/save', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body: 'field=firmware_template&value=' + encodeURIComponent(val)
        });
        const d = await r.json();
        st.textContent = d.ok ? '✅ Đã lưu ' + d.saved_at : '❌ Lỗi';
        setTimeout(() => st.textContent = '', 4000);
    } catch(e) { st.textContent = '❌ Lỗi mạng'; }
}

async function saveFirmwareVersion(id) {
    const val = document.getElementById('fw-ver-' + id).value;
    const st  = document.getElementById('fw-status-' + id);
    st.textContent = 'Đang lưu...';
    try {
        const r = await fetch('/settings/iot/types/' + id + '/save', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body: 'field=firmware_version&value=' + encodeURIComponent(val)
        });
        const d = await r.json();
        st.textContent = d.ok ? '✅ Version saved!' : '❌ Lỗi';
        setTimeout(() => st.textContent = '', 4000);
    } catch(e) { st.textContent = '❌ Lỗi mạng'; }
}

async function saveBaseFirmware(id) {
    const val = document.getElementById('basefw-' + id).value;
    const st  = document.getElementById('basefw-status-' + id);
    st.textContent = 'Đang lưu...';
    try {
        const r = await fetch('/settings/iot/types/' + id + '/save', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body: 'field=base_firmware&value=' + encodeURIComponent(val)
        });
        const d = await r.json();
        st.textContent = d.ok ? '✅ Đã lưu base firmware' : '❌ Lỗi';
        setTimeout(() => st.textContent = '', 4000);
    } catch(e) { st.textContent = '❌ Lỗi mạng'; }
}

async function saveProto(id) {
    const val = document.getElementById('proto-' + id).value;
    const st  = document.getElementById('proto-status-' + id);
    st.textContent = 'Đang lưu...';
    try {
        const r = await fetch('/settings/iot/types/' + id + '/save', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body: 'field=mqtt_protocol&value=' + encodeURIComponent(val)
        });
        const d = await r.json();
        st.textContent = d.ok ? '✅ ' + d.saved_at : '❌ Lỗi';
        setTimeout(() => st.textContent = '', 4000);
    } catch(e) { st.textContent = '❌ Lỗi'; }
}

async function saveMeta(id) {
    const body = new URLSearchParams({
        field: 'meta',
        name: document.getElementById('meta-name-' + id).value,
        description: document.getElementById('meta-desc-' + id).value,
        device_class: document.getElementById('meta-class-' + id).value,
        total_channels: document.getElementById('meta-ch-' + id).value,
    });
    try {
        const r = await fetch('/settings/iot/types/' + id + '/save', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body: body.toString()
        });
        const d = await r.json();
        if (d.ok) {
            // Cập nhật tên trên card
            const nameEl = document.querySelector('#card-' + id + ' .font-semibold');
            if (nameEl) nameEl.textContent = document.getElementById('meta-name-' + id).value;
            alert('✅ Đã lưu thông tin!');
        }
    } catch(e) { alert('❌ Lỗi'); }
}

async function deleteType(id, name) {
    if (!confirm('Xóa loại "' + name + '"?')) return;
    const r = await fetch('/settings/iot/type/' + id + '/delete', {
        method: 'POST',
        headers: {'X-Requested-With':'XMLHttpRequest'}
    });
    const d = await r.json();
    if (d.ok) location.reload();
}

function copyCode(id) {
    const code = document.getElementById(id).value;
    navigator.clipboard.writeText(code).then(() => {
        alert('✅ Đã copy firmware code!');
    });
}

// Ctrl+S để lưu firmware của type đang mở
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        const selected = document.querySelector('.type-card .border-blue-500') ||
                         document.querySelector('[id^="editor-"]:not(.hidden)');
        if (selected) {
            const id = selected.id.replace('editor-', '');
            if (id) saveFirmware(parseInt(id));
        }
    }
});

// Redirect sau khi tạo type mới
const urlParams = new URLSearchParams(window.location.search);
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
