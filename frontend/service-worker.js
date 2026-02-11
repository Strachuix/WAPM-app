/**
 * Service Worker for WAPM GPS Tracker PWA
 * Zapewnia działanie offline i szybkie ładowanie
 * 
 * @version 1.0
 */

const CACHE_NAME = 'wapm-gps-v1';
const ASSETS_TO_CACHE = [
    '/index.html',
    '/manifest.json',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
];

/**
 * Instalacja Service Workera
 * Cachuje podstawowe zasoby
 */
self.addEventListener('install', (event) => {
    console.log('[SW] Installing...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Caching assets');
                return cache.addAll(ASSETS_TO_CACHE);
            })
            .then(() => self.skipWaiting())
            .catch((err) => {
                console.error('[SW] Installation failed:', err);
            })
    );
});

/**
 * Aktywacja Service Workera
 * Usuwa stare cache'e
 */
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating...');
    
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => {
                        if (cacheName !== CACHE_NAME) {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => self.clients.claim())
    );
});

/**
 * Przechwytywanie requestów
 * Strategia: Network First z fallbackiem na cache
 */
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Nie cachuj API calls (zawsze świeże dane)
    if (url.pathname.includes('/backend/api.php')) {
        event.respondWith(
            fetch(request).catch(() => {
                return new Response(
                    JSON.stringify({
                        error: true,
                        message: 'No internet connection',
                        offline: true
                    }),
                    {
                        headers: { 'Content-Type': 'application/json' },
                        status: 503
                    }
                );
            })
        );
        return;
    }
    
    // Network First dla pozostałych zasobów
    event.respondWith(
        fetch(request)
            .then((response) => {
                // Sklonuj odpowiedź (można ją użyć tylko raz)
                const responseClone = response.clone();
                
                // Zapisz do cache
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(request, responseClone);
                });
                
                return response;
            })
            .catch(() => {
                // Jeśli network fail, użyj cache
                return caches.match(request).then((cachedResponse) => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    
                    // Jeśli nie ma w cache, zwróć fallback
                    if (request.destination === 'document') {
                        return caches.match('/index.html');
                    }
                    
                    return new Response('Offline - resource not available', {
                        status: 503,
                        statusText: 'Service Unavailable'
                    });
                });
            })
    );
});

/**
 * Obsługa wiadomości z aplikacji
 */
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'CLEAR_CACHE') {
        caches.delete(CACHE_NAME).then(() => {
            console.log('[SW] Cache cleared');
        });
    }
});

/**
 * Synchronizacja w tle (Background Sync)
 * Można rozszerzyć o kolejkowanie requestów offline
 */
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-positions') {
        event.waitUntil(
            // Tutaj można dodać logikę synchronizacji
            Promise.resolve()
        );
    }
});

/**
 * Push Notifications (opcjonalnie)
 * Można dodać powiadomienia o alertach
 */
self.addEventListener('push', (event) => {
    const options = {
        body: event.data ? event.data.text() : 'Nowa aktualizacja GPS',
        icon: '/icon-192.png',
        badge: '/badge-72.png',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        }
    };
    
    event.waitUntil(
        self.registration.showNotification('WAPM GPS Tracker', options)
    );
});

/**
 * Obsługa kliknięcia w powiadomienie
 */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    
    event.waitUntil(
        clients.openWindow('/')
    );
});

console.log('[SW] Service Worker loaded');
