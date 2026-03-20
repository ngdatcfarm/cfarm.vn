const CACHE = 'cfarm-v3';
const OFFLINE_URLS = ['/'];

self.addEventListener('install', e => {
    e.waitUntil(
        caches.open(CACHE).then(c => c.addAll(OFFLINE_URLS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', e => {
    // Chỉ cache GET, bỏ qua POST và API calls
    if (e.request.method !== 'GET') return;

    e.respondWith(
        fetch(e.request)
            .then(res => {
                // Cache static assets
                if (e.request.url.match(/\.(css|js|png|ico|woff2?)$/)) {
                    const clone = res.clone();
                    caches.open(CACHE).then(c => c.put(e.request, clone));
                }
                return res;
            })
            .catch(() => caches.match(e.request))
    );
});

// Push notification handler
self.addEventListener('push', e => {
    const data = e.data ? e.data.json() : {};
    const options = {
        body:  data.body  || '',
        icon:  data.icon  || '/icons/icon-192.png',
        badge: data.badge || '/icons/icon-192.png',
        data:  { url: data.url || '/', type: data.type || '' },
        vibrate: [200, 100, 200],
    };

    // Thêm action "Đã biết" cho DEVICE_OFFLINE để dừng lặp thông báo
    if (data.type === 'DEVICE_OFFLINE') {
        options.actions = [
            { action: 'acknowledge', title: 'Đã biết' }
        ];
        options.requireInteraction = true; // Giữ notification cho đến khi user tương tác
    }

    e.waitUntil(
        self.registration.showNotification(data.title || 'CFarm', options)
    );
});

// Click notification → mở URL hoặc xử lý action
self.addEventListener('notificationclick', e => {
    e.notification.close();
    const data = e.notification.data || {};

    // Action "Đã biết" → gọi API acknowledge, không mở URL
    if (e.action === 'acknowledge' && data.type) {
        e.waitUntil(
            fetch('/push/acknowledge', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type: data.type }),
            })
        );
        return;
    }

    // Click bình thường → mở URL
    e.waitUntil(
        clients.matchAll({ type: 'window' }).then(list => {
            const url = data.url || '/';
            for (const client of list) {
                if (client.url === url && 'focus' in client) return client.focus();
            }
            if (clients.openWindow) return clients.openWindow(url);
        })
    );
});
