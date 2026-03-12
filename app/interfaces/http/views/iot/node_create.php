<?php
$title = 'Thêm node IoT mới';
ob_start();

// Group device types
$types_by_class = [];
foreach ($device_types as $t) {
    $types_by_class[$t->device_class][] = $t;
}
$class_labels = ['relay'=>'🎛️ Relay', 'sensor'=>'🌡️ Cảm biến'];
?>

<div class="mb-4 flex items-center gap-2">
    <a href="/settings/iot" class="text-sm text-blue-600">← IoT Settings</a>
    <span class="text-gray-300">/</span>
    <span class="text-sm font-semibold">Thêm node mới</span>
</div>

<?php if ($error === 'missing_fields'): ?>
<div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 rounded-xl text-sm text-red-600">
    ❌ Vui lòng điền đầy đủ: tên, mã thiết bị, loại thiết bị
</div>
<?php elseif ($error === 'duplicate_code'): ?>
<div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 rounded-xl text-sm text-red-600">
    ❌ Mã thiết bị <strong><?= htmlspecialchars($dup_code ?? '') ?></strong> đã tồn tại
</div>
<?php endif; ?>

<?php if ($saved && $new_device_id): ?>
<div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-2xl">
    <div class="text-sm font-semibold text-green-700 dark:text-green-300 mb-2">✅ Node đã được tạo!</div>
    <div class="flex gap-2">
        <a href="/settings/iot/firmware/<?= $new_device_id ?>"
           class="flex-1 text-center text-sm font-semibold py-2.5 rounded-xl bg-green-600 text-white">
            💾 Lấy Firmware ngay
        </a>
        <a href="/iot/nodes/create"
           class="flex-1 text-center text-sm font-semibold py-2.5 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
            ➕ Thêm node khác
        </a>
    </div>
</div>
<?php endif; ?>

<form method="POST" action="/iot/nodes/store" id="nodeForm">

<!-- STEP 1: Chọn loại thiết bị -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-3">
    <div class="text-sm font-semibold mb-3">① Loại thiết bị</div>
    <div class="space-y-2" id="typeList">
        <?php foreach ($types_by_class as $class => $types): ?>
        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mt-2 mb-1">
            <?= $class_labels[$class] ?? $class ?>
        </div>
        <?php foreach ($types as $t): ?>
        <?php
            $proto = $t->mqtt_protocol ? json_decode($t->mqtt_protocol, true) : [];
            $fields = $proto['fields'] ?? (isset($proto['env']['payload']) ? array_keys($proto['env']['payload']) : []);
            $sensors = $proto['sensors'] ?? [];
        ?>
        <label class="flex items-start gap-3 p-3 rounded-xl border-2 border-gray-100 dark:border-gray-700
                       has-[:checked]:border-blue-400 has-[:checked]:bg-blue-50 dark:has-[:checked]:bg-blue-900/20
                       cursor-pointer transition-all">
            <input type="radio" name="device_type_id" value="<?= $t->id ?>"
                   class="mt-0.5 accent-blue-600"
                   data-class="<?= $t->device_class ?>"
                   data-chip="<?= str_contains(strtolower($t->name), 'esp8266') ? 'esp8266' : 'esp32' ?>"
                   data-fields="<?= htmlspecialchars(implode(', ', $fields)) ?>"
                   data-sensors="<?= htmlspecialchars(implode(', ', $sensors)) ?>"
                   onchange="onTypeChange(this)"
                   <?= ($t->id == 4) ? 'checked' : '' ?>>
            <div class="flex-1">
                <div class="text-sm font-semibold"><?= htmlspecialchars($t->name) ?></div>
                <div class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($t->description ?? '') ?></div>
                <?php if (!empty($fields)): ?>
                <div class="flex flex-wrap gap-1 mt-1.5">
                    <?php foreach ($fields as $f): ?>
                    <span class="text-xs bg-teal-50 dark:bg-teal-900/30 text-teal-600 px-1.5 py-0.5 rounded-md"><?= $f ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </label>
        <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</div>

<!-- STEP 2: Thông tin cơ bản -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-3">
    <div class="text-sm font-semibold mb-3">② Thông tin thiết bị</div>
    <div class="space-y-3">

        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">Tên thiết bị *</label>
            <input type="text" name="name" required placeholder="vd: Cảm biến đầu chuồng 1"
                   class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
        </div>

        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">Mã thiết bị (device_code) *</label>
            <div class="flex gap-2">
                <input type="text" name="device_code" id="deviceCode" required
                       placeholder="vd: esp-barn1-temp-01"
                       class="flex-1 border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-400 font-mono">
                <button type="button" onclick="genCode()"
                        class="px-3 py-2 text-xs bg-gray-100 dark:bg-gray-700 rounded-xl text-gray-600 dark:text-gray-300 font-semibold">
                    🎲 Auto
                </button>
            </div>
            <div class="text-xs text-gray-400 mt-1">Chỉ dùng chữ thường, số, dấu gạch ngang. Không đổi sau khi flash!</div>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">Chip *</label>
            <input type="hidden" name="chip_type" id="chipTypeHidden" value="esp8266">
            <div class="flex gap-2">
                <button type="button" id="btnEsp8266"
                        onclick="setChip('esp8266')"
                        class="flex-1 py-2 text-sm font-semibold rounded-xl border-2 border-blue-400 bg-blue-50 dark:bg-blue-900/20 text-blue-600">
                    ESP8266
                </button>
                <button type="button" id="btnEsp32"
                        onclick="setChip('esp32')"
                        class="flex-1 py-2 text-sm font-semibold rounded-xl border-2 border-gray-200 dark:border-gray-600 text-gray-400">
                    ESP32
                </button>
            </div>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">Gắn vào chuồng</label>
            <select name="barn_id" id="barnSelect"
                    class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
                <option value="">— Chưa gán chuồng —</option>
                <?php foreach ($barns as $b): ?>
                <option value="<?= $b->id ?>">
                    <?= htmlspecialchars($b->name) ?>
                    <?= $b->active_cycle ? ' · ' . $b->active_cycle : ' · Chưa có chu kỳ' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">Vị trí trong chuồng</label>
            <input type="text" name="location_note" placeholder="vd: Đầu chuồng, cách mặt đất 1.5m"
                   class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
            <div class="text-xs text-gray-400 mt-1">Quan trọng cho AI — vị trí ảnh hưởng đến giá trị đo</div>
        </div>

    </div>
</div>

<!-- STEP 3: Cấu hình nâng cao -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-3">
    <div class="text-sm font-semibold mb-3">③ Cấu hình nâng cao</div>
    <div class="space-y-3">

        <div id="intervalRow">
            <label class="text-xs font-medium text-gray-500 block mb-1">Tần suất gửi ENV data</label>
            <select name="env_interval_seconds"
                    class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
                <option value="30">30 giây — Realtime (tốn pin/data)</option>
                <option value="60">1 phút</option>
                <option value="120">2 phút</option>
                <option value="300" selected>5 phút — Khuyến nghị</option>
                <option value="600">10 phút</option>
                <option value="900">15 phút — Tiết kiệm</option>
            </select>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">Ghi chú</label>
            <textarea name="notes" rows="2" placeholder="Ghi chú thêm về thiết bị, cấu hình phần cứng..."
                      class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-400 resize-none"></textarea>
        </div>

    </div>
</div>

<!-- Preview MQTT topic -->
<div class="bg-gray-50 dark:bg-gray-700/50 rounded-2xl p-4 mb-4" id="mqttPreview">
    <div class="text-xs font-semibold text-gray-500 mb-2">📡 MQTT Topic sẽ dùng</div>
    <div class="font-mono text-sm text-blue-600" id="mqttTopicPreview">cfarm/barn?</div>
    <div class="text-xs text-gray-400 mt-1">
        Heartbeat: <span class="font-mono" id="topicHB">cfarm/barn?/heartbeat</span><br>
        ENV data:  <span class="font-mono" id="topicEnv">cfarm/barn?/env</span>
    </div>
</div>

<button type="submit"
        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3.5 rounded-2xl text-sm transition-colors">
    ✅ Tạo node & lấy Firmware
</button>

</form>

<script>
function setChip(chip) {
    document.getElementById('chipTypeHidden').value = chip;
    const b8 = document.getElementById('btnEsp8266');
    const b32 = document.getElementById('btnEsp32');
    if (chip === 'esp8266') {
        b8.className  = 'flex-1 py-2 text-sm font-semibold rounded-xl border-2 border-blue-400 bg-blue-50 dark:bg-blue-900/20 text-blue-600';
        b32.className = 'flex-1 py-2 text-sm font-semibold rounded-xl border-2 border-gray-200 dark:border-gray-600 text-gray-400';
    } else {
        b32.className = 'flex-1 py-2 text-sm font-semibold rounded-xl border-2 border-blue-400 bg-blue-50 dark:bg-blue-900/20 text-blue-600';
        b8.className  = 'flex-1 py-2 text-sm font-semibold rounded-xl border-2 border-gray-200 dark:border-gray-600 text-gray-400';
    }
}

function onTypeChange(el) {
    const chip = el.dataset.chip || 'esp8266';
    setChip(chip);
    updateMqttPreview();
    // Show/hide interval row for relay
    document.getElementById('intervalRow').style.display =
        el.dataset.class === 'relay' ? 'none' : 'block';
}

function updateMqttPreview() {
    const barnSel = document.getElementById('barnSelect');
    const barnId  = barnSel.value || '?';
    const base    = barnId !== '?' ? `cfarm/barn${barnId}` : 'cfarm/barn?';
    document.getElementById('mqttTopicPreview').textContent = base;
    document.getElementById('topicHB').textContent  = base + '/heartbeat';
    document.getElementById('topicEnv').textContent = base + '/env';
}

function genCode() {
    const barnSel  = document.getElementById('barnSelect');
    const typeRadio = document.querySelector('input[name="device_type_id"]:checked');
    const barnId   = barnSel.value || 'x';
    const ts       = Date.now().toString().slice(-4);
    const prefix   = typeRadio?.dataset?.class === 'relay' ? 'relay' : 'env';
    document.getElementById('deviceCode').value = `esp-barn${barnId}-${prefix}-${ts}`;
}

document.getElementById('barnSelect').addEventListener('change', updateMqttPreview);

// Init
const firstType = document.querySelector('input[name="device_type_id"]:checked');
if (firstType) onTypeChange(firstType);
updateMqttPreview();
</script>

<?php $content = ob_get_clean(); require view_path('layouts/main.php'); ?>
