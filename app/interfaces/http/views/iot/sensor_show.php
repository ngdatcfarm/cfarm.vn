<?php
$title = 'Sensor — ' . e($device->device_code);
ob_start();

$has_data = !empty($hourly) || $latest;
$hours_json = json_encode(array_map(fn($h) => date('H:i', strtotime($h->hour)), $hourly));
$temps_json = json_encode(array_map(fn($h) => $h->avg_temp, $hourly));
$hums_json  = json_encode(array_map(fn($h) => $h->avg_hum,  $hourly));
$days_json  = json_encode(array_map(fn($d) => date('d/m', strtotime($d->day)), $daily));
$daily_temps_json = json_encode(array_map(fn($d) => $d->avg_temp, $daily));
$daily_hums_json  = json_encode(array_map(fn($d) => $d->avg_hum,  $daily));
?>

<div class="mb-4 flex items-center justify-between">
    <a href="/iot/devices" class="text-sm text-blue-600 hover:underline">← Danh sách thiết bị</a>
    <span class="text-xs text-gray-400"><?= $total_readings ?> readings</span>
</div>

<!-- Header card -->
<div class="bg-teal-600 rounded-2xl p-4 mb-4">
    <div class="flex items-center gap-3 mb-3">
        <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-2xl">🌡️</div>
        <div>
            <div class="font-bold text-white"><?= e($device->device_code) ?></div>
            <div class="text-sm text-white/70"><?= e($device->barn_name ?? 'Chưa gán chuồng') ?></div>
            <div class="flex items-center gap-1.5 mt-0.5">
                <span class="w-2 h-2 rounded-full <?= $device->is_online ? 'bg-green-400' : 'bg-red-400' ?>"></span>
                <span class="text-xs text-white/60"><?= $device->is_online ? 'Online' : 'Offline' ?></span>
            </div>
        </div>
    </div>

    <!-- Latest reading -->
    <?php if ($latest): ?>
    <div class="grid grid-cols-3 gap-2">
        <div class="bg-white/10 rounded-xl p-3 text-center">
            <div class="text-2xl font-bold text-white"><?= $latest->temperature ?>°</div>
            <div class="text-xs text-white/60">Nhiệt độ</div>
        </div>
        <div class="bg-white/10 rounded-xl p-3 text-center">
            <div class="text-2xl font-bold text-white"><?= $latest->humidity ?>%</div>
            <div class="text-xs text-white/60">Độ ẩm</div>
        </div>
        <div class="bg-white/10 rounded-xl p-3 text-center">
            <div class="text-2xl font-bold text-white"><?= $latest->heat_index ?>°</div>
            <div class="text-xs text-white/60">Cảm giác</div>
        </div>
    </div>
    <div class="text-xs text-white/40 text-right mt-2">
        Cập nhật: <?= date('H:i:s d/m', strtotime($latest->recorded_at)) ?>
    </div>
    <?php else: ?>
    <div class="bg-white/10 rounded-xl p-4 text-center text-white/60 text-sm">
        Chưa có dữ liệu — chờ ESP32 gửi telemetry
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($hourly)): ?>
<!-- Biểu đồ 24h -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="flex items-center justify-between mb-3">
        <div class="text-sm font-semibold">📈 24 giờ qua</div>
        <div class="flex gap-3 text-xs text-gray-400">
            <span class="flex items-center gap-1"><span class="w-3 h-0.5 bg-orange-400 inline-block"></span>Nhiệt độ</span>
            <span class="flex items-center gap-1"><span class="w-3 h-0.5 bg-blue-400 inline-block"></span>Độ ẩm</span>
        </div>
    </div>
    <canvas id="chart24h" height="180"></canvas>
</div>
<?php endif; ?>

<?php if (!empty($daily)): ?>
<!-- Biểu đồ 7 ngày -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">📅 7 ngày qua</div>
    <canvas id="chart7d" height="180"></canvas>
</div>

<!-- Bảng thống kê 7 ngày -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">📊 Thống kê theo ngày</div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead>
                <tr class="text-gray-400 border-b border-gray-100 dark:border-gray-700">
                    <th class="text-left py-2">Ngày</th>
                    <th class="text-right py-2">TB °C</th>
                    <th class="text-right py-2">Min</th>
                    <th class="text-right py-2">Max</th>
                    <th class="text-right py-2">TB %</th>
                    <th class="text-right py-2">Readings</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (array_reverse($daily) as $d): ?>
            <tr class="border-t border-gray-50 dark:border-gray-700/50">
                <td class="py-2 font-medium"><?= date('d/m', strtotime($d->day)) ?></td>
                <td class="text-right text-orange-500 font-semibold"><?= $d->avg_temp ?>°</td>
                <td class="text-right text-blue-400"><?= $d->min_temp ?>°</td>
                <td class="text-right text-red-400"><?= $d->max_temp ?>°</td>
                <td class="text-right text-blue-500"><?= $d->avg_hum ?>%</td>
                <td class="text-right text-gray-300"><?= $d->readings ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!$has_data): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-8 text-center">
    <div class="text-4xl mb-3">📡</div>
    <div class="text-sm text-gray-500">Chưa nhận được dữ liệu từ cảm biến</div>
    <div class="text-xs text-gray-400 mt-1">ESP32 cần gửi MQTT topic: <code><?= e($device->mqtt_topic) ?>/telemetry</code></div>
</div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const isDark = document.documentElement.classList.contains('dark');
const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
const textColor = isDark ? '#9ca3af' : '#6b7280';

const commonOptions = {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { display: false } },
    scales: {
        x: { grid: { color: gridColor }, ticks: { color: textColor, maxTicksLimit: 8 } },
        y: { grid: { color: gridColor }, ticks: { color: textColor } }
    }
};

<?php if (!empty($hourly)): ?>
new Chart(document.getElementById('chart24h'), {
    type: 'line',
    data: {
        labels: <?= $hours_json ?>,
        datasets: [
            {
                label: 'Nhiệt độ (°C)',
                data: <?= $temps_json ?>,
                borderColor: '#f97316',
                backgroundColor: 'rgba(249,115,22,0.1)',
                fill: true, tension: 0.4, pointRadius: 2
            },
            {
                label: 'Độ ẩm (%)',
                data: <?= $hums_json ?>,
                borderColor: '#60a5fa',
                backgroundColor: 'rgba(96,165,250,0.1)',
                fill: true, tension: 0.4, pointRadius: 2,
                yAxisID: 'y2'
            }
        ]
    },
    options: {
        ...commonOptions,
        scales: {
            ...commonOptions.scales,
            y:  { ...commonOptions.scales.y, position: 'left',  title: { display: true, text: '°C', color: '#f97316' } },
            y2: { ...commonOptions.scales.y, position: 'right', title: { display: true, text: '%',  color: '#60a5fa' }, grid: { display: false } }
        }
    }
});
<?php endif; ?>

<?php if (!empty($daily)): ?>
new Chart(document.getElementById('chart7d'), {
    type: 'bar',
    data: {
        labels: <?= $days_json ?>,
        datasets: [
            {
                label: 'Nhiệt độ TB (°C)',
                data: <?= $daily_temps_json ?>,
                backgroundColor: 'rgba(249,115,22,0.7)',
                borderRadius: 6
            },
            {
                label: 'Độ ẩm TB (%)',
                data: <?= $daily_hums_json ?>,
                backgroundColor: 'rgba(96,165,250,0.7)',
                borderRadius: 6
            }
        ]
    },
    options: commonOptions
});
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
