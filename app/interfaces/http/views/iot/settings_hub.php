<?php
$title = 'IoT Settings';
ob_start();
?>

<div class="mb-4">
    <div class="font-bold text-lg">⚙️ IoT Settings</div>
    <div class="text-xs text-gray-400">Quản lý thiết bị và cấu hình hệ thống</div>
</div>

<div class="space-y-3">

    <a href="/iot/nodes/create"
       class="flex items-center gap-4 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 hover:border-blue-300 transition-colors">
        <div class="w-12 h-12 bg-blue-50 dark:bg-blue-900/30 rounded-xl flex items-center justify-center text-2xl">📡</div>
        <div class="flex-1">
            <div class="font-semibold text-sm">Thêm node IoT mới</div>
            <div class="text-xs text-gray-400 mt-0.5">ESP8266/ESP32 sensor, relay — tạo & lấy firmware</div>
        </div>
        <span class="text-gray-300 text-lg">›</span>
    </a>

    <a href="/iot/curtains/setup"
       class="flex items-center gap-4 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 hover:border-blue-300 transition-colors">
        <div class="w-12 h-12 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center text-2xl">🪟</div>
        <div class="flex-1">
            <div class="font-semibold text-sm">Cài đặt bộ bạt</div>
            <div class="text-xs text-gray-400 mt-0.5">Thêm/sửa 4 bạt tự động từ relay 8 kênh</div>
        </div>
        <span class="text-gray-300 text-lg">›</span>
    </a>

    <a href="/iot/devices"
       class="flex items-center gap-4 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 hover:border-blue-300 transition-colors">
        <div class="w-12 h-12 bg-green-50 dark:bg-green-900/30 rounded-xl flex items-center justify-center text-2xl">🎛️</div>
        <div class="flex-1">
            <div class="font-semibold text-sm">Dashboard thiết bị</div>
            <div class="text-xs text-gray-400 mt-0.5">Xem trạng thái realtime tất cả node</div>
        </div>
        <span class="text-gray-300 text-lg">›</span>
    </a>

    <a href="/iot/control"
       class="flex items-center gap-4 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 hover:border-blue-300 transition-colors">
        <div class="w-12 h-12 bg-purple-50 dark:bg-purple-900/30 rounded-xl flex items-center justify-center text-2xl">🕹️</div>
        <div class="flex-1">
            <div class="font-semibold text-sm">Điều khiển bạt</div>
            <div class="text-xs text-gray-400 mt-0.5">Mở/đóng bạt thủ công từng chuồng</div>
        </div>
        <span class="text-gray-300 text-lg">›</span>
    </a>

    <a href="/settings/iot/types"
       class="flex items-center gap-4 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 hover:border-blue-300 transition-colors">
        <div class="w-12 h-12 bg-orange-50 dark:bg-orange-900/30 rounded-xl flex items-center justify-center text-2xl">🔧</div>
        <div class="flex-1">
            <div class="font-semibold text-sm">Device Types & Firmware</div>
            <div class="text-xs text-gray-400 mt-0.5">Quản lý loại thiết bị và template firmware</div>
        </div>
        <span class="text-gray-300 text-lg">›</span>
    </a>

    <a href="/env"
       class="flex items-center gap-4 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 hover:border-blue-300 transition-colors">
        <div class="w-12 h-12 bg-teal-50 dark:bg-teal-900/30 rounded-xl flex items-center justify-center text-2xl">🌡️</div>
        <div class="flex-1">
            <div class="font-semibold text-sm">Dashboard môi trường</div>
            <div class="text-xs text-gray-400 mt-0.5">Nhiệt độ, độ ẩm, NH3, CO2 theo chuồng</div>
        </div>
        <span class="text-gray-300 text-lg">›</span>
    </a>

    <a href="/export"
       class="flex items-center gap-4 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 hover:border-blue-300 transition-colors">
        <div class="w-12 h-12 bg-purple-50 dark:bg-purple-900/30 rounded-xl flex items-center justify-center text-2xl">🤖</div>
        <div class="flex-1">
            <div class="font-semibold text-sm">Export AI Training Data</div>
            <div class="text-xs text-gray-400 mt-0.5">CSV · JSON · JSONL cho Google Colab</div>
        </div>
        <span class="text-gray-300 text-lg">›</span>
    </a>

    <a href="/settings/iot/help"
       class="flex items-center gap-4 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 hover:border-blue-300 transition-colors">
        <div class="w-12 h-12 bg-gray-50 dark:bg-gray-700 rounded-xl flex items-center justify-center text-2xl">❓</div>
        <div class="flex-1">
            <div class="font-semibold text-sm">Hướng dẫn sử dụng IoT</div>
            <div class="text-xs text-gray-400 mt-0.5">Cách thêm thiết bị, cấu hình và sử dụng</div>
        </div>
        <span class="text-gray-300 text-lg">›</span>
    </a>

</div>

<?php $content = ob_get_clean(); require view_path('layouts/main.php'); ?>
