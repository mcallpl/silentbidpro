/**
 * SILENT BID BUDDY — Stripe Checkout
 * Payment processing via Stripe Checkout
 */

document.addEventListener('DOMContentLoaded', async function() {
    const checkoutBtn = document.getElementById('checkoutBtn');
    const checkoutError = document.getElementById('checkoutError');

    checkoutBtn.addEventListener('click', async function() {
        // Show loading state
        checkoutBtn.classList.add('loading');
        checkoutBtn.querySelector('.btn-text').style.display = 'none';
        checkoutBtn.querySelector('.btn-spinner').style.display = 'inline';
        checkoutError.style.display = 'none';

        try {
            // Create checkout session
            const sessionResponse = await SBB.API.post('/silentbidbuddy/api/checkout/create-session.php', {
                item_id: window.SBB.itemId
            });

            if (!sessionResponse.session_id) {
                throw new Error(sessionResponse.error || 'Failed to create checkout session');
            }

            // Initialize Stripe
            const stripe = Stripe(sessionResponse.public_key);

            // Redirect to Stripe Checkout
            const result = await stripe.redirectToCheckout({
                sessionId: sessionResponse.session_id
            });

            if (result.error) {
                throw new Error(result.error.message);
            }
        } catch (error) {
            console.error('Checkout error:', error);
            checkoutError.textContent = error.message || 'Payment processing failed. Please try again.';
            checkoutError.style.display = 'block';

            // Reset button
            checkoutBtn.classList.remove('loading');
            checkoutBtn.querySelector('.btn-text').style.display = 'inline';
            checkoutBtn.querySelector('.btn-spinner').style.display = 'none';
        }
    });
});
