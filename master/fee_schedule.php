<?php
/**
 * Fee Schedule Management - Master Panel
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master();

$success = '';
$error = '';

$mode = isset($_GET['mode']) ? sanitize_input($_GET['mode']) : (isset($_POST['mode']) ? sanitize_input($_POST['mode']) : 'monthly');
if (!in_array($mode, ['monthly', 'other'])) {
    $mode = 'monthly';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Save Class Monthly / Package Fee Schedule
    if (isset($_POST['action']) && $_POST['action'] === 'save_fee') {
        $mode = 'monthly';
        $class = sanitize_input($_POST['class'] ?? '');
        $fixed_monthly_fee = floatval($_POST['fixed_monthly_fee'] ?? 0);

        if (empty($class)) {
            $error = 'Please select a class.';
        } elseif ($fixed_monthly_fee <= 0) {
            $error = 'Monthly fee must be greater than zero.';
        } else {
            $stmt = $conn->prepare("INSERT INTO fee_schedule (class, fixed_monthly_fee) VALUES (?, ?) ON DUPLICATE KEY UPDATE fixed_monthly_fee = VALUES(fixed_monthly_fee)");
            $stmt->bind_param("sd", $class, $fixed_monthly_fee);
            if ($stmt->execute()) {
                $success = 'Fee schedule for Class ' . htmlspecialchars($class) . ' saved successfully.';
            } else {
                $error = 'Failed to save fee schedule: ' . $conn->error;
            }
            $stmt->close();
        }
    } 
    // 2. Schedule Other Fee (Exam Fee, Party Fee, Photo Fee, Maintenance Fee, etc.)
    elseif (isset($_POST['action']) && $_POST['action'] === 'schedule_other_fee') {
        $mode = 'other';
        $other_fee_name = sanitize_input(trim($_POST['other_fee_name'] ?? ''));
        $other_fee_amount = floatval($_POST['other_fee_amount'] ?? 0);
        $class_choice = sanitize_input($_POST['class'] ?? '');
        $section_choice = sanitize_input($_POST['section'] ?? 'all');

        $reserved_titles = ['admission', 'pre_year', 'prev-year', 'pre-year', 'yearly package', 'package-12', 'fine', 'other', 'other payment'];
        
        if (empty($other_fee_name)) {
            $error = 'Please enter a fee title/name (e.g. Exam Fee, Party Fee, Photo Fee, Maintenance Fee).';
        } elseif ($other_fee_amount <= 0) {
            $error = 'Fee amount must be greater than zero.';
        } elseif (empty($class_choice)) {
            $error = 'Please select a class (or All Classes).';
        } elseif (in_array(strtolower($other_fee_name), $reserved_titles) || preg_match('/^[A-Za-z]{3}-[0-9]{4}$/', $other_fee_name)) {
            $error = 'The title "' . htmlspecialchars($other_fee_name) . '" is reserved for standard tuition/package months. Please enter a custom title (e.g. Exam Fee, Party Fee).';
        } else {
            // Find ALL active students matching criteria (INCLUDING Passed-10 & Passed-12)
            $stu_query = "SELECT id, name, class, section FROM students WHERE status = 'active'";
            $params = [];
            $types = '';
            
            if ($class_choice !== 'all') {
                $stu_query .= " AND class = ?";
                $params[] = $class_choice;
                $types .= 's';
            }
            if ($section_choice !== 'all') {
                $stu_query .= " AND section = ?";
                $params[] = $section_choice;
                $types .= 's';
            }

            $stmt = $conn->prepare($stu_query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $matched_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($matched_students)) {
                $error = 'No active students found in the selected Class / Section.';
            } else {
                $conn->begin_transaction();
                try {
                    $ins_stmt = $conn->prepare("INSERT INTO fee_records (student_id, month, amount, status) 
                                                VALUES (?, ?, ?, 'unpaid') 
                                                ON DUPLICATE KEY UPDATE amount = IF(status = 'unpaid', VALUES(amount), amount)");
                    $scheduled_count = 0;
                    foreach ($matched_students as $st) {
                        $s_id = intval($st['id']);
                        $ins_stmt->bind_param('isd', $s_id, $other_fee_name, $other_fee_amount);
                        $ins_stmt->execute();
                        $scheduled_count++;
                    }
                    $ins_stmt->close();
                    $conn->commit();

                    $target_class_txt = ($class_choice === 'all') ? 'All Classes' : 'Class ' . htmlspecialchars($class_choice);
                    $target_sec_txt = ($section_choice === 'all') ? 'All Sections' : 'Section ' . htmlspecialchars($section_choice);
                    $success = "Successfully scheduled '<strong>" . htmlspecialchars($other_fee_name) . "</strong>' (Rs. " . number_format($other_fee_amount, 2) . ") for " . $scheduled_count . " student(s) in " . $target_class_txt . " (" . $target_sec_txt . ")! They will now appear in Fee Payment.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Error scheduling other fee: ' . $e->getMessage();
                }
            }
        }
    }
    // 3. Cancel / Remove Unpaid Other Fee Schedule
    elseif (isset($_POST['action']) && $_POST['action'] === 'cancel_other_fee') {
        $mode = 'other';
        $fee_title_del = sanitize_input($_POST['fee_title'] ?? '');
        $class_del = sanitize_input($_POST['fee_class'] ?? '');
        $section_del = sanitize_input($_POST['fee_section'] ?? 'all');

        if (!empty($fee_title_del)) {
            $del_query = "DELETE fr FROM fee_records fr 
                          JOIN students s ON fr.student_id = s.id 
                          WHERE fr.month = ? AND fr.status = 'unpaid'";
            $d_params = [$fee_title_del];
            $d_types = 's';
            if (!empty($class_del) && $class_del !== 'all') {
                $del_query .= " AND s.class = ?";
                $d_params[] = $class_del;
                $d_types .= 's';
            }
            if (!empty($section_del) && $section_del !== 'all') {
                $del_query .= " AND s.section = ?";
                $d_params[] = $section_del;
                $d_types .= 's';
            }

            $d_stmt = $conn->prepare($del_query);
            $d_stmt->bind_param($d_types, ...$d_params);
            $d_stmt->execute();
            $del_count = $d_stmt->affected_rows;
            $d_stmt->close();
            $success = "Removed " . $del_count . " unpaid fee record(s) for '<strong>" . htmlspecialchars($fee_title_del) . "</strong>'. (Any already paid records were safely preserved).";
        }
    }
}

// Fetch current class monthly fee schedules
$schedules = [];
$res = $conn->query("SELECT * FROM fee_schedule ORDER BY FIELD(class, 'P.G', 'Nursury', 'Pre', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', 'Passed-10', 'Passed-12')");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $schedules[$row['class']] = $row['fixed_monthly_fee'];
    }
}

// Fetch scheduled other fee summaries for Mode 2
$other_fee_summaries = [];
if ($mode === 'other') {
    $other_res = $conn->query("SELECT f.month as fee_title, s.class, s.section, 
                                      MAX(f.amount) as original_amount,
                                      COUNT(f.id) as total_students,
                                      SUM(CASE WHEN f.status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                                      SUM(CASE WHEN f.status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
                                      MIN(f.created_at) as created_time
                               FROM fee_records f
                               JOIN students s ON f.student_id = s.id
                               WHERE f.month NOT IN ('Admission', 'Pre_Year', 'Prev-Year', 'Pre-Year', 'Yearly Package', 'Package-12')
                                 AND f.month NOT REGEXP '^[A-Za-z]{3}-[0-9]{4}$'
                               GROUP BY f.month, s.class, s.section
                               ORDER BY MIN(f.id) DESC");
    if ($other_res) {
        $other_fee_summaries = $other_res->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Schedule - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .mode-container {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 50px;
            display: inline-flex;
            gap: 5px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);
            flex-wrap: wrap;
            justify-content: center;
        }
        .mode-btn {
            border-radius: 40px !important;
            font-weight: 600;
            padding: 10px 24px;
            font-size: 15px;
            transition: all 0.3s ease;
            border: none !important;
            text-decoration: none;
        }
        .mode-btn.active-mode {
            background-color: #24493a !important;
            color: #fff !important;
            box-shadow: 0 4px 12px rgba(36, 73, 58, 0.3);
        }
        .mode-btn:not(.active-mode) {
            color: #7f8c8d;
            background: transparent;
        }
        .mode-btn:not(.active-mode):hover {
            color: #333;
            background: rgba(0,0,0,0.04);
        }
        .quick-title-btn {
            cursor: pointer;
            font-size: 0.8rem;
            padding: 5px 12px;
            border-radius: 20px;
            transition: all 0.2s;
        }
        .quick-title-btn:hover {
            background-color: #24493a !important;
            color: #fff !important;
            border-color: #24493a !important;
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
                        <h2>Fee Schedule</h2>
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
                        <a href="dashboard.php" class="module-nav-btn">
                            <i class="fas fa-chart-bar"></i> Dashboard
                        </a>
                        <a href="add_student.php" class="module-nav-btn">
                            <i class="fas fa-user-plus"></i> Add Student
                        </a>
                        <a href="student_record.php" class="module-nav-btn">
                            <i class="fas fa-address-book"></i> Student Record
                        </a>
                        <a href="student_add_details.php" class="module-nav-btn">
                            <i class="fas fa-history"></i> Add Log
                        </a>
                        <a href="fee_schedule.php" class="module-nav-btn active">
                            <i class="fas fa-calendar-alt"></i> Fee Schedule
                        </a>
                        <a href="fee_management.php" class="module-nav-btn">
                            <i class="fas fa-money-bill-wave"></i> Fee Management
                        </a>
                        <a href="defaulter_list.php" class="module-nav-btn">
                            <i class="fas fa-list"></i> Pending List
                        </a>
                        <a href="paid_students.php" class="module-nav-btn">
                            <i class="fas fa-check-circle text-success"></i> Paid Students
                        </a>
                        <a href="payment_analytics.php" class="module-nav-btn">
                            <i class="fas fa-chart-line"></i> Analytics
                        </a>
                        <a href="receipt_analysis.php" class="module-nav-btn">
                            <i class="fas fa-receipt"></i> Receipt Analysis
                        </a>
                        <a href="expenses.php" class="module-nav-btn">
                            <i class="fas fa-wallet"></i> Expenses
                        </a>
                        <a href="data_correction.php" class="module-nav-btn">
                            <i class="fas fa-edit"></i> Data Correction
                        </a>
                        <a href="promotion.php" class="module-nav-btn">
                            <i class="fas fa-arrow-up"></i> Promotion
                        </a>
                        <a href="drop_student.php" class="module-nav-btn">
                            <i class="fas fa-trash"></i> Drop Student
                        </a>
                        <a href="delete_student.php" class="module-nav-btn">
                            <i class="fas fa-user-minus text-success"></i> Delete Student
                        </a>
                        <a href="users.php" class="module-nav-btn">
                            <i class="fas fa-users-cog"></i> Users
                        </a>
                        <a href="account_close_log.php" class="module-nav-btn">
                            <i class="fas fa-lock"></i> Close Logs
                        </a>
                        <a href="receipt_note.php" class="module-nav-btn">
                            <i class="fas fa-sticky-note"></i> Custom Note
                        </a>
                        <a href="../help.php" class="module-nav-btn">
                            <i class="fas fa-question-circle text-success"></i> Help & About
                        </a>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- MODE SWITCHER TABS -->
                <div class="text-center mb-4">
                    <div class="mode-container">
                        <a href="fee_schedule.php?mode=monthly" class="btn mode-btn <?php echo ($mode === 'monthly') ? 'active-mode' : ''; ?>">
                            <i class="fas fa-calendar-alt me-2"></i> Class Fee Schedule
                        </a>
                        <a href="fee_schedule.php?mode=other" class="btn mode-btn <?php echo ($mode === 'other') ? 'active-mode' : ''; ?>">
                            <i class="fas fa-file-invoice-dollar me-2"></i> Other Fee Schedule
                        </a>
                    </div>
                </div>

                <?php if ($mode === 'monthly'): ?>
                    <!-- MODE 1: CLASS MONTHLY / PACKAGE FEE SCHEDULE -->
                    <div class="row g-4">
                        <!-- Form Panel -->
                        <div class="col-lg-4">
                            <div class="analytics-section">
                                <h4>Set Class Default Fee</h4>
                                <form method="POST" class="mt-3">
                                    <input type="hidden" name="action" value="save_fee">
                                    <input type="hidden" name="mode" value="monthly">
                                    
                                    <div class="mb-3">
                                        <label for="class" class="form-label">Select Class <span class="text-danger">*</span></label>
                                        <select id="class" name="class" class="form-select" required>
                                            <option value="">Choose Class...</option>
                                            <?php foreach ($CLASSES as $cls): ?>
                                                <option value="<?php echo $cls; ?>"><?php echo $cls; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="fixed_monthly_fee" class="form-label" id="feeLabel">Fixed Monthly Fee (Rs.) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rs.</span>
                                            <input type="number" step="0.01" min="0.01" class="form-control" id="fixed_monthly_fee" name="fixed_monthly_fee" required placeholder="0.00">
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn-primary w-100 mt-2">
                                        <i class="fas fa-save"></i> Save Class Fee
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- List Panel -->
                        <div class="col-lg-8">
                            <div class="analytics-section">
                                <h4>Class Default Fee Schedule List</h4>
                                <div class="table-responsive mt-3">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Class</th>
                                                <th>Default Fee</th>
                                                <th>Type</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($CLASSES as $cls): 
                                                $fee = isset($schedules[$cls]) ? $schedules[$cls] : null;
                                                $is_pkg = is_college_class($cls);
                                            ?>
                                                <tr>
                                                    <td><strong><?php echo $cls; ?></strong></td>
                                                    <td>
                                                        <?php if ($fee !== null): ?>
                                                            <strong class="text-success"><?php echo format_currency($fee); ?></strong>
                                                            <?php if ($is_pkg): ?>
                                                                <small class="text-muted d-block">(Yearly Package)</small>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted italic">Not Configured</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($is_pkg): ?>
                                                            <span class="badge bg-primary"><i class="fas fa-box"></i> Yearly Package</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Monthly Fee</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($fee !== null): ?>
                                                            <button class="btn btn-sm btn-outline-primary" onclick="editFee('<?php echo htmlspecialchars($cls); ?>', <?php echo $fee; ?>)">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-outline-success" onclick="editFee('<?php echo htmlspecialchars($cls); ?>', 0)">
                                                                <i class="fas fa-plus"></i> Configure
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- MODE 2: OTHER FEE SCHEDULE (EXAM, PARTY, PHOTO, MAINTENANCE, ETC.) -->
                    <div class="row g-4">
                        <!-- Form Panel -->
                        <div class="col-lg-4">
                            <div class="analytics-section">
                                <h4><i class="fas fa-calendar-plus text-success me-2"></i>Schedule Other Fee</h4>
                                
                                <form method="POST" class="mt-3">
                                    <input type="hidden" name="action" value="schedule_other_fee">
                                    <input type="hidden" name="mode" value="other">
                                    
                                    <div class="mb-3">
                                        <label for="other_fee_name" class="form-label">Fee Name / Title <span class="text-danger">*</span></label>
                                        <input type="text" id="other_fee_name" name="other_fee_name" class="form-control" placeholder="e.g. Exam Fee, Party Fee, Photo Fee" required>
                                        
                                        <!-- Quick Suggestion Badges -->
                                        <div class="mt-2">
                                            <span class="text-muted small d-block mb-1">Quick Suggestions:</span>
                                            <button type="button" class="btn btn-sm btn-outline-secondary quick-title-btn" data-val="Exam Fee">Exam Fee</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary quick-title-btn" data-val="Party Fee">Party Fee</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary quick-title-btn" data-val="Photo Fee">Photo Fee</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary quick-title-btn" data-val="Maintenance Fee">Maintenance Fee</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary quick-title-btn" data-val="Sports Fee">Sports Fee</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary quick-title-btn" data-val="ID Card Fee">ID Card Fee</button>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="other_fee_amount" class="form-label">Amount (Rs.) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rs.</span>
                                            <input type="number" step="0.01" min="1" class="form-control" id="other_fee_amount" name="other_fee_amount" required placeholder="e.g. 500">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="other_class" class="form-label">Target Class <span class="text-danger">*</span></label>
                                        <select id="other_class" name="class" class="form-select" required>
                                            <option value="">Choose Class...</option>
                                            <option value="all">All Classes (Whole School)</option>
                                            <?php foreach ($CLASSES as $cls): ?>
                                                <option value="<?php echo $cls; ?>"><?php echo $cls; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="other_section" class="form-label">Target Section <span class="text-danger">*</span></label>
                                        <select id="other_section" name="section" class="form-select">
                                            <option value="all">All Sections (Boys & Girls)</option>
                                            <?php foreach ($SECTIONS as $sec): ?>
                                                <option value="<?php echo $sec; ?>">Section <?php echo $sec; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn-primary w-100 mt-3" onclick="return confirm('Are you sure you want to schedule this fee for the selected students?');">
                                        <i class="fas fa-calendar-check me-1"></i> Schedule Fee for Class Students
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Summary List Panel -->
                        <div class="col-lg-8">
                            <div class="analytics-section">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="mb-0">Scheduled Other Fees Overview</h4>
                                    <span class="badge bg-success-subtle text-success border border-success px-3 py-2">
                                        Total Types: <?php echo count($other_fee_summaries); ?>
                                    </span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Fee Title</th>
                                                <th>Target Class-Sec</th>
                                                <th>Amount</th>
                                                <th>Students Scheduled</th>
                                                <th>Status</th>
                                                <th class="text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($other_fee_summaries) > 0): ?>
                                                <?php foreach ($other_fee_summaries as $item): ?>
                                                    <tr>
                                                        <td>
                                                            <strong class="text-dark"><i class="fas fa-tag text-warning me-1"></i> <?php echo htmlspecialchars($item['fee_title']); ?></strong>
                                                            <small class="text-muted d-block"><?php echo date('d-M-Y', strtotime($item['created_time'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-light text-dark border">
                                                                <?php echo htmlspecialchars($item['class']); ?> - <?php echo htmlspecialchars($item['section']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <strong class="text-success"><?php echo format_currency($item['original_amount']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <strong><?php echo intval($item['total_students']); ?></strong> Students
                                                        </td>
                                                        <td>
                                                            <?php if (intval($item['unpaid_count']) > 0): ?>
                                                                <span class="badge bg-danger-subtle text-danger border border-danger">
                                                                    <?php echo intval($item['unpaid_count']); ?> Unpaid
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if (intval($item['paid_count']) > 0): ?>
                                                                <span class="badge bg-success-subtle text-success border border-success ms-1">
                                                                    <?php echo intval($item['paid_count']); ?> Paid
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-end">
                                                            <?php if (intval($item['unpaid_count']) > 0): ?>
                                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove this unpaid fee schedule for <?php echo htmlspecialchars(addslashes($item['fee_title'])); ?> (Class <?php echo htmlspecialchars(addslashes($item['class'])); ?>)? Any already paid records will be preserved.');">
                                                                    <input type="hidden" name="action" value="cancel_other_fee">
                                                                    <input type="hidden" name="mode" value="other">
                                                                    <input type="hidden" name="fee_title" value="<?php echo htmlspecialchars($item['fee_title']); ?>">
                                                                    <input type="hidden" name="fee_class" value="<?php echo htmlspecialchars($item['class']); ?>">
                                                                    <input type="hidden" name="fee_section" value="<?php echo htmlspecialchars($item['section']); ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove unpaid records">
                                                                        <i class="fas fa-trash-alt me-1"></i> Cancel Unpaid
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <span class="badge bg-success text-white"><i class="fas fa-check-double me-1"></i> All Paid</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-5 text-muted">
                                                        <i class="fas fa-receipt text-secondary mb-3" style="font-size: 36px;"></i>
                                                        <p class="mb-0 fw-bold">No other custom fees currently scheduled.</p>
                                                        <small class="text-muted">Use the form on the left to schedule Exam Fee, Party Fee, Photo Fee, etc. for any class or section.</small>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        const collegeClasses = ['11', '12', 'passed-12', '11th', '12th'];
        
        function editFee(className, feeAmount) {
            const classSelect = document.getElementById('class');
            if (classSelect) {
                classSelect.value = className;
                document.getElementById('fixed_monthly_fee').value = feeAmount > 0 ? feeAmount : '';
                updateFeeLabel();
                document.getElementById('fixed_monthly_fee').focus();
            }
        }

        function updateFeeLabel() {
            const classSelect = document.getElementById('class');
            const label = document.getElementById('feeLabel');
            if (classSelect && label) {
                const cls = classSelect.value;
                if (cls && collegeClasses.includes(cls.toString().trim().toLowerCase())) {
                    label.innerHTML = 'Package Fee (Yearly Rs.) <span class="text-danger">*</span>';
                } else {
                    label.innerHTML = 'Fixed Monthly Fee (Rs.) <span class="text-danger">*</span>';
                }
            }
        }

        const classEl = document.getElementById('class');
        if (classEl) {
            classEl.addEventListener('change', updateFeeLabel);
        }

        // Quick suggestion chips in Mode 2
        document.querySelectorAll('.quick-title-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const titleInput = document.getElementById('other_fee_name');
                if (titleInput) {
                    titleInput.value = this.getAttribute('data-val');
                    titleInput.focus();
                }
            });
        });
    </script>
</body>
</html>