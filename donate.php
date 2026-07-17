<?php
// ============================================================
// SILENT BID PRO — Donate Now
// Direct contributions to the event's organization, open to
// everyone (donors don't have to be bidders). Branded per event.
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/events.php';
require_once __DIR__ . '/includes/public-nav.php';
require_once __DIR__ . '/includes/branding-helper.php';

$user = getCurrentUser();

// Resolve the event: explicit ?event=<id or slug>, else the pinned/active one.
$event = null;
$event_param = (string)($_GET['event'] ?? '');
if ($event_param !== '') {
    if (ctype_digit($event_param)) {
        $event = dbGetRow(
            "SELECT e.id, e.name, e.slug, e.donations_enabled, o.name AS organization_name
             FROM events e JOIN organizations o ON o.id = e.organization_id
             WHERE e.id = ? AND e.status IN ('open','draft')",
            [(int)$event_param]
        );
    } else {
        $event = dbGetRow(
            "SELECT e.id, e.name, e.slug, e.donations_enabled, o.name AS organization_name
             FROM events e JOIN organizations o ON o.id = e.organization_id
             WHERE e.slug = ? AND e.status IN ('open','draft')",
            [$event_param]
        );
    }
}
if (!$event) {
    $current = getCurrentEvent();
    if ($current) {
        $event = dbGetRow(
            "SELECT e.id, e.name, e.slug, e.donations_enabled, o.name AS organization_name
             FROM events e JOIN organizations o ON o.id = e.organization_id
             WHERE e.id = ?",
            [(int)$current['id']]
        );
    }
}

if (!$event || (int)$event['donations_enabled'] !== 1) {
    renderPublicMessagePage([
        'status' => 404,
        'title' => 'Donate',
        'heading' => 'Donations are not available right now',
        'message' => 'This event is not currently accepting direct donations.',
        'actions' => [
            ['href' => 'items.php', 'label' => 'Browse Auction Items', 'class' => 'btn-primary']
        ],
        'user' => $user
    ]);
}

$thanks = !empty($_GET['thanks']);
$page_title = 'Donate - ' . htmlspecialchars($event['organization_name']);
$branding = getBrandingData();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php renderPageMeta([
        'title' => htmlspecialchars_decode($page_title),
        'description' => 'Make a direct donation to ' . $event['organization_name'] . ' through Silent Bid Pro.'
    ]); ?>
</head>
<body class="donate-page" data-vapid-public-key="<?php echo htmlspecialchars(VAPID_PUBLIC_KEY); ?>">
    <?php renderPublicHeader(['back_href' => 'items.php', 'back_label' => '← Items', 'user' => $user]); ?>

    <div class="container donate-container">
        <?php if ($branding): ?>
            <?php renderEventBanner(['show_logo' => true, 'show_mission' => false]); ?>
        <?php endif; ?>

        <?php if ($thanks): ?>
            <section class="success-message">
                <div class="success-icon">💖</div>
                <h1>Thank you!</h1>
                <p class="success-text">Your donation to <?php echo htmlspecialchars($event['organization_name']); ?> means the world. A receipt is on its way from our payment processor.</p>
                <div class="action-buttons">
                    <a href="items.php" class="btn btn-primary btn-large">Back to the Auction</a>
                </div>
            </section>
        <?php else: ?>
            <section class="donate-form-section">
                <h1>💝 Donate to <?php echo htmlspecialchars($event['organization_name']); ?></h1>
                <p class="donate-lead">Every dollar goes to the cause — no bidding required.</p>

                <div class="donate-amounts" id="donateAmounts">
                    <?php foreach ([25, 50, 100, 250] as $preset): ?>
                        <button type="button" class="btn btn-secondary donate-preset" data-amount="<?php echo $preset; ?>">$<?php echo $preset; ?></button>
                    <?php endforeach; ?>
                </div>

                <div class="form-group donate-custom">
                    <label for="donateCustomAmount">Or enter your own amount</label>
                    <input type="number" id="donateCustomAmount" min="1" max="25000" step="1" placeholder="$" />
                </div>

                <button id="donateBtn" class="btn btn-primary btn-large">Donate Now</button>
                <div id="donateError" class="error-message" style="display: none;"></div>
                <p class="security-badge">🔒 Secure payment by Stripe</p>
            </section>
        <?php endif; ?>
    </div>

    <script src="js/app.js"></script>
    <script>
        (function() {
            const eventId = <?php echo (int)$event['id']; ?>;
            let selected = 0;

            document.querySelectorAll('.donate-preset').forEach((btn) => {
                btn.addEventListener('click', () => {
                    selected = parseFloat(btn.dataset.amount);
                    document.querySelectorAll('.donate-preset').forEach(b => b.classList.remove('is-active'));
                    btn.classList.add('is-active');
                    const custom = document.getElementById('donateCustomAmount');
                    if (custom) custom.value = '';
                });
            });

            const custom = document.getElementById('donateCustomAmount');
            if (custom) {
                custom.addEventListener('input', () => {
                    selected = parseFloat(custom.value) || 0;
                    document.querySelectorAll('.donate-preset').forEach(b => b.classList.remove('is-active'));
                });
            }

            const donateBtn = document.getElementById('donateBtn');
            const donateError = document.getElementById('donateError');
            if (donateBtn) {
                donateBtn.addEventListener('click', async () => {
                    if (!selected || selected < 1) {
                        donateError.textContent = 'Please choose or enter a donation amount.';
                        donateError.style.display = 'block';
                        return;
                    }
                    donateError.style.display = 'none';
                    donateBtn.disabled = true;
                    donateBtn.textContent = 'One moment…';
                    try {
                        const response = await SBB.API.post('/api/checkout/create-donation-session.php', {
                            amount: selected,
                            event_id: eventId
                        });
                        if (response.status === 'ok' && response.url) {
                            window.location.href = response.url;
                            return;
                        }
                        throw new Error(response.message || 'Could not start donation');
                    } catch (err) {
                        donateError.textContent = err.message || 'Something went wrong. Please try again.';
                        donateError.style.display = 'block';
                        donateBtn.disabled = false;
                        donateBtn.textContent = 'Donate Now';
                    }
                });
            }
        })();
    </script>
</body>
</html>
