// Service Worker for Hamza for Perfumes POS - Offline Support
const CACHE_NAME = 'hamza-pos-v2';
const STATIC_ASSETS = [
    'assets/style.css',
    'assets/logo.png',
    'manifest.json'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(STATIC_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
            );
        }).then(() => self.clients.claim())
    );
});

// Handle messages from layout/pos
self.addEventListener('message', event => {
    if (!event.data) return;
    if (event.data.type === 'CACHE_POS_HTML' && event.data.html) {
        caches.open(CACHE_NAME).then(cache => {
            const res = new Response(event.data.html, {
                status: 200,
                headers: { 'Content-Type': 'text/html; charset=utf-8' }
            });
            cache.put('index.php?r=pos', res.clone());
            cache.put(self.location.origin + '/index.php?r=pos', res.clone());
            cache.put('./index.php?r=pos', res.clone());
        });
    } else if (event.data.type === 'CACHE_POS_PAGE' && event.data.url) {
        fetch(event.data.url).then(res => {
            if (res && res.status === 200) {
                caches.open(CACHE_NAME).then(cache => cache.put(event.data.url, res));
            }
        }).catch(() => {});
    }
});

self.addEventListener('fetch', event => {
    const req = event.request;
    const url = new URL(req.url);

    // Only handle GET requests
    if (req.method !== 'GET') return;

    // Never cache API or sync endpoints
    if (url.searchParams.has('r')) {
        const r = url.searchParams.get('r');
        if (r === 'pos_sync_offline' || r === 'quick_add_customer' || r === 'barcode_lookup' || r === 'api_pos_return') {
            return;
        }
    }

    // Network-first with cached snapshot fallback for POS page
    if (url.searchParams.get('r') === 'pos' || url.searchParams.get('r') === 'online_pos') {
        event.respondWith(
            fetch(req)
                .then(networkRes => {
                    if (networkRes && networkRes.status === 200) {
                        const copy = networkRes.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(req, copy));
                    }
                    return networkRes;
                })
                .catch(() => {
                    return caches.match(req).then(cached => {
                        if (cached) return cached;
                        return caches.match('index.php?r=pos');
                    });
                })
        );
        return;
    }

    // Cache-first with network fallback for assets (CSS, images, fonts)
    if (req.destination === 'style' || req.destination === 'image' || req.destination === 'font' || req.destination === 'script') {
        event.respondWith(
            caches.match(req).then(cachedRes => {
                if (cachedRes) return cachedRes;
                return fetch(req).then(networkRes => {
                    if (networkRes && networkRes.status === 200) {
                        const copy = networkRes.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(req, copy));
                    }
                    return networkRes;
                });
            })
        );
        return;
    }

    // Default fetch
    event.respondWith(
        fetch(req).catch(() => caches.match(req))
    );
});
