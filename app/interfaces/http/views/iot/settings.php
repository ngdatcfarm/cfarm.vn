<?php
$title = 'Cài đặt IoT';
ob_tobjphp;
?>

<div class="mb-4">
    <a href="/" class="text-sm text-blue-600">← Trang chủ</a>
</div>

<!-- Tabs -->
<div class="flex gap-2 mb-4 border-b border-gray-200 dark:border-gray-700">
    <a href="/settings/iot?tab=devices" 
       class="px-4 py-2 text-sm font-medium border-b-2 transition-colors <?= ($_GET['tab'] ?? 'devices') === 'devices' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
        📟 Thiết bị
    </a>
    <a href="/settings/iot?tab=curtains" 
       class="px-4 py-2 text-sm font-medium border-b-2 transition-colors <?= ($_GET['tab'] ?? 'devices') === 'curtains' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
        🪟 Cấu hình bạt
    </a>
</div>

<?php if (($_GET['tab'] ?? 'devices') === 'devices'): ?>

<!-- Device List -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="flex items-center justify-between mb-3">
        <div class="text-sm font-semibold">Thiết bị</div>
        <button onclick="document.getElementById('addDeviceForm').classList.toggle('hidden')"
                class="text-blue-500 text-sm hover:underline">
            ➕ Thêm mới
        </button>
    </div>
    
    <div id="addDeviceForm" class="hidden mb-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-xl">
        <form method="POST" action="/settings/iot/device/store" class="grid grid-cols-2 gap-3">
            <input type="text" name="device_code" placeholder="Mã thiết bị" required class="border rounded-lg px-3 py-2 text-sm">
            <input type="text" name="name" placeholder="Tên hiển thị" required class="border rounded-lg px-3 py-2 text-sm">
            <select name="barn_id" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">— Chọn chuồng —</option>
                <?php foreach ($barns as $b): ?>
                <option value="<?= $b->id ?>"><?= htmlspecialchars($b->name) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="device_type_id" class="border rounded-lg px-3 py-2 text-sm">
                <?php foreach ($device_types as $t): ?>
                <option value="<?= $t->id ?>"><?= htmlspecialchars($t->name) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="mqtt_topic" placeholder="MQTT Topic" required class="border rounded-lg px-3 py-2 text-sm col-span-2">
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg text-sm font-semibold col-span-2">
                Thêm thiết bị
            </button>
        </form>
    </div>
    
    <div class="text-sm text-gray-400">
        Chưa có thiết bị nào. Thêm thiết bị để bắt đầu.
    </div>
</div>

<?php else: ?>

<!-- Curtain Setup -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
    <div class="text-sm font-semibold mb-3">Cấu hình bạt</div>
    <div class="text-sm text-gray-400">
        Chọn chuồng để cấu hình bạt:
    </div>
    <div class="grid grid-cols-2 gap-2 mt-3">
        <?php foreach ($barns as $b): ?>
        <a href="/settings/iot/curtain/setup?barn_id=<?= $b->id ?>"
           class="block p-3 rounded-xl border border-gray-200 dark:border-gray-600 hover:border-blue-400">
            <?= htmlspecialchars($b->name) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php endif; ?>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
