<?php
// ============================================================
// SILENT BID BUDDY — Admin Dashboard
// Comprehensive admin interface for auction management
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/page-meta.php';

$is_logged_in = isAdminLoggedIn();
$page_title = APP_NAME . ' — Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php renderPageMeta([
        'title' => $page_title,
        'description' => 'Silent Bid Buddy administrator dashboard.',
        'stylesheets' => ['css/main.css', 'css/admin.css']
    ]); ?>
</head>
<body class="admin-page">
    <!-- Login Screen (shown if not logged in) -->
    <div id="loginContainer" class="login-container" style="<?php echo $is_logged_in ? 'display:none;' : ''; ?>">
        <div class="login-box">
            <h1><?php echo htmlspecialchars(APP_NAME); ?></h1>
            <p class="subtitle">Admin Dashboard</p>

            <form id="loginForm" class="admin-form">
                <div class="form-group">
                    <label for="adminUsername" class="form-label">Username</label>
                    <input
                        type="text"
                        id="adminUsername"
                        class="form-input"
                        placeholder="Enter your username"
                        required
                        autocomplete="username"
                    />
                </div>

                <div class="form-group">
                    <label for="adminPassword" class="form-label">Password</label>
                    <input
                        type="password"
                        id="adminPassword"
                        class="form-input"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    />
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <span class="btn-text">Sign In</span>
                    <span class="btn-spinner" style="display: none;">Signing in...</span>
                </button>

                <div id="loginError" class="error-message" style="display: none;"></div>
            </form>
        </div>
    </div>

    <!-- Dashboard (shown if logged in) -->
    <div id="dashboardContainer" class="dashboard-container" style="<?php echo !$is_logged_in ? 'display:none;' : ''; ?>">
        <!-- Header -->
        <header class="admin-header">
            <div class="header-left">
                <h1 class="dashboard-title"><?php echo htmlspecialchars(APP_NAME); ?> — Admin</h1>
            </div>
            <div class="header-right">
                <button id="logoutBtn" class="btn btn-secondary btn-small">Logout</button>
            </div>
        </header>

        <!-- Navigation Tabs -->
        <nav class="admin-nav">
            <button class="nav-tab active" data-section="dashboard">Dashboard</button>
            <button class="nav-tab" data-section="items">Items</button>
            <button class="nav-tab" data-section="users">Bidders</button>
            <button class="nav-tab" data-section="bids">Bids</button>
            <button class="nav-tab" data-section="transactions">Transactions</button>
            <button class="nav-tab" data-section="admins" style="background-color: #e8f4f8; font-weight: bold;">👤 Admin Control</button>
        </nav>

        <!-- Main Content -->
        <main class="admin-content">
            <!-- Dashboard Section -->
            <section id="dashboardSection" class="admin-section active">
                <h2>Live Auction Metrics</h2>
                <div class="last-updated">Last Updated: <span id="metricsTimestamp">-</span></div>

                <!-- Metrics Cards -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-value" id="metricActiveItems">0</div>
                        <div class="metric-label">Active Items</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value" id="metricActiveBidders">0</div>
                        <div class="metric-label">Active Bidders (1h)</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value" id="metricTotalBids">0</div>
                        <div class="metric-label">Total Bids (1h)</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value" id="metricTotalRaised">$0</div>
                        <div class="metric-label">Estimated Raised</div>
                    </div>
                </div>

                <!-- Status Cards -->
                <div class="status-grid">
                    <div class="status-card">
                        <div class="status-label">Pending Payments</div>
                        <div class="status-value" id="statusPending">0</div>
                    </div>
                    <div class="status-card">
                        <div class="status-label">Completion Rate</div>
                        <div class="status-value" id="statusCompletion">0%</div>
                    </div>
                </div>

                <!-- High Traffic Items -->
                <h3 style="margin-top: 2rem;">High-Traffic Items</h3>
                <div id="highTrafficContainer" class="data-table">
                    <p class="loading">Loading items...</p>
                </div>

                <!-- Recent Activity -->
                <h3 style="margin-top: 2rem;">Recent Activity</h3>
                <div id="recentActivityContainer" class="data-table">
                    <p class="loading">Loading activity...</p>
                </div>

                <!-- Auction Management -->
                <div style="margin-top: 2rem; padding: 1.5rem; background: #f0f9ff; border-radius: 8px; border-left: 4px solid #2563eb;">
                    <h3>Auction Management</h3>
                    <p style="color: #666; margin: 0.5rem 0 1rem 0;">Close expired auctions and sync bidder statistics</p>
                    <button id="closeAuctionsBtn" class="btn btn-primary" style="background-color: #10b981;">
                        <span class="btn-text">🏁 Close Expired Auctions</span>
                        <span class="btn-spinner" style="display: none;">Closing...</span>
                    </button>
                    <div id="closeAuctionsResult" style="margin-top: 1rem; padding: 1rem; display: none; border-radius: 4px; color: #2d5016; background: #dcfce7;"></div>
                </div>
            </section>

            <!-- Items Section -->
            <section id="itemsSection" class="admin-section">
                <h2>Auction Items</h2>

                <!-- Create Item Button -->
                <button id="createItemBtn" class="btn btn-primary" style="margin-bottom: 1.5rem;">+ Create Item</button>

                <!-- Items List -->
                <div id="itemsContainer" class="data-table">
                    <p class="loading">Loading items...</p>
                </div>

                <!-- Pagination -->
                <div id="itemsPagination" class="pagination" style="display: none; margin-top: 1rem;"></div>
            </section>

            <!-- Transactions Section -->
            <section id="transactionsSection" class="admin-section">
                <h2>Transactions & Payments</h2>

                <!-- Status Filter -->
                <div class="filter-group">
                    <label for="transactionStatusFilter">Filter by Status:</label>
                    <select id="transactionStatusFilter" class="form-input" style="width: auto;">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>

                <!-- Transactions List -->
                <div id="transactionsContainer" class="data-table">
                    <p class="loading">Loading transactions...</p>
                </div>

                <!-- Pagination -->
                <div id="transactionsPagination" class="pagination" style="display: none; margin-top: 1rem;"></div>
            </section>

            <!-- Users Section -->
            <section id="usersSection" class="admin-section">
                <div class="section-title-row">
                    <h2>Bidders & Users</h2>
                    <button type="button" id="createUserBtn" class="btn btn-primary btn-small">Create Bidder</button>
                </div>

                <!-- Search -->
                <div class="filter-group">
                    <input
                        type="text"
                        id="userSearchInput"
                        class="form-input"
                        placeholder="Search by name or phone..."
                        style="width: 100%; margin-bottom: 1rem;"
                    />
                </div>

                <!-- Users List -->
                <div id="usersContainer" class="data-table">
                    <p class="loading">Loading users...</p>
                </div>

                <!-- Pagination -->
                <div id="usersPagination" class="pagination" style="display: none; margin-top: 1rem;"></div>
            </section>

            <!-- Bids Section -->
            <section id="bidsSection" class="admin-section">
                <h2>All Bids</h2>
                <div class="filter-group">
                    <select id="bidStatusFilter" class="form-input" style="width: 200px;">
                        <option value="">All Bids</option>
                        <option value="active">Active Bids</option>
                        <option value="winning">Winning Bids</option>
                    </select>
                </div>
                <div id="bidsContainer" class="data-table">
                    <p class="loading">Loading bids...</p>
                </div>
                <div id="bidsPagination" class="pagination" style="display: none; margin-top: 1rem;"></div>
            </section>

            <!-- Admin Control Section (Super Admin Only) -->
            <section id="adminsSection" class="admin-section">
                <h2>👤 Admin Control Panel</h2>
                <p style="color: #666; margin-bottom: 1.5rem;">Manage admin accounts, users, items, and all database records</p>

                <!-- Tabs for different admin controls -->
                <div style="display: flex; gap: 0.5rem; margin-bottom: 2rem; border-bottom: 1px solid #eee; padding-bottom: 1rem;">
                    <button class="admin-control-tab active" data-target="adminAccountsTab">Admin Accounts</button>
                    <button class="admin-control-tab" data-target="usersManageTab">User Management</button>
                    <button class="admin-control-tab" data-target="itemsManageTab">Item Management</button>
                </div>

                <!-- Admin Accounts Tab -->
                <div id="adminAccountsTab" class="admin-control-content">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3>Admin Accounts</h3>
                    </div>
                    <div id="adminsContainer" class="data-table">
                        <p class="loading">Loading admin accounts...</p>
                    </div>
                </div>

                <!-- User Management Tab -->
                <div id="usersManageTab" class="admin-control-content" style="display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3>User Management</h3>
                    </div>
                    <div id="usersManageContainer" class="data-table">
                        <p class="loading">Loading users...</p>
                    </div>
                    <div id="usersManagePagination" class="pagination" style="display: none; margin-top: 1rem;"></div>
                </div>

                <!-- Items Management Tab -->
                <div id="itemsManageTab" class="admin-control-content" style="display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3>Item Management</h3>
                        <button id="createItemManageBtn" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">+ Create Item</button>
                    </div>
                    <div id="itemsManageContainer" class="data-table">
                        <p class="loading">Loading items...</p>
                    </div>
                    <div id="itemsManagePagination" class="pagination" style="display: none; margin-top: 1rem;"></div>
                </div>

            </section>
        </main>
    </div>

    <!-- Create/Edit Item Modal -->
    <div id="itemModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="itemModalTitle">Create New Item</h2>
                <button class="modal-close" data-dismiss="modal">&times;</button>
            </div>
            <form id="itemForm" class="admin-form">
                <div class="form-group">
                    <label class="form-label">Item Title *</label>
                    <input type="text" name="title" class="form-input" required />
                </div>
                <div class="form-group">
                    <div class="description-field-header">
                        <label class="form-label" for="itemDescription">Description</label>
                        <button type="button" id="improveDescriptionBtn" class="btn btn-secondary btn-small">
                            <span class="btn-text">Improve description</span>
                            <span class="btn-spinner" style="display: none;">Improving...</span>
                        </button>
                    </div>
                    <textarea
                        id="itemDescription"
                        name="description"
                        class="form-input"
                        rows="6"
                        placeholder="Describe the item, experience, restrictions, donor notes, mood, location, what is included, and anything that should appear in the final auction listing."
                    ></textarea>
                    <div class="ai-item-note">
                        <strong>Image assist:</strong>
                        A detailed description helps Silent Bid Buddy create a polished item image for cards, catalogs, and print materials. Use concrete details like setting, style, colors, audience, and what the bidder receives.
                    </div>
                    <div id="descriptionImproveResult" class="description-improve-result" style="display: none;"></div>
                </div>
                <!-- QR Code Display (for existing items) -->
                <div id="itemQRDisplay" style="display: none; margin-bottom: 2rem; padding: 1.5rem; background: #f0f4ff; border-radius: 8px; border-left: 3px solid #667eea;">
                    <label class="form-label" style="margin-bottom: 1rem;">QR Code & Document</label>
                    <div style="display: flex; gap: 2rem; align-items: center;">
                        <div style="text-align: center;">
                            <img id="modalQRCode" src="" alt="QR Code" style="max-width: 150px; border-radius: 6px; background: white; padding: 8px;" />
                            <p style="font-size: 0.85rem; color: #666; margin-top: 0.5rem;">
                                <a id="modalQRLink" href="#" target="_blank" style="color: #667eea; text-decoration: none;">View QR URL</a>
                            </p>
                        </div>
                        <div style="flex: 1;">
                            <p style="font-size: 0.95rem; color: #333; margin-bottom: 0.5rem;"><strong>Print-Ready Document</strong></p>
                            <p style="font-size: 0.85rem; color: #666; margin-bottom: 1rem;">Download the professional PDF with QR code for printing and table placement.</p>
                            <a id="modalDocumentLink" href="#" target="_blank" class="btn btn-primary" style="display: inline-block;">📄 Download Document</a>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Item Image</label>
                    <div class="image-upload-zone" id="imageUploadZone">
                        <input type="file" id="imageFileInput" name="image_file" accept="image/*" style="display: none;" />
                        <input type="hidden" name="image_url" id="imageUrlInput" />
                        <div class="upload-placeholder" id="uploadPlaceholder">
                            <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
	                            <p class="upload-text">Drag image or URL here or <button type="button" class="upload-btn" id="browseImageBtn">browse from Mac Photo</button></p>
	                            <p class="upload-hint">File (JPG, PNG, GIF, WebP) or image URL. If no strong photo is available, use the item description to generate one.</p>
	                        </div>
                        <div class="image-preview" id="imagePreview" style="display: none;">
                            <img id="previewImg" alt="Preview" />
                            <button type="button" class="remove-image-btn" id="removeImageBtn">✕ Remove</button>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Fair Market Value</label>
                    <input type="number" name="fair_market_value" class="form-input" step="0.01" />
                </div>
                <div class="form-group">
                    <label class="form-label">Starting Bid *</label>
                    <input type="number" name="starting_bid" class="form-input" step="0.01" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Minimum Increment *</label>
                    <input type="number" name="min_increment" class="form-input" step="0.01" value="5" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Buy Now Price</label>
                    <input type="number" name="buy_now_price" class="form-input" step="0.01" />
                </div>
                <div class="form-group">
                    <label class="form-label">Auction Duration (hours:minutes:seconds) *</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="number" name="duration_hours" class="form-input" min="0" value="2" style="width: 80px;" />
                        <span>:</span>
                        <input type="number" name="duration_minutes" class="form-input" min="0" max="59" value="0" style="width: 80px;" />
                        <span>:</span>
                        <input type="number" name="duration_seconds" class="form-input" min="0" max="59" value="0" style="width: 80px;" />
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Winner</label>
                    <input type="text" name="winner_name" class="form-input" readonly style="background-color: #f5f5f5; cursor: not-allowed;" />
                    <p style="font-size: 0.85rem; color: #666; margin-top: 0.25rem;">Shows the name of the bidder who won the auction (if closed)</p>
                </div>
                <div id="itemFormError" class="error-message" style="display: none;"></div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Item</button>
                    <button type="button" id="createPDFBtn" class="btn btn-secondary" style="display: none;">📄 Create PDF</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- User Details Modal -->
    <div id="userModal" class="modal" style="display: none;">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2 id="userModalTitle">Bidder Details</h2>
                <button class="modal-close" data-dismiss="modal">&times;</button>
            </div>
            <div id="userModalBody" class="modal-body">
                <p class="loading">Loading user details...</p>
            </div>
        </div>
    </div>

    <!-- Create/Edit Bidder Modal -->
    <div id="userEditModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="userEditModalTitle">Create Bidder</h2>
                <button class="modal-close" data-dismiss="modal">&times;</button>
            </div>
            <form id="userEditForm" class="admin-form">
                <div class="form-group">
                    <label class="form-label" for="userFullNameInput">Name *</label>
                    <input type="text" id="userFullNameInput" name="full_name" class="form-input" required />
                </div>
                <div class="form-group">
                    <label class="form-label" for="userPhoneInput">Phone *</label>
                    <input type="tel" id="userPhoneInput" name="phone_number" class="form-input" placeholder="(555) 123-4567" required />
                    <p class="form-hint">Phone numbers are normalized for bidder sign-in.</p>
                </div>
                <div class="form-group">
                    <label class="form-label" for="userEmailInput">Email</label>
                    <input type="email" id="userEmailInput" name="email" class="form-input" placeholder="bidder@example.org" />
                </div>
                <div class="form-group">
                    <label class="form-label" for="userStripeInput">Stripe Customer ID</label>
                    <input type="text" id="userStripeInput" name="stripe_customer_id" class="form-input" />
                </div>
                <div id="userEditError" class="error-message" style="display: none;"></div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Bidder</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Item QR Code Modal -->
    <div id="itemQRModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="qrItemTitle">Item QR Code</h2>
                <button class="modal-close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" style="text-align: center; padding: 2rem;">
                <p style="color: #666; margin-bottom: 1.5rem;">Your item has been created! Share the QR code and document with bidders.</p>

                <div style="background: #f9f9f9; padding: 2rem; border-radius: 12px; margin-bottom: 2rem;">
                    <img id="qrCodeImage" src="" alt="QR Code" style="max-width: 250px; height: auto; margin: 0 auto; display: block;" />
                </div>

                <p style="font-size: 0.9rem; color: #666; margin-bottom: 1rem;">QR Code URL:</p>
                <div style="background: #f0f4ff; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; word-break: break-all;">
                    <a id="qrCodeURL" href="#" style="color: #667eea; text-decoration: none; font-weight: 500;" target="_blank">Copy URL</a>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: center;">
                    <a id="documentLink" href="#" target="_blank" class="btn btn-primary">📄 Download Document</a>
                    <button class="btn btn-secondary" data-dismiss="modal">Done</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            AdminDashboard.init(<?php echo json_encode($is_logged_in); ?>);
        });
    </script>
</body>
</html>
