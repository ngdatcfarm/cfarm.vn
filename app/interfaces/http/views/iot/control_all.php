<?php
$title = 'Điều khiển bạt';
ob_start();
?>

<!-- Header -->
<div class="bg-indigo-600 rounded-2xl p-4 mb-4">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-2xl">🎛️</div>
        <div>
            <div class="text-lg font-bold text-white">Điều khiển bạt</div>
            <div class="text-sm text-indigo-200">Tất cả chuồng</div>
        </div>
    </div>
</div>

<?php
$has_curtains = false;
foreach ($all_curtains as $curtains) { if (!empty($curtains)) $has_curtains = true; }
?>

<?php if (!$has_curtains): ?>
<div class="text-center py-16 text-gray-400">
    <div class="text-5xl mb-4">⚙️</div>
    <p>Chưa có bạt nào được cấu hình</p>
    <a href="/settings/iot" class="text-blue-500 hover:underline text-sm mt-2 inline-block">⚙️ IoT Settings</a>
</div>
<?php else: ?>

<div class="space-y-3">
<?php foreach ($barns as $barn): ?>
<?php $curtains = $all_curtains[$barn->id] ?? []; ?>
<?php if (empty($curtains)) continue; ?>
<?php
    $has_moving = false;
    foreach ($curtains as $cur) {
        if ($cur->moving_state !== 'idle') { $has_moving = true; break; }
    }
?>

<!-- Barn accordion -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
    <!-- Header (clickable) -->
    <button onclick="toggleBarn(<?= e($barn->id) ?>)"
            class="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
            id="barn_header_<?= e($barn->id) ?>">
        <div class="flex items-center gap-3">
            <span class="text-lg">🏠</span>
            <div class="text-left">
                <div class="text-sm font-bold"><?= e($barn->name) ?></div>
                <div class="text-xs text-gray-400"><?= count($curtains) ?> bạt</div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span id="barn_badge_<?= e($barn->id) ?>" class="<?= $has_moving ? '' : 'hidden' ?> text-xs bg-orange-100 dark:bg-orange-900/30 text-orange-600 font-semibold px-2 py-0.5 rounded-full animate-pulse">
                Đang chạy
            </span>
            <span id="barn_arrow_<?= e($barn->id) ?>" class="text-gray-400 transition-transform">▼</span>
        </div>
    </button>

    <!-- Content (collapsed by default, expanded if has moving) -->
    <div id="barn_content_<?= e($barn->id) ?>" class="<?= $has_moving ? '' : 'hidden' ?> border-t border-gray-100 dark:border-gray-700 px-4 pb-4 pt-3">
        <!-- Barn controls -->
        <div class="flex gap-2 mb-3">
            <button onclick="moveBarnCurtains(<?= e($barn->id) ?>, 100)"
                    class="flex-1 bg-green-500 hover:bg-green-600 text-white text-xs font-semibold py-2 rounded-lg transition-colors">▲ Mở hết</button>
            <button onclick="stopBarnCurtains(<?= e($barn->id) ?>)"
                    class="flex-1 bg-red-500 hover:bg-red-600 text-white text-xs font-semibold py-2 rounded-lg transition-colors">■ Dừng</button>
            <button onclick="moveBarnCurtains(<?= e($barn->id) ?>, 0)"
                    class="flex-1 bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold py-2 rounded-lg transition-colors">▼ Đóng hết</button>
        </div>

        <!-- Curtains -->
        <div class="space-y-2">
        <?php foreach ($curtains as $cur): ?>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3" id="curtain_card_<?= e($cur->id) ?>" data-barn="<?= e($barn->id) ?>">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <span class="text-sm font-semibold"><?= e($cur->name) ?></span>
                        <?php $online = $cur->up_online && $cur->down_online; ?>
                        <span class="text-xs ml-1 <?= $online ? 'text-green-500' : 'text-red-500' ?>">●</span>
                        <span class="text-xs ml-1">
                            <span id="cur_pos_label_<?= e($cur->id) ?>" class="font-semibold"><?= e($cur->real_position) ?>%</span>
                            <span id="cur_moving_<?= e($cur->id) ?>" class="ml-1 <?= $cur->moving_state === 'idle' ? 'hidden' : '' ?>">
                                <?php if ($cur->moving_state === 'moving_up'): ?>
                                    <span class="text-green-500 animate-pulse">▲ <span id="cur_countdown_<?= e($cur->id) ?>"></span></span>
                                <?php elseif ($cur->moving_state === 'moving_down'): ?>
                                    <span class="text-orange-500 animate-pulse">▼ <span id="cur_countdown_<?= e($cur->id) ?>"></span></span>
                                <?php endif; ?>
                            </span>
                        </span>
                    </div>
                    <button onclick="stopCurtain(<?= e($cur->id) ?>)"
                            class="bg-red-500 hover:bg-red-600 text-white text-xs font-bold px-2 py-1 rounded-lg">■</button>
                </div>

                <!-- Progress bar -->
                <div class="relative h-2.5 bg-gray-200 dark:bg-gray-600 rounded-full mb-2 overflow-hidden">
                    <div id="cur_bar_<?= e($cur->id) ?>"
                         class="absolute left-0 top-0 h-full bg-indigo-500 rounded-full transition-all duration-500"
                         style="width: <?= e($cur->real_position) ?>%"></div>
                </div>

                <!-- Quick buttons -->
                <div class="flex gap-1">
                    <?php foreach ([0, 25, 50, 75, 100] as $pct): ?>
                    <button onclick="moveCurtain(<?= e($cur->id) ?>, <?= $pct ?>)"
                            class="flex-1 py-1 text-xs font-semibold rounded-lg border transition-all
                                   <?= (int)$cur->real_position === $pct
                                       ? 'border-indigo-500 text-indigo-600 bg-indigo-50 dark:bg-indigo-900/30'
                                       : 'border-gray-200 dark:border-gray-500 text-gray-500 hover:border-indigo-400' ?>">
                        <?= $pct ?>%
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<script>
// Toggle accordion
function toggleBarn(barnId) {
    const content = document.getElementById('barn_content_' + barnId);
    const arrow = document.getElementById('barn_arrow_' + barnId);
    content.classList.toggle('hidden');
    arrow.style.transform = content.classList.contains('hidden') ? '' : 'rotate(180deg)';
}

function updateCurtainUI(id, pct) {
    const label = document.getElementById('cur_pos_label_' + id);
    const bar = document.getElementById('cur_bar_' + id);
    if (label) label.textContent = pct + '%';
    if (bar) bar.style.width = pct + '%';
}

function updateBarnBadge(barnId) {
    const cards = document.querySelectorAll('[data-barn="' + barnId + '"]');
    let hasMoving = false;
    cards.forEach(card => {
        const movingEl = card.querySelector('[id^="cur_moving_"]');
        if (movingEl && !movingEl.classList.contains('hidden')) hasMoving = true;
    });
    const badge = document.getElementById('barn_badge_' + barnId);
    if (badge) {
        if (hasMoving) badge.classList.remove('hidden');
        else badge.classList.add('hidden');
    }
}

async function moveCurtain(id, targetPct) {
    const card = document.getElementById('curtain_card_' + id);
    const barnId = card.dataset.barn;
    card.classList.add('opacity-70');
    try {
        const res = await fetch('/iot/curtain/' + id + '/move', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ target_pct: targetPct })
        });
        const json = await res.json();
        if (json.ok) {
            updateCurtainUI(id, json.position);
            if (json.duration > 0) {
                showMovingState(id, json.direction, json.duration, json.position, json.target);
                showToast(json.direction === 'up' ? '▲ Đang đóng...' : '▼ Đang mở...', json.duration);
            } else {
                hideMovingState(id);
            }
            updateBarnBadge(barnId);
        } else {
            alert(json.message || 'Lỗi gửi lệnh');
        }
    } catch(e) {
        alert('Lỗi kết nối');
    }
    card.classList.remove('opacity-70');
}

async function stopCurtain(id) {
    const card = document.getElementById('curtain_card_' + id);
    const barnId = card.dataset.barn;
    const res = await fetch('/iot/curtain/' + id + '/stop', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({})
    });
    const json = await res.json();
    if (json.ok) {
        hideMovingState(id);
        updateCurtainUI(id, json.position);
        updateBarnBadge(barnId);
        showToast('■ Dừng · ' + json.position + '%');
    }
}

function moveBarnCurtains(barnId, pct) {
    document.querySelectorAll('[data-barn="' + barnId + '"]').forEach(card => {
        const id = parseInt(card.id.replace('curtain_card_', ''));
        moveCurtain(id, pct);
    });
}

function stopBarnCurtains(barnId) {
    document.querySelectorAll('[data-barn="' + barnId + '"]').forEach(card => {
        const id = parseInt(card.id.replace('curtain_card_', ''));
        stopCurtain(id);
    });
}

// Moving state
let _movingTimers = {};

function showMovingState(id, direction, duration, fromPos, toPos) {
    const el = document.getElementById('cur_moving_' + id);
    if (!el) return;
    el.classList.remove('hidden');
    el.innerHTML = direction === 'up'
        ? '<span class="text-green-500 animate-pulse">▲ <span id="cur_countdown_' + id + '">' + duration.toFixed(1) + 's</span></span>'
        : '<span class="text-orange-500 animate-pulse">▼ <span id="cur_countdown_' + id + '">' + duration.toFixed(1) + 's</span></span>';

    if (_movingTimers[id]) clearInterval(_movingTimers[id]);
    let remaining = duration;
    const total = duration;
    _movingTimers[id] = setInterval(() => {
        remaining -= 0.5;
        const cd = document.getElementById('cur_countdown_' + id);
        if (cd) cd.textContent = Math.max(0, remaining).toFixed(1) + 's';
        const ratio = Math.min(1, (total - remaining) / total);
        const currentPct = Math.round(fromPos + (toPos - fromPos) * ratio);
        updateCurtainUI(id, currentPct);
        if (remaining <= 0) {
            updateCurtainUI(id, toPos);
            hideMovingState(id);
            const card = document.getElementById('curtain_card_' + id);
            if (card) updateBarnBadge(card.dataset.barn);
        }
    }, 500);
}

function hideMovingState(id) {
    const el = document.getElementById('cur_moving_' + id);
    if (el) { el.classList.add('hidden'); el.innerHTML = ''; }
    if (_movingTimers[id]) { clearInterval(_movingTimers[id]); delete _movingTimers[id]; }
}

function showToast(msg, duration) {
    const existing = document.getElementById('iot_toast');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.id = 'iot_toast';
    toast.className = 'fixed bottom-20 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-sm px-4 py-2.5 rounded-xl shadow-lg z-50 transition-opacity';
    toast.textContent = duration ? msg + ' (' + duration.toFixed(1) + 's)' : msg;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
}
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
