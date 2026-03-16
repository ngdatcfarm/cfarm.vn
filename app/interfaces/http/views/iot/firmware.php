<?php
$title = 'Firmware — ' . e($device->device_code);
$device_class = $device->device_class ?? 'relay';

// Relay pins & interlocks (chỉ dùng cho relay)
// Đọc custom pins từ GET (nếu user tùy chỉnh)
$default_pins = [1=>32, 2=>33, 3=>25, 4=>26, 5=>27, 6=>14, 7=>12, 8=>13];
$relay_pins = $default_pins;
if (!empty($_GET['pins'])) {
    $custom = explode(',', $_GET['pins']);
    if (count($custom) === 8) {
        foreach ($custom as $i => $p) {
            $p = (int)$p;
            if ($p > 0 && $p <= 39) $relay_pins[$i+1] = $p;
        }
    }
}
$interlocks = [];
foreach ($curtains as $cur) {
    $interlocks[] = ['up' => (int)$cur->up_ch, 'down' => (int)$cur->down_ch];
}

// Lấy firmware từ template device_type, thay placeholders
if (!empty($device->firmware_template)) {
    $firmware_code = $device->firmware_template;
    $firmware_code = str_replace('YOUR_DEVICE_CODE', $device->device_code,  $firmware_code);
    $firmware_code = str_replace('YOUR_WIFI_SSID',   'Dat Lim',             $firmware_code);
    $firmware_code = str_replace('YOUR_WIFI_PASS',   'hoilamgi',            $firmware_code);
    $firmware_code = str_replace('cfarm/barnX',      $device->mqtt_topic,   $firmware_code);

    // Relay: cập nhật RELAY_PINS array
    if ($device_class === 'relay') {
        $pins_str = implode(',', array_values($relay_pins));
        $firmware_code = preg_replace(
            '/const int RELAY_PINS\[8\]\s*=\s*\{[^}]+\};/',
            'const int RELAY_PINS[8] = {' . $pins_str . '};',
            $firmware_code
        );
    }
    // Relay: cập nhật interlock pairs
    if ($device_class === 'relay' && !empty($interlocks)) {
        $pairs_str = implode(',', array_map(fn($il) => '{' . $il['up'] . ',' . $il['down'] . '}', $interlocks));
        $firmware_code = preg_replace(
            '/const int INTERLOCK_PAIRS\[\]\[2\] = \{[^;]+\};/',
            'const int INTERLOCK_PAIRS[][2] = {' . $pairs_str . '};',
            $firmware_code
        );
        $firmware_code = preg_replace(
            '/const int NUM_INTERLOCKS = \d+;/',
            'const int NUM_INTERLOCKS = ' . count($interlocks) . ';',
            $firmware_code
        );
    }
    $source_label = '📦 ' . e($device->type_name) . ' v' . e($device->firmware_version ?? '1.0.0');
} else {
    $firmware_code = '// Chưa có firmware template cho loại: ' . e($device->type_name) . "\n"
        . '// Vào Settings > IoT > Loại thiết bị > Sửa để thêm firmware template.';
    $source_label = '⚠️ Chưa có template';
}

// Header color theo device_class
$header_color = match($device_class) {
    'sensor' => 'bg-teal-600',
    'mixed'  => 'bg-purple-600',
    default  => 'bg-indigo-600',
};
$header_icon = match($device_class) {
    'sensor' => '🌡️',
    'mixed'  => '🔀',
    default  => '🔌',
};

ob_start();
?>
<div class="mb-4 flex items-center justify-between">
    <a href="/settings/iot" class="text-sm text-blue-600 hover:underline">← IoT Settings</a>
    <a href="/iot/devices" class="text-sm text-gray-400 hover:underline">📡 Thiết bị</a>
</div>

<!-- Header -->
<div class="<?= $header_color ?> rounded-2xl p-4 mb-4">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-2xl"><?= $header_icon ?></div>
        <div>
            <div class="text-lg font-bold text-white"><?= e($device->device_code) ?></div>
            <div class="text-sm text-white/70">
                <?= e($device->barn_name ?? 'Chưa gán chuồng') ?> · <?= e($device->type_name ?? $device_class) ?>
            </div>
            <div class="text-xs text-white/50 mt-0.5"><?= $source_label ?></div>
        </div>
    </div>
</div>

<!-- Firmware Allocation -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="flex items-center justify-between mb-3">
        <div class="text-sm font-semibold">📦 Cấp phát Firmware</div>
        <button onclick="allocateFirmware()"
                class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg">
            🔄 Cấp phát mới
        </button>
    </div>
    <?php if (!empty($allocations)): ?>
    <div class="text-xs space-y-1">
        <div class="text-gray-500 font-semibold mb-2">📋 Lịch sử cấp phát:</div>
        <?php foreach ($allocations as $alloc): ?>
        <div class="flex justify-between items-center py-1 border-b border-gray-100 dark:border-gray-700 last:border-0">
            <div>
                <span class="font-mono bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">v<?= e($alloc->firmware_version) ?></span>
                <span class="text-gray-500 ml-2"><?= e($alloc->type_name) ?></span>
            </div>
            <div class="text-gray-400"><?= date('d/m/Y H:i', strtotime($alloc->allocated_at)) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-xs text-gray-400 italic">Chưa có lịch sử cấp phát</div>
    <?php endif; ?>
</div>

<!-- Available Firmwares from Library -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="flex items-center justify-between mb-3">
        <div class="text-sm font-semibold">📚 Firmware Library</div>
        <a href="/settings/iot/firmwares"
           class="text-xs text-blue-500 hover:underline">
           📦 Xem tất cả
        </a>
    </div>
    <?php if (!empty($available_firmwares)): ?>
    <div class="text-xs space-y-1">
        <?php foreach ($available_firmwares as $fw): ?>
        <div class="flex justify-between items-center py-1 border-b border-gray-100 dark:border-gray-700 last:border-0">
            <div class="flex items-center gap-2">
                <span class="font-mono bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 px-2 py-0.5 rounded">v<?= e($fw->version) ?></span>
                <span class="text-gray-500"><?= number_format($fw->file_size) ?> bytes</span>
            </div>
            <div class="flex items-center gap-2">
                <a href="/api/firmware/download/<?= $fw->id ?>" download
                   class="text-blue-500 hover:text-blue-600 text-xs">
                   ⬇️ Tải
                </a>
                <span class="text-gray-400"><?= date('d/m/y', strtotime($fw->uploaded_at)) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-xs text-gray-400 italic">
        Chưa có firmware nào được upload cho loại này.
        <a href="/settings/iot/firmwares" class="text-blue-500 hover:underline">Upload now</a>
    </div>
    <?php endif; ?>
</div>

<script>
async function allocateFirmware() {
    if (!confirm('Cấp phát firmware mới cho thiết bị này?')) return;
    try {
        const r = await fetch('/settings/iot/device/<?= $device->id ?>/allocate-firmware', {
            method: 'POST',
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
        const d = await r.json();
        if (d.ok) {
            alert('✅ ' + d.message);
            location.reload();
        } else {
            alert('❌ ' + d.message);
        }
    } catch(e) {
        alert('❌ Lỗi kết nối');
    }
}
</script>

<?php if ($device_class === 'relay'): ?>
<!-- Pin Configurator -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="flex items-center justify-between mb-3">
        <div class="text-sm font-semibold">🔌 GPIO Pin Mapping</div>
        <button onclick="togglePinEditor()" id="pinEditorBtn"
                class="text-xs text-blue-500 font-semibold">✏️ Tùy chỉnh</button>
    </div>

    <!-- Preset selector -->
    <div class="flex gap-2 mb-3 flex-wrap" id="presetRow">
        <span class="text-xs text-gray-400 self-center">Preset:</span>
        <button onclick="applyPreset([32,33,25,26,27,14,12,13])"
                class="text-xs px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-700 font-mono">
            Default
        </button>
        <button onclick="applyPreset([16,17,18,19,21,22,23,25])"
                class="text-xs px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-700 font-mono">
            GPIO 16-25
        </button>
        <button onclick="applyPreset([4,5,18,19,21,22,23,26])"
                class="text-xs px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-700 font-mono">
            Common 4-26
        </button>
        <button onclick="applyPreset([2,4,5,12,13,14,15,16])"
                class="text-xs px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-700 font-mono">
            Low GPIO
        </button>
    </div>

    <!-- Pin inputs — hidden by default -->
    <div id="pinEditor" class="hidden">
        <div class="grid grid-cols-4 gap-2 mb-3">
            <?php for ($i = 1; $i <= 8; $i++): ?>
            <div class="text-center">
                <div class="text-xs text-gray-400 mb-1">CH<?= $i ?></div>
                <input type="number" id="pin<?= $i ?>"
                       value="<?= $relay_pins[$i] ?>"
                       min="0" max="39"
                       onchange="onPinChange()"
                       class="w-full text-center border border-gray-200 dark:border-gray-600 rounded-lg py-1.5 text-sm font-mono bg-white dark:bg-gray-700">
            </div>
            <?php endfor; ?>
        </div>
        <button onclick="applyCustomPins()"
                class="w-full py-2 text-sm font-semibold bg-blue-600 text-white rounded-xl">
            🔄 Cập nhật firmware
        </button>
    </div>

    <!-- Current mapping summary -->
    <div class="grid grid-cols-4 gap-1" id="pinSummary">
        <?php foreach ($relay_pins as $ch => $pin): ?>
        <div class="text-xs text-center py-1 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
            <span class="text-gray-400">CH<?= $ch ?></span>
            <span class="font-mono font-semibold ml-1">G<?= $pin ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
const currentPins = <?= json_encode(array_values($relay_pins)) ?>;
const deviceId = <?= $device->id ?>;
const currentUrl = new URL(window.location.href);

function togglePinEditor() {
    const ed = document.getElementById('pinEditor');
    const btn = document.getElementById('pinEditorBtn');
    if (ed.classList.contains('hidden')) {
        ed.classList.remove('hidden');
        btn.textContent = '✖ Đóng';
    } else {
        ed.classList.add('hidden');
        btn.textContent = '✏️ Tùy chỉnh';
    }
}

function applyPreset(pins) {
    pins.forEach((p, i) => {
        const el = document.getElementById('pin' + (i+1));
        if (el) el.value = p;
    });
    document.getElementById('pinEditor').classList.remove('hidden');
    document.getElementById('pinEditorBtn').textContent = '✖ Đóng';
}

function applyCustomPins() {
    const pins = [];
    for (let i = 1; i <= 8; i++) {
        pins.push(parseInt(document.getElementById('pin' + i).value) || 0);
    }
    currentUrl.searchParams.set('pins', pins.join(','));
    window.location.href = currentUrl.toString();
}

function onPinChange() {} // placeholder
</script>

<!-- Channel mapping — chỉ hiển thị cho relay -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">📋 Kênh & GPIO</div>
    <div class="space-y-0">
    <?php foreach ($channels as $ch): ?>
    <div class="flex justify-between items-center text-xs py-1.5 border-t border-gray-100 dark:border-gray-700">
        <span class="font-medium">CH<?= e($ch->channel_number) ?> · GPIO <?= e($relay_pins[$ch->channel_number] ?? '?') ?></span>
        <span class="text-gray-400"><?= e($ch->name) ?> <span class="text-gray-300">(<?= e($ch->channel_type) ?>)</span></span>
    </div>
    <?php endforeach; ?>
    </div>
    <?php if (!empty($interlocks)): ?>
    <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
        <div class="text-xs font-semibold text-gray-500 mb-1">🔒 Interlock</div>
        <?php foreach ($curtains as $cur): ?>
        <div class="text-xs text-gray-400">↑CH<?= $cur->up_ch ?> ↔ ↓CH<?= $cur->down_ch ?> — <?= e($cur->name) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($device_class === 'sensor'): ?>
<!-- Sensor info -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-2">🌡️ Cấu hình cảm biến</div>
    <div class="text-xs text-gray-500 space-y-1">
        <div>Pin cảm biến: <strong>GPIO 4</strong> (mặc định, sửa <code>DHT_PIN</code> nếu khác)</div>
        <div>Loại cảm biến: <strong>DHT22</strong> (sửa <code>DHT_TYPE</code> nếu dùng DHT11)</div>
        <div>Gửi telemetry: <strong>mỗi 60 giây</strong></div>
        <div>Topic: <strong><?= e($device->mqtt_topic) ?>/telemetry</strong></div>
    </div>
</div>
<?php endif; ?>

<!-- MQTT Protocol -->
<?php if ($device->mqtt_protocol): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <details>
        <summary class="text-sm font-semibold cursor-pointer">📡 MQTT Protocol</summary>
        <pre class="text-xs bg-gray-900 text-green-400 p-3 rounded-xl mt-3 overflow-x-auto"><?= e($device->mqtt_protocol) ?></pre>
    </details>
</div>
<?php endif; ?>

<!-- Firmware code -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="flex items-center justify-between mb-3">
        <div class="text-sm font-semibold">📝 Arduino Sketch</div>
        <div class="flex gap-2">
            <a href="/settings/iot/firmware/<?= $device->id ?>/raw"
               download
               class="bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-3 py-2 rounded-lg">
                ⬇️ Download .ino
            </a>
            <button onclick="copyFirmware()"
                    class="<?= str_replace('bg-', 'bg-', $header_color) ?> hover:opacity-90 text-white text-xs font-semibold px-4 py-2 rounded-lg">
                📋 Copy Code
            </button>
        </div>
    </div>
    <pre id="firmware_code" class="bg-gray-900 text-green-400 text-xs p-4 rounded-xl overflow-x-auto max-h-[65vh] overflow-y-auto leading-relaxed"><code><?= e($firmware_code) ?></code></pre>
</div>

<!-- OTA Instructions -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <details>
        <summary class="text-sm font-semibold cursor-pointer">🔄 Hướng dẫn cài đặt OTA (Auto-Update)</summary>
        <div class="mt-3 text-xs text-gray-500 space-y-2">
            <div><strong>Bước 1:</strong> Thêm thư viện ESPhttpUpdate vào Arduino IDE:
                <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">Library Manager → Search "ESP32httpUpdate"</code>
            </div>
            <div><strong>Bước 2:</strong> Thêm code sau vào sketch (trước setup):</div>
            <pre class="bg-gray-900 text-green-400 p-2 rounded overflow-x-auto text-[10px]">// ========== OTA CONFIG ==========
#include &lt;ESP32HTTPUpdate.h&gt;

#define OTA_CHECK_INTERVAL 3600000  // 1 giờ
#define FIRMWARE_VERSION "<?= e($device->firmware_version ?? '1.0.0') ?>"
#define DEVICE_TYPE_ID <?= $device->device_type_id ?? 0 ?>

String otaCheckUrl = String("<?= $_SERVER['HTTP_HOST'] ?? 'app.cfarm.vn' ?>/api/firmware/") + DEVICE_TYPE_ID + "/latest?version=" + FIRMWARE_VERSION;

void handleOTA() {
    Serial.println("[OTA] Checking for updates...");
    t_httpUpdate_return ret = ESPhttpUpdate.update(otaCheckUrl);
    switch(ret) {
        case HTTP_UPDATE_OK:
            Serial.println("[OTA] Update successful! Rebooting...");
            break;
        case HTTP_UPDATE_NO_UPDATES:
            Serial.println("[OTA] No updates available");
            break;
        case HTTP_UPDATE_FAILED:
            Serial.printf("[OTA] Update failed: %s\n", ESPhttpUpdate.getLastErrorString().c_str());
            break;
    }
}

unsigned long lastOtaCheck = 0;

// Trong loop(), thêm:
if (millis() - lastOtaCheck > OTA_CHECK_INTERVAL) {
    lastOtaCheck = millis();
    handleOTA();
}</pre>
            <div><strong>Bước 3:</strong> Upload firmware đầu tiên qua USB</div>
            <div><strong>Bước 4:</strong> Các lần sau, upload firmware mới lên Library, ESP32 sẽ tự cập nhật!</div>
            <div class="text-green-600 mt-2">✅ ESP32 sẽ tự kiểm tra cập nhật mỗi giờ và flash firmware mới khi có</div>
        </div>
    </details>
</div>

<!-- Hướng dẫn -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
    <div class="text-sm font-semibold mb-2">📖 Hướng dẫn flash</div>
    <div class="text-xs text-gray-500 space-y-1.5">
        <div>1. Arduino IDE → board <strong>ESP32 Dev Module</strong></div>
        <div>2. Cài thư viện: <strong>PubSubClient</strong>, <strong>ArduinoJson</strong>
            <?php if ($device_class === 'sensor'): ?>, <strong>DHT sensor library</strong><?php endif; ?>
        </div>
        <div>3. Copy code → paste vào sketch mới</div>
        <div>4. Sửa <code>WIFI_SSID</code>, <code>WIFI_PASSWORD</code> nếu cần</div>
        <div>5. Upload → Serial Monitor (115200 baud)</div>
        <div>6. Khi thấy <em>"MQTT connected!"</em> là xong ✅</div>
    </div>
</div>

<script>
function copyFirmware() {
    const code = document.getElementById('firmware_code').textContent;
    navigator.clipboard.writeText(code).then(() => {
        const btn = event.target;
        const orig = btn.textContent;
        btn.textContent = '✅ Đã copy!';
        setTimeout(() => btn.textContent = orig, 2000);
    });
}
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
