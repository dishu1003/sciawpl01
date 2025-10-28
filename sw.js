// Service Worker for Direct Selling Business Support System
const CACHE_NAME = 'ds-support-v1.0.0';
const STATIC_CACHE = 'ds-static-v1.0.0';
const DYNAMIC_CACHE = 'ds-dynamic-v1.0.0';

// Files to cache for offline functionality
const STATIC_FILES = [
    '/',
    '/admin/index.php',
    '/team/index.php',
    '/success.php',
    '/manifest.json',
    '/css/style.css',
    '/js/main.js',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://fonts.googleapis.com/css2/?family=Inter:wght@300;400;500;600;700;800;900&display=swap'
];

// Install event - cache static files
self.addEventListener('install', event => {
    console.log('Service Worker installing...');
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('Caching static files');
                return cache.addAll(STATIC_FILES);
            })
            .then(() => {
                console.log('Static files cached successfully');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('Error caching static files:', error);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('Service Worker activating...');
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                            console.log('Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('Service Worker activated');
                return self.clients.claim();
            })
    );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Skip external API calls and admin-specific files
    if (url.origin !== location.origin || url.pathname.includes('/admin/')) {
        return;
    }

    event.respondWith(
        caches.match(request)
            .then(cachedResponse => {
                if (cachedResponse) {
                    console.log('Serving from cache:', request.url);
                    return cachedResponse;
                }

                console.log('Fetching from network:', request.url);
                return fetch(request)
                    .then(response => {
                        // Check if response is valid
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }

                        // Clone the response for caching
                        const responseToCache = response.clone();

                        // Cache dynamic content
                        caches.open(DYNAMIC_CACHE)
                            .then(cache => {
                                cache.put(request, responseToCache);
                            });

                        return response;
                    })
                    .catch(error => {
                        console.log('Network request failed, serving offline page:', error);
                        
                        // Return offline page for navigation requests
                        if (request.mode === 'navigate') {
                            return caches.match('/offline.html');
                        }
                        
                        // Return cached version or fallback
                        return caches.match(request);
                    });
            })
    );
});

// Background sync for offline form submissions
self.addEventListener('sync', event => {
    if (event.tag === 'background-sync-leads') {
        console.log('Background sync triggered for leads');
        event.waitUntil(syncLeads());
    }
});

// Push notifications
self.addEventListener('push', event => {
    console.log('Push notification received');
    
    const options = {
        body: event.data ? event.data.text() : 'New update available!',
        icon: '/icons/icon-192x192.png',
        badge: '/icons/badge-72x72.png',
        vibrate: [200, 100, 200],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            {
                action: 'explore',
                title: 'View Details',
                icon: '/icons/checkmark.png'
            },
            {
                action: 'close',
                title: 'Close',
                icon: '/icons/xmark.png'
            }
        ]
    };

    event.waitUntil(
        self.registration.showNotification('Direct Selling Support', options)
    );
});

// Notification click handler
self.addEventListener('notificationclick', event => {
    console.log('Notification clicked:', event.action);
    
    event.notification.close();

    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow('/admin/index.php')
        );
    }
});

// Sync leads when back online
async function syncLeads() {
    try {
        // Get pending leads from IndexedDB
        const pendingLeads = await getPendingLeads();
        
        for (const lead of pendingLeads) {
            try {
                const response = await fetch('/api/leads', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(lead)
                });

                if (response.ok) {
                    console.log('Lead synced successfully:', lead);
                    await removePendingLead(lead.id);
                }
            } catch (error) {
                console.error('Error syncing lead:', error);
            }
        }
    } catch (error) {
        console.error('Error in background sync:', error);
    }
}

// Helper functions for IndexedDB
function getPendingLeads() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('DS_Support', 1);
        
        request.onsuccess = () => {
            const db = request.result;
            const transaction = db.transaction(['pendingLeads'], 'readonly');
            const store = transaction.objectStore('pendingLeads');
            const getAllRequest = store.getAll();
            
            getAllRequest.onsuccess = () => {
                resolve(getAllRequest.result);
            };
            
            getAllRequest.onerror = () => {
                reject(getAllRequest.error);
            };
        };
        
        request.onerror = () => {
            reject(request.error);
        };
    });
}

function removePendingLead(leadId) {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('DS_Support', 1);
        
        request.onsuccess = () => {
            const db = request.result;
            const transaction = db.transaction(['pendingLeads'], 'readwrite');
            const store = transaction.objectStore('pendingLeads');
            const deleteRequest = store.delete(leadId);
            
            deleteRequest.onsuccess = () => {
                resolve();
            };
            
            deleteRequest.onerror = () => {
                reject(deleteRequest.error);
            };
        };
        
        request.onerror = () => {
            reject(request.error);
        };
    });
}

// Message handling for communication with main thread
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'GET_VERSION') {
        event.ports[0].postMessage({ version: CACHE_NAME });
    }
});

console.log('Service Worker loaded successfully');
