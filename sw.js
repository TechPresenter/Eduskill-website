/* =============================================================================
   EDUSKILL INDIA FOUNDATION — Service Worker (PWA offline support)
   Strategy: cache-first for static assets, network-first for pages (with an
   offline fallback). Bump CACHE_VERSION when assets change.
   ============================================================================ */
const CACHE_VERSION = 'pwf-v1';
const ASSET_CACHE = CACHE_VERSION + '-assets';
const PAGE_CACHE = CACHE_VERSION + '-pages';

// Precache the design system + logo (paths are relative to the SW scope).
const PRECACHE = [
    'assets/css/tailwind.css',
    'assets/css/premium.css',
    'assets/js/main.js',
    'assets/js/premium.js',
    'assets/js/forms.js',
    'assets/images/logo-256.webp',
    'assets/images/placeholder.svg',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(ASSET_CACHE).then((cache) =>
            Promise.allSettled(PRECACHE.map((u) => cache.add(u).catch(() => null)))
        ).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => !k.startsWith(CACHE_VERSION)).map((k) => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;
    // Never cache admin, api or form endpoints.
    if (/\/(admin|api|forms)\//.test(url.pathname)) return;

    const isAsset = /\.(css|js|svg|png|jpg|jpeg|webp|gif|woff2?)$/i.test(url.pathname);

    if (isAsset) {
        // Cache-first.
        event.respondWith(
            caches.match(req).then((cached) =>
                cached || fetch(req).then((res) => {
                    const copy = res.clone();
                    caches.open(ASSET_CACHE).then((c) => c.put(req, copy));
                    return res;
                }).catch(() => cached)
            )
        );
    } else {
        // Network-first for pages, fall back to cache.
        event.respondWith(
            fetch(req).then((res) => {
                const copy = res.clone();
                caches.open(PAGE_CACHE).then((c) => c.put(req, copy));
                return res;
            }).catch(() => caches.match(req).then((c) => c || caches.match('./')))
        );
    }
});
