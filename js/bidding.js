/**
 * SILENT BID PRO — Bidding Interface
 * Real-time updates, countdown timer, bid placement
 */

SBB.Bidding = {
    itemId: window.SBB.itemId,
    sessionToken: window.SBB.sessionToken,
    countdownInterval: null,
    feedInterval: null,
    pendingBid: null,

    init() {
        this.startCountdown();
        this.loadBidFeed();
        this.setupEventListeners();
        this.startFeedRefresh();
    },

    setupEventListeners() {
        // Quick bid button
        const quickBidBtn = document.getElementById('quickBidBtn');
        if (quickBidBtn) {
            quickBidBtn.addEventListener('click', () => this.quickBid());
        }

        // Buy It Now button
        const buyNowBtn = document.getElementById('buyNowBtn');
        if (buyNowBtn) {
            buyNowBtn.addEventListener('click', () => {
                const price = parseFloat(buyNowBtn.dataset.buyNowPrice);
                this.buyNow(price);
            });
        }

        // Custom bid form
        const toggleBtn = document.querySelector('.toggle-custom-bid');
        console.log('[SETUP] Looking for .toggle-custom-bid button:', toggleBtn);

        // Custom bid form toggle
        const customBidToggle = document.querySelector('.toggle-custom-bid');
        if (customBidToggle) {
            customBidToggle.addEventListener('click', function(e) {
                e.preventDefault();
                const form = document.getElementById('customBidForm');
                if (form) {
                    form.style.display = form.style.display === 'block' ? 'none' : 'block';
                }
            });
        }

        const customBidForm = document.getElementById('customBidForm');
        if (customBidForm) {
            customBidForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.customBid();
            });
        }

        // Bid modal
        const confirmBtn = document.getElementById('confirmBidBtn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => this.confirmBid());
        }

        const cancelBtn = document.getElementById('cancelBidBtn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => this.closeBidModal());
        }
    },

    startCountdown() {
        const countdownTimer = document.getElementById('countdownTimer');
        if (!countdownTimer) return;

        // Count down from the SERVER-provided remaining milliseconds using the
        // local monotonic clock, instead of parsing a MySQL datetime string with
        // new Date() — that returns Invalid Date on Safari/iOS (a "NaNs" timer)
        // and is otherwise parsed in the viewer's timezone (hours off). Falls back
        // to the epoch value if the remaining ms wasn't provided.
        const startedAt = Date.now();
        let baseRemaining = (typeof window.SBB.timeRemainingMs === 'number')
            ? window.SBB.timeRemainingMs
            : (typeof window.SBB.auctionEndEpochMs === 'number'
                ? Math.max(0, window.SBB.auctionEndEpochMs - Date.now())
                : 0);

        this.countdownInterval = setInterval(() => {
            const elapsed = Date.now() - startedAt;
            const distance = Math.max(0, baseRemaining - elapsed);

            if (distance === 0) {
                clearInterval(this.countdownInterval);
                countdownTimer.textContent = 'Auction Closed';
                countdownTimer.classList.add('time-expired');
                this.disableBidding();
                return;
            }

            const hours = Math.floor(distance / (1000 * 60 * 60));
            const mins = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const secs = Math.floor((distance % (1000 * 60)) / 1000);

            let timeStr = '';
            if (hours > 0) {
                timeStr = hours + 'h ' + mins + 'm ' + secs + 's';
            } else if (mins > 0) {
                timeStr = mins + ':' + (secs < 10 ? '0' : '') + secs;
            } else {
                timeStr = secs + 's';
            }

            countdownTimer.textContent = timeStr;

            // Turn red if less than 5 minutes
            const countdownSection = document.getElementById('countdownSection');
            if (distance < 5 * 60 * 1000) {
                countdownSection?.classList.add('urgency');
            }
        }, 1000);
    },

    startFeedRefresh() {
        // CRITICAL: Refresh every 2 seconds to ensure real-time bid sync
        // Must NOT be longer - users need to see other bids immediately
        this.feedInterval = setInterval(() => {
            this.loadBidFeed();
        }, 2000);
    },

    async loadBidFeed() {
        try {
            const url = '/api/bidding/get-live-feed.php?id=' + this.itemId + '&limit=20&t=' + Date.now();
            const response = await SBB.API.get(url);

            // CRITICAL: Log all responses for debugging sync issues
            console.log('[BID SYNC] Feed loaded at', new Date().toLocaleTimeString(), '- Bids:', response?.bids?.length || 0);

            if (response && response.status === 'ok' && Array.isArray(response.bids)) {
                this.renderBidFeed(response.bids);
                this.applyBidStateFromFeed(response);
                this.syncItemStateFromFeed(response);
            } else {
                console.warn('[BID SYNC] Invalid response:', response);
                // Don't clear the feed on error - keep showing last known bids
                if (!response || !response.bids) {
                    console.error('[BID SYNC] API returned empty or invalid bids');
                }
            }
        } catch (err) {
            console.error('[BID SYNC] Network error loading bids:', err);
            // Don't clear the feed on error
        }
    },

    renderBidFeed(bids) {
        const bidFeed = document.getElementById('bidFeed');
        if (!bidFeed) return;

        if (bids.length === 0) {
            bidFeed.innerHTML = '<p class="no-bids">No bids yet. Be the first!</p>';
            return;
        }

        let html = '';
        bids.forEach(bid => {
            html += `
                <div class="bid-item">
                    <div class="bid-amount">${SBB.Utils.formatCurrency(bid.bid_amount)}</div>
                    <div class="bid-details">
                        <span class="bidder-name">${SBB.Utils.escapeHtml(bid.bidder_name)}</span>
                        <span class="time-ago">${bid.time_ago}</span>
                    </div>
                </div>
            `;
        });

        bidFeed.innerHTML = html;
    },

    // Derive the viewer's winning/outbid/neutral state from the live feed poll
    // and paint the bid block accordingly (green = winning, red = outbid).
    applyBidStateFromFeed(response) {
        if (typeof response.viewer_is_winning === 'undefined') return;
        let state = 'neutral';
        if (response.viewer_has_bid) {
            state = response.viewer_is_winning ? 'winning' : 'outbid';
        }
        this.applyBidState(state);
    },

    // Keep the displayed high bid, quick-bid amount, and open/closed state in step
    // with the live feed. Without this, being outbid by another bidder left the big
    // price and Quick Bid button showing the old amount — so Quick Bid submitted a
    // stale (too-low) bid the server then rejected — and a Buy It Now by someone
    // else left this bidder's UI fully open.
    syncItemStateFromFeed(response) {
        // The server's next_minimum is authoritative — the Quick Bid button must
        // always show exactly what the server will accept, never local arithmetic.
        const serverMin = parseFloat(response.next_minimum);
        if (!isNaN(serverMin) && serverMin > 0) {
            window.SBB.nextMinimum = serverMin;
        }
        const high = parseFloat(response.current_high_bid);
        if (!isNaN(high) && high > 0 && high !== window.SBB.currentHighBid) {
            window.SBB.currentHighBid = high;
            window.SBB.hasBids = response.has_bids !== undefined ? !!response.has_bids : true;
            const nextMin = !isNaN(serverMin) && serverMin > 0 ? serverMin : high + window.SBB.minIncrement;
            const currentBidAmount = document.querySelector('.current-bid-amount');
            if (currentBidAmount) currentBidAmount.textContent = SBB.Utils.formatCurrency(high);
            const qb = document.querySelector('.quick-bid-amount');
            if (qb) qb.textContent = SBB.Utils.formatCurrency(nextMin);
            const nm = document.querySelector('.next-minimum-bid');
            if (nm) nm.textContent = 'Next minimum: ' + SBB.Utils.formatCurrency(nextMin);
        }
        if (response.is_closed) {
            if (this.countdownInterval) clearInterval(this.countdownInterval);
            const timer = document.getElementById('countdownTimer');
            if (timer) { timer.textContent = 'Auction Closed'; timer.classList.add('time-expired'); }
            this.disableBidding();
            window.SBB.isAuctionOpen = false;
        }
    },

    applyBidState(state) {
        const block = document.querySelector('.bid-block');
        if (!block) return;
        const prev = block.getAttribute('data-bid-state') || 'neutral';
        if (prev === state) return;
        block.setAttribute('data-bid-state', state);

        // Keep the colored status pill in sync.
        let pill = block.querySelector('.bid-status-indicator');
        if (state === 'neutral') {
            if (pill) pill.remove();
        } else {
            if (!pill) {
                pill = document.createElement('div');
                pill.className = 'bid-status-indicator';
                block.appendChild(pill);
            }
            pill.textContent = state === 'winning'
                ? "🏆 You're winning"
                : "🔴 You've been outbid — bid again to retake the lead";
        }

        // Fire the one-time alert only when you were leading and just got passed.
        if (state === 'outbid' && prev === 'winning') {
            block.classList.add('just-outbid');
            setTimeout(() => block.classList.remove('just-outbid'), 900);
            if (navigator.vibrate) {
                try { navigator.vibrate([120, 60, 120]); } catch (e) {}
            }
        }
    },

    quickBid() {
        // Prefer the server-provided minimum (kept fresh by the 2s feed poll and
        // by bid responses); fall back to local math only if it never arrived.
        const nextMinimum = (typeof window.SBB.nextMinimum === 'number' && window.SBB.nextMinimum > 0)
            ? window.SBB.nextMinimum
            : (window.SBB.hasBids && window.SBB.currentHighBid > 0
                ? window.SBB.currentHighBid + window.SBB.minIncrement
                : window.SBB.startingBid);

        this.showBidModal(nextMinimum);
    },

    customBid() {
        const customAmount = parseFloat(document.getElementById('customAmount').value);
        const maxAmount = document.getElementById('maxAmount').value
            ? parseFloat(document.getElementById('maxAmount').value)
            : null;

        if (!customAmount || customAmount <= 0) {
            SBB.UI.showNotice('Please enter a valid bid amount', 'error');
            return;
        }

        if (maxAmount && maxAmount < customAmount) {
            SBB.UI.showNotice('Max bid must be greater than or equal to your bid amount', 'error');
            return;
        }

        this.showBidModal(customAmount, maxAmount);
    },

    buyNow(price) {
        if (!price || price <= 0) return;
        // Buy Now wins immediately and closes the auction — confirm the commitment.
        this.showBidModal(price, null, true);
    },

    showBidModal(amount, maxBid = null, isBuyNow = false) {
        this.pendingBid = { amount, maxBid, isBuyNow };
        document.getElementById('modalBidAmount').textContent = SBB.Utils.formatCurrency(amount);
        const modal = document.getElementById('bidModal');

        // Buy It Now is IRREVERSIBLE (instant win + auction closes) — the modal
        // must look unmistakably different from an ordinary bid confirmation.
        const title = document.getElementById('modalTitle');
        if (title) title.textContent = isBuyNow ? '⚡ Buy It Now — are you sure?' : 'Confirm Your Bid';
        const lead = document.getElementById('modalBidLead');
        if (lead) lead.textContent = isBuyNow ? "You're about to BUY this item now for" : "You're about to bid";
        const warning = document.getElementById('modalBuyNowWarning');
        if (warning) warning.style.display = isBuyNow ? 'block' : 'none';

        const confirmBtn = document.getElementById('confirmBidBtn');
        if (confirmBtn) {
            confirmBtn.dataset.defaultLabel = confirmBtn.dataset.defaultLabel || confirmBtn.textContent;
            confirmBtn.textContent = isBuyNow ? 'Yes — Buy It Now' : (confirmBtn.dataset.defaultLabel || 'Confirm Bid');
            confirmBtn.classList.toggle('btn-buy-now-confirm', isBuyNow);
        }
        const cancelBtn = document.getElementById('cancelBidBtn');
        if (cancelBtn) cancelBtn.textContent = isBuyNow ? 'No, keep bidding' : 'Cancel';
        modal.style.display = 'block';
    },

    closeBidModal() {
        document.getElementById('bidModal').style.display = 'none';
        this.pendingBid = null;
    },

    async confirmBid() {
        if (!this.pendingBid) return;

        const confirmBtn = document.getElementById('confirmBidBtn');
        confirmBtn.classList.add('loading');
        confirmBtn.textContent = 'Placing bid...';

        console.log('[BID ATTEMPT] User placing bid:', {
            itemId: this.itemId,
            amount: this.pendingBid.amount,
            maxBid: this.pendingBid.maxBid,
            token: this.sessionToken ? 'YES' : 'NO'
        });

        try {
            const response = await SBB.API.post('/api/bidding/place-bid.php', {
                item_id: this.itemId,
                bid_amount: this.pendingBid.amount,
                max_bid_amount: this.pendingBid.maxBid,
                buy_now: !!this.pendingBid.isBuyNow
            });

            console.log('[BID RESPONSE]', response);

            if (response.status === 'success') {
                console.log('[BID SYNC] ✓ Bid placed successfully:', response);

                // Buy It Now closes the auction and wins immediately — reload so the
                // page reflects the closed/won state and the payment status.
                if (response.buy_now || response.is_closed) {
                    this.closeBidModal();
                    this.showSuccessNotification('🎉 You won this item with Buy It Now!');
                    setTimeout(() => window.location.reload(), 1200);
                    return;
                }

                if (typeof response.next_minimum === 'number') {
                    window.SBB.nextMinimum = response.next_minimum;
                }

                // Update UI
                this.updateItemDisplay(response);
                this.closeBidModal();

                // Refresh feed IMMEDIATELY - don't wait for next interval
                await this.loadBidFeed();

                // Show success message
                this.showSuccessNotification(response.proxy_message || 'Your bid was placed successfully.');
            } else if (response.code === 'card_required') {
                // First bid needs a card on file. Send them to Stripe's secure
                // card-setup page and bring them straight back here.
                this.closeBidModal();
                this.showNotification('To place your first bid, add a card — you\'ll only be charged if you win. Taking you to secure card setup…');
                await this.startCardSetup();
            } else if (response.code === 'meets_buy_now') {
                // Their bid amount reaches the Buy It Now price. Never convert
                // silently — re-open the modal as an explicit buy-now confirm.
                const price = parseFloat(response.buy_now_price);
                this.showBidModal(price, null, true);
            } else if (response.code === 'bid_too_low' && typeof response.next_minimum === 'number') {
                // Someone bid in the polling gap. Refresh to the fresh server
                // minimum and re-prompt at the new amount instead of dead-ending.
                window.SBB.nextMinimum = response.next_minimum;
                window.SBB.currentHighBid = response.current_high_bid || window.SBB.currentHighBid;
                const qb = document.querySelector('.quick-bid-amount');
                if (qb) qb.textContent = SBB.Utils.formatCurrency(response.next_minimum);
                this.showNotification('The price just went up — new minimum is ' + SBB.Utils.formatCurrency(response.next_minimum) + '.');
                if (!this.pendingBid.maxBid && !this.pendingBid.isBuyNow) {
                    this.showBidModal(response.next_minimum);
                } else {
                    this.closeBidModal();
                }
            } else {
                console.error('[BID SYNC] ❌ Bid placement failed:', response);
                this.closeBidModal();
                this.showErrorNotification(response.message || 'Bid failed');
            }
        } catch (err) {
            this.showErrorNotification('Network error. Please try again.');
        } finally {
            confirmBtn.classList.remove('loading');
            confirmBtn.classList.remove('btn-buy-now-confirm');
            confirmBtn.textContent = confirmBtn.dataset.defaultLabel || 'Confirm Bid';
        }
    },

    // Kick off Stripe Checkout in setup mode to save a card, returning to this
    // page (with ?card_saved=1) afterwards so the bidder can continue.
    async startCardSetup() {
        try {
            const returnPath = window.location.pathname.replace(/^\//, '') + window.location.search;
            const response = await SBB.API.post('/api/checkout/setup-card.php', { return_path: returnPath });
            if (response.status === 'ok' && response.url) {
                window.location.href = response.url;
            } else {
                this.showErrorNotification(response.message || 'Could not start card setup. Please try again.');
            }
        } catch (err) {
            this.showErrorNotification('Network error starting card setup. Please try again.');
        }
    },

    updateItemDisplay(bidResponse) {
        // Update current high bid
        window.SBB.currentHighBid = parseFloat(bidResponse.new_high_bid);
        window.SBB.hasBids = true;

        const currentBidAmount = document.querySelector('.current-bid-amount');
        if (currentBidAmount) {
            currentBidAmount.textContent = SBB.Utils.formatCurrency(bidResponse.new_high_bid);
        }

        const bidHeader = document.querySelector('.bid-header');
        if (bidHeader) {
            bidHeader.textContent = 'Current High Bid';
        }

        // Update next minimum
        const nextMinimum = document.querySelector('.next-minimum-bid');
        if (nextMinimum) {
            nextMinimum.textContent = 'Next minimum: ' + SBB.Utils.formatCurrency(bidResponse.next_minimum);
        }

        // Update quick bid button
        const quickBidBtn = document.querySelector('.quick-bid-amount');
        if (quickBidBtn) {
            quickBidBtn.textContent = SBB.Utils.formatCurrency(bidResponse.next_minimum);
        }

        const quickBidLabel = document.querySelector('.quick-bid-label');
        if (quickBidLabel) {
            quickBidLabel.textContent = 'Quick Bid';
        }

        // Update bidder status
        const bidderStatus = document.querySelector('.bidder-status');
        if (bidderStatus) {
            if (bidResponse.is_user_winning) {
                bidderStatus.innerHTML = '<span class="badge badge-winning">You\'re Winning!</span>';
            } else {
                bidderStatus.innerHTML = '<span class="bidder-name">Another bidder is still ahead</span>';
            }
        }

        // Paint the block green immediately after your own successful bid.
        this.applyBidState(bidResponse.is_user_winning ? 'winning' : 'outbid');

        // Show anti-sniping message if applied
        if (bidResponse.was_anti_sniping_applied) {
            this.showNotification('Anti-sniping activated! Auction extended by 2 minutes.');
        }
    },

    disableBidding() {
        const quickBidBtn = document.getElementById('quickBidBtn');
        const customForm = document.getElementById('customBidForm');

        if (quickBidBtn) {
            quickBidBtn.disabled = true;
            quickBidBtn.textContent = 'Auction Closed';
        }

        if (customForm) {
            customForm.style.display = 'none';
        }
    },

    showSuccessNotification(message) {
        this.showNotification(message, 'success');
    },

    showErrorNotification(message) {
        const bidError = document.getElementById('bidError');
        if (bidError) {
            bidError.textContent = message;
            bidError.style.display = 'block';

            setTimeout(() => {
                bidError.style.display = 'none';
            }, 5000);
        }
    },

    showNotification(message, type = 'info') {
        console.log('[' + type.toUpperCase() + ']', message);
        SBB.UI.showNotice(message, type === 'success' ? 'info' : type);
    },

    destroy() {
        if (this.countdownInterval) clearInterval(this.countdownInterval);
        if (this.feedInterval) clearInterval(this.feedInterval);
    }
};

// NOTE: init() is called from inline script in item.php after bidding.js loads
// Do NOT initialize here to avoid duplicate event listeners

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (SBB.Bidding) SBB.Bidding.destroy();
});
