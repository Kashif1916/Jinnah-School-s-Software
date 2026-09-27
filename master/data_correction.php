<?php
/**
 * Data Correction (Historical Payments Corrector)
 * School Finance Management System - Master Panel
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master(); // Only allow master (Principal)

$error = '';
$success = '';
$student_id = intval($_GET['id'] ?? 0);
$student = null;

$paid_months_in_db = [];
$fee_records_db = [];
$custom_fees_db = [];

$dynamic_months_list = [];
$months_list_labels = [];
$has_admission_record = false;
$has_prev_year_record = false;

if ($student_id > 0) {
    $student = get_student($student_id);
    if ($student) {
        $is_college_student = (is_college_class($student['class']) || !empty($student['is_package']));
        
        if ($is_college_student) {
            // UNTOUCHED COLLEGE LOGIC
            $pkg_amount = floatval($student['package_amount'] > 0 ? $student['package_amount'] : $student['fixed_monthly_fee']);
            if ($pkg_amount <= 0 && !empty($student['class'])) {
                $fs_stmt = $conn->prepare("SELECT fixed_monthly_fee FROM fee_schedule WHERE class = ?");
                $fs_stmt->bind_param('s', $student['class']);
                $fs_stmt->execute();
                $fs_res = $fs_stmt->get_result()->fetch_assoc();
                if ($fs_res) {
                    $pkg_amount = floatval($fs_res['fixed_monthly_fee']);
                }
                $fs_stmt->close();
            }
            $pkg_concession = floatval($student['concession_amount'] ?? 0);
            $net_pkg = max(0, $pkg_amount - $pkg_concession);

            $pkg_rec_res = $conn->query("SELECT id, amount, status FROM fee_records WHERE student_id = $student_id AND (month LIKE '%Package%' OR month = 'Yearly Package') ORDER BY id DESC LIMIT 1");
            $pkg_rec = ($pkg_rec_res && $pkg_rec_res->num_rows > 0) ? $pkg_rec_res->fetch_assoc() : null;
            if ($pkg_rec) {
                $pkg_pending_fee = ($pkg_rec['status'] === 'paid') ? 0 : floatval($pkg_rec['amount']);
            } else {
                $pkg_pending_fee = $net_pkg;
            }

            $pre_rec_res = $conn->query("SELECT id, amount, status FROM fee_records WHERE student_id = $student_id AND month IN ('Pre_Year', 'Prev-Year', 'Pre-Year') ORDER BY id DESC LIMIT 1");
            $pre_rec = ($pre_rec_res && $pre_rec_res->num_rows > 0) ? $pre_rec_res->fetch_assoc() : null;
            if ($pre_rec) {
                $pre_year_pending_fee = ($pre_rec['status'] === 'paid') ? 0 : floatval($pre_rec['amount']);
            } else {
                $pre_year_pending_fee = 0;
            }

            $pay_res = $conn->query("SELECT SUM(amount) as total_paid FROM payments WHERE student_id = $student_id");
            $pkg_total_paid = ($pay_res && $pay_row = $pay_res->fetch_assoc()) ? floatval($pay_row['total_paid'] ?? 0) : 0;
        } else {
            // SCHOOL SECTION (PG to 10th Class)

            // 1. Fetch exact fee_records & payments
            $res = $conn->query("SELECT id, month, status, amount FROM fee_records WHERE student_id = $student_id ORDER BY id ASC");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $m_title = $row['month'];
                    $rec_amt = floatval($row['amount']);

                    // If amount is 0 in fee_records for paid item, fetch actual amount from payments table
                    if ($row['status'] === 'paid' && $rec_amt <= 0) {
                        $p_chk = $conn->query("SELECT amount FROM payments WHERE student_id = $student_id AND paid_for_month = '$m_title' ORDER BY id DESC LIMIT 1");
                        if ($p_chk && $p_chk->num_rows > 0) {
                            $rec_amt = floatval($p_chk->fetch_assoc()['amount']);
                        }
                    }

                    $fee_records_db[$m_title] = [
                        'status' => $row['status'],
                        'amount' => $rec_amt
                    ];

                    if ($row['status'] === 'paid') {
                        $paid_months_in_db[] = $m_title;
                    }

                    if ($m_title === 'Admission') {
                        $has_admission_record = true;
                    } elseif (in_array($m_title, ['Prev-Year', 'Pre_Year', 'Pre-Year'])) {
                        $has_prev_year_record = true;
                    } else {
                        // Check for extra/custom fees
                        $is_standard_month = false;
                        for ($m = 1; $m <= 12; $m++) {
                            if (date('M-Y', mktime(0,0,0,$m,1,2025)) === $m_title || date('M-Y', mktime(0,0,0,$m,1,2026)) === $m_title || date('M-Y', mktime(0,0,0,$m,1,2027)) === $m_title) {
                                $is_standard_month = true;
                                break;
                            }
                        }
                        if (!$is_standard_month && strtotime("01-" . $m_title) === false) {
                            $custom_fees_db[$m_title] = $fee_records_db[$m_title];
                        }
                    }
                }
            }

            // 2. Dynamic Schedule Start Month Determination
            $start_timestamp = null;
            foreach ($fee_records_db as $m_key => $f_val) {
                if (!in_array($m_key, ['Admission', 'Prev-Year', 'Pre_Year', 'Pre-Year']) && !isset($custom_fees_db[$m_key])) {
                    $parsed_ts = strtotime("01-" . $m_key);
                    if ($parsed_ts) {
                        $start_timestamp = $parsed_ts;
                        break;
                    }
                }
            }

            if (!$start_timestamp) {
                $adm_date_str = !empty($student['admission_date']) ? $student['admission_date'] : (!empty($student['created_at']) ? $student['created_at'] : date('Y-m-01'));
                $start_timestamp = strtotime(date('Y-m-01', strtotime($adm_date_str)));
            }

            // Generate Complete 12 Months Schedule
            for ($i = 0; $i < 12; $i++) {
                $m_time = strtotime("+$i month", $start_timestamp);
                $m_key = date('M-Y', $m_time);
                $m_label = date('F Y', $m_time);
                
                $dynamic_months_list[] = $m_key;
                $months_list_labels[$m_key] = $m_label;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'correct' && $student) {
    $is_college_student = (is_college_class($student['class']) || !empty($student['is_package']));
    $previous_month_date = date('Y-m-t 23:59:59', strtotime('last day of previous month'));
    $received_by = get_username() ?? 'System';

    if ($is_college_student) {
        // UNTOUCHED COLLEGE POST PROCESS
        $new_package_pending = floatval($_POST['package_pending_fee'] ?? 0);
        $new_pre_year_pending = floatval($_POST['pre_year_pending_fee'] ?? 0);

        $conn->begin_transaction();
        try {
            $pkg_amount = floatval($student['package_amount'] > 0 ? $student['package_amount'] : $student['fixed_monthly_fee']);
            $pkg_concession = floatval($student['concession_amount'] ?? 0);
            $net_pkg = max(0, $pkg_amount - $pkg_concession);
            $calculated_paid_amount = max(0, $net_pkg - $new_package_pending);

            $pkg_month_name = ($student['class'] == '12' || $student['class'] == '12th') ? 'Package-12' : 'Yearly Package';
            $chk = $conn->query("SELECT id FROM fee_records WHERE student_id = $student_id AND (month LIKE '%Package%' OR month = 'Yearly Package')");
            if ($chk && $chk->num_rows > 0) {
                $rec_id = $chk->fetch_assoc()['id'];
                if ($new_package_pending <= 0) {
                    $conn->query("UPDATE fee_records SET amount = $net_pkg, status = 'paid', payment_date = '$previous_month_date' WHERE id = $rec_id");
                } else {
                    $conn->query("UPDATE fee_records SET amount = $new_package_pending, status = 'unpaid' WHERE id = $rec_id");
                }
            } else {
                $status = ($new_package_pending <= 0) ? 'paid' : 'unpaid';
                $stmt = $conn->prepare("INSERT INTO fee_records (student_id, month, amount, status, payment_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('isdss', $student_id, $pkg_month_name, $net_pkg, $status, $previous_month_date);
                $stmt->execute();
                $stmt->close();
            }

            $conn->query("DELETE FROM payments WHERE student_id = $student_id AND (paid_for_month LIKE '%Package%' OR paid_for_month = 'Yearly Package')");
            if ($calculated_paid_amount > 0) {
                $stmt_pay = $conn->prepare("INSERT INTO payments (student_id, amount, paid_for_month, payment_date, received_by, payment_mode) VALUES (?, ?, ?, ?, ?, 'cash')");
                $stmt_pay->bind_param('idsss', $student_id, $calculated_paid_amount, $pkg_month_name, $previous_month_date, $received_by);
                $stmt_pay->execute();
                $stmt_pay->close();
            }

            $total_pending_all = $new_package_pending + $new_pre_year_pending;
            $conn->query("UPDATE students SET admission_fee = $total_pending_all WHERE id = $student_id");

            $conn->commit();
            $success = "College student pending package corrected successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error correcting college fee: " . $e->getMessage();
        }
    } else {
        // SCHOOL POST PROCESS (PG to 10th Class)
        $pending_amount = floatval($_POST['pending_amount'] ?? 0);
        $new_admission_fee = floatval($_POST['admission_fee'] ?? 0);
        $admission_paid = isset($_POST['admission_paid']) ? 1 : 0;
        $checked_months = $_POST['paid_months'] ?? [];
        $checked_custom_fees = $_POST['custom_paid_fees'] ?? [];
        
        $fixed_monthly_fee = floatval($student['fixed_monthly_fee']);
        $concession_amount = floatval($student['concession_amount']);
        $net_fee = max(0, $fixed_monthly_fee - $concession_amount);
        
        $conn->begin_transaction();
        try {
            // 1. Admission Fee Correction
            if ($has_admission_record || $new_admission_fee > 0) {
                $adm_status = $admission_paid ? 'paid' : 'unpaid';
                $chk_adm = $conn->query("SELECT id FROM fee_records WHERE student_id = $student_id AND month = 'Admission'");
                
                if ($chk_adm && $chk_adm->num_rows > 0) {
                    $conn->query("UPDATE fee_records SET amount = $new_admission_fee, status = '$adm_status' WHERE student_id = $student_id AND month = 'Admission'");
                } else {
                    $conn->query("INSERT INTO fee_records (student_id, month, amount, status) VALUES ($student_id, 'Admission', $new_admission_fee, '$adm_status')");
                }

                if ($admission_paid && $new_admission_fee > 0) {
                    $chk_adm_pay = $conn->query("SELECT id FROM payments WHERE student_id = $student_id AND paid_for_month = 'Admission'");
                    if (!$chk_adm_pay || $chk_adm_pay->num_rows == 0) {
                        $stmt_ap = $conn->prepare("INSERT INTO payments (student_id, amount, paid_for_month, payment_date, created_at, received_by, payment_mode) VALUES (?, ?, 'Admission', ?, ?, ?, 'cash')");
                        $stmt_ap->bind_param('idsss', $student_id, $new_admission_fee, $previous_month_date, $previous_month_date, $received_by);
                        $stmt_ap->execute();
                        $stmt_ap->close();
                    }
                } else {
                    $conn->query("DELETE FROM payments WHERE student_id = $student_id AND paid_for_month = 'Admission'");
                }
            }

            // 2. Previous Dues Sync
            if ($pending_amount > 0 || $has_prev_year_record) {
                $stmt_update_student = $conn->prepare("UPDATE students SET admission_fee = ? WHERE id = ?");
                $stmt_update_student->bind_param('di', $pending_amount, $student_id);
                $stmt_update_student->execute();
                $stmt_update_student->close();

                if ($pending_amount > 0) {
                    $chk_prev = $conn->query("SELECT id FROM fee_records WHERE student_id = $student_id AND month IN ('Prev-Year', 'Pre_Year', 'Pre-Year')");
                    if ($chk_prev && $chk_prev->num_rows > 0) {
                        $conn->query("UPDATE fee_records SET amount = $pending_amount, status = 'unpaid' WHERE student_id = $student_id AND month IN ('Prev-Year', 'Pre_Year', 'Pre-Year')");
                    } else {
                        $conn->query("INSERT INTO fee_records (student_id, month, amount, status) VALUES ($student_id, 'Prev-Year', $pending_amount, 'unpaid')");
                    }
                } else {
                    $conn->query("DELETE FROM fee_records WHERE student_id = $student_id AND month IN ('Prev-Year', 'Pre_Year', 'Pre-Year')");
                    $conn->query("DELETE FROM payments WHERE student_id = $student_id AND paid_for_month IN ('Prev-Year', 'Pre_Year', 'Pre-Year')");
                }
            }

            // 3. Monthly Fee Schedule Sync
            foreach ($dynamic_months_list as $month) {
                $is_checked = in_array($month, $checked_months);
                $chk_rec = $conn->query("SELECT id FROM fee_records WHERE student_id = $student_id AND month = '$month'");
                $exists = ($chk_rec && $chk_rec->num_rows > 0);
                
                if ($is_checked) {
                    if ($exists) {
                        $conn->query("UPDATE fee_records SET status = 'paid', amount = $net_fee, payment_date = COALESCE(payment_date, '$previous_month_date') WHERE student_id = $student_id AND month = '$month'");
                    } else {
                        $conn->query("INSERT INTO fee_records (student_id, month, amount, status, payment_date) VALUES ($student_id, '$month', $net_fee, 'paid', '$previous_month_date')");
                    }
                    
                    $chk_pay = $conn->query("SELECT id FROM payments WHERE student_id = $student_id AND paid_for_month = '$month'");
                    if (!$chk_pay || $chk_pay->num_rows == 0) {
                        if ($net_fee > 0) {
                            $stmt_p = $conn->prepare("INSERT INTO payments (student_id, amount, paid_for_month, payment_date, created_at, received_by, payment_mode) VALUES (?, ?, ?, ?, ?, ?, 'cash')");
                            $stmt_p->bind_param('idssss', $student_id, $net_fee, $month, $previous_month_date, $previous_month_date, $received_by);
                            $stmt_p->execute();
                            $stmt_p->close();
                        }
                    }
                } else {
                    if ($exists) {
                        $conn->query("UPDATE fee_records SET status = 'unpaid', amount = $net_fee, payment_date = NULL WHERE student_id = $student_id AND month = '$month'");
                    } else {
                        $conn->query("INSERT INTO fee_records (student_id, month, amount, status) VALUES ($student_id, '$month', $net_fee, 'unpaid')");
                    }
                    $conn->query("DELETE FROM payments WHERE student_id = $student_id AND paid_for_month = '$month'");
                }
            }

            // 4. Extra / Custom Fees Sync
            foreach ($custom_fees_db as $c_title => $c_data) {
                $is_c_checked = in_array($c_title, $checked_custom_fees);
                $c_amt = $c_data['amount'];
                
                if ($is_c_checked) {
                    $conn->query("UPDATE fee_records SET status = 'paid', payment_date = COALESCE(payment_date, '$previous_month_date') WHERE student_id = $student_id AND month = '$c_title'");
                    $chk_cp = $conn->query("SELECT id FROM payments WHERE student_id = $student_id AND paid_for_month = '$c_title'");
                    if (!$chk_cp || $chk_cp->num_rows == 0) {
                        $stmt_cp = $conn->prepare("INSERT INTO payments (student_id, amount, paid_for_month, payment_date, created_at, received_by, payment_mode) VALUES (?, ?, ?, ?, ?, ?, 'cash')");
                        $stmt_cp->bind_param('idssss', $student_id, $c_amt, $c_title, $previous_month_date, $previous_month_date, $received_by);
                        $stmt_cp->execute();
                        $stmt_cp->close();
                    }
                } else {
                    $conn->query("UPDATE fee_records SET status = 'unpaid', payment_date = NULL WHERE student_id = $student_id AND month = '$c_title'");
                    $conn->query("DELETE FROM payments WHERE student_id = $student_id AND paid_for_month = '$c_title'");
                }
            }
            
            $conn->commit();
            $success = "Student payment records corrected successfully!";
            
            // Refresh Data
            $student = get_student($student_id);
            $paid_months_in_db = [];
            $fee_records_db = [];
            $custom_fees_db = [];

            $res = $conn->query("SELECT month, status, amount FROM fee_records WHERE student_id = $student_id ORDER BY id ASC");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $m_title = $row['month'];
                    $rec_amt = floatval($row['amount']);
                    if ($row['status'] === 'paid' && $rec_amt <= 0) {
                        $p_chk = $conn->query("SELECT amount FROM payments WHERE student_id = $student_id AND paid_for_month = '$m_title' ORDER BY id DESC LIMIT 1");
                        if ($p_chk && $p_chk->num_rows > 0) {
                            $rec_amt = floatval($p_chk->fetch_assoc()['amount']);
                        }
                    }
                    $fee_records_db[$m_title] = [
                        'status' => $row['status'],
                        'amount' => $rec_amt
                    ];
                    if ($row['status'] === 'paid') {
                        $paid_months_in_db[] = $m_title;
                    }
                }
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error correcting logs: " . $e->getMessage();
        }
    }
}

// Student Search Filtering
$search_name = sanitize_input($_GET['search_name'] ?? '');
$search_class = sanitize_input($_GET['search_class'] ?? '');
$search_section = sanitize_input($_GET['search_section'] ?? '');
$students_list = [];

if ($student_id == 0) {
    $q = "SELECT * FROM students WHERE 1=1";
    $params = [];
    $param_types = '';
    
    if (!empty($search_name)) {
        $q .= " AND name LIKE ?";
        $params[] = '%' . $search_name . '%';
        $param_types .= 's';
    }
    if (!empty($search_class)) {
        $q .= " AND class = ?";
        $params[] = $search_class;
        $param_types .= 's';
    }
    if (!empty($search_section)) {
        $q .= " AND section = ?";
        $params[] = $search_section;
        $param_types .= 's';
    }
    
    $q .= " ORDER BY id DESC";
    if (empty($search_name) && empty($search_class) && empty($search_section)) {
        $q .= " LIMIT 20";
    }
    
    $stmt = $conn->prepare($q);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $students_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Entry Correction - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .student-meta {
            background: #f8fcf9;
            border-left: 4px solid #1f5f46;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <a href="dashboard.php"><?php echo render_system_logo('topbar-logo'); ?></a>
                    <div class="panel-brand">
                        <h2>Data Correction</h2>
                        <span>Principal Panel</span>
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
            
            <div class="content">
                <div class="module-nav-panel">
                    <div class="module-nav-row">
                        <a href="dashboard.php" class="module-nav-btn"><i class="fas fa-chart-bar"></i> Dashboard</a>
                        <a href="add_student.php" class="module-nav-btn"><i class="fas fa-user-plus"></i> Add Student</a>
                        <a href="student_record.php" class="module-nav-btn"><i class="fas fa-address-book"></i> Student Record</a>
                        <a href="student_add_details.php" class="module-nav-btn"><i class="fas fa-history"></i> Add Log</a>
                        <a href="fee_schedule.php" class="module-nav-btn"><i class="fas fa-calendar-alt"></i> Fee Schedule</a>
                        <a href="fee_management.php" class="module-nav-btn"><i class="fas fa-money-bill-wave"></i> Fee Management</a>
                        <a href="defaulter_list.php" class="module-nav-btn"><i class="fas fa-list"></i> Pending List</a>
                        <a href="paid_students.php" class="module-nav-btn"><i class="fas fa-check-circle text-success"></i> Paid Students</a>
                        <a href="payment_analytics.php" class="module-nav-btn"><i class="fas fa-chart-line"></i> Analytics</a>
                        <a href="receipt_analysis.php" class="module-nav-btn"><i class="fas fa-receipt"></i> Receipt Analysis</a>
                        <a href="expenses.php" class="module-nav-btn"><i class="fas fa-wallet"></i> Expenses</a>
                        <a href="data_correction.php" class="module-nav-btn active"><i class="fas fa-edit"></i> Data Correction</a>
                        <a href="promotion.php" class="module-nav-btn"><i class="fas fa-arrow-up"></i> Promotion</a>
                        <a href="drop_student.php" class="module-nav-btn"><i class="fas fa-trash"></i> Drop Student</a>
                        <a href="delete_student.php" class="module-nav-btn"><i class="fas fa-user-minus text-success"></i> Delete Student</a>
                        <a href="users.php" class="module-nav-btn"><i class="fas fa-users-cog"></i> Users</a>
                        <a href="account_close_log.php" class="module-nav-btn"><i class="fas fa-lock"></i> Close Logs</a>
                        <a href="receipt_note.php" class="module-nav-btn"><i class="fas fa-sticky-note"></i> Custom Note</a>
                        <a href="../help.php" class="module-nav-btn"><i class="fas fa-question-circle text-success"></i> Help & About</a>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Step 1: Select Student -->
                <?php if (!$student): ?>
                    <div class="card p-4 mb-4">
                        <h4 class="mb-3 text-success"><i class="fas fa-filter"></i> Filter Students for Payment Correction</h4>
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Student Name</label>
                                <input type="text" name="search_name" class="form-control" value="<?php echo htmlspecialchars($search_name); ?>" placeholder="Search by name...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Class</label>
                                <select name="search_class" class="form-select">
                                    <option value="">All Classes</option>
                                    <?php foreach ($CLASSES as $cls): ?>
                                        <option value="<?php echo $cls; ?>" <?php echo $search_class == $cls ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Section</label>
                                <select name="search_section" class="form-select">
                                    <option value="">All Sections</option>
                                    <?php foreach ($SECTIONS as $sec): ?>
                                        <option value="<?php echo $sec; ?>" <?php echo $search_section == $sec ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
                            </div>
                        </form>
                    </div>

                    <div class="card p-4">
                        <h4 class="mb-3 text-secondary">Filtered Student Results</h4>
                        <?php if (empty($students_list)): ?>
                            <p class="text-muted">No students found matching your criteria.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Student Name</th>
                                            <th>Father Name</th>
                                            <th>Class - Section</th>
                                            <th>Net Monthly Fee / Package</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students_list as $st): ?>
                                            <?php 
                                            $is_st_pkg = (!empty($st['is_package']) || floatval($st['package_amount'] ?? 0) > 0 || is_college_class($st['class']));
                                            ?>
                                            <tr>
                                                <td><strong><?php echo str_pad($st['id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                                                <td><?php echo htmlspecialchars($st['name']); ?></td>
                                                <td><?php echo htmlspecialchars($st['father_name']); ?></td>
                                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($st['class'] . ' - ' . $st['section']); ?></span></td>
                                                <td>
                                                    <?php 
                                                    if ($is_st_pkg) {
                                                        $raw_pkg = floatval($st['package_amount'] > 0 ? $st['package_amount'] : $st['fixed_monthly_fee']);
                                                        $net_pkg = max(0, $raw_pkg - floatval($st['concession_amount'] ?? 0));
                                                        echo '<strong class="text-primary">' . format_currency($net_pkg) . '</strong> <span class="badge bg-primary text-white ms-1">Yearly Pkg</span>';
                                                    } else {
                                                        $net_fee = floatval($st['monthly_fee'] > 0 ? $st['monthly_fee'] : (floatval($st['fixed_monthly_fee']) - floatval($st['concession_amount'] ?? 0)));
                                                        echo '<strong class="text-success">' . format_currency($net_fee) . '</strong>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <a href="data_correction.php?id=<?php echo $st['id']; ?>" class="btn btn-sm btn-success text-white px-3">
                                                        <i class="fas fa-check-double"></i> Select Student
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <!-- Step 2: Display Form -->
                <?php else: ?>
                    <div class="card p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="text-success m-0"><i class="fas fa-user-edit"></i> Correct Historical Payments</h4>
                            <a href="data_correction.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Select Different Student</a>
                        </div>

                        <?php
                        $is_college_student = (is_college_class($student['class']) || !empty($student['is_package']));
                        $net_monthly_fee_display = floatval($student['monthly_fee']);
                        if ($net_monthly_fee_display <= 0) {
                            $net_monthly_fee_display = max(0, floatval($student['fixed_monthly_fee']) - floatval($student['concession_amount']));
                        }
                        ?>
                        <div class="student-meta row">
                            <div class="col-md-3">
                                <strong>Student Name:</strong>
                                <p class="m-0 text-dark fs-5"><?php echo htmlspecialchars($student['name']); ?></p>
                            </div>
                            <div class="col-md-3">
                                <strong>Father's Name:</strong>
                                <p class="m-0 text-dark fs-5"><?php echo htmlspecialchars($student['father_name']); ?></p>
                            </div>
                            <div class="col-md-3">
                                <strong>Class & Section:</strong>
                                <p class="m-0 text-dark fs-5"><?php echo htmlspecialchars($student['class'] . ' (' . $student['section'] . ')'); ?></p>
                            </div>
                            <div class="col-md-3">
                                <?php if ($is_college_student): ?>
                                    <strong>Net Package Fee:</strong>
                                    <p class="m-0 text-primary fs-5 fw-bold"><?php echo format_currency($net_pkg); ?> <span class="badge bg-primary text-white ms-1 fs-6">Yearly Pkg</span></p>
                                <?php else: ?>
                                    <strong>Net Monthly Fee:</strong>
                                    <p class="m-0 text-success fs-5 fw-bold"><?php echo format_currency($net_monthly_fee_display); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="action" value="correct">
                            
                            <?php if ($is_college_student): ?>
                                <!-- UNTOUCHED COLLEGE UI -->
                                <div class="row mb-4 mt-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold text-success fs-6" for="package_pending_fee">
                                            <i class="fas fa-money-bill-wave me-1"></i> Current Package Pending Fee (Rs.)
                                        </label>
                                        <input type="number" id="package_pending_fee" name="package_pending_fee" class="form-control form-control-lg" value="<?php echo htmlspecialchars($pkg_pending_fee); ?>" step="0.01" min="0" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold text-danger fs-6" for="pre_year_pending_fee">
                                            <i class="fas fa-exclamation-triangle me-1"></i> Pre-Year (11th) Pending Fee (Rs.)
                                        </label>
                                        <input type="number" id="pre_year_pending_fee" name="pre_year_pending_fee" class="form-control form-control-lg border-danger" value="<?php echo htmlspecialchars($pre_year_pending_fee); ?>" step="0.01" min="0">
                                    </div>
                                    <div class="col-md-4 d-flex align-items-center">
                                        <div class="p-3 bg-light rounded w-100 border">
                                            <p class="mb-1"><strong>Total Package Fee:</strong> <?php echo format_currency($pkg_amount); ?></p>
                                            <p class="mb-0"><strong>Concession Amount:</strong> <?php echo format_currency($pkg_concession); ?> (Net: <?php echo format_currency($net_pkg); ?>)</p>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- SCHOOL CLASSES (PG to 10th Class) UI -->
                                <div class="row mb-4 mt-3">
                                    <?php 
                                    $admission_val_show = isset($fee_records_db['Admission']) ? $fee_records_db['Admission']['amount'] : 0;
                                    $is_admission_paid = isset($fee_records_db['Admission']) && $fee_records_db['Admission']['status'] === 'paid';
                                    
                                    $prev_year_val_show = 0;
                                    foreach (['Prev-Year', 'Pre_Year', 'Pre-Year'] as $pyk) {
                                        if (isset($fee_records_db[$pyk])) {
                                            $prev_year_val_show = $fee_records_db[$pyk]['amount'];
                                            break;
                                        }
                                    }
                                    ?>

                                    <?php if ($has_admission_record): ?>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-success" for="admission_fee">
                                            <i class="fas fa-id-card me-1"></i> Admission Fee / Dues (Rs.)
                                        </label>
                                        <div class="input-group">
                                            <input type="number" id="admission_fee" name="admission_fee" class="form-control" value="<?php echo htmlspecialchars($admission_val_show); ?>" step="0.01" min="0">
                                            <div class="input-group-text bg-white">
                                                <input class="form-check-input me-1" type="checkbox" name="admission_paid" value="1" id="adm_paid" <?php echo $is_admission_paid ? 'checked' : ''; ?>>
                                                <label class="form-check-label small text-dark fw-bold" for="adm_paid">Paid</label>
                                            </div>
                                        </div>
                                        <div class="form-text text-muted">Admission Fee amount remains fixed on paid status.</div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($has_prev_year_record || $prev_year_val_show > 0): ?>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-success" for="pending_amount">
                                            <i class="fas fa-money-bill-wave me-1"></i> Previous Pending Fee (if any)
                                        </label>
                                        <input type="number" id="pending_amount" name="pending_amount" class="form-control" value="<?php echo htmlspecialchars($prev_year_val_show); ?>" step="0.01" min="0">
                                        <div class="form-text text-muted">Set to 0 if student has no previous dues.</div>
                                    </div>
                                    <?php else: ?>
                                        <input type="hidden" name="pending_amount" value="0">
                                    <?php endif; ?>
                                </div>

                                <!-- Custom / Extra Fees Block (If Scheduled) -->
                                <?php if (!empty($custom_fees_db)): ?>
                                    <div class="card p-3 mb-4 border-warning bg-light">
                                        <h6 class="text-warning fw-bold mb-2"><i class="fas fa-star me-2"></i> Custom / Extra Scheduled Dues</h6>
                                        <div class="row g-3">
                                            <?php foreach ($custom_fees_db as $c_title => $c_data): ?>
                                                <div class="col-6 col-md-3">
                                                    <div class="form-check p-2 border rounded bg-white">
                                                        <input class="form-check-input ms-1 me-2" type="checkbox" name="custom_paid_fees[]" value="<?php echo $c_title; ?>" id="c_check_<?php echo $c_title; ?>" <?php echo $c_data['status'] === 'paid' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label text-dark small" for="c_check_<?php echo $c_title; ?>">
                                                            <strong><?php echo $c_title; ?></strong> (Rs. <?php echo format_currency($c_data['amount']); ?>)
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Fee Paid Months Schedule (12 Months Grid) -->
                                <div class="card p-4 mb-4 border-0 shadow-sm" style="background: rgba(31, 95, 70, 0.05); border-radius: 12px;">
                                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                        <h5 class="text-success mb-0" style="font-weight: 600;">
                                            <i class="fas fa-calendar-check me-2"></i> Monthly Fee Schedule Checkbox List (12 Months)
                                        </h5>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-success" id="selectAll">Select All</button>
                                            <button type="button" class="btn btn-outline-secondary" id="deselectAll">Deselect All</button>
                                        </div>
                                    </div>
                                    <p class="text-muted small mb-3">Check items to mark as Paid in both <code>fee_records</code> and <code>payments</code> tables.</p>
                                    <div class="row g-3">
                                        <?php foreach ($dynamic_months_list as $m): ?>
                                            <?php 
                                            $is_paid_db = in_array($m, $paid_months_in_db);
                                            $label = $months_list_labels[$m] ?? $m;
                                            ?>
                                            <div class="col-6 col-md-3">
                                                <div class="form-check p-2 border rounded bg-white shadow-xs" style="transition: all 0.2s;">
                                                    <input class="form-check-input ms-1 me-2 month-check" type="checkbox" name="paid_months[]" value="<?php echo $m; ?>" id="check_<?php echo $m; ?>" <?php echo $is_paid_db ? 'checked' : ''; ?>>
                                                    <label class="form-check-label text-dark small cursor-pointer" for="check_<?php echo $m; ?>">
                                                        <?php echo $label; ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="form-actions mt-5 text-end">
                                <button type="submit" class="btn btn-success btn-lg px-5 text-white"><i class="fas fa-save"></i> Save & Apply Corrections</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('selectAll')?.addEventListener('click', function() {
            document.querySelectorAll('.month-check').forEach(cb => cb.checked = true);
        });
        
        document.getElementById('deselectAll')?.addEventListener('click', function() {
            document.querySelectorAll('.month-check').forEach(cb => cb.checked = false);
        });
    </script>
</body>
</html>