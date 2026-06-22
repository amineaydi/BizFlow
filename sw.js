// ========================================
// 💼 BIZFLOW - Service Worker
// Enables offline functionality + caching
// ========================================

const CACHE_VERSION = 'bizflow-v1.0.0';
const CACHE_NAME = `bizflow-cache-${CACHE_VERSION}`;

// Files to cache for offline use
const PRECACHE_URLS = [
    '/',
    '/login.php',
    '/manifest.json'
];

// API endpoints (don't cache - always fresh)
const API_PATTERNS = [
    /\/pos_action\.php/,
    /\/admin_action\.php/,
    /\/auth_action\.php/
];

// ========================================
// 📦 INSTALL: Cache essential files
// ========================================
self.addEventListener('install', event => {
    console.log('[BizFlow SW] Installing v' + CACHE_VERSION);
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[BizFlow SW] Caching essential files');
                return cache.addAll(PRECACHE_URLS).catch(err => {
                    console.warn('[BizFlow SW] Some files failed to cache:', err);
                });
            })
            .then(() => self.skipWaiting())
    );
});

// ========================================
// 🚀 ACTIVATE: Clean old caches
// ========================================
self.addEventListener('activate', event => {
    console.log('[BizFlow SW] Activated v' + CACHE_VERSION);
    
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(name => {
                    if (name !== CACHE_NAME && name.startsWith('bizflow-')) {
                        console.log('[BizFlow SW] Removing old cache:', name);
                        return caches.delete(name);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// ========================================
// 🌐 FETCH: Network-first with cache fallback
// ========================================
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') return;
    
    // Skip cross-origin
    if (url.origin !== location.origin) return;
    
    // Skip API endpoints (always go to network)
    if (API_PATTERNS.some(pattern => pattern.test(url.pathname))) {
        return;
    }
    
    // Network-first strategy with cache fallback
    event.respondWith(
        fetch(request)
            .then(response => {
                // Cache successful responses
                if (response.ok && response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(request, responseClone);
                    });
                }
                return response;
            })
            .catch(() => {
                // Offline - try cache
                console.log('[BizFlow SW] Network failed, trying cache:', url.pathname);
                return caches.match(request).then(cached => {
                    if (cached) {
                        return cached;
                    }
                    
                    // Return offline page for navigation requests
                    if (request.mode === 'navigate') {
                        return caches.match('/offline.html').then(offline => {
                            return offline || new Response(
                                createOfflinePage(),
                                { headers: { 'Content-Type': 'text/html' } }
                            );
                        });
                    }
                    
                    return new Response('Offline', {
                        status: 503,
                        statusText: 'Offline'
                    });
                });
            })
    );
});

// ========================================
// 🔔 PUSH NOTIFICATIONS
// ========================================
self.addEventListener('push', event => {
    if (!event.data) return;
    
    let data = {};
    try {
        data = event.data.json();
    } catch (e) {
        data = { title: 'BizFlow', body: event.data.text() };
    }
    
    const options = {
        body: data.body || 'You have a new notification',
        icon: data.icon || 'https://api.dicebear.com/7.x/shapes/svg?seed=BizFlow&backgroundColor=3b82f6&size=192',
        badge: 'https://api.dicebear.com/7.x/shapes/svg?seed=BizFlow&backgroundColor=3b82f6&size=72',
        vibrate: [200, 100, 200],
        tag: data.tag || 'bizflow-notification',
        renotify: true,
        requireInteraction: data.important || false,
        data: data.url || '/',
        actions: data.actions || [
            { action: 'open', title: 'Open App' },
            { action: 'dismiss', title: 'Dismiss' }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title || 'BizFlow', options)
    );
});

// ========================================
// 🖱️ NOTIFICATION CLICK
// ========================================
self.addEventListener('notificationclick', event => {
    event.notification.close();
    
    if (event.action === 'dismiss') return;
    
    const url = event.notification.data || '/';
    
    event.waitUntil(
        clients.matchAll({ type: 'window' }).then(windowClients => {
            // Try to focus existing window
            for (const client of windowClients) {
                if (client.url.includes(url) && 'focus' in client) {
                    return client.focus();
                }
            }
            // Open new window
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});

// ========================================
// 🔄 BACKGROUND SYNC (for offline sales)
// ========================================
self.addEventListener('sync', event => {
    if (event.tag === 'sync-sales') {
        console.log('[BizFlow SW] Syncing pending sales...');
        event.waitUntil(syncPendingSales());
    }
});

async function syncPendingSales() {
    // Get pending sales from IndexedDB (future feature)
    // For now, just log
    console.log('[BizFlow SW] Background sync triggered');
    return Promise.resolve();
}

// ========================================
// 💬 MESSAGES FROM APP
// ========================================
self.addEventListener('message', event => {
    if (event.data === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

// ========================================
// 📄 OFFLINE PAGE
// ========================================
function createOfflinePage() {
    return `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline - BizFlow</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #0a0e1a;
            color: white;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .box {
            text-align: center;
            max-width: 400px;
        }
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%,100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(0.95); }
        }
        h1 {
            font-size: 28px;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        p {
            color: #9ca3af;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        button {
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(59,130,246,0.3);
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">📡</div>
        <h1>You're Offline</h1>
        <p>BizFlow needs an internet connection to sync your data. Please check your connection and try again.</p>
        <button onclick="location.reload()">🔄 Try Again</button>
    </div>
</body>
</html>`;
}

console.log('[BizFlow SW] Service Worker loaded');
