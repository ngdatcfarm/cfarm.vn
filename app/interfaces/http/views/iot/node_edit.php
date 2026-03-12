<?php
$title = 'Sửa node — ' . e($device->device_code);
ob_start();
?>

<div class="mb-4 flex items-center gap-2">
    <a href="/iot/devices" class="text-sm text-blue-600">← Danh sách thiết bị</a>
    <span class="text-gray-300">/</span>
    <span class="text-sm font-semibold truncate"><?= e($device->device_code) ?></span>
</div>

<?php if ($saved): ?>
<div class="mb-3 p-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-600">✅ Đã lưu thay đổi</div>
<?php elseif ($error === 'missing_fields'): ?>
<div class="mb-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-600">❌ Vui lòng điền đầy đủ tên và mã thiết bị</div>
<?php elseif ($error === 'duplicate_code'): ?>
<div class="mb-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-600">❌ Mã thiết bị đã tồn tại</div>
<?php elseif ($error === 'has_curtains'): ?>
<div class="mb-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-600">❌ Không thể xóa — thiết bị đang điều khiển bạt. Xóa bạt trước tại <a href="/iot/curtains/setup" class="underline">Cài đặt bạt</a></div>
<?php endif; ?>

<!-- Quick actions -->
<div class="flex gap-2 mb-4">
    <a href="/settings/iot/firmware/<?= $device->id ?>"
       class="flex-1 text-center text-sm font-semibold py-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600">
        💾 Firmware
    </a>
    <?php if (($device->device_class ?? '') !== 'relay'): ?>
    <a href="/iot/sensor/<?= $device->id ?>"
       class="flex-1 text-center text-sm font-semibold py-2.5 rounded-xl bg-teal-50 dark:bg-teal-900/20 text-teal-600">
        📊 Sensor data
    </a>
    <?php else: ?>
    <a href="/iot/control/<?= $device->barn_id ?? '' ?>"
       class="flex-1 text-center text-sm font-semibold py-2.5 rounded-xl bg-purple-50 dark:bg-purple-900/20 text-purple-600">
        🕹️ Điều khiển
    </a>
    <?php endif; ?>
</div>

<form method="POST" action="/iot/nodes/<?= $device->id ?>/update">

<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-3">
    <div class="text-sm font-semibold mb-3">① Thông tin cơ bản</div>
    <div class="space-y-3">

        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">Tên thiết bị *</label>
            <input type="text" name="name" required
                   value="<?= e($device->name) ?>"
                   class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
        </div>

        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">Mã thiết bị *</label>
            <input type="text" name="device_code" required
                   value="<?= e($device->device_code) ?>"
                   class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm font-mono bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
            <div class="text-xs text-orange-500 mt-1">⚠️ Đổi mã sẽ cần flash lại firmware!</div>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">Loại thiết bị</label>
            <select name="device_type_id"
                    class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-700">
                <option value="">— Chọn loại —</option>
                <?php foreach ($device_types as $dt): ?>
                <option value="<?= $dt->id ?>" <?= $device->device_type_id == $dt->id ? 'selected' : '' ?>>
                    <?= e($dt->name) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">Chip</label>
            <input type="hidden" name="chip_type" id="chipTypeHidden" value="<?= e($device->chip_type ?? 'esp32') ?>">
            <div class="flex gap-2">
                <button type="button" id="btnEsp8266" onclick="setChip('esp8266')"
                        class="flex-1 py-2 text-sm font-semibold rounded-xl border-2 transition-colors
                               <?= ($device->chip_type ?? '') === 'esp8266' ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/20 text-blue-600' : 'border-gray-200 dark:border-gray-600 text-gray-400' ?>">
                    ESP8266
                </button>
                <button type="button" id="btnEsp32" onclick="setChip('esp32')"
                        class="flex-1 py-2 text-sm font-semibold rounded-xl border-2 transition-colors
                               <?= ($device->chip_type ?? 'esp32') !== 'esp8266' ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/20 text-blue-600' : 'border-gray-200 dark:border-gray-600 text-gray-400' ?>">
                    ESP32
                </button>
            </div>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">Gắn vào chuồng</label>
            <select name="barn_id"
                    class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-700">
                <option value="">— Chưa gán —</option>
                <?php foreach ($barns as $b): ?>
                <option value="<?= $b->id ?>" <?= $device->barn_id == $b->id ? 'selected' : '' ?>>
                    <?= e($b->name) ?><?= $b->active_cycle ? ' · ' . $b->active_cycle : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">Vị trí trong chuồng</label>
            <input type="text" name="location_note"
                   value="<?= e($device->location_note ?? '') ?>"
                   placeholder="vd: Đầu chuồng, cách mặt đất 1.5m"
                   class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
        </div>

    </div>
</div>

<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-3">
    <div class="text-sm font-semibold mb-3">② Cấu hình nâng cao</div>
    <div class="space-y-3">

        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">Tần suất gửi ENV</label>
            <select name="env_interval_seconds"
                    class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-700">
                <?php foreach ([30=>'30 giây — Realtime',60=>'1 phút',120=>'2 phút',300=>'5 phút — Khuyến nghị',600=>'10 phút',900=>'15 phút — Tiết kiệm'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= ($device->env_interval_seconds??300)==$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">MQTT Topic</label>
            <div class="font-mono text-xs bg-gray-50 dark:bg-gray-700/50 rounded-xl px-3 py-2 text-gray-500">
                <?= e($device->mqtt_topic ?? 'cfarm/barn?') ?>
            </div>
            <div class="text-xs text-gray-400 mt-1">Tự động cập nhật khi đổi chuồng</div>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">Ghi chú</label>
            <textarea name="notes" rows="2"
                      class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-700 resize-none focus:outline-none focus:ring-2 focus:ring-blue-400"><?= e($device->notes ?? '') ?></textarea>
        </div>

    </div>
</div>

<!-- Thống kê -->
<div class="grid grid-cols-3 gap-2 mb-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-3 text-center">
        <div class="text-xs text-gray-400">Trạng thái</div>
        <div class="font-semibold text-sm mt-1 <?= $device->is_online ? 'text-green-500' : 'text-red-400' ?>">
            <?= $device->is_online ? '● Online' : '○ Offline' ?>
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-3 text-center">
        <div class="text-xs text-gray-400">Kênh</div>
        <div class="font-semibold text-sm mt-1"><?= $device->total_channels ?? 0 ?></div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-3 text-center">
        <div class="text-xs text-gray-400">ID</div>
        <div class="font-semibold text-sm mt-1 font-mono">#<?= $device->id ?></div>
    </div>
</div>

<button type="submit"
        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3.5 rounded-2xl text-sm mb-3 transition-colors">
    💾 Lưu thay đổi
</button>

</form>

<form method="POST" action="/iot/nodes/<?= $device->id ?>/delete"
      onsubmit="return confirm('Xóa thiết bị <?= e($device->device_code) ?>?\nDữ liệu sensor readings sẽ bị xóa theo!')">
    <button type="submit"
            class="w-full py-3 rounded-2xl text-sm font-semibold text-red-500 border border-red-200 dark:border-red-800">
        🗑️ Xóa thiết bị
    </button>
</form>

<script>
function setChip(chip) {
    document.getElementById('chipTypeHidden').value = chip;
    const active   = 'flex-1 py-2 text-sm font-semibold rounded-xl border-2 border-blue-400 bg-blue-50 dark:bg-blue-900/20 text-blue-600 transition-colors';
    const inactive = 'flex-1 py-2 text-sm font-semibold rounded-xl border-2 border-gray-200 dark:border-gray-600 text-gray-400 transition-colors';
    document.getElementById('btnEsp8266').className = chip === 'esp8266' ? active : inactive;
    document.getElementById('btnEsp32').className   = chip === 'esp32'   ? active : inactive;
}
</script>

<?php $content = ob_get_clean(); require view_path('layouts/main.php'); ?>
