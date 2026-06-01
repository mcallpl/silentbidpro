/**
 * SILENT BID BUDDY — Main Application JavaScript
 * Authentication, session management, API communication
 */

window.SBB = window.SBB || {};

// ============================================================
// API Communication
// ============================================================
SBB.API = {
    async post(endpoint, data = {}) {
        const options = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        };

        // Include session token if available
        const token = localStorage.getItem('session_token');
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
        const token = localStorage.getItem('session_token');
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
        const name = nameInput.value.trim();
        const phone = phoneInput.value.trim();
        const error = document.getElementById('phoneError');

        error.style.display = 'none';

        if (!name || !phone) {
            error.textContent = 'Please enter your name and phone number';
            error.style.display = 'block';
            return;
        }

        // Show loading
        const sendCodeBtn = document.getElementById('sendCodeBtn');
        sendCodeBtn.classList.add('loading');
        sendCodeBtn.querySelector('.btn-text').style.display = 'none';
        sendCodeBtn.querySelector('.btn-spinner').style.display = 'inline';

        try {
            const response = await SBB.API.post('/silentbidbuddy/api/auth/send-code.php', { phone });

            if (response.status === 'ok') {
                this.currentPhone = phone;
                this.currentName = name;
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
            const response = await SBB.API.post('/silentbidbuddy/api/auth/verify-code.php', {
                phone: this.currentPhone,
                full_name: this.currentName,
                code: code
            });

            if (response.status === 'ok') {
                // Save session token and user info to localStorage
                localStorage.setItem('session_token', response.session_token);
                localStorage.setItem('user_id', response.user.id);
                localStorage.setItem('user_name', response.user.full_name || this.currentName);

                // Show success
                this.showSuccessMessage();

                // Redirect after 2 seconds to return URL or default item
                setTimeout(() => {
                    const params = new URLSearchParams(window.location.search);
                    const returnUrl = params.get('return');
                    if (returnUrl) {
                        window.location.href = decodeURIComponent(returnUrl);
                    } else {
                        window.location.href = '/silentbidbuddy/item.php?id=1';
                    }
                }, 2000);
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
        window.location.href = '/silentbidbuddy/index.php';
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
    }
};

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SBB;
}
