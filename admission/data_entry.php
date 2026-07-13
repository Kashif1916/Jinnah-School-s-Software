<?php
/**
 * Data Entry (Temporary Student Loader with Historical Payments)
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// Enforce login check
require_login();

// Allow both Master and Admission roles
if (!is_master() && !is_admission()) {
    header('Location: ../login.php');
    exit();
}

$error = '';
$success = '';

// Fetch class fee schedule
$class_fees = [];
$fee_res = $conn->query("SELECT class, fixed_monthly_fee FROM fee_schedule");
if ($fee_res) {
    while ($row = $fee_res->fetch_assoc()) {
        $class_fees[$row['class']] = floatval($row['fixed_monthly_fee']);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize_input($_POST['name'] ?? '');
    $father_name = sanitize_input($_POST['father_name'] ?? '');
    $class = sanitize_input($_POST['class'] ?? '');
    $section = sanitize_input($_POST['section'] ?? '');
    $fixed_monthly_fee = floatval($_POST['fixed_monthly_fee'] ?? 0);
    $contact_number = sanitize_input($_POST['contact_number'] ?? '');
    $contact_number2 = sanitize_input($_POST['contact_number2'] ?? '');
    $whatsapp_number = sanitize_input($_POST['whatsapp_number'] ?? '');
    $concession_amount = floatval($_POST['concession_amount'] ?? 0);
    $concession_reason = sanitize_input($_POST['concession_reason'] ?? '');
    $pending_amount = floatval($_POST['pending_amount'] ?? 0);
    
    $paid_months = $_POST['paid_months'] ?? []; // Checked months array
    
    // Validation
    if (empty($name) || empty($father_name) || empty($class) || empty($section) || $fixed_monthly_fee <= 0) {
        $error = 'All required fields must be filled correctly!';
    } else {
        // Check if student already exists in the same class and section
        $check_query = "SELECT id FROM students WHERE name = ? AND father_name = ? AND class = ? AND section = ? AND status = 'active'";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('ssss', $name, $father_name, $class, $section);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $error = 'Student is not entered because this student is already exist in the same class with the same name and same class section!';
            $check_stmt->close();
        } else {
            $check_stmt->close();
            $conn->begin_transaction();
            try {
                $monthly_fee = $fixed_monthly_fee - $concession_amount;
                if ($monthly_fee < 0) $monthly_fee = 0;
                
                // Insert student with monthly_fee calculated and pending_amount stored in admission_fee
                $created_by = get_username();
                $query = "INSERT INTO students (name, father_name, class, section, fixed_monthly_fee, monthly_fee, admission_fee, contact_number, contact_number2, whatsapp_number, concession_amount, concession_reason, status, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ssssdddsssdss', $name, $father_name, $class, $section, $fixed_monthly_fee, $monthly_fee, $pending_amount, $contact_number, $contact_number2, $whatsapp_number, $concession_amount, $concession_reason, $created_by);
            
            if ($stmt->execute()) {
                $student_id = $conn->insert_id;
                $stmt->close();
                
                // Historical Months of 2026
                $months_2026 = [
                    'Jan-2026', 'Feb-2026', 'Mar-2026', 'Apr-2026', 'May-2026', 'Jun-2026',
                    'Jul-2026', 'Aug-2026', 'Sep-2026', 'Oct-2026', 'Nov-2026', 'Dec-2026'
                ];
                
                $payment_date = date('Y-m-d H:i:s');
                $received_by = get_username() ?? 'System';
                
                // Determine partial month
                $partial_month = null;
                $fully_paid_months = $paid_months;
                
                if ($pending_amount > 0) {
                    if (!empty($paid_months)) {
                        $partial_month = $paid_months[count($paid_months) - 1];
                        $fully_paid_months = array_diff($paid_months, [$partial_month]);
                    } else {
                        $partial_month = 'Jan-2026';
                        $fully_paid_months = [];
                    }
                }
                
                foreach ($months_2026 as $month) {
                    if (in_array($month, $fully_paid_months)) {
                        // Mark as Paid in fee_records (amount = 0 remaining)
                        $query_fee = "INSERT INTO fee_records (student_id, month, amount, status, payment_date) VALUES (?, ?, 0, 'paid', ?)";
                        $stmt_fee = $conn->prepare($query_fee);
                        $stmt_fee->bind_param('iss', $student_id, $month, $payment_date);
                        $stmt_fee->execute();
                        $stmt_fee->close();
                        
                        // Insert into payments table
                        $query_pay = "INSERT INTO payments (student_id, amount, paid_for_month, payment_date, received_by, payment_mode) VALUES (?, ?, ?, ?, ?, 'cash')";
                        $stmt_pay = $conn->prepare($query_pay);
                        $stmt_pay->bind_param('idsss', $student_id, $monthly_fee, $month, $payment_date, $received_by);
                        $stmt_pay->execute();
                        $stmt_pay->close();
                    } elseif ($month === $partial_month) {
                        // Mark as Partially Paid: status is 'unpaid', amount remaining is $pending_amount
                        $query_fee = "INSERT INTO fee_records (student_id, month, amount, status, payment_date) VALUES (?, ?, ?, 'unpaid', ?)";
                        $stmt_fee = $conn->prepare($query_fee);
                        $stmt_fee->bind_param('isds', $student_id, $month, $pending_amount, $payment_date);
                        $stmt_fee->execute();
                        $stmt_fee->close();
                        
                        $paid_amount = $monthly_fee - $pending_amount;
                        if ($paid_amount > 0) {
                            $query_pay = "INSERT INTO payments (student_id, amount, paid_for_month, payment_date, received_by, payment_mode) VALUES (?, ?, ?, ?, ?, 'cash')";
                            $stmt_pay = $conn->prepare($query_pay);
                            $stmt_pay->bind_param('idsss', $student_id, $paid_amount, $month, $payment_date, $received_by);
                            $stmt_pay->execute();
                            $stmt_pay->close();
                        }
                    } else {
                        // Mark as Unpaid in fee_records
                        $query_fee = "INSERT INTO fee_records (student_id, month, amount, status) VALUES (?, ?, ?, 'unpaid')";
                        $stmt_fee = $conn->prepare($query_fee);
                        $stmt_fee->bind_param('isd', $student_id, $month, $monthly_fee);
                        $stmt_fee->execute();
                        $stmt_fee->close();
                    }
                }
                
                $conn->commit();
                $success = 'Student added successfully via Data Entry! Fees generated from Jan-2026.';
            } else {
                throw new Exception($stmt->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Error adding student: ' . $e->getMessage();
        }
    }
}
}

$months_list = [
    'Jan-2026' => 'January 2026',
    'Feb-2026' => 'February 2026',
    'Mar-2026' => 'March 2026',
    'Apr-2026' => 'April 2026',
    'May-2026' => 'May 2026',
    'Jun-2026' => 'June 2026',
    'Jul-2026' => 'July 2026',
    'Aug-2026' => 'August 2026',
    'Sep-2026' => 'September 2026',
    'Oct-2026' => 'October 2026',
    'Nov-2026' => 'November 2026',
    'Dec-2026' => 'December 2026'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Entry - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <!-- Top Bar -->
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <?php echo render_system_logo('topbar-logo'); ?>
                    <div class="panel-brand">
                        <h2>Data Entry</h2>
                        <span>Admission Panel (Temporary Mode)</span>
                    </div>
                </div>
                <div class="topbar-right">
                    <span class="user-info">
                        <i class="fas fa-user-circle"></i> <?php echo get_username(); ?>
                    </span>
                    <a href="../logout.php" class="btn-secondary">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <!-- Dashboard Content -->
            <div class="content">
                <div class="module-nav-panel">
                    <div class="module-nav-row">
                        <a href="add_student.php" class="module-nav-btn">
                            <i class="fas fa-user-plus"></i> Add Student
                        </a>
                        <a href="data_entry.php" class="module-nav-btn active">
                            <i class="fas fa-keyboard"></i> Data Entry
                        </a>
                        <a href="student_record.php" class="module-nav-btn">
                            <i class="fas fa-address-book"></i> Student Record
                        </a>
                        <a href="promotion.php" class="module-nav-btn">
                            <i class="fas fa-arrow-up"></i> Promotion
                        </a>
                        <a href="drop_student.php" class="module-nav-btn">
                            <i class="fas fa-trash"></i> Drop Student
                        </a>
                    </div>
                </div>

                <div class="form-section">
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="student-form" id="dataEntryForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="name">Student Name *</label>
                                <input type="text" id="name" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="father_name">Father's Name *</label>
                                <input type="text" id="father_name" name="father_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label" for="class">Class *</label>
                                <select id="class" name="class" class="form-select" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($CLASSES as $cls): ?>
                                        <option value="<?php echo $cls; ?>"><?php echo $cls; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="section">Section *</label>
                                <select id="section" name="section" class="form-select" required>
                                    <option value="">Select Section</option>
                                    <?php foreach ($SECTIONS as $sec): ?>
                                        <option value="<?php echo $sec; ?>"><?php echo $sec; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="fixed_monthly_fee">Fixed Monthly Fee *</label>
                                <input type="number" id="fixed_monthly_fee" name="fixed_monthly_fee" class="form-control" required step="0.01" min="0" readonly>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label" for="contact_number">Contact Number 1</label>
                                <input type="tel" id="contact_number" name="contact_number" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="contact_number2">Contact Number 2</label>
                                <input type="tel" id="contact_number2" name="contact_number2" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="whatsapp_number">WhatsApp Number</label>
                                <input type="tel" id="whatsapp_number" name="whatsapp_number" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label" for="concession_amount">Concession Amount</label>
                                <input type="number" id="concession_amount" name="concession_amount" class="form-control" value="0" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="concession_reason">Concession Reason</label>
                                <select id="concession_reason" name="concession_reason" class="form-select">
                                    <option value="">None</option>
                                    <option value="Sibling">Sibling</option>
                                    <option value="Hafiz">Hafiz</option>
                                    <option value="Orfan">Orfan</option>
                                    <option value="S.C">S.C</option>
                                    <option value="EMP">EMP</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="pending_amount">Previous Pending Fee (if any)</label>
                                <input type="number" id="pending_amount" name="pending_amount" class="form-control" value="0" step="0.01" min="0">
                            </div>
                        </div>

                        <!-- 2026 Fee Paid Months Block -->
                        <div class="card p-4 mb-4 border-0 shadow-sm" style="background: rgba(31, 95, 70, 0.05); border-radius: 12px;">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <h5 class="text-success mb-0" style="font-weight: 600;">
                                    <i class="fas fa-calendar-check me-2"></i> Fee Paid for Month (Jan 2026 - Dec 2026)
                                </h5>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-success" id="selectAll">Select All</button>
                                    <button type="button" class="btn btn-outline-secondary" id="deselectAll">Deselect All</button>
                                </div>
                            </div>
                            <p class="text-muted small mb-3">Check the months for which the student has already paid. Unchecked months will remain pending (unpaid).</p>
                            <div class="row g-3">
                                <?php foreach ($months_list as $value => $label): ?>
                                    <div class="col-6 col-md-3">
                                        <div class="form-check p-2 border rounded bg-white shadow-xs" style="transition: all 0.2s;">
                                            <input class="form-check-input ms-1 me-2 month-check" type="checkbox" name="paid_months[]" value="<?php echo $value; ?>" id="check_<?php echo $value; ?>">
                                            <label class="form-check-label text-dark small cursor-pointer" for="check_<?php echo $value; ?>">
                                                <?php echo $label; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Save Student Data
                            </button>
                            <a href="student_record.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        const classFees = <?php echo json_encode($class_fees); ?>;
        document.getElementById('class').addEventListener('change', function() {
            const selectedClass = this.value;
            const feeInput = document.getElementById('fixed_monthly_fee');
            if (classFees[selectedClass] !== undefined) {
                feeInput.value = classFees[selectedClass];
            } else {
                feeInput.value = '';
            }
        });

        // Select All / Deselect All
        document.getElementById('selectAll').addEventListener('click', function() {
            document.querySelectorAll('.month-check').forEach(cb => cb.checked = true);
        });
        document.getElementById('deselectAll').addEventListener('click', function() {
            document.querySelectorAll('.month-check').forEach(cb => cb.checked = false);
        });

        document.getElementById('dataEntryForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const fatherName = document.getElementById('father_name').value.trim();
            const fixedMonthlyFee = parseFloat(document.getElementById('fixed_monthly_fee').value);
            const concession = parseFloat(document.getElementById('concession_amount').value || 0);
            
            if (!name || !fatherName || fixedMonthlyFee <= 0) {
                e.preventDefault();
                alert('Please fill all required fields correctly!');
                return;
            }

            if (concession < 0 || concession > fixedMonthlyFee) {
                e.preventDefault();
                alert('Concession amount must be between 0 and the monthly fee.');
            }
        });

        <?php if (!empty($error) && strpos($error, 'already exist') !== false): ?>
        alert(<?php echo json_encode($error); ?>);
        <?php endif; ?>
    </script>
</body>
</html>
