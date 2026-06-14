# Jinnah School's Finance Management System (SFMS)

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

