<?php
$title = '🌡️ ENV — ' . ($barn['name'] ?? '');
ob_start();
$labels_24h    = json_encode(array_column($env_24h, 'time_label'));
$temp_data     = json_encode(array_map(fn($r) => $r['temperature'], $env_24h));
$hum_data      = json_encode(array_map(fn($r) => $r['humidity'], $env_24h));
$nh3_data      = json_encode(array_map(fn($r) => $r['nh3_ppm'], $env_24h));
$co2_data      = json_encode(array_map(fn($r) => $r['co2_ppm'], $env_24h));
$lux_data      = json_encode(array_map(fn($r) => $r['light_lux'], $env_24h));
$fcr_labels    = json_encode(array_column($env_fcr, 'day_age'));
$fcr_data      = json_encode(array_column($env_fcr, 'fcr_cumulative'));
$avg_temp_data = json_encode(array_column($env_fcr, 'avg_temp'));
?>

<div class="mb-3 flex items-center gap-2">
    <a href="/env" class="text-sm text-blue-600">← ENV</a>
    <span class="text-gray-300">/</span>
    <span class="text-sm font-semibold"><?= htmlspecialchars($barn['name']) ?></span>
    <?php if ($cycle): ?>
    <span class="text-xs bg-teal-50 dark:bg-teal-900/30 text-teal-600 px-2 py-0.5 rounded-full ml-auto">
        <?= htmlspecialchars($cycle['code']) ?> · ngày <?= $latest['day_age'] ?? '?' ?>
    </span>
    <?php endif; ?>
</div>


<?php if ($device): ?>
<!-- Interval config -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="flex items-center justify-between">
        <div>
            <div class="text-sm font-semibold">⏱️ Tần suất cập nhật</div>
            <div class="text-xs text-gray-400 mt-0.5">
                Hiện tại: <strong><?= $device['env_interval_seconds'] ?>s</strong>
                (<?= round($device['env_interval_seconds']/60, 1) ?> phút)
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
                <option value="<?= $val ?>" <?= $device['env_interval_seconds'] == $val ? 'selected' : '' ?>>
                    <?= $label ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php if (isset($_GET['saved'])): ?>
    <div class="mt-2 text-xs text-green-600 bg-green-50 dark:bg-green-900/20 rounded-lg px-3 py-1.5">
        ✅ Đã cập nhật — ESP32 sẽ nhận config trong lần kết nối tiếp theo
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Latest reading cards -->
<?php if ($latest): ?>
<div class="grid grid-cols-4 gap-2 mb-4">
<?php
$cards = [
    ['icon'=>'🌡️','label'=>'Nhiệt độ',  'val'=> $latest['temperature'] ? $latest['temperature'].'°C' : '—',  'sub'=> $latest['heat_index'] ? 'Cảm giác '.$latest['heat_index'].'°C' : ''],
    ['icon'=>'💧','label'=>'Độ ẩm',     'val'=> $latest['humidity']    ? $latest['humidity'].'%'    : '—',  'sub'=>''],
    ['icon'=>'☁️','label'=>'NH3',        'val'=> $latest['nh3_ppm']     ? $latest['nh3_ppm'].'ppm'  : '—',  'sub'=>'ngưỡng 25ppm'],
    ['icon'=>'🌫️','label'=>'CO2',        'val'=> $latest['co2_ppm']     ? $latest['co2_ppm'].'ppm'  : '—',  'sub'=>'ngưỡng 3000ppm'],
    ['icon'=>'💨','label'=>'Gió',        'val'=> $latest['wind_speed_ms'] ? $latest['wind_speed_ms'].'m/s' : '—', 'sub'=>''],
    ['icon'=>'☀️','label'=>'Ánh sáng',   'val'=> $latest['light_lux']   ? number_format($latest['light_lux']).'lux' : '—', 'sub'=>''],
    ['icon'=>'🌧️','label'=>'Mưa',        'val'=> $latest['is_raining']===null ? '—' : ($latest['is_raining'] ? 'Đang mưa' : 'Không mưa'), 'sub'=>''],
    ['icon'=>'🕐','label'=>'Cập nhật',   'val'=> date('H:i', strtotime($latest['recorded_at'])), 'sub'=> date('d/m', strtotime($latest['recorded_at']))],
];
foreach ($cards as $c): ?>
<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-3 text-center">
    <div class="text-base"><?= $c['icon'] ?></div>
    <div class="font-bold text-sm mt-0.5"><?= $c['val'] ?></div>
    <div class="text-xs text-gray-400"><?= $c['label'] ?></div>
    <?php if ($c['sub']): ?><div class="text-xs text-gray-300"><?= $c['sub'] ?></div><?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Biểu đồ 24h -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">📈 24 giờ qua</div>
    <?php if (empty($env_24h)): ?>
    <div class="text-center py-8 text-sm text-gray-400">Chưa có dữ liệu 24h</div>
    <?php else: ?>
    <div class="mb-4"><canvas id="chartTempHum" height="120"></canvas></div>
    <div class="mb-4"><canvas id="chartGas"     height="100"></canvas></div>
    <div>        <canvas id="chartLux"     height="80"></canvas></div>
    <?php endif; ?>
</div>

<!-- ENV vs FCR -->
<?php if (!empty($env_fcr) && count(array_filter(array_column($env_fcr, 'fcr_cumulative')))): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-1">🔬 Nhiệt độ vs FCR theo ngày tuổi</div>
    <div class="text-xs text-gray-400 mb-3">Tương quan môi trường → hiệu quả chăn nuôi</div>
    <canvas id="chartEnvFcr" height="120"></canvas>
</div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const isDark = document.documentElement.classList.contains('dark');
const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
const textColor = isDark ? '#9ca3af' : '#6b7280';

const labels = <?= $labels_24h ?>;
const tempData = <?= $temp_data ?>;
const humData  = <?= $hum_data  ?>;
const nh3Data  = <?= $nh3_data  ?>;
const co2Data  = <?= $co2_data  ?>;
const luxData  = <?= $lux_data  ?>;

const baseOpts = {
    responsive: true,
    plugins: { legend: { labels: { color: textColor, boxWidth: 12, font: { size: 11 } } } },
    scales: {
        x: { ticks: { color: textColor, maxTicksLimit: 8, font:{size:10} }, grid: { color: gridColor } },
        y: { ticks: { color: textColor, font:{size:10} }, grid: { color: gridColor } }
    }
};

// Chart 1: Nhiệt + Ẩm
new Chart(document.getElementById('chartTempHum'), {
    type: 'line',
    data: {
        labels,
        datasets: [
            { label:'Nhiệt độ (°C)', data: tempData, borderColor:'#ef4444', backgroundColor:'rgba(239,68,68,0.1)', tension:0.3, pointRadius:0, yAxisID:'y' },
            { label:'Độ ẩm (%)',     data: humData,  borderColor:'#3b82f6', backgroundColor:'rgba(59,130,246,0.1)', tension:0.3, pointRadius:0, yAxisID:'y1' },
        ]
    },
    options: { ...baseOpts, scales: { ...baseOpts.scales,
        y:  { ...baseOpts.scales.y, position:'left',  title:{display:true,text:'°C',color:textColor} },
        y1: { ...baseOpts.scales.y, position:'right', title:{display:true,text:'%',color:textColor}, grid:{display:false} }
    }}
});

// Chart 2: NH3 + CO2
new Chart(document.getElementById('chartGas'), {
    type: 'line',
    data: {
        labels,
        datasets: [
            { label:'NH3 (ppm)', data: nh3Data, borderColor:'#f59e0b', tension:0.3, pointRadius:0, yAxisID:'y' },
            { label:'CO2 (ppm)', data: co2Data, borderColor:'#8b5cf6', tension:0.3, pointRadius:0, yAxisID:'y1' },
        ]
    },
    options: { ...baseOpts, scales: { ...baseOpts.scales,
        y:  { ...baseOpts.scales.y, position:'left',  title:{display:true,text:'NH3 ppm',color:textColor} },
        y1: { ...baseOpts.scales.y, position:'right', title:{display:true,text:'CO2 ppm',color:textColor}, grid:{display:false} }
    }}
});

// Chart 3: Ánh sáng
new Chart(document.getElementById('chartLux'), {
    type: 'line',
    data: { labels, datasets: [
        { label:'Ánh sáng (lux)', data: luxData, borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,0.1)', tension:0.3, pointRadius:0, fill:true }
    ]},
    options: baseOpts
});

// Chart 4: ENV vs FCR
const fcrEl = document.getElementById('chartEnvFcr');
if (fcrEl) {
    const fcrLabels = <?= $fcr_labels ?>;
    const fcrData   = <?= $fcr_data   ?>;
    const avgTemp   = <?= $avg_temp_data ?>;
    new Chart(fcrEl, {
        type: 'line',
        data: { labels: fcrLabels, datasets: [
            { label:'FCR tích lũy', data: fcrData,  borderColor:'#10b981', tension:0.3, pointRadius:2, yAxisID:'y' },
            { label:'Nhiệt TB (°C)', data: avgTemp, borderColor:'#ef4444', tension:0.3, pointRadius:2, yAxisID:'y1', borderDash:[4,4] },
        ]},
        options: { ...baseOpts, scales: { ...baseOpts.scales,
            x:  { ...baseOpts.scales.x, title:{display:true,text:'Ngày tuổi',color:textColor} },
            y:  { ...baseOpts.scales.y, position:'left',  title:{display:true,text:'FCR',color:textColor} },
            y1: { ...baseOpts.scales.y, position:'right', title:{display:true,text:'Nhiệt °C',color:textColor}, grid:{display:false} }
        }}
    });
}
</script>

<?php $content = ob_get_clean(); require view_path('layouts/main.php'); ?>
