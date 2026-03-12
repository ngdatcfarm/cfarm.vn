<?php
$title = 'Báo cáo — ' . $cycle->code;
ob_start();
?>

<div class="max-w-lg mx-auto">

    <!-- Header -->
    <div class="flex items-center gap-3 mb-4">
        <a href="/reports" class="text-gray-400 hover:text-gray-600">←</a>
        <div>
            <h1 class="text-xl font-bold"><?= e($cycle->code) ?></h1>
            <div class="text-xs text-gray-400">
                <?= e($cycle->start_date) ?> ·
                <?= (int)((time() - strtotime($cycle->start_date)) / 86400) + 1 ?> ngày tuổi ·
                <?= number_format($cycle->current_quantity) ?> con
            </div>
        </div>
    </div>

    <!-- FCR Card -->

    <!-- Cảnh báo -->
    <?php if (!empty($cycle_alerts)): ?>
    <div class="mb-4 space-y-2">
        <?php foreach ($cycle_alerts as $a): ?>
        <?php
        $colors = [
            'danger'  => 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-700 dark:text-red-400',
            'warning' => 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800 text-yellow-700 dark:text-yellow-400',
            'info'    => 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-400',
        ];
        $icons = ['danger' => '🔴', 'warning' => '🟡', 'info' => '🔵'];
        $color = $colors[$a['severity']] ?? $colors['info'];
        $icon  = $icons[$a['severity']] ?? '🔵';
        ?>
        <div class="flex gap-3 rounded-xl border px-3 py-2.5 <?= $color ?>">
            <span class="text-sm"><?= $icon ?></span>
            <div>
                <div class="text-xs font-semibold"><?= e($a['message']) ?></div>
                <div class="text-xs opacity-75"><?= e($a['detail']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Dự đoán tăng trọng -->
    <?php if ($growth_pred): ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <div class="flex justify-between items-start mb-3">
            <div>
                <div class="text-sm font-semibold">🔮 Dự đoán tăng trọng</div>
                <div class="text-xs text-gray-400">
                    Linear regression · <?= $growth_pred['n_points'] ?> mốc cân
                    <?php if (!$growth_pred['reliable']): ?>
                    · <span class="text-yellow-500">⚠️ Cần thêm dữ liệu</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-right">
                <div class="text-lg font-bold text-purple-600 dark:text-purple-400">
                    ~<?= number_format($growth_pred['predicted_g']) ?>g
                </div>
                <div class="text-xs text-gray-400">Ngày <?= $growth_pred['target_day'] ?> ±<?= number_format($growth_pred['ci_kg']*1000) ?>g</div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 mb-3">
            <div class="bg-purple-50 dark:bg-purple-900/20 rounded-xl p-3 text-center">
                <div class="text-sm font-bold text-purple-700">+<?= $growth_pred['trend_g_per_day'] ?>g</div>
                <div class="text-xs text-gray-400">Tăng trọng/ngày</div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-3 text-center">
                <div class="text-sm font-bold"><?= number_format($growth_pred['r_squared']*100, 0) ?>%</div>
                <div class="text-xs text-gray-400">Độ tin cậy (R²)</div>
            </div>
        </div>
        <!-- Forecast chart -->
        <div style="position:relative;height:160px;">
            <canvas id="growthChart"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <div class="text-sm font-semibold mb-3">🔢 FCR</div>
        <?php if ($fcr): ?>
        <?php
        $fcr_color = $fcr < 1.8 ? 'text-green-600' : ($fcr <= 2.2 ? 'text-yellow-600' : 'text-red-600');
        $fcr_label = $fcr < 1.8 ? '✅ Tốt' : ($fcr <= 2.2 ? '⚠️ Trung bình' : '❌ Cần cải thiện');
        ?>
        <div class="flex items-center justify-between mb-3">
            <div class="text-4xl font-bold <?= $fcr_color ?>"><?= number_format($fcr, 2) ?></div>
            <span class="text-sm px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-700"><?= $fcr_label ?></span>
        </div>
        <div class="grid grid-cols-2 gap-3 text-center">
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-3">
                <div class="text-base font-bold text-blue-700"><?= number_format($total_feed_kg_consumed, 1) ?>kg</div>
                <div class="text-xs text-gray-400">Feed thực ăn</div>
            </div>
            <div class="bg-purple-50 dark:bg-purple-900/20 rounded-xl p-3">
                <div class="text-base font-bold text-purple-700"><?= number_format($weight_gain_kg ?? 0, 1) ?>kg</div>
                <div class="text-xs text-gray-400">Tăng trọng ước tính</div>
            </div>
        </div>
        <?php else: ?>
        <div class="text-center py-6 text-gray-400 text-sm">
            Chưa đủ dữ liệu tính FCR
            <div class="mt-1 text-xs">Cần ít nhất 2 buổi cân gà có phân tách trống/mái</div>
            <div class="mt-2">
                <a href="/events/create?cycle_id=<?= e($cycle->id) ?>"
                   class="text-blue-500 hover:underline">→ Ghi chép cân gà</a>
            </div>
        </div>
        <?php endif; ?>
    </div>


    <!-- FCR theo giai đoạn -->
    <?php if (!empty($fcr_stages)): ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <div class="text-sm font-semibold mb-3">📊 FCR theo giai đoạn</div>
        <div class="space-y-3">
            <?php foreach ($fcr_stages as $i => $stage): ?>
            <?php
            $fcr_color = !$stage['fcr'] ? 'text-gray-400'
                : ($stage['fcr'] < 1.8 ? 'text-green-600'
                : ($stage['fcr'] <= 2.2 ? 'text-yellow-600' : 'text-red-600'));
            ?>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-3">
                <div class="flex justify-between items-center mb-2">
                    <div class="text-xs font-semibold text-gray-500">
                        Giai đoạn <?= $i+1 ?> · Ngày <?= e($stage['from_day']) ?> → <?= e($stage['to_day']) ?>
                    </div>
                    <div class="text-lg font-bold <?= $fcr_color ?>">
                        <?= $stage['fcr'] ? 'FCR '.$stage['fcr'] : '—' ?>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div>
                        <div class="text-xs font-medium"><?= number_format($stage['avg_from']) ?>g → <?= number_format($stage['avg_to']) ?>g</div>
                        <div class="text-xs text-gray-400">Trọng lượng</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium <?= $stage['gain_g'] > 0 ? 'text-green-600' : 'text-red-500' ?>">
                            <?= $stage['gain_g'] > 0 ? '+' : '' ?><?= number_format($stage['gain_g']) ?>g/con
                        </div>
                        <div class="text-xs text-gray-400">Tăng trọng</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium"><?= e($stage['feed_kg']) ?>kg</div>
                        <div class="text-xs text-gray-400">Feed tiêu thụ</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Chi phí -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <div class="text-sm font-semibold mb-3">💸 Chi phí</div>
        <div class="space-y-2.5">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm">🐥 Nhập gà</div>
                    <div class="text-xs text-gray-400"><?= number_format($cycle->initial_quantity) ?> con × <?= number_format($cycle->purchase_price) ?>đ</div>
                </div>
                <span class="font-semibold text-sm"><?= number_format($chick_cost) ?>đ</span>
            </div>
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm">🌾 Cám</div>
                    <div class="text-xs text-gray-400"><?= number_format($total_feed_kg_poured, 1) ?>kg đổ vào</div>
                </div>
                <span class="font-semibold text-sm">
                    <?= $total_feed_cost > 0 ? number_format($total_feed_cost).'đ' : '<span class="text-gray-400 text-xs">Chưa có giá</span>' ?>
                </span>
            </div>
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm">💊 Thuốc</div>
                    <div class="text-xs text-gray-400"><?= count($care_medications) ?> lần dùng</div>
                </div>
                <span class="font-semibold text-sm">
                    <?= $total_med_cost > 0 ? number_format($total_med_cost).'đ' : '<span class="text-gray-400 text-xs">Chưa có giá</span>' ?>
                </span>
            </div>
            <div class="border-t border-gray-100 dark:border-gray-700 pt-2.5 flex justify-between items-center">
                <span class="text-sm font-bold">Tổng chi phí</span>
                <span class="text-lg font-bold text-red-600"><?= number_format($total_cost) ?>đ</span>
            </div>
        </div>
    </div>

    <!-- Doanh thu & P&L -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <div class="text-sm font-semibold mb-3">💰 Doanh thu & Lãi/Lỗ</div>
        <div class="space-y-2.5">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm">Doanh thu bán gà</div>
                    <div class="text-xs text-gray-400"><?= count($care_sales) ?> đợt bán</div>
                </div>
                <span class="font-semibold text-sm text-green-600"><?= number_format($total_revenue) ?>đ</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-sm">Tổng chi phí</span>
                <span class="font-semibold text-sm text-red-600"><?= number_format($total_cost) ?>đ</span>
            </div>
            <div class="border-t border-gray-100 dark:border-gray-700 pt-2.5">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-bold">Lãi / Lỗ ước tính</span>
                    <span class="text-xl font-bold <?= $profit >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= ($profit >= 0 ? '+' : '') . number_format($profit) ?>đ
                    </span>
                </div>
                <?php if ($total_cost > 0): ?>
                <div class="text-xs text-gray-400 text-right mt-1">
                    <?php if ($total_revenue > 0): ?>
                    Tỷ suất: <?= number_format($profit / $total_cost * 100, 1) ?>%
                    <?php else: ?>
                    Chưa có doanh thu
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <!-- Hao hụt -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <div class="text-sm font-semibold mb-3">📉 Hao hụt</div>
        <?php if (empty($care_deaths)): ?>
        <div class="text-center py-4 text-gray-400 text-sm">Không có hao hụt</div>
        <?php else: ?>
        <?php
        $total_deaths = array_sum(array_column($care_deaths, 'quantity'));
        $death_rate   = $cycle->initial_quantity > 0
            ? round($total_deaths / $cycle->initial_quantity * 100, 2) : 0;
        ?>
        <div class="grid grid-cols-3 gap-3 mb-3">
            <div class="text-center bg-red-50 dark:bg-red-900/20 rounded-xl p-3">
                <div class="text-xl font-bold text-red-600"><?= number_format($total_deaths) ?></div>
                <div class="text-xs text-gray-400">Tổng con chết</div>
            </div>
            <div class="text-center bg-orange-50 dark:bg-orange-900/20 rounded-xl p-3">
                <div class="text-xl font-bold text-orange-600"><?= $death_rate ?>%</div>
                <div class="text-xs text-gray-400">Tỷ lệ hao hụt</div>
            </div>
            <div class="text-center bg-gray-50 dark:bg-gray-700 rounded-xl p-3">
                <div class="text-xl font-bold text-gray-600"><?= number_format($cycle->current_quantity) ?></div>
                <div class="text-xs text-gray-400">Con hiện tại</div>
            </div>
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            <?php foreach (array_reverse($care_deaths) as $d): ?>
            <div class="py-2 flex justify-between text-sm">
                <div>
                    <span class="font-medium"><?= e($d['reason'] ?? 'Không rõ') ?></span>
                    <span class="text-xs text-gray-400 ml-1"><?= date('d/m', strtotime($d['recorded_at'])) ?></span>
                </div>
                <span class="font-semibold text-red-600">-<?= number_format($d['quantity']) ?> con</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Thuốc -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <div class="text-sm font-semibold mb-3">💊 Thuốc đã dùng</div>
        <?php if (empty($care_medications)): ?>
        <div class="text-center py-4 text-gray-400 text-sm">Chưa dùng thuốc</div>
        <?php else: ?>
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            <?php foreach ($care_medications as $m): ?>
            <div class="py-2 flex justify-between items-start text-sm">
                <div>
                    <div class="font-medium"><?= e($m['medication_name']) ?></div>
                    <div class="text-xs text-gray-400">
                        <?= e($m['dosage']) ?> <?= e($m['unit']) ?>
                        · <?= date('d/m', strtotime($m['recorded_at'])) ?>
                    </div>
                </div>
                <?php if (!empty($m['price_per_unit'])): ?>
                <span class="text-xs text-green-600 font-medium">
                    <?= number_format((float)$m['dosage'] * (float)$m['price_per_unit']) ?>đ
                </span>
                <?php else: ?>
                <span class="text-xs text-gray-400">—</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="border-t border-gray-100 dark:border-gray-700 pt-2 mt-2 flex justify-between text-sm font-semibold">
            <span>Tổng chi phí thuốc</span>
            <span class="text-purple-600"><?= $total_med_cost > 0 ? number_format($total_med_cost).'đ' : '—' ?></span>
        </div>
        <?php endif; ?>
    </div>


    <!-- FCR chart theo ngày -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <div class="text-sm font-semibold mb-1">📈 FCR tích lũy theo ngày</div>
        <div class="text-xs text-gray-400 mb-3">FCR càng thấp càng tốt · dưới 1.8 là tốt</div>
        <?php if (!empty($daily_snapshots)): ?>
        <div style="position:relative;height:200px;">
            <canvas id="fcrChart"></canvas>
        </div>
        <?php else: ?>
        <div class="text-center py-8 text-gray-400 text-sm">Chưa có dữ liệu</div>
        <?php endif; ?>
    </div>

    <!-- Biomass chart -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <div class="text-sm font-semibold mb-1">⚖️ Biomass đàn theo ngày</div>
        <div class="text-xs text-gray-400 mb-3">Tổng khối lượng đàn (kg)</div>
        <?php if (!empty($daily_snapshots)): ?>
        <div style="position:relative;height:200px;">
            <canvas id="biomassChart"></canvas>
        </div>
        <?php else: ?>
        <div class="text-center py-8 text-gray-400 text-sm">Chưa có dữ liệu</div>
        <?php endif; ?>
    </div>

    <!-- Biểu đồ chi phí cám theo ngày -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <div class="text-sm font-semibold mb-3">📈 Chi phí cám theo ngày tuổi</div>
        <?php if (!empty($feed_cost_by_day) && $total_feed_cost > 0): ?>
        <div style="position:relative;height:200px;">
            <canvas id="costChart"></canvas>
        </div>
        <?php else: ?>
        <div class="text-center py-8 text-gray-400 text-sm">
            Chưa có dữ liệu giá cám
            <div class="mt-1 text-xs">→ Thêm giá/bao trong cài đặt hãng cám</div>
        </div>
        <?php endif; ?>
    </div>

</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<?php
$snap_labels = json_encode(array_column($daily_snapshots, 'day_age'));
$snap_fcr    = json_encode(array_map(fn($s) => $s['fcr_cumulative'], $daily_snapshots));
$snap_biomass= json_encode(array_map(fn($s) => $s['biomass_kg'], $daily_snapshots));
?>
<script>
const snapLabels  = <?= $snap_labels ?>.map(d => 'N' + d);
const snapFcr     = <?= $snap_fcr ?>;
const snapBiomass = <?= $snap_biomass ?>;

// FCR chart
const fcrCtx = document.getElementById('fcrChart');
if (fcrCtx) {
    new Chart(fcrCtx, {
        type: 'line',
        data: {
            labels: snapLabels,
            datasets: [{
                label: 'FCR tích lũy',
                data: snapFcr,
                borderColor: 'rgb(139,92,246)',
                backgroundColor: 'rgba(139,92,246,0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 2,
                spanGaps: true,
            }, {
                label: 'Mục tiêu 1.8',
                data: snapLabels.map(() => 1.8),
                borderColor: 'rgba(34,197,94,0.5)',
                borderDash: [5,5],
                pointRadius: 0,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: true, labels: { font: { size: 10 } } } },
            scales: {
                x: { ticks: { font: { size: 10 }, maxTicksLimit: 10 } },
                y: { beginAtZero: false, ticks: { font: { size: 10 } } }
            }
        }
    });
}

// Biomass chart
const bioCtx = document.getElementById('biomassChart');
if (bioCtx) {
    new Chart(bioCtx, {
        type: 'line',
        data: {
            labels: snapLabels,
            datasets: [{
                label: 'Biomass (kg)',
                data: snapBiomass,
                borderColor: 'rgb(59,130,246)',
                backgroundColor: 'rgba(59,130,246,0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 2,
                spanGaps: true,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { font: { size: 10 }, maxTicksLimit: 10 } },
                y: { beginAtZero: true, ticks: { font: { size: 10 }, callback: v => v.toLocaleString('vi-VN') + 'kg' } }
            }
        }
    });
}
</script>

<script>
<?php if (!empty($feed_cost_by_day) && $total_feed_cost > 0): ?>
const cost_data = <?= json_encode(array_map(
    fn($d,$c) => ['day'=>$d,'cost'=>$c],
    array_keys($feed_cost_by_day),
    array_values($feed_cost_by_day)
)) ?>;

new Chart(document.getElementById('costChart'), {
    type: 'bar',
    data: {
        labels: cost_data.map(r => 'N' + r.day),
        datasets: [{
            label: 'Chi phí cám (đ)',
            data: cost_data.map(r => r.cost),
            backgroundColor: 'rgba(249,115,22,0.7)',
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false },
            tooltip: { callbacks: { label: ctx => number_format(ctx.parsed.y) + 'đ' } }
        },
        scales: {
            x: { ticks: { font: { size: 10 } }, title: { display: true, text: 'Ngày tuổi', font: { size: 10 } } },
            y: { beginAtZero: true, ticks: { font: { size: 10 }, callback: v => (v/1000000).toFixed(1)+'M' } }
        }
    }
});

function number_format(n) {
    return new Intl.NumberFormat('vi-VN').format(Math.round(n));
}
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>

<?php if ($growth_pred): ?>
<?php
$all_points   = array_merge(
    array_map(fn($p) => ['day' => $p['x'], 'actual' => $p['y'], 'pred' => null, 'lower' => null, 'upper' => null], $growth_pred['points']),
    array_map(fn($f) => ['day' => $f['day'], 'actual' => null, 'pred' => $f['predicted_g'], 'lower' => $f['lower_g'], 'upper' => $f['upper_g']], $growth_pred['forecast'])
);
?>
<script>
const growthData = <?= json_encode($all_points) ?>;
const growthCtx = document.getElementById('growthChart');
if (growthCtx) {
    new Chart(growthCtx, {
        type: 'line',
        data: {
            labels: growthData.map(p => 'N' + p.day),
            datasets: [
                {
                    label: 'Thực tế',
                    data: growthData.map(p => p.actual),
                    borderColor: 'rgb(139,92,246)',
                    backgroundColor: 'rgba(139,92,246,0.15)',
                    pointRadius: 4,
                    spanGaps: false,
                    tension: 0.3,
                },
                {
                    label: 'Dự đoán',
                    data: growthData.map(p => p.pred),
                    borderColor: 'rgba(139,92,246,0.5)',
                    borderDash: [5,5],
                    pointRadius: 2,
                    spanGaps: false,
                    tension: 0.3,
                },
                {
                    label: 'Khoảng tin cậy',
                    data: growthData.map(p => p.upper),
                    borderColor: 'transparent',
                    backgroundColor: 'rgba(139,92,246,0.08)',
                    fill: '+1',
                    pointRadius: 0,
                    spanGaps: false,
                },
                {
                    data: growthData.map(p => p.lower),
                    borderColor: 'transparent',
                    backgroundColor: 'rgba(139,92,246,0.08)',
                    fill: false,
                    pointRadius: 0,
                    spanGaps: false,
                },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: true, labels: { font: { size: 10 }, filter: i => i.datasetIndex < 2 } }
            },
            scales: {
                x: { ticks: { font: { size: 10 }, maxTicksLimit: 8 } },
                y: { ticks: { font: { size: 10 }, callback: v => v ? number_format(v) + 'g' : '' } }
            }
        }
    });
}
</script>
<?php endif; ?>
