<?php
global $pdo;

$title = 'Firmware Library';

// Get device types
$device_types = [];
$firmwares = [];

try {
    $device_types = $pdo->query("
        SELECT dt.*, 
               (SELECT COUNT(*) FROM device_firmwares WHERE device_type_id = dt.id AND is_active = 1) as firmware_count
        FROM device_types dt
        ORDER BY dt.is_active DESC, dt.name
    ")->fetchAll(PDO::FETCH_OBJ);

    $firmwares = $pdo->query("
        SELECT f.*, dt.name as type_name
        FROM device_firmwares f
        JOIN device_types dt ON dt.id = f.device_type_id
        ORDER BY dt.name, f.is_latest DESC, f.version DESC
    ")->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    error_log("Firmware error: " . $e->getMessage());
}

ob_start();
?>

<div class="max-w-lg mx-auto">
    <div class="flex items-center gap-3 mb-4">
        <a href="/settings" class="text-gray-400 hover:text-gray-600">←</a>
        <h1 class="text-xl font-bold">📦 Firmware Library</h1>
    </div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="mb-4 p-3 bg-green-50 text-green-600 rounded-xl text-sm">✅ Đã lưu!</div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
    <div class="mb-4 p-3 bg-red-50 text-red-600 rounded-xl text-sm">
        <?php if ($_GET['error'] === 'missing_fields'): ?>
            ❌ Vui lòng điền đầy đủ thông tin
        <?php elseif ($_GET['error'] === 'type_in_use'): ?>
            ❌ Không thể xóa! Đang có thiết bị sử dụng loại này
        <?php elseif ($_GET['error'] === 'type_has_firmware'): ?>
            ❌ Không thể xóa! Đang có firmware cho loại này
        <?php else: ?>
            ❌ Lỗi
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Device Types Management -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-4 mb-5">
        <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-semibold">📋 Loại thiết bị (Device Types)</div>
            <button onclick="document.getElementById('addTypeForm').classList.toggle('hidden')"
                    class="text-blue-500 text-xs hover:underline">
                ➕ Thêm loại
            </button>
        </div>
        
        <!-- Add Device Type Form -->
        <div id="addTypeForm" class="hidden mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <form method="POST" action="/settings/iot/type/store" class="space-y-2">
                <input type="text" name="name" placeholder="Tên loại: ESP32 Relay 8CH" required
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-600 rounded-lg px-3 py-2 text-sm">
                <input type="text" name="description" placeholder="Mô tả"
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-600 rounded-lg px-3 py-2 text-sm">
                <div class="grid grid-cols-2 gap-2">
                    <select name="device_class" class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-600 rounded-lg px-3 py-2 text-sm">
                        <option value="relay">Relay</option>
                        <option value="sensor">Sensor</option>
                        <option value="mixed">Mixed</option>
                    </select>
                    <input type="number" name="total_channels" value="8" min="0" max="32"
                           class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-600 rounded-lg px-3 py-2 text-sm"
                           placeholder="Số kênh">
                </div>
                <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg text-sm font-medium">
                    Thêm loại thiết bị
                </button>
            </form>
        </div>

        <!-- Device Types List -->
        <div class="space-y-2">
            <?php foreach ($device_types as $type): ?>
            <div class="flex items-center justify-between p-3 rounded-xl border border-gray-200 dark:border-gray-600 <?= $type->is_active ? '' : 'opacity-50' ?>">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-sm">
                        <?= $type->device_class === 'relay' ? '🔌' : ($type->device_class === 'sensor' ? '📡' : '🔧') ?>
                    </div>
                    <div>
                        <div class="text-sm font-medium"><?= htmlspecialchars($type->name) ?></div>
                        <div class="text-xs text-gray-400">
                            <?= $type->total_channels ?> kênh · <?= $type->firmware_count ?? 0 ?> firmware
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="toggleType(<?= $type->id ?>)" 
                            class="text-xs px-2 py-1 rounded <?= $type->is_active ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400' ?>">
                        <?= $type->is_active ? 'Active' : 'Disabled' ?>
                    </button>
                    <button onclick="deleteType(<?= $type->id ?>)" 
                            class="text-red-500 hover:text-red-700 text-xs" title="Xóa loại thiết bị">
                        🗑️
                    </button>
                    <?php if ($type->firmware_count > 0): ?>
                    <a href="/settings/iot/firmware/<?= $type->id ?>/type" 
                       class="text-blue-500 text-xs hover:underline">Firmware</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($device_types)): ?>
            <div class="text-center text-gray-400 py-4 text-sm">
                Chưa có loại thiết bị nào
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Firmwares List -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-4">
        <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-semibold">📦 Firmware Templates</div>
            <button onclick="document.getElementById('addFirmwareForm').classList.toggle('hidden')"
                    class="text-blue-500 text-xs hover:underline">
                ➕ Thêm firmware
            </button>
        </div>

        <!-- Add Firmware Form -->
        <div id="addFirmwareForm" class="hidden mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-xl space-y-3">
            <form method="POST" action="/settings/iot/firmware/store" class="space-y-2">
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="name" placeholder="Tên firmware" required
                           class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-600 rounded-lg px-3 py-2 text-sm">
                    <input type="text" name="version" placeholder="Version: 1.0.0" required
                           class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-600 rounded-lg px-3 py-2 text-sm">
                </div>
                <select name="device_type_id" required
                        class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-600 rounded-lg px-3 py-2 text-sm">
                    <option value="">— Chọn loại thiết bị —</option>
                    <?php foreach ($device_types as $t): ?>
                    <?php if ($t->is_active): ?>
                    <option value="<?= $t->id ?>"><?= htmlspecialchars($t->name) ?></option>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <textarea name="description" placeholder="Mô tả..." rows="2"
                          class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-600 rounded-lg px-3 py-2 text-sm"></textarea>
                <label class="flex items-center gap-2 text-xs">
                    <input type="checkbox" name="is_latest" value="1" checked class="rounded">
                    <span>Đây là phiên bản mới nhất</span>
                </label>
                <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg text-sm font-medium">
                    Thêm firmware
                </button>
            </form>
        </div>

        <!-- Firmwares Table -->
        <?php if (empty($firmwares)): ?>
        <div class="text-center text-gray-400 py-8">
            <div class="text-3xl mb-2">📦</div>
            <div class="text-sm">Chưa có firmware nào</div>
            <div class="text-xs mt-1">Thêm loại thiết bị và firmware</div>
        </div>
        <?php else: ?>
        <div class="space-y-2">
            <?php 
            $current_type = '';
            foreach ($firmwares as $fw): 
                if ($current_type !== $fw->type_name):
                    if ($current_type !== '') echo '</div>';
                    $current_type = $fw->type_name;
            ?>
            <div class="text-xs font-semibold text-gray-400 mt-3 mb-1"><?= htmlspecialchars($fw->type_name) ?></div>
            <?php endif; ?>
            
            <div class="flex items-center justify-between p-3 rounded-xl border <?= $fw->is_active ? 'border-gray-200 dark:border-gray-600' : 'border-red-200 dark:border-red-800 opacity-60' ?>">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg <?= $fw->is_latest ? 'bg-green-100 dark:bg-green-900/30' : 'bg-gray-100 dark:bg-gray-700' ?> flex items-center justify-center text-sm">
                        <?= $fw->is_latest ? '⭐' : '📄' ?>
                    </div>
                    <div>
                        <div class="text-sm font-medium"><?= htmlspecialchars($fw->name) ?></div>
                        <div class="text-xs text-gray-400">v<?= htmlspecialchars($fw->version) ?></div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($fw->is_latest): ?>
                    <span class="text-xs bg-green-100 dark:bg-green-900/30 text-green-600 px-2 py-1 rounded">Latest</span>
                    <?php endif; ?>
                    <?php if (!$fw->is_active): ?>
                    <span class="text-xs bg-red-100 dark:bg-red-900/30 text-red-600 px-2 py-1 rounded">Disabled</span>
                    <?php endif; ?>
                    <a href="/settings/iot/firmware/<?= $fw->id ?>/edit" class="text-blue-500 text-xs hover:underline">✏️</a>
                    <button onclick="deleteFirmware(<?= $fw->id ?>)" class="text-red-500 text-xs hover:underline">🗑️</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleType(id) {
    fetch('/settings/iot/type/' + id + '/toggle', { method: 'POST' })
        .then(() => window.location.reload());
}

function deleteType(id) {
    if (confirm('Xóa loại thiết bị này? (Chỉ xóa được khi không có thiết bị hoặc firmware nào)')) {
        fetch('/settings/iot/type/' + id + '/delete', { method: 'POST' })
            .then(() => window.location.reload());
    }
}

function deleteFirmware(id) {
    if (confirm('Xóa firmware này? Thiết bị sẽ không thể OTA.')) {
        fetch('/settings/iot/firmware/' + id + '/delete', { method: 'POST' })
            .then(() => window.location.reload());
    }
}
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
