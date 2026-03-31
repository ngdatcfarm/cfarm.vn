<?php require view_path('layouts/main.php'); ?>

<div class="px-4 pt-4 pb-24">

    <!-- Header -->
    <div class="mb-5">
        <div class="text-xl font-bold">🏠 Tổng quan</div>
        <div class="text-xs text-gray-400"><?= date('d/m/Y') ?> · <?= $total_barns ?> chuồng · <?= count($active_cycles) ?> chu kỳ đang nuôi</div>
    </div>

    <!-- Test notification -->
    <div id="notif_test_bar" class="hidden mb-4 bg-blue-50 dark:bg-blue-900/20 rounded-2xl p-3 flex items-center justify-between">
        <div class="text-xs text-blue-700 dark:text-blue-300">Thông báo đã bật ✅</div>
        <button onclick="testNotification()"
                class="bg-blue-600 text-white text-xs font-semibold px-3 py-1.5 rounded-lg">
            Gửi test
        </button>
    </div>

    <!-- Shortcuts -->
    <div class="grid grid-cols-5 gap-2 mb-5">
        <a href="/events/create<?= !empty($active_cycles) ? '?cycle_id='.$active_cycles[0]['id'] : '' ?>"
           class="bg-blue-50 dark:bg-blue-900/20 rounded-2xl p-3 text-center active:scale-95 transition-transform">
            <div class="text-2xl mb-1">📋</div>
            <div class="text-xs font-semibold text-blue-700 dark:text-blue-300">Ghi chép</div>
        </a>
        <a href="/barns"
           class="bg-amber-50 dark:bg-amber-900/20 rounded-2xl p-3 text-center active:scale-95 transition-transform">
            <div class="text-2xl mb-1">🏠</div>
            <div class="text-xs font-semibold text-amber-700 dark:text-amber-300">Chuồng</div>
        </a>
        <a href="/reports"
           class="bg-purple-50 dark:bg-purple-900/20 rounded-2xl p-3 text-center active:scale-95 transition-transform">
            <div class="text-2xl mb-1">📊</div>
            <div class="text-xs font-semibold text-purple-700 dark:text-purple-300">Báo cáo</div>
        </a>
        <a href="/iot/control"
           class="bg-indigo-50 dark:bg-indigo-900/20 rounded-2xl p-3 text-center active:scale-95 transition-transform">
            <div class="text-2xl mb-1">🎛️</div>
            <div class="text-xs font-semibold text-indigo-700 dark:text-indigo-300">Bạt</div>
        </a>
        <a href="/env"
           class="bg-teal-50 dark:bg-teal-900/20 rounded-2xl p-3 text-center active:scale-95 transition-transform">
            <div class="text-2xl mb-1">🌡️</div>
            <div class="text-xs font-semibold text-teal-700 dark:text-teal-300">Môi trường</div>
        </a>
    </div>


    <!-- Daily Checklist -->
    <?php if (!empty($daily_checklist)): ?>
    <div class="mb-5">
        <div class="text-sm font-semibold mb-2">Hôm nay <?= date('d/m') ?></div>
        <div class="space-y-2">
        <?php foreach ($active_cycles as $c):
            $cid = (int)$c['id'];
            $cl = $daily_checklist[$cid] ?? null;
            if (!$cl) continue;
            $now_hour = (int)date('H');
            $items = [];

            // Sáng
            if ($now_hour >= 6) {
                $items[] = [
                    'done' => $cl['has_morning_feed'],
                    'label' => 'Cho ăn sáng',
                    'icon' => '🌾',
                ];
            }
            // Chiều
            if ($now_hour >= 12) {
                $items[] = [
                    'done' => $cl['has_evening_feed'],
                    'label' => 'Cho ăn chiều',
                    'icon' => '🌾',
                ];
            }
            // Kiểm máng
            if ($cl['trough_pending'] > 0) {
                $items[] = [
                    'done' => false,
                    'label' => $cl['trough_pending'] . ' bữa chưa kiểm máng',
                    'icon' => '🪣',
                ];
            }

            $done_count = count(array_filter($items, fn($i) => $i['done']));
            $total_count = count($items);
            $all_done = $total_count > 0 && $done_count === $total_count;
        ?>
        <a href="/events/create?cycle_id=<?= $cid ?>"
           class="block bg-white dark:bg-gray-800 rounded-2xl border <?= $all_done ? 'border-green-200 dark:border-green-800' : 'border-orange-200 dark:border-orange-800' ?> p-3 active:scale-[0.98] transition-transform">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-semibold"><?= e($c['barn_name']) ?> · <?= e($c['code']) ?></div>
                <?php if ($all_done): ?>
                <span class="text-xs text-green-600 font-semibold">Xong</span>
                <?php else: ?>
                <span class="text-xs text-orange-500 font-semibold"><?= $done_count ?>/<?= $total_count ?></span>
                <?php endif; ?>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-1">
                <?php foreach ($items as $item): ?>
                <div class="flex items-center gap-1 text-xs <?= $item['done'] ? 'text-green-600' : 'text-gray-400' ?>">
                    <span><?= $item['done'] ? '✅' : '⬜' ?></span>
                    <span><?= e($item['label']) ?></span>
                </div>
                <?php endforeach; ?>
                <?php if ($cl['death_count'] > 0): ?>
                <div class="flex items-center gap-1 text-xs text-red-500">
                    <span>💀</span><span><?= $cl['death_count'] ?> con chết</span>
                </div>
                <?php endif; ?>
                <?php if ($cl['med_count'] > 0): ?>
                <div class="flex items-center gap-1 text-xs text-blue-500">
                    <span>💊</span><span><?= $cl['med_count'] ?> lần thuốc</span>
                </div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Thông báo thiết bị IoT -->
    <?php if (!empty($device_notifications)): ?>
    <div class="mb-5">
        <div class="flex items-center justify-between mb-2">
            <div class="text-sm font-semibold">
                📳 Thông báo thiết bị
                <?php if ($notif_today > 0): ?>
                <span class="ml-1 bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full"><?= $notif_today ?></span>
                <?php endif; ?>
            </div>
            <a href="/notifications" class="text-xs text-blue-500 hover:underline font-semibold">Xem tất cả →</a>
        </div>
        <div class="space-y-2">
        <?php foreach ($device_notifications as $n): ?>
        <a href="/iot/devices"
           class="flex items-center gap-3 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-3 active:scale-[0.98] transition-transform">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-lg shrink-0
                        <?= $n->type === 'device_offline' ? 'bg-red-100 dark:bg-red-900/30' : 'bg-blue-100 dark:bg-blue-900/30' ?>">
                <?= $n->type === 'device_offline' ? '⚠️' : '📳' ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-xs font-semibold truncate"><?= e($n->title) ?></div>
                <div class="text-xs text-gray-400 truncate"><?= e($n->body) ?></div>
            </div>
            <div class="text-xs text-gray-300 shrink-0"><?= date('H:i', strtotime($n->sent_at)) ?></div>
        </a>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

   <!-- Cảnh báo -->
<?php if (!empty($alerts)): ?>
<div class="mb-5" x-data="{ open:false }">

    <!-- Header -->
    <div class="flex items-center justify-between cursor-pointer select-none"
        @click="open = !open">

        <div class="text-sm font-semibold flex items-center gap-2">
            🚨 <span>Cảnh báo</span>
        </div>

        <svg class="w-4 h-4 text-gray-400 transition-transform duration-200"
            :class="open ? 'rotate-90' : ''"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24">
            <path stroke-linecap="round"
                stroke-linejoin="round"
                d="M9 5l7 7-7 7"/>
        </svg>

    </div>

    <!-- Nội dung -->
    <div class="space-y-2 mt-3" x-show="open" x-transition>

        <?php foreach ($alerts as $alert): ?>
        <?php
        $colors = [
            'danger'  => 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800',
            'warning' => 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800',
            'orange'  => 'bg-orange-50 dark:bg-orange-900/20 border-orange-200 dark:border-orange-800',
            'info'    => 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800',
        ];
        $icons = ['danger' => '🔴', 'orange' => '🟠', 'warning' => '🟡', 'info' => '🔵'];
        $color = $colors[$alert['type']] ?? $colors['info'];
        $icon  = $icons[$alert['type']]  ?? '🔵';
        ?>

        <a href="/cycles/<?= e($alert['cycle_id']) ?>"
           class="flex items-center gap-3 rounded-xl border px-3 py-2.5 <?= $color ?>">
            <span><?= $icon ?></span>

            <div class="flex-1 min-w-0">
                <div class="text-xs font-semibold truncate">
                    <?= e($alert['barn_name']) ?> · <?= e($alert['cycle_code']) ?>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    <?= e($alert['message']) ?>
                </div>
            </div>

            <span class="text-gray-400 text-xs">›</span>
        </a>

        <?php endforeach; ?>

    </div>
</div>
<?php endif; ?>

    <!-- Cycles active -->
    <div class="text-sm font-semibold mb-2">🐔 Chu kỳ đang nuôi</div>
    <?php if (empty($active_cycles)): ?>
    <div class="text-center py-10 text-gray-400 text-sm">
        <div class="text-3xl mb-2">🐣</div>
        Chưa có chu kỳ nào đang nuôi
        <div class="mt-3">
            <a href="/barns" class="text-blue-500 hover:underline text-sm">→ Tạo chu kỳ mới</a>
        </div>
    </div>
    <?php else: ?>
    <div class="space-y-3">
        <?php foreach ($active_cycles as $c): ?>
        <?php
        $death_rate = $c['initial_quantity'] > 0
            ? (float)($c['total_deaths'] ?? 0) / $c['initial_quantity'] * 100 : 0;
        $fcr = $c['latest_fcr'];
        $fcr_color = !$fcr ? 'text-gray-400'
            : ($fcr < 1.8 ? 'text-green-600' : ($fcr <= 2.2 ? 'text-yellow-600' : 'text-red-600'));
        ?>
        <a href="/cycles/<?= e($c['id']) ?>"
           class="block bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 active:scale-[0.98] transition-transform">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <div class="font-semibold text-sm"><?= e($c['barn_name']) ?></div>
                    <div class="text-xs text-gray-400"><?= e($c['code']) ?> · Ngày <?= e($c['day_age']) ?></div>
                </div>
                <span class="bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs font-medium px-2 py-0.5 rounded-full">Đang nuôi</span>
            </div>
            <div class="grid grid-cols-4 gap-2 text-center">
                <div>
                    <div class="text-sm font-bold"><?= number_format($c['current_quantity']) ?></div>
                    <div class="text-xs text-gray-400">Con</div>
                </div>
                <div>
                    <div class="text-sm font-bold <?= $death_rate > 5 ? 'text-red-500' : '' ?>">
                        <?= number_format($death_rate, 1) ?>%
                    </div>
                    <div class="text-xs text-gray-400">Hao hụt</div>
                </div>
                <div>
                    <div class="text-sm font-bold">
                        <?= $c['latest_avg_weight'] ? number_format((float)$c['latest_avg_weight']) . 'g' : '—' ?>
                    </div>
                    <div class="text-xs text-gray-400">TB/con</div>
                </div>
                <div>
                    <div class="text-sm font-bold <?= $fcr_color ?>">
                        <?= $fcr ? number_format((float)$fcr, 2) : '—' ?>
                    </div>
                    <div class="text-xs text-gray-400">FCR</div>
                </div>
            </div>
            <!-- Quick action -->
            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 flex gap-2">
                <a href="/events/create?cycle_id=<?= e($c['id']) ?>"
                   onclick="event.stopPropagation()"
                   class="flex-1 text-center bg-blue-600 text-white text-xs font-semibold py-1.5 rounded-lg">
                    + Ghi chép
                </a>
                <a href="/reports/<?= e($c['id']) ?>"
                   onclick="event.stopPropagation()"
                   class="flex-1 text-center bg-gray-100 dark:bg-gray-700 text-xs font-semibold py-1.5 rounded-lg">
                    📊 Báo cáo
                </a>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>
<script>
async function testNotification() {
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Đang gửi...';
    const res = await fetch('/push/test', { method: 'POST' });
    const json = await res.json();
    btn.textContent = json.ok ? '✅ Đã gửi!' : '❌ Lỗi';
    setTimeout(() => { btn.disabled = false; btn.textContent = 'Gửi test'; }, 3000);
}

window.addEventListener('load', async () => {
    if (!('serviceWorker' in navigator)) return;
    try {
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        if (sub) document.getElementById('notif_test_bar')?.classList.remove('hidden');
    } catch(e) {}
});
</script>

<script src="//unpkg.com/alpinejs" defer></script>
