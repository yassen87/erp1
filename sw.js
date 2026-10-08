/**
 * Service Worker — Hamza Perfumes POS Offline Support v3
 * Strategy: Cache-first for POS page, network-first for everything else.
 * Explicitly stores POS HTML in a dedicated named cache entry.
 */

const CACHE_NAME   = 'hamza-pos-v3';
const POS_CACHE_KEY = 'hamza-pos-page-html'; // dedicated key for POS page
const STATIC_CACHE = 'hamza-static-v3';

// Static assets to pre-cache (relative paths served by XAMPP)
const STATIC_URLS = [
    '/test/assets/style.css',
    '/test/assets/app.js',
    '/test/assets/logo.png',
];

// ================================================================
// INSTALL — pre-cache static assets only
// ================================================================
self.addEventListener('install', event => {
    self.skipWaiting(); // activate immediately

    event.waitUntil(
        caches.open(STATIC_CACHE).then(cache => {
            return Promise.allSettled(
                STATIC_URLS.map(url =>
                    fetch(url, { cache: 'no-cache' })
                        .then(r => r.ok ? cache.put(url, r) : null)
                        .catch(() => null)
                )
            );
        })
    );
});

// ================================================================
// ACTIVATE — delete old caches, take control immediately
// ================================================================
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys
                    .filter(k => k !== CACHE_NAME && k !== STATIC_CACHE && k !== POS_CACHE_KEY)
                    .map(k => caches.delete(k))
            )
        ).then(() => self.clients.claim()) // take control of all open pages
    );
});

// ================================================================
// FETCH — intercept all requests
// ================================================================
self.addEventListener('fetch', event => {
    // Ignore non-GET and chrome-extension requests
    if (event.request.method !== 'GET') return;
    if (!event.request.url.startsWith('http')) return;

    const url = new URL(event.request.url);

    // ---- POS page (index.php?r=pos or any pos-related route) ----
    const isPOSPage = (
        url.hostname === 'localhost' &&
        url.pathname.startsWith('/test/') &&
        url.searchParams.get('r') === 'pos' &&
        !url.searchParams.has('print')
    );

    if (isPOSPage) {
        event.respondWith(handlePOSFetch(event.request));
        return;
    }

    // ---- Static assets ----
    const isStatic = STATIC_URLS.some(u => url.pathname === u || url.pathname.endsWith(u.split('/test')[1] || ''));
    if (isStatic) {
        event.respondWith(
            caches.match(url.pathname).then(cached => {
                if (cached) return cached;
                return fetch(event.request).then(r => {
                    if (r && r.ok) {
                        caches.open(STATIC_CACHE).then(c => c.put(url.pathname, r.clone()));
                    }
                    return r;
                }).catch(() => caches.match(url.pathname));
            })
        );
        return;
    }

    // ---- Everything else: network-first, silent fail ----
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});

/**
 * Handle POS page fetches:
 * 1. Try network → if success, update cache → return response
 * 2. If network fails → return cached POS page
 * 3. If no cache → return offline fallback
 */
async function handlePOSFetch(request) {
    try {
        const response = await fetch(request, { cache: 'no-cache' });
        if (response && response.ok) {
            // Save a clone in the dedicated POS cache
            const clone = response.clone();
            caches.open(CACHE_NAME).then(cache => {
                // Store both with exact URL and with canonical key
                cache.put(request.url, clone.clone());
                cache.put('/test/index.php?r=pos', clone);
            });
            return response;
        }
        throw new Error('Bad response: ' + response.status);
    } catch (err) {
        // Network failed — try cache
        const cache = await caches.open(CACHE_NAME);

        // Try exact URL first
        let cached = await cache.match(request.url);

        // Try canonical POS URL
        if (!cached) {
            cached = await cache.match('/test/index.php?r=pos');
        }

        // Try any matching entry in cache
        if (!cached) {
            const allCaches = await caches.keys();
            for (const cacheName of allCaches) {
                const c = await caches.open(cacheName);
                cached = await c.match('/test/index.php?r=pos');
                if (cached) break;
            }
        }

        if (cached) {
            return cached;
        }

        // No cache at all — return offline fallback page
        return new Response(offlineFallbackHTML(), {
            status: 200,
            headers: { 'Content-Type': 'text/html; charset=utf-8' }
        });
    }
}

// ================================================================
// MESSAGES from the POS page
// ================================================================
self.addEventListener('message', event => {
    if (!event.data) return;

    if (event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    // Store a pre-rendered HTML snapshot sent from the page
    if (event.data.type === 'CACHE_POS_HTML' && event.data.html) {
        const html = event.data.html;
        const blob = new Blob([html], { type: 'text/html; charset=utf-8' });
        const response = new Response(blob, {
            status: 200,
            headers: { 'Content-Type': 'text/html; charset=utf-8' }
        });
        caches.open(CACHE_NAME).then(cache => {
            cache.put('/test/index.php?r=pos', response);
        });
    }

    // Legacy: cache POS page by fetching its URL
    if (event.data.type === 'CACHE_POS_PAGE' && event.data.url) {
        fetch(event.data.url, { cache: 'reload' })
            .then(r => {
                if (r && r.ok) {
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put('/test/index.php?r=pos', r.clone());
                        cache.put(event.data.url, r);
                    });
                }
            })
            .catch(() => {});
    }

    // Trigger sync in all client pages
    if (event.data.type === 'SW_SYNC_TRIGGER') {
        self.clients.matchAll({ includeUncontrolled: true }).then(clients => {
            clients.forEach(client => {
                client.postMessage({ type: 'SW_SYNC_TRIGGER' });
            });
        });
    }
});

// ================================================================
// BACKGROUND SYNC
// ================================================================
self.addEventListener('sync', event => {
    if (event.tag === 'sync-pending-invoices') {
        event.waitUntil(
            self.clients.matchAll({ includeUncontrolled: true }).then(clients => {
                clients.forEach(client => {
                    client.postMessage({ type: 'SW_SYNC_TRIGGER' });
                });
            })
        );
    }
});

// ================================================================
// OFFLINE FALLBACK HTML
// ================================================================
function offlineFallbackHTML() {
    return `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>حمزة للعطور — أوفلاين</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: Arial, sans-serif;
    background: #1a1210;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    text-align: center;
    padding: 20px;
  }
  .card {
    background: #2a1f18;
    border: 2px solid rgba(201,168,76,0.35);
    border-radius: 20px;
    padding: 40px 30px;
    max-width: 480px;
    width: 100%;
    box-shadow: 0 8px 40px rgba(0,0,0,0.5);
  }
  .icon { font-size: 60px; margin-bottom: 18px; }
  h1 { color: #C9A84C; font-size: 22px; margin-bottom: 10px; }
  p  { color: #bbb; font-size: 14px; line-height: 1.8; margin-bottom: 16px; }
  .steps {
    background: rgba(201,168,76,0.08);
    border: 1px solid rgba(201,168,76,0.2);
    border-radius: 12px;
    padding: 14px 18px;
    text-align: right;
    margin-bottom: 20px;
    font-size: 13px;
    color: #ddd;
    line-height: 2;
  }
  .btn {
    display: inline-block;
    background: linear-gradient(135deg, #9E7A2A, #C9A84C);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 12px 26px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    margin: 4px;
    text-decoration: none;
  }
  .btn:hover { opacity: 0.88; }
  .btn.blue { background: linear-gradient(135deg, #1d4ed8, #3b82f6); }
</style>
</head>
<body>
<div class="card">
  <div class="icon">📡</div>
  <h1>الكاشير غير متاح الآن</h1>
  <p>لم يتم تحميل صفحة الكاشير مسبقاً في الذاكرة.<br>للعمل بدون إنترنت يجب فتح الصفحة مرة أولى وهي متصلة.</p>
  <div class="steps">
    <strong style="color:#C9A84C">للإعداد الصحيح:</strong><br>
    ① شغّل XAMPP وافتح صفحة الكاشير<br>
    ② انتظر ثوانٍ بعد فتحها (يتم الحفظ تلقائياً)<br>
    ③ بعدها يمكن استخدامها بدون نت
  </div>
  <button class="btn" onclick="location.reload()">🔄 إعادة المحاولة</button>
  <a class="btn blue" href="/test/index.php?r=pos">🏪 فتح الكاشير</a>
</div>
</body>
</html>`;
}
