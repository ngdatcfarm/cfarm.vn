<?php
$title = 'Môi trường chuồng nuôi';
ob_start();
?>
<div class="mb-4 flex items-center justify-between">
    <h1 class="text-lg font-bold">🌡️ Môi trường chuồng nuôi</h1>
    <span class="text-xs text-gray-400" id="last-update">Cập nhật: <?= date('H:i:s') ?></span>
</div>

<?php if (!empty($alerts)): ?>
<div class="space-y-2 mb-4">
    <?php foreach ($alerts as $a):
        $bg = $a['type']==='danger'
            ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-700 dark:text-red-300'
            : 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800 text-yellow-700 dark:text-yellow-300';
    ?>
    <div class="rounded-xl border px-4 py-2.5 text-sm flex items-center gap-2 <?= $bg ?>">
        <?= $a['type']==='danger' ? '🔴' : '⚠️' ?>
        <strong><?= htmlspecialchars($a['barn']) ?>:</strong>
        <?= htmlspecialchars($a['msg']) ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="space-y-3">
<?php foreach ($barns as $b):
    $has_data  = !empty($b['recorded_at']);
    $is_fresh  = $has_data && ($b['minutes_ago'] ?? 999) <= 15;
    $temp      = $b['temperature'];
    $hum       = $b['humidity'];
    $nh3       = $b['nh3_ppm'];
    $co2       = $b['co2_ppm'];
    $temp_color = !$temp ? 'text-gray-400' : ($temp > 35 ? 'text-red-500' : ($temp < 20 ? 'text-blue-500' : 'text-green-600'));
    $hum_color  = !$hum  ? 'text-gray-400' : ($hum > 85 ? 'text-red-500' : 'text-green-600');
    $nh3_color  = !$nh3  ? 'text-gray-400' : ($nh3 > 25 ? 'text-red-500' : ($nh3 > 15 ? 'text-yellow-500' : 'text-green-600'));
    $co2_color  = !$co2  ? 'text-gray-400' : ($co2 > 3000 ? 'text-red-500' : ($co2 > 2000 ? 'text-yellow-500' : 'text-green-600'));
?>
<a href="/env/barn/<?= $b['id'] ?>"
   class="block bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 hover:border-teal-300 transition-colors">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <span class="font-semibold text-sm"><?= htmlspecialchars($b['name']) ?></span>
            <?php if ($b['cycle_code']): ?>
            <span class="text-xs bg-teal-50 dark:bg-teal-900/30 text-teal-600 px-2 py-0.5 rounded-full">
                <?= htmlspecialchars($b['cycle_code']) ?> · ngày <?= $b['day_age'] ?>
            </span>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-1.5">
            <?php if (!$has_data): ?>
                <span class="text-xs text-gray-400">Chưa có sensor</span>
            <?php elseif ($is_fresh): ?>
                <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                <span class="text-xs text-gray-400"><?= $b['minutes_ago'] ?>p trước</span>
            <?php else: ?>
                <span class="w-2 h-2 rounded-full bg-gray-300"></span>
                <span class="text-xs text-gray-400"><?= $b['minutes_ago'] ?>p trước</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($has_data): ?>
    <div class="grid grid-cols-4 gap-2">
        <div class="text-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
            <div class="text-xs text-gray-400 mb-0.5">🌡️ Nhiệt</div>
            <div class="font-bold text-sm <?= $temp_color ?>"><?= $temp ? $temp.'°C' : '—' ?></div>
        </div>
        <div class="text-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
            <div class="text-xs text-gray-400 mb-0.5">💧 Ẩm</div>
            <div class="font-bold text-sm <?= $hum_color ?>"><?= $hum ? $hum.'%' : '—' ?></div>
        </div>
        <div class="text-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
            <div class="text-xs text-gray-400 mb-0.5">☁️ NH3</div>
            <div class="font-bold text-sm <?= $nh3_color ?>"><?= $nh3 ? $nh3.'ppm' : '—' ?></div>
        </div>
        <div class="text-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
            <div class="text-xs text-gray-400 mb-0.5">🌫️ CO2</div>
            <div class="font-bold text-sm <?= $co2_color ?>"><?= $co2 ? $co2.'ppm' : '—' ?></div>
        </div>
    </div>
    <?php if ($b['wind_speed_ms'] || $b['light_lux'] || $b['is_raining'] !== null): ?>
    <div class="flex gap-3 mt-2 text-xs text-gray-400">
        <?php if ($b['wind_speed_ms']): ?><span>💨 <?= $b['wind_speed_ms'] ?>m/s</span><?php endif; ?>
        <?php if ($b['light_lux']):    ?><span>☀️ <?= number_format($b['light_lux']) ?>lux</span><?php endif; ?>
        <?php if ($b['is_raining'] !== null): ?>
            <span><?= $b['is_raining'] ? '🌧️ Đang mưa' : '☀️ Không mưa' ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <div class="text-center py-4 text-sm text-gray-400">
        Chưa có thiết bị ENV sensor — <a href="/settings/iot" class="text-blue-500 underline">Cài đặt</a>
    </div>
    <?php endif; ?>
</a>
<?php endforeach; ?>
</div>

<script>
// Auto refresh mỗi 5 phút
setTimeout(() => location.reload(), 300000);
</script>

<?php $content = ob_get_clean(); require view_path('layouts/main.php'); ?>
