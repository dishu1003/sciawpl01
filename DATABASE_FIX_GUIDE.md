# 🔧 Database Connection Fix Guide

## 🚨 Error: Access denied for user 'root'@'localhost'

This error means your MySQL has a password set, but our script is trying to connect without one.

## 🚀 Quick Fix Solutions

### Option 1: Automatic Fix (Recommended)
```bash
php fix-database.php
```
This will try common passwords automatically.

### Option 2: Manual Setup
```bash
# Open in browser:
http://localhost/setup-database-manual.php
```
Enter your MySQL credentials manually.

### Option 3: Updated Auto Setup
```bash
php setup-database.php
```
Now tries multiple common passwords.

## 🔍 Find Your MySQL Password

### For XAMPP Users:
1. Open XAMPP Control Panel
2. Click "Config" next to MySQL
3. Select "my.ini" or "my.cnf"
4. Look for password settings
5. **Default XAMPP password is usually EMPTY**

### For WAMP Users:
1. Open WAMP Control Panel
2. Right-click on WAMP icon
3. Go to "MySQL" → "my.ini"
4. Look for password settings
5. **Default WAMP password is usually EMPTY**

### For MAMP Users:
- Username: `root`
- Password: `root`

### For Custom Installations:
Check your MySQL configuration or ask your system administrator.

## 🛠️ Manual Solutions

### Solution 1: Reset MySQL Password
```bash
# Stop MySQL service
# Start MySQL without authentication
mysqld --skip-grant-tables

# In another terminal, connect to MySQL
mysql -u root

# Reset password
ALTER USER 'root'@'localhost' IDENTIFIED BY '';
FLUSH PRIVILEGES;
```

### Solution 2: Create New User
```sql
-- Connect to MySQL as root
mysql -u root -p

-- Create new user
CREATE USER 'spartan'@'localhost' IDENTIFIED BY 'spartan123';
GRANT ALL PRIVILEGES ON *.* TO 'spartan'@'localhost';
FLUSH PRIVILEGES;
```

Then update your database config to use:
- Username: `spartan`
- Password: `spartan123`

### Solution 3: Use Different Port
Some setups use different ports:
- Standard: `3306`
- XAMPP: `3306`
- MAMP: `8889`

## 📋 Step-by-Step Fix

### Step 1: Test MySQL Connection
```bash
# Try connecting manually
mysql -u root -p
# Enter your password when prompted
```

### Step 2: If Connection Works
Run our setup script:
```bash
php setup-database.php
```

### Step 3: If Connection Fails
Try the automatic fix:
```bash
php fix-database.php
```

### Step 4: Still Having Issues?
Use manual setup:
```
http://localhost/setup-database-manual.php
```

## 🔧 Common Issues & Solutions

### Issue: "MySQL service not running"
**Solution:**
- Start XAMPP/WAMP control panel
- Start MySQL service
- Wait for it to turn green

### Issue: "Database doesn't exist"
**Solution:**
- Our script creates the database automatically
- Just run `php fix-database.php`

### Issue: "Permission denied"
**Solution:**
- Run as administrator
- Check file permissions
- Ensure MySQL user has proper privileges

### Issue: "Port already in use"
**Solution:**
- Stop other MySQL instances
- Use different port
- Check XAMPP/WAMP configuration

## 🎯 Quick Test Commands

### Test 1: Basic Connection
```bash
php -r "
try {
    \$pdo = new PDO('mysql:host=localhost', 'root', '');
    echo 'SUCCESS: No password needed\n';
} catch (Exception \$e) {
    echo 'FAILED: ' . \$e->getMessage() . '\n';
}
"
```

### Test 2: With Password
```bash
php -r "
try {
    \$pdo = new PDO('mysql:host=localhost', 'root', 'root');
    echo 'SUCCESS: Password is root\n';
} catch (Exception \$e) {
    echo 'FAILED: ' . \$e->getMessage() . '\n';
}
"
```

## 📞 Still Need Help?

### Check These Files:
1. `fix-database.php` - Automatic fix
2. `setup-database-manual.php` - Manual setup
3. `test-connection.php` - Test after setup

### Verify Setup:
After successful setup, test:
1. `http://localhost/test-connection.php`
2. `http://localhost/admin/` (admin/admin123)
3. `http://localhost/index.html` (form flow)

## 🎉 Success Indicators

You'll know it's working when:
- ✅ `php fix-database.php` shows "SUCCESS!"
- ✅ `test-connection.php` shows green checkmarks
- ✅ Admin login works
- ✅ Forms save data to database

---

**Most Common Solution:** Run `php fix-database.php` - it fixes 90% of database issues automatically! 🚀
