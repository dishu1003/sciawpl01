# 🚀 Hostinger Setup Guide - SpartanCommunityIndia

## 📋 Your Hostinger Database Info
- **Host**: localhost
- **Database Name**: u782093275_awpl
- **Username**: u782093275_awpl
- **Password**: Vktmdp@2025

## 🔧 Quick Setup (5 Minutes)

### Step 1: Upload Files
1. Upload all files to your Hostinger public_html folder
2. Make sure all files are uploaded correctly

### Step 2: Setup Database
```bash
# Run this in your browser:
https://your-domain.com/setup-hostinger.php
```

### Step 3: Test Everything
```bash
# Test database connection:
https://your-domain.com/test-connection.php

# Test admin login:
https://your-domain.com/admin/
# Username: admin
# Password: admin123
```

## 🎯 Hostinger Specific Steps

### 1. Database Setup
Your database credentials are already configured:
- Database: `u782093275_awpl`
- Username: `u782093275_awpl`
- Password: `Vktmdp@2025`

### 2. File Upload
Upload these files to your `public_html` folder:
```
public_html/
├── index.html (Form A)
├── _form-b-team.html (Form B)
├── _form-c-team.html (Form C)
├── _form-d-team.html (Form D)
├── thank-you.php
├── admin/ (Admin panel)
├── team/ (Team panel)
├── forms/ (Form handlers)
├── includes/ (Core files)
├── config/ (Configuration)
├── logs/ (Log files)
└── setup-hostinger.php (Setup script)
```

### 3. Domain Configuration
Update these files with your actual domain:
- Replace `your-domain.com` with your actual domain
- Update `SITE_URL` in configuration files

## 🔐 Login Credentials

### Admin Panel
- **URL**: `https://your-domain.com/admin/`
- **Username**: `admin`
- **Password**: `admin123`

### Team Panel
- **URL**: `https://your-domain.com/team/`
- **Username**: `team`
- **Password**: `team123`

## 📝 Form Flow Testing

### Complete Form Journey:
1. **Form A**: `https://your-domain.com/index.html`
2. **Form B**: `https://your-domain.com/_form-b-team.html`
3. **Form C**: `https://your-domain.com/_form-c-team.html`
4. **Form D**: `https://your-domain.com/_form-d-team.html`
5. **Thank You**: `https://your-domain.com/thank-you.php`

## 🛠️ Hostinger Control Panel Setup

### 1. Database Management
- Go to Hostinger Control Panel
- Navigate to "Databases"
- Verify database `u782093275_awpl` exists
- Check user permissions

### 2. File Manager
- Go to "File Manager"
- Navigate to `public_html`
- Upload all website files
- Set proper permissions (755 for folders, 644 for files)

### 3. SSL Certificate
- Enable SSL certificate in Hostinger
- Force HTTPS redirect
- Update all URLs to use HTTPS

## 🔧 Troubleshooting

### Issue: Database Connection Failed
**Solution:**
1. Check database credentials in Hostinger panel
2. Verify database exists
3. Check user permissions
4. Run `setup-hostinger.php`

### Issue: Forms Not Working
**Solution:**
1. Check file permissions
2. Verify all files uploaded correctly
3. Check error logs in `/logs/` folder
4. Test database connection

### Issue: Login Not Working
**Solution:**
1. Run `setup-hostinger.php` again
2. Check users table in database
3. Clear browser cache
4. Try different browser

## 📊 Hostinger Optimizations

### Performance
- Enable Hostinger's caching
- Use CDN if available
- Optimize images
- Enable compression

### Security
- Enable SSL certificate
- Set up firewall rules
- Regular backups
- Update passwords

### Monitoring
- Use Hostinger's monitoring tools
- Check error logs regularly
- Monitor database performance
- Track form submissions

## 🎉 Testing Checklist

### ✅ Database
- [ ] Database connection works
- [ ] Tables created successfully
- [ ] Admin user exists
- [ ] Team user exists

### ✅ Website
- [ ] Main page loads
- [ ] Forms work correctly
- [ ] Admin panel accessible
- [ ] Team panel accessible

### ✅ Forms
- [ ] Form A submits
- [ ] Form B loads after A
- [ ] Form C loads after B
- [ ] Form D loads after C
- [ ] Thank you page shows
- [ ] Data saves to database

### ✅ Security
- [ ] HTTPS enabled
- [ ] CSRF protection active
- [ ] Rate limiting works
- [ ] Error logging works

## 📞 Hostinger Support

If you need help:
1. Check Hostinger's documentation
2. Contact Hostinger support
3. Check error logs
4. Verify database settings

## 🚀 Go Live!

Once everything is tested:
1. Update domain in configuration files
2. Enable SSL certificate
3. Set up monitoring
4. Start capturing leads!

---

**Your SpartanCommunityIndia website is now ready on Hostinger!** 🎉
