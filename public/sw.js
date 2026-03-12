const CACHE = 'cfarm-v2';
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
    e.waitUntil(
        self.registration.showNotification(data.title || 'CFarm', {
            body:  data.body  || '',
            icon:  data.icon  || '/icons/icon-192.png',
            badge: data.badge || '/icons/icon-192.png',
            data:  { url: data.url || '/' },
            vibrate: [200, 100, 200],
        })
    );
});

// Click notification → mở URL
self.addEventListener('notificationclick', e => {
    e.notification.close();
    e.waitUntil(
        clients.matchAll({ type: 'window' }).then(list => {
            const url = e.notification.data?.url || '/';
            for (const client of list) {
                if (client.url === url && 'focus' in client) return client.focus();
            }
            if (clients.openWindow) return clients.openWindow(url);
        })
    );
});
