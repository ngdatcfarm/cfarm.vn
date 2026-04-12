<?php
/**
 * app/interfaces/http/views/layouts/main.php
 *
 * Layout chính của ứng dụng.
 * Mobile-first, Material Design style, dark/light toggle.
 * Bottom navigation bar với FAB ở giữa.
 */
?>
<!DOCTYPE html>
<html lang="vi" class="<?= $_COOKIE['theme'] ?? 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="CFarm">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#2563eb">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title><?= e($title ?? 'CFarm') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <style>
        body {
            font-family: 'Be Vietnam Pro', sans-serif;
            padding-bottom: 90px;
        }
        .fab-menu-item {
            transition: all 0.2s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-slide-up { animation: slideUp 0.25s ease both; }
        @keyframes slideSheet {
            from { transform: translateY(100%); }
            to   { transform: translateY(0); }
        }
        .animate-sheet { animation: slideSheet 0.25s ease both; }
    </style>
</head>
<body class="bg-slate-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen">

    <!-- top header -->
    <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 sticky top-0 z-10">
        <div class="max-w-2xl mx-auto px-4 h-14 flex justify-between items-center">
            <a href="/" class="font-bold text-lg text-blue-600 dark:text-blue-400">
                🐔 CFarm
            </a>
            <div class="flex items-center gap-2">
                <button id="notif_btn" onclick="toggleNotifications()"
                        class="p-2 rounded-full bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-sm transition-colors"
                        title="Bật thông báo">🔔</button>
                <button id="notif_test_btn" onclick="testNotification()" style="display:none"
                        class="p-2 rounded-full bg-green-100 dark:bg-green-900/30 hover:bg-green-200 text-sm transition-colors"
                        title="Test thông báo">📨</button>
                <button onclick="toggleTheme()"
                        class="p-2 rounded-full bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-sm transition-colors">
                    <span class="dark:hidden">🌙</span>
                    <span class="hidden dark:inline">☀️</span>
                </button>
            </div>
        </div>
        <script>
    async function testNotification() {
    const btn = document.getElementById('notif_test_btn');
    btn.textContent = '⏳';
    try {
        const res  = await fetch('/push/test', { method: 'POST' });
        const json = await res.json();
        btn.textContent = json.ok ? '✅' : '❌';
    } catch(e) { btn.textContent = '❌'; }
    setTimeout(() => btn.textContent = '📨', 2000);
}

async function toggleNotifications() {
        const btn = document.getElementById('notif_btn');
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            alert('Trình duyệt không hỗ trợ thông báo'); return;
        }
        const perm = await Notification.requestPermission();
        if (perm !== 'granted') { alert('Bạn đã từ chối thông báo'); return; }

        const reg = await navigator.serviceWorker.ready;
        const existing = await reg.pushManager.getSubscription();
        if (existing) {
            await existing.unsubscribe();
            await fetch('/push/unsubscribe', { method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({endpoint: existing.endpoint}) });
            btn.textContent = '🔔';
            btn.title = 'Bật thông báo';
            return;
        }

        const keyRes = await fetch('/push/vapid-public-key');
        const { key } = await keyRes.json();
        const raw = Uint8Array.from(atob(key.replace(/-/g,'+').replace(/_/g,'/')), c => c.charCodeAt(0));
        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: raw
        });
        await fetch('/push/subscribe', { method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(sub) });
        btn.textContent = '🔕';
        btn.title = 'Tắt thông báo';
        alert('✅ Đã bật thông báo!');
    }

    // Kiểm tra trạng thái khi load
    window.addEventListener('load', async () => {
        const btn = document.getElementById('notif_btn');
        if (!btn || !('serviceWorker' in navigator)) return;
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        btn.textContent = sub ? '🔕' : '🔔';
        btn.title = sub ? 'Tắt thông báo' : 'Bật thông báo';
    });
    </script>
</header>

    <!-- content -->
    <main class="max-w-2xl mx-auto px-4 py-5 animate-slide-up">
        <?= $content ?? '' ?>
    </main>

    <!-- bottom navigation bar -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 z-20">
        <div class="max-w-2xl mx-auto px-2">
            <div class="flex items-center justify-around h-16 relative">

                <!-- home -->
                <a href="/"
                   class="flex flex-col items-center gap-0.5 px-3 py-1 rounded-xl text-xs
                          <?= active('/') ? 'text-blue-600 dark:text-blue-400 font-semibold' : 'text-gray-400 dark:text-gray-500' ?>">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Tổng quan
                </a>

                <!-- môi trường -->
                <a href="/env"
                   class="flex flex-col items-center gap-0.5 px-3 py-1 rounded-xl text-xs
                          <?= active('/env') ? 'text-blue-600 dark:text-blue-400 font-semibold' : 'text-gray-400 dark:text-gray-500' ?>">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                    </svg>
                    Môi trường
                </a>

                <!-- cài đặt -->
                <a href="/settings"
                   class="flex flex-col items-center gap-0.5 px-3 py-1 rounded-xl text-xs
                          <?= active('/settings') ? 'text-blue-600 dark:text-blue-400 font-semibold' : 'text-gray-400 dark:text-gray-500' ?>">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Cài đặt
                </a>

                <!-- tài khoản -->
                <a href="/account"
                   class="flex flex-col items-center gap-0.5 px-3 py-1 rounded-xl text-xs
                          <?= active('/account') ? 'text-blue-600 dark:text-blue-400 font-semibold' : 'text-gray-400 dark:text-gray-500' ?>">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Tài khoản
                </a>

                <!-- FAB button -->
                <button onclick="toggleFab()"
                        id="fab_btn"
                        class="absolute -top-7 left-1/2 -translate-x-1/2 w-14 h-14 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-xl flex items-center justify-center border-4 border-white dark:border-gray-800 transition-transform duration-200">
                    <svg id="fab_icon" class="w-6 h-6 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                </button>

            </div>
        </div>
    </nav>

    <!-- backdrop -->
    <div id="fab_backdrop"
         onclick="closeFab()"
         class="hidden fixed inset-0 bg-black/30 dark:bg-black/50 z-20">
    </div>

    <!-- cycle selector bottom sheet -->
    <div id="fab_sheet"
         class="hidden fixed bottom-0 left-0 right-0 z-30">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white dark:bg-gray-800 rounded-t-2xl shadow-2xl animate-sheet">

                <!-- handle -->
                <div class="flex justify-center pt-3 pb-1">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full"></div>
                </div>

                <div class="px-5 pt-3 pb-2">
                    <div class="text-base font-bold text-gray-900 dark:text-gray-100">Chọn cycle để ghi chép</div>
                    <div class="text-sm text-gray-400 mt-0.5">Các cycle đang hoạt động</div>
                </div>

                <!-- danh sách cycle active — render động từ PHP -->
                <div class="px-4 pb-8 space-y-2 max-h-72 overflow-y-auto" id="cycle_list">
                    <?php if (!empty($GLOBALS["active_cycles_for_fab"] ?? [])): ?>
                        <?php foreach (($GLOBALS["active_cycles_for_fab"] ?? []) as $fc): ?>
                        <a href="/events/create?cycle_id=<?= e($fc->id) ?>"
                           class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors">
                            <div class="w-11 h-11 bg-blue-50 dark:bg-blue-900/30 rounded-xl flex items-center justify-center text-xl flex-shrink-0">🐔</div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-sm text-gray-900 dark:text-gray-100"><?= e($fc->code) ?></div>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    <?= e(number_format($fc->current_quantity)) ?> con
                                    · <?= e($fc->age_in_days()) ?> ngày tuổi
                                </div>
                            </div>
                            <span class="text-xs font-semibold px-2.5 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-full">Active</span>
                            <svg class="w-4 h-4 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6"/>
                            </svg>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-400">
                            <div class="text-3xl mb-2">🐔</div>
                            <div class="text-sm">Không có cycle nào đang hoạt động</div>
                            <a href="/barns" class="text-blue-600 text-sm mt-2 inline-block hover:underline">Tạo cycle mới</a>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <script>
        // dark mode
        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.classList.toggle('dark');
            document.cookie = 'theme=' + (isDark ? 'dark' : 'light') + ';path=/;max-age=31536000';
        }

        // FAB
        let fab_open = false;

        function toggleFab() {
            fab_open ? closeFab() : openFab();
        }

        function openFab() {
            fab_open = true;
            document.getElementById('fab_sheet').classList.remove('hidden');
            document.getElementById('fab_backdrop').classList.remove('hidden');
            document.getElementById('fab_btn').style.transform = 'translateX(-50%) rotate(45deg)';
        }

        function closeFab() {
            fab_open = false;
            document.getElementById('fab_sheet').classList.add('hidden');
            document.getElementById('fab_backdrop').classList.add('hidden');
            document.getElementById('fab_btn').style.transform = 'translateX(-50%) rotate(0deg)';
        }
    </script>


    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js')
                .then(r => console.log('SW registered'))
                .catch(e => console.log('SW error', e));
        });
    }
    </script>
</body>
</html>
