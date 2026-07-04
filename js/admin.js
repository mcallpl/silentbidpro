// ============================================================
// SILENT BID PRO — Admin Dashboard JavaScript
// Handles authentication, metrics polling, CRUD operations
// ============================================================

const AdminDashboard = {
    config: {
        metricsRefreshRate: 2000, // 2 seconds
        apiBaseUrl: '/api/admin'
    },

    state: {
        isLoggedIn: false,
        currentSection: 'dashboard',
        currentPage: {},
        metricsInterval: null,
        selectedEventId: null,
        events: []
    },

    init(isLoggedIn) {
        this.state.isLoggedIn = isLoggedIn;

        if (isLoggedIn) {
            this.setupDashboard();
        } else {
            this.setupLogin();
        }
    },

    setupLogin() {
        const loginForm = document.getElementById('loginForm');
        const loginError = document.getElementById('loginError');

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const username = document.getElementById('adminUsername').value.trim();
            const password = document.getElementById('adminPassword').value;
            const btn = loginForm.querySelector('.btn');
            const btnText = btn.querySelector('.btn-text');
            const btnSpinner = btn.querySelector('.btn-spinner');

            if (!username || !password) {
                loginError.textContent = 'Username and password are required';
                loginError.style.display = 'block';
                return;
            }

            btnText.style.display = 'none';
            btnSpinner.style.display = 'inline';
            btn.disabled = true;

            try {
                const response = await fetch(this.config.apiBaseUrl + '/login-account.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });

                const data = await response.json();

                if (response.ok && data.status === 'ok') {
                    // Login successful, redirect to dashboard
                    // Role will be fetched from API by setupDashboard()
                    window.location.href = window.location.href;
                } else {
                    loginError.textContent = data.message || 'Invalid username or password';
                    loginError.style.display = 'block';
                }
            } catch (error) {
                loginError.textContent = 'Error: ' + error.message;
                loginError.style.display = 'block';
            } finally {
                btnText.style.display = 'inline';
                btnSpinner.style.display = 'none';
                btn.disabled = false;
                document.getElementById('adminPassword').value = '';
            }
        });
    },

    async setupDashboard() {
        // Get admin token from cookie
        this.getAdminTokenFromCookie();

        // Fetch actual admin info from API (server is the authority)
        try {
            const response = await fetch(this.config.apiBaseUrl + '/get-current-admin.php');
            const data = await response.json();

            if (response.ok && data.status === 'ok') {
                // Store super admin status for feature visibility
                this.state.isSuperAdmin = data.admin.is_super_admin;

                if (data.admin.is_super_admin) {
                    // Update title for super admin
                    const title = document.querySelector('.dashboard-title');
                    if (title) {
                        title.textContent = title.textContent.replace(' — Admin', ' — Super Admin');
                    }

                    // Show Events tab for super admins
                    const eventsTab = document.getElementById('eventsTab');
                    if (eventsTab) {
                        eventsTab.style.display = '';
                    }
                } else {
                    // For regular admins, check if they have any assigned events
                    this.checkIfAdminHasAssignedEvents(data.admin.id);
                }
            }
        } catch (error) {
            // Silently fail - display default "Admin", backend authorization is the real security
            console.debug('Could not fetch admin info:', error.message);
        }

        this.setupNav();
        this.setupEventSelector();
        this.setupModals();
        this.setupButtons();
        this.setupFilters();
        this.setupAdminControls();

        // Load initial dashboard
        this.showSection('dashboard');

        // Start metrics polling
        this.startMetricsPolling();
    },

    async checkIfAdminHasAssignedEvents(adminId) {
        try {
            const response = await fetch(`/api/admin/get-admin-assigned-events.php?admin_id=${adminId}`, {
                method: 'GET',
                headers: this.getAuthHeaders(),
                credentials: 'include'
            });

            const data = await response.json();

            if (response.ok && data.status === 'ok' && data.event_count > 0) {
                // Show Events tab for admins with assigned events
                const eventsTab = document.getElementById('eventsTab');
                if (eventsTab) {
                    eventsTab.style.display = '';
                }
            }
        } catch (error) {
            console.debug('Could not check assigned events:', error.message);
        }
    },

    getAdminTokenFromCookie() {
        // Session cookie is sent automatically by browser, no need to extract it
        // The API checks for admin_session_token cookie directly
    },

    getAuthHeaders() {
        return {
            'Content-Type': 'application/json'
            // Session cookie sent automatically by browser, no Authorization header needed
        };
    },

    setupNav() {
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                this.showSection(tab.dataset.section);
            });
        });
    },

    async setupEventSelector() {
        // Load available events and populate dropdown
        try {
            const response = await fetch(this.config.apiBaseUrl + '/get-events.php');
            const data = await response.json();

            if (response.ok && data.status === 'ok' && data.events && data.events.length > 0) {
                this.state.events = data.events;
                this.populateEventSelector(data.events);

                // Auto-select first event if available
                if (data.events.length > 0) {
                    const selector = document.getElementById('eventSelector');
                    selector.value = data.events[0].id;
                    this.selectEvent(data.events[0].id);
                }
            }
        } catch (error) {
            console.debug('Could not load events:', error.message);
        }
    },

    populateEventSelector(events) {
        const selector = document.getElementById('eventSelector');
        selector.innerHTML = '<option value="">-- Choose an event --</option>';

        events.forEach(event => {
            const option = document.createElement('option');
            option.value = event.id;
            option.textContent = event.name;
            selector.appendChild(option);
        });

        // Add change event listener
        selector.addEventListener('change', (e) => {
            if (e.target.value) {
                this.selectEvent(parseInt(e.target.value));
            } else {
                this.selectedEventId = null;
                this.reloadAllData();
            }
        });
    },

    selectEvent(eventId) {
        this.state.selectedEventId = eventId;

        // Update event info
        const event = this.state.events.find(e => e.id === eventId);
        const eventInfo = document.getElementById('eventInfo');
        if (event && eventInfo) {
            const dateStr = event.event_date ? new Date(event.event_date).toLocaleDateString() : 'No date';
            eventInfo.textContent = `📅 ${dateStr} | Status: ${event.status}`;
        }

        // Reload all data for this event
        this.reloadAllData();
    },

    reloadAllData() {
        // Reload current section with selected event filter
        if (this.state.currentSection === 'dashboard') {
            this.loadMetrics();
        } else if (this.state.currentSection === 'items') {
            this.loadItems(1);
        } else if (this.state.currentSection === 'transactions') {
            this.loadTransactions(1);
        } else if (this.state.currentSection === 'users') {
            this.loadUsers(1);
        } else if (this.state.currentSection === 'bids') {
            this.loadBids(1);
        }
    },

    showSection(section) {
        // Update nav tabs
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.section === section);
        });

        // Update sections
        document.querySelectorAll('.admin-section').forEach(sec => {
            sec.classList.toggle('active', sec.id === section + 'Section');
        });

        this.state.currentSection = section;

        // Load section data
        if (section === 'dashboard') {
            this.loadMetrics();
        } else if (section === 'items') {
            this.loadItems(1);
        } else if (section === 'transactions') {
            this.loadTransactions(1);
        } else if (section === 'users') {
            this.loadUsers(1);
        } else if (section === 'bids') {
            this.loadBids(1);
        } else if (section === 'events') {
            this.loadEvents();
        } else if (section === 'admins') {
            this.loadAdminAccounts();
        }
    },

    async loadBids(page = 1) {
        const container = document.getElementById('bidsContainer');

        // If no event selected, show message
        if (!this.state.selectedEventId) {
            container.innerHTML = '<p class="empty-state">Please select an event first.</p>';
            return;
        }

        try {
            // Get filter and sort state from UI or use defaults
            const filterType = document.getElementById('bidsFilterDropdown')?.value || 'all';
            const itemFilter = document.getElementById('bidsItemFilter')?.dataset.itemId || 0;
            const sortBy = document.getElementById('bidsSortBy')?.dataset.sortBy || 'b.created_at';
            const sortOrder = document.getElementById('bidsSortBy')?.dataset.sortOrder || 'DESC';

            container.innerHTML = '<p class="loading">Loading bids...</p>';

            let url = this.config.apiBaseUrl + '/get-bids.php?page=' + page + '&limit=50';
            url += '&event_id=' + this.state.selectedEventId;
            url += '&filter=' + encodeURIComponent(filterType);
            url += '&sort_by=' + encodeURIComponent(sortBy);
            url += '&sort_order=' + encodeURIComponent(sortOrder);
            if (itemFilter > 0) {
                url += '&item_id=' + itemFilter;
            }

            const response = await fetch(url, {
                headers: this.getAuthHeaders()
            });

            if (!response.ok) {
                throw new Error('Failed to load bids');
            }

            const data = await response.json();

            if (data.status !== 'ok' || !Array.isArray(data.bids)) {
                throw new Error('Invalid bids response');
            }

            const bids = data.bids;

            // Build filter controls HTML
            let filterHtml = `
                <div style="margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: center;">
                    <select id="bidsFilterDropdown" class="form-input" style="width: auto;">
                        <option value="all" ${filterType === 'all' ? 'selected' : ''}>All Bids</option>
                        <option value="active" ${filterType === 'active' ? 'selected' : ''}>Active Bids</option>
                        <option value="winning" ${filterType === 'winning' ? 'selected' : ''}>Winning Bids</option>
                    </select>
            `;

            if (itemFilter > 0) {
                filterHtml += `
                    <div style="padding: 0.5rem 1rem; background: #e8f4f8; border-radius: 4px; font-size: 0.9rem;">
                        <strong>Filtered to item:</strong> ${this.escapeHtml(bids[0]?.item_title || 'Unknown')}
                        <button id="clearItemFilter" style="margin-left: 1rem; padding: 0.25rem 0.75rem; background: #667eea; color: white; border: none; border-radius: 3px; cursor: pointer;">Show All Items</button>
                    </div>
                `;
            }

            filterHtml += `</div>`;

            if (bids.length === 0) {
                container.innerHTML = filterHtml + '<p style="color: #999; text-align: center; padding: 2rem;">No bids match the current filters</p>';
                this.setupBidsFilters(data);
                return;
            }

            // Build bids table with sortable headers
            const sortToggle = (column) => {
                const newOrder = (sortBy === column && sortOrder === 'DESC') ? 'ASC' : 'DESC';
                return `data-sort-by="${column}" data-sort-order="${newOrder}" style="cursor: pointer; user-select: none;"`;
            };

            const getSortIndicator = (column) => {
                if (sortBy !== column) return '';
                return sortOrder === 'ASC' ? ' ↑' : ' ↓';
            };

            let html = filterHtml + `
                <table class="data-table-inner" style="width: 100%;">
                    <thead>
                        <tr>
                            <th ${sortToggle('b.id')}>Bid ID${getSortIndicator('b.id')}</th>
                            <th ${sortToggle('i.title')}>Item${getSortIndicator('i.title')}</th>
                            <th ${sortToggle('u.full_name')}>Bidder${getSortIndicator('u.full_name')}</th>
                            <th ${sortToggle('b.bid_amount')}>Bid Amount${getSortIndicator('b.bid_amount')}</th>
                            <th ${sortToggle('i.current_high_bid')}>Current High${getSortIndicator('i.current_high_bid')}</th>
                            <th>Status</th>
                            <th ${sortToggle('b.created_at')}>Date/Time${getSortIndicator('b.created_at')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${bids.map(bid => `
                            <tr style="${bid.is_winning_bid ? 'background-color: #fffacd; border-left: 4px solid #ffd700;' : ''}">
                                <td>#${bid.id}</td>
                                <td>
                                    <strong style="cursor: pointer; color: #667eea;" class="filter-by-item" data-item-id="${bid.item_id}">
                                        ${this.escapeHtml(bid.item_title)}
                                    </strong>
                                    <br><small style="color: #999;">#${bid.item_number}</small>
                                </td>
                                <td>
                                    <strong>${this.escapeHtml(bid.full_name)}</strong>
                                    <br><span class="phone-number-clickable" data-phone="${bid.phone_number}" style="color: #667eea; text-decoration: underline; cursor: pointer; font-size: 0.9rem; font-weight: bold;"><strong>${this.formatPhoneNumber(bid.phone_number)}</strong></span>
                                </td>
                                <td style="text-align: right; font-weight: bold;">$${bid.bid_amount.toFixed(2)}</td>
                                <td style="text-align: right;">$${bid.current_high_bid.toFixed(2)}</td>
                                <td>
                                    <span class="badge ${bid.is_closed ? 'badge-closed' : 'badge-open'}">
                                        ${bid.is_closed ? 'Closed' : 'Open'}
                                    </span>
                                    ${bid.is_winning_bid ? '<span class="badge" style="background-color: #ffd700; color: #333; margin-left: 0.5rem;">✓ Winning</span>' : ''}
                                </td>
                                <td style="font-size: 0.9rem; color: #666;">${new Date(bid.created_at).toLocaleString()}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;

            container.innerHTML = html;

            // Setup event listeners
            this.setupBidsFilters(data);

        } catch (error) {
            container.innerHTML = `<p class="error">Error loading bids: ${error.message}</p>`;
            console.error('Error loading bids:', error);
        }
    },

    setupBidsFilters(data) {
        // Filter dropdown change
        const filterDropdown = document.getElementById('bidsFilterDropdown');
        if (filterDropdown) {
            filterDropdown.addEventListener('change', () => this.loadBids(1));
        }

        // Clear item filter
        const clearBtn = document.getElementById('clearItemFilter');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                const filterDiv = document.getElementById('bidsItemFilter');
                if (filterDiv) filterDiv.dataset.itemId = 0;
                this.loadBids(1);
            });
        }

        // Clickable item names to filter
        document.querySelectorAll('.filter-by-item').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const itemId = e.target.dataset.itemId;
                const filterDiv = document.getElementById('bidsContainer');
                if (!filterDiv.querySelector('#bidsItemFilter')) {
                    const div = document.createElement('div');
                    div.id = 'bidsItemFilter';
                    filterDiv.insertBefore(div, filterDiv.firstChild);
                }
                document.getElementById('bidsItemFilter').dataset.itemId = itemId;
                this.loadBids(1);
            });
        });

        // Clickable phone numbers - show call/text menu
        document.querySelectorAll('.phone-number-clickable').forEach(span => {
            span.addEventListener('click', (e) => {
                e.preventDefault();
                const phone = e.target.closest('.phone-number-clickable').dataset.phone;
                this.showPhoneMenu(phone, e);
            });
        });

        // Sortable column headers
        document.querySelectorAll('th[data-sort-by]').forEach(th => {
            th.addEventListener('click', () => {
                const sortBy = th.dataset.sortBy;
                const sortOrder = th.dataset.sortOrder || 'DESC';
                const sortDiv = document.getElementById('bidsSortBy');
                if (!sortDiv) {
                    const div = document.createElement('div');
                    div.id = 'bidsSortBy';
                    div.style.display = 'none';
                    document.getElementById('bidsContainer').appendChild(div);
                }
                document.getElementById('bidsSortBy').dataset.sortBy = sortBy;
                document.getElementById('bidsSortBy').dataset.sortOrder = sortOrder;
                this.loadBids(1);
            });
        });

        // Pagination
        this.renderPagination('bidsPagination', data.pagination, data.pagination.page, (p) => this.loadBids(p));
    },

    showPhoneMenu(phone, event) {
        // Remove any existing menu
        const existing = document.getElementById('phoneMenu');
        if (existing) existing.remove();

        // Create menu
        const menu = document.createElement('div');
        menu.id = 'phoneMenu';
        menu.style.cssText = `
            position: fixed;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            min-width: 180px;
        `;

        const rect = event.target.getBoundingClientRect();
        menu.style.top = (rect.bottom + 5) + 'px';
        menu.style.left = (rect.left - 50) + 'px';

        menu.innerHTML = `
            <div style="padding: 0;">
                <a href="tel:${phone}" style="display: block; padding: 12px 16px; color: #333; text-decoration: none; border-bottom: 1px solid #eee; cursor: pointer;">
                    <strong>📞 Call</strong>
                </a>
                <a href="sms:${phone}" style="display: block; padding: 12px 16px; color: #333; text-decoration: none; cursor: pointer;">
                    <strong>💬 Text</strong>
                </a>
            </div>
        `;

        document.body.appendChild(menu);

        // Close menu when clicking outside
        setTimeout(() => {
            const closeMenu = () => {
                if (menu && menu.parentNode) menu.remove();
                document.removeEventListener('click', closeMenu);
            };
            document.addEventListener('click', closeMenu);
        }, 100);
    },

    // ============================================================
    // METRICS & DASHBOARD
    // ============================================================

    startMetricsPolling() {
        this.loadMetrics();
        this.state.metricsInterval = setInterval(() => {
            if (this.state.currentSection === 'dashboard') {
                this.loadMetrics();
            }
        }, this.config.metricsRefreshRate);
    },

    async loadMetrics() {
        // If no event selected, skip metrics
        if (!this.state.selectedEventId) {
            return;
        }

        try {
            let url = this.config.apiBaseUrl + '/get-metrics.php';
            if (this.state.selectedEventId) {
                url += '?event_id=' + this.state.selectedEventId;
            }

            const response = await fetch(url, {
                headers: this.getAuthHeaders()
            });
            const data = await response.json();

            if (data.status === 'ok') {
                const metrics = data.metrics;
                const summary = data.summary;

                // Update metric cards
                document.getElementById('metricActiveItems').textContent = metrics.active_items || 0;
                document.getElementById('metricActiveBidders').textContent = metrics.active_bidders || 0;
                document.getElementById('metricTotalBids').textContent = metrics.total_bids || 0;
                document.getElementById('metricTotalRaised').textContent = '$' + this.formatCurrency(summary.total_raised || 0);

                // Update status cards
                document.getElementById('statusPending').textContent = summary.pending_payments || 0;
                const completionRate = summary.completion_rate || 0;
                document.getElementById('statusCompletion').textContent = completionRate + '%';

                // Update timestamp
                const now = new Date();
                document.getElementById('metricsTimestamp').textContent = now.toLocaleTimeString();

                // Render high-traffic items
                this.renderHighTrafficItems(metrics.high_traffic_items || []);

                // Render recent activity
                this.renderRecentActivity(metrics.recent_bids || []);
            }
        } catch (error) {
            console.error('Error loading metrics:', error);
        }
    },

    renderHighTrafficItems(items) {
        const container = document.getElementById('highTrafficContainer');

        if (items.length === 0) {
            container.innerHTML = '<p class="empty-state">No bidding activity yet</p>';
            return;
        }

        let html = '<table class="admin-table"><thead><tr><th>Item</th><th>Bids</th><th>Current Bid</th></tr></thead><tbody>';

        items.slice(0, 5).forEach(item => {
            html += `<tr>
                <td>${this.escapeHtml(item.title)}</td>
                <td>${item.bid_count}</td>
                <td>$${this.formatCurrency(item.current_high_bid)}</td>
            </tr>`;
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    },

    renderRecentActivity(bids) {
        const container = document.getElementById('recentActivityContainer');

        if (bids.length === 0) {
            container.innerHTML = '<p class="empty-state">No recent activity</p>';
            return;
        }

        let html = '<div class="activity-list">';

        bids.slice(0, 10).forEach(bid => {
            const time = new Date(bid.created_at).toLocaleTimeString();
            html += `<div class="activity-item">
                <span class="time">${time}</span>
                <span class="activity">${this.escapeHtml(bid.full_name)} bid $${this.formatCurrency(bid.bid_amount)} on "${this.escapeHtml(bid.title)}"</span>
            </div>`;
        });

        html += '</div>';
        container.innerHTML = html;
    },

    // ============================================================
    // ITEMS MANAGEMENT
    // ============================================================

    async loadItems(page = 1) {
        // If no event selected, show message
        if (!this.state.selectedEventId) {
            const container = document.getElementById('itemsContainer');
            container.innerHTML = '<p class="empty-state">Please select an event first.</p>';
            return;
        }

        try {
            let url = this.config.apiBaseUrl + '/get-items.php?page=' + page + '&limit=25';
            if (this.state.selectedEventId) {
                url += '&event_id=' + this.state.selectedEventId;
            }

            const response = await fetch(url, {
                headers: this.getAuthHeaders()
            });
            const data = await response.json();

            if (data.status === 'ok') {
                this.renderItemsTable(data.items);
                this.renderPagination('itemsPagination', page, data.pagination.pages, (p) => this.loadItems(p));
                this.state.currentPage.items = page;
            }
        } catch (error) {
            console.error('Error loading items:', error);
        }
    },

    renderItemsTable(items) {
        const container = document.getElementById('itemsContainer');

        if (items.length === 0) {
            container.innerHTML = '<p class="empty-state">No items yet. Create one to get started.</p>';
            return;
        }

        let html = '<table class="admin-table"><thead><tr><th>Item #</th><th>Title</th><th>Status</th><th>Current Bid</th><th>Bids</th><th>Time</th><th>Actions</th></tr></thead><tbody>';

        items.forEach(item => {
            const status = item.is_closed ? 'Closed' : 'Active';
            const timeRemaining = item.time_remaining_seconds > 0
                ? this.formatTime(item.time_remaining_seconds)
                : 'Ended';

            html += `<tr>
                <td>#${item.item_number}</td>
                <td>${this.escapeHtml(item.title)}</td>
                <td><span class="badge badge-${item.is_closed ? 'danger' : 'success'}">${status}</span></td>
                <td>$${this.formatCurrency(item.current_high_bid)}</td>
                <td>${item.bid_count}</td>
                <td>${timeRemaining}</td>
                <td>
                    <button class="btn btn-small btn-secondary edit-item" data-id="${item.id}">Edit</button>
                    <button class="btn btn-small btn-secondary delete-item" data-id="${item.id}">Delete</button>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';
        container.innerHTML = html;

        // Attach event listeners
        container.querySelectorAll('.edit-item').forEach(btn => {
            btn.addEventListener('click', () => this.editItem(btn.dataset.id));
        });

        container.querySelectorAll('.delete-item').forEach(btn => {
            btn.addEventListener('click', () => this.deleteItem(btn.dataset.id));
        });
    },

    editItem(itemId) {
        const modal = document.getElementById('itemModal');
        const form = document.getElementById('itemForm');

        console.log('[EDIT ITEM] Opening item:', itemId);

        // CRITICAL: Reset form and modal completely before loading new item
        form.reset();
        form.dataset.itemId = itemId;
        document.getElementById('itemModalTitle').textContent = 'Edit Item';
        document.getElementById('itemFormError').style.display = 'none';
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('uploadPlaceholder').style.display = 'block';
        document.getElementById('itemQRDisplay').style.display = 'none';
        document.getElementById('createPDFBtn').style.display = 'none';
        document.getElementById('modalDocumentLink').href = '#';
        form.dataset.imagePrompt = '';
        const improveResult = document.getElementById('descriptionImproveResult');
        if (improveResult) {
            improveResult.style.display = 'none';
            improveResult.textContent = '';
        }

        modal.style.display = 'block';
        this.setupImageUpload();

        // Load item data
        this.loadItemForEdit(itemId);
    },

    async loadItemForEdit(itemId) {
        try {
            console.log('[EDIT ITEM] Loading item ID:', itemId);

            const url = this.config.apiBaseUrl + '/crud-items.php?action=get&item_id=' + itemId;
            console.log('[EDIT ITEM] Fetching from:', url);

            const response = await fetch(url, {
                headers: this.getAuthHeaders()
            });

            console.log('[EDIT ITEM] Response status:', response.status, response.ok);

            if (!response.ok) {
                throw new Error('Failed to load item. Status: ' + response.status);
            }

            const data = await response.json();
            console.log('[EDIT ITEM] API response:', data);

            if (data.status !== 'ok' || !data.data) {
                throw new Error('Item not found or invalid response: ' + JSON.stringify(data));
            }

            const item = data.data;
            console.log('[EDIT ITEM] Item data:', item);

            // Populate form fields
            const form = document.getElementById('itemForm');

            // Check if form exists
            if (!form) {
                console.error('[EDIT ITEM] ❌ Form not found!');
                throw new Error('Form element not found');
            }

            // Populate each field with validation
            const titleField = form.querySelector('[name="title"]');
            if (titleField) {
                titleField.value = item.title || '';
                console.log('[EDIT ITEM] Set title:', titleField.value);
            } else {
                console.warn('[EDIT ITEM] ⚠️ Title field not found');
            }

            const descField = form.querySelector('[name="description"]');
            if (descField) {
                descField.value = item.description || '';
                console.log('[EDIT ITEM] Set description length:', descField.value.length);
            }

            const fmvField = form.querySelector('[name="fair_market_value"]');
            if (fmvField) {
                fmvField.value = item.fair_market_value || '';
            }

            const startField = form.querySelector('[name="starting_bid"]');
            if (startField) {
                startField.value = item.starting_bid || '';
                console.log('[EDIT ITEM] Set starting_bid:', startField.value);
            }

            const minField = form.querySelector('[name="min_increment"]');
            if (minField) {
                minField.value = item.min_increment || '';
            }

            const buyNowField = form.querySelector('[name="buy_now_price"]');
            if (buyNowField) {
                buyNowField.value = item.buy_now_price || '';
            }

            // Calculate and fill duration fields
            if (item.auction_end_time) {
                const endTime = new Date(item.auction_end_time);
                const startTime = new Date(item.auction_start_time);
                const diffMs = endTime - startTime;
                const diffSeconds = Math.floor(diffMs / 1000);
                const hours = Math.floor(diffSeconds / 3600);
                const minutes = Math.floor((diffSeconds % 3600) / 60);
                const seconds = diffSeconds % 60;

                form.querySelector('[name="duration_hours"]').value = hours;
                form.querySelector('[name="duration_minutes"]').value = minutes;
                form.querySelector('[name="duration_seconds"]').value = seconds;
            }

            // Display winner name - handle both open and closed items
            const winnerField = form.querySelector('[name="winner_name"]');
            if (winnerField) {
                if (item.is_closed === 0) {
                    winnerField.value = '(Auction still active)';
                } else if (item.current_high_bid > 0 && item.winner_name) {
                    winnerField.value = item.winner_name;
                } else if (item.current_high_bid === 0) {
                    winnerField.value = '(No bids received)';
                } else {
                    winnerField.value = '(No winner)';
                }
            }

            // Show image if exists
            if (item.image_url) {
                document.getElementById('imageUrlInput').value = item.image_url;
                document.getElementById('previewImg').src = item.image_url;
                document.getElementById('imagePreview').style.display = 'flex';
                document.getElementById('uploadPlaceholder').style.display = 'none';
            }

            console.log('[EDIT ITEM] ✓ Form populated successfully');

            // Display QR code if exists
            if (item.short_url) {
                document.getElementById('itemQRDisplay').style.display = 'block';

                // Generate QR code from short URL using reliable service
                const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent('https://' + item.short_url);
                const qrImage = document.getElementById('modalQRCode');
                qrImage.src = qrUrl;
                qrImage.onerror = () => {
                    // Fallback to another service if primary fails
                    qrImage.src = 'https://quickchart.io/qr?text=' + encodeURIComponent('https://' + item.short_url) + '&size=150';
                };

                document.getElementById('modalQRLink').href = 'https://' + item.short_url;
                document.getElementById('modalQRLink').textContent = item.short_url;

                // Generate document link
                const docPath = 'documents/item-' + item.id + '.html';
                document.getElementById('modalDocumentLink').href = docPath;
            } else {
                document.getElementById('itemQRDisplay').style.display = 'none';
            }

            // Validate and show Create PDF button if all requirements met
            this.validatePDFRequirements();
        } catch (error) {
            console.error('Error loading item:', error);
            this.showToast('Error loading item details', 'error');
        }
    },

    async deleteItem(itemId) {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch(this.config.apiBaseUrl + '/crud-items.php?action=delete&item_id=' + itemId, {
                method: 'POST',
                headers: this.getAuthHeaders()
            });

            const data = await response.json();

            if (response.ok && data.status === 'ok') {
                this.showToast('Item deleted successfully', 'success');
                this.loadItems(this.state.currentPage.items || 1);
            } else {
                this.showToast(data.message || 'Error deleting item', 'error');
            }
        } catch (error) {
            this.showToast('Error: ' + error.message, 'error');
        }
    },

    // ============================================================
    // TRANSACTIONS
    // ============================================================

    async loadTransactions(page = 1, status = '') {
        // If no event selected, show message
        const container = document.getElementById('transactionsContainer');
        if (!this.state.selectedEventId) {
            container.innerHTML = '<p class="empty-state">Please select an event first.</p>';
            return;
        }

        try {
            let url = this.config.apiBaseUrl + '/get-transactions.php?page=' + page + '&limit=25';
            url += '&event_id=' + this.state.selectedEventId;
            if (status) {
                url += '&status=' + encodeURIComponent(status);
            }

            const response = await fetch(url, {
                headers: this.getAuthHeaders()
            });
            const data = await response.json();

            if (data.status === 'ok') {
                this.renderTransactionsTable(data.transactions);
                this.renderPagination('transactionsPagination', page, data.pagination.pages, (p) => this.loadTransactions(p, status));
                this.state.currentPage.transactions = page;
            }
        } catch (error) {
            console.error('Error loading transactions:', error);
        }
    },

    renderTransactionsTable(transactions) {
        const container = document.getElementById('transactionsContainer');

        if (transactions.length === 0) {
            container.innerHTML = '<p class="empty-state">No transactions yet</p>';
            return;
        }

        let html = '<table class="admin-table"><thead><tr><th>Item</th><th>Winner</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>';

        transactions.forEach(t => {
            const statusBadge = `<span class="badge badge-${t.status === 'paid' ? 'success' : t.status === 'pending' ? 'warning' : 'danger'}">${t.status}</span>`;
            const date = new Date(t.created_at).toLocaleDateString();

            html += `<tr>
                <td>${this.escapeHtml(t.item_title)}</td>
                <td>${this.escapeHtml(t.winner_name)}</td>
                <td>$${this.formatCurrency(t.amount)}</td>
                <td>${statusBadge}</td>
                <td>${date}</td>
            </tr>`;
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    },

    // ============================================================
    // USERS
    // ============================================================

    async loadUsers(page = 1, search = '') {
        // If no event selected, show message
        if (!this.state.selectedEventId) {
            const container = document.getElementById('usersContainer');
            container.innerHTML = '<p class="empty-state">Please select an event first.</p>';
            return;
        }

        try {
            let url = this.config.apiBaseUrl + '/get-users.php?page=' + page + '&limit=25';
            if (this.state.selectedEventId) {
                url += '&event_id=' + this.state.selectedEventId;
            }
            if (search) {
                url += '&search=' + encodeURIComponent(search);
            }

            const response = await fetch(url, {
                headers: this.getAuthHeaders()
            });
            const data = await response.json();

            if (data.status === 'ok') {
                this.renderUsersTable(data.users);
                this.renderPagination('usersPagination', page, data.pagination.pages, (p) => this.loadUsers(p, search));
                this.state.currentPage.users = page;
            }
        } catch (error) {
            console.error('Error loading users:', error);
        }
    },

    renderUsersTable(users) {
        const container = document.getElementById('usersContainer');

        if (users.length === 0) {
            container.innerHTML = '<p class="empty-state">No bidders yet</p>';
            return;
        }

        let html = '<table class="admin-table"><thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Bids</th><th>Won</th><th>Total Spent</th><th>Last Bid</th><th>Actions</th></tr></thead><tbody>';

        users.forEach(user => {
            const lastBid = user.last_bid_at ? new Date(user.last_bid_at).toLocaleTimeString() : '-';
            const bidderName = this.escapeHtml(user.full_name || 'this bidder');

            html += `<tr>
                <td>${this.escapeHtml(user.full_name || '-')}</td>
                <td>${this.renderPhoneLink(user.phone_number, user.phone_display)}</td>
                <td>${this.renderEmailLink(user.email)}</td>
                <td>${parseInt(user.bid_count || 0, 10)}</td>
                <td>${parseInt(user.items_won || 0, 10)}</td>
                <td>$${this.formatCurrency(user.total_spent)}</td>
                <td>${lastBid}</td>
                <td>
                    <div class="admin-row-actions">
                        <button class="btn btn-small btn-secondary view-user" data-id="${user.id}">View</button>
                        <button class="btn btn-small btn-secondary edit-user" data-id="${user.id}">Edit</button>
                        <button class="btn btn-small btn-danger delete-user" data-id="${user.id}" data-name="${bidderName}">Delete</button>
                    </div>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';
        container.innerHTML = html;

        // Attach event listeners
        container.querySelectorAll('.view-user').forEach(btn => {
            btn.addEventListener('click', () => this.viewUserDetails(btn.dataset.id));
        });
        container.querySelectorAll('.edit-user').forEach(btn => {
            btn.addEventListener('click', () => this.showEditUserForm(btn.dataset.id));
        });
        container.querySelectorAll('.delete-user').forEach(btn => {
            btn.addEventListener('click', () => this.deleteUser(btn.dataset.id, btn.dataset.name));
        });
    },

    getUserSearchValue() {
        return document.getElementById('userSearchInput')?.value.trim() || '';
    },

    resetUserEditForm() {
        const form = document.getElementById('userEditForm');
        const errorDiv = document.getElementById('userEditError');

        form.reset();
        form.dataset.userId = '';
        errorDiv.textContent = '';
        errorDiv.style.display = 'none';
    },

    showCreateUserForm() {
        this.resetUserEditForm();
        document.getElementById('userEditModalTitle').textContent = 'Create Bidder';
        document.getElementById('userEditModal').style.display = 'block';
        document.getElementById('userFullNameInput')?.focus();
    },

    async showEditUserForm(userId) {
        const modal = document.getElementById('userEditModal');
        const form = document.getElementById('userEditForm');
        const errorDiv = document.getElementById('userEditError');

        this.resetUserEditForm();
        document.getElementById('userEditModalTitle').textContent = 'Edit Bidder';
        modal.style.display = 'block';
        errorDiv.textContent = 'Loading bidder...';
        errorDiv.style.display = 'block';

        try {
            const response = await fetch(this.config.apiBaseUrl + '/crud-users.php?action=get&user_id=' + encodeURIComponent(userId), {
                method: 'GET',
                headers: this.getAuthHeaders(),
                credentials: 'include'
            });
            const data = await response.json();

            if (!response.ok || data.status !== 'ok') {
                throw new Error(data.message || 'Could not load bidder');
            }

            const user = data.data;
            form.dataset.userId = user.id;
            document.getElementById('userFullNameInput').value = user.full_name || '';
            document.getElementById('userPhoneInput').value = this.formatPhoneNumber(user.phone_number || '');
            document.getElementById('userEmailInput').value = user.email || '';
            document.getElementById('userStripeInput').value = user.stripe_customer_id || '';
            errorDiv.style.display = 'none';
            document.getElementById('userFullNameInput')?.focus();
        } catch (error) {
            errorDiv.textContent = error.message;
            errorDiv.style.display = 'block';
        }
    },

    async handleUserFormSubmit(event) {
        event.preventDefault();

        const form = event.target;
        const errorDiv = document.getElementById('userEditError');
        const submitBtn = form.querySelector('button[type="submit"]');
        const userId = form.dataset.userId;
        const formData = new FormData(form);
        const payload = {
            full_name: (formData.get('full_name') || '').trim(),
            phone_number: (formData.get('phone_number') || '').trim(),
            email: (formData.get('email') || '').trim(),
            stripe_customer_id: (formData.get('stripe_customer_id') || '').trim()
        };

        errorDiv.style.display = 'none';
        submitBtn.disabled = true;

        try {
            const action = userId ? 'update&user_id=' + encodeURIComponent(userId) : 'create';
            const response = await fetch(this.config.apiBaseUrl + '/crud-users.php?action=' + action, {
                method: 'POST',
                headers: this.getAuthHeaders(),
                credentials: 'include',
                body: JSON.stringify(payload)
            });
            const data = await response.json();

            if (!response.ok || data.status !== 'ok') {
                throw new Error(data.message || 'Could not save bidder');
            }

            document.getElementById('userEditModal').style.display = 'none';
            this.showToast(userId ? 'Bidder updated' : 'Bidder created', 'success');
            this.loadUsers(this.state.currentPage.users || 1, this.getUserSearchValue());
            if (this.state.currentSection === 'admins') {
                this.loadUsersManagement();
            }
        } catch (error) {
            errorDiv.textContent = error.message;
            errorDiv.style.display = 'block';
        } finally {
            submitBtn.disabled = false;
        }
    },

    async deleteUser(userId, userName) {
        const confirmed = window.confirm('Delete ' + userName + '? This removes their bidder sign-in record. If they have protected auction history, Silent Bid Pro will keep the record and tell you why.');
        if (!confirmed) return;

        try {
            const response = await fetch(this.config.apiBaseUrl + '/crud-users.php?action=delete&user_id=' + encodeURIComponent(userId), {
                method: 'POST',
                headers: this.getAuthHeaders(),
                credentials: 'include'
            });
            const data = await response.json();

            if (!response.ok || data.status !== 'ok') {
                throw new Error(data.message || 'Could not delete bidder');
            }

            this.showToast('Bidder deleted', 'success');
            this.loadUsers(this.state.currentPage.users || 1, this.getUserSearchValue());
            if (this.state.currentSection === 'admins') {
                this.loadUsersManagement();
            }
        } catch (error) {
            this.showToast(error.message, 'error');
        }
    },

    async viewUserDetails(userId) {
        const modal = document.getElementById('userModal');
        const body = document.getElementById('userModalBody');

        modal.style.display = 'block';
        body.innerHTML = '<p class="loading">Loading user details...</p>';

        try {
            const response = await fetch(this.config.apiBaseUrl + '/get-user-details.php?user_id=' + userId, {
                headers: this.getAuthHeaders()
            });
            const data = await response.json();

            if (data.status === 'ok') {
                const user = data.user;
                document.getElementById('userModalTitle').textContent = user.full_name + ' — Details';

                let html = '<div class="user-details">';
                html += '<h3>User Information</h3>';
                html += '<p><strong>Name:</strong> ' + this.escapeHtml(user.full_name) + '</p>';
                html += '<p><strong>Phone:</strong> ' + this.renderPhoneLink(user.phone_number, user.phone_display) + '</p>';
                html += '<p><strong>Email:</strong> ' + this.renderEmailLink(user.email) + '</p>';
                html += '<p><strong>Member Since:</strong> ' + new Date(user.created_at).toLocaleDateString() + '</p>';

                if (data.wins.length > 0) {
                    html += '<h3>Won Items</h3>';
                    html += '<table class="admin-table"><thead><tr><th>Item</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
                    data.wins.forEach(win => {
                        const statusBadge = `<span class="badge badge-${win.transaction_status === 'paid' ? 'success' : 'warning'}">${win.transaction_status || 'Pending'}</span>`;
                        html += '<tr><td>' + this.escapeHtml(win.title) + '</td><td>$' + this.formatCurrency(win.winning_amount) + '</td><td>' + statusBadge + '</td></tr>';
                    });
                    html += '</tbody></table>';
                }

                if (data.bid_history.length > 0) {
                    html += '<h3>Recent Bids</h3>';
                    html += '<table class="admin-table"><thead><tr><th>Item</th><th>Bid Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>';
                    data.bid_history.slice(0, 10).forEach(bid => {
                        let badgeClass = 'badge-secondary';
                        if (bid.status === 'WON') badgeClass = 'badge-success';
                        else if (bid.status === 'CURRENT HIGH BID') badgeClass = 'badge-warning';
                        const status = '<span class="badge ' + badgeClass + '">' + bid.status + '</span>';
                        const date = new Date(bid.created_at).toLocaleString();
                        html += '<tr><td>' + this.escapeHtml(bid.item_title) + '</td><td>$' + this.formatCurrency(bid.bid_amount) + '</td><td>' + status + '</td><td>' + date + '</td></tr>';
                    });
                    html += '</tbody></table>';
                }

                html += '</div>';
                body.innerHTML = html;
            }
        } catch (error) {
            body.innerHTML = '<p class="error-message">Error loading user details: ' + error.message + '</p>';
        }
    },

    // ============================================================
    // MODALS & FORMS
    // ============================================================

    setupModals() {
        // Modal close buttons
        document.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const modal = btn.closest('.modal');
                if (modal) modal.style.display = 'none';
            });
        });

        // Close modal on background click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });

        // Item form submission
        document.getElementById('itemForm').addEventListener('submit', (e) => this.handleItemFormSubmit(e));

        const improveDescriptionBtn = document.getElementById('improveDescriptionBtn');
        if (improveDescriptionBtn) {
            improveDescriptionBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleImproveDescription();
            });
        }

        // Create PDF button with direct reference
        const createPDFBtn = document.getElementById('createPDFBtn');
        if (createPDFBtn) {
            createPDFBtn.addEventListener('click', (e) => {
                e.preventDefault();
                console.log('Create PDF clicked');
                this.handleCreatePDF();
            });
        }

        // Form field changes for PDF validation
        const form = document.getElementById('itemForm');
        ['title', 'starting_bid', 'min_increment'].forEach(fieldName => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.addEventListener('input', () => this.validatePDFRequirements());
                field.addEventListener('change', () => this.validatePDFRequirements());
            }
        });
    },

    async handleImproveDescription() {
        const form = document.getElementById('itemForm');
        const titleField = form.querySelector('[name="title"]');
        const descriptionField = form.querySelector('[name="description"]');
        const resultDiv = document.getElementById('descriptionImproveResult');
        const button = document.getElementById('improveDescriptionBtn');
        const btnText = button?.querySelector('.btn-text');
        const btnSpinner = button?.querySelector('.btn-spinner');

        const title = titleField?.value.trim() || '';
        const description = descriptionField?.value.trim() || '';

        if (!title) {
            this.showToast('Add an item title first', 'error');
            titleField?.focus();
            return;
        }

        if (description.split(/\s+/).filter(Boolean).length < 8) {
            this.showToast('Add a few more description details first', 'error');
            descriptionField?.focus();
            return;
        }

        if (resultDiv) {
            resultDiv.style.display = 'none';
            resultDiv.textContent = '';
        }

        if (button) button.disabled = true;
        if (btnText) btnText.style.display = 'none';
        if (btnSpinner) btnSpinner.style.display = 'inline';

        try {
            const formData = new FormData(form);
            const response = await fetch(this.config.apiBaseUrl + '/improve-description.php', {
                method: 'POST',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({
                    title,
                    description,
                    fair_market_value: formData.get('fair_market_value') || null,
                    starting_bid: formData.get('starting_bid') || null,
                    buy_now_price: formData.get('buy_now_price') || null
                })
            });

            const data = await response.json();

            if (!response.ok || data.status !== 'ok') {
                throw new Error(data.message || 'Could not improve description');
            }

            descriptionField.value = data.description;
            form.dataset.imagePrompt = data.image_prompt || '';

            if (resultDiv) {
                resultDiv.textContent = 'Improved copy is in the description box. Review it, adjust any details, then save. The image generator will use this stronger description as its creative brief.';
                resultDiv.style.display = 'block';
            }

            this.showToast(data.message || 'Description improved', 'success');
        } catch (error) {
            this.showToast(error.message || 'Could not improve description', 'error');
        } finally {
            if (button) button.disabled = false;
            if (btnText) btnText.style.display = 'inline';
            if (btnSpinner) btnSpinner.style.display = 'none';
        }
    },

    async handleItemFormSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const formData = new FormData(form);
        const itemId = form.dataset.itemId;

        const data = {
            title: formData.get('title'),
            description: formData.get('description'),
            image_url: formData.get('image_url'),
            fair_market_value: formData.get('fair_market_value') ? parseFloat(formData.get('fair_market_value')) : null,
            starting_bid: parseFloat(formData.get('starting_bid')),
            min_increment: parseFloat(formData.get('min_increment')),
            buy_now_price: formData.get('buy_now_price') ? parseFloat(formData.get('buy_now_price')) : null
        };

        if (!itemId) {
            // Creating new item
            const hours = parseInt(formData.get('duration_hours')) || 0;
            const minutes = parseInt(formData.get('duration_minutes')) || 0;
            const seconds = parseInt(formData.get('duration_seconds')) || 0;

            const endTime = new Date();
            endTime.setHours(endTime.getHours() + hours);
            endTime.setMinutes(endTime.getMinutes() + minutes);
            endTime.setSeconds(endTime.getSeconds() + seconds);

            data.auction_end_time = endTime.toISOString();

            // Items must belong to the currently-selected event, or they are
            // invisible to bidders (the public catalog filters by event_id).
            if (!this.state.selectedEventId) {
                alert('Please select an event before creating an item.');
                return;
            }
            data.event_id = this.state.selectedEventId;
        }

        try {
            const url = itemId
                ? this.config.apiBaseUrl + '/crud-items.php?action=update&item_id=' + itemId
                : this.config.apiBaseUrl + '/crud-items.php?action=create';

            const response = await fetch(url, {
                method: 'POST',
                headers: this.getAuthHeaders(),
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (response.ok && result.status === 'ok') {
                this.showToast(itemId ? 'Item updated' : 'Item created', 'success');

                // For new items, show QR code and document
                if (!itemId && result.item && result.item.document_url) {
                    setTimeout(() => {
                        this.showItemQRModal(result.item);
                    }, 500);
                }

                document.getElementById('itemModal').style.display = 'none';
                form.reset();
                this.loadItems(this.state.currentPage.items || 1);
            } else {
                const errorDiv = document.getElementById('itemFormError');
                errorDiv.textContent = result.message || 'Error saving item';
                errorDiv.style.display = 'block';
            }
        } catch (error) {
            const errorDiv = document.getElementById('itemFormError');
            errorDiv.textContent = 'Error: ' + error.message;
            errorDiv.style.display = 'block';
        }
    },

    setupButtons() {
        // Create item button
        document.getElementById('createItemBtn')?.addEventListener('click', () => {
            document.getElementById('itemModalTitle').textContent = 'Create New Item';
            document.getElementById('itemForm').reset();
            document.getElementById('itemForm').dataset.itemId = '';
            document.getElementById('itemForm').dataset.imagePrompt = '';
            document.getElementById('itemFormError').style.display = 'none';
            const improveResult = document.getElementById('descriptionImproveResult');
            if (improveResult) {
                improveResult.style.display = 'none';
                improveResult.textContent = '';
            }
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('uploadPlaceholder').style.display = 'block';
            document.getElementById('createPDFBtn').style.display = 'none';
            document.getElementById('itemModal').style.display = 'block';
            this.setupImageUpload();
        });

        // Logout button
        document.getElementById('logoutBtn').addEventListener('click', () => {
            this.logout();
        });

        // Close auctions button
        document.getElementById('closeAuctionsBtn')?.addEventListener('click', () => {
            this.closeExpiredAuctions();
        });

        document.getElementById('createUserBtn')?.addEventListener('click', () => {
            this.showCreateUserForm();
        });

        document.getElementById('userEditForm')?.addEventListener('submit', (e) => {
            this.handleUserFormSubmit(e);
        });

        // Assign admins to events button
        document.getElementById('assignAdminsBtn')?.addEventListener('click', () => {
            this.showAssignAdminsModal();
        });

        // Assign admins submit button
        document.getElementById('assignAdminsSubmitBtn')?.addEventListener('click', () => {
            this.saveAdminAssignments();
        });
    },

    async closeExpiredAuctions() {
        const btn = document.getElementById('closeAuctionsBtn');
        const btnText = btn.querySelector('.btn-text');
        const btnSpinner = btn.querySelector('.btn-spinner');
        const resultDiv = document.getElementById('closeAuctionsResult');

        btnText.style.display = 'none';
        btnSpinner.style.display = 'inline';
        btn.disabled = true;

        try {
            const response = await fetch('/api/admin/close-auctions.php', {
                method: 'POST',
                headers: this.getAuthHeaders()
            });

            const data = await response.json();

            if (data.status === 'ok') {
                resultDiv.textContent = '✅ ' + data.message;
                resultDiv.style.display = 'block';
                resultDiv.style.background = '#dcfce7';
                resultDiv.style.color = '#2d5016';

                // Reload bidders/users section to show updated stats
                setTimeout(() => {
                    this.loadUsers(1);
                    this.loadItems(1);
                    this.startMetricsPolling();
                }, 1000);
            } else {
                resultDiv.textContent = '❌ Error: ' + data.message;
                resultDiv.style.display = 'block';
                resultDiv.style.background = '#fee2e2';
                resultDiv.style.color = '#7c2d12';
            }
        } catch (err) {
            resultDiv.textContent = '❌ Network error: ' + err.message;
            resultDiv.style.display = 'block';
            resultDiv.style.background = '#fee2e2';
            resultDiv.style.color = '#7c2d12';
        } finally {
            btnText.style.display = 'inline';
            btnSpinner.style.display = 'none';
            btn.disabled = false;
        }
    },

    validatePDFRequirements() {
        const form = document.getElementById('itemForm');
        const title = form.querySelector('[name="title"]').value.trim();
        const startingBid = form.querySelector('[name="starting_bid"]').value.trim();
        const minIncrement = form.querySelector('[name="min_increment"]').value.trim();
        const itemId = form.dataset.itemId;

        // Only show Create PDF button for existing items with all required fields
        const createPDFBtn = document.getElementById('createPDFBtn');
        if (itemId && title && startingBid && minIncrement) {
            createPDFBtn.style.display = 'inline-block';
        } else {
            createPDFBtn.style.display = 'none';
        }
    },

    async handleCreatePDF() {
        const form = document.getElementById('itemForm');
        const itemId = form.dataset.itemId;

        console.log('[PDF CREATE] itemId from form:', itemId);
        console.log('[PDF CREATE] form.dataset:', form.dataset);

        if (!itemId) {
            this.showToast('Please save the item first', 'error');
            return;
        }

        const formData = new FormData(form);
        const title = formData.get('title');
        console.log('[PDF CREATE] title from form:', title);

        const data = {
            item_id: parseInt(itemId),
            title: title,
            description: formData.get('description'),
            image_url: formData.get('image_url'),
            fair_market_value: formData.get('fair_market_value') ? parseFloat(formData.get('fair_market_value')) : null,
            starting_bid: parseFloat(formData.get('starting_bid')),
            min_increment: parseFloat(formData.get('min_increment')),
            buy_now_price: formData.get('buy_now_price') ? parseFloat(formData.get('buy_now_price')) : null,
            auction_duration_seconds: this.calculateAuctionDuration(formData)
        };

        console.log('[PDF CREATE] Sending to API:', data);

        try {
            const response = await fetch(this.config.apiBaseUrl + '/create-item-document.php', {
                method: 'POST',
                headers: this.getAuthHeaders(),
                body: JSON.stringify(data)
            });

            const result = await response.json();

            console.log('[PDF CREATE] API response:', result);

            if (response.ok && result.status === 'ok') {
                this.showToast('Document created successfully!', 'success');
                // Download the document
                window.open(result.document_url, '_blank');
            } else {
                this.showToast(result.message || 'Error creating document', 'error');
            }
        } catch (error) {
            this.showToast('Error: ' + error.message, 'error');
        }
    },

    calculateAuctionDuration(formData) {
        const hours = parseInt(formData.get('duration_hours')) || 0;
        const minutes = parseInt(formData.get('duration_minutes')) || 0;
        const seconds = parseInt(formData.get('duration_seconds')) || 0;
        return (hours * 3600) + (minutes * 60) + seconds;
    },

    setupImageUpload() {
        const zone = document.getElementById('imageUploadZone');
        const fileInput = document.getElementById('imageFileInput');
        const browseBtn = document.getElementById('browseImageBtn');
        const removeBtn = document.getElementById('removeImageBtn');

        if (!zone) return;

        browseBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            fileInput.click();
        });

        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) this.handleImageFile(file);
        });

        removeBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.clearImage();
        });

        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('drag-over');
        });

        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('drag-over');

            // Try to handle file drop first
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const file = files[0];
                if (file.type.startsWith('image/')) {
                    this.handleImageFile(file);
                    return;
                }
            }

            // Try to handle text URL drop
            const text = e.dataTransfer.getData('text/plain') || e.dataTransfer.getData('text/uri-list');
            if (text) {
                const trimmedText = text.trim();
                // Check if it looks like a URL
                if (trimmedText.startsWith('http://') || trimmedText.startsWith('https://')) {
                    this.handleImageURL(trimmedText);
                } else {
                    this.showToast('Please drop an image file or image URL', 'error');
                }
            } else if (files.length === 0) {
                this.showToast('Please drop an image file or image URL', 'error');
            }
        });
    },

    handleImageFile(file) {
        if (file.size > 10 * 1024 * 1024) {
            this.showToast('Image must be smaller than 10MB', 'error');
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            const dataUrl = e.target.result;
            document.getElementById('imageUrlInput').value = dataUrl;
            document.getElementById('previewImg').src = dataUrl;
            document.getElementById('imagePreview').style.display = 'flex';
            document.getElementById('uploadPlaceholder').style.display = 'none';
        };
        reader.onerror = () => {
            this.showToast('Error reading image file', 'error');
        };
        reader.readAsDataURL(file);
    },

    handleImageURL(url) {
        // Show loading state
        const previewImg = document.getElementById('previewImg');
        previewImg.alt = 'Loading...';
        document.getElementById('imageUrlInput').value = url;
        document.getElementById('imagePreview').style.display = 'flex';
        document.getElementById('uploadPlaceholder').style.display = 'none';

        // Test if the URL is a valid image
        const img = new Image();
        img.onload = () => {
            // URL is valid, update preview
            previewImg.src = url;
            previewImg.alt = 'Preview';
            this.showToast('Image URL loaded', 'success');
        };
        img.onerror = () => {
            this.showToast('Invalid image URL or image not accessible', 'error');
            this.clearImage();
        };
        img.src = url;
    },

    clearImage() {
        document.getElementById('imageFileInput').value = '';
        document.getElementById('imageUrlInput').value = '';
        document.getElementById('previewImg').src = '';
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('uploadPlaceholder').style.display = 'block';
    },

    showItemQRModal(item) {
        const modal = document.getElementById('itemQRModal');
        if (!modal) return; // Will be added to HTML

        document.getElementById('qrItemTitle').textContent = 'Item #' + item.item_number + ': ' + item.title;
        document.getElementById('qrCodeImage').src = item.qr_code_image;
        document.getElementById('qrCodeURL').textContent = item.qr_url;
        document.getElementById('qrCodeURL').href = item.qr_url;
        document.getElementById('documentLink').href = item.document_url;

        modal.style.display = 'block';
    },

    setupFilters() {
        // Transaction status filter
        document.getElementById('transactionStatusFilter').addEventListener('change', (e) => {
            this.loadTransactions(1, e.target.value);
        });

        // User search (with debounce)
        let searchTimeout;
        document.getElementById('userSearchInput').addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.loadUsers(1, e.target.value);
            }, 300);
        });
    },

    // ============================================================
    // UTILITIES
    // ============================================================

    async logout() {
        try {
            await fetch(this.config.apiBaseUrl + '/logout-account.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            window.location.reload();
        } catch (error) {
            console.error('Logout error:', error);
            window.location.href = 'admin.php';
        }
    },

    renderPagination(containerId, currentPage, totalPages, onPageClick) {
        const container = document.getElementById(containerId);

        if (totalPages <= 1) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';
        let html = '<div class="pagination-links">';

        if (currentPage > 1) {
            html += `<button class="pagination-btn" onclick="AdminDashboard.handlePaginationClick(${currentPage - 1}, arguments[1])">← Previous</button>`;
        }

        for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
            const active = i === currentPage ? ' active' : '';
            html += `<button class="pagination-btn${active}" onclick="AdminDashboard.handlePaginationClick(${i}, arguments[1])">${i}</button>`;
        }

        if (currentPage < totalPages) {
            html += `<button class="pagination-btn" onclick="AdminDashboard.handlePaginationClick(${currentPage + 1}, arguments[1])">Next →</button>`;
        }

        html += '</div>';
        container.innerHTML = html;

        // Store callback for pagination clicks
        window.AdminDashboardPaginationCallback = onPageClick;
    },

    handlePaginationClick(page, event) {
        event?.preventDefault();
        if (window.AdminDashboardPaginationCallback) {
            window.AdminDashboardPaginationCallback(page);
        }
    },

    showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.textContent = message;

        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('show');
        }, 10);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 9000);
    },

    formatCurrency(amount) {
        return parseFloat(amount).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },

    formatTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (hours > 0) {
            return hours + 'h ' + minutes + 'm';
        } else if (minutes > 0) {
            return minutes + 'm ' + secs + 's';
        } else {
            return secs + 's';
        }
    },

    formatPhoneNumber(phone) {
        if (!phone) return '';
        // Remove all non-digits
        const digits = phone.replace(/\D/g, '');
        // Format as XXX-XXX-XXXX (assuming 10 digit number)
        if (digits.length === 10) {
            return digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6);
        } else if (digits.length === 11 && digits[0] === '1') {
            // Handle +1 prefix
            return digits.slice(1, 4) + '-' + digits.slice(4, 7) + '-' + digits.slice(7);
        }
        return phone;
    },

    renderPhoneLink(phone, displayText = '') {
        const rawPhone = String(phone || '').trim();
        if (!rawPhone) return '<span class="text-muted">Not provided</span>';

        const digits = rawPhone.replace(/\D/g, '');
        const telNumber = digits.length === 10 ? '+1' + digits : rawPhone.replace(/[^\d+]/g, '');
        const label = displayText || this.formatPhoneNumber(rawPhone);

        return `<a class="admin-contact-link" href="tel:${this.escapeHtml(telNumber)}">${this.escapeHtml(label)}</a>`;
    },

    renderEmailLink(email) {
        const cleanEmail = String(email || '').trim();
        if (!cleanEmail) return '<span class="text-muted">Not provided</span>';

        return `<a class="admin-contact-link" href="mailto:${this.escapeHtml(cleanEmail)}">${this.escapeHtml(cleanEmail)}</a>`;
    },

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    },

    // ============================================================
    // SUPER ADMIN CRUD OPERATIONS
    // ============================================================

    setupAdminControls() {
        // Admin control tab switching
        document.querySelectorAll('.admin-control-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.target;
                document.querySelectorAll('.admin-control-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.admin-control-content').forEach(c => c.style.display = 'none');
                tab.classList.add('active');
                document.getElementById(target).style.display = 'block';

                // Load content based on tab
                if (target === 'adminAccountsTab') {
                    this.loadAdminAccounts();
                } else if (target === 'usersManageTab') {
                    this.loadUsersManagement();
                } else if (target === 'itemsManageTab') {
                    this.loadItemsManagement();
                }
            });
        });

        // Create buttons
        const createItemManageBtn = document.getElementById('createItemManageBtn');

        if (createItemManageBtn) {
            createItemManageBtn.addEventListener('click', () => this.showCreateItemForm());
        }

        // Load initial content
        this.loadAdminAccounts();
    },

    async loadEvents() {
        const container = document.getElementById('eventsContainer');
        try {
            const response = await fetch('/api/admin/get-events.php', {
                method: 'GET',
                headers: this.getAuthHeaders(),
                credentials: 'include'
            });

            const data = await response.json();

            if (!response.ok || data.status !== 'ok') {
                container.innerHTML = `<p class="error">Error: ${data.message || 'Failed to load events'}</p>`;
                return;
            }

            const events = data.events || [];

            if (events.length === 0) {
                container.innerHTML = '<p style="color: #999; padding: 2rem; text-align: center;">No events found</p>';
                return;
            }

            // Build events table
            let html = '<table class="admin-table"><thead><tr><th>Event ID</th><th>Name</th><th>Organization</th><th>Status</th><th>Event Date</th><th>Items</th><th>Actions</th></tr></thead><tbody>';

            events.forEach(event => {
                const statusColor = event.status === 'open' ? 'green' : event.status === 'draft' ? 'orange' : 'red';
                const statusDisplay = event.status.charAt(0).toUpperCase() + event.status.slice(1);
                const eventDate = event.event_date ? new Date(event.event_date).toLocaleDateString() : 'N/A';
                html += `
                    <tr>
                        <td>${event.id}</td>
                        <td><strong>${event.name}</strong></td>
                        <td>${event.organization_name || 'N/A'}</td>
                        <td><span style="color: ${statusColor};">${statusDisplay}</span></td>
                        <td>${eventDate}</td>
                        <td>${event.item_count || 0}</td>
                        <td>
                            <button class="btn btn-small" onclick="AdminDashboard.editEvent(${event.id})">Edit</button>
                            <button class="btn btn-small btn-secondary" onclick="AdminDashboard.manageEventUsers(${event.id})">👥 Users</button>
                            <button class="btn btn-small btn-secondary" onclick="AdminDashboard.viewEventSettings(${event.id})">Settings</button>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        } catch (error) {
            container.innerHTML = `<p class="error">Error: ${error.message}</p>`;
        }
    },

    editEvent(eventId) {
        // Fetch event data and open modal
        this.loadEventForEditing(eventId);
    },

    viewEventSettings(eventId) {
        alert('Event settings (SMS, branding, payment) coming soon. Event ID: ' + eventId);
    },

    async manageEventUsers(eventId) {
        this.currentEventId = eventId;
        this.showModal('manageUsersModal');
        await this.loadEventUsers(eventId);
        this.setupUserManagementListeners();
    },

    async loadEventUsers(eventId) {
        try {
            const response = await fetch(`/api/admin/get-event-users.php?event_id=${eventId}`, {
                method: 'GET',
                headers: this.getAuthHeaders(),
                credentials: 'include'
            });

            const data = await response.json();
            const container = document.getElementById('usersContainer');

            if (!response.ok || data.status !== 'ok') {
                container.innerHTML = `<p class="error">Error: ${data.message || 'Failed to load users'}</p>`;
                return;
            }

            const users = data.users || [];

            if (users.length === 0) {
                container.innerHTML = '<p style="color: #999; padding: 1rem;">No users created yet for this event</p>';
                return;
            }

            // Build users table
            let html = '<table class="admin-table" style="width: 100%;"><thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Type</th><th>Actions</th></tr></thead><tbody>';

            users.forEach(user => {
                const typeColor = user.user_type === 'admin' ? 'green' : user.user_type === 'viewer' ? 'blue' : 'gray';
                const typeLabel = user.user_type.charAt(0).toUpperCase() + user.user_type.slice(1);

                html += `
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 0.75rem;">${user.full_name}</td>
                        <td style="padding: 0.75rem;">${user.phone_number}</td>
                        <td style="padding: 0.75rem;">${user.email || 'N/A'}</td>
                        <td style="padding: 0.75rem;"><span style="color: ${typeColor};"><strong>${typeLabel}</strong></span></td>
                        <td style="padding: 0.75rem;">
                            <button class="btn btn-small btn-danger" onclick="AdminDashboard.deleteEventUser(${user.id}, ${eventId})">Delete</button>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        } catch (error) {
            document.getElementById('usersContainer').innerHTML = `<p class="error">Error: ${error.message}</p>`;
        }
    },

    setupUserManagementListeners() {
        const createBtn = document.getElementById('createUserBtn');
        const saveBtn = document.getElementById('saveUserBtn');
        const cancelBtn = document.getElementById('cancelUserBtn');

        if (createBtn) {
            createBtn.onclick = () => {
                document.getElementById('createUserForm').style.display = 'block';
                document.getElementById('userFullName').focus();
            };
        }

        if (saveBtn) {
            saveBtn.onclick = () => this.saveNewEventUser();
        }

        if (cancelBtn) {
            cancelBtn.onclick = () => {
                document.getElementById('createUserForm').style.display = 'none';
                document.getElementById('userFullName').value = '';
                document.getElementById('userPhoneNumber').value = '';
                document.getElementById('userEmail').value = '';
                document.getElementById('userType').value = '';
                document.getElementById('createUserError').style.display = 'none';
            };
        }
    },

    async saveNewEventUser() {
        const eventId = this.currentEventId;
        const fullName = document.getElementById('userFullName').value.trim();
        const phoneNumber = document.getElementById('userPhoneNumber').value.trim();
        const email = document.getElementById('userEmail').value.trim();
        const userType = document.getElementById('userType').value;
        const errorContainer = document.getElementById('createUserError');

        errorContainer.style.display = 'none';

        if (!fullName || !phoneNumber || !userType) {
            errorContainer.textContent = 'Full name, phone number, and user type are required';
            errorContainer.style.display = 'block';
            return;
        }

        try {
            const response = await fetch('/api/admin/create-event-user.php', {
                method: 'POST',
                headers: this.getAuthHeaders(),
                credentials: 'include',
                body: JSON.stringify({
                    event_id: eventId,
                    full_name: fullName,
                    phone_number: phoneNumber,
                    email: email || null,
                    user_type: userType
                })
            });

            const data = await response.json();

            if (response.ok && data.status === 'ok') {
                alert('User created successfully!');
                // Reset form
                document.getElementById('userFullName').value = '';
                document.getElementById('userPhoneNumber').value = '';
                document.getElementById('userEmail').value = '';
                document.getElementById('userType').value = '';
                document.getElementById('createUserForm').style.display = 'none';
                // Reload users list
                this.loadEventUsers(eventId);
            } else {
                errorContainer.textContent = data.message || 'Failed to create user';
                errorContainer.style.display = 'block';
            }
        } catch (error) {
            errorContainer.textContent = 'Error: ' + error.message;
            errorContainer.style.display = 'block';
        }
    },

    async deleteEventUser(userId, eventId) {
        if (!confirm('Are you sure you want to delete this user?')) {
            return;
        }

        try {
            const response = await fetch('/api/admin/delete-event-user.php', {
                method: 'POST',
                headers: this.getAuthHeaders(),
                credentials: 'include',
                body: JSON.stringify({
                    user_id: userId,
                    event_id: eventId
                })
            });

            const data = await response.json();

            if (response.ok && data.status === 'ok') {
                alert('User deleted successfully');
                this.loadEventUsers(eventId);
            } else {
                alert('Error: ' + (data.message || 'Failed to delete user'));
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    },

    async loadEventForEditing(eventId) {
        try {
            const response = await fetch(`/api/admin/get-event.php?id=${eventId}`, {
                method: 'GET',
                headers: this.getAuthHeaders(),
                credentials: 'include'
            });

            const data = await response.json();

            if (!response.ok || data.status !== 'ok') {
                alert('Error loading event: ' + (data.message || 'Unknown error'));
                return;
            }

            const event = data.event;

            // Populate form
            document.getElementById('eventId').value = event.id;
            document.getElementById('eventName').value = event.name;
            document.getElementById('eventDate').value = event.event_date || '';
            document.getElementById('eventStatus').value = event.status;
            document.getElementById('eventPaymentMode').value = event.payment_mode;
            document.getElementById('eventTimezone').value = event.timezone;

            // Convert datetime to local format for datetime-local input
            if (event.auction_start_time) {
                document.getElementById('eventStartTime').value = event.auction_start_time.replace(' ', 'T');
            }
            if (event.auction_end_time) {
                document.getElementById('eventEndTime').value = event.auction_end_time.replace(' ', 'T');
            }

            document.getElementById('eventModalTitle').textContent = `Edit Event: ${event.name}`;

            // Show modal
            this.showModal('eventModal');

            // Setup form submit handler
            document.getElementById('eventForm').onsubmit = (e) => this.saveEvent(e);

        } catch (error) {
            alert('Error: ' + error.message);
        }
    },

    async saveEvent(e) {
        e.preventDefault();

        const eventId = document.getElementById('eventId').value;
        const formData = {
            id: eventId,
            name: document.getElementById('eventName').value,
            event_date: document.getElementById('eventDate').value || null,
            status: document.getElementById('eventStatus').value,
            auction_start_time: document.getElementById('eventStartTime').value || null,
            auction_end_time: document.getElementById('eventEndTime').value,
            payment_mode: document.getElementById('eventPaymentMode').value,
            timezone: document.getElementById('eventTimezone').value
        };

        try {
            const response = await fetch('/api/admin/update-event.php', {
                method: 'POST',
                headers: this.getAuthHeaders(),
                credentials: 'include',
                body: JSON.stringify(formData)
            });

            const data = await response.json();

            if (response.ok && data.status === 'ok') {
                alert('Event saved successfully!');
                this.hideModal('eventModal');
                this.loadEvents();
            } else {
                document.getElementById('eventFormError').textContent = data.message || 'Failed to save event';
                document.getElementById('eventFormError').style.display = 'block';
            }
        } catch (error) {
            document.getElementById('eventFormError').textContent = 'Error: ' + error.message;
            document.getElementById('eventFormError').style.display = 'block';
        }
    },

    // Admin Assignment Functions
    async showAssignAdminsModal() {
        this.showModal('assignAdminsModal');
        await this.loadEventsForAssignment();
        await this.loadAdminsForAssignment();
    },

    async loadEventsForAssignment() {
        try {
            const response = await fetch(this.config.apiBaseUrl + '/get-events.php', {
                method: 'GET',
                headers: this.getAuthHeaders(),
                credentials: 'include'
            });

            const data = await response.json();
            const select = document.getElementById('assignEventSelect');
            select.innerHTML = '<option value="">-- Choose an event --</option>';

            if (data.status === 'ok' && data.events) {
                data.events.forEach(event => {
                    const option = document.createElement('option');
                    option.value = event.id;
                    option.textContent = event.name;
                    select.appendChild(option);
                });

                // Load assignments when event is selected
                select.addEventListener('change', () => this.loadEventAdminAssignments(select.value));
            }
        } catch (error) {
            console.error('Error loading events:', error);
        }
    },

    async loadAdminsForAssignment() {
        try {
            const response = await fetch(this.config.apiBaseUrl + '/crud-admins.php?action=list', {
                method: 'GET',
                headers: this.getAuthHeaders(),
                credentials: 'include'
            });

            const data = await response.json();
            const container = document.getElementById('adminCheckboxes');
            container.innerHTML = '';

            if (data.status === 'ok' && data.admins) {
                // Filter out super admins, show only regular admins
                const regularAdmins = data.admins.filter(admin => !admin.is_super_admin);

                if (regularAdmins.length === 0) {
                    container.innerHTML = '<p style="color: #999;">No regular admins available</p>';
                    return;
                }

                regularAdmins.forEach(admin => {
                    const label = document.createElement('label');
                    label.style.display = 'flex';
                    label.style.alignItems = 'center';
                    label.style.padding = '0.5rem 0';
                    label.style.cursor = 'pointer';

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.value = admin.id;
                    checkbox.className = 'admin-checkbox';
                    checkbox.style.marginRight = '0.5rem';

                    const span = document.createElement('span');
                    span.textContent = `${admin.full_name} (${admin.username})`;

                    label.appendChild(checkbox);
                    label.appendChild(span);
                    container.appendChild(label);
                });
            }
        } catch (error) {
            console.error('Error loading admins:', error);
        }
    },

    async loadEventAdminAssignments(eventId) {
        if (!eventId) {
            document.getElementById('currentAssignments').innerHTML = '<p style="color: #999;">Select an event to see current assignments</p>';
            return;
        }

        try {
            const response = await fetch(`/api/admin/get-event-admins.php?event_id=${eventId}`, {
                method: 'GET',
                headers: this.getAuthHeaders(),
                credentials: 'include'
            });

            const data = await response.json();
            const container = document.getElementById('currentAssignments');

            if (data.status === 'ok' && data.assignments && data.assignments.length > 0) {
                let html = '<table style="width: 100%; border-collapse: collapse;"><thead><tr style="background: #f0f0f0;"><th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd;">Admin</th><th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd;">Role</th><th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd;">Action</th></tr></thead><tbody>';

                data.assignments.forEach(assignment => {
                    html += `<tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 0.5rem;">${assignment.admin_name} (${assignment.admin_username})</td>
                        <td style="padding: 0.5rem;"><strong>${assignment.role}</strong></td>
                        <td style="padding: 0.5rem;">
                            <button class="btn btn-small btn-danger" onclick="AdminDashboard.removeAdminAssignment(${eventId}, ${assignment.admin_id})">Remove</button>
                        </td>
                    </tr>`;
                });

                html += '</tbody></table>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p style="color: #999;">No admins assigned to this event yet</p>';
            }
        } catch (error) {
            console.error('Error loading assignments:', error);
        }
    },

    async saveAdminAssignments() {
        const eventId = document.getElementById('assignEventSelect').value;
        const role = document.getElementById('adminRoleSelect').value;
        const checkboxes = document.querySelectorAll('.admin-checkbox:checked');
        const adminIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

        const errorContainer = document.getElementById('assignError');
        errorContainer.style.display = 'none';

        if (!eventId) {
            errorContainer.textContent = 'Please select an event';
            errorContainer.style.display = 'block';
            return;
        }

        if (!role) {
            errorContainer.textContent = 'Please select a role';
            errorContainer.style.display = 'block';
            return;
        }

        if (adminIds.length === 0) {
            errorContainer.textContent = 'Please select at least one admin';
            errorContainer.style.display = 'block';
            return;
        }

        try {
            const response = await fetch('/api/admin/assign-event-admins.php', {
                method: 'POST',
                headers: this.getAuthHeaders(),
                credentials: 'include',
                body: JSON.stringify({
                    event_id: parseInt(eventId),
                    admin_ids: adminIds,
                    role: role
                })
            });

            const data = await response.json();

            if (response.ok && data.status === 'ok') {
                alert(`Successfully assigned ${adminIds.length} admin(s) to the event!`);
                // Reload assignments
                this.loadEventAdminAssignments(eventId);
                // Uncheck all checkboxes
                document.querySelectorAll('.admin-checkbox').forEach(cb => cb.checked = false);
                document.getElementById('adminRoleSelect').value = '';
            } else {
                errorContainer.textContent = data.message || 'Failed to assign admins';
                errorContainer.style.display = 'block';
            }
        } catch (error) {
            errorContainer.textContent = 'Error: ' + error.message;
            errorContainer.style.display = 'block';
        }
    },

    async removeAdminAssignment(eventId, adminId) {
        if (!confirm('Remove this admin from the event?')) {
            return;
        }

        try {
            const response = await fetch('/api/admin/remove-event-admin.php', {
                method: 'POST',
                headers: this.getAuthHeaders(),
                credentials: 'include',
                body: JSON.stringify({
                    event_id: eventId,
                    admin_id: adminId
                })
            });

            const data = await response.json();

            if (response.ok && data.status === 'ok') {
                alert('Admin removed from event');
                this.loadEventAdminAssignments(eventId);
            } else {
                alert('Error: ' + (data.message || 'Failed to remove admin'));
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    },

    async loadAdminAccounts() {
        const container = document.getElementById('adminsContainer');
        try {
            const response = await fetch(this.config.apiBaseUrl + '/crud-admins.php?action=list', {
                method: 'GET',
                headers: this.getAuthHeaders(),
                credentials: 'include' // CRITICAL: Send session cookie
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                container.innerHTML = `<p class="error">Error: ${data.message || response.statusText}</p>`;
                return;
            }

            const data = await response.json();

            if (!data.data || data.data.length === 0) {
                container.innerHTML = '<p style="color: #999; text-align: center; padding: 2rem;">No admin accounts found</p>';
                return;
            }

            let html = `<div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; background: white; border: 1px solid #ddd; border-radius: 6px;">
                <thead>
                    <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                        <th style="text-align: left; padding: 1rem; font-weight: 600; color: #333;">Username</th>
                        <th style="text-align: left; padding: 1rem; font-weight: 600; color: #333;">Email</th>
                        <th style="text-align: left; padding: 1rem; font-weight: 600; color: #333;">Name</th>
                        <th style="text-align: center; padding: 1rem; font-weight: 600; color: #333;">Role</th>
                        <th style="text-align: center; padding: 1rem; font-weight: 600; color: #333;">Status</th>
                        <th style="text-align: center; padding: 1rem; font-weight: 600; color: #333;">Last Login</th>
                    </tr>
                </thead>
                <tbody>`;

            (data.data || []).forEach((admin, idx) => {
                const role = admin.is_super_admin
                    ? '<span style="background: #ffeaa7; padding: 0.4rem 0.8rem; border-radius: 4px; font-size: 0.85rem; font-weight: 600;">Super Admin</span>'
                    : '<span style="background: #e8f4f8; padding: 0.4rem 0.8rem; border-radius: 4px; font-size: 0.85rem;">Admin</span>';
                const status = admin.is_active
                    ? '<span style="color: #27ae60; font-weight: 600;">✓ Active</span>'
                    : '<span style="color: #e74c3c; font-weight: 600;">✗ Inactive</span>';
                const lastLogin = admin.last_login
                    ? new Date(admin.last_login).toLocaleDateString() + ' ' + new Date(admin.last_login).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})
                    : '<span style="color: #999;">Never</span>';
                const bgColor = idx % 2 === 0 ? '#fafafa' : 'white';

                html += `<tr style="border-bottom: 1px solid #eee; background: ${bgColor};">`;
                html += `<td style="padding: 1rem;"><strong>${this.escapeHtml(admin.username)}</strong></td>`;
                html += `<td style="padding: 1rem;">${this.escapeHtml(admin.email || '-')}</td>`;
                html += `<td style="padding: 1rem;">${this.escapeHtml(admin.full_name || '-')}</td>`;
                html += `<td style="padding: 1rem; text-align: center;">${role}</td>`;
                html += `<td style="padding: 1rem; text-align: center;">${status}</td>`;
                html += `<td style="padding: 1rem; text-align: center; font-size: 0.9rem;">${lastLogin}</td>`;
                html += `</tr>`;
            });

            html += `</tbody></table></div>`;
            container.innerHTML = html;
        } catch (error) {
            container.innerHTML = `<p class="error">Error loading admin accounts: ${error.message}</p>`;
        }
    },

    async loadUsersManagement() {
        const container = document.getElementById('usersManageContainer');
        try {
            const response = await fetch(this.config.apiBaseUrl + '/crud-users.php?action=list&page=1&limit=20', {
                method: 'GET',
                headers: this.getAuthHeaders(),
                credentials: 'include' // CRITICAL: Send session cookie
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                container.innerHTML = `<p class="error">Error: ${data.message || response.statusText}</p>`;
                return;
            }

            const data = await response.json();

            if (!data.data || data.data.length === 0) {
                container.innerHTML = '<p style="color: #999; text-align: center; padding: 2rem;">No users found</p>';
                return;
            }

            let html = `<div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; background: white; border: 1px solid #ddd; border-radius: 6px;">
                <thead>
                    <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                        <th style="text-align: left; padding: 1rem; font-weight: 600; color: #333;">Name</th>
                        <th style="text-align: left; padding: 1rem; font-weight: 600; color: #333;">Phone</th>
                        <th style="text-align: left; padding: 1rem; font-weight: 600; color: #333;">Stripe ID</th>
                        <th style="text-align: left; padding: 1rem; font-weight: 600; color: #333;">Joined</th>
                        <th style="text-align: center; padding: 1rem; font-weight: 600; color: #333;">Actions</th>
                    </tr>
                </thead>
                <tbody>`;

            (data.data || []).forEach((user, idx) => {
                const joined = new Date(user.created_at).toLocaleDateString();
                const bgColor = idx % 2 === 0 ? '#fafafa' : 'white';

                html += `<tr style="border-bottom: 1px solid #eee; background: ${bgColor};">`;
                html += `<td style="padding: 1rem;"><strong>${this.escapeHtml(user.full_name)}</strong></td>`;
                html += `<td style="padding: 1rem; font-family: monospace; font-size: 0.9rem;">${this.renderPhoneLink(user.phone_number)}</td>`;
                html += `<td style="padding: 1rem; font-size: 0.85rem; color: #666;">${this.escapeHtml(user.stripe_customer_id || '-')}</td>`;
                html += `<td style="padding: 1rem; font-size: 0.9rem;">${joined}</td>`;
                html += `<td style="padding: 1rem; text-align: center;"><button class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="AdminDashboard.viewUserDetails(${user.id})">View</button></td>`;
                html += `</tr>`;
            });

            html += `</tbody></table></div>`;
            container.innerHTML = html;

            // Handle pagination
            if (data.pagination) {
                this.renderPagination('usersManagePagination', data.pagination.page, data.pagination.pages,
                    (page) => this.loadUsersManagementPage(page));
            }
        } catch (error) {
            container.innerHTML = `<p class="error">Error loading users: ${error.message}</p>`;
        }
    },

    async loadItemsManagement() {
        const container = document.getElementById('itemsManageContainer');
        try {
            const response = await fetch(this.config.apiBaseUrl + '/crud-items.php?action=list&page=1&limit=20', {
                method: 'GET',
                headers: this.getAuthHeaders(),
                credentials: 'include' // CRITICAL: Send session cookie
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                container.innerHTML = `<p class="error">Error: ${data.message || response.statusText}</p>`;
                return;
            }

            const data = await response.json();

            if (!data.data || data.data.length === 0) {
                container.innerHTML = '<p style="color: #999; text-align: center; padding: 2rem;">No items found</p>';
                return;
            }

            let html = `<div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; background: white; border: 1px solid #ddd; border-radius: 6px;">
                <thead>
                    <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                        <th style="text-align: left; padding: 1rem; font-weight: 600; color: #333;">Item #</th>
                        <th style="text-align: left; padding: 1rem; font-weight: 600; color: #333;">Title</th>
                        <th style="text-align: right; padding: 1rem; font-weight: 600; color: #333;">Starting</th>
                        <th style="text-align: right; padding: 1rem; font-weight: 600; color: #333;">Current High</th>
                        <th style="text-align: center; padding: 1rem; font-weight: 600; color: #333;">Status</th>
                        <th style="text-align: left; padding: 1rem; font-weight: 600; color: #333;">Ends</th>
                        <th style="text-align: center; padding: 1rem; font-weight: 600; color: #333;">Actions</th>
                    </tr>
                </thead>
                <tbody>`;

            (data.data || []).forEach((item, idx) => {
                const status = item.is_closed
                    ? '<span style="background: #ffe0e0; color: #e74c3c; padding: 0.4rem 0.8rem; border-radius: 4px; font-size: 0.85rem; font-weight: 600;">Closed</span>'
                    : '<span style="background: #e0ffe0; color: #27ae60; padding: 0.4rem 0.8rem; border-radius: 4px; font-size: 0.85rem; font-weight: 600;">Active</span>';
                const endTime = new Date(item.auction_end_time).toLocaleDateString();
                const bgColor = idx % 2 === 0 ? '#fafafa' : 'white';

                html += `<tr style="border-bottom: 1px solid #eee; background: ${bgColor};">`;
                html += `<td style="padding: 1rem; font-weight: 600; color: #667eea;">#${item.item_number}</td>`;
                html += `<td style="padding: 1rem;"><strong>${this.escapeHtml(item.title)}</strong></td>`;
                html += `<td style="padding: 1rem; text-align: right; color: #666;">$${parseFloat(item.starting_bid).toFixed(2)}</td>`;
                html += `<td style="padding: 1rem; text-align: right;"><strong style="color: #27ae60;">$${parseFloat(item.current_high_bid || 0).toFixed(2)}</strong></td>`;
                html += `<td style="padding: 1rem; text-align: center;">${status}</td>`;
                html += `<td style="padding: 1rem; font-size: 0.9rem;">${endTime}</td>`;
                html += `<td style="padding: 1rem; text-align: center;"><button class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="AdminDashboard.editItem(${item.id})">Edit</button></td>`;
                html += `</tr>`;
            });

            html += `</tbody></table></div>`;
            container.innerHTML = html;

            // Handle pagination
            if (data.pagination) {
                this.renderPagination('itemsManagePagination', data.pagination.page, data.pagination.pages,
                    (page) => this.loadItemsManagementPage(page));
            }
        } catch (error) {
            container.innerHTML = `<p class="error">Error loading items: ${error.message}</p>`;
        }
    },

    showCreateItemForm() {
        document.getElementById('itemModalTitle').textContent = 'Create New Item';
        document.getElementById('itemForm').reset();
        document.getElementById('itemForm').dataset.itemId = '';
        document.getElementById('itemFormError').style.display = 'none';
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('uploadPlaceholder').style.display = 'block';
        document.getElementById('createPDFBtn').style.display = 'none';
        document.getElementById('itemModal').style.display = 'block';
        this.setupImageUpload();
    },

    editUser(userId) {
        this.viewUserDetails(userId);
    },

    editItemInPanel(itemId) {
        // This is for the admin control panel edit
        const modal = document.getElementById('itemModal');
        document.getElementById('itemModalTitle').textContent = 'Edit Item';
        document.getElementById('itemForm').dataset.itemId = itemId;
        document.getElementById('itemFormError').style.display = 'none';
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('uploadPlaceholder').style.display = 'block';
        modal.style.display = 'block';
        this.setupImageUpload();

        // Load item data
        this.loadItemForEdit(itemId);
    }
};
