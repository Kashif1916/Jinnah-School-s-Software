<?php
/**
 * Master Dashboard
 * School Finance Management System (School & College Segregated)
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master();

$current_year_str = date('Y');
$current_month_str = date('M-Y');

// Regex for College / Passed Out Classes
$college_classes_regex = '^(11|12|11th|12th|F\\.Sc|FA|ICS|I\\.Com|1st Year|2nd Year)';
$passed_classes_regex  = '^(Passed-10|Passed-12|Passed)';

// ---------------------------------------------------------------------
// 1. ACTIVE STUDENTS BREAKDOWN (Excluding Passed Out Classes)
// ---------------------------------------------------------------------
$school_students_query = "SELECT COUNT(*) as count 
                          FROM students 
                          WHERE status = 'active' 
                            AND (is_package = 0 OR is_package IS NULL)
                            AND class NOT REGEXP '$college_classes_regex' 
                            AND class NOT REGEXP '$passed_classes_regex'";
$school_total_students = intval($conn->query($school_students_query)->fetch_assoc()['count'] ?? 0);

$college_students_query = "SELECT COUNT(*) as count 
                           FROM students 
                           WHERE status = 'active' 
                             AND (is_package = 1 OR class REGEXP '$college_classes_regex') 
                             AND class NOT REGEXP '$passed_classes_regex'";
$college_total_students = intval($conn->query($college_students_query)->fetch_assoc()['count'] ?? 0);

$total_students = $school_total_students + $college_total_students;

$total_boys = intval($conn->query("SELECT COUNT(*) as count FROM students WHERE section = 'B' AND status = 'active'  AND class NOT REGEXP '$college_classes_regex' AND class NOT REGEXP '$passed_classes_regex'")->fetch_assoc()['count'] ?? 0);
$total_girls = intval($conn->query("SELECT COUNT(*) as count FROM students WHERE section = 'G' AND status = 'active' AND class NOT REGEXP '$college_classes_regex' AND class NOT REGEXP '$passed_classes_regex'")->fetch_assoc()['count'] ?? 0);

// ---------------------------------------------------------------------
// 2. AJAX FILTER FOR SCHOOL PAID STUDENTS
// ---------------------------------------------------------------------
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_paid_students') {
    header('Content-Type: application/json');
    $selected_month = sanitize_input($_GET['month'] ?? date('M-Y'));
    
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

    $percentage = ($school_total_students > 0) ? round(($count / $school_total_students) * 100, 1) : 0;

    echo json_encode([
        'success' => true, 
        'count' => $count,
        'percentage' => $percentage
    ]);
    exit();
}

// ---------------------------------------------------------------------
// 3. GRAPH 1: SCHOOL MONTHLY BREAKDOWN (UPDATED LOGIC)
// ---------------------------------------------------------------------

// Step 1: Active School Students ki total monthly expected fee
$school_month_total = 0;
$sc_tot_res = $conn->query("SELECT SUM(monthly_fee) as fee 
                            FROM students s
                            WHERE s.status = 'active' 
                              AND (s.is_package = 0 OR s.is_package IS NULL)
                              AND s.class NOT REGEXP '$college_classes_regex' 
                              AND s.class NOT REGEXP '$passed_classes_regex'");
if ($sc_tot_res) {
    $school_month_total = round(floatval($sc_tot_res->fetch_assoc()['fee'] ?? 0));
}

// Step 2: Current month ke payments table se total collected fee
$school_month_collected = 0;
$sc_coll_res = $conn->query("SELECT SUM(p.amount) as total 
                             FROM payments p 
                             JOIN students s ON s.id = p.student_id 
                             WHERE p.paid_for_month = '$current_month_str' 
                               AND s.status = 'active' 
                               AND (s.is_package = 0 OR s.is_package IS NULL)
                               AND s.class NOT REGEXP '$college_classes_regex' 
                               AND s.class NOT REGEXP '$passed_classes_regex'");
if ($sc_coll_res) {
    $school_month_collected = round(floatval($sc_coll_res->fetch_assoc()['total'] ?? 0));
}

// Step 3: Minus karke Pending Value nikalna
$school_month_unpaid = max(0, $school_month_total - $school_month_collected);
$school_paid_percentage = $school_month_total > 0 ? round(($school_month_collected / $school_month_total) * 100) : 0;

// ---------------------------------------------------------------------
// 4. GRAPH 2 & CARDS: COLLEGE YEARLY PACKAGE BREAKDOWN
// ---------------------------------------------------------------------
$college_total_expected = 0;
$clg_exp_res = $conn->query("SELECT SUM(GREATEST(0, (s.package_amount - s.concession_amount))) as total 
                            FROM students s 
                            WHERE s.status = 'active' 
                              AND (s.is_package = 1 OR s.class REGEXP '$college_classes_regex') 
                              AND s.class NOT REGEXP '$passed_classes_regex'");
if ($clg_exp_res) {
    $college_total_expected = round(floatval($clg_exp_res->fetch_assoc()['total'] ?? 0));
}

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

$college_pending_year = max(0, $college_total_expected - $college_received_year);
$college_paid_percentage = $college_total_expected > 0 ? round(($college_received_year / $college_total_expected) * 100) : 0;

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
// 5. SCHOOL PAID STUDENTS (Default Current Month)
// ---------------------------------------------------------------------
$school_paid_curr_res = $conn->query("SELECT COUNT(DISTINCT fr.student_id) as count 
                                       FROM fee_records fr 
                                       JOIN students s ON s.id = fr.student_id 
                                       WHERE fr.month = '$current_month_str' 
                                         AND fr.status = 'paid' 
                                         AND s.status = 'active' 
                                         AND (s.is_package = 0 OR s.is_package IS NULL)
                                         AND s.class NOT REGEXP '$college_classes_regex' 
                                         AND s.class NOT REGEXP '$passed_classes_regex'");
$school_students_paid_current = intval($school_paid_curr_res->fetch_assoc()['count'] ?? 0);
$school_current_paid_pct = ($school_total_students > 0) ? round(($school_students_paid_current / $school_total_students) * 100, 1) : 0;

// ---------------------------------------------------------------------
// 6. FINANCIAL CALCULATIONS & FINE / OTHER DUES
// ---------------------------------------------------------------------
$today_collection = round(floatval(get_daily_collection(date('Y-m-d'))));

$start_of_month = date('Y-m-01 00:00:00');
$end_of_month = date('Y-m-t 23:59:59');

$this_month_collection = 0;
$month_coll_res = $conn->query("SELECT SUM(amount) as total FROM payments WHERE payment_date >= '$start_of_month' AND payment_date <= '$end_of_month'");
if ($month_coll_res) {
    $this_month_collection = round(floatval($month_coll_res->fetch_assoc()['total'] ?? 0));
}

$this_month_expenses = 0;
$month_exp_res = $conn->query("SELECT SUM(amount) as total FROM expenses WHERE created_at >= '$start_of_month' AND created_at <= '$end_of_month'");
if ($month_exp_res) {
    $this_month_expenses = round(floatval($month_exp_res->fetch_assoc()['total'] ?? 0));
}

$this_month_net_profit = $this_month_collection - $this_month_expenses;

// Fine Collection
$this_month_fine = 0;
$fine_coll_res = $conn->query("SELECT SUM(amount) as total FROM payments 
                               WHERE paid_for_month = 'Fine' 
                                 AND payment_date >= '$start_of_month' AND payment_date <= '$end_of_month'");
if ($fine_coll_res) {
    $this_month_fine = round(floatval($fine_coll_res->fetch_assoc()['total'] ?? 0));
}

// Other Custom Fee Collection
$this_month_other_fee = 0;
$other_fee_res = $conn->query("SELECT SUM(p.amount) as total FROM payments p
                               WHERE p.payment_date >= '$start_of_month' AND p.payment_date <= '$end_of_month'
                                 AND p.paid_for_month NOT IN ('Admission', 'Pre_Year', 'Prev-Year', 'Pre-Year', 'Yearly Package', 'Fine', 'Other')
                                 AND p.paid_for_month NOT REGEXP '^[A-Za-z]{3}-[0-9]{4}$'
                                 AND p.paid_for_month NOT LIKE '%Package%'");
if ($other_fee_res) {
    $this_month_other_fee = round(floatval($other_fee_res->fetch_assoc()['total'] ?? 0));
}

// Dynamic Month List
$month_options = [];
$first_day_of_month = strtotime(date('Y-m-01'));
for ($i = 0; $i < 12; $i++) {
    $m_key = date('M-Y', strtotime("-$i month", $first_day_of_month));
    $m_label = date('F Y', strtotime("-$i month", $first_day_of_month));
    $month_options[$m_key] = $m_label;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
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
        
        /* Strict 4 Cards Per Row Grid Layout */
        .dashboard-cards-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            width: 100%;
        }

        @media (max-width: 1200px) {
            .dashboard-cards-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 576px) {
            .dashboard-cards-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper dashboard-shell">
        <main class="main-content">
            <!-- Top Bar -->
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <?php echo render_system_logo('topbar-logo'); ?>
                    <div class="panel-brand">
                        <h2>Dashboard</h2>
                        <span>Principal Panel</span>
                    </div>
                </div>
                <div class="topbar-right">
                    <span class="user-info">
                        <i class="fas fa-user-circle"></i> <?php echo get_username(); ?> 
                        <small>(Principal)</small>
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
                        <a href="dashboard.php" class="module-nav-btn active">
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
                        <a href="fee_schedule.php" class="module-nav-btn">
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
                            <i class="fas fa-trash text-success"></i> Drop Student
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
                    </div>
                </div>

                <!-- TOP ROW: EXPANDED HERO BANNER (50%) + COMPACT SCHOOL GRAPH (25%) + COMPACT COLLEGE GRAPH (25%) -->
                <div class="row g-3 mb-4">
                    <!-- EXPANDED HERO BANNER -->
                    <div class="col-lg-6">
                        <section class="stage-panel stage-panel--hero h-100 d-flex flex-column justify-content-between" style="padding: 24px;">
                            <div>
                                <span class="dashboard-kicker"><i class="fas fa-school"></i> Principal Dashboard</span>
                                <h3 class="mt-2 mb-2" style="font-size: 1.45rem;">Jinnah High School & Inter College Khushab</h3>
                                <p style="font-size: 0.9rem; max-width: 95%;" class="text-white-50 mb-3">Track students, manage fees, review pending payments, and keep promotion work moving seamlessly.</p>
                            </div>
                            <div class="hero-row d-flex flex-wrap gap-2">
                                <span class="hero-tag"><i class="fas fa-shield-alt"></i> Principal access only</span>
                                <a href="backup.php" class="hero-tag" style="text-decoration:none; color:inherit;">
                                    <i class="fas fa-database"></i> Backup System
                                </a>
                                <span class="hero-tag" role="button" style="cursor:pointer;" data-bs-toggle="modal" data-bs-target="#monthlyReportModal">
                                    <i class="fas fa-calendar-alt"></i> Monthly Report
                                </span>
                                <span class="hero-tag" role="button" style="cursor:pointer;" data-bs-toggle="modal" data-bs-target="#yearlyReportModal">
                                    <i class="fas fa-chart-bar"></i> Yearly Report
                                </span>
                            </div>
                        </section>
                    </div>

                    <!-- GRAPH 1: SCHOOL (COMPACT SIZE - MATCHED COLORS) -->
                    <div class="col-lg-3 col-md-6">
                        <aside class="stage-panel h-100">
                            <div class="dashboard-nav-header mb-2">
                                <h4 style="font-size: 1rem;"><i class="fas fa-graduation-cap text-success me-1"></i>School Fee Breakdown</h4>
                                <p class="mb-0 small text-muted">For <?php echo date('F Y'); ?> Fee Status</p>
                            </div>
                            <div class="metric-card p-2">
                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: nowrap;">
                                    <div style="width: 90px; height: 90px; border-radius: 50%; background: conic-gradient(#3b82f6 0% <?php echo $school_paid_percentage; ?>%, #1f5f46 <?php echo $school_paid_percentage; ?>% 100%); position: relative; flex-shrink: 0;">
                                        <div style="position: absolute; inset: 10px; border-radius: 50%; background: #ffffff; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                                            <strong style="font-size: 16px; margin: 0; color: #13211a;"><?php echo $school_paid_percentage; ?>%</strong>
                                            <small style="margin: 0; color: #6c7a73; font-size: 8px;">Collected</small>
                                        </div>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div class="mb-1">
                                            <span style="font-size: 10px; color: #6c7a73; display: block;">Total Fee (<?php echo date('M Y'); ?>)</span>
                                            <strong style="font-size: 12px; color: #13211a;">Rs. <?php echo number_format($school_month_total, 0); ?></strong>
                                        </div>
                                        <div class="mb-1">
                                            <span style="font-size: 10px; color: #6c7a73; display: block;"><i class="fas fa-circle" style="color: #3b82f6; font-size: 7px;"></i> Received</span>
                                            <strong style="font-size: 12px; color: #3b82f6;">Rs. <?php echo number_format($school_month_collected, 0); ?></strong>
                                        </div>
                                        <div>
                                            <span style="font-size: 10px; color: #6c7a73; display: block;"><i class="fas fa-circle" style="color: #1f5f46; font-size: 7px;"></i> Pending</span>
                                            <strong style="font-size: 12px; color: #1f5f46;">Rs. <?php echo number_format($school_month_unpaid, 0); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>

                    <!-- GRAPH 2: COLLEGE (COMPACT SIZE - MATCHED THEME COLORS) -->
                    <div class="col-lg-3 col-md-6">
                        <aside class="stage-panel h-100">
                            <div class="dashboard-nav-header mb-2">
                                <h4 style="font-size: 1rem;"><i class="fas fa-university text-success me-1"></i>College Package Breakdown</h4>
                                <p class="mb-0 small text-muted">Session <?php echo $current_year_str; ?> Package Dues</p>
                            </div>
                            <div class="metric-card p-2">
                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: nowrap;">
                                    <div style="width: 90px; height: 90px; border-radius: 50%; background: conic-gradient(#3b82f6 0% <?php echo $college_paid_percentage; ?>%, #1f5f46 <?php echo $college_paid_percentage; ?>% 100%); position: relative; flex-shrink: 0;">
                                        <div style="position: absolute; inset: 10px; border-radius: 50%; background: #ffffff; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                                            <strong style="font-size: 16px; margin: 0; color: #13211a;"><?php echo $college_paid_percentage; ?>%</strong>
                                            <small style="margin: 0; color: #6c7a73; font-size: 8px;">Recovered</small>
                                        </div>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div class="mb-1">
                                            <span style="font-size: 10px; color: #6c7a73; display: block;">Total Package (<?php echo $current_year_str; ?>)</span>
                                            <strong style="font-size: 12px; color: #13211a;">Rs. <?php echo number_format($college_total_expected, 0); ?></strong>
                                        </div>
                                        <div class="mb-1">
                                            <span style="font-size: 10px; color: #6c7a73; display: block;"><i class="fas fa-circle" style="color: #3b82f6; font-size: 7px;"></i> Received</span>
                                            <strong style="font-size: 12px; color: #3b82f6;">Rs. <?php echo number_format($college_received_year, 0); ?></strong>
                                        </div>
                                        <div>
                                            <span style="font-size: 10px; color: #6c7a73; display: block;"><i class="fas fa-circle" style="color: #1f5f46; font-size: 7px;"></i> Remaining</span>
                                            <strong style="font-size: 12px; color: #1f5f46;">Rs. <?php echo number_format($college_pending_year, 0); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>

                <!-- STATISTICS CARDS GRID (ALL ICONS GREEN, EXACT SAME STYLING) -->
                <div class="stats-grid-container" style="width: 100%;">
                    <div class="dashboard-cards-grid">
                        
                        <!-- ROW 1: CARD 1 -> School Active Students -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                <i class="fas fa-child"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3><?php echo $school_total_students; ?></h3>
                                <p>School Active Students</p>
                            </div>
                        </div>

                        <!-- ROW 1: CARD 2 -> College Active Students -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3><?php echo $college_total_students; ?></h3>
                                <p>College Active Students</p>
                            </div>
                        </div>

                        <!-- ROW 1: CARD 3 -> Boys -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                <i class="fas fa-mars"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3><?php echo $total_boys; ?></h3>
                                <p>Boys (Sec B)</p>
                            </div>
                        </div>

                        <!-- ROW 1: CARD 4 -> Girls -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                <i class="fas fa-venus"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3><?php echo $total_girls; ?></h3>
                                <p>Girls (Sec G)</p>
                            </div>
                        </div>

                        <!-- ROW 2: CARD 5 -> School Paid Students (Monthly Filter Dropdown) -->
                        <div class="stat-card stat-card--dropdown" style="width: 100%; min-width: 0;">
                            <div class="stat-card__content-wrapper">
                                <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <div class="stat-content" style="min-width: 0;">
                                    <h3>
                                        <span id="paid_students_count"><?php echo $school_students_paid_current; ?></span>
                                        <span class="stat-percentage" id="paid_students_percentage">(<?php echo $school_current_paid_pct; ?>%)</span>
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

                        <!-- ROW 2: CARD 6 -> College Paid Students -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3>
                                    <?php echo $college_paid_students_count; ?>
                                    <span class="stat-percentage"> (<?php echo $college_paid_percentage_std; ?>%)</span>
                                </h3>
                                <p>College Paid (<?php echo $current_year_str; ?>)</p>
                            </div>
                        </div>

                        <!-- ROW 3: CARD 9 -> Today's Collection -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3 style="white-space: nowrap;">Rs. <?php echo number_format($today_collection, 0); ?></h3>
                                <p>Today's Collection</p>
                            </div>
                        </div>

                        <!-- ROW 3: CARD 10 -> This Month Collection -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3 style="white-space: nowrap;">Rs. <?php echo number_format($this_month_collection, 0); ?></h3>
                                <p>This Month Collection</p>
                            </div>
                        </div>

                        <!-- ROW 3: CARD 11 -> This Month Expenses -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3 style="white-space: nowrap;">Rs. <?php echo number_format($this_month_expenses, 0); ?></h3>
                                <p>This Month Expenses</p>
                            </div>
                        </div>

                        <!-- ROW 3: CARD 12 -> This Month Net Profit -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3 style="white-space: nowrap;">Rs. <?php echo number_format($this_month_net_profit, 0); ?></h3>
                                <p>This Month Profit</p>
                            </div>
                        </div>

                        <!-- ROW 4: CARD 13 -> This Month Fine -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3 style="white-space: nowrap;">Rs. <?php echo number_format($this_month_fine, 0); ?></h3>
                                <p>This Month Fine</p>
                            </div>
                        </div>

                        <!-- ROW 4: CARD 14 -> This Month Other Dues -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                <i class="fas fa-tags"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3 style="white-space: nowrap;">Rs. <?php echo number_format($this_month_other_fee, 0); ?></h3>
                                <p>This Month Other Dues</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Monthly Report Modal -->
    <div class="modal fade" id="monthlyReportModal" tabindex="-1" aria-labelledby="monthlyReportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="monthlyReportModalLabel"><i class="fas fa-calendar-alt"></i> Generate Monthly Report</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="monthly_report.php" method="GET" target="_blank" onsubmit="bootstrap.Modal.getInstance(document.getElementById('monthlyReportModal')).hide();">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="report_month" class="form-label text-dark fw-bold">Select Month & Year</label>
                            <input type="month" id="report_month" name="month" class="form-control" value="<?php echo date('Y-m'); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-print"></i> Generate & Print</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Yearly Report Modal -->
    <div class="modal fade" id="yearlyReportModal" tabindex="-1" aria-labelledby="yearlyReportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="yearlyReportModalLabel"><i class="fas fa-chart-bar"></i> Generate Yearly Report</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="yearlyReportForm" action="yearly_report.php" method="GET" target="_blank" onsubmit="submitYearlyReportForm(event)">
                    <div class="modal-body">
                        <label class="form-label d-block mb-3 text-dark fw-bold">Select Year(s) (Check multiple years to compare)</label>
                        <div class="row g-2">
                            <?php 
                            $curr_year = intval(date('Y'));
                            for ($y = $curr_year; $y >= $curr_year - 10; $y--): ?>
                                <div class="col-6 col-sm-4">
                                    <div class="form-check p-2 border rounded bg-light">
                                        <input class="form-check-input ms-1 me-2 year-checkbox" type="checkbox" value="<?php echo $y; ?>" id="year_<?php echo $y; ?>" <?php echo $y === $curr_year ? 'checked' : ''; ?>>
                                        <label class="form-check-label text-dark cursor-pointer" for="year_<?php echo $y; ?>">
                                            <?php echo $y; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="years" id="selected_years_hidden">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-print"></i> Generate & Print</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function submitYearlyReportForm(e) {
            const checkboxes = document.querySelectorAll('.year-checkbox:checked');
            if (checkboxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one year!');
                return;
            }
            const years = Array.from(checkboxes).map(cb => cb.value).join(',');
            document.getElementById('selected_years_hidden').value = years;
            bootstrap.Modal.getInstance(document.getElementById('yearlyReportModal')).hide();
        }

        // Paid Students Dynamic AJAX Dropdown Listener for School
        document.querySelectorAll('.month-select-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const selectedMonth = this.getAttribute('data-month');
                const displayLabel = this.getAttribute('data-label');

                fetch(`dashboard.php?ajax_action=get_paid_students&month=${encodeURIComponent(selectedMonth)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('paid_students_count').innerText = data.count;
                            document.getElementById('paid_students_percentage').innerText = `(${data.percentage}%)`;
                            document.getElementById('paid_students_label').innerText = `School Paid (${displayLabel})`;
                        }
                    })
                    .catch(err => console.error("Error fetching paid students:", err));
            });
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>