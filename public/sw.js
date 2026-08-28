/* Rai Stats service worker */
const VERSION = 'v1';
const PRECACHE = `rai-stats-precache-${VERSION}`;
const RUNTIME = `rai-stats-runtime-${VERSION}`;

const PRECACHE_URLS = [
    '/offline.html',
    '/favicon.svg',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(PRECACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key !== PRECACHE && key !== RUNTIME)
                    .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Navigation requests: network-first, fall back to offline page.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html'))
        );
        return;
    }

    // Static build assets & icons: cache-first with background refresh.
    if (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.startsWith('/fonts/') ||
        /\.(?:css|js|woff2?|png|svg|jpg|jpeg|gif|webp)$/.test(url.pathname)
    ) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const network = fetch(request)
                    .then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(RUNTIME).then((cache) => cache.put(request, copy));
                        }
                        return response;
                    })
                    .catch(() => cached);
                return cached || network;
            })
        );
    }
});
