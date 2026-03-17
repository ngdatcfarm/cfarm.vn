<?php
global $pdo;

$title = 'Cấu hình Bạt';

// Lấy relay devices
$relay_stmt = $pdo->query("
    SELECT d.*, b.name as barn_name, dt.name as type_name
    FROM devices d
    JOIN device_types dt ON dt.id = d.device_type_id
    WHERE dt.device_class = 'relay'
    ORDER BY b.name, d.name
");
$relay_devices = [];
while ($row = $relay_stmt->fetch(PDO::FETCH_OBJ)) {
    $relay_devices[$row->barn_id][] = $row;
}

// Lấy channels cho mỗi device
$device_channels = [];
if (!empty($relay_devices)) {
    $device_ids = array_merge(...array_map(fn($arr) => array_column($arr, 'id'), $relay_devices));
    if (!empty($device_ids)) {
        $placeholders = implode(',', array_fill(0, count($device_ids), '?'));
        $ch_stmt = $pdo->prepare("SELECT * FROM device_channels WHERE device_id IN ($placeholders) ORDER BY device_id, channel_number");
        $ch_stmt->execute($device_ids);
        while ($ch = $ch_stmt->fetch(PDO::FETCH_OBJ)) {
            $device_channels[$ch->device_id][] = $ch;
        }
    }
}

ob_start();
?>

<div class="mb-4 flex items-center gap-2">
    <a href="/settings/iot?tab=curtains" class="text-sm text-blue-600">← Quay lại</a>
</div>

<?php if ($saved): ?>
<div class="mb-3 p-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-600">✅ Đã lưu cấu hình bạt!</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="mb-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-600">❌ Vui lòng điền đầy đủ thông tin</div>
<?php endif; ?>

<?php if (!$barn_id): ?>
<!-- Chọn barn -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
    <div class="text-sm font-semibold mb-3">Chọn chuồng để cấu hình bạt:</div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
        <?php foreach ($barns_list as $b): ?>
        <a href="/settings/iot/curtain/setup?barn_id=<?= $b->id ?>"
           class="block p-3 rounded-xl border border-gray-200 dark:border-gray-600 hover:border-blue-400 text-center">
            <?= htmlspecialchars($b->name) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php else: // Đã chọn barn ?>

<div class="mb-4">
    <div class="text-lg font-semibold">Cấu hình bạt - <?= htmlspecialchars($selected_barn->name ?? '') ?></div>
</div>

<!-- Form thêm bạt mới -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">➕ Thêm bạt mới</div>
    
    <?php 
    $barn_relays = $relay_devices[$barn_id] ?? [];
    if (empty($barn_relays)): 
    ?>
    <div class="text-red-500 p-4 bg-red-50 rounded-xl">
        Chưa có thiết bị relay nào cho chuồng này. 
        <a href="/settings/iot?tab=devices" class="underline">Thêm thiết bị relay</a> trước.
    </div>
    <?php else: ?>
    
    <form method="POST" action="/settings/iot/curtain/store" class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="hidden" name="barn_id" value="<?= $barn_id ?>">
        
        <div>
            <label class="text-xs text-gray-500 block mb-1">Thiết bị Relay</label>
            <select name="device_id" id="deviceSelect" required class="w-full border rounded-lg px-3 py-2 text-sm"
                    onchange="loadChannels(this.value)">
                <option value="">— Chọn thiết bị —</option>
                <?php foreach ($barn_relays as $dev): ?>
                <option value="<?= $dev->id ?>"><?= htmlspecialchars($dev->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label class="text-xs text-gray-500 block mb-1">Tên bạt</label>
            <input type="text" name="curtain_name" placeholder="Bạt 1" required class="w-full border rounded-lg px-3 py-2 text-sm">
        </div>
        
        <div>
            <label class="text-xs text-gray-500 block mb-1">Kênh LÊN (UP)</label>
            <select name="up_channel_id" id="upChannel" required class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">— Chọn kênh —</option>
            </select>
        </div>
        
        <div>
            <label class="text-xs text-gray-500 block mb-1">Kênh XUỐNG (DOWN)</label>
            <select name="down_channel_id" id="downChannel" required class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">— Chọn kênh —</option>
            </select>
        </div>
        
        <div>
            <label class="text-xs text-gray-500 block mb-1">Thời gian lên hoàn toàn (giây)</label>
            <input type="number" name="up_seconds" value="30" min="5" max="300" class="w-full border rounded-lg px-3 py-2 text-sm">
        </div>
        
        <div>
            <label class="text-xs text-gray-500 block mb-1">Thời gian xuống hoàn toàn (giây)</label>
            <input type="number" name="down_seconds" value="30" min="5" max="300" class="w-full border rounded-lg px-3 py-2 text-sm">
        </div>
        
        <div class="md:col-span-2">
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                ➕ Thêm bạt
            </button>
        </div>
    </form>
    <?php endif; ?>
</div>

<!-- Danh sách bạt đã cấu hình -->
<?php if (!empty($curtains)): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th class="px-4 py-3 text-left font-semibold">Tên bạt</th>
                <th class="px-4 py-3 text-left font-semibold">Kênh UP</th>
                <th class="px-4 py-3 text-left font-semibold">Kênh DOWN</th>
                <th class="px-4 py-3 text-left font-semibold">Thời gian</th>
                <th class="px-4 py-3 text-left font-semibold">Vị trí</th>
                <th class="px-4 py-3 text-left font-semibold">Thao tác</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <?php foreach ($curtains as $c): ?>
            <tr>
                <td class="px-4 py-3 font-semibold"><?= htmlspecialchars($c->name) ?></td>
                <td class="px-4 py-3">CH<?= $c->up_ch ?? '—' ?></td>
                <td class="px-4 py-3">CH<?= $c->down_ch ?? '—' ?></td>
                <td class="px-4 py-3"><?= $c->full_up_seconds ?>/<?= $c->full_down_seconds ?>s</td>
                <td class="px-4 py-3"><?= $c->current_position_pct ?>%</td>
                <td class="px-4 py-3">
                    <button onclick="deleteCurtain(<?= $c->id ?>)" class="text-red-500 hover:underline text-xs">Xóa</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
// Data for channels
const deviceChannels = <?= json_encode($device_channels) ?>;

function loadChannels(deviceId) {
    const upSelect = document.getElementById('upChannel');
    const downSelect = document.getElementById('downChannel');
    
    upSelect.innerHTML = '<option value="">— Chọn kênh —</option>';
    downSelect.innerHTML = '<option value="">— Chọn kênh —</option>';
    
    if (!deviceId || !deviceChannels[deviceId]) return;
    
    const channels = deviceChannels[deviceId];
    channels.forEach(ch => {
        const opt1 = new Option('CH' + ch.channel_number + ' - ' + ch.name, ch.id);
        const opt2 = new Option('CH' + ch.channel_number + ' - ' + ch.name, ch.id);
        upSelect.add(opt1);
        downSelect.add(opt2);
    });
}

function deleteCurtain(id) {
    if (confirm('Xóa bạt này?')) {
        fetch('/settings/iot/curtain/' + id + '/delete', { method: 'POST' })
            .then(() => window.location.reload());
    }
}
</script>

<?php endif; ?>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
