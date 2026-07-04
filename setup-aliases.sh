#!/bin/bash
# ============================================================
# SETUP SHELL ALIASES FOR SILENTBIDPRO DEVELOPMENT
# Run this once to add aliases to your shell
# ============================================================

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "=================================="
echo "SETTING UP DEVELOPMENT ALIASES"
echo "=================================="
echo ""

# Determine shell rc file
if [ -f "$HOME/.zshrc" ]; then
    RC_FILE="$HOME/.zshrc"
    SHELL_NAME="zsh"
elif [ -f "$HOME/.bashrc" ]; then
    RC_FILE="$HOME/.bashrc"
    SHELL_NAME="bash"
else
    echo "❌ Could not find shell config file"
    exit 1
fi

echo "Shell: $SHELL_NAME"
echo "Config file: $RC_FILE"
echo ""

# Add aliases to shell config
ALIASES="
# ===== Silent Bid Pro Aliases =====
alias sbbstart='cd $PROJECT_DIR && ./start-local.sh'
alias sbbtest='cd $PROJECT_DIR && ./pre-deploy-test.sh'
alias pushit='cd $PROJECT_DIR && ./push.sh'
alias sbbverify='cd $PROJECT_DIR && ./post-deploy-verify.sh'
alias sbbdocs='cat $PROJECT_DIR/DEVELOPMENT_WORKFLOW.md'
"

# Check if aliases already exist
if grep -q "Silent Bid Pro Aliases" "$RC_FILE"; then
    echo "⚠️  Aliases already exist in $RC_FILE"
else
    echo "$ALIASES" >> "$RC_FILE"
    echo "✅ Aliases added to $RC_FILE"
fi

echo ""
echo "=================================="
echo "AVAILABLE ALIASES"
echo "=================================="
echo ""
echo "sbbstart   - Start local development server"
echo "sbbtest    - Run pre-deployment tests"
echo "pushit     - Deploy to production"
echo "sbbverify  - Verify production is healthy"
echo "sbbdocs    - View development workflow docs"
echo ""
echo "To use aliases immediately, run:"
echo "source $RC_FILE"
echo ""
