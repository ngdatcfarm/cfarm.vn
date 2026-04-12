<?php
$title = 'Điều khiển - ' . e($barn->name);
ob_start();
?>

<div class="mb-4">
    <a href="/settings/iot" class="text-sm text-blue-600 hover:underline">← IoT Settings</a>
</div>

<!-- Header -->
<div class="bg-blue-600 rounded-2xl p-4 mb-4">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-2xl">🏠</div>
        <div>
            <div class="text-lg font-bold text-white"><?= e($barn->name) ?></div>
            <div class="text-sm text-blue-200">Điều khiển bạt</div>
        </div>
    </div>
</div>

<?php if (empty($curtains)): ?>
<div class="text-center py-16 text-gray-400">
    <div class="text-5xl mb-4">⚙️</div>
    <p>Chưa có bạt nào được cấu hình</p>
    <p class="text-xs mt-2">Thêm device và cấu hình bạt trong phần cài đặt</p>
</div>
<?php else: ?>

<!-- Điều khiển tất cả -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">⚡ Điều khiển tất cả</div>
    <div class="grid grid-cols-3 gap-2">
        <button onclick="moveAllCurtains(100)"
                class="bg-green-500 hover:bg-green-600 text-white text-sm font-semibold py-3 rounded-xl transition-colors">
            ▲ Mở hết
        </button>
        <button onclick="stopAllCurtains()"
                class="bg-red-500 hover:bg-red-600 text-white text-sm font-semibold py-3 rounded-xl transition-colors">
            ■ Dừng
        </button>
        <button onclick="moveAllCurtains(0)"
                class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold py-3 rounded-xl transition-colors">
            ▼ Đóng hết
        </button>
    </div>
</div>

<!-- Danh sách bạt -->
<div class="space-y-3">
<?php foreach ($curtains as $cur): ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4"
         id="curtain_card_<?= e($cur->id) ?>">
        <!-- Header -->
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-lg">🪟</div>
                <div>
                    <div class="text-sm font-semibold"><?= e($cur->name) ?></div>
                    <div class="text-xs text-gray-400">
                        <?php if ($cur->is_online): ?>
                        <span class="text-green-500">● Online</span>
                        <?php else: ?>
                        <span class="text-red-500">● Offline</span>
                        <?php endif; ?>
                        · Vị trí: <span id="cur_pos_label_<?= e($cur->id) ?>" class="font-semibold"><?= e($cur->real_position) ?>%</span>
                        <span id="cur_moving_<?= e($cur->id) ?>" class="ml-1 <?= $cur->moving_state === 'idle' ? 'hidden' : '' ?>">
                            <?php if ($cur->moving_state === 'moving_up'): ?>
                                <span class="text-orange-500 animate-pulse">▲ Đang đóng...</span>
                            <?php elseif ($cur->moving_state === 'moving_down'): ?>
                                <span class="text-green-500 animate-pulse">▼ Đang mở...</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
            <button onclick="stopCurtain(<?= e($cur->id) ?>)"
                    class="bg-red-500 hover:bg-red-600 text-white text-xs font-bold px-3 py-2 rounded-xl">
                ■ DỪNG
            </button>
        </div>

        <!-- Progress bar -->
        <div class="relative h-4 bg-gray-100 dark:bg-gray-700 rounded-full mb-3 overflow-hidden">
            <div id="cur_bar_<?= e($cur->id) ?>"
                 class="absolute left-0 top-0 h-full bg-indigo-500 rounded-full transition-all duration-500"
                 style="width: <?= e($cur->real_position) ?>%"></div>
        </div>

        <!-- Slider -->
        <div class="flex items-center gap-3 mb-3">
            <span class="text-xs text-gray-400 w-8">Đóng</span>
            <input type="range" id="cur_slider_<?= e($cur->id) ?>" min="0" max="100" step="5"
                   value="<?= e($cur->real_position) ?>"
                   oninput="previewCurtain(<?= e($cur->id) ?>, this.value)"
                   class="flex-1 accent-indigo-500">
            <span class="text-xs text-gray-400 w-6">Mở</span>
            <span id="cur_slider_val_<?= e($cur->id) ?>" class="text-sm font-bold text-indigo-600 w-10 text-right"><?= e($cur->real_position) ?>%</span>
        </div>

        <!-- Quick buttons -->
        <div class="grid grid-cols-6 gap-1.5 mb-3">
            <?php foreach ([0, 20, 40, 60, 80, 100] as $pct): ?>
            <button onclick="moveCurtain(<?= e($cur->id) ?>, <?= $pct ?>)"
                    class="py-2 text-xs font-semibold rounded-lg border transition-all
                           <?= (int)$cur->real_position === $pct
                               ? 'border-indigo-500 text-indigo-600 bg-indigo-50 dark:bg-indigo-900/30'
                               : 'border-gray-200 dark:border-gray-600 text-gray-500 hover:border-indigo-400' ?>">
                <?= $pct ?>%
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Nút lên/xuống -->
        <div class="grid grid-cols-2 gap-2 mt-3">
            <button onclick="moveCurtain(<?= e($cur->id) ?>, 100)"
                    class="bg-green-50 dark:bg-green-900/20 hover:bg-green-100 text-green-600 text-sm font-semibold py-2.5 rounded-xl border border-green-200 dark:border-green-800">
                ▲ Mở hoàn toàn
            </button>
            <button onclick="moveCurtain(<?= e($cur->id) ?>, 0)"
                    class="bg-orange-50 dark:bg-orange-900/20 hover:bg-orange-100 text-orange-600 text-sm font-semibold py-2.5 rounded-xl border border-orange-200 dark:border-orange-800">
                ▼ Đóng hoàn toàn
            </button>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<script>
const CURTAIN_IDS = [<?= implode(',', array_map(fn($c) => $c->id, $curtains ?? [])) ?>];

function previewCurtain(id, val) {
    document.getElementById('cur_slider_val_' + id).textContent = val + '%';
    document.getElementById('cur_bar_' + id).style.width = val + '%';
}

function updateCurtainUI(id, pct) {
    document.getElementById('cur_pos_label_' + id).textContent = pct + '%';
    document.getElementById('cur_bar_' + id).style.width = pct + '%';
    document.getElementById('cur_slider_' + id).value = pct;
    document.getElementById('cur_slider_val_' + id).textContent = pct + '%';
}

async function moveCurtain(id, targetPct) {
    const card = document.getElementById('curtain_card_' + id);
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
                showToast(json.direction === 'up' ? '▼ Đang mở bạt...' : '▲ Đang đóng bạt...', json.duration);
            }
        } else {
            alert(json.message || 'Lỗi gửi lệnh');
        }
    } catch(e) {
        console.error('moveCurtain error:', e);
        alert('Lỗi kết nối: ' + e.message);
    }
    card.classList.remove('opacity-70');
}

async function stopCurtain(id) {
    try {
        const res = await fetch('/iot/curtain/' + id + '/stop', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'}
        });
        const json = await res.json();
        if (json.ok) {
            hideMovingState(id);
            updateCurtainUI(id, json.position);
            showToast('■ Đã dừng bạt · Vị trí: ' + json.position + '%');
        } else {
            alert(json.message || 'Lỗi gửi lệnh dừng');
        }
    } catch(e) {
        console.error('stopCurtain error:', e);
        alert('Lỗi kết nối: ' + e.message);
    }
}

let _movingTimers = {};

function showMovingState(id, direction, duration, fromPos, toPos) {
    const el = document.getElementById('cur_moving_' + id);
    if (!el) return;
    el.classList.remove('hidden');
    el.innerHTML = direction === 'up'
        ? '<span class="text-orange-500 animate-pulse">▲ Đang đóng... <span id="cur_countdown_' + id + '">' + duration.toFixed(1) + 's</span></span>'
        : '<span class="text-green-500 animate-pulse">▼ Đang mở... <span id="cur_countdown_' + id + '">' + duration.toFixed(1) + 's</span></span>';

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
        }
    }, 500);
}

function hideMovingState(id) {
    const el = document.getElementById('cur_moving_' + id);
    if (el) { el.classList.add('hidden'); el.innerHTML = ''; }
    if (_movingTimers[id]) { clearInterval(_movingTimers[id]); delete _movingTimers[id]; }
}

function moveAllCurtains(pct) {
    CURTAIN_IDS.forEach(id => moveCurtain(id, pct));
}

function stopAllCurtains() {
    CURTAIN_IDS.forEach(id => stopCurtain(id));
}

function showToast(msg) {
    const existing = document.getElementById('iot_toast');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.id = 'iot_toast';
    toast.className = 'fixed bottom-20 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-sm px-4 py-2.5 rounded-xl shadow-lg z-50';
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

document.querySelectorAll('input[type=range]').forEach(slider => {
    slider.addEventListener('change', function() {
        const id = this.id.replace('cur_slider_', '');
        moveCurtain(parseInt(id), parseInt(this.value));
    });
});
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
