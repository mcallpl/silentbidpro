/**
 * SILENT BID PRO — Push Notifications Handler
 * Manages browser push notification subscriptions
 */

SBB.PushNotifications = {
    vapidPublicKey: null,
    registration: null,
    subscription: null,
    isSupported: false,
    permissionGranted: false,

    /**
     * Initialize push notifications
     * @param {string} vapidPublicKey VAPID public key from server
     */
    async init(vapidPublicKey) {
        this.vapidPublicKey = vapidPublicKey;

        // Check browser support
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.warn('[PUSH] Browser does not support push notifications');
            this.isSupported = false;
            return;
        }

        this.isSupported = true;
        console.log('[PUSH] Push notifications supported');

        try {
            // Register Service Worker
            this.registration = await navigator.serviceWorker.register('/public/sw.js', {
                scope: '/'
            });
            console.log('[PUSH] Service Worker registered:', this.registration);

            // Check existing subscription
            this.subscription = await this.registration.pushManager.getSubscription();
            if (this.subscription) {
                console.log('[PUSH] Already subscribed');
                this.permissionGranted = true;
            } else {
                console.log('[PUSH] Not yet subscribed');
                this.checkNotificationPermission();
            }

            // Periodic subscription refresh (every 24 hours)
            setInterval(() => this.refreshSubscription(), 24 * 60 * 60 * 1000);
        } catch (error) {
            console.error('[PUSH] Failed to register Service Worker:', error);
            this.isSupported = false;
        }
    },

    /**
     * Check current notification permission and show prompt if needed
     */
    checkNotificationPermission() {
        const permission = Notification.permission;

        if (permission === 'granted') {
            console.log('[PUSH] Notification permission already granted');
            this.permissionGranted = true;
            this.subscribe();
        } else if (permission === 'default') {
            console.log('[PUSH] Permission prompt will show when user interacts');
            this.showPermissionPrompt();
        } else if (permission === 'denied') {
            console.log('[PUSH] Notification permission denied by user');
            this.permissionGranted = false;
        }
    },

    /**
     * Show permission request banner
     */
    showPermissionPrompt() {
        const banner = document.createElement('div');
        banner.className = 'notification-permission-banner';
        banner.innerHTML = `
            <div class="banner-content">
                <span class="banner-text">Get push notifications for bid updates</span>
                <button class="btn btn-sm btn-primary" id="enableNotificationsBtn">Enable</button>
                <button class="btn btn-sm btn-secondary" id="dismissNotificationsBtn">Maybe Later</button>
            </div>
        `;

        document.body.appendChild(banner);

        document.getElementById('enableNotificationsBtn').addEventListener('click', () => {
            this.requestPermission();
            banner.remove();
        });

        document.getElementById('dismissNotificationsBtn').addEventListener('click', () => {
            banner.remove();
        });
    },

    /**
     * Request notification permission from user
     */
    async requestPermission() {
        try {
            const permission = await Notification.requestPermission();
            console.log('[PUSH] Permission result:', permission);

            if (permission === 'granted') {
                this.permissionGranted = true;
                this.subscribe();
            } else {
                this.permissionGranted = false;
                console.log('[PUSH] User denied notification permission');
            }
        } catch (error) {
            console.error('[PUSH] Error requesting permission:', error);
        }
    },

    /**
     * Subscribe to push notifications
     */
    async subscribe() {
        if (!this.isSupported || !this.registration || !this.vapidPublicKey) {
            console.warn('[PUSH] Cannot subscribe: missing requirements');
            return;
        }

        try {
            // Convert VAPID public key from base64 to Uint8Array
            const vapidArray = this.urlBase64ToUint8Array(this.vapidPublicKey);

            // Subscribe to push notifications
            this.subscription = await this.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: vapidArray
            });

            console.log('[PUSH] Subscribed to push notifications');

            // Send subscription to server
            await this.sendSubscriptionToServer(this.subscription);

            // Show success message
            SBB.UI.showNotice('✓ Push notifications enabled', 'info');
        } catch (error) {
            console.error('[PUSH] Subscription failed:', error);
            SBB.UI.showNotice('Failed to enable notifications', 'error');
        }
    },

    /**
     * Send subscription endpoint to server with validation
     */
    async sendSubscriptionToServer(subscription) {
        try {
            // Validate subscription keys exist
            const authKey = subscription.getKey('auth');
            const p256dhKey = subscription.getKey('p256dh');

            if (!authKey || !p256dhKey) {
                console.error('[PUSH] Missing required subscription keys');
                throw new Error('Invalid subscription keys');
            }

            // Validate endpoint format
            if (!subscription.endpoint || !subscription.endpoint.startsWith('https://')) {
                console.error('[PUSH] Invalid endpoint:', subscription.endpoint);
                throw new Error('Invalid endpoint URL');
            }

            const response = await SBB.API.post('/api/notifications/subscribe.php', {
                endpoint: subscription.endpoint,
                auth_key: this.arrayBufferToBase64(authKey),
                p256dh_key: this.arrayBufferToBase64(p256dhKey),
                browser_type: this.getBrowserType()
            });

            if (response.status === 'success') {
                console.log('[PUSH] Subscription saved to server:', response.subscription_id);
                localStorage.setItem('sbb_push_subscription', JSON.stringify({
                    subscriptionId: response.subscription_id,
                    endpoint: subscription.endpoint,
                    timestamp: new Date().toISOString()
                }));
                return true;
            } else {
                console.error('[PUSH] Server rejected subscription:', response.message);
                return false;
            }
        } catch (error) {
            console.error('[PUSH] Failed to send subscription to server:', error);
            SBB.UI.showNotice('Failed to save notification settings', 'error');
            return false;
        }
    },

    /**
     * Unsubscribe from push notifications
     */
    async unsubscribe() {
        if (!this.subscription) {
            console.log('[PUSH] Not currently subscribed');
            return;
        }

        try {
            // Unsubscribe from browser push
            await this.subscription.unsubscribe();
            console.log('[PUSH] Unsubscribed from browser push');

            // Notify server
            await SBB.API.post('/api/notifications/unsubscribe.php', {
                endpoint: this.subscription.endpoint
            });

            localStorage.removeItem('sbb_push_subscription');
            this.subscription = null;
            SBB.UI.showNotice('✓ Push notifications disabled', 'info');
        } catch (error) {
            console.error('[PUSH] Unsubscription failed:', error);
        }
    },

    /**
     * Refresh subscription (check if still valid)
     */
    async refreshSubscription() {
        if (!this.registration) return;

        try {
            const subscription = await this.registration.pushManager.getSubscription();
            if (subscription) {
                this.subscription = subscription;
                console.log('[PUSH] Subscription refreshed and valid');
            } else {
                console.log('[PUSH] Subscription expired, re-subscribing');
                await this.subscribe();
            }
        } catch (error) {
            console.error('[PUSH] Subscription refresh failed:', error);
        }
    },

    /**
     * Convert base64 VAPID key to Uint8Array
     */
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    },

    /**
     * Convert ArrayBuffer to base64 string
     */
    arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    },

    /**
     * Detect browser type for logging
     */
    getBrowserType() {
        const ua = navigator.userAgent;
        if (ua.indexOf('Chrome') > -1) return 'chrome';
        if (ua.indexOf('Safari') > -1) return 'safari';
        if (ua.indexOf('Firefox') > -1) return 'firefox';
        if (ua.indexOf('Edge') > -1) return 'edge';
        return 'unknown';
    }
};

// Store reference for global access
window.SBB.PushNotifications = SBB.PushNotifications;
