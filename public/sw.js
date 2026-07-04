// ============================================================
// SERVICE WORKER — Push Notification Handler
// Handles incoming push messages and displays notifications
// ============================================================

const NOTIFICATION_ICON = '/images/brand/icon-192.png';
const NOTIFICATION_BADGE = '/images/brand/icon-192.png';

// Handle incoming push messages
self.addEventListener('push', (event) => {
    if (!event.data) {
        console.log('[SW] Push received with no data');
        return;
    }

    try {
        const payload = event.data.json();
        const { title, body, icon, badge, tag, data, actions } = payload;

        const notificationOptions = {
            body: body || '',
            icon: icon || NOTIFICATION_ICON,
            badge: badge || NOTIFICATION_BADGE,
            tag: tag || 'sbb-notification',
            data: data || {},
            actions: actions || [
                {
                    action: 'open',
                    title: 'Open'
                },
                {
                    action: 'close',
                    title: 'Close'
                }
            ],
            requireInteraction: false,
            vibrate: [200, 100, 200]
        };

        event.waitUntil(
            self.registration.showNotification(title || 'Silent Bid Pro', notificationOptions)
        );

        console.log('[SW] Notification displayed:', title);
    } catch (error) {
        console.error('[SW] Error parsing push payload:', error);
        // Fallback notification if JSON parsing fails
        event.waitUntil(
            self.registration.showNotification('Silent Bid Pro', {
                body: 'You have a new notification',
                icon: NOTIFICATION_ICON,
                badge: NOTIFICATION_BADGE
            })
        );
    }
});

// Handle notification clicks
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked:', event.notification.tag);
    event.notification.close();

    // A "Close" action just dismisses — don't open a window.
    if (event.action === 'close') {
        return;
    }

    // Push payload data lives on event.notification.data, NOT on the event itself.
    // Reading event.data made this always undefined, so every click fell back to
    // /items.php instead of the outbid item.
    const data = event.notification.data || {};
    let urlToOpen = '/items.php';
    if (data.item_id) {
        urlToOpen = `/item.php?id=${data.item_id}`;
    } else if (data.url) {
        urlToOpen = data.url;
    }

    event.waitUntil(
        clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then((clientList) => {
            // Reuse an existing tab, but NAVIGATE it to the target first — the old
            // code just focused any tab containing "silentbidpro" without moving it.
            for (const client of clientList) {
                if ('focus' in client) {
                    if ('navigate' in client) {
                        return client.navigate(urlToOpen).then((c) => (c || client).focus());
                    }
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});

// Handle notification close
self.addEventListener('notificationclose', (event) => {
    console.log('[SW] Notification dismissed:', event.notification.tag);
});

// Handle Service Worker installation
self.addEventListener('install', (event) => {
    console.log('[SW] Service Worker installed');
    self.skipWaiting();
});

// Handle Service Worker activation
self.addEventListener('activate', (event) => {
    console.log('[SW] Service Worker activated');
    event.waitUntil(clients.claim());
});

// Handle periodic background sync (for subscription refresh)
self.addEventListener('sync', (event) => {
    if (event.tag === 'refresh-subscription') {
        console.log('[SW] Refreshing subscription');
        // Subscription refresh is handled client-side in push-notifications.js
    }
});
