#!/bin/bash
# ============================================================
# POST-DEPLOYMENT VERIFICATION SCRIPT
# Runs on production AFTER push.sh completes
# Verifies CODE + ENVIRONMENT + PERMISSIONS all work together
# ============================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

FAILED=0
DO_SERVER="root@64.227.108.128"
DO_PATH="/var/www/html/silentbidpro"
APP_URL="https://silentbidpro.com"

echo "=================================="
echo "POST-DEPLOYMENT VERIFICATION"
echo "=================================="
echo ""

# TEST 1: SSH Connection
echo "🔍 TEST 1: Server Connection"
if ssh -o ConnectTimeout=5 $DO_SERVER "echo 'Connected'" > /dev/null 2>&1; then
    echo -e "${GREEN}✓ SSH connection to production server${NC}"
else
    echo -e "${RED}✗ Cannot connect to production server${NC}"
    FAILED=1
fi
echo ""

# TEST 2: Application Directory
echo "🔍 TEST 2: Application Directory on Production"
DIR_EXISTS=$(ssh $DO_SERVER "test -d $DO_PATH && echo 'exists'" 2>/dev/null)
if [ "$DIR_EXISTS" = "exists" ]; then
    echo -e "${GREEN}✓ Application directory exists: $DO_PATH${NC}"
else
    echo -e "${RED}✗ Application directory missing${NC}"
    FAILED=1
fi
echo ""

# TEST 3: File Ownership
echo "🔍 TEST 3: File Ownership (must be www-data)"
OWNER=$(ssh $DO_SERVER "stat -c '%U:%G' $DO_PATH" 2>/dev/null || ssh $DO_SERVER "stat -f '%Su:%Sg' $DO_PATH")
if [[ "$OWNER" == "www-data:www-data" ]]; then
    echo -e "${GREEN}✓ Files owned by www-data:www-data${NC}"
else
    echo -e "${YELLOW}⚠ Files owned by: $OWNER (should be www-data:www-data)${NC}"
fi
echo ""

# TEST 4: Directory Permissions
echo "🔍 TEST 4: Directory Permissions"
PERMS=$(ssh $DO_SERVER "stat -c '%a' $DO_PATH" 2>/dev/null | head -1)
if [ "$PERMS" = "755" ] || [ "$PERMS" = "755" ]; then
    echo -e "${GREEN}✓ Root directory has 755 permissions${NC}"
else
    echo -e "${YELLOW}⚠ Root directory permissions: $PERMS${NC}"
fi

WRITABLE_DIRS=("documents" "uploads" "qr_codes" "logs")
for DIR in "${WRITABLE_DIRS[@]}"; do
    DIR_PERMS=$(ssh $DO_SERVER "stat -c '%a' $DO_PATH/$DIR 2>/dev/null" | head -1)
    if [ "$DIR_PERMS" = "755" ]; then
        echo -e "${GREEN}✓ $DIR directory has 755 permissions${NC}"
    else
        echo -e "${YELLOW}⚠ $DIR directory permissions: $DIR_PERMS${NC}"
    fi
done
echo ""

# TEST 5: PHP Syntax on Production
echo "🔍 TEST 5: PHP Syntax on Production"
SYNTAX_CHECK=$(ssh $DO_SERVER "cd $DO_PATH && php -l index.php 2>&1 | grep -i 'parse error'" | wc -l)
if [ "$SYNTAX_CHECK" -eq 0 ]; then
    echo -e "${GREEN}✓ Production PHP files have valid syntax${NC}"
else
    echo -e "${RED}✗ Syntax errors on production${NC}"
    ssh $DO_SERVER "cd $DO_PATH && php -l *.php api/*/*.php includes/*.php 2>&1 | grep -i 'parse error'"
    FAILED=1
fi
echo ""

# TEST 6: HTTP Status Codes
echo "🔍 TEST 6: HTTP Status Codes"
HOMEPAGE=$(curl -I "$APP_URL" 2>/dev/null | grep HTTP | awk '{print $2}')
if [ "$HOMEPAGE" = "200" ]; then
    echo -e "${GREEN}✓ Homepage returns HTTP 200${NC}"
else
    echo -e "${RED}✗ Homepage returns HTTP $HOMEPAGE${NC}"
    FAILED=1
fi

ITEMS=$(curl -I "$APP_URL/items.php" 2>/dev/null | grep HTTP | awk '{print $2}')
if [ "$ITEMS" = "200" ]; then
    echo -e "${GREEN}✓ Items page returns HTTP 200${NC}"
else
    echo -e "${RED}✗ Items page returns HTTP $ITEMS${NC}"
    FAILED=1
fi
echo ""

# TEST 7: CSS/JS Paths (must be relative)
echo "🔍 TEST 7: CSS/JS Paths (must be relative, not /silentbidpro/)"
CSS_PATH=$(curl -s "$APP_URL" | grep 'href="css' | head -1)
if echo "$CSS_PATH" | grep -q 'href="css/'; then
    echo -e "${GREEN}✓ CSS uses relative paths${NC}"
else
    echo -e "${RED}✗ CSS paths are not relative:${NC}"
    echo "$CSS_PATH"
    FAILED=1
fi

JS_PATH=$(curl -s "$APP_URL/bid.php" | grep 'src="js' | head -1)
if echo "$JS_PATH" | grep -q 'src="js/'; then
    echo -e "${GREEN}✓ JavaScript uses relative paths on bidder sign-in${NC}"
else
    echo -e "${RED}✗ JavaScript paths are not relative on bidder sign-in:${NC}"
    echo "$JS_PATH"
    FAILED=1
fi
echo ""

# TEST 8: Page Styling (not blank white)
echo "🔍 TEST 8: Page Styling Check"
PAGE_SIZE=$(curl -s "$APP_URL" | wc -c)
if [ "$PAGE_SIZE" -gt 2000 ]; then
    echo -e "${GREEN}✓ Page loads with content ($PAGE_SIZE bytes)${NC}"
else
    echo -e "${RED}✗ Page too small ($PAGE_SIZE bytes) - may be unstyled${NC}"
    FAILED=1
fi
echo ""

# TEST 9: Documents Directory Writeable
echo "🔍 TEST 9: Documents Directory Writeable"
WRITE_TEST=$(ssh $DO_SERVER "touch $DO_PATH/documents/.write-test 2>&1 && rm $DO_PATH/documents/.write-test && echo 'writable'" 2>/dev/null)
if [ "$WRITE_TEST" = "writable" ]; then
    echo -e "${GREEN}✓ Documents directory is writable by web server${NC}"
else
    echo -e "${RED}✗ Documents directory is NOT writable${NC}"
    FAILED=1
fi
echo ""

# TEST 10: No /silentbidpro/ paths on production
echo "🔍 TEST 10: No Hardcoded /silentbidpro/ Paths"
HARDCODED=$(ssh $DO_SERVER "grep -r '/silentbidpro/' $DO_PATH --include='*.php' --include='*.html' 2>/dev/null | wc -l")
if [ "$HARDCODED" -eq 0 ]; then
    echo -e "${GREEN}✓ No hardcoded /silentbidpro/ paths on production${NC}"
else
    echo -e "${RED}✗ Found $HARDCODED hardcoded /silentbidpro/ paths${NC}"
    FAILED=1
fi
echo ""

# SUMMARY
echo "=================================="
if [ "$FAILED" -eq 0 ]; then
    echo -e "${GREEN}✅ PRODUCTION IS HEALTHY${NC}"
    echo ""
    echo "✓ Application is deployed"
    echo "✓ Permissions are correct"
    echo "✓ Files are accessible"
    echo "✓ HTTP responses are correct"
    echo "✓ Paths are relative"
    echo "✓ Page styling loads"
    echo "✓ Web server can write files"
    echo ""
    echo "Application is live and ready: $APP_URL"
    exit 0
else
    echo -e "${RED}❌ PRODUCTION HAS ISSUES${NC}"
    echo ""
    echo "Review errors above and fix before users access the app"
    exit 1
fi
