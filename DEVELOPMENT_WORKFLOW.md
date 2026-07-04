# Development Workflow - Local to Production

## Quick Start

### 1️⃣ Start Local Development Server

```bash
./start-local.sh
```

Opens at: **http://localhost:8000**

### 2️⃣ Make Your Changes Locally

Edit files in `/Users/chipmcallister/Projects/silentbidpro/`

### 3️⃣ Test Locally Before Pushing

Test in your browser:
- http://localhost:8000/index.php (login page)
- http://localhost:8000/items.php (items list)
- http://localhost:8000/admin.php (admin dashboard)

### 4️⃣ Run Pre-Deployment Tests

```bash
./pre-deploy-test.sh
```

**This must PASS before deploying.**

### 5️⃣ Deploy to Production

```bash
./pushit "Brief description of changes"
```

This will:
- ✅ Commit changes to git
- ✅ Push to GitHub
- ✅ Sync to production server
- ✅ Fix permissions automatically
- ✅ Deploy is LIVE instantly

### 6️⃣ Verify Production

```bash
./post-deploy-verify.sh
```

Reports if production is healthy or has issues.

---

## How It Works: Local vs Production

### Environment Auto-Detection

The app automatically detects where it's running:

**Local (localhost:8000)**
```
- APP_DOMAIN = http://localhost:8000
- CSS links = css/main.css (relative)
- API calls = /api/admin/...
- QR codes = http://localhost:8000/item-qr.php
```

**Production (silentbidpro.peoplestar.com)**
```
- APP_DOMAIN = https://silentbidpro.peoplestar.com
- CSS links = css/main.css (relative)
- API calls = /api/admin/...
- QR codes = https://silentbidpro.peoplestar.com/item-qr.php
```

✅ **Same code works in both places!**

---

## Complete Development Cycle

```bash
# 1. Start local server (new terminal window)
./start-local.sh

# 2. Test locally in browser
# Open: http://localhost:8000

# 3. Make changes to files

# 4. Test changes locally
# Refresh browser to see changes

# 5. When satisfied, verify tests pass
./pre-deploy-test.sh

# 6. Deploy to production (one command)
./pushit "Description of your changes"

# 7. Verify production is healthy
./post-deploy-verify.sh

# Done! Changes are live on https://silentbidpro.peoplestar.com
```

---

## What Each Script Does

| Script | Purpose | When to Use |
|--------|---------|------------|
| `start-local.sh` | Start local PHP dev server | Beginning of work session |
| `pre-deploy-test.sh` | Test code & environment before push | Before every deployment |
| `push.sh` (alias: `pushit`) | Deploy to GitHub + Production | When ready to go live |
| `post-deploy-verify.sh` | Verify production is healthy | After every deployment |

---

## Troubleshooting

### Local Server Won't Start

```bash
# Check if port 8000 is in use
lsof -i :8000

# Kill the process using that port
lsof -ti:8000 | xargs kill -9

# Try starting again
./start-local.sh
```

### Tests Fail Before Deploy

```bash
# Read the error message carefully
./pre-deploy-test.sh

# Fix the issues mentioned
# Then run tests again
./pre-deploy-test.sh

# Once tests pass, you can deploy
./pushit "Your message"
```

### Production Shows Error After Deploy

```bash
# Check production health immediately
./post-deploy-verify.sh

# If issues found, review the errors
# Fix locally, test, then redeploy
```

---

## Key Points

✅ **Always test locally first** - Fixes problems before production  
✅ **Always run pre-deploy tests** - Catches 90% of issues  
✅ **Deploy immediately when tests pass** - Reduces time between test & production  
✅ **Verify production after deploy** - Confirms deployment succeeded  

## Commands Reference

```bash
# Start development
./start-local.sh

# Test before deploying
./pre-deploy-test.sh

# Deploy to production
./pushit "Your commit message"

# Verify production
./post-deploy-verify.sh
```

That's it! The workflow handles everything else automatically.
