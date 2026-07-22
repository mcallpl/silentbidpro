<?php
// ============================================================
// SILENT BID PRO — Bidder Authentication
// Phone verification flow for auction guests.
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/page-meta.php';
require_once __DIR__ . '/includes/events.php';

if (isAuthenticated()) {
    header('Location: items.php');
    exit;
}

// The auction this sign-in will join (?event= link, session pin, or the
// open event) — displayed PROMINENTLY so a bidder always knows which
// auction they are registering for before they type anything.
$joining = getCurrentEvent();

$page_title = APP_NAME . ' - Bidder Sign In';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php renderPageMeta([
        'title' => $page_title,
        'description' => 'Sign in to Silent Bid Pro and start bidding in a polished, secure nonprofit auction experience.'
    ]); ?>
</head>
<body class="auth-page" data-vapid-public-key="<?php echo htmlspecialchars(VAPID_PUBLIC_KEY); ?>">
    <div class="container">
        <div class="auth-splash">
            <div class="splash-header">
                <h1><?php echo htmlspecialchars(APP_NAME); ?></h1>
                <p class="subtitle">Silent Auction Platform</p>
            </div>

            <?php if ($joining): ?>
            <div class="joining-banner" role="status">
                <span class="joining-label">You&rsquo;re joining</span>
                <span class="joining-event"><?php echo htmlspecialchars($joining['name']); ?></span>
                <?php if (!empty($joining['organization_name'])): ?>
                    <span class="joining-org">Hosted by <?php echo htmlspecialchars($joining['organization_name']); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

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

                <div class="form-group">
                    <label for="emailInput" class="form-label">Email <span class="optional">(optional)</span></label>
                    <input
                        type="email"
                        id="emailInput"
                        class="form-input"
                        placeholder="you@example.org"
                        autocomplete="email"
                    />
                    <p class="form-hint">Used for receipts and auction updates.</p>
                </div>

                <button id="sendCodeBtn" class="btn btn-primary btn-large">
                    <span class="btn-text">Send Verification Code</span>
                    <span class="btn-spinner" style="display: none;">Sending...</span>
                </button>

                <div id="phoneError" class="error-message" style="display: none;"></div>
            </div>

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

            <div class="auth-form success-message" id="successMessage" style="display: none;">
                <h2>Welcome!</h2>
                <p>Redirecting you to the auction...</p>
            </div>
        </div>
    </div>

    <script src="js/push-notifications.js"></script>
    <script src="js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            SBB.Auth.init();
        });
    </script>
</body>
</html>
