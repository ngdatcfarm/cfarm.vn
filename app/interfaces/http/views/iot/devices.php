<?php
$title = 'Quản lý Thiết bị IoT';
ob_start();
?>

<div class="mb-4 flex items-center gap-2">
    <a href="/settings" class="text-sm text-blue-600">← Settings</a>
    <span class="text-gray-300">/</span>
    <span class="text-sm font-semibold">Thiết bị IoT</span>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="mb-3 p-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-600">✅ Đã lưu!</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="mb-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-600">
    <?php echo $_GET['error'] === 'missing_fields' ? '❌ Vui lòng điền đầy đủ thông tin' : '❌ Lỗi'; ?>
</div>
<?php endif; ?>

<!-- Form thêm thiết bị -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">➕ Thêm thiết bị mới</div>
    
    <form method="POST" action="/settings/iot/device/store" class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <div>
            <label class="text-xs text-gray-500 block mb-1">Mã thiết bị</label>
            <input type="text" name="device_code" placeholder="esp-barn1-relay-001" required
                   class="w-full border rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Tên hiển thị</label>
            <input type="text" name="name" placeholder="Relay chuồng 1" required
                   class="w-full border rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Chuồng</label>
            <select name="barn_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">— Chọn chuồng —</option>
                <?php foreach ($barns as $b): ?>
                <option value="<?= $b->id ?>"><?= htmlspecialchars($b->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Loại</label>
            <select name="device_type_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                <?php foreach ($device_types as $t): ?>
                <option value="<?= $t->id ?>"><?= htmlspecialchars($t->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">MQTT Topic</label>
            <input type="text" name="mqtt_topic" placeholder="cfarm/barn1" required
                   class="w-full border rounded-lg px-3 py-2 text-sm">
        </div>
        <div class="md:col-span-5">
            <label class="text-xs text-gray-500 block mb-1">Ghi chú</label>
            <input type="text" name="notes" placeholder="Ghi chú thêm..."
                   class="w-full border rounded-lg px-3 py-2 text-sm">
        </div>
        <div class="md:col-span-5">
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                ➕ Thêm thiết bị
            </button>
        </div>
    </form>
</div>

<!-- Danh sách thiết bị -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th class="px-4 py-3 text-left font-semibold">Thiết bị</th>
                <th class="px-4 py-3 text-left font-semibold">Chuồng</th>
                <th class="px-4 py-3 text-left font-semibold">Loại</th>
                <th class="px-4 py-3 text-left font-semibold">MQTT Topic</th>
                <th class="px-4 py-3 text-left font-semibold">Trạng thái</th>
                <th class="px-4 py-3 text-left font-semibold">Thao tác</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <?php if (empty($devices)): ?>
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                    Chưa có thiết bị nào
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($devices as $d): ?>
            <tr>
                <td class="px-4 py-3">
                    <div class="font-semibold"><?= htmlspecialchars($d->name) ?></div>
                    <div class="text-xs text-gray-400"><?= htmlspecialchars($d->device_code) ?></div>
                </td>
                <td class="px-4 py-3"><?= htmlspecialchars($d->barn_name ?? '—') ?></td>
                <td class="px-4 py-3"><?= htmlspecialchars($d->type_name) ?></td>
                <td class="px-4 py-3 font-mono text-xs"><?= htmlspecialchars($d->mqtt_topic) ?></td>
                <td class="px-4 py-3">
                    <?php if ($d->is_online): ?>
                    <span class="inline-flex items-center gap-1 text-green-600">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span> Online
                    </span>
                    <?php else: ?>
                    <span class="inline-flex items-center gap-1 text-gray-400">
                        <span class="w-2 h-2 bg-gray-300 rounded-full"></span> Offline
                    </span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3">
                    <div class="flex gap-2">
                        <button onclick="editDevice(<?= $d->id ?>)" class="text-blue-500 hover:underline text-xs">Sửa</button>
                        <button onclick="testDevice(<?= $d->id ?>)" class="text-green-500 hover:underline text-xs">Test</button>
                        <button onclick="deleteDevice(<?= $d->id ?>)" class="text-red-500 hover:underline text-xs">Xóa</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function editDevice(id) {
    window.location.href = '/settings/iot/device/' + id + '/edit';
}

function testDevice(id) {
    if (confirm('Gửi lệnh test đến thiết bị?')) {
        fetch('/settings/iot/device/' + id + '/test', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                alert(data.ok ? '✅ Đã gửi lệnh!' : '❌ Thất bại');
            });
    }
}

function deleteDevice(id) {
    if (confirm('Xóa thiết bị này?')) {
        fetch('/settings/iot/device/' + id + '/delete', { method: 'POST' })
            .then(() => window.location.reload());
    }
}
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
