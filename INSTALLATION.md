# INSTALLATION GUIDE - School Finance Management System

## Quick Start (5 Minutes)

### Step 1: Verify XAMPP Installation
1. Start XAMPP Control Panel
2. Click "Start" on Apache and MySQL
3. Verify both show "Running" (green indicator)

### Step 2: Extract Project
1. Extract `Finance_System.zip` to `C:\xampp\htdocs\`
2. Folder should be: `C:\xampp\htdocs\Finance_System\`

### Step 3: Create Database
1. Open browser and go to: `http://localhost/phpmyadmin`
2. Click on "Import" tab
3. Click "Choose File" and select `database.sql` from the project
4. Scroll down and click "Import" button
5. You should see: "Insertion of data in table 'users' succeeded"

### Step 4: Access System
Open browser and go to:
```
http://localhost/Finance_System/
```

### Step 5: Login
Use these credentials:
- **Master Login:** master / 1234
- **Finance Login:** finance / 1234

---

## Detailed Installation Steps

### Prerequisites Check
- [ ] XAMPP is installed
- [ ] Apache service works
- [ ] MySQL service works
- [ ] PHP version 7.0+
- [ ] phpMyAdmin is accessible

### Installation Process

#### 1. Download & Extract
```
Source: Download Finance_System
Target: C:\xampp\htdocs\Finance_System\
```

#### 2. Start Services
```
XAMPP Control Panel → Start Apache
XAMPP Control Panel → Start MySQL
```

#### 3. Create Database
**Option A: Using phpMyAdmin (Recommended)**
1. Navigate to `http://localhost/phpmyadmin`
2. Click "Import" in top menu
3. Select `Finance_System/database.sql`
4. Click "Import" button at bottom
5. Wait for completion message

**Option B: Manual SQL Execution**
1. Go to `http://localhost/phpmyadmin`
2. Click on "SQL" tab
3. Paste contents of `database.sql`
4. Click "Go" to execute

#### 4. Verify Installation
Go to `http://localhost/Finance_System/login.php`

You should see the login page with:
- SFMS heading
- Username field
- Password field
- Test credentials info box

#### 5. Test Login
Try logging in with:
- Username: `master`
- Password: `1234`

You should see the Master Dashboard

---

## Troubleshooting

### Issue: "Connection Failed"
**Solution:**
1. Check MySQL is running (XAMPP Control Panel)
2. Verify database name in `config/db.php`
3. Check default user: `root` with empty password

### Issue: "404 Not Found"
**Solution:**
1. Verify folder is at `C:\xampp\htdocs\Finance_System\`
2. Check URL: `http://localhost/Finance_System/`
3. Ensure Apache is running

### Issue: "Page Blank or Not Loading"
**Solution:**
1. Check PHP error log
2. Verify all files are extracted
3. Check file permissions
4. Review browser console for errors (F12)

### Issue: "Database not found"
**Solution:**
1. Open phpMyAdmin
2. Verify `school_finance` database exists
3. Re-import `database.sql` if needed
4. Check MySQL error log

### Issue: "Login fails with correct credentials"
**Solution:**
1. Check session.php file exists in `includes/`
2. Verify PHP sessions are enabled
3. Clear browser cookies
4. Check database `users` table has rows
5. Try incognito/private window

### Issue: "CSS not loading (unstyled page)"
**Solution:**
1. Check `assets/css/style.css` exists
2. Clear browser cache (Ctrl+Shift+Del)
3. Verify CDN links (Bootstrap, FontAwesome)
4. Check browser console for 404 errors

---

## Post-Installation Configuration

### Optional: Change Database Credentials
If you set up MySQL with different credentials:

Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');    // or IP address
define('DB_USER', 'your_username'); // MySQL username
define('DB_PASS', 'your_password'); // MySQL password
define('DB_NAME', 'school_finance');
```

### Optional: Change Classes/Sections
Edit `config/config.php`:
```php
// Change classes
$CLASSES = ['1', '2', '3', '4', '5', ...]; // Add/remove as needed

// Change sections
$SECTIONS = ['A', 'B', 'C', ...]; // Add/remove as needed
```

### Optional: Change Site Information
Edit `config/config.php`:
```php
define('SITE_NAME', 'Your School Name');
define('SYSTEM_NAME', 'Your System Abbreviation');
define('BASE_URL', 'http://localhost/Finance_System/');
```

---

## Security Setup (Optional)

### Create Custom User
To add more users besides master/finance:

1. Go to `http://localhost/phpmyadmin`
2. Select `school_finance` database
3. Click `users` table
4. Click "Insert" tab
5. Fill in:
   - username: `your_username`
   - password: `your_password`
   - role: `master` or `finance`
6. Click "Go"

### Change Default Passwords
1. Go to `http://localhost/phpmyadmin`
2. Select `school_finance` database
3. Click `users` table
4. Edit user you want to change
5. Update password field
6. Click "Go" to save

---

## File Permissions

On Linux/Mac, you may need to set permissions:

```bash
# Make config folder readable
chmod 755 config/

# Make includes folder readable
chmod 755 includes/

# Make assets folder readable
chmod 755 assets/

# Make master/finance folders readable
chmod 755 master/ finance/

# If uploads enabled, make writable:
chmod 777 pdf/
```

---

## Backup System

### Weekly Backup
1. Use phpMyAdmin Export feature
2. Or copy entire project folder
3. Store in external drive or cloud

### Database Only Backup
1. Go to phpMyAdmin
2. Select `school_finance` database
3. Click "Export"
4. Select "SQL" format
5. Click "Go"
6. Save file safely

---

## Performance Tips

1. **Clear Browser Cache Regularly**
   - Ctrl+Shift+Delete (Windows)
   - Cmd+Shift+Delete (Mac)

2. **Optimize Database**
   - Already done in database.sql
   - Includes indexes for fast queries

3. **Backup Regularly**
   - Weekly database exports
   - Monthly full backups

---

## Uninstallation

To remove the system:

1. Stop XAMPP services
2. Delete folder: `C:\xampp\htdocs\Finance_System\`
3. (Optional) Drop database in phpMyAdmin
4. Clear browser cache

---

## Support Resources

- **PHP Errors:** Check `error.log` in project root
- **MySQL Errors:** Check XAMPP MySQL error log
- **Browser Issues:** Check browser console (F12)
- **Database Issues:** Use phpMyAdmin diagnostics

---

## System Testing Checklist

- [ ] Can access login page
- [ ] Can login with master credentials
- [ ] Can login with finance credentials
- [ ] Master dashboard loads
- [ ] Finance dashboard loads
- [ ] Can add student
- [ ] Can search students
- [ ] Can view fees
- [ ] Can record payment
- [ ] Styling loads correctly
- [ ] Tables are responsive
- [ ] Forms validate input
- [ ] Alerts show properly
- [ ] PDF generation works

---

## You're Ready!

If all tests pass, your system is ready to use.

For support: Refer to README.md for full documentation

**Enjoy using SFMS! 🎉**
