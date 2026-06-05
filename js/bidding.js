/**
 * SILENT BID BUDDY — Bidding Interface
 * Real-time updates, countdown timer, bid placement
 */

SBB.Bidding = {
    itemId: window.SBB.itemId,
    sessionToken: window.SBB.sessionToken,
    countdownInterval: null,
    feedInterval: null,
    pendingBid: null,

    init() {
        console.log('[INIT] Bidding.init() called');
        this.startCountdown();
        console.log('[INIT] startCountdown complete');
        this.loadBidFeed();
        console.log('[INIT] loadBidFeed complete');
        this.setupEventListeners();
        console.log('[INIT] setupEventListeners complete');
        this.startFeedRefresh();
        console.log('[INIT] startFeedRefresh complete');
    },

    setupEventListeners() {
        // Quick bid button
        const quickBidBtn = document.getElementById('quickBidBtn');
        if (quickBidBtn) {
            quickBidBtn.addEventListener('click', () => this.quickBid());
        }

        // Custom bid form
        const toggleBtn = document.querySelector('.toggle-custom-bid');
        console.log('[SETUP] Looking for .toggle-custom-bid button:', toggleBtn);

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const form = document.getElementById('customBidForm');
                if (form) {
                    form.classList.toggle('show');
                    console.log('[CLICK] Custom bid form toggled. Has show class:', form.classList.contains('show'));
                }
            });
        } else {
            console.error('[ERROR] .toggle-custom-bid button NOT FOUND');
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

        this.countdownInterval = setInterval(() => {
            const now = new Date().getTime();
            const endTime = new Date(window.SBB.auctionEndTime).getTime();
            const distance = Math.max(0, endTime - now);

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

    quickBid() {
        const nextMinimum = window.SBB.currentHighBid > 0
            ? window.SBB.currentHighBid + window.SBB.minIncrement
            : window.SBB.startingBid;

        this.showBidModal(nextMinimum);
    },

    customBid() {
        const customAmount = parseFloat(document.getElementById('customAmount').value);
        const maxAmount = document.getElementById('maxAmount').value
            ? parseFloat(document.getElementById('maxAmount').value)
            : null;

        if (!customAmount || customAmount <= 0) {
            alert('Please enter a valid bid amount');
            return;
        }

        if (maxAmount && maxAmount < customAmount) {
            alert('Max bid must be greater than or equal to your bid amount');
            return;
        }

        this.showBidModal(customAmount, maxAmount);
    },

    showBidModal(amount, maxBid = null) {
        this.pendingBid = { amount, maxBid };
        document.getElementById('modalBidAmount').textContent = SBB.Utils.formatCurrency(amount);
        document.getElementById('bidModal').style.display = 'block';
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
                max_bid_amount: this.pendingBid.maxBid
            });

            console.log('[BID RESPONSE]', response);

            if (response.status === 'success') {
                console.log('[BID SYNC] ✓ Bid placed successfully:', response);

                // Update UI
                this.updateItemDisplay(response);
                this.closeBidModal();

                // Refresh feed IMMEDIATELY - don't wait for next interval
                await this.loadBidFeed();

                // Show success message
                this.showSuccessNotification('Your bid was placed successfully! Refreshing live bids...');
            } else {
                console.error('[BID SYNC] ❌ Bid placement failed:', response);
                this.showErrorNotification(response.message || 'Bid failed');
            }
        } catch (err) {
            this.showErrorNotification('Network error. Please try again.');
        } finally {
            confirmBtn.classList.remove('loading');
            confirmBtn.textContent = 'Confirm Bid';
        }
    },

    updateItemDisplay(bidResponse) {
        // Update current high bid
        const currentBidAmount = document.querySelector('.current-bid-amount');
        if (currentBidAmount) {
            currentBidAmount.textContent = SBB.Utils.formatCurrency(bidResponse.new_high_bid);
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

        // Update bidder status
        const bidderStatus = document.querySelector('.bidder-status');
        if (bidderStatus) {
            bidderStatus.innerHTML = '<span class="badge badge-winning">You\'re Winning! 🏆</span>';
        }

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
        // Simple notification (could be enhanced with a toast library)
        console.log('[' + type.toUpperCase() + ']', message);
        alert(message); // Basic implementation
    },

    destroy() {
        if (this.countdownInterval) clearInterval(this.countdownInterval);
        if (this.feedInterval) clearInterval(this.feedInterval);
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    if (window.SBB.isAuctionOpen) {
        SBB.Bidding.init();
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (SBB.Bidding) SBB.Bidding.destroy();
});
