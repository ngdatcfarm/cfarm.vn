<?php
/**
 * app/interfaces/http/views/cycle/cycle_show.php
 */
$title = 'Cycle — ' . e($cycle->code);
ob_start();

$stage_labels  = ['chick' => 'Gà con', 'grower' => 'Gà choai', 'adult' => 'Gà trưởng thành'];
$method_labels = ['water' => 'Uống nước', 'inject' => 'Tiêm', 'feed_mix' => 'Trộn cám', 'other' => 'Khác'];
$gender_labels = ['male' => 'Trống', 'female' => 'Mái', 'mixed' => 'Hỗn hợp'];
?>

<div class="max-w-lg mx-auto">

    <!-- header -->
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <a href="/barns/<?= e($cycle->barn_id) ?>" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">←</a>
            <div>
                <h1 class="text-xl font-bold"><?= e($cycle->code) ?></h1>
                <div class="text-xs text-gray-400"><?= e($barn->name) ?></div>
            </div>
        </div>
        <span class="text-xs font-semibold px-3 py-1 rounded-full
            <?= $cycle->is_active()
                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' ?>">
            <?= $cycle->is_active() ? 'Active' : 'Đã đóng' ?>
        </span>
    </div>

    <!-- summary strip -->
    <div class="bg-blue-600 rounded-2xl p-4 mb-4 grid grid-cols-4 gap-2">
        <div class="text-center">
            <div class="text-lg font-bold text-white"><?= e(number_format($cycle->current_quantity)) ?></div>
            <div class="text-xs text-blue-200">Hiện tại</div>
        </div>
        <div class="text-center border-l border-blue-400">
            <div class="text-lg font-bold text-white"><?= e($cycle->age_in_days()) ?></div>
            <div class="text-xs text-blue-200">Ngày tuổi</div>
        </div>
        <div class="text-center border-l border-blue-400">
            <div class="text-lg font-bold text-white"><?= e($cycle->mortality_rate()) ?>%</div>
            <div class="text-xs text-blue-200">Hao hụt</div>
        </div>
        <div class="text-center border-l border-blue-400">
            <div class="text-lg font-bold text-white"><?= e(number_format($cycle->initial_quantity)) ?></div>
            <div class="text-xs text-blue-200">Ban đầu</div>
        </div>
    </div>

    <!-- hãng cám -->
    <?php if ($current_feed_program): ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-4 mb-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center text-lg">🌾</div>
                <div>
                    <div class="text-xs text-gray-400">Hãng cám đang dùng</div>
                    <div class="font-semibold text-sm"><?= e($current_feed_program['brand_name']) ?></div>
                    <div class="text-xs text-gray-400"><?= e($current_feed_program['kg_per_bag']) ?>kg/bao · Từ <?= e($current_feed_program['start_date']) ?></div>
                </div>
            </div>
            <?php if ($cycle->is_active()): ?>
            <div class="flex flex-col gap-1.5 items-end">
                <a href="/cycles/<?= e($cycle->id) ?>/feed-program"
                   class="text-xs text-blue-600 hover:underline">Đổi hãng</a>
                <a href="/cycles/<?= e($cycle->id) ?>/feed-stages"
                   class="text-xs text-blue-600 hover:underline">Mã cám/stage</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif ($cycle->is_active()): ?>
    <div class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-xl text-sm text-yellow-700 dark:text-yellow-300 flex justify-between items-center">
        <span>⚠️ Chưa có hãng cám</span>
        <a href="/cycles/<?= e($cycle->id) ?>/feed-program" class="underline font-medium text-xs">Cài đặt ngay →</a>
    </div>
    <?php endif; ?>

    <!-- ghi chép nhanh -->
    <?php if ($cycle->is_active()): ?>
    <a href="/events/create?cycle_id=<?= e($cycle->id) ?>"
       class="flex items-center justify-center gap-2 w-full py-3 mb-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl text-sm transition-colors">
        ✏️ Ghi chép hôm nay
    </a>
    <?php endif; ?>

    <!-- tabs -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm mb-4 overflow-hidden">
        <div class="flex overflow-x-auto" style="scrollbar-width:none;">
            <?php
            $tabs = [
                'overview'   => '📋 Tổng quan',
                'feed'       => '🌾 Cho ăn',
                'weight'     => '⚖️ Cân gà',
                'medication' => '💊 Thuốc',
                'death'      => '📉 Hao hụt',
                'sale'       => '💰 Bán gà',
                'health'     => '🏥 Sức khỏe',
                'expense'    => '💸 Chi phí',
                'vaccine'    => '💉 Vaccine',
            ];
            foreach ($tabs as $key => $label):
            ?>
            <button onclick="switchTab('<?= $key ?>', this)"
                    class="tab-btn flex-shrink-0 px-4 py-3 text-xs font-medium border-b-2 transition-colors whitespace-nowrap
                           <?= $key === 'overview'
                               ? 'text-blue-600 border-blue-600 font-semibold'
                               : 'text-gray-400 border-transparent' ?>">
                <?= $label ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- TAB: Tổng quan -->
    <div id="tab_overview">
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden mb-3">
            <?php
            $info = [
                'Giống gà'         => $cycle->breed ?: '—',
                'Giai đoạn'        => $stage_labels[$cycle->stage] ?? $cycle->stage,
                'Ngày bắt đầu'     => $cycle->start_date,
                'Dự kiến kết thúc' => $cycle->expected_end_date ?: '—',
                'Trống / Mái'      => e($cycle->male_quantity) . ' / ' . e($cycle->female_quantity),
                'Giá nhập'         => number_format($cycle->purchase_price) . ' đ/con',
            ];
            if (!$cycle->is_active()) {
                $info['Ngày kết thúc'] = $cycle->end_date ?? '—';
                $info['Lý do đóng']   = $cycle->close_reason ?? '—';
            }
            ?>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php foreach ($info as $label => $value): ?>
                <div class="flex justify-between items-center px-4 py-3">
                    <span class="text-xs text-gray-400"><?= e($label) ?></span>
                    <span class="text-sm font-medium"><?= $value ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- actions -->
        <?php if ($cycle->is_active()): ?>
        <div class="grid grid-cols-3 gap-2 mb-3">
            <a href="/cycles/<?= e($cycle->id) ?>/edit"
               class="flex flex-col items-center gap-1 p-3 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-400 hover:border-blue-400 transition-colors">
                ✏️ <span>Sửa</span>
            </a>
            <a href="/cycles/<?= e($cycle->id) ?>/split"
               class="flex flex-col items-center gap-1 p-3 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-400 hover:border-blue-400 transition-colors">
                🔀 <span>Tách đàn</span>
            </a>
            <a href="/cycles/<?= e($cycle->id) ?>/close"
               class="flex flex-col items-center gap-1 p-3 bg-white dark:bg-gray-800 rounded-xl border border-red-200 dark:border-red-800 text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                🔒 <span>Đóng</span>
            </a>
        </div>
        <?php endif; ?>

        <!-- splits -->
        <?php if (!empty($splits)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 text-sm font-semibold">🔀 Lịch sử tách đàn</div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php foreach ($splits as $s): ?>
                <div class="px-4 py-3 text-xs flex justify-between">
                    <span class="text-gray-400"><?= e($s['split_date']) ?></span>
                    <span class="font-semibold"><?= e(number_format($s['quantity'])) ?> con → <?= e($s['to_code'] ?? '—') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB: Cho ăn -->
    <div id="tab_feed" class="hidden">

        <!-- CHART: Xu hướng ăn -->
        <div class="mb-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="text-sm font-semibold">📈 Xu hướng ăn</div>
                        <div class="text-xs text-gray-400">Toàn bộ vòng nuôi · ngày tuổi</div>
                    </div>
                    <button onclick="expandChart()" class="text-xs text-blue-500 hover:underline px-2 py-1 rounded-lg border border-blue-200">
                        ⛶ Phóng to
                    </button>
                </div>
                <div style="position:relative;height:180px;">
                    <canvas id="feedChart"></canvas>
                </div>
            </div>
        </div>

        <!-- MODAL: Chart fullscreen -->
        <div id="chart_modal" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-900 rounded-2xl w-full max-w-3xl p-5">
                <div class="flex justify-between items-center mb-4">
                    <div class="text-sm font-semibold">📈 Xu hướng ăn — <?= e($cycle->code) ?></div>
                    <button onclick="document.getElementById('chart_modal').classList.add('hidden')"
                            class="text-gray-400 text-2xl leading-none">×</button>
                </div>
                <div style="position:relative;height:400px;">
                    <canvas id="feedChartLarge"></canvas>
                </div>
                <div class="mt-3 flex gap-4 text-xs text-gray-500 justify-center">
                    <span><span class="inline-block w-3 h-0.5 bg-blue-500 align-middle mr-1"></span>Kg đổ vào/ngày</span>
                    <span><span class="inline-block w-3 h-0.5 bg-orange-400 align-middle mr-1"></span>% còn lại trong máng</span>
                </div>
            </div>
        </div>


        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="flex justify-between items-center px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                <div class="text-sm font-semibold">Lịch sử cho ăn</div>
                <div class="text-xs text-gray-400"><?= count($care_feeds) ?> bản ghi</div>
            </div>
            <?php if (empty($care_feeds)): ?>
                <div class="text-center py-10 text-gray-400 text-sm">Chưa có dữ liệu</div>
            <?php else: ?>
                <?php
                $total_bags     = array_sum(array_column($care_feeds, 'bags'));
                $total_kg       = array_sum(array_column($care_feeds, 'kg_actual'));
                // tổng gà thực ăn = tổng từng lần (trừ phần còn lại)
                $total_consumed = 0;
                foreach ($care_feeds as $f) {
                    $pct = isset($f['latest_remaining_pct']) && $f['latest_remaining_pct'] !== null
                        ? (int)$f['latest_remaining_pct'] : null;
                    $total_consumed += $pct !== null
                        ? $f['kg_actual'] * (1 - $pct / 100)
                        : $f['kg_actual'];
                }
                ?>
                <div class="grid grid-cols-3 gap-px bg-gray-100 dark:bg-gray-700">
                    <div class="bg-white dark:bg-gray-800 px-3 py-3 text-center">
                        <div class="text-base font-bold text-blue-600"><?= e(number_format($total_bags, 1)) ?></div>
                        <div class="text-xs text-gray-400">Tổng bao</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 px-3 py-3 text-center">
                        <div class="text-base font-bold text-blue-600"><?= e(number_format($total_kg, 1)) ?></div>
                        <div class="text-xs text-gray-400">Tổng kg đổ</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 px-3 py-3 text-center">
                        <div class="text-base font-bold text-green-600"><?= e(number_format($total_consumed, 1)) ?></div>
                        <div class="text-xs text-gray-400">Gà thực ăn</div>
                    </div>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700 max-h-96 overflow-y-auto">
                    <?php foreach (array_reverse($care_feeds) as $f): ?>
                    <?php
                        $pct      = isset($f['latest_remaining_pct']) && $f['latest_remaining_pct'] !== null ? (int)$f['latest_remaining_pct'] : null;
                        $consumed = $pct !== null
                            ? round($f['kg_actual'] * (1 - $pct / 100), 1)
                            : $f['kg_actual'];
                    ?>
                    <div class="px-4 py-3">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="text-sm font-medium"><?= e($f['brand_name']) ?> · <?= e($f['feed_code']) ?></div>
                                <div class="text-xs text-gray-400 mt-0.5"><?= e($f['recorded_at']) ?></div>
                                <?php if ($f['note']): ?>
                                <div class="text-xs text-gray-400"><?= e($f['note']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-semibold"><?= e($f['bags']) ?> bao · <?= e(number_format($f['kg_actual'],1)) ?>kg</div>
                                <?php if ($pct !== null && $pct > 0): ?>
                                    <div class="text-xs mt-0.5">
                                        <span class="text-orange-500">còn <?= e($pct) ?>%</span>
                                        <span class="text-gray-300 mx-1">·</span>
                                        <span class="text-green-600">ăn ≈<?= e(number_format($consumed,1)) ?>kg</span>
                                    </div>
                                <?php elseif ($pct === 0): ?>
                                    <div class="text-xs text-green-600 mt-0.5">ăn hết máng ✓</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: Cân gà -->
    <div id="tab_weight" class="hidden">

        <!-- Chart tăng trưởng -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <div class="text-sm font-semibold">📈 Tăng trưởng</div>
                    <div class="text-xs text-gray-400">Trọng lượng trung bình theo ngày tuổi</div>
                </div>
                <button onclick="expandWeightChart()"
                        class="text-xs text-purple-500 hover:underline px-2 py-1 rounded-lg border border-purple-200">
                    ⛶ Phóng to
                </button>
            </div>
            <div style="position:relative;height:180px;">
                <canvas id="weightChart"></canvas>
            </div>
        </div>

        <!-- MODAL chart lớn -->
        <div id="weight_chart_modal" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-900 rounded-2xl w-full max-w-3xl p-5">
                <div class="flex justify-between items-center mb-4">
                    <div class="text-sm font-semibold">⚖️ Tăng trưởng — <?= e($cycle->code) ?></div>
                    <button onclick="document.getElementById('weight_chart_modal').classList.add('hidden')"
                            class="text-gray-400 text-2xl leading-none">×</button>
                </div>
                <div style="position:relative;height:400px;">
                    <canvas id="weightChartLarge"></canvas>
                </div>
                <div class="mt-3 flex gap-4 text-xs text-gray-500 justify-center">
                    <span><span class="inline-block w-3 h-0.5 bg-purple-500 align-middle mr-1"></span>Avg g/con</span>
                    <span><span class="inline-block w-3 h-0.5 bg-blue-400 align-middle mr-1 border-dashed"></span>Chuẩn giống</span>
                </div>
            </div>
        </div>

        <!-- Danh sách buổi cân -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="flex justify-between items-center px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                <div class="text-sm font-semibold">Lịch sử cân</div>
                <div class="text-xs text-gray-400"><?= count($weight_sessions) ?> buổi</div>
            </div>
            <?php if (empty($weight_sessions)): ?>
            <div class="text-center py-10 text-gray-400 text-sm">Chưa có dữ liệu cân</div>
            <?php else: ?>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php foreach (array_reverse($weight_sessions) as $ws): ?>
                <div class="px-4 py-3">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-sm font-medium">
                                Ngày tuổi <?= e($ws['day_age']) ?>
                                <span class="text-xs text-gray-400 ml-1"><?= date('d/m H:i', strtotime($ws['weighed_at'])) ?></span>
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">
                                <?= e($ws['sample_count']) ?> con mẫu
                                <?php if ($ws['note']): ?>· <?= e($ws['note']) ?><?php endif; ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="text-right">
                                <div class="text-sm font-bold text-purple-700 dark:text-purple-300">
                                    <?= e(number_format((float)$ws['avg_weight_g'])) ?>g
                                </div>
                                <div class="text-xs text-gray-400">
                                    <?= e(number_format((float)$ws['avg_weight_g']/1000, 3)) ?> kg/con
                                </div>
                            </div>
                            <div class="flex flex-col gap-1">
                                <button onclick="editWeightSession(<?= e($ws['id']) ?>, <?= e($ws['day_age']) ?>, '<?= e($ws['weighed_at']) ?>', '<?= e($ws['note'] ?? '') ?>')"
                                        class="p-1.5 text-blue-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg">✏️</button>
                                <button onclick="deleteWeightSession(<?= e($ws['id']) ?>, this)"
                                        class="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg">🗑️</button>
                            </div>
                        </div>
                    </div>
                    <!-- Edit form inline -->
                    <div id="ws_edit_<?= e($ws['id']) ?>" class="hidden mt-3">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-3 space-y-2">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-medium mb-1">Ngày tuổi</label>
                                    <input type="number" id="ws_day_<?= e($ws['id']) ?>"
                                           value="<?= e($ws['day_age']) ?>" min="1"
                                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">Thời gian</label>
                                    <input type="datetime-local" id="ws_time_<?= e($ws['id']) ?>"
                                           value="<?= e(date('Y-m-d\TH:i', strtotime($ws['weighed_at']))) ?>"
                                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1">Ghi chú</label>
                                <input type="text" id="ws_note_<?= e($ws['id']) ?>"
                                       value="<?= e($ws['note'] ?? '') ?>"
                                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                            <div class="flex gap-2">
                                <button onclick="saveWeightSession(<?= e($ws['id']) ?>)"
                                        class="flex-1 bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 rounded-xl text-sm">Lưu</button>
                                <button onclick="document.getElementById('ws_edit_<?= e($ws['id']) ?>').classList.add('hidden')"
                                        class="px-4 py-2 border border-gray-300 rounded-xl text-sm text-gray-500">Hủy</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: Thuốc -->
    <div id="tab_medication" class="hidden">

        <!-- Chart thuốc -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
            <div class="text-sm font-semibold mb-3">📊 Tần suất dùng thuốc</div>
            <div style="position:relative;height:160px;">
                <canvas id="medChart"></canvas>
            </div>
        </div>


        <!-- Chart thuốc -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
            <div class="text-sm font-semibold mb-3">📊 Tần suất dùng thuốc</div>
            <div style="position:relative;height:160px;">
                <canvas id="medChart"></canvas>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="flex justify-between items-center px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                <div class="text-sm font-semibold">Lịch sử dùng thuốc</div>
                <div class="text-xs text-gray-400"><?= count($care_medications) ?> bản ghi</div>
            </div>
            <?php if (empty($care_medications)): ?>
                <div class="text-center py-10 text-gray-400 text-sm">Chưa có dữ liệu</div>
            <?php else: ?>
                <div class="divide-y divide-gray-100 dark:divide-gray-700 max-h-96 overflow-y-auto">
                    <?php foreach (array_reverse($care_medications) as $m): ?>
                    <div class="px-4 py-3">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="text-sm font-medium"><?= e($m['medication_name']) ?></div>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    <?= e($method_labels[$m['method']] ?? $m['method']) ?> · <?= e($m['recorded_at']) ?>
                                </div>
                                <?php if ($m['note']): ?>
                                <div class="text-xs text-gray-400"><?= e($m['note']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="text-sm font-semibold"><?= e($m['dosage']) ?> <?= e($m['unit']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: Hao hụt -->
    <div id="tab_death" class="hidden">

        <!-- Chart hao hụt -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
            <div class="text-sm font-semibold mb-3">📉 Hao hụt theo ngày</div>
            <div style="position:relative;height:160px;">
                <canvas id="deathChart"></canvas>
            </div>
        </div>


        <!-- Chart hao hụt -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
            <div class="text-sm font-semibold mb-3">📉 Hao hụt theo ngày</div>
            <div style="position:relative;height:160px;">
                <canvas id="deathChart"></canvas>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="flex justify-between items-center px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                <div class="text-sm font-semibold">Lịch sử hao hụt</div>
                <div class="text-xs text-gray-400"><?= count($care_deaths) ?> bản ghi</div>
            </div>
            <?php if (empty($care_deaths)): ?>
                <div class="text-center py-10 text-gray-400 text-sm">Chưa có dữ liệu</div>
            <?php else: ?>
                <?php $total_deaths = array_sum(array_column($care_deaths, 'quantity')); ?>
                <div class="bg-red-50 dark:bg-red-900/20 px-4 py-3 text-center border-b border-gray-100 dark:border-gray-700">
                    <div class="text-xl font-bold text-red-600"><?= e(number_format($total_deaths)) ?> con</div>
                    <div class="text-xs text-gray-400">Tổng hao hụt toàn cycle</div>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700 max-h-96 overflow-y-auto">
                    <?php foreach (array_reverse($care_deaths) as $d): ?>
                    <div class="px-4 py-3">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="text-sm font-medium"><?= e($d['reason'] ?? 'Không rõ lý do') ?></div>
                                <?php if ($d['symptoms']): ?>
                                <div class="text-xs text-gray-400 mt-0.5"><?= e($d['symptoms']) ?></div>
                                <?php endif; ?>
                                <div class="text-xs text-gray-400 mt-0.5"><?= e($d['recorded_at']) ?></div>
                            </div>
                            <div class="text-sm font-bold text-red-600">-<?= e(number_format($d['quantity'])) ?> con</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: Bán gà -->
    <div id="tab_sale" class="hidden">

        <!-- Chart bán gà -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
            <div class="text-sm font-semibold mb-3">💰 Doanh thu theo đợt bán</div>
            <div style="position:relative;height:160px;">
                <canvas id="saleChart"></canvas>
            </div>
        </div>


        <!-- Chart bán gà -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
            <div class="text-sm font-semibold mb-3">💰 Doanh thu theo đợt bán</div>
            <div style="position:relative;height:160px;">
                <canvas id="saleChart"></canvas>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="flex justify-between items-center px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                <div class="text-sm font-semibold">Lịch sử bán gà</div>
                <div class="text-xs text-gray-400"><?= count($care_sales) ?> bản ghi</div>
            </div>
            <?php if (empty($care_sales)): ?>
                <div class="text-center py-10 text-gray-400 text-sm">Chưa có dữ liệu</div>
            <?php else: ?>
                <?php
                $total_weight  = array_sum(array_column($care_sales, 'weight_kg'));
                $total_revenue = array_sum(array_column($care_sales, 'total_amount'));
                ?>
                <div class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-700">
                    <div class="bg-white dark:bg-gray-800 px-4 py-3 text-center">
                        <div class="text-base font-bold text-green-600"><?= e(number_format($total_weight, 1)) ?> kg</div>
                        <div class="text-xs text-gray-400">Tổng cân</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 px-4 py-3 text-center">
                        <div class="text-base font-bold text-green-600"><?= e(number_format($total_revenue)) ?>đ</div>
                        <div class="text-xs text-gray-400">Tổng doanh thu</div>
                    </div>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700 max-h-96 overflow-y-auto">
                    <?php foreach (array_reverse($care_sales) as $s): ?>
                    <div class="px-4 py-3">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="text-sm font-medium">
                                    <?= e(number_format($s['weight_kg'],1)) ?>kg
                                    · <?= e(number_format($s['price_per_kg'])) ?>đ/kg
                                    <?php if ($s['gender']): ?>
                                    · <?= e($gender_labels[$s['gender']] ?? '') ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($s['quantity']): ?>
                                <div class="text-xs text-gray-400 mt-0.5"><?= e(number_format($s['quantity'])) ?> con</div>
                                <?php endif; ?>
                                <div class="text-xs text-gray-400"><?= e($s['recorded_at']) ?></div>
                            </div>
                            <div class="text-sm font-bold text-green-600"><?= e(number_format($s['total_amount'])) ?>đ</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    </div>



    <!-- TAB: Sức khỏe -->
    <div id="tab_health" class="hidden px-4 py-4" id="health">
        <!-- Form thêm ghi chú -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
            <div class="text-sm font-semibold mb-3">➕ Ghi chú sức khỏe</div>
            <form method="POST" action="/health/store" enctype="multipart/form-data" class="space-y-3">
                <input type="hidden" name="cycle_id" value="<?= e($cycle->id) ?>">
                <input type="hidden" name="recorded_at" value="<?= date('Y-m-d H:i:s') ?>">
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Triệu chứng / Quan sát</label>
                    <textarea name="symptoms" rows="3" required
                              placeholder="Mô tả triệu chứng, hành vi bất thường..."
                              class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block">Mức độ</label>
                        <select name="severity" class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="mild">🟡 Nhẹ</option>
                            <option value="moderate">🟠 Trung bình</option>
                            <option value="severe">🔴 Nặng</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block">Ảnh (tùy chọn)</label>
                        <input type="file" name="image" accept="image/*" capture="environment"
                               class="w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-xs file:bg-blue-50 file:text-blue-700">
                    </div>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white text-sm font-semibold py-2.5 rounded-xl">
                    💾 Lưu ghi chú
                </button>
            </form>
        </div>

        <!-- Danh sách ghi chú -->
        <?php if (empty($health_notes)): ?>
        <div class="text-center py-8 text-gray-400 text-sm">Chưa có ghi chú sức khỏe nào</div>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($health_notes as $hn): ?>
            <?php
            $sev_color = ['mild' => 'border-yellow-300 bg-yellow-50 dark:bg-yellow-900/10',
                          'moderate' => 'border-orange-300 bg-orange-50 dark:bg-orange-900/10',
                          'severe'   => 'border-red-300 bg-red-50 dark:bg-red-900/10'][$hn['severity']] ?? '';
            $sev_icon  = ['mild' => '🟡', 'moderate' => '🟠', 'severe' => '🔴'][$hn['severity']] ?? '⚪';
            $sev_label = ['mild' => 'Nhẹ', 'moderate' => 'Trung bình', 'severe' => 'Nặng'][$hn['severity']] ?? '';
            ?>
            <div class="rounded-2xl border <?= $sev_color ?> p-4">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex items-center gap-2">
                        <span><?= $sev_icon ?></span>
                        <span class="text-xs font-semibold"><?= $sev_label ?></span>
                        <span class="text-xs text-gray-400">· Ngày <?= e($hn['day_age']) ?></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if (!$hn['resolved']): ?>
                        <form method="POST" action="/health/<?= $hn['id'] ?>/resolve" class="inline">
                            <button class="text-xs text-green-600 hover:underline">✅ Đã xử lý</button>
                        </form>
                        <?php else: ?>
                        <span class="text-xs text-green-600">✅ Đã xử lý</span>
                        <?php endif; ?>
                        <form method="POST" action="/health/<?= $hn['id'] ?>/delete" class="inline"
                              onsubmit="return confirm('Xóa ghi chú này?')">
                            <button class="text-xs text-red-400 hover:underline">Xóa</button>
                        </form>
                    </div>
                </div>
                <p class="text-sm"><?= nl2br(e($hn['symptoms'])) ?></p>
                <?php if ($hn['image_path']): ?>
                <img src="<?= e($hn['image_path']) ?>" alt="Ảnh sức khỏe"
                     class="mt-2 rounded-xl max-h-48 w-full object-cover cursor-pointer"
                     onclick="this.classList.toggle('max-h-48')">
                <?php endif; ?>
                <div class="text-xs text-gray-400 mt-2"><?= date('d/m/Y H:i', strtotime($hn['recorded_at'])) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB: Vaccine -->
    <div id="tab_vaccine" class="hidden px-4 py-4" id="vaccine">

        <?php if (isset($_GET['msg'])): ?>
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 rounded-xl px-4 py-3 mb-4 text-sm text-green-700">
            <?= e($_GET['msg']) ?>
        </div>
        <?php endif; ?>

        <!-- Chọn bộ lịch vaccine -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
            <div class="text-sm font-semibold mb-3">📋 Bộ lịch vaccine</div>
            <form method="POST" action="/cycles/<?= e($cycle->id) ?>/apply-vaccine-program" class="flex gap-2">
                <select name="vaccine_program_id"
                        class="flex-1 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Không dùng bộ lịch --</option>
                    <?php foreach ($vaccine_programs as $vp): ?>
                    <option value="<?= $vp['id'] ?>"
                            <?= $cycle->vaccine_program_id == $vp['id'] ? 'selected' : '' ?>>
                        <?= e($vp['name']) ?> (<?= $vp['item_count'] ?> vaccine)
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"
                        class="bg-blue-600 text-white text-sm font-semibold px-4 py-2.5 rounded-xl whitespace-nowrap">
                    Áp dụng
                </button>
            </form>
            <?php if ($cycle->vaccine_program_id): ?>
            <div class="text-xs text-gray-400 mt-2">
                ⚠️ Áp dụng lại sẽ tạo lại toàn bộ lịch chưa tiêm
            </div>
            <?php endif; ?>
        </div>
        <!-- Form thêm lịch vaccine -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
            <div class="text-sm font-semibold mb-3">➕ Thêm lịch vaccine</div>
            <form method="POST" action="/vaccine/store" class="space-y-3">
                <input type="hidden" name="cycle_id" value="<?= e($cycle->id) ?>">
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Tên vaccine</label>
                    <input type="text" name="vaccine_name" required placeholder="VD: Newcastle, Gumboro..."
                           class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block">Ngày tiêm</label>
                        <input type="date" name="scheduled_date" required value="<?= date('Y-m-d') ?>"
                               class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block">Phương pháp</label>
                        <select name="method" class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="drink">💧 Uống nước</option>
                            <option value="inject">💉 Tiêm</option>
                            <option value="eye_drop">👁️ Nhỏ mắt</option>
                            <option value="spray">🌫️ Phun sương</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block">Liều lượng</label>
                        <input type="text" name="dosage" placeholder="VD: 1 liều/con"
                               class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block">Nhắc trước (ngày)</label>
                        <input type="number" name="remind_days" value="1" min="0" max="7"
                               class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Ghi chú (tùy chọn)</label>
                    <input type="text" name="notes" placeholder="Thông tin thêm..."
                           class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white text-sm font-semibold py-2.5 rounded-xl">
                    💾 Lưu lịch vaccine
                </button>
            </form>
        </div>

        <!-- Danh sách vaccine -->
        <?php if (empty($vaccine_schedules)): ?>
        <div class="text-center py-8 text-gray-400 text-sm">Chưa có lịch vaccine nào</div>
        <?php else: ?>
        <?php
        $method_labels = ['drink' => '💧 Uống', 'inject' => '💉 Tiêm', 'eye_drop' => '👁️ Nhỏ mắt', 'spray' => '🌫️ Phun'];
        $upcoming = array_filter($vaccine_schedules, fn($v) => !$v['done']);
        $done     = array_filter($vaccine_schedules, fn($v) =>  $v['done']);
        ?>
        <?php if (!empty($upcoming)): ?>
        <div class="text-xs font-semibold text-gray-500 mb-2">📅 Sắp tiêm</div>
        <div class="space-y-2 mb-4">
            <?php foreach ($upcoming as $vac): ?>
            <?php
            $days_left = (int)((strtotime($vac['scheduled_date']) - strtotime('today')) / 86400);
            $urgent    = $days_left <= $vac['remind_days'];
            $overdue   = $days_left < 0;
            ?>
            <div class="bg-white dark:bg-gray-800 rounded-2xl border <?= $overdue ? 'border-red-300' : ($urgent ? 'border-orange-300' : 'border-gray-100 dark:border-gray-700') ?> p-4">
                <div class="flex justify-between items-start mb-1">
                    <div class="font-semibold text-sm"><?= e($vac['vaccine_name']) ?></div>
                    <div class="flex gap-2 items-center">
                        <form method="POST" action="/vaccine/<?= $vac['id'] ?>/done" class="inline">
                            <button class="text-xs text-green-600 hover:underline">✅ Đã tiêm</button>
                        </form>
                        <form method="POST" action="/vaccine/<?= $vac['id'] ?>/delete" class="inline"
                              onsubmit="return confirm('Xóa lịch này?')">
                            <button class="text-xs text-red-400 hover:underline">Xóa</button>
                        </form>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 text-xs text-gray-500">
                    <span>📅 <?= date('d/m/Y', strtotime($vac['scheduled_date'])) ?></span>
                    <span>· Ngày <?= e($vac['day_age_target']) ?></span>
                    <span>· <?= $method_labels[$vac['method']] ?? $vac['method'] ?></span>
                    <?php if ($vac['dosage']): ?><span>· <?= e($vac['dosage']) ?></span><?php endif; ?>
                </div>
                <?php if ($overdue): ?>
                <div class="text-xs text-red-500 mt-1">⚠️ Đã quá hạn <?= abs($days_left) ?> ngày</div>
                <?php elseif ($urgent): ?>
                <div class="text-xs text-orange-500 mt-1">⏰ Còn <?= $days_left ?> ngày</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($done)): ?>
        <div class="text-xs font-semibold text-gray-400 mb-2">✅ Đã tiêm</div>
        <div class="space-y-2">
            <?php foreach ($done as $vac): ?>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-xl border border-gray-100 dark:border-gray-600 px-4 py-3 opacity-60">
                <div class="flex justify-between">
                    <span class="text-sm font-medium"><?= e($vac['vaccine_name']) ?></span>
                    <span class="text-xs text-gray-400"><?= date('d/m/Y', strtotime($vac['scheduled_date'])) ?></span>
                </div>
                <div class="text-xs text-gray-400"><?= $method_labels[$vac['method']] ?? '' ?><?= $vac['dosage'] ? ' · ' . e($vac['dosage']) : '' ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>


    <!-- TAB: Chi phí -->
    <div id="tab_expense" class="hidden px-4 py-4">
        <?php
        $cat_labels = [
            'electricity' => '⚡ Điện',
            'labor'       => '👷 Nhân công',
            'repair'      => '🔧 Sửa chữa',
            'other'       => '📦 Khác',
        ];
        $total_expense = array_sum(array_column($expenses, 'amount'));
        ?>

        <!-- Tổng chi phí -->
        <?php if (!empty($expenses)): ?>
        <div class="bg-red-50 dark:bg-red-900/20 rounded-2xl p-4 mb-4 flex justify-between items-center">
            <div class="text-sm text-red-700 dark:text-red-300 font-medium">Tổng chi phí khác</div>
            <div class="text-lg font-bold text-red-700 dark:text-red-300">
                <?= number_format($total_expense) ?> đ
            </div>
        </div>
        <?php endif; ?>

        <!-- Form thêm -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
            <div class="text-sm font-semibold mb-3">➕ Ghi chi phí</div>
            <form method="POST" action="/expenses/store" class="space-y-3">
                <input type="hidden" name="cycle_id" value="<?= e($cycle->id) ?>">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block">Loại</label>
                        <select name="category" id="expense_category"
                                onchange="updateLabel(this)"
                                class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="electricity">⚡ Điện</option>
                            <option value="labor">👷 Nhân công</option>
                            <option value="repair">🔧 Sửa chữa</option>
                            <option value="other">📦 Khác</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block">Ngày</label>
                        <input type="date" name="recorded_at" value="<?= date('Y-m-d') ?>"
                               class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Tên chi phí</label>
                    <input type="text" name="label" id="expense_label" required
                           placeholder="VD: Tiền điện tháng 3..."
                           class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Số tiền (đ)</label>
                    <input type="text" name="amount" required placeholder="VD: 500000"
                           inputmode="numeric"
                           class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Ghi chú (tùy chọn)</label>
                    <input type="text" name="note" placeholder="..."
                           class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit"
                        class="w-full bg-blue-600 text-white text-sm font-semibold py-2.5 rounded-xl">
                    💾 Lưu chi phí
                </button>
            </form>
        </div>

        <!-- Danh sách -->
        <?php if (empty($expenses)): ?>
        <div class="text-center py-8 text-gray-400 text-sm">Chưa có chi phí nào</div>
        <?php else: ?>
        <?php
        // Group by category
        $by_cat = [];
        foreach ($expenses as $ex) {
            $by_cat[$ex['category']][] = $ex;
        }
        ?>
        <div class="space-y-2">
            <?php foreach ($expenses as $ex): ?>
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 px-4 py-3">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded-full">
                                <?= $cat_labels[$ex['category']] ?? $ex['category'] ?>
                            </span>
                            <span class="text-xs text-gray-400"><?= date('d/m/Y', strtotime($ex['recorded_at'])) ?></span>
                        </div>
                        <div class="text-sm font-medium mt-1"><?= e($ex['label']) ?></div>
                        <?php if ($ex['note']): ?>
                        <div class="text-xs text-gray-400"><?= e($ex['note']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-3 ml-3">
                        <div class="text-sm font-bold text-red-600"><?= number_format($ex['amount']) ?>đ</div>
                        <form method="POST" action="/expenses/<?= $ex['id'] ?>/delete"
                              onsubmit="return confirm('Xóa chi phí này?')">
                            <input type="hidden" name="redirect" value="/cycles/<?= e($cycle->id) ?>?tab=expense#expense">
                            <button class="text-red-400 text-xs hover:underline">Xóa</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Tổng theo loại -->
        <div class="mt-4 bg-gray-50 dark:bg-gray-700 rounded-2xl p-4">
            <div class="text-xs font-semibold text-gray-500 mb-3">Tổng theo loại</div>
            <?php foreach ($by_cat as $cat => $items): ?>
            <div class="flex justify-between text-sm py-1">
                <span><?= $cat_labels[$cat] ?? $cat ?></span>
                <span class="font-semibold"><?= number_format(array_sum(array_column($items, 'amount'))) ?>đ</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <script>
        function updateLabel(sel) {
            const labels = {
                electricity: 'Tiền điện',
                labor: 'Tiền nhân công',
                repair: 'Chi phí sửa chữa',
                other: ''
            };
            const lbl = document.getElementById('expense_label');
            if (labels[sel.value]) lbl.value = labels[sel.value];
            else lbl.value = '';
            lbl.focus();
        }
        </script>
    </div>

<script>
function switchTab(key, btn) {
    document.querySelectorAll('[id^="tab_"]').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(t => {
        t.classList.remove('text-blue-600','border-blue-600','font-semibold');
        t.classList.add('text-gray-400','border-transparent');
    });
    document.getElementById('tab_' + key).classList.remove('hidden');
    btn.classList.add('text-blue-600','border-blue-600','font-semibold');
    btn.classList.remove('text-gray-400','border-transparent');
    if (key === 'feed') setTimeout(loadChart, 100);
}
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>


<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function() {
    const CYCLE_ID = <?= e($cycle->id) ?>;
    let chart_mini  = null;
    let chart_large = null;
    let chart_data  = null;

    function buildDatasets(data) {
        const feed_days  = data.feed_by_day.map(r => r.day_age);
        const feed_kg    = data.feed_by_day.map(r => parseFloat(r.total_kg));
        const trough_days = data.trough_checks.map(r => r.day_age);
        const trough_pct  = data.trough_checks.map(r => r.remaining_pct);

        return {
            labels_feed:   feed_days,
            labels_trough: trough_days,
            ds_feed: {
                label: 'Kg đổ vào/ngày',
                data: feed_days.map((d,i) => ({x: d, y: feed_kg[i]})),
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59,130,246,0.08)',
                fill: true,
                tension: 0.3,
                pointRadius: 3,
                yAxisID: 'y',
            },
            ds_trough: {
                label: '% còn lại máng',
                data: trough_days.map((d,i) => ({x: d, y: trough_pct[i]})),
                borderColor: '#f97316',
                backgroundColor: 'rgba(249,115,22,0.08)',
                fill: false,
                tension: 0,
                pointRadius: 4,
                pointStyle: 'circle',
                yAxisID: 'y2',
            }
        };
    }

    function makeConfig(ds, small) {
        return {
            type: 'line',
            data: { datasets: [ds.ds_feed, ds.ds_trough] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: !small, position: 'top', labels: { font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            title: ctx => 'Ngày tuổi ' + ctx[0].parsed.x,
                            label: ctx => ctx.dataset.label + ': ' +
                                (ctx.dataset.yAxisID === 'y2'
                                    ? ctx.parsed.y + '%'
                                    : ctx.parsed.y.toFixed(1) + ' kg')
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'linear',
                        title: { display: !small, text: 'Ngày tuổi', font: { size: 11 } },
                        ticks: { font: { size: small ? 9 : 11 } }
                    },
                    y: {
                        position: 'left',
                        title: { display: !small, text: 'Kg', font: { size: 11 } },
                        ticks: { font: { size: small ? 9 : 11 } },
                        beginAtZero: true,
                    },
                    y2: {
                        position: 'right',
                        min: 0, max: 100,
                        title: { display: !small, text: '% còn lại', font: { size: 11 } },
                        ticks: {
                            font: { size: small ? 9 : 11 },
                            callback: v => v + '%'
                        },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        };
    }

    window.loadChart = async function loadChart() {
        const res  = await fetch(`/cycles/${CYCLE_ID}/feed-chart-data`);
        chart_data = await res.json();
        if (!chart_data.ok || !chart_data.feed_by_day.length) return;

        const ds = buildDatasets(chart_data);

        // mini chart
        const ctx = document.getElementById('feedChart');
        if (ctx) {
            if (chart_mini) chart_mini.destroy();
            chart_mini = new Chart(ctx, makeConfig(ds, true));
        }
    }

    window.expandChart = function() {
        document.getElementById('chart_modal').classList.remove('hidden');
        if (!chart_data || !chart_data.feed_by_day.length) return;
        const ds = buildDatasets(chart_data);
        setTimeout(() => {
            const ctx2 = document.getElementById('feedChartLarge');
            if (chart_large) chart_large.destroy();
            chart_large = new Chart(ctx2, makeConfig(ds, false));
        }, 50);
    };

    // Load khi tab feed được mở
    document.addEventListener('DOMContentLoaded', () => {
        // Nếu tab feed đang active thì load ngay
        const tab_feed = document.getElementById('tab_feed');
        if (tab_feed && !tab_feed.classList.contains('hidden')) {
            loadChart();
        }
        // Hook vào tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.dataset.tab === 'feed' && !chart_mini) {
                    setTimeout(loadChart, 100);
                }
            });
        });
    });
})();

// ================================================================
// WEIGHT CHART
// ================================================================
(function() {
    const CYCLE_ID_W = <?= e($cycle->id) ?>;
    let wchart_mini  = null;
    let wchart_large = null;
    let wdata        = null;

    const BREED_STANDARD = {
        1:50,3:100,5:170,7:250,10:400,14:600,17:800,
        21:1000,24:1200,28:1500,35:2000,42:2500
    };

    function buildWeightConfig(sessions, small) {
        const labels = sessions.map(s => parseInt(s.day_age));
        const avgs   = sessions.map(s => parseFloat(s.avg_weight_g));
        const maxDay = Math.max(...labels, 7);
        const stdDays = Object.keys(BREED_STANDARD).map(Number).filter(d => d <= maxDay);
        const stdVals = stdDays.map(d => BREED_STANDARD[d]);

        return {
            type: 'line',
            data: {
                datasets: [
                    {
                        label: 'Avg g/con',
                        data: labels.map((d,i) => ({x:d, y:avgs[i]})),
                        borderColor: '#9333ea',
                        backgroundColor: 'rgba(147,51,234,0.08)',
                        fill: true, tension: 0.3, pointRadius: 4,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Chuẩn giống',
                        data: stdDays.map((d,i) => ({x:d, y:stdVals[i]})),
                        borderColor: '#93c5fd',
                        borderDash: [5,5],
                        fill: false, tension: 0.3, pointRadius: 0,
                        yAxisID: 'y',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: !small, position: 'top', labels: { font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            title: ctx => 'Ngày tuổi ' + ctx[0].parsed.x,
                            label: ctx => ctx.dataset.label + ': ' + Math.round(ctx.parsed.y).toLocaleString('vi-VN') + 'g'
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'linear',
                        title: { display: !small, text: 'Ngày tuổi', font: { size: 11 } },
                        ticks: { font: { size: small ? 9 : 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        title: { display: !small, text: 'gram/con', font: { size: 11 } },
                        ticks: { font: { size: small ? 9 : 11 }, callback: v => v.toLocaleString('vi-VN') + 'g' }
                    }
                }
            }
        };
    }

    async function loadWeightChart() {
        if (wdata) { renderWeightMini(); return; }
        const res = await fetch(`/weight/cycle/${CYCLE_ID_W}/chart-data`);
        wdata = await res.json();
        renderWeightMini();
    }

    function renderWeightMini() {
        const ctx = document.getElementById('weightChart');
        if (!ctx || !wdata || !wdata.sessions.length) return;
        if (wchart_mini) wchart_mini.destroy();
        wchart_mini = new Chart(ctx, buildWeightConfig(wdata.sessions, true));
    }

    window.expandWeightChart = function() {
        document.getElementById('weight_chart_modal').classList.remove('hidden');
        if (!wdata || !wdata.sessions.length) return;
        setTimeout(() => {
            const ctx2 = document.getElementById('weightChartLarge');
            if (wchart_large) wchart_large.destroy();
            wchart_large = new Chart(ctx2, buildWeightConfig(wdata.sessions, false));
        }, 50);
    };

    // Hook vào switchTab
    const _orig = window.switchTab;
    window.switchTab = function(key, btn) {
        _orig(key, btn);
        if (key === 'weight') setTimeout(loadWeightChart, 100);
    };
})();


// ================================================================
// CHARTS: Thuốc / Hao hụt / Bán gà
// ================================================================
(function() {
    // Data từ PHP
    const med_data = <?php
        $med_by_day = [];
        foreach ($care_medications as $m) {
            $day = (int)((strtotime($m['recorded_at']) - strtotime($cycle->start_date)) / 86400) + 1;
            $med_by_day[$day] = ($med_by_day[$day] ?? 0) + 1;
        }
        ksort($med_by_day);
        echo json_encode(array_map(fn($d,$c) => ['day'=>$d,'count'=>$c],
            array_keys($med_by_day), array_values($med_by_day)));
    ?>;

    const death_data = <?php
        $death_by_day = [];
        foreach ($care_deaths as $d) {
            $day = (int)((strtotime($d['recorded_at']) - strtotime($cycle->start_date)) / 86400) + 1;
            $death_by_day[$day] = ($death_by_day[$day] ?? 0) + $d['quantity'];
        }
        ksort($death_by_day);
        echo json_encode(array_map(fn($d,$c) => ['day'=>$d,'count'=>$c],
            array_keys($death_by_day), array_values($death_by_day)));
    ?>;

    const sale_data = <?php
        echo json_encode(array_map(fn($s) => [
            'day'     => (int)((strtotime($s['recorded_at']) - strtotime($cycle->start_date)) / 86400) + 1,
            'revenue' => (float)$s['total_amount'],
            'kg'      => (float)$s['weight_kg'],
        ], $care_sales));
    ?>;

    function makeBarConfig(labels, values, color, label, yFmt) {
        return {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label,
                    data: values,
                    backgroundColor: color,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: ctx => 'Ngày tuổi ' + ctx[0].label,
                            label: ctx => label + ': ' + yFmt(ctx.parsed.y)
                        }
                    }
                },
                scales: {
                    x: { ticks: { font: { size: 10 } }, title: { display: true, text: 'Ngày tuổi', font: { size: 10 } } },
                    y: { beginAtZero: true, ticks: { font: { size: 10 }, callback: yFmt } }
                }
            }
        };
    }

    // Hook switchTab
    const _orig2 = window.switchTab;
    let med_chart_inst = null, death_chart_inst = null, sale_chart_inst = null;

    window.switchTab = function(key, btn) {
        _orig2(key, btn);

        if (key === 'medication' && !med_chart_inst) {
            setTimeout(() => {
                const ctx = document.getElementById('medChart');
                if (!ctx || !med_data.length) return;
                med_chart_inst = new Chart(ctx, makeBarConfig(
                    med_data.map(r => r.day),
                    med_data.map(r => r.count),
                    'rgba(139,92,246,0.7)',
                    'Số lần dùng',
                    v => v + ' lần'
                ));
            }, 100);
        }

        if (key === 'death' && !death_chart_inst) {
            setTimeout(() => {
                const ctx = document.getElementById('deathChart');
                if (!ctx || !death_data.length) return;
                death_chart_inst = new Chart(ctx, makeBarConfig(
                    death_data.map(r => r.day),
                    death_data.map(r => r.count),
                    'rgba(239,68,68,0.7)',
                    'Số con hao hụt',
                    v => v + ' con'
                ));
            }, 100);
        }

        if (key === 'sale' && !sale_chart_inst) {
            setTimeout(() => {
                const ctx = document.getElementById('saleChart');
                if (!ctx || !sale_data.length) return;
                sale_chart_inst = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: sale_data.map(r => 'Ngày ' + r.day),
                        datasets: [
                            {
                                label: 'Doanh thu (đ)',
                                data: sale_data.map(r => r.revenue),
                                backgroundColor: 'rgba(34,197,94,0.7)',
                                borderRadius: 4,
                                yAxisID: 'y',
                            },
                            {
                                label: 'Kg bán',
                                data: sale_data.map(r => r.kg),
                                backgroundColor: 'rgba(59,130,246,0.5)',
                                borderRadius: 4,
                                yAxisID: 'y2',
                                type: 'line',
                                tension: 0.3,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: true, position: 'top', labels: { font: { size: 10 } } } },
                        scales: {
                            x: { ticks: { font: { size: 10 } } },
                            y:  { beginAtZero: true, ticks: { font: { size: 10 }, callback: v => (v/1000000).toFixed(1) + 'M' } },
                            y2: { position: 'right', beginAtZero: true, ticks: { font: { size: 10 }, callback: v => v + 'kg' }, grid: { drawOnChartArea: false } }
                        }
                    }
                });
            }, 100);
        }
    };
})();



// Weight session edit/delete
function editWeightSession(id, day_age, weighed_at, note) {
    document.getElementById('ws_edit_' + id).classList.toggle('hidden');
}

async function saveWeightSession(id) {
    const day   = document.getElementById('ws_day_' + id).value;
    const time  = document.getElementById('ws_time_' + id).value;
    const note  = document.getElementById('ws_note_' + id).value;
    const body  = new URLSearchParams({
        day_age:    day,
        weighed_at: time.replace('T', ' ') + ':00',
        note:       note,
    });
    const res  = await fetch(`/weight/session/${id}/update`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body,
    });
    const json = await res.json();
    if (json.ok) location.reload();
    else alert(json.message);
}

async function deleteWeightSession(id, btn) {
    if (!confirm('Xóa buổi cân này?')) return;
    const res  = await fetch(`/weight/session/${id}/delete`, { method: 'POST' });
    const json = await res.json();
    if (json.ok) location.reload();
    else alert(json.message);
}

</script>
