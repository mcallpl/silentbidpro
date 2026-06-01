<?php
// ============================================================
// SILENT BID BUDDY — Authentication Splash
// Landing page with phone entry and verification flow
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

// PERSISTENT SESSION: Users should NOT need to re-verify every visit
// Session persists for 30 days via HTTP-only secure cookie
// This is stored in the 'session_token' cookie and validated on every page load
if (isAuthenticated()) {
    // User has valid session - redirect to items page (all available items)
    header('Location: /silentbidbuddy/items.php');
    exit;
}

$page_title = APP_NAME . ' - Bid Now';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="/silentbidbuddy/css/main.css">
    <link rel="stylesheet" href="/silentbidbuddy/css/mobile.css">
</head>
<body class="auth-page">
    <div class="container">
        <div class="auth-splash">
            <!-- Logo/Header -->
            <div class="splash-header">
                <h1><?php echo htmlspecialchars(APP_NAME); ?></h1>
                <p class="subtitle">Silent Auction Platform</p>
            </div>

            <!-- Phone Entry Form -->
            <div class="auth-form" id="phoneForm">
                <h2>Sign Up to Bid</h2>
                <p class="form-description">Enter your info and we'll send a verification code.</p>

                <div class="form-group">
                    <label for="nameInput" class="form-label">Your Name <span class="required">*</span></label>
                    <input
                        type="text"
                        id="nameInput"
                        class="form-input"
                        placeholder="Your Name"
                        autocomplete="name"
                        required
                    />
                </div>

                <div class="form-group">
                    <label for="phoneInput" class="form-label">Phone Number <span class="required">*</span></label>
                    <input
                        type="tel"
                        id="phoneInput"
                        class="form-input"
                        placeholder="(555) 123-4567"
                        autocomplete="tel"
                        inputmode="tel"
                        required
                    />
                </div>

                <button id="sendCodeBtn" class="btn btn-primary btn-large">
                    <span class="btn-text">Send Verification Code</span>
                    <span class="btn-spinner" style="display: none;">Sending...</span>
                </button>

                <div id="phoneError" class="error-message" style="display: none;"></div>
            </div>

            <!-- Code Verification Form (Hidden initially) -->
            <div class="auth-form" id="codeForm" style="display: none;">
                <h2>Enter Your Code</h2>
                <p class="form-description">Check your SMS for the 6-digit code.</p>

                <div class="form-group">
                    <input
                        type="text"
                        id="codeInput"
                        class="form-input code-input"
                        placeholder="000000"
                        inputmode="numeric"
                        maxlength="6"
                        autocomplete="one-time-code"
                    />
                </div>

                <button id="verifyCodeBtn" class="btn btn-primary btn-large">
                    <span class="btn-text">Verify & Continue</span>
                    <span class="btn-spinner" style="display: none;">Verifying...</span>
                </button>

                <button id="backBtn" class="btn btn-secondary">
                    Back to Phone Entry
                </button>

                <div id="codeError" class="error-message" style="display: none;"></div>
            </div>

            <!-- Success Message (Hidden initially) -->
            <div class="auth-form success-message" id="successMessage" style="display: none;">
                <h2>🎉 Welcome!</h2>
                <p>Redirecting you to the auction...</p>
            </div>
        </div>
    </div>

    <script src="/silentbidbuddy/js/app.js"></script>
    <script>
        // Initialize auth flow
        document.addEventListener('DOMContentLoaded', function() {
            SBB.Auth.init();
        });
    </script>
</body>
