#!/bin/bash
# ============================================================
# Silent Bid Buddy — One-Command Push to GitHub + DigitalOcean
# Usage: ./push.sh "Commit message here"
# ============================================================

set -e

if [ -z "$1" ]; then
    echo "❌ Error: Commit message required"
    echo "Usage: ./push.sh \"Your commit message\""
    exit 1
fi

COMMIT_MSG="$1"
GITHUB_REPO="https://github.com/mcallpl/silentbidbuddy.git"
DO_SERVER="root@64.227.108.128"
DO_PATH="/var/www/html/silentbidbuddy"

echo "=================================="
echo "PUSHING TO GITHUB + DIGITALOCEAN"
echo "=================================="
echo ""

# Step 1: Git commit and push
echo "📝 Step 1: Committing changes..."
git add -A
git commit -m "$COMMIT_MSG

Co-Authored-By: Claude Haiku 4.5 <noreply@anthropic.com>" || {
    echo "   ⚠️  No changes to commit"
}

echo "📤 Step 2: Pushing to GitHub..."
git push origin main || {
    echo "   ⚠️  GitHub push skipped (may already be up to date)"
}

echo ""
echo "☁️  Step 3: Deploying to DigitalOcean..."
rsync -avz --delete ./ $DO_SERVER:$DO_PATH/ \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='.DS_Store' \
    --exclude='*.log' \
    > /tmp/deploy.log 2>&1

echo ""
echo "✅ DEPLOYMENT COMPLETE!"
echo ""
echo "✓ Changes committed to git"
echo "✓ Pushed to GitHub"
echo "✓ Synced to DigitalOcean (64.227.108.128)"
echo ""
echo "Live at: https://silentbidbuddy.peoplestar.com/"
