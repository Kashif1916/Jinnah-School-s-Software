<?php
/**
 * Finance Dashboard
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_finance();

$current_year_str = date('Y');
$current_month_str = date('M-Y');

// Regex for College / Passed Out Classes
$college_classes_regex = '^(11|12|11th|12th|F\\.Sc|FA|ICS|I\\.Com|1st Year|2nd Year)';
$passed_classes_regex  = '^(Passed-10|Passed-12|Passed)';

// ---------------------------------------------------------------------
// 1. ACTIVE STUDENTS BREAKDOWN (School vs College)
// ---------------------------------------------------------------------
// School Active Students (PG to 10)
$school_students_query = "SELECT COUNT(*) as count 
                          FROM students 
                          WHERE status = 'active' 
                            AND (is_package = 0 OR is_package IS NULL)
                            AND class NOT REGEXP '$college_classes_regex' 
                            AND class NOT REGEXP '$passed_classes_regex'";
$school_total_students = intval($conn->query($school_students_query)->fetch_assoc()['count'] ?? 0);

// College Active Students (11 & 12)
$college_students_query = "SELECT COUNT(*) as count 
                           FROM students 
                           WHERE status = 'active' 
                             AND (is_package = 1 OR class REGEXP '$college_classes_regex') 
                             AND class NOT REGEXP '$passed_classes_regex'";
$college_total_students = intval($conn->query($college_students_query)->fetch_assoc()['count'] ?? 0);

$total_students = $school_total_students + $college_total_students;

// Section B (Boys) Count (Excluding Passed Out)
$total_boys_query = "SELECT COUNT(*) as count FROM students WHERE status = 'active' AND section = 'B' AND class NOT REGEXP '$college_classes_regex' AND class NOT REGEXP '$passed_classes_regex'";
$total_boys = intval($conn->query($total_boys_query)->fetch_assoc()['count'] ?? 0);

// Section G (Girls) Count (Excluding Passed Out)
$total_girls_query = "SELECT COUNT(*) as count FROM students WHERE status = 'active' AND section = 'G' AND class NOT REGEXP '$passed_classes_regex'";
$total_girls = intval($conn->query($total_girls_query)->fetch_assoc()['count'] ?? 0);

// ---------------------------------------------------------------------
// 2. COLLEGE YEARLY & MONTHLY PACKAGE FINANCIAL CALCULATIONS
// ---------------------------------------------------------------------
$start_of_month = date('Y-m-01 00:00:00');
$end_of_month = date('Y-m-t 23:59:59');

// This Month College Fee Collected
$college_month_collected = 0;
$clg_m_rec_res = $conn->query("SELECT SUM(p.amount) as total 
                               FROM payments p 
                               JOIN students s ON s.id = p.student_id 
                               WHERE p.payment_date >= '$start_of_month' AND p.payment_date <= '$end_of_month' 
                                 AND s.status = 'active' 
                                 AND (s.is_package = 1 OR s.class REGEXP '$college_classes_regex') 
                                 AND s.class NOT REGEXP '$passed_classes_regex'");
if ($clg_m_rec_res) {
    $college_month_collected = round(floatval($clg_m_rec_res->fetch_assoc()['total'] ?? 0));
}

// College Total Expected Package Fee (Current Year)
$college_total_expected = 0;
$clg_exp_res = $conn->query("SELECT SUM(GREATEST(0, (s.package_amount - s.concession_amount))) as total 
                            FROM students s 
                            WHERE s.status = 'active' 
                              AND (s.is_package = 1 OR s.class REGEXP '$college_classes_regex') 
                              AND s.class NOT REGEXP '$passed_classes_regex'");
if ($clg_exp_res) {
    $college_total_expected = round(floatval($clg_exp_res->fetch_assoc()['total'] ?? 0));
}

// College Total Received Fee (Current Year)
$college_received_year = 0;
$clg_rec_res = $conn->query("SELECT SUM(p.amount) as total 
                             FROM payments p 
                             JOIN students s ON s.id = p.student_id 
                             WHERE (YEAR(p.payment_date) = '$current_year_str' OR p.paid_for_month LIKE '%-$current_year_str') 
                               AND s.status = 'active' 
                               AND (s.is_package = 1 OR s.class REGEXP '$college_classes_regex') 
                               AND s.class NOT REGEXP '$passed_classes_regex'");
if ($clg_rec_res) {
    $college_received_year = round(floatval($clg_rec_res->fetch_assoc()['total'] ?? 0));
}

// College Received Fee Financial Percentage
$college_financial_pct = ($college_total_expected > 0) ? round(($college_received_year / $college_total_expected) * 100, 1) : 0;

// College Paid Students Count (Strict Full Payment Check)
$college_paid_students_count = intval($conn->query("
    SELECT COUNT(*) as count FROM (
        SELECT s.id
        FROM students s
        JOIN payments p ON s.id = p.student_id
        WHERE s.status = 'active'
          AND (s.is_package = 1 OR s.class REGEXP '$college_classes_regex')
          AND s.class NOT REGEXP '$passed_classes_regex'
          AND (YEAR(p.payment_date) = '$current_year_str' OR p.paid_for_month LIKE '%-$current_year_str')
        GROUP BY s.id, s.package_amount, s.concession_amount
        HAVING SUM(p.amount) >= GREATEST(0, (s.package_amount - s.concession_amount))
    ) AS fully_paid_college_students
")->fetch_assoc()['count'] ?? 0);

$college_paid_percentage_std = ($college_total_students > 0) ? round(($college_paid_students_count / $college_total_students) * 100, 1) : 0;

// ---------------------------------------------------------------------
// 3. HANDLE AJAX REQUEST FOR SCHOOL PAID STUDENTS FILTER
// ---------------------------------------------------------------------
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_paid_students') {
    header('Content-Type: application/json');
    $selected_month = sanitize_input($_GET['month'] ?? date('M-Y'));
    
    // School Paid Count
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT fr.student_id) as count 
                            FROM fee_records fr 
                            JOIN students s ON s.id = fr.student_id 
                            WHERE fr.month = ? AND fr.status = 'paid' AND s.status = 'active' 
                              AND (s.is_package = 0 OR s.is_package IS NULL)
                              AND s.class NOT REGEXP '$college_classes_regex' 
                              AND s.class NOT REGEXP '$passed_classes_regex'");
    $stmt->bind_param('s', $selected_month);
    $stmt->execute();
    $count = intval($stmt->get_result()->fetch_assoc()['count'] ?? 0);
    $stmt->close();

    // Boys Paid Count (School)
    $stmt_b = $conn->prepare("SELECT COUNT(DISTINCT f.student_id) as count 
                              FROM fee_records f 
                              JOIN students s ON f.student_id = s.id 
                              WHERE f.month = ? AND f.status = 'paid' AND s.section = 'B' AND s.status = 'active' 
                                AND (s.is_package = 0 OR s.is_package IS NULL)
                                AND s.class NOT REGEXP '$college_classes_regex' 
                                AND s.class NOT REGEXP '$passed_classes_regex'");
    $stmt_b->bind_param('s', $selected_month);
    $stmt_b->execute();
    $boys_count = intval($stmt_b->get_result()->fetch_assoc()['count'] ?? 0);
    $stmt_b->close();

    // Girls Paid Count (School)
    $stmt_g = $conn->prepare("SELECT COUNT(DISTINCT f.student_id) as count 
                              FROM fee_records f 
                              JOIN students s ON f.student_id = s.id 
                              WHERE f.month = ? AND f.status = 'paid' AND s.section = 'G' AND s.status = 'active' 
                                AND (s.is_package = 0 OR s.is_package IS NULL)
                                AND s.class NOT REGEXP '$college_classes_regex' 
                                AND s.class NOT REGEXP '$passed_classes_regex'");
    $stmt_g->bind_param('s', $selected_month);
    $stmt_g->execute();
    $girls_count = intval($stmt_g->get_result()->fetch_assoc()['count'] ?? 0);
    $stmt_g->close();

    // Calculate Percentages
    $percentage = ($school_total_students > 0) ? round(($count / $school_total_students) * 100, 1) : 0;
    $boys_percentage = ($total_boys > 0) ? round(($boys_count / $total_boys) * 100, 1) : 0;
    $girls_percentage = ($total_girls > 0) ? round(($girls_count / $total_girls) * 100, 1) : 0;

    echo json_encode([
        'success' => true, 
        'count' => $count,
        'percentage' => $percentage,
        'boys_count' => $boys_count,
        'boys_percentage' => $boys_percentage,
        'girls_count' => $girls_count,
        'girls_percentage' => $girls_percentage
    ]);
    exit();
}

// Logged-in user info
$current_user = get_username();
$today_date = date('Y-m-d');

// 1. Filtered Today's Collection
$stmt_coll = $conn->prepare("SELECT SUM(amount) as total FROM payments WHERE received_by = ? AND DATE(payment_date) = ?");
$stmt_coll->bind_param('ss', $current_user, $today_date);
$stmt_coll->execute();
$today_collection = round(floatval($stmt_coll->get_result()->fetch_assoc()['total'] ?? 0));
$stmt_coll->close();

// 2. Today's Total Receipts Count
$stmt_rec = $conn->prepare("SELECT COUNT(DISTINCT payment_date) as count FROM payments WHERE received_by = ? AND DATE(payment_date) = ?");
$stmt_rec->bind_param('ss', $current_user, $today_date);
$stmt_rec->execute();
$today_receipts = intval($stmt_rec->get_result()->fetch_assoc()['count'] ?? 0);
$stmt_rec->close();

// 3. Default Current Month Paid Students (School)
$stmt_paid_curr = $conn->prepare("SELECT COUNT(DISTINCT fr.student_id) as count 
                                  FROM fee_records fr 
                                  JOIN students s ON s.id = fr.student_id 
                                  WHERE fr.month = ? AND fr.status = 'paid' AND s.status = 'active' 
                                    AND (s.is_package = 0 OR s.is_package IS NULL)
                                    AND s.class NOT REGEXP '$college_classes_regex' 
                                    AND s.class NOT REGEXP '$passed_classes_regex'");
$stmt_paid_curr->bind_param('s', $current_month_str);
$stmt_paid_curr->execute();
$total_students_paid_current = intval($stmt_paid_curr->get_result()->fetch_assoc()['count'] ?? 0);
$stmt_paid_curr->close();

// Boys Paid Current Month (School)
$stmt_b_curr = $conn->prepare("SELECT COUNT(DISTINCT f.student_id) as count 
                                FROM fee_records f 
                                JOIN students s ON f.student_id = s.id 
                                WHERE f.month = ? AND f.status = 'paid' AND s.section = 'B' AND s.status = 'active' 
                                  AND (s.is_package = 0 OR s.is_package IS NULL)
                                  AND s.class NOT REGEXP '$college_classes_regex' 
                                  AND s.class NOT REGEXP '$passed_classes_regex'");
$stmt_b_curr->bind_param('s', $current_month_str);
$stmt_b_curr->execute();
$boys_paid_current = intval($stmt_b_curr->get_result()->fetch_assoc()['count'] ?? 0);
$stmt_b_curr->close();

// Girls Paid Current Month (School)
$stmt_g_curr = $conn->prepare("SELECT COUNT(DISTINCT f.student_id) as count 
                                FROM fee_records f 
                                JOIN students s ON f.student_id = s.id 
                                WHERE f.month = ? AND f.status = 'paid' AND s.section = 'G' AND s.status = 'active' 
                                  AND (s.is_package = 0 OR s.is_package IS NULL)
                                  AND s.class NOT REGEXP '$college_classes_regex' 
                                  AND s.class NOT REGEXP '$passed_classes_regex'");
$stmt_g_curr->bind_param('s', $current_month_str);
$stmt_g_curr->execute();
$girls_paid_current = intval($stmt_g_curr->get_result()->fetch_assoc()['count'] ?? 0);
$stmt_g_curr->close();

// Calculate Current Month Percentages
$current_paid_percentage = ($school_total_students > 0) ? round(($total_students_paid_current / $school_total_students) * 100, 1) : 0;
$current_boys_percentage = ($total_boys > 0) ? round(($boys_paid_current / $total_boys) * 100, 1) : 0;
$current_girls_percentage = ($total_girls > 0) ? round(($girls_paid_current / $total_girls) * 100, 1) : 0;

// Dynamic Month List
$month_options = [];
$first_day_of_month = strtotime(date('Y-m-01'));
for ($i = 0; $i < 12; $i++) {
    $m_key = date('M-Y', strtotime("-$i month", $first_day_of_month));
    $m_label = date('F Y', strtotime("-$i month", $first_day_of_month));
    $month_options[$m_key] = $m_label;
}

// Monthly Fine & Other Fees
$this_month_fine = 0;
$fine_coll_res = $conn->query("SELECT SUM(amount) as total FROM payments 
                               WHERE paid_for_month = 'Fine' 
                                 AND payment_date >= '$start_of_month' AND payment_date <= '$end_of_month'");
if ($fine_coll_res) {
    $this_month_fine = round(floatval($fine_coll_res->fetch_assoc()['total'] ?? 0));
}

$this_month_other_fee = 0;
$other_fee_res = $conn->query("SELECT SUM(p.amount) as total FROM payments p
                               WHERE p.payment_date >= '$start_of_month' AND p.payment_date <= '$end_of_month'
                                 AND p.paid_for_month NOT IN ('Admission', 'Pre_Year', 'Prev-Year', 'Pre-Year', 'Yearly Package', 'Fine', 'Other')
                                 AND p.paid_for_month NOT REGEXP '^[A-Za-z]{3}-[0-9]{4}$'
                                 AND p.paid_for_month NOT LIKE '%Package%'");
if ($other_fee_res) {
    $this_month_other_fee = round(floatval($other_fee_res->fetch_assoc()['total'] ?? 0));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        /* Strict 4 Grid Columns Layout */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: repeat(1, 1fr);
            }
        }

        .stat-card--dropdown {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .stat-card__content-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-grow: 1;
            min-width: 0;
        }
        .stat-card__dropdown-btn {
            background: #ffffff;
            border: 1px solid #ced4da;
            color: #495057;
            padding: 4px 8px;
            font-size: 0.75rem;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            white-space: nowrap;
        }
        .stat-card__dropdown-btn:hover, .stat-card__dropdown-btn:focus {
            background: #f8f9fa;
            color: #198754;
        }
        .stat-percentage {
            font-size: 0.95rem;
            font-weight: 600;
            color: #198754;
            margin-left: 4px;
        }
    </style>
</head>
<body>
    <div class="wrapper dashboard-shell">
        <main class="main-content">
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <?php echo render_system_logo('topbar-logo'); ?>
                    <div class="panel-brand">
                        <h2>Finance Dashboard</h2>
                        <span>Finance / Clerk Panel</span>
                    </div>
                </div>
                <div class="topbar-right">
                    <span class="user-info">
                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($current_user); ?> 
                        <small>(Finance Clerk)</small>
                    </span>
                    <a href="../logout.php" class="btn-secondary">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <div class="content">
                <div class="module-nav-panel">
                    <div class="module-nav-row">
                        <a href="dashboard.php" class="module-nav-btn active">
                            <i class="fas fa-chart-bar"></i> Dashboard
                        </a>
                        <a href="add_student.php" class="module-nav-btn">
                            <i class="fas fa-list"></i> Add Student
                        </a>
                        <a href="student_record.php" class="module-nav-btn">
                            <i class="fas fa-address-book"></i> Student Record
                        </a>
                        <a href="fee_payment.php" class="module-nav-btn">
                            <i class="fas fa-money-bill-wave"></i> Fee Payment
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
                        <a href="drop_student.php" class="module-nav-btn">
                            <i class="fas fa-trash text-success"></i> Drop Student
                        </a>
                        <a href="account_close.php" class="module-nav-btn">
                            <i class="fas fa-lock"></i> Close Account
                        </a>
                        <a href="../help.php" class="module-nav-btn">
                            <i class="fas fa-question-circle text-success"></i> Help & About
                        </a>
                    </div>
                </div>
                
                <div class="dashboard-stage dashboard-stage--single mb-4">
                    <aside class="stage-panel stage-panel--hero">
                        <div class="welcome-card__header">
                            <div class="welcome-avatar">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div>
                                <span class="welcome-label">Welcome</span>
                                <h4 style="color: white;"><?php echo htmlspecialchars($current_user); ?></h4>
                                <p style="color: rgba(255,255,255,0.8);">Finance clerk active</p>
                            </div>
                        </div>

                        <p class="welcome-card__text" style="color: rgba(255,255,255,0.9);">
                            Quick access to payments, pending fees, and daily collections. Use the buttons below to continue your work.
                        </p>

                        <div class="hero-row">
                            <span class="hero-tag"><i class="fas fa-shield-alt"></i> Finance access only</span>
                            <a href="backup.php" class="hero-tag" style="text-decoration:none; color:inherit;">
                                <i class="fas fa-database"></i> Backup Data
                            </a>
                        </div>
                    </aside>
                </div>

                <!-- Stats Grid (Strict 4 per row layout) -->
                <div class="stats-grid">
                    <!-- ROW 1: CARD 1 -> School Active Students -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                            <i class="fas fa-child"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $school_total_students; ?></h3>
                            <p>School Active Students</p>
                        </div>
                    </div>

                    <!-- ROW 1: CARD 2 -> College Active Students -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $college_total_students; ?></h3>
                            <p>College Active Students</p>
                        </div>
                    </div>

                    <!-- ROW 1: CARD 3 -> Boys (Section B) -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                            <i class="fas fa-mars"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $total_boys; ?></h3>
                            <p>Boys (Section B)</p>
                        </div>
                    </div>

                    <!-- ROW 1: CARD 4 -> Girls (Section G) -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                            <i class="fas fa-venus"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $total_girls; ?></h3>
                            <p>Girls (Section G)</p>
                        </div>
                    </div>

                    <!-- ROW 2: CARD 5 -> School Paid Students (With Month Dropdown) -->
                    <div class="stat-card stat-card--dropdown">
                        <div class="stat-card__content-wrapper">
                            <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="stat-content">
                                <h3>
                                    <span id="paid_students_count"><?php echo $total_students_paid_current; ?></span>
                                    <span class="stat-percentage" id="paid_students_percentage">(<?php echo $current_paid_percentage; ?>%)</span>
                                </h3>
                                <p id="paid_students_label" class="mb-0" style="white-space: nowrap;">School Paid (<?php echo date('M Y'); ?>)</p>
                            </div>
                        </div>

                        <div class="dropdown">
                            <button class="btn dropdown-toggle stat-card__dropdown-btn" type="button" id="monthDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="monthDropdown">
                                <?php foreach ($month_options as $m_val => $m_text): ?>
                                    <li>
                                        <a class="dropdown-item month-select-item" href="#" data-month="<?php echo $m_val; ?>" data-label="<?php echo date('M Y', strtotime("01-$m_val")); ?>">
                                            <?php echo $m_text; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                     
                    <!-- ROW 2: CARD 6 -> Boys Paid Card -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                            <i class="fas fa-mars"></i>
                        </div>
                        <div class="stat-content">
                            <h3>
                                <span id="boys_paid_count"><?php echo $boys_paid_current; ?></span>
                                <span class="stat-percentage" id="boys_paid_percentage">(<?php echo $current_boys_percentage; ?>%)</span>
                            </h3>
                            <p id="boys_paid_label" class="mb-0">Boys Paid (<?php echo date('M Y'); ?>)</p>
                        </div>
                    </div>

                    <!-- ROW 2: CARD 7 -> Girls Paid Card -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                            <i class="fas fa-venus"></i>
                        </div>
                        <div class="stat-content">
                            <h3>
                                <span id="girls_paid_count"><?php echo $girls_paid_current; ?></span>
                                <span class="stat-percentage" id="girls_paid_percentage">(<?php echo $current_girls_percentage; ?>%)</span>
                            </h3>
                            <p id="girls_paid_label" class="mb-0">Girls Paid (<?php echo date('M Y'); ?>)</p>
                        </div>
                    </div> 
                      
                    <!-- ROW 2: CARD 8 -> Today's Total Receipts -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $today_receipts; ?></h3>
                            <p>Today's Total Receipts</p>
                        </div>
                    </div>

                    <!-- ROW 3: CARD 9 -> College Paid Students (Fully Cleared Package Only) -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="stat-content">
                            <h3>
                                <?php echo $college_paid_students_count; ?>
                                <span class="stat-percentage"> (<?php echo $college_paid_percentage_std; ?>%)</span>
                            </h3>
                            <p>College Paid (<?php echo $current_year_str; ?>)</p>
                        </div>
                    </div>

                    <!-- ROW 3: CARD 10 -> This Month College Fee Collected -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="white-space: nowrap;">Rs. <?php echo number_format($college_month_collected, 0); ?></h3>
                            <p>This Month College Fee (<?php echo date('M Y'); ?>)</p>
                        </div>
                    </div>

                    <!-- ROW 3: CARD 11 -> College Total Fee (Current Year) -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                            <i class="fas fa-university"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="white-space: nowrap;">Rs. <?php echo number_format($college_total_expected, 0); ?></h3>
                            <p>College Total Fee (<?php echo $current_year_str; ?>)</p>
                        </div>
                    </div>

                    <!-- ROW 3: CARD 12 -> College Received Fee (Current Year) + Percentage -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="white-space: nowrap;">
                                Rs. <?php echo number_format($college_received_year, 0); ?>
                                <span class="stat-percentage"> (<?php echo $college_financial_pct; ?>%)</span>
                            </h3>
                            <p>College Received Fee (<?php echo $current_year_str; ?>)</p>
                        </div>
                    </div>

                    <!-- ROW 4: CARD 13 -> My Today's Collection -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Rs. <?php echo number_format($today_collection, 0); ?></h3>
                            <p>My Today's Collection</p>
                        </div>
                    </div>

                    <!-- ROW 4: CARD 14 -> This Month Fine -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="white-space: nowrap;">Rs. <?php echo number_format($this_month_fine, 0); ?></h3>
                            <p>This Month Fine</p>
                        </div>
                    </div>

                    <!-- ROW 4: CARD 15 -> This Month Other Fee -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="white-space: nowrap;">Rs. <?php echo number_format($this_month_other_fee, 0); ?></h3>
                            <p>This Month Other Dues</p>
                        </div>
                    </div>
                </div>
                
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        document.querySelectorAll('.month-select-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const selectedMonth = this.getAttribute('data-month');
                const displayLabel = this.getAttribute('data-label');

                fetch(`dashboard.php?ajax_action=get_paid_students&month=${encodeURIComponent(selectedMonth)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update School Paid Card
                            document.getElementById('paid_students_count').innerText = data.count;
                            document.getElementById('paid_students_percentage').innerText = `(${data.percentage}%)`;
                            document.getElementById('paid_students_label').innerText = `School Paid (${displayLabel})`;

                            // Update Boys Paid Card
                            document.getElementById('boys_paid_count').innerText = data.boys_count;
                            document.getElementById('boys_paid_percentage').innerText = `(${data.boys_percentage}%)`;
                            document.getElementById('boys_paid_label').innerText = `Boys Paid (${displayLabel})`;

                            // Update Girls Paid Card
                            document.getElementById('girls_paid_count').innerText = data.girls_count;
                            document.getElementById('girls_paid_percentage').innerText = `(${data.girls_percentage}%)`;
                            document.getElementById('girls_paid_label').innerText = `Girls Paid (${displayLabel})`;
                        }
                    })
                    .catch(err => console.error("Error fetching paid students:", err));
            });
        });
    </script>
</body>
</html>