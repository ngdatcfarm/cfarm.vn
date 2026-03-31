<?php
$title = 'ENV — ' . ($barn['name'] ?? '');
ob_start();

// JSON data cho biểu đồ 24h
$labels_24h    = json_encode(array_column($env_24h, 'time_label'));
$temp_data     = json_encode(array_map(fn($r) => $r['temperature'], $env_24h));
$hum_data      = json_encode(array_map(fn($r) => $r['humidity'], $env_24h));
$nh3_data      = json_encode(array_map(fn($r) => $r['nh3_ppm'], $env_24h));
$co2_data      = json_encode(array_map(fn($r) => $r['co2_ppm'], $env_24h));
$lux_data      = json_encode(array_map(fn($r) => $r['light_lux'], $env_24h));
$hi_data       = json_encode(array_map(fn($r) => $r['heat_index'], $env_24h));

// JSON data cho biểu đồ 7 ngày
$daily_labels    = json_encode(array_column($daily_7d, 'short_date'));
$daily_avg_temp  = json_encode(array_column($daily_7d, 'avg_temp'));
$daily_min_temp  = json_encode(array_column($daily_7d, 'min_temp'));
$daily_max_temp  = json_encode(array_column($daily_7d, 'max_temp'));
$daily_avg_hum   = json_encode(array_column($daily_7d, 'avg_hum'));
$daily_min_hum   = json_encode(array_column($daily_7d, 'min_hum'));
$daily_max_hum   = json_encode(array_column($daily_7d, 'max_hum'));
$daily_avg_nh3   = json_encode(array_column($daily_7d, 'avg_nh3'));
$daily_max_nh3   = json_encode(array_column($daily_7d, 'max_nh3'));
$daily_avg_co2   = json_encode(array_column($daily_7d, 'avg_co2'));
$daily_max_co2   = json_encode(array_column($daily_7d, 'max_co2'));
$daily_avg_lux   = json_encode(array_column($daily_7d, 'avg_lux'));

// JSON data cho biểu đồ phân bố theo giờ
$hourly_labels   = json_encode(array_map(fn($r) => str_pad((string)$r['hour_slot'], 2, '0', STR_PAD_LEFT) . ':00', $hourly_dist));
$hourly_temp     = json_encode(array_column($hourly_dist, 'avg_temp'));
$hourly_hum      = json_encode(array_column($hourly_dist, 'avg_hum'));
$hourly_nh3      = json_encode(array_column($hourly_dist, 'avg_nh3'));
$hourly_lux      = json_encode(array_column($hourly_dist, 'avg_lux'));

// ENV vs FCR
$fcr_labels    = json_encode(array_column($env_fcr, 'day_age'));
$fcr_data      = json_encode(array_column($env_fcr, 'fcr_cumulative'));
$avg_temp_data = json_encode(array_column($env_fcr, 'avg_temp'));
$avg_nh3_fcr   = json_encode(array_column($env_fcr, 'avg_nh3'));
?>

<!-- Breadcrumb -->
<div class="mb-3 flex items-center gap-2">
    <a href="/env" class="text-sm text-blue-600 dark:text-blue-400">← ENV</a>
    <span class="text-gray-300 dark:text-gray-600">/</span>
    <span class="text-sm font-semibold"><?= htmlspecialchars($barn['name']) ?></span>
    <?php if ($cycle): ?>
    <span class="text-xs bg-teal-50 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400 px-2 py-0.5 rounded-full ml-auto">
        <?= htmlspecialchars($cycle['code']) ?> · ngày <?= $latest['day_age'] ?? '?' ?>
    </span>
    <?php endif; ?>
</div>

<?php if ($device): ?>
<!-- Sensor selector tabs (chỉ hiện khi > 1 sensor) -->
<?php if (count($devices) > 1): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-3 mb-4">
    <div class="text-xs text-gray-400 mb-2">Chọn sensor</div>
    <div class="flex flex-wrap gap-2">
        <a href="?sensor=all"
           class="px-3 py-1.5 rounded-xl text-xs font-medium transition-colors
                  <?= $selected_sensor === 'all'
                      ? 'bg-teal-500 text-white'
                      : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200' ?>">
            Tất cả (TB)
        </a>
        <?php foreach ($devices as $dv): ?>
        <a href="?sensor=<?= $dv['id'] ?>"
           class="px-3 py-1.5 rounded-xl text-xs font-medium transition-colors
                  <?= $selected_sensor === (int)$dv['id']
                      ? 'bg-teal-500 text-white'
                      : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200' ?>">
            <?= htmlspecialchars($dv['name'] ?: 'Sensor #'.$dv['id']) ?>
            <?php if ($dv['is_online']): ?><span class="ml-1 w-1.5 h-1.5 rounded-full bg-green-400 inline-block"></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php $interval = $device['env_interval_seconds'] ?? 300; ?>
<!-- Interval config -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="flex items-center justify-between">
        <div>
            <div class="text-sm font-semibold">Tần suất cập nhật</div>
            <div class="text-xs text-gray-400 mt-0.5">
                Hiện tại: <strong><?= $interval ?>s</strong>
                (<?= round($interval/60, 1) ?> phút)
            </div>
        </div>
        <form method="POST" action="/env/barn/<?= $barn['id'] ?>/interval" class="flex items-center gap-2">
            <select name="interval_seconds"
                    onchange="this.form.submit()"
                    class="text-xs border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2 bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-teal-400">
                <?php foreach ([
                    30  => '30 giây',
                    60  => '1 phút',
                    120 => '2 phút',
                    300 => '5 phút',
                    600 => '10 phút',
                    900 => '15 phút',
                ] as $val => $label): ?>
                <option value="<?= $val ?>" <?= $interval == $val ? 'selected' : '' ?>>
                    <?= $label ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php if (isset($_GET['saved'])): ?>
    <div class="mt-2 text-xs text-green-600 bg-green-50 dark:bg-green-900/20 rounded-lg px-3 py-1.5">
        Da cap nhat — ESP32 se nhan config trong lan ket noi tiep theo
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ===== LATEST READING CARDS ===== -->
<?php if ($latest): ?>
<div class="grid grid-cols-4 gap-2 mb-4">
<?php
$cards = [
    ['icon'=>'🌡️','label'=>'Nhiệt độ',  'val'=> $latest['temperature'] !== null ? $latest['temperature'].'°C' : '—',  'sub'=> $latest['heat_index'] ? 'Cảm giác '.$latest['heat_index'].'°C' : ''],
    ['icon'=>'💧','label'=>'Độ ẩm',     'val'=> $latest['humidity'] !== null ? $latest['humidity'].'%' : '—',  'sub'=>''],
    ['icon'=>'☁️','label'=>'NH3',        'val'=> $latest['nh3_ppm'] !== null ? $latest['nh3_ppm'].'ppm' : '—',  'sub'=>'ngưỡng 25ppm'],
    ['icon'=>'🌫️','label'=>'CO2',        'val'=> $latest['co2_ppm'] !== null ? $latest['co2_ppm'].'ppm' : '—',  'sub'=>'ngưỡng 3000ppm'],
    ['icon'=>'💨','label'=>'Gió',        'val'=> ($weather['wind_speed_ms'] ?? null) ? $weather['wind_speed_ms'].'m/s' : '—', 'sub'=> $weather ? '' : 'chưa có trạm'],
    ['icon'=>'☀️','label'=>'Ánh sáng',   'val'=> $latest['light_lux'] !== null ? number_format((float)$latest['light_lux']).'lux' : '—', 'sub'=>''],
    ['icon'=>'🌧️','label'=>'Mưa',        'val'=> ($weather['is_raining'] ?? null)===null ? '—' : ($weather['is_raining'] ? 'Đang mưa' : 'Không'), 'sub'=> $weather ? '' : 'chưa có trạm'],
    ['icon'=>'🕐','label'=>'Cập nhật',   'val'=> date('H:i', strtotime($latest['recorded_at'])), 'sub'=> date('d/m', strtotime($latest['recorded_at']))],
];
foreach ($cards as $c): ?>
<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-3 text-center">
    <div class="text-base"><?= $c['icon'] ?></div>
    <div class="font-bold text-sm mt-0.5"><?= $c['val'] ?></div>
    <div class="text-xs text-gray-400"><?= $c['label'] ?></div>
    <?php if ($c['sub']): ?><div class="text-xs text-gray-300 dark:text-gray-500"><?= $c['sub'] ?></div><?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ===== THỐNG KÊ 7 NGÀY ===== -->
<?php if ($stats_7d && $stats_7d['total_readings'] > 0): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">Thống kê 7 ngày</div>
    <div class="grid grid-cols-3 gap-3">
        <div class="text-center">
            <div class="text-xs text-gray-400">Nhiệt TB</div>
            <div class="font-bold text-lg text-red-500"><?= $stats_7d['avg_temp'] ?>°C</div>
            <div class="text-xs text-gray-400"><?= $stats_7d['min_temp'] ?> ~ <?= $stats_7d['max_temp'] ?>°C</div>
        </div>
        <div class="text-center">
            <div class="text-xs text-gray-400">Ẩm TB</div>
            <div class="font-bold text-lg text-blue-500"><?= $stats_7d['avg_hum'] ?>%</div>
            <?php if ($stats_7d['hum_over_count'] > 0): ?>
            <div class="text-xs text-red-400"><?= $stats_7d['hum_over_count'] ?> lần >85%</div>
            <?php else: ?>
            <div class="text-xs text-green-500">Ổn định</div>
            <?php endif; ?>
        </div>
        <div class="text-center">
            <div class="text-xs text-gray-400">NH3 TB</div>
            <div class="font-bold text-lg <?= ($stats_7d['avg_nh3'] ?? 0) > 15 ? 'text-yellow-500' : 'text-green-500' ?>">
                <?= $stats_7d['avg_nh3'] ?? '—' ?>ppm
            </div>
            <?php if ($stats_7d['nh3_over_count'] > 0): ?>
            <div class="text-xs text-red-400"><?= $stats_7d['nh3_over_count'] ?> lần >25ppm</div>
            <?php else: ?>
            <div class="text-xs text-green-500">An toàn</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="mt-3 flex items-center justify-between text-xs text-gray-400 border-t border-gray-100 dark:border-gray-700 pt-2">
        <span><?= number_format((int)$stats_7d['total_readings']) ?> bản ghi</span>
        <span><?= $stats_7d['temp_readings'] ?> có nhiệt · <?= $stats_7d['gas_readings'] ?> có khí</span>
        <?php if ($stats_7d['temp_over_count'] > 0): ?>
        <span class="text-red-400"><?= $stats_7d['temp_over_count'] ?> lần >35°C</span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ===== BIỂU ĐỒ 24H ===== -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">24 giờ qua</div>
    <?php if (empty($env_24h)): ?>
    <div class="text-center py-8 text-sm text-gray-400">Chưa có dữ liệu 24h</div>
    <?php else: ?>
    <div class="mb-4"><canvas id="chartTempHum" height="120"></canvas></div>
    <div class="mb-4"><canvas id="chartGas"     height="100"></canvas></div>
    <div>            <canvas id="chartLux"     height="80"></canvas></div>
    <?php endif; ?>
</div>

<!-- ===== BIỂU ĐỒ XU HƯỚNG 7 NGÀY (min-max band) ===== -->
<?php if (!empty($daily_7d) && count($daily_7d) >= 2): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-1">Xu hướng 7 ngày</div>
    <div class="text-xs text-gray-400 mb-3">Dải min-max + trung bình mỗi ngày</div>
    <div class="mb-4"><canvas id="chart7dTemp" height="120"></canvas></div>
    <div class="mb-4"><canvas id="chart7dHum"  height="100"></canvas></div>
    <div>            <canvas id="chart7dGas"  height="100"></canvas></div>
</div>
<?php endif; ?>

<!-- ===== PHÂN BỐ THEO GIỜ TRONG NGÀY ===== -->
<?php if (!empty($hourly_dist) && count($hourly_dist) >= 4): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-1">Phân bố theo giờ (TB 7 ngày)</div>
    <div class="text-xs text-gray-400 mb-3">Giờ nào nóng nhất, NH3 cao nhất, sáng tối thế nào</div>
    <div class="mb-4"><canvas id="chartHourlyTemp" height="100"></canvas></div>
    <div>            <canvas id="chartHourlyCombo" height="100"></canvas></div>
</div>
<?php endif; ?>

<!-- ===== ENV vs FCR ===== -->
<?php if (!empty($env_fcr) && count(array_filter(array_column($env_fcr, 'fcr_cumulative')))): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-1">Nhiệt độ & NH3 vs FCR theo ngày tuổi</div>
    <div class="text-xs text-gray-400 mb-3">Tương quan môi trường → hiệu quả chăn nuôi</div>
    <canvas id="chartEnvFcr" height="140"></canvas>
</div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const isDark = document.documentElement.classList.contains('dark');
const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
const textColor = isDark ? '#9ca3af' : '#6b7280';

const baseOpts = {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { labels: { color: textColor, boxWidth: 12, font: { size: 11 } } } },
    scales: {
        x: { ticks: { color: textColor, maxTicksLimit: 8, font:{size:10} }, grid: { color: gridColor } },
        y: { ticks: { color: textColor, font:{size:10} }, grid: { color: gridColor } }
    }
};

// ==================== BIỂU ĐỒ 24H ====================
<?php if (!empty($env_24h)): ?>
const labels = <?= $labels_24h ?>;
const tempData = <?= $temp_data ?>;
const humData  = <?= $hum_data  ?>;
const nh3Data  = <?= $nh3_data  ?>;
const co2Data  = <?= $co2_data  ?>;
const luxData  = <?= $lux_data  ?>;
const hiData   = <?= $hi_data   ?>;

// Nhiệt + Ẩm 24h
new Chart(document.getElementById('chartTempHum'), {
    type: 'line',
    data: {
        labels,
        datasets: [
            { label:'Nhiệt độ (°C)', data: tempData, borderColor:'#ef4444', backgroundColor:'rgba(239,68,68,0.08)', tension:0.3, pointRadius:0, fill:true, yAxisID:'y' },
            { label:'Heat Index', data: hiData, borderColor:'#f97316', borderDash:[4,4], tension:0.3, pointRadius:0, yAxisID:'y' },
            { label:'Độ ẩm (%)', data: humData, borderColor:'#3b82f6', backgroundColor:'rgba(59,130,246,0.08)', tension:0.3, pointRadius:0, fill:true, yAxisID:'y1' },
        ]
    },
    options: { ...baseOpts, scales: { ...baseOpts.scales,
        y:  { ...baseOpts.scales.y, position:'left',  title:{display:true,text:'°C',color:textColor} },
        y1: { ...baseOpts.scales.y, position:'right', title:{display:true,text:'%',color:textColor}, grid:{display:false} }
    }}
});

// NH3 + CO2 24h
new Chart(document.getElementById('chartGas'), {
    type: 'line',
    data: {
        labels,
        datasets: [
            { label:'NH3 (ppm)', data: nh3Data, borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,0.08)', tension:0.3, pointRadius:0, fill:true, yAxisID:'y' },
            { label:'CO2 (ppm)', data: co2Data, borderColor:'#8b5cf6', tension:0.3, pointRadius:0, yAxisID:'y1' },
        ]
    },
    options: { ...baseOpts, scales: { ...baseOpts.scales,
        y:  { ...baseOpts.scales.y, position:'left',  title:{display:true,text:'NH3 ppm',color:textColor} },
        y1: { ...baseOpts.scales.y, position:'right', title:{display:true,text:'CO2 ppm',color:textColor}, grid:{display:false} }
    },
    plugins: { ...baseOpts.plugins, annotation: {} }
    }
});

// Ánh sáng 24h
new Chart(document.getElementById('chartLux'), {
    type: 'line',
    data: { labels, datasets: [
        { label:'Ánh sáng (lux)', data: luxData, borderColor:'#eab308', backgroundColor:'rgba(234,179,8,0.12)', tension:0.3, pointRadius:0, fill:true }
    ]},
    options: baseOpts
});
<?php endif; ?>

// ==================== BIỂU ĐỒ 7 NGÀY ====================
<?php if (!empty($daily_7d) && count($daily_7d) >= 2): ?>
const dLabels  = <?= $daily_labels ?>;
const dAvgTemp = <?= $daily_avg_temp ?>;
const dMinTemp = <?= $daily_min_temp ?>;
const dMaxTemp = <?= $daily_max_temp ?>;
const dAvgHum  = <?= $daily_avg_hum ?>;
const dMinHum  = <?= $daily_min_hum ?>;
const dMaxHum  = <?= $daily_max_hum ?>;
const dAvgNh3  = <?= $daily_avg_nh3 ?>;
const dMaxNh3  = <?= $daily_max_nh3 ?>;
const dAvgCo2  = <?= $daily_avg_co2 ?>;
const dMaxCo2  = <?= $daily_max_co2 ?>;
const dAvgLux  = <?= $daily_avg_lux ?>;

// Nhiệt 7 ngày: min-max band + avg line
new Chart(document.getElementById('chart7dTemp'), {
    type: 'line',
    data: {
        labels: dLabels,
        datasets: [
            { label:'Max °C', data: dMaxTemp, borderColor:'rgba(239,68,68,0.3)', backgroundColor:'rgba(239,68,68,0.08)', fill:'+1', tension:0.3, pointRadius:2, borderWidth:1 },
            { label:'Min °C', data: dMinTemp, borderColor:'rgba(59,130,246,0.3)', backgroundColor:'rgba(59,130,246,0.08)', fill:false, tension:0.3, pointRadius:2, borderWidth:1 },
            { label:'TB °C',  data: dAvgTemp, borderColor:'#ef4444', tension:0.3, pointRadius:3, borderWidth:2.5 },
        ]
    },
    options: { ...baseOpts, plugins: { ...baseOpts.plugins,
        title: { display:true, text:'Nhiệt độ (min / TB / max)', color: textColor, font:{size:12} }
    }}
});

// Ẩm 7 ngày: min-max band + avg line
new Chart(document.getElementById('chart7dHum'), {
    type: 'line',
    data: {
        labels: dLabels,
        datasets: [
            { label:'Max %', data: dMaxHum, borderColor:'rgba(59,130,246,0.3)', backgroundColor:'rgba(59,130,246,0.08)', fill:'+1', tension:0.3, pointRadius:2, borderWidth:1 },
            { label:'Min %', data: dMinHum, borderColor:'rgba(59,130,246,0.2)', fill:false, tension:0.3, pointRadius:2, borderWidth:1 },
            { label:'TB %',  data: dAvgHum, borderColor:'#3b82f6', tension:0.3, pointRadius:3, borderWidth:2.5 },
        ]
    },
    options: { ...baseOpts, plugins: { ...baseOpts.plugins,
        title: { display:true, text:'Độ ẩm (min / TB / max)', color: textColor, font:{size:12} }
    }}
});

// Khí 7 ngày: avg + max bars
new Chart(document.getElementById('chart7dGas'), {
    type: 'bar',
    data: {
        labels: dLabels,
        datasets: [
            { label:'NH3 TB', data: dAvgNh3, backgroundColor:'rgba(245,158,11,0.6)', borderRadius:4 },
            { label:'NH3 Max', data: dMaxNh3, backgroundColor:'rgba(245,158,11,0.2)', borderColor:'#f59e0b', borderWidth:1, borderRadius:4 },
            { label:'CO2 TB', data: dAvgCo2, backgroundColor:'rgba(139,92,246,0.6)', borderRadius:4 },
        ]
    },
    options: { ...baseOpts, plugins: { ...baseOpts.plugins,
        title: { display:true, text:'Khí NH3 & CO2 (TB / Max)', color: textColor, font:{size:12} }
    },
    scales: { ...baseOpts.scales,
        y: { ...baseOpts.scales.y, title:{display:true, text:'ppm', color:textColor} }
    }}
});
<?php endif; ?>

// ==================== PHÂN BỐ THEO GIỜ ====================
<?php if (!empty($hourly_dist) && count($hourly_dist) >= 4): ?>
const hLabels = <?= $hourly_labels ?>;
const hTemp = <?= $hourly_temp ?>;
const hHum  = <?= $hourly_hum ?>;
const hNh3  = <?= $hourly_nh3 ?>;
const hLux  = <?= $hourly_lux ?>;

// Nhiệt theo giờ — bar chart gradient
new Chart(document.getElementById('chartHourlyTemp'), {
    type: 'bar',
    data: {
        labels: hLabels,
        datasets: [{
            label:'Nhiệt TB (°C)',
            data: hTemp,
            backgroundColor: hTemp.map(v => {
                if (v === null) return 'rgba(156,163,175,0.3)';
                if (v > 35) return 'rgba(239,68,68,0.7)';
                if (v > 30) return 'rgba(245,158,11,0.7)';
                if (v < 20) return 'rgba(59,130,246,0.7)';
                return 'rgba(34,197,94,0.7)';
            }),
            borderRadius: 4,
        }]
    },
    options: { ...baseOpts, plugins: { ...baseOpts.plugins,
        title: { display:true, text:'Nhiệt độ TB theo giờ', color:textColor, font:{size:12} },
        legend: { display:false }
    },
    scales: { ...baseOpts.scales,
        y: { ...baseOpts.scales.y, title:{display:true, text:'°C', color:textColor} }
    }}
});

// NH3 + Lux theo giờ — combo
new Chart(document.getElementById('chartHourlyCombo'), {
    type: 'bar',
    data: {
        labels: hLabels,
        datasets: [
            { label:'NH3 TB (ppm)', data: hNh3, backgroundColor:'rgba(245,158,11,0.5)', borderRadius:4, yAxisID:'y' },
            { label:'Ánh sáng (lux)', data: hLux, type:'line', borderColor:'#eab308', tension:0.3, pointRadius:1, yAxisID:'y1' },
        ]
    },
    options: { ...baseOpts, plugins: { ...baseOpts.plugins,
        title: { display:true, text:'NH3 & Ánh sáng theo giờ', color:textColor, font:{size:12} },
    },
    scales: { ...baseOpts.scales,
        y:  { ...baseOpts.scales.y, position:'left',  title:{display:true, text:'NH3 ppm', color:textColor} },
        y1: { ...baseOpts.scales.y, position:'right', title:{display:true, text:'Lux',     color:textColor}, grid:{display:false} },
    }}
});
<?php endif; ?>

// ==================== ENV vs FCR ====================
<?php if (!empty($env_fcr) && count(array_filter(array_column($env_fcr, 'fcr_cumulative')))): ?>
const fcrLabels = <?= $fcr_labels ?>;
const fcrData   = <?= $fcr_data   ?>;
const avgTemp   = <?= $avg_temp_data ?>;
const avgNh3Fcr = <?= $avg_nh3_fcr ?>;

new Chart(document.getElementById('chartEnvFcr'), {
    type: 'line',
    data: { labels: fcrLabels, datasets: [
        { label:'FCR tích lũy', data: fcrData,    borderColor:'#10b981', tension:0.3, pointRadius:2, yAxisID:'y', borderWidth:2.5 },
        { label:'Nhiệt TB (°C)', data: avgTemp,   borderColor:'#ef4444', tension:0.3, pointRadius:1, yAxisID:'y1', borderDash:[4,4] },
        { label:'NH3 TB (ppm)', data: avgNh3Fcr,   borderColor:'#f59e0b', tension:0.3, pointRadius:1, yAxisID:'y2', borderDash:[2,2] },
    ]},
    options: { ...baseOpts, scales: { ...baseOpts.scales,
        x:  { ...baseOpts.scales.x, title:{display:true,text:'Ngày tuổi',color:textColor} },
        y:  { ...baseOpts.scales.y, position:'left',  title:{display:true,text:'FCR',color:textColor} },
        y1: { ...baseOpts.scales.y, position:'right', title:{display:true,text:'°C',color:textColor}, grid:{display:false} },
        y2: { display:false },
    }}
});
<?php endif; ?>

// Auto refresh mỗi 5 phút
setTimeout(() => location.reload(), 300000);
</script>

<?php $content = ob_get_clean(); require view_path('layouts/main.php'); ?>
