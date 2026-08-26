// Daftari service worker — deliberately narrow in scope.
//
// This is an accounting/invoicing app: every page shows data that must be
// current (balances, invoice status, stock levels), and every mutating
// request needs a live session. So this worker does NOT do an app-shell
// offline strategy and never intercepts navigations, API calls, or form
// submissions — only network requests reaching here at all is enough to
// satisfy "add to home screen" installability criteria on both Android and
// desktop Chrome/Edge, since that just requires a registered worker with a
// fetch handler plus a valid manifest, not that it serve everything from
// cache.
//
// What it DOES do: cache-first the handful of static, content-hashed build
// assets (CSS/JS/fonts under /build/, plus the icons and favicon) so a
// repeat visit skips the network for those files entirely. Those are safe
// to cache aggressively because Vite's filename hash changes whenever their
// content does — a stale cache entry for an old hash is simply never
// requested again once a new build ships.

const CACHE_NAME = 'daftari-static-v1';

const CACHEABLE_PATH_PREFIXES = ['/build/', '/icons/'];
const CACHEABLE_EXACT_PATHS = ['/favicon.ico', '/apple-touch-icon.png', '/manifest.webmanifest'];

function isCacheable(url) {
    if (url.origin !== self.location.origin) {
        return false;
    }
    if (CACHEABLE_EXACT_PATHS.includes(url.pathname)) {
        return true;
    }
    return CACHEABLE_PATH_PREFIXES.some((prefix) => url.pathname.startsWith(prefix));
}

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);
    if (!isCacheable(url)) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cached) => {
            if (cached) {
                return cached;
            }
            return fetch(event.request).then((response) => {
                if (response.ok) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                }
                return response;
            });
        })
    );
});
