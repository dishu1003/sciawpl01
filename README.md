# Lead Management System - Installation Guide

## 🚀 Quick Setup for Hostinger

### Step 1: Upload Files
1. Login to Hostinger cPanel
2. Go to File Manager
3. Navigate to `public_html` folder
4. Upload all files maintaining folder structure

### Step 2: Create Database
1. Go to MySQL Databases in cPanel
2. Create new database: `lead_management`
3. Create database user with password
4. Assign user to database with ALL PRIVILEGES
5. Import `database.sql` file using phpMyAdmin

### Step 3: Configure Database
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'lead_management');# awplsci
