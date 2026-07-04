#!/bin/bash
# ============================================================
# PRE-DEPLOYMENT TEST SCRIPT
# Tests CODE + ENVIRONMENT + PERMISSIONS before deploying
# MUST pass before running push.sh
# ============================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

FAILED=0

echo "=================================="
echo "PRE-DEPLOYMENT VERIFICATION"
echo "=================================="
echo ""

# TEST 1: PHP Syntax
echo "🔍 TEST 1: PHP Syntax Check"
SYNTAX_ERRORS=$(find . -name "*.php" -type f -exec php -l {} \; 2>&1 | grep -i "parse error" | wc -l)
if [ "$SYNTAX_ERRORS" -eq 0 ]; then
    echo -e "${GREEN}✓ All PHP files have valid syntax${NC}"
else
    echo -e "${RED}✗ SYNTAX ERRORS FOUND:${NC}"
    find . -name "*.php" -type f -exec php -l {} \; 2>&1 | grep -i "parse error"
    FAILED=1
fi
echo ""

# TEST 2: File Completeness
echo "🔍 TEST 2: Critical Files Completeness"
FILES_CHECK=(
    "items.php:120"
    "item.php:100"
    "checkout.php:80"
    "admin.php:400"
)

for FILE_CHECK in "${FILES_CHECK[@]}"; do
    FILE="${FILE_CHECK%:*}"
    MIN_LINES="${FILE_CHECK#*:}"
    ACTUAL_LINES=$(wc -l < "$FILE")

    if [ "$ACTUAL_LINES" -ge "$MIN_LINES" ]; then
        echo -e "${GREEN}✓ $FILE: $ACTUAL_LINES lines (min: $MIN_LINES)${NC}"
    else
        echo -e "${RED}✗ $FILE: $ACTUAL_LINES lines (NEED AT LEAST $MIN_LINES)${NC}"
        FAILED=1
    fi
done
echo ""

# TEST 3: Link Path Consistency
echo "🔍 TEST 3: Link Path Consistency (must be RELATIVE)"
ABSOLUTE_LINKS=$(grep -r '/silentbidpro/' --include="*.php" --include="*.js" --include="*.html" . 2>/dev/null | grep -v node_modules | grep -v ".git" | wc -l)

if [ "$ABSOLUTE_LINKS" -eq 0 ]; then
    echo -e "${GREEN}✓ No hardcoded /silentbidpro/ paths found${NC}"
else
    echo -e "${RED}✗ FOUND $ABSOLUTE_LINKS instances of hardcoded /silentbidpro/ paths:${NC}"
    grep -r '/silentbidpro/' --include="*.php" --include="*.js" --include="*.html" . 2>/dev/null | grep -v node_modules | grep -v ".git" | head -5
    FAILED=1
fi
echo ""

# TEST 4: Local Permissions
echo "🔍 TEST 4: Local Directory Permissions"
DIRS_TO_CHECK=("documents" "uploads" "qr_codes" "logs")

for DIR in "${DIRS_TO_CHECK[@]}"; do
    if [ -d "$DIR" ]; then
        PERMS=$(stat -f "%OLp" "$DIR" 2>/dev/null || stat -c "%a" "$DIR" 2>/dev/null)
        echo -e "${GREEN}✓ $DIR exists (perms: $PERMS)${NC}"
    else
        mkdir -p "$DIR"
        echo -e "${YELLOW}⚠ Created missing directory: $DIR${NC}"
    fi
done
echo ""

# TEST 5: Config Files
echo "🔍 TEST 5: Configuration Files"
if [ -f "config.php" ]; then
    echo -e "${GREEN}✓ config.php exists${NC}"
else
    echo -e "${RED}✗ config.php missing${NC}"
    FAILED=1
fi

if [ -f "config.local.php" ]; then
    echo -e "${GREEN}✓ config.local.php exists${NC}"
else
    echo -e "${YELLOW}⚠ config.local.php missing (will be created on production)${NC}"
fi
echo ""

# TEST 6: .htaccess
echo "🔍 TEST 6: .htaccess Configuration"
if [ -f ".htaccess" ]; then
    echo -e "${GREEN}✓ .htaccess exists${NC}"
    if grep -q "RewriteEngine On" .htaccess; then
        echo -e "${GREEN}✓ RewriteEngine is enabled${NC}"
    else
        echo -e "${YELLOW}⚠ RewriteEngine not found in .htaccess${NC}"
    fi
else
    echo -e "${RED}✗ .htaccess missing${NC}"
    FAILED=1
fi
echo ""

# TEST 7: Database Parameter Passing
echo "🔍 TEST 7: Database Parameter Passing (must use plain arrays)"
WRONG_PARAMS=$(grep -r "dbGetRow.*array(" . --include="*.php" 2>/dev/null | wc -l)
if [ "$WRONG_PARAMS" -eq 0 ]; then
    echo -e "${GREEN}✓ Database calls use correct parameter format${NC}"
else
    echo -e "${YELLOW}⚠ Found $WRONG_PARAMS database calls with potential issues${NC}"
fi
echo ""

# SUMMARY
echo "=================================="
if [ "$FAILED" -eq 0 ]; then
    echo -e "${GREEN}✅ ALL TESTS PASSED${NC}"
    echo ""
    echo "✓ Code is valid"
    echo "✓ Files are complete"
    echo "✓ Paths are relative"
    echo "✓ Directories exist"
    echo "✓ Configuration is ready"
    echo ""
    echo "SAFE TO DEPLOY: Run ./push.sh \"Your message\""
    exit 0
else
    echo -e "${RED}❌ TESTS FAILED${NC}"
    echo ""
    echo "Fix the above issues before deploying"
    exit 1
fi
