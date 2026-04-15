<?php
$title = 'Dashboard - Cloud Control';
ob_start();
?>

<div class="px-4 pt-4 pb-24">

    <!-- Header -->
    <div class="mb-6">
        <div class="text-xl font-bold">🖥️ Cloud Control</div>
        <div class="text-xs text-gray-400">Remote relay & curtain control</div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 gap-3 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 border border-gray-100 dark:border-gray-700">
            <div class="text-3xl font-bold text-blue-600"><?= $device_count ?></div>
            <div class="text-xs text-gray-500">Thiết bị IoT</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 border border-gray-100 dark:border-gray-700">
            <div class="text-3xl font-bold text-green-600"><?= $online_count ?></div>
            <div class="text-xs text-gray-500">Online</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mb-6">
        <div class="text-sm font-semibold mb-3">⚡ Điều khiển nhanh</div>
        <div class="grid grid-cols-2 gap-3">
            <a href="/iot/devices"
               class="bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl p-4 text-center active:scale-95 transition-transform">
                <div class="text-2xl mb-1">📟</div>
                <div class="text-xs font-semibold text-indigo-700 dark:text-indigo-300">Thiết bị</div>
            </a>
            <a href="/iot/control"
               class="bg-emerald-50 dark:bg-emerald-900/30 rounded-2xl p-4 text-center active:scale-95 transition-transform">
                <div class="text-2xl mb-1">🎛️</div>
                <div class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">Điều khiển</div>
            </a>
        </div>
    </div>

    <!-- Push Notifications -->
    <div class="mb-6">
        <div class="text-sm font-semibold mb-3">🔔 Push Notifications</div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 border border-gray-100 dark:border-gray-700">
            <div id="push-status" class="text-sm text-gray-500">Đang kiểm tra...</div>
            <div class="flex gap-2 mt-3">
                <button id="btn-subscribe" class="btn btn-sm btn-primary hidden">Bật thông báo</button>
                <button id="btn-unsubscribe" class="btn btn-sm btn-secondary hidden">Tắt thông báo</button>
                <button id="btn-test" class="btn btn-sm btn-secondary hidden">Gửi test</button>
            </div>
        </div>
    </div>

    <!-- Recent Notifications -->
    <div class="mb-6">
        <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-semibold">🔔 Thông báo gần đây</div>
            <a href="/notifications" class="text-xs text-blue-500 hover:underline">Xem tất cả</a>
        </div>
        <?php if (empty($recent_notifications)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700 text-center text-gray-400">
            <div class="text-3xl mb-2">🔕</div>
            <div class="text-sm">Chưa có thông báo nào</div>
        </div>
        <?php else: ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <?php foreach (array_slice($recent_notifications, 0, 5) as $n): ?>
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                <div class="text-sm font-medium"><?= htmlspecialchars($n->title ?? 'Notification') ?></div>
                <div class="text-xs text-gray-400 mt-1"><?= time_ago($n->sent_at) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Online Devices -->
    <div class="mb-6">
        <div class="text-sm font-semibold mb-3">📡 Trạng thái thiết bị</div>
        <?php if (empty($devices)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700 text-center text-gray-400">
            <div class="text-3xl mb-2">📡</div>
            <div class="text-sm">Chưa có thiết bị nào</div>
            <a href="/settings/iot" class="text-xs text-blue-500 hover:underline mt-2 inline-block">Thêm thiết bị</a>
        </div>
        <?php else: ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <?php foreach ($devices as $d): ?>
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-0 flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium"><?= htmlspecialchars($d->name) ?></div>
                    <div class="text-xs text-gray-400"><?= htmlspecialchars($d->device_code) ?></div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full <?= $d->is_online ? 'bg-green-500' : 'bg-gray-300' ?>"></span>
                    <span class="text-xs <?= $d->is_online ? 'text-green-600' : 'text-gray-400' ?>">
                        <?= $d->is_online ? 'Online' : 'Offline' ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
(async function() {
    const statusEl = document.getElementById('push-status');
    const btnSubscribe = document.getElementById('btn-subscribe');
    const btnUnsubscribe = document.getElementById('btn-unsubscribe');
    const btnTest = document.getElementById('btn-test');

    let subscription = null;
    let vapidKey = null;

    async function getVapidKey() {
        try {
            const res = await fetch('/push/vapid-public-key');
            const data = await res.json();
            return data.key;
        } catch (e) { return null; }
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
        return outputArray;
    }

    async function registerSW() {
        if ('serviceWorker' in navigator) {
            try {
                const reg = await navigator.serviceWorker.register('/sw.js');
                await navigator.serviceWorker.ready;
                return reg;
            } catch (e) { return null; }
        }
        return null;
    }

    async function checkSubscription(sw) {
        if (!sw) return null;
        try { return await sw.pushManager.getSubscription(); } catch (e) { return null; }
    }

    async function subscribe() {
        const sw = await registerSW();
        if (!sw) { statusEl.textContent = '❌ Trình duyệt không hỗ trợ Service Worker'; return; }
        if (!vapidKey) vapidKey = await getVapidKey();
        if (!vapidKey) { statusEl.textContent = '❌ Không lấy được VAPID key'; return; }

        try {
            subscription = await sw.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidKey)
            });
            const subData = subscription.toJSON();
            await fetch('/push/subscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(subData)
            });
            updateUI(true);
            statusEl.textContent = '✅ Đã bật thông báo!';
        } catch (e) {
            statusEl.textContent = '❌ Lỗi đăng ký: ' + e.message;
        }
    }

    async function unsubscribe() {
        if (subscription) {
            try { await subscription.unsubscribe(); } catch (e) {}
        }
        subscription = null;
        updateUI(false);
        statusEl.textContent = 'ℹ️ Đã tắt thông báo';
    }

    async function sendTest() {
        try {
            const res = await fetch('/push/test', { method: 'POST' });
            const data = await res.json();
            statusEl.textContent = data.ok ? '✅ Đã gửi thông báo test!' : '❌ Lỗi: ' + data.message;
        } catch (e) {
            statusEl.textContent = '❌ Lỗi gửi test';
        }
    }

    function updateUI(subscribed) {
        btnSubscribe.classList.toggle('hidden', subscribed);
        btnUnsubscribe.classList.toggle('hidden', !subscribed);
        btnTest.classList.toggle('hidden', !subscribed);
    }

    async function init() {
        const sw = await registerSW();
        subscription = await checkSubscription(sw);
        if (!sw) { statusEl.textContent = '❌ Trình duyệt không hỗ trợ Service Worker'; return; }
        if (subscription) {
            statusEl.textContent = '✅ Đã đăng ký - nhấn "Gửi test" để kiểm tra';
            updateUI(true);
        } else {
            statusEl.textContent = '⚠️ Chưa bật thông báo push';
            updateUI(false);
        }
    }

    btnSubscribe.addEventListener('click', subscribe);
    btnUnsubscribe.addEventListener('click', unsubscribe);
    btnTest.addEventListener('click', sendTest);
    init();
})();
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');

// Helper function
function time_ago(string $datetime): string {
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) return 'Vừa xong';
    if ($diff < 3600) return floor($diff / 60) . ' phút trước';
    if ($diff < 86400) return floor($diff / 3600) . ' giờ trước';
    return floor($diff / 86400) . ' ngày trước';
}
