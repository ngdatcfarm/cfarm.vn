<?php
$title = 'Cài đặt';
ob_start();

// Đếm device online/offline để hiển thị badge
$iot_stats = null;
try {
    global $pdo;
    if (!isset($pdo)) {
        $pdo = require '/var/www/app.cfarm.vn/app/shared/database/mysql.php';
    }
    $row = $pdo->query("SELECT COUNT(*) as total, SUM(is_online) as online FROM devices")->fetch();
    $iot_stats = $row;
} catch (\Throwable $e) {}
?>

<div class="max-w-lg mx-auto">

    <h1 class="text-xl font-bold mb-6">⚙️ Cài đặt</h1>

    <!-- IoT Dashboard — nổi bật lên đầu -->
    <div class="bg-indigo-600 rounded-2xl p-4 mb-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 bg-white/20 rounded-xl flex items-center justify-center text-2xl">📡</div>
            <div>
                <div class="text-sm font-bold text-white">Dashboard IoT</div>
                <div class="text-xs text-indigo-200">
                    <?php if ($iot_stats && $iot_stats['total'] > 0): ?>
                        <?= (int)$iot_stats['online'] ?> online · <?= (int)($iot_stats['total'] - $iot_stats['online']) ?> offline · <?= (int)$iot_stats['total'] ?> thiết bị
                    <?php else: ?>
                        Xem trạng thái thiết bị ESP32
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <a href="/iot/devices"
           class="bg-white text-indigo-600 text-xs font-bold px-4 py-2 rounded-full shrink-0">
            Mở →
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden divide-y divide-gray-100 dark:divide-gray-700">


        <a href="/account"
           class="flex items-center gap-4 p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <div class="w-11 h-11 bg-gray-100 dark:bg-gray-700 rounded-xl flex items-center justify-center text-xl shrink-0">👤</div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold">Tài khoản</div>
                <div class="text-xs text-gray-400 mt-0.5">Đổi mật khẩu · Đăng xuất</div>
            </div>
            <span class="text-gray-300">›</span>
        </a>

        <a href="/settings/iot"
           class="flex items-center gap-4 p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <div class="w-11 h-11 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center text-xl shrink-0">🎛️</div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold">Cài đặt IoT</div>
                <div class="text-xs text-gray-400 mt-0.5">Cấu hình bạt, relay, thiết bị & loại ESP32</div>
            </div>
            <span class="text-gray-300">›</span>
        </a>

        <a href="/settings/iot/firmwares"
           class="flex items-center gap-4 p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <div class="w-11 h-11 bg-purple-50 dark:bg-purple-900/30 rounded-xl flex items-center justify-center text-xl shrink-0">📦</div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold">Firmware Library</div>
                <div class="text-xs text-gray-400 mt-0.5">Quản lý firmware upload & OTA</div>
            </div>
            <span class="text-gray-300">›</span>
        </a>

        <a href="/settings/feed-brands"
           class="flex items-center gap-4 p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <div class="w-11 h-11 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center text-xl shrink-0">🌾</div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold">Hãng cám & Mã cám</div>
                <div class="text-xs text-gray-400 mt-0.5">Quản lý danh mục cám sử dụng</div>
            </div>
            <span class="text-gray-300">›</span>
        </a>

        <a href="/settings/vaccine-programs"
           class="flex items-center gap-4 p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <div class="w-11 h-11 bg-teal-50 dark:bg-teal-900/30 rounded-xl flex items-center justify-center text-xl shrink-0">📋</div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold">Bộ lịch vaccine</div>
                <div class="text-xs text-gray-400 mt-0.5">Tạo và quản lý lịch tiêm theo ngày tuổi</div>
            </div>
            <span class="text-gray-300">›</span>
        </a>

        <a href="/settings/vaccine-brands"
           class="flex items-center gap-4 p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <div class="w-11 h-11 bg-teal-50 dark:bg-teal-900/30 rounded-xl flex items-center justify-center text-xl shrink-0">💉</div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold">Hãng vaccine</div>
                <div class="text-xs text-gray-400 mt-0.5">Quản lý hãng sản xuất vaccine</div>
            </div>
            <span class="text-gray-300">›</span>
        </a>

        <a href="/settings/notifications"
           class="flex items-center gap-4 p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <div class="w-11 h-11 bg-blue-50 dark:bg-blue-900/30 rounded-xl flex items-center justify-center text-xl shrink-0">🔔</div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold">Cài đặt thông báo</div>
                <div class="text-xs text-gray-400 mt-0.5">Mức độ & lịch gửi push notification</div>
            </div>
            <span class="text-gray-300">›</span>
        </a>

        <a href="/settings/medications"
           class="flex items-center gap-4 p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <div class="w-11 h-11 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center text-xl shrink-0">💊</div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold">Danh mục thuốc</div>
                <div class="text-xs text-gray-400 mt-0.5">Thuốc & liều lượng thường dùng</div>
            </div>
            <span class="text-gray-300">›</span>
        </a>

    </div>

</div>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
