# ⚡ Quick Setup Guide - Security Updates

## 🚨 IMPORTANT: Follow These Steps Immediately

### Step 1: Backup Current System (5 minutes)
```bash
# Backup database
mysqldump -u u782093275_awpl -p u782093275_awpl > backup_$(date +%Y%m%d).sql

# Backup files
tar -czf backup_files_$(date +%Y%m%d).tar.gz .
```

### Step 2: Update .env File (2 minutes)
```bash
# The .env file is already created with your current credentials
# But you should generate new secure keys:

# Open .env file
nano .env

# Replace these values with newly generated keys:
# ENCRYPTION_KEY=<generate new 32 char key>
# WEBHOOK_SECRET=<generate new secret>
# SESSION_SECRET=<generate new secret>
```

**Generate New Keys:**
```php
<?php
// Run this in PHP to generate secure keys
echo "ENCRYPTION_KEY=" . bin2hex(random_bytes(16)) . "\n";
echo "WEBHOOK_SECRET=" . bin2hex(random_bytes(32)) . "\n";
echo "SESSION_SECRET=" . bin2hex(random_bytes(32)) . "\n";
?>
```

### Step 3: Run Database Migration (3 minutes)
```bash
# Login to phpMyAdmin or use command line
mysql -u u782093275_awpl -p u782093275_awpl < migrations/add_indexes.sql
```

**Or via phpMyAdmin:**
1. Login to phpMyAdmin
2. Select database: `u782093275_awpl`
3. Go to "Import" tab
4. Choose file: `migrations/add_indexes.sql`
5. Click "Go"

### Step 4: Set File Permissions (1 minute)
```bash
# Make logs directory writable
chmod 755 logs/
chmod 644 logs/.gitignore

# Protect .env file
chmod 600 .env
```

### Step 5: Update Your Forms (10 minutes)

**Add CSRF Token to ALL Forms:**

Find all your form files and add this line inside each `<form>` tag:
```php
<?php echo CSRF::inputField(); ?>
```

**Example:**
```html
<!-- BEFORE -->
<form method="POST" action="/forms/submit_a.php">
    <input type="text" name="full_name">
    <button type="submit">Submit</button>
</form>

<!-- AFTER -->
<form method="POST" action="/forms/submit_a.php">
    <?php echo CSRF::inputField(); ?>
    <input type="text" name="full_name">
    <button type="submit">Submit</button>
</form>
```

**Files to Update:**
- `index.php` (Form A)
- `form-b.php` (Form B)
- `form-c.php` (Form C)
- `form-d.php` (Form D)
- Any admin forms

### Step 6: Update Other Form Handlers (15 minutes)

Update these files similar to `forms/submit_a.php`:
- `forms/submit_b.php`
- `forms/submit_c.php`
- `forms/submit_d.php`

**Add at the top of each file:**
```php
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/security.php';

SecurityHeaders::setAll();

// Rate limiting
$rateLimiter = new RateLimiter($pdo, 'form_submission');
if (!$rateLimiter->check(5, 300, 900)) {
    Logger::security('Rate limit exceeded');
    $_SESSION['error'] = 'Too many submissions. Please try again later.';
    header('Location: /previous-page.php');
    exit;
}

// CSRF Protection
CSRF::validateRequest();
```

### Step 7: Test Everything (10 minutes)

1. **Test Form Submission:**
   - Go to your website
   - Fill out Form A
   - Submit and check if it works
   - Check logs: `logs/app.log`

2. **Test Rate Limiting:**
   - Submit form 6 times quickly
   - Should get blocked on 6th attempt
   - Wait 15 minutes or reset manually

3. **Test CSRF Protection:**
   - Try submitting form without token
   - Should get "CSRF token validation failed" error

4. **Check Logs:**
   ```bash
   tail -f logs/app.log
   tail -f logs/error.log
   tail -f logs/security.log
   ```

### Step 8: Monitor for 24 Hours

**Check these regularly:**
```bash
# Check for errors
tail -20 logs/error.log

# Check security events
tail -20 logs/security.log

# Check general activity
tail -50 logs/app.log
```

---

## 🔧 Quick Commands

### Generate Secure Keys
```bash
php -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"
```

### Check Database Indexes
```sql
SHOW INDEX FROM leads;
SHOW INDEX FROM users;
```

### Reset Rate Limit (for testing)
```php
<?php
require_once 'includes/init.php';
require_once 'includes/security.php';
$rateLimiter = new RateLimiter($pdo, 'form_submission');
$rateLimiter->reset();
echo "Rate limit reset!";
?>
```

### View Recent Logs
```bash
# Last 50 lines of app log
tail -50 logs/app.log

# Last 20 errors
tail -20 logs/error.log

# Watch logs in real-time
tail -f logs/app.log
```

### Clear Old Logs
```php
<?php
require_once 'includes/logger.php';
Logger::clearOldLogs(30); // Clear logs older than 30 days
echo "Old logs cleared!";
?>
```

---

## ⚠️ Common Issues & Quick Fixes

### Issue 1: "Class 'CSRF' not found"
**Fix:** Add to top of file:
```php
require_once __DIR__ . '/../includes/init.php';
```

### Issue 2: "Class 'Logger' not found"
**Fix:** Add to top of file:
```php
require_once __DIR__ . '/../includes/logger.php';
```

### Issue 3: "Class 'RateLimiter' not found"
**Fix:** Add to top of file:
```php
require_once __DIR__ . '/../includes/security.php';
```

### Issue 4: Forms not submitting
**Fix:** Check if CSRF token is present:
```html
<!-- View page source and look for: -->
<input type="hidden" name="csrf_token" value="...">
```

### Issue 5: "Too many requests" error during testing
**Fix:** Reset rate limit:
```php
$rateLimiter = new RateLimiter($pdo, 'form_submission');
$rateLimiter->reset();
```

---

## 📋 Checklist

- [ ] Backup database
- [ ] Backup files
- [ ] Update .env with new keys
- [ ] Run database migration
- [ ] Set file permissions
- [ ] Add CSRF tokens to all forms
- [ ] Update form handlers (submit_b, submit_c, submit_d)
- [ ] Test form submission
- [ ] Test rate limiting
- [ ] Test CSRF protection
- [ ] Check logs for errors
- [ ] Monitor for 24 hours

---

## 🎯 Priority Order

**Do These First (Critical):**
1. ✅ Backup everything
2. ✅ Run database migration
3. ✅ Update .env keys
4. ✅ Add CSRF to forms

**Do These Next (Important):**
5. Update other form handlers
6. Test everything
7. Monitor logs

**Do These Later (Optional):**
8. Optimize further
9. Add more features
10. Performance tuning

---

## 📞 Need Help?

**Check Logs First:**
```bash
tail -50 logs/error.log
```

**Common Log Locations:**
- Application logs: `logs/app.log`
- Error logs: `logs/error.log`
- Security logs: `logs/security.log`

**Debug Mode:**
Edit `.env`:
```
APP_DEBUG=true
```
(Remember to set back to `false` in production!)

---

**Estimated Total Time:** 45-60 minutes
**Difficulty:** Medium
**Risk Level:** Low (with backup)

✅ **You're all set! Your system is now much more secure!**
