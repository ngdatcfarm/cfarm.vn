<?php
$title = 'Hướng dẫn sử dụng IoT';
ob_start();
?>

<div class="mb-4">
    <a href="/settings/iot" class="text-sm text-blue-600 hover:underline">← IoT Settings</a>
</div>

<!-- Header -->
<div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl p-4 mb-4">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-2xl">📡</div>
        <div>
            <div class="text-lg font-bold text-white">Hướng dẫn sử dụng IoT</div>
            <div class="text-sm text-white/70">Quản lý ESP32, cảm biến và điều khiển bạt</div>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="grid grid-cols-3 gap-3 mb-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-3 text-center">
        <div class="text-2xl font-bold text-blue-600"><?= $stats['devices'] ?></div>
        <div class="text-xs text-gray-400">Thiết bị</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-3 text-center">
        <div class="text-2xl font-bold text-orange-600"><?= $stats['device_types'] ?></div>
        <div class="text-xs text-gray-400">Loại thiết bị</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-3 text-center">
        <div class="text-2xl font-bold text-green-600"><?= $stats['curtains'] ?></div>
        <div class="text-xs text-gray-400">Bộ bạt</div>
    </div>
</div>

<!-- Table of Contents -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">📑 Mục lục</div>
    <div class="text-xs space-y-1.5">
        <div><a href="#overview" class="text-blue-500 hover:underline">1. Tổng quan hệ thống IoT</a></div>
        <div><a href="#add-device" class="text-blue-500 hover:underline">2. Thêm thiết bị mới</a></div>
        <div><a href="#device-types" class="text-blue-500 hover:underline">3. Device Types & Firmware</a></div>
        <div><a href="#curtain" class="text-blue-500 hover:underline">4. Cài đặt bộ bạt</a></div>
        <div><a href="#control" class="text-blue-500 hover:underline">5. Điều khiển</a></div>
        <div><a href="#data-flow" class="text-blue-500 hover:underline">6. Dòng dữ liệu</a></div>
        <div><a href="#mqtt" class="text-blue-500 hover:underline">7. MQTT Protocol</a></div>
        <div><a href="#ota" class="text-blue-500 hover:underline">8. Cập nhật Firmware (OTA)</a></div>
    </div>
</div>

<!-- 1. Overview -->
<div id="overview" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">1️⃣ Tổng quan hệ thống IoT</div>
    <div class="text-xs text-gray-600 dark:text-gray-300 space-y-2">
        <p>Hệ thống IoT của cfarm.vn bao gồm:</p>
        <ul class="list-disc list-inside space-y-1 ml-2">
            <li><strong>ESP32/ESP8266</strong> - Vi điều khiển chạy firmware kết nối WiFi</li>
            <li><strong>Cảm biến</strong> - Nhiệt độ, độ ẩm, NH3, CO2...</li>
            <li><strong>Relay 8 kênh</strong> - Điều khiển bạt, quạt, đèn...</li>
            <li><strong>MQTT Broker</strong> - Trung gian truyền dữ liệu</li>
            <li><strong>Server</strong> - Nhận dữ liệu, lưu database, hiển thị dashboard</li>
        </ul>
    </div>
</div>

<!-- 2. Add Device -->
<div id="add-device" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">2️⃣ Thêm thiết bị mới</div>
    <div class="text-xs text-gray-600 dark:text-gray-300 space-y-3">
        <p><strong>Bước 1:</strong> Vào <a href="/settings/iot" class="text-blue-500 hover:underline">IoT Settings</a> → <strong>Thêm node IoT mới</a></p>

        <p><strong>Bước 2:</strong> Điền thông tin:</p>
        <ul class="list-disc list-inside space-y-1 ml-2">
            <li><code>Tên thiết bị</code> - Tên hiển thị (VD: "Cảm biến chuồng 1")</li>
            <li><code>Mã thiết bị</code> - ID duy nhất (VD: "ESP001") - <span class="text-red-500">quan trọng!</span></li>
            <li><code>Loại thiết bị</code> - Chọn loại đã tạo ở Device Types</li>
            <li><code>Chuồng</code> - Gán thiết bị vào chuồng (tùy chọn)</li>
            <li><code>Chip</code> - ESP8266 hoặc ESP32</li>
        </ul>

        <p><strong>Bước 3:</strong> Nhấn <strong>Lưu</strong> → Hệ thống sẽ:</p>
        <ul class="list-disc list-inside space-y-1 ml-2">
            <li>Tạo bản ghi trong database <code>devices</code></li>
            <li>Tự động tạo các kênh (channels) theo loại thiết bị</li>
            <li>Hiển thị trang Firmware với code Arduino có sẵn</li>
        </ul>

        <p><strong>Bước 4:</strong> Copy code Arduino → Nạp vào ESP32 qua USB</p>

        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-2 mt-2">
            <div class="text-yellow-700 dark:text-yellow-300 font-semibold">⚠️ Lưu ý quan trọng</div>
            <div class="text-xs">Mỗi thiết bị cần có <code>device_code</code> duy nhất. Đây là ID để ESP32 gửi dữ liệu về server.</div>
        </div>
    </div>
</div>

<!-- 3. Device Types -->
<div id="device-types" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">3️⃣ Device Types & Firmware</div>
    <div class="text-xs text-gray-600 dark:text-gray-300 space-y-3">
        <p><strong>Device Types</strong> định nghĩa các loại thiết bị khác nhau:</p>
        <ul class="list-disc list-inside space-y-1 ml-2">
            <li><strong>Relay 8 kênh</strong> - Điều khiển bạt, quạt</li>
            <li><strong>Sensor DHT22</strong> - Đo nhiệt độ, độ ẩm</li>
            <li><strong>Mixed</strong> - Kết hợp cả sensor và relay</li>
        </ul>

        <p><strong>Các trường trong Device Type:</strong></p>
        <ul class="list-disc list-inside space-y-1 ml-2">
            <li><code>Tên</code> - Tên loại thiết bị</li>
            <li><code>Class</code> - relay / sensor / mixed</li>
            <li><code>Số kênh</code> - Số relay (1-8)</li>
            <li><code>Firmware Template</code> - Code Arduino mẫu (có thể tùy chỉnh)</li>
            <li><code>Firmware Version</code> - Phiên bản firmware hiện tại</li>
            <li><code>Base Firmware</code> - Template tái sử dụng cho nhiều thiết bị</li>
        </ul>

        <p><strong>Firmware Library:</strong> <a href="/settings/iot/firmwares" class="text-blue-500 hover:underline">/settings/iot/firmwares</a></p>
        <ul class="list-disc list-inside space-y-1 ml-2">
            <li>Upload file .bin để lưu trữ firmware</li>
            <li>Quản lý các phiên bản</li>
            <li>ESP32 có thể tự động cập nhật (OTA)</li>
        </ul>
    </div>
</div>

<!-- 4. Curtain Setup -->
<div id="curtain" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">4️⃣ Cài đặt bộ bạt</div>
    <div class="text-xs text-gray-600 dark:text-gray-300 space-y-3">
        <p><strong>Bạt</strong> = 2 kênh relay (lên/xuống) được ghép cặp.</p>

        <p><strong>Cách thêm bạt mới:</strong></p>
        <ol class="list-decimal list-inside space-y-1 ml-2">
            <li>Vào <a href="/iot/curtains/setup" class="text-blue-500 hover:underline">Cài đặt bộ bạt</a></li>
            <li>Chọn chuồng</li>
            <li>Đặt tên bạt (VD: "Bạt trước")</li>
            <li>Chọn kênh LÊN và kênh XUỐNG từ relay</li>
            <li>Thời gian đầy đủ lên/xuống (giây)</li>
        </ol>

        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-2 mt-2">
            <div class="text-blue-700 dark:text-blue-300 font-semibold">💡 Mẹo</div>
            <div class="text-xs">Interlock: Hệ thống tự động chặn không cho bạt vừa lên vừa xuống cùng lúc.</div>
        </div>
    </div>
</div>

<!-- 5. Control -->
<div id="control" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">5️⃣ Điều khiển</div>
    <div class="text-xs text-gray-600 dark:text-gray-300 space-y-3">
        <p>Có 2 cách điều khiển:</p>

        <p><strong>Cách 1 - Điều khiển thủ công:</strong></p>
        <ul class="list-disc list-inside space-y-1 ml-2">
            <li>Vào <a href="/iot/control" class="text-blue-500 hover:underline">Điều khiển bạt</a></li>
            <li>Nhấn nút LÊN/XUỐNG/STOP</li>
            <li>Server gửi lệnh qua MQTT → ESP32 thực thi</li>
        </ul>

        <p><strong>Cách 2 - Auto theo nhiệt độ:</strong></p>
        <ul class="list-disc list-inside space-y-1 ml-2">
            <li>Vào trang chuồng (Cycle)</li>
            <li>Cấu hình nhiệt độ ngưỡng</li>
            <li>Hệ thống tự động điều khiển theo quy tắc</li>
        </ul>
    </div>
</div>

<!-- 6. Data Flow -->
<div id="data-flow" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">6️⃣ Dòng dữ liệu (Data Flow)</div>
    <div class="text-xs text-gray-600 dark:text-gray-300 space-y-3">
        <p><strong>ESP32 → Server (gửi dữ liệu):</strong></p>
        <pre class="bg-gray-900 text-green-400 p-2 rounded overflow-x-auto text-[10px]">
ESP32 ──MQTT──> cfarm/ESP001/heartbeat ──> MQTT Broker ──> mqtt_listener.php ──> database (devices)
ESP32 ─-MQTT──> cfarm/ESP001/telemetry ──> MQTT Broker ──> mqtt_listener.php ──> database (env_readings)
        </pre>

        <p><strong>Server → ESP32 (điều khiển):</strong></p>
        <pre class="bg-gray-900 text-green-400 p-2 rounded overflow-x-auto text-[10px]">
Web UI ──HTTP──> device_controller ──MQTT──> cfarm/ESP001/command ──> ESP32
        </pre>

        <p><strong>Các bảng dữ liệu chính:</strong></p>
        <ul class="list-disc list-inside space-y-1 ml-2">
            <li><code>devices</code> - Thông tin thiết bị, trạng thái online/offline</li>
            <li><code>device_channels</code> - Các kênh relay</li>
            <li><code>device_states</code> - Trạng thái hiện tại của từng kênh</li>
            <li><code>device_commands</code> - Lịch sử lệnh điều khiển</li>
            <li><code>device_state_log</code> - Log thay đổi trạng thái</li>
            <li><code>curtain_configs</code> - Cấu hình bộ bạt</li>
            <li><code>env_readings</code> - Dữ liệu cảm biến (nhiệt, ẩm, NH3...)</li>
            <li><code>sensor_readings</code> - Dữ liệu thô từ cảm biến</li>
        </ul>
    </div>
</div>

<!-- 7. MQTT -->
<div id="mqtt" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">7️⃣ MQTT Protocol</div>
    <div class="text-xs text-gray-600 dark:text-gray-300 space-y-3">
        <p><strong>Topics mà ESP32 cần gửi:</strong></p>

        <p><code>cfarm/{device_code}/heartbeat</code> - Gửi mỗi 30 giây:</p>
        <pre class="bg-gray-900 text-green-400 p-2 rounded overflow-x-auto text-[10px]">{
  "device": "ESP001",
  "status": "online",
  "wifi_rssi": -45,
  "ip": "192.168.1.100",
  "uptime": 12345,
  "heap": 25000,
  "version": "1.0.0"
}</pre>

        <p><code>cfarm/{device_code}/telemetry</code> - Gửi dữ liệu cảm biến:</p>
        <pre class="bg-gray-900 text-green-400 p-2 rounded overflow-x-auto text-[10px]">{
  "device": "ESP001",
  "temp": 28.5,
  "hum": 75.2,
  "nh3": 5.2,
  "co2": 450,
  "timestamp": 1699999999
}</pre>

        <p><strong>Topics để nhận lệnh:</strong></p>
        <p><code>cfarm/{device_code}/command</code> - Nhận lệnh điều khiển:</p>
        <pre class="bg-gray-900 text-green-400 p-2 rounded overflow-x-auto text-[10px]">{
  "action": "relay",
  "channel": 1,
  "state": "on",
  "duration": 30
}</pre>
    </div>
</div>

<!-- 8. OTA -->
<div id="ota" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">8️⃣ Cập nhật Firmware (OTA)</div>
    <div class="text-xs text-gray-600 dark:text-gray-300 space-y-3">
        <p><strong>OTA (Over-The-AUpdate)</strong> cho phép ESP32 tự cập nhật firmware qua WiFi mà không cần USB.</p>

        <p><strong>Cách sử dụng:</strong></p>
        <ol class="list-decimal list-inside space-y-1 ml-2">
            <li>Vào <a href="/settings/iot/firmwares" class="text-blue-500 hover:underline">Firmware Library</a></li>
            <li>Upload file .bin mới với version cao hơn</li>
            <li>ESP32 sẽ tự kiểm tra và cập nhật (nếu có code OTA)</li>
        </ol>

        <p><strong>API Endpoints:</strong></p>
        <ul class="list-disc list-inside space-y-1 ml-2">
            <li><code>GET /api/firmware/{type_id}/latest?version=x.x.x</code> - Kiểm tra cập nhật</li>
            <li><code>GET /api/firmware/{type_id}/bin</code> - Redirect đến file .bin mới nhất</li>
            <li><code>GET /api/firmware/download/{id}</code> - Tải firmware cụ thể</li>
        </ul>

        <p><strong>Thêm code OTA vào ESP32:</strong></p>
        <p>Xem hướng dẫn chi tiết trong trang Firmware của từng thiết bị.</p>
    </div>
</div>

<!-- Troubleshooting -->
<div class="bg-red-50 dark:bg-red-900/20 rounded-2xl border border-red-200 dark:border-red-800 p-4 mb-4">
    <div class="text-sm font-semibold mb-3 text-red-700 dark:text-red-300">🔧 Khắc phục sự cố thường gặp</div>
    <div class="text-xs text-gray-600 dark:text-gray-300 space-y-2">
        <div><strong>Thiết bị không online?</strong>
            <ul class="list-disc list-inside ml-2">
                <li>Kiểm tra WiFi và MQTT broker</li>
                <li>Xem Serial Monitor (115200 baud)</li>
                <li>Kiểm tra device_code có khớp với database không</li>
            </ul>
        </div>
        <div><strong>Không gửi được lệnh?</strong>
            <ul class="list-disc list-inside ml-2">
                <li>Kiểm tra MQTT broker đang chạy</li>
                <li>Kiểm tra ESP32 đang subscribe topic đúng</li>
            </ul>
        </div>
        <div><strong>Dữ liệu cảm biến không lưu?</strong>
            <ul class="list-disc list-inside ml-2">
                <li>Kiểm tra MQTT topic đúng format</li>
                <li>Xem log mqtt_listener.php</li>
            </ul>
        </div>
    </div>
</div>

<!-- Links -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
    <div class="text-sm font-semibold mb-3">🔗 Liên kết nhanh</div>
    <div class="flex flex-wrap gap-2">
        <a href="/settings/iot" class="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 px-3 py-1.5 rounded-full">⚙️ IoT Settings</a>
        <a href="/iot/nodes/create" class="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 px-3 py-1.5 rounded-full">➕ Thêm thiết bị</a>
        <a href="/settings/iot/types" class="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 px-3 py-1.5 rounded-full">🔧 Device Types</a>
        <a href="/settings/iot/firmwares" class="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 px-3 py-1.5 rounded-full">📦 Firmware Library</a>
        <a href="/iot/curtains/setup" class="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 px-3 py-1.5 rounded-full">🪟 Cài đặt bạt</a>
        <a href="/iot/control" class="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 px-3 py-1.5 rounded-full">🕹️ Điều khiển</a>
        <a href="/iot/devices" class="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 px-3 py-1.5 rounded-full">📡 Dashboard</a>
        <a href="/env" class="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 px-3 py-1.5 rounded-full">🌡️ Môi trường</a>
    </div>
</div>

<?php $content = ob_get_clean(); require view_path('layouts/main.php'); ?>
