<?php
global $pdo;

$title = 'Cài đặt IoT';
$tab = $_GET['tab'] ?? 'devices';

// Get device types
$device_types = [];
$barns = [];
$devices = [];
$curtains = [];

try {
    $device_types = $pdo->query("SELECT * FROM device_types ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
    $barns = $pdo->query("SELECT * FROM barns ORDER BY number")->fetchAll(PDO::FETCH_OBJ);
    
    // Get devices
    $devices = $pdo->query("
        SELECT d.*, b.name as barn_name, dt.name as type_name
        FROM devices d
        LEFT JOIN barns b ON b.id = d.barn_id
        LEFT JOIN device_types dt ON dt.id = d.device_type_id
        ORDER BY b.name, d.name
    ")->fetchAll(PDO::FETCH_OBJ);
    
    // Get curtains
    $curtains = $pdo->query("
        SELECT cc.*, b.name as barn_name, d.name as device_name
        FROM curtain_configs cc
        LEFT JOIN barns b ON b.id = cc.barn_id
        LEFT JOIN devices d ON d.id = cc.device_id
        ORDER BY b.name, cc.name
    ")->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    error_log("IoT settings error: " . $e->getMessage());
}

ob_start();
?>

<div class="max-w-lg mx-auto">
    <div class="flex items-center gap-3 mb-4">
        <a href="/settings" class="text-gray-400 hover:text-gray-600">←</a>
        <h1 class="text-xl font-bold">🎛️ Cài đặt IoT</h1>
    </div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="mb-4 p-3 bg-green-50 text-green-600 rounded-xl text-sm">✅ Đã lưu!</div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
    <div class="mb-4 p-3 bg-red-50 text-red-600 rounded-xl text-sm">
        <?php
            $errMsg = match($_GET['error']) {
                'missing_fields' => '❌ Vui lòng điền đầy đủ thông tin',
                'duplicate_code' => '❌ Mã thiết bị đã tồn tại, vui lòng chọn mã khác',
                default => '❌ Lỗi',
            };
            echo $errMsg;
        ?>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="flex gap-1 mb-4 bg-gray-100 dark:bg-gray-700 p-1 rounded-xl">
        <a href="/settings/iot?tab=devices" 
           class="flex-1 py-2 px-3 text-center text-sm font-medium rounded-lg transition-colors <?= $tab === 'devices' ? 'bg-white dark:bg-gray-600 shadow text-blue-600' : 'text-gray-500 hover:text-gray-700' ?>">
            📟 Thiết bị
        </a>
        <a href="/settings/iot?tab=curtains" 
           class="flex-1 py-2 px-3 text-center text-sm font-medium rounded-lg transition-colors <?= $tab === 'curtains' ? 'bg-white dark:bg-gray-600 shadow text-blue-600' : 'text-gray-500 hover:text-gray-700' ?>">
            🪟 Cấu hình bạt
        </a>
    </div>

    <?php if ($tab === 'devices'): ?>
    
    <!-- Thêm thiết bị mới -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-4 mb-5">
        <div class="text-sm font-semibold mb-3">➕ Thêm thiết bị ESP</div>
        
        <form method="POST" action="/settings/iot/device/store" class="space-y-3">
            
            <!-- Chọn chuồng và loại trước -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Chuồng <span class="text-red-500">*</span></label>
                    <select name="barn_id" id="barnSelect" required
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm"
                            onchange="autoGenerate()">
                        <option value="">— Chọn —</option>
                        <?php foreach ($barns as $b): ?>
                        <option value="<?= $b->id ?>" data-name="<?= htmlspecialchars($b->name) ?>" data-number="<?= $b->number ?>">
                            <?= htmlspecialchars($b->name) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Loại thiết bị <span class="text-red-500">*</span></label>
                    <select name="device_type_id" id="deviceTypeSelect" required
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm"
                            onchange="autoGenerate()">
                        <option value="">— Chọn —</option>
                        <?php foreach ($device_types as $t): ?>
                        <option value="<?= $t->id ?>" data-class="<?= $t->device_class ?>" data-channels="<?= $t->total_channels ?>">
                            <?= htmlspecialchars($t->name) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium mb-1">Mã thiết bị (device_code)</label>
                <input type="text" name="device_code" id="deviceCode" placeholder="auto-generated" required
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm font-mono">
            </div>

            <div>
                <label class="block text-xs font-medium mb-1">Tên hiển thị</label>
                <input type="text" name="name" id="deviceName" placeholder="auto-generated" required
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm">
            </div>

            <div>
                <label class="block text-xs font-medium mb-1">MQTT Topic</label>
                <input type="text" name="mqtt_topic" id="mqttTopic" placeholder="auto-generated" required
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm font-mono">
            </div>

            <div>
                <label class="block text-xs font-medium mb-1">Ghi chú</label>
                <input type="text" name="notes" id="notes" placeholder="Ghi chú thêm..."
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm"
                       oninput="autoGenerate()">
            </div>

            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2.5 rounded-xl text-sm">
                ➕ Thêm thiết bị
            </button>
        </form>
    </div>

    <!-- Danh sách thiết bị -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
        <?php if (empty($devices)): ?>
        <div class="p-8 text-center text-gray-400">
            <div class="text-3xl mb-2">📡</div>
            <div>Chưa có thiết bị nào</div>
        </div>
        <?php else: ?>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Thiết bị</th>
                    <th class="px-4 py-3 text-left font-semibold">Chuồng</th>
                    <th class="px-4 py-3 text-left font-semibold">Trạng thái</th>
                    <th class="px-2 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php foreach ($devices as $d): ?>
                <tr>
                    <td class="px-4 py-3">
                        <div class="font-semibold"><?= htmlspecialchars($d->name) ?></div>
                        <div class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($d->mqtt_topic) ?></div>
                    </td>
                    <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($d->barn_name ?? '—') ?></td>
                    <td class="px-4 py-3">
                        <?php if ($d->is_online): ?>
                        <span class="inline-flex items-center gap-1 text-green-600 text-xs font-medium">
                            <span class="w-2 h-2 bg-green-500 rounded-full"></span> Online
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-gray-400 text-xs">
                            <span class="w-2 h-2 bg-gray-300 rounded-full"></span> Offline
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-2 py-3">
                        <button onclick="viewFirmware(<?= $d->id ?>, '<?= htmlspecialchars($d->mqtt_topic, ENT_QUOTES) ?>')" class="text-blue-500 hover:text-blue-700 text-xs" title="Xem firmware">📦</button>
                        <button onclick="flashFirmware(<?= $d->id ?>, '<?= htmlspecialchars($d->mqtt_topic, ENT_QUOTES) ?>')" class="text-green-500 hover:text-green-700 text-xs" title="Cấp phát firmware">🔄</button>
                        <button onclick="deleteDevice(<?= $d->id ?>)" class="text-red-500 hover:text-red-700 text-xs">🗑️</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php else: // curtains tab ?>

    <!-- Quick links to configure curtains per barn -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-4 mb-5">
        <div class="text-sm font-semibold mb-3">Chọn chuồng để cấu hình bạt:</div>
        
        <?php if (empty($barns)): ?>
        <div class="text-gray-400">Chưa có chuồng nào</div>
        <?php else: ?>
        <div class="grid grid-cols-2 gap-2">
            <?php foreach ($barns as $b): ?>
            <a href="/settings/iot/curtain/setup?barn_id=<?= $b->id ?>"
               class="block p-3 rounded-xl border border-gray-200 dark:border-gray-600 hover:border-blue-400 text-center text-sm">
                <?= htmlspecialchars($b->name) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Danh sách bạt đã cấu hình -->
    <?php if (!empty($curtains)): ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Tên bạt</th>
                    <th class="px-4 py-3 text-left font-semibold">Chuồng</th>
                    <th class="px-4 py-3 text-left font-semibold">Vị trí</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php foreach ($curtains as $c): ?>
                <tr>
                    <td class="px-4 py-3 font-semibold"><?= htmlspecialchars($c->name) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($c->barn_name ?? '—') ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $c->moving_state === 'idle' ? 'bg-gray-100 text-gray-600' : 'bg-blue-100 text-blue-600' ?>">
                            <?= $c->current_position_pct ?>% · <?= $c->moving_state ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script>
// Firmware data - loaded from PHP
const firmwareData = {};

<?php 
// Load all firmwares for JS
$fwStmt = $pdo->query("SELECT f.*, dt.name as type_name FROM device_firmwares f JOIN device_types dt ON dt.id = f.device_type_id WHERE f.is_active = 1");
while ($fw = $fwStmt->fetch(PDO::FETCH_OBJ)) {
    echo "firmwareData[{$fw->device_type_id}] = " . json_encode($fw) . ";\n";
}
?>

function viewFirmware(deviceId, mqttTopic) {
    // First get device type
    fetch('/settings/iot/device/' + deviceId + '/json')
        .then(r => r.json())
        .then(data => {
            const fw = firmwareData[data.device_type_id];
            if (!fw) {
                alert('Chưa có firmware cho loại thiết bị này!');
                return;
            }
            
            // Generate personalized firmware code
            let code = fw.code
                .replace(/YOUR_DEVICE_CODE/g, data.device_code || 'esp-device')
                .replace(/YOUR_MQTT_TOPIC/g, mqttTopic || 'cfarm/device')
                .replace(/YOUR_WIFI_SSID/g, 'YOUR_WIFI_SSID')
                .replace(/YOUR_WIFI_PASSWORD/g, 'YOUR_WIFI_PASSWORD');
            
            // Show in modal or new window
            const win = window.open('', '_blank');
            win.document.write('<pre style="background:#1a1a1a;color:#0f0;padding:20px;font-family:monospace;font-size:12px;white-space:pre-wrap;">' + code.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</pre>');
        })
        .catch(e => {
            alert('Không lấy được thông tin thiết bị');
        });
}
// Get existing device count for barn
const deviceCountByBarn = <?php 
$countStmt = $pdo->query("SELECT barn_id, COUNT(*) as cnt FROM devices WHERE barn_id IS NOT NULL GROUP BY barn_id");
$counts = [];
while ($row = $countStmt->fetch(PDO::FETCH_ASSOC)) {
    $counts[$row['barn_id']] = (int)$row['cnt'];
}
echo json_encode($counts);
?>;

function autoGenerate() {
    const barnSelect = document.getElementById('barnSelect');
    const typeSelect = document.getElementById('deviceTypeSelect');
    const notesInput = document.getElementById('notes');
    
    const barnOption = barnSelect.options[barnSelect.selectedIndex];
    const typeOption = typeSelect.options[typeSelect.selectedIndex];
    
    const barnId = barnSelect.value;
    const barnName = barnOption.dataset.name || '';
    const barnNumber = barnOption.dataset.number || '';
    const typeClass = typeOption.dataset.class || 'relay';
    const channels = parseInt(typeOption.dataset.channels) || 8;
    const notes = notesInput.value || '';
    
    if (!barnId) return;
    
    // Get device count for this barn
    const count = (deviceCountByBarn[barnId] || 0) + 1;
    
    // Generate slug from barn name
    const barnSlug = barnName.toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]/g, '');
    
    // Generate device_code: esp-barn1-relay-001
    let deviceCode = `esp-${barnSlug}-${typeClass}-${String(count).padStart(3, '0')}`;
    document.getElementById('deviceCode').value = deviceCode;
    
    // Generate name: Relay Chuồng 1
    let displayName = '';
    if (typeClass === 'relay') {
        displayName = `Relay ${barnName}`;
    } else if (typeClass === 'sensor') {
        displayName = `Sensor ${barnName}`;
    } else {
        displayName = `${typeClass.charAt(0).toUpperCase() + typeClass.slice(1)} ${barnName}`;
    }
    document.getElementById('deviceName').value = displayName;
    
    // Generate MQTT topic: cfarm/barn1
    let mqttTopic = `cfarm/barn${barnNumber}`;
    document.getElementById('mqttTopic').value = mqttTopic;
}

function deleteDevice(id) {
    if (confirm('Xóa thiết bị này? Tất cả dữ liệu liên quan sẽ bị xóa.')) {
        fetch('/settings/iot/device/' + id + '/delete', { method: 'POST' })
            .then(response => {
                window.location.reload();
            })
            .catch(err => {
                alert('Lỗi: ' + err);
            });
    }
}

function flashFirmware(deviceId, mqttTopic) {
    if (!confirm('Gửi lệnh cập nhật firmware cho thiết bị này?\n\nESP32 sẽ tải firmware mới qua OTA.\n\nĐảm bảo ESP32 đang online!')) {
        return;
    }
    
    // Get device info first
    fetch('/settings/iot/device/' + deviceId + '/json')
        .then(r => r.json())
        .then(data => {
            const fw = firmwareData[data.device_type_id];
            if (!fw) {
                alert('Chưa có firmware cho loại thiết bị này!');
                return;
            }
            
            // Send OTA command via MQTT
            const otaUrl = '/api/firmware/download/' + fw.id;
            
            // Replace placeholders in firmware
            let code = fw.code
                .replace(/YOUR_DEVICE_CODE/g, data.device_code || 'esp-device')
                .replace(/YOUR_MQTT_TOPIC/g, mqttTopic || 'cfarm/device');
            
            // Send command to device
            fetch('/settings/iot/device/' + deviceId + '/ota', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    firmware_url: window.location.origin + otaUrl,
                    version: fw.version
                })
            })
            .then(r => r.json())
            .then(result => {
                if (result.ok) {
                    alert('✅ Đã gửi lệnh OTA!\n\nESP32 sẽ tự động tải và cập nhật firmware.\n\nVersion: ' + fw.version);
                } else {
                    alert('❌ Lỗi: ' + result.message);
                }
            })
            .catch(e => {
                alert('❌ Lỗi kết nối: ' + e.message);
            });
        })
        .catch(e => {
            alert('Không lấy được thông tin thiết bị');
        });
}
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
