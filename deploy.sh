#!/bin/bash
# ============================================================
# Silent Bid Buddy — Deployment Script for Digital Ocean
# Run as root: bash deploy.sh
# ============================================================

set -e

echo "=================================="
echo "SILENT BID BUDDY DEPLOYMENT"
echo "=================================="
echo ""

# Configuration
DOMAIN="silentbidbuddy"
WEB_ROOT="/var/www/silentbidbuddy"
DB_NAME="silentbidbuddy"
DB_USER="mcallpl"
DB_PASS="amazing123"
DB_HOST="localhost"

echo "📦 Step 1: Create directories..."
mkdir -p $WEB_ROOT
mkdir -p $WEB_ROOT/logs
mkdir -p $WEB_ROOT/uploads
mkdir -p $WEB_ROOT/qr_codes
echo "   ✓ Directories created"

echo ""
echo "📦 Step 2: Clone repository..."
if [ -d "$WEB_ROOT/.git" ]; then
    echo "   Repository already exists, pulling latest..."
    cd $WEB_ROOT
    git pull origin main
else
    echo "   Cloning repository..."
    git clone https://github.com/chipmcallister/silentbidbuddy.git $WEB_ROOT
fi
echo "   ✓ Repository cloned/updated"

echo ""
echo "📦 Step 3: Set file permissions..."
chown -R www-data:www-data $WEB_ROOT
chmod 755 $WEB_ROOT
chmod 755 $WEB_ROOT/{api,includes,css,js,sql,logs,uploads,qr_codes}
chmod 644 $WEB_ROOT/*.php
chmod 644 $WEB_ROOT/api/*/*.php
chmod 644 $WEB_ROOT/includes/*.php
chmod 755 $WEB_ROOT/auction.php
echo "   ✓ Permissions set"

echo ""
echo "📦 Step 4: Create local config..."
if [ ! -f "$WEB_ROOT/config.local.php" ]; then
    cat > "$WEB_ROOT/config.local.php" << 'EOF'
<?php
// Local server configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'mcallpl');
define('DB_PASS', 'amazing123');
define('DB_NAME', 'silentbidbuddy');

// Set domain based on server
$protocol = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
$domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('APP_DOMAIN', $protocol . '://' . $domain);

?>
EOF
    echo "   ✓ config.local.php created"
else
    echo "   ✓ config.local.php already exists"
fi

echo ""
echo "📦 Step 5: Initialize database..."
mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" $DB_NAME < $WEB_ROOT/sql/schema.sql 2>/dev/null || {
    echo "   Creating database..."
    mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" $DB_NAME < $WEB_ROOT/sql/schema.sql
}
echo "   ✓ Database initialized"

echo ""
echo "📦 Step 6: Configure Apache..."
if command -v apache2ctl &> /dev/null; then
    echo "   Enabling mod_rewrite..."
    a2enmod rewrite 2>/dev/null || true

    # Create Apache config if needed
    if [ ! -f "/etc/apache2/sites-available/silentbidbuddy.conf" ]; then
        cat > "/etc/apache2/sites-available/silentbidbuddy.conf" << EOF
<VirtualHost *:80>
    ServerName silentbidbuddy.${DOMAIN}
    ServerAlias *.${DOMAIN}
    DocumentRoot $WEB_ROOT

    <Directory $WEB_ROOT>
        AllowOverride All
        Require all granted

        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteBase /
        </IfModule>
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php-fpm.sock|fcgi://localhost"
    </FilesMatch>

    ErrorLog \${APACHE_LOG_DIR}/silentbidbuddy_error.log
    CustomLog \${APACHE_LOG_DIR}/silentbidbuddy_access.log combined
</VirtualHost>
EOF
        echo "   ✓ Apache config created"
    fi

    # Enable site
    a2ensite silentbidbuddy.conf 2>/dev/null || true
    apache2ctl configtest > /dev/null && apache2ctl reload || true
fi

echo ""
echo "✅ DEPLOYMENT COMPLETE!"
echo ""
echo "=================================="
echo "NEXT STEPS:"
echo "=================================="
echo ""
echo "1. Access the application:"
echo "   http://$(hostname -I | awk '{print $1}')/index.php"
echo ""
echo "2. Create your first auction item:"
echo "   cd $WEB_ROOT"
echo "   php auction.php item:create"
echo ""
echo "3. Generate QR codes:"
echo "   php auction.php qr:generate"
echo ""
echo "4. Monitor live auctions:"
echo "   php auction.php monitor:live"
echo ""
echo "=================================="
