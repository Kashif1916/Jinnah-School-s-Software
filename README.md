# School Finance Management System (SFMS)

A complete, production-ready School Finance Management System built with PHP, MySQL, HTML, and CSS. Designed to run on XAMPP localhost with clean folder structure and modular code.

## 📋 Features

### Authentication System
- **Two Role-Based Access Control**
  - **MASTER (Admin)**: Full system access
  - **FINANCE (Clerk)**: Limited access (fee payment & defaulter list only)
- Session-based login with secure credentials
- Separate dashboards for each role

### Master Dashboard Features

1. **Add Student**
   - Form with all student details
   - Automatic annual fee record creation for 12 months
   - Form validation on submission

2. **Edit Student**
   - Search by name, class, or section
   - Update student information
   - Modify monthly fees

3. **Fee Management**
   - Search students by name, class, section
   - View complete fee history (month-wise)
   - Mark fees as paid
   - Automatic fee record generation for new months
   - Generate payment receipts as printable documents

4. **Defaulter List**
   - View students with unpaid fees
   - Filter by month, class, and section
   - Export defaulter report as PDF
   - Real-time defaulter count

5. **Payment Analytics**
   - Daily collection summary
   - Monthly collection summary
   - Filterable payment history
   - Average payment calculations
   - Last 12 months data

6. **Promotion System**
   - Promote entire class to next class
   - Update class field in database for all students
   - Batch operation support

7. **Drop Student**
   - Mark student as "dropped"
   - Exclude from active records
   - View history of dropped students

### Finance Dashboard Features

1. **Fee Payment** - Record payment for unpaid fees
2. **Defaulter List** - View students with unpaid fees (read-only)

## 🗄️ Database Structure

### Tables

**users**
- id (PK)
- username
- password
- role (master / finance)
- created_at

**students**
- id (PK)
- name
- father_name
- class
- section
- monthly_fee
- description
- contact_number
- status (active / dropped)
- created_at
- updated_at

**fee_records**
- id (PK)
- student_id (FK)
- month (e.g., Jan-2026)
- amount
- status (paid / unpaid)
- payment_date
- created_at

**payments**
- id (PK)
- student_id (FK)
- amount
- paid_for_month
- payment_date
- received_by
- created_at

## 📂 Project Structure

```
/Finance_System
├── config/
│   ├── db.php           (Database configuration)
│   └── config.php       (General configuration)
├── includes/
│   ├── session.php      (Session management)
│   └── helpers.php      (Helper functions)
├── master/
│   ├── dashboard.php
│   ├── add_student.php
│   ├── edit_student.php
│   ├── fee_management.php
│   ├── defaulter_list.php
│   ├── payment_analytics.php
│   ├── promotion.php
│   ├── drop_student.php
│   ├── receipt.php      (PDF generation)
│   └── defaulter_report.php
├── finance/
│   ├── dashboard.php
│   ├── fee_payment.php
│   └── defaulter_list.php
├── assets/
│   ├── css/
│   │   └── style.css    (Modern styling)
│   ├── js/
│   │   └── script.js    (Client-side functionality)
│   └── images/
├── pdf/                 (PDF storage)
├── login.php            (Login page)
├── logout.php           (Logout handler)
├── index.php            (Dashboard router)
├── database.sql         (Database setup)
└── .htaccess           (URL rewriting)
```

## 🚀 Installation & Setup

### Prerequisites
- XAMPP installed and running
- Apache & MySQL services enabled
- PHP 7.0 or higher

### Step 1: Download & Extract
Extract the project to:
```
C:\xampp\htdocs\Finance_System\
```

### Step 2: Create Database
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Go to "Import" tab
3. Select `database.sql` from the project folder
4. Click "Import"

**OR** Run SQL manually:
1. Open phpMyAdmin
2. Create new database: `school_finance`
3. Copy and paste contents of `database.sql`
4. Execute

### Step 3: Access Application
Navigate to:
```
http://localhost/Finance_System/
```

## 🔐 Default Credentials

### Master (Admin)
- **Username:** `master`
- **Password:** `1234`

### Finance (Clerk)
- **Username:** `finance`
- **Password:** `1234`

## 📊 Usage Guide

### For Master (Admin)

#### Adding a Student
1. Navigate to "Add Student"
2. Fill in all required fields
3. Submit form
4. Annual fees will be automatically created for 12 months

#### Managing Fees
1. Go to "Fee Management"
2. Search for student by name, class, or section
3. View student's fee history
4. Mark fees as paid to create payment records
5. Generate payment receipt for printing

#### Viewing Defaulters
1. Go to "Defaulter List"
2. Apply filters (optional)
3. Export as PDF for records

#### Analytics
1. Go to "Payment Analytics"
2. Select date to view daily collection
3. View monthly summary for last 12 months
4. Filter by date range

#### Promotion
1. Go to "Promotion"
2. Select current class and section
3. Select target class and section
4. Click "Promote Class"

### For Finance (Clerk)

#### Recording Payment
1. Go to "Fee Payment"
2. Search for student
3. View unpaid fees
4. Click "Record Payment" for each unpaid month
5. Generate receipt for student

#### Viewing Defaulters
1. Go to "Defaulter List"
2. Apply filters to search
3. Contact students for payment

## ⚙️ Configuration

### Database Connection
Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_finance');
```

### General Settings
Edit `config/config.php`:
- Change `BASE_URL` if needed
- Modify `$CLASSES` and `$SECTIONS` arrays
- Update site name and system name

## 🎨 UI/UX Features

- **Modern Dashboard** with gradient backgrounds
- **Responsive Design** - Works on desktop and tablet
- **Sidebar Navigation** - Easy access to all features
- **Statistics Cards** - Quick overview of key metrics
- **Data Tables** - Sortable and responsive
- **Form Validation** - Client and server-side
- **Alert Messages** - Success and error notifications
- **Bootstrap Integration** - Professional components
- **Font Awesome Icons** - Professional iconography
- **Smooth Animations** - Enhanced user experience

## 🔒 Security Features

- **Prepared Statements** - SQL injection prevention
- **Session Management** - Secure user authentication
- **Role-Based Access Control** - Feature restrictions
- **Input Sanitization** - XSS prevention
- **.htaccess Protection** - Directory listing disabled
- **HTTPS Ready** - Support for secure connections

## 📝 Additional Features

- **Annual Fee Generation** - Automatic 12-month fee record creation
- **Payment Tracking** - Detailed payment history
- **Receipt Generation** - Printable payment receipts
- **PDF Reports** - Defaulter lists export
- **Search & Filter** - Quick data retrieval
- **Data Validation** - Form field verification
- **Responsive Layout** - Mobile-friendly interface
- **User-Friendly** - Intuitive navigation

## 🐛 Troubleshooting

### Database Connection Error
- Check MySQL is running in XAMPP
- Verify database name in `config/db.php`
- Ensure `school_finance` database exists

### Login Issues
- Clear browser cache and cookies
- Verify username and password (case-sensitive)
- Check session.php is included in files

### Styling Issues
- Verify Bootstrap CDN link is working
- Check `assets/css/style.css` is accessible
- Clear browser cache

### PDF Generation
- Use browser's print function (Ctrl+P)
- Select "Save as PDF" as printer
- Ensure JavaScript is enabled

## 📈 Performance Tips

1. **Regular Backups** - Export database regularly
2. **Clear Sessions** - Delete old session files
3. **Optimize Images** - Compress images before upload
4. **Index Database** - Already optimized in SQL file
5. **Cache Files** - Browser caching is enabled

## 🔄 Backup & Recovery

### Backup Database
1. Go to phpMyAdmin
2. Select `school_finance` database
3. Click "Export"
4. Choose "SQL" format
5. Click "Go"

### Restore Database
1. Create new database or drop existing
2. Go to phpMyAdmin
3. Click "Import"
4. Select backup SQL file
5. Click "Import"

## 📞 Support & Documentation

For issues or questions:
1. Check this README
2. Review code comments
3. Check database queries
4. Verify configuration files

## 📋 Changelog

### Version 1.0 (Initial Release)
- Complete SFMS system
- Master and Finance roles
- All 7 master features
- 2 finance features
- PDF generation
- Modern UI with responsive design
- Database with indexes
- Security implementations

## 📄 License

This is an educational and demonstration project. Feel free to modify and use as needed.

## 👨‍💻 System Requirements

- **Server:** Apache (included with XAMPP)
- **Database:** MySQL 5.7+ (included with XAMPP)
- **PHP:** 7.0 or higher (included with XAMPP)
- **Browser:** Chrome, Firefox, Safari, Edge (latest versions)
- **Screen:** Minimum 1024x768 resolution

## 🎯 Future Enhancements

- Email notifications for payments
- SMS alerts for defaulters
- Mobile app integration
- Advanced analytics & reports
- Scholarship management
- Event management
- Parent portal
- API integration

---

**Made with ❤️ for School Management | SFMS v1.0**
