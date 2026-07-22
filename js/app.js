/**
 * SILENT BID PRO — Main Application JavaScript
 * Authentication, session management, API communication
 */

window.SBB = window.SBB || {};

// ============================================================
// API Communication
// ============================================================
SBB.API = {
    getSessionToken() {
        // Try localStorage first (from login flow)
        let token = localStorage.getItem('session_token');
        if (token) return token;

        // Try cookie as fallback (persistent session)
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'session_token') {
                return decodeURIComponent(value);
            }
        }
        return null;
    },

    async post(endpoint, data = {}) {
        const options = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        };

        // Include session token if available
        const token = this.getSessionToken();
        if (token) {
            options.headers['Authorization'] = 'Bearer ' + token;
        }

        const response = await fetch(endpoint, options);
        return await response.json();
    },

    async get(endpoint) {
        const options = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        };

        // Include session token if available
        const token = this.getSessionToken();
        if (token) {
            options.headers['Authorization'] = 'Bearer ' + token;
        }

        const response = await fetch(endpoint, options);
        return await response.json();
    }
};

// ============================================================
// Authentication Module
// ============================================================
SBB.Auth = {
    currentPhone: null,
    currentName: null,
    currentEmail: null,

    init() {
        this.setupEventListeners();
    },

    setupEventListeners() {
        // Phone form
        const sendCodeBtn = document.getElementById('sendCodeBtn');
        if (sendCodeBtn) {
            sendCodeBtn.addEventListener('click', () => this.sendCode());
        }

        const phoneInput = document.getElementById('phoneInput');
        if (phoneInput) {
            phoneInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') this.sendCode();
            });
        }

        // Code form
        const verifyCodeBtn = document.getElementById('verifyCodeBtn');
        if (verifyCodeBtn) {
            verifyCodeBtn.addEventListener('click', () => this.verifyCode());
        }

        const codeInput = document.getElementById('codeInput');
        if (codeInput) {
            codeInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') this.verifyCode();
            });
        }

        // Back button
        const backBtn = document.getElementById('backBtn');
        if (backBtn) {
            backBtn.addEventListener('click', () => this.showPhoneForm());
        }
    },

    async sendCode() {
        const nameInput = document.getElementById('nameInput');
        const phoneInput = document.getElementById('phoneInput');
        const emailInput = document.getElementById('emailInput');
        const name = nameInput.value.trim();
        const phone = phoneInput.value.trim();
        const email = emailInput ? emailInput.value.trim() : '';
        const error = document.getElementById('phoneError');

        error.style.display = 'none';

        if (!name || !phone) {
            error.textContent = 'Please enter your name and phone number';
            error.style.display = 'block';
            return;
        }

        if (email && !emailInput.checkValidity()) {
            error.textContent = 'Please enter a valid email address or leave it blank';
            error.style.display = 'block';
            return;
        }

        // Show loading
        const sendCodeBtn = document.getElementById('sendCodeBtn');
        sendCodeBtn.classList.add('loading');
        sendCodeBtn.querySelector('.btn-text').style.display = 'none';
        sendCodeBtn.querySelector('.btn-spinner').style.display = 'inline';

        try {
            const response = await SBB.API.post('/api/auth/send-code.php', { phone });

            if (response.status === 'ok') {
                this.currentPhone = phone;
                this.currentName = name;
                this.currentEmail = email;
                this.showCodeForm();
            } else {
                error.textContent = response.message || 'Failed to send code';
                error.style.display = 'block';
            }
        } catch (err) {
            error.textContent = 'Network error. Please try again.';
            error.style.display = 'block';
        } finally {
            sendCodeBtn.classList.remove('loading');
            sendCodeBtn.querySelector('.btn-text').style.display = 'inline';
            sendCodeBtn.querySelector('.btn-spinner').style.display = 'none';
        }
    },

    async verifyCode() {
        const codeInput = document.getElementById('codeInput');
        const code = codeInput.value.trim();
        const error = document.getElementById('codeError');

        error.style.display = 'none';

        if (!code || code.length !== 6) {
            error.textContent = 'Please enter the 6-digit code';
            error.style.display = 'block';
            return;
        }

        // Show loading
        const verifyCodeBtn = document.getElementById('verifyCodeBtn');
        verifyCodeBtn.classList.add('loading');
        verifyCodeBtn.querySelector('.btn-text').style.display = 'none';
        verifyCodeBtn.querySelector('.btn-spinner').style.display = 'inline';

        try {
            const urlParams = new URLSearchParams(window.location.search);
            const response = await SBB.API.post('/api/auth/verify-code.php', {
                phone: this.currentPhone,
                full_name: this.currentName,
                email: this.currentEmail,
                code: code,
                // The auction link they entered through — bonds their account
                // to that event (user_events) on the server.
                event: urlParams.get('event') || ''
            });

            if (response.status === 'ok') {
                // Save session token and user info to localStorage
                localStorage.setItem('session_token', response.session_token);
                localStorage.setItem('user_id', response.user.id);
                localStorage.setItem('user_name', response.user.full_name || this.currentName);
                if (response.user.email) {
                    localStorage.setItem('user_email', response.user.email);
                }

                // Route the bidder to THEIR auction:
                //  - explicit ?event= link or return URL wins;
                //  - one membership -> straight to that auction;
                //  - several -> let them choose from a dropdown.
                const params = new URLSearchParams(window.location.search);
                const returnUrl = params.get('return');
                const explicitEvent = params.get('event');
                const myEvents = (response.events || []).filter(ev => ev.status !== 'closed');

                if (!returnUrl && !explicitEvent && myEvents.length > 1) {
                    this.showEventChooser(myEvents);
                } else {
                    this.showSuccessMessage();
                    setTimeout(() => {
                        let dest = SBB.Utils.safeInternalPath(returnUrl, 'items.php');
                        if (!returnUrl) {
                            const slug = explicitEvent || (myEvents.length === 1 ? myEvents[0].slug : '');
                            if (slug) dest = 'items.php?event=' + encodeURIComponent(slug);
                        }
                        window.location.href = dest;
                    }, 2000);
                }
            } else {
                error.textContent = response.message || 'Invalid code';
                error.style.display = 'block';
            }
        } catch (err) {
            error.textContent = 'Network error. Please try again.';
            error.style.display = 'block';
        } finally {
            verifyCodeBtn.classList.remove('loading');
            verifyCodeBtn.querySelector('.btn-text').style.display = 'inline';
            verifyCodeBtn.querySelector('.btn-spinner').style.display = 'none';
        }
    },

    showPhoneForm() {
        document.getElementById('phoneForm').style.display = 'block';
        document.getElementById('codeForm').style.display = 'none';
        document.getElementById('phoneInput').focus();
    },

    showCodeForm() {
        document.getElementById('phoneForm').style.display = 'none';
        document.getElementById('codeForm').style.display = 'block';
        document.getElementById('codeInput').focus();
    },

    showSuccessMessage() {
        document.getElementById('codeForm').style.display = 'none';
        document.getElementById('successMessage').style.display = 'block';
    },

    // Signed in and a member of several auctions with no explicit link:
    // let them pick which one to enter (dropdown), then pin via ?event=.
    showEventChooser(events) {
        document.getElementById('codeForm').style.display = 'none';
        const box = document.createElement('div');
        box.className = 'auth-form event-chooser';
        box.innerHTML =
            '<h2>Welcome back!</h2>' +
            '<p class="form-description">You&rsquo;re part of more than one auction. Which one are you joining today?</p>' +
            '<div class="form-group">' +
            '<label class="form-label" for="eventChooserSelect">Your auctions</label>' +
            '<select id="eventChooserSelect" class="form-input"></select>' +
            '</div>' +
            '<button type="button" class="btn btn-primary btn-block" id="eventChooserGo">Enter auction</button>';
        const sel = box.querySelector('#eventChooserSelect');
        events.forEach(ev => {
            const opt = document.createElement('option');
            opt.value = ev.slug;
            opt.textContent = ev.name + (ev.status === 'open' ? ' — live now' : '');
            sel.appendChild(opt);
        });
        box.querySelector('#eventChooserGo').addEventListener('click', () => {
            window.location.href = 'items.php?event=' + encodeURIComponent(sel.value);
        });
        const parent = document.getElementById('codeForm').parentNode;
        parent.appendChild(box);
    }
};

// ============================================================
// Session Management
// ============================================================
SBB.Session = {
    getToken() {
        return localStorage.getItem('session_token');
    },

    getUserId() {
        return localStorage.getItem('user_id');
    },

    isAuthenticated() {
        return !!this.getToken();
    },

    logout() {
        localStorage.removeItem('session_token');
        localStorage.removeItem('user_id');
        localStorage.removeItem('user_name');
        localStorage.removeItem('user_email');
        window.location.href = 'bid.php';
    }
};

// ============================================================
// Public UI
// ============================================================
SBB.UI = {
    init() {
        this.setupPublicMenu();
    },

    setupPublicMenu() {
        const toggle = document.querySelector('.js-public-menu-toggle');
        const menu = document.getElementById('publicMenu');
        const overlay = document.getElementById('publicMenuOverlay');
        const close = document.querySelector('.js-public-menu-close');
        const logout = document.querySelector('.js-public-logout');

        if (!toggle || !menu || !overlay) return;

        const openMenu = () => {
            menu.hidden = false;
            overlay.hidden = false;
            document.body.classList.add('public-menu-open');
            toggle.setAttribute('aria-expanded', 'true');
            close?.focus();
        };

        const closeMenu = () => {
            menu.hidden = true;
            overlay.hidden = true;
            document.body.classList.remove('public-menu-open');
            toggle.setAttribute('aria-expanded', 'false');
        };

        toggle.addEventListener('click', openMenu);
        close?.addEventListener('click', closeMenu);
        overlay.addEventListener('click', closeMenu);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !menu.hidden) closeMenu();
        });

        logout?.addEventListener('click', async () => {
            logout.disabled = true;
            try {
                await SBB.API.post('/api/auth/logout.php');
            } catch (err) {
                // Local cleanup still signs the user out if the network call fails.
            } finally {
                SBB.Session.logout();
            }
        });
    },

    showNotice(message, type = 'info') {
        let notice = document.getElementById('sbbNotice');
        if (!notice) {
            notice = document.createElement('div');
            notice.id = 'sbbNotice';
            notice.className = 'sbb-notice';
            notice.setAttribute('role', 'status');
            document.body.appendChild(notice);
        }

        notice.textContent = message;
        notice.className = `sbb-notice ${type === 'error' ? 'is-error' : 'is-info'} is-visible`;

        window.clearTimeout(notice.dismissTimer);
        notice.dismissTimer = window.setTimeout(() => {
            notice.classList.remove('is-visible');
        }, 3500);
    }
};

// ============================================================
// Utilities
// ============================================================
SBB.Utils = {
    formatCurrency(amount) {
        return '$' + parseFloat(amount).toFixed(2);
    },

    formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return mins + ':' + (secs < 10 ? '0' : '') + secs;
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    // Sanitize a caller-supplied redirect target so it can only ever point back
    // into THIS site. Blocks open-redirects and javascript:/data: URLs (e.g.
    // bid.php?return=https://evil.com or return=javascript:alert(1)). Accepts
    // only a same-path relative URL; anything else falls back to `fallback`.
    safeInternalPath(raw, fallback) {
        fallback = fallback || 'items.php';
        if (!raw) return fallback;
        let decoded;
        try { decoded = decodeURIComponent(raw); } catch (e) { return fallback; }
        decoded = decoded.trim();
        // Reject absolute URLs, protocol-relative URLs, scheme (javascript:, data:,
        // http:), backslashes, and control chars.
        if (/^[a-z][a-z0-9+.-]*:/i.test(decoded)) return fallback; // has a scheme
        if (/^\/\//.test(decoded)) return fallback;                // //evil.com
        if (/[\\\x00-\x1f]/.test(decoded)) return fallback;
        if (decoded.charAt(0) === '/') return fallback;            // keep it relative
        return decoded;
    }
};

// ============================================================
// Item sharing (Web Share API with copy-link fallback)
// ============================================================
SBB.Share = {
    init() {
        document.querySelectorAll('.js-share-item').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const url = btn.dataset.shareUrl || window.location.href;
                const title = btn.dataset.shareTitle || document.title;
                const text = 'Check out "' + title + '" in this silent auction — every bid supports the cause!';
                if (navigator.share) {
                    try {
                        await navigator.share({ title, text, url });
                        return;
                    } catch (err) {
                        if (err && err.name === 'AbortError') return; // user cancelled
                    }
                }
                try {
                    await navigator.clipboard.writeText(url);
                    SBB.UI.showNotice('Link copied — paste it anywhere to share!');
                } catch (err) {
                    window.prompt('Copy this link to share:', url);
                }
            });
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    if (window.SBB && window.SBB.UI) {
        window.SBB.UI.init();
    }

    if (window.SBB && window.SBB.Share) {
        window.SBB.Share.init();
    }

    // Returning from Stripe card setup: confirm and clean the URL.
    if (/[?&]card_saved=1/.test(window.location.search)) {
        SBB.UI.showNotice('✅ Card saved — you\'re all set to bid! You\'ll only be charged if you win.');
        const clean = window.location.href.replace(/([?&])card_saved=1(&|$)/, (m, p1, p2) => (p2 === '&' ? p1 : ''));
        window.history.replaceState({}, '', clean);
    }

    // Initialize push notifications if user is authenticated
    if (window.SBB && window.SBB.PushNotifications && SBB.API.getSessionToken()) {
        const vapidKey = document.body.getAttribute('data-vapid-public-key');
        if (vapidKey) {
            SBB.PushNotifications.init(vapidKey);
        }
    }
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SBB;
}
