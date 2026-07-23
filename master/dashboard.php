<?php
/**
 * Master Dashboard
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master();

// Get Statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'active'")->fetch_assoc()['count'];
$paid_students = $conn->query("SELECT COUNT(DISTINCT fr.student_id) as count FROM fee_records fr JOIN students s ON s.id = fr.student_id WHERE fr.status = 'paid' AND s.status = 'active'")->fetch_assoc()['count'] ?? 0;
$remaining_students = max(0, $total_students - $paid_students);
$paid_percentage = $total_students > 0 ? round(($paid_students / $total_students) * 100) : 0;
$remaining_percentage = 100 - $paid_percentage;
$total_unpaid = $conn->query("SELECT SUM(amount) as total FROM fee_records WHERE status = 'unpaid'")->fetch_assoc()['total'] ?? 0;

// NEW QUERIES: Fetch count for Section B (Boys) and Section G (Girls) for active students
$total_boys = $conn->query("SELECT COUNT(*) as count FROM students WHERE section = 'B' AND status = 'active'")->fetch_assoc()['count'] ?? 0;
$total_girls = $conn->query("SELECT COUNT(*) as count FROM students WHERE section = 'G' AND status = 'active'")->fetch_assoc()['count'] ?? 0;

$today_collection = get_daily_collection(date('Y-m-d'));

// Fetch Monthly Stats for Current Month
$start_of_month = date('Y-m-01 00:00:00');
$end_of_month = date('Y-m-t 23:59:59');

$this_month_collection = 0.00;
$month_coll_res = $conn->query("SELECT SUM(amount) as total FROM payments WHERE payment_date >= '$start_of_month' AND payment_date <= '$end_of_month'");
if ($month_coll_res) {
    $this_month_collection = floatval($month_coll_res->fetch_assoc()['total'] ?? 0);
}

// FETCH THIS MONTH UNPAID/PENDING FEES FOR GRAPH
$this_month_unpaid = 0.00;
$month_unpaid_res = $conn->query("SELECT SUM(amount) as total FROM fee_records WHERE status = 'unpaid' AND (created_at >= '$start_of_month' AND created_at <= '$end_of_month')");
if ($month_unpaid_res) {
    $this_month_unpaid = floatval($month_unpaid_res->fetch_assoc()['total'] ?? 0);
}

// Calculations for Month Fee Breakdown
$this_month_total_expected = $this_month_collection + $this_month_unpaid;
$month_paid_percentage = $this_month_total_expected > 0 ? round(($this_month_collection / $this_month_total_expected) * 100) : 0;
$month_remaining_percentage = 100 - $month_paid_percentage;

$this_month_expenses = 0.00;
$month_exp_res = $conn->query("SELECT SUM(amount) as total FROM expenses WHERE created_at >= '$start_of_month' AND created_at <= '$end_of_month'");
if ($month_exp_res) {
    $this_month_expenses = floatval($month_exp_res->fetch_assoc()['total'] ?? 0);
}

$this_month_net_profit = $this_month_collection - $this_month_expenses;
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
</head>
<body>
    <div class="wrapper dashboard-shell">
        <!-- Main Content -->
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
                        <a href="payment_analytics.php" class="module-nav-btn">
                            <i class="fas fa-chart-line"></i> Analytics
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
                        <a href="users.php" class="module-nav-btn">
                            <i class="fas fa-users-cog"></i> Users
                        </a>
                        <a href="receipt_note.php" class="module-nav-btn">
                            <i class="fas fa-sticky-note"></i> Receipt Note
                        </a>
                    </div>
                </div>

                <div class="dashboard-stage">
                    <section class="stage-panel stage-panel--hero">
                        <span class="dashboard-kicker"><i class="fas fa-school"></i> Principal Dashboard</span>
                        <h3>Control the school finance system from one place.</h3>
                        <p>Track students, manage fees, review pending payments, and keep promotion work moving with quick access to every major module.</p>
                        <div class="hero-row">
                            <span class="hero-tag"><i class="fas fa-shield-alt"></i> Principal access only</span>
                            <a href="backup.php" class="hero-tag" style="text-decoration:none; color:inherit;">
                                <i class="fas fa-database"></i> Backup System
                            </a>
                            <span class="hero-tag" role="button" style="cursor:pointer;" data-bs-toggle="modal" data-bs-target="#monthlyReportModal">
                             <i class="fas fa-calendar-alt"></i> Monthly Report
                             </span>
        
                                 <!-- Yearly Report Span -->
                                 <span class="hero-tag" role="button" style="cursor:pointer;" data-bs-toggle="modal" data-bs-target="#yearlyReportModal">
                                      <i class="fas fa-chart-bar"></i> Yearly Report
                                 </span>
                        </div>
                    </section>

                    <aside class="stage-panel">
                        <div class="dashboard-nav-header">
                            <h4>Collected vs Pending Fees</h4>
                            <p>For the current month.</p>
                        </div>
                        <div class="metric-card" style="padding: 18px;">
                            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                                <!-- Conic Gradient Donut Chart (Blue = Collected, Theme Green = Pending) -->
                                <div style="width: 140px; height: 140px; border-radius: 50%; background: conic-gradient(#3b82f6 0% <?php echo $month_paid_percentage; ?>%, #1f5f46 <?php echo $month_paid_percentage; ?>% 100%); position: relative; flex-shrink: 0;">
                                    <div style="position: absolute; inset: 16px; border-radius: 50%; background: #ffffff; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                                        <strong style="font-size: 24px; margin: 0; color: #13211a;"><?php echo $month_paid_percentage; ?>%</strong>
                                        <small style="margin: 0; color: #6c7a73; font-size: 11px;">Collected</small>
                                    </div>
                                </div>
                                
                                <!-- Text Data (Right Side) -->
                                <div style="flex: 1; min-width: 180px;">
                                    <!-- Total Collection -->
                                    <div style="margin-bottom: 12px;">
                                        <span style="font-size: 12px; color: #6c7a73; display: block; font-weight: 500;">Total Collection of This Month</span>
                                        <strong style="font-size: 15px; color: #13211a;"><?php echo format_currency(round($this_month_total_expected)); ?></strong>
                                    </div>

                                    <!-- Total Received Collection -->
                                    <div style="margin-bottom: 12px;">
                                        <span style="font-size: 12px; color: #6c7a73; display: block; font-weight: 500;"><i class="fas fa-circle" style="color: #3b82f6; font-size: 9px; margin-right: 4px;"></i> Total Received Collection of This Month</span>
                                        <strong style="font-size: 15px; color: #3b82f6;"><?php echo format_currency(round($this_month_collection)); ?></strong>
                                    </div>

                                    <!-- Remaining Collection -->
                                    <div>
                                        <span style="font-size: 12px; color: #6c7a73; display: block; font-weight: 500;"><i class="fas fa-circle" style="color: #1f5f46; font-size: 9px; margin-right: 4px;"></i> Remaining Collection of This Month</span>
                                        <strong style="font-size: 15px; color: #1f5f46;"><?php echo format_currency(round($this_month_unpaid)); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid-container" style="width: 100%;">
                    <!-- 4 Columns Grid Layout for both rows -->
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; width: 100%;">
                        
                        <!-- CARD 1: Active Students -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #e3f1ea;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3><?php echo intval($total_students); ?></h3>
                                <p>Active Students</p>
                            </div>
                        </div>

                        <!-- CARD 2: Boys -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                <i class="fas fa-mars"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3><?php echo intval($total_boys); ?></h3>
                                <p>Boys (Sec B)</p>
                            </div>
                        </div>

                        <!-- CARD 3: Girls -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                <i class="fas fa-venus"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3><?php echo intval($total_girls); ?></h3>
                                <p>Girls (Sec G)</p>
                            </div>
                        </div>

                        <!-- CARD 4: Today's Collection -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #f0f4ef;">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3 style="white-space: nowrap;"><?php echo format_currency(round($today_collection)); ?></h3>
                                <p>Today's Collection</p>
                            </div>
                        </div>

                        <!-- CARD 5 (Row 2): This Month Collection - Placed under Card 1 -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3 style="white-space: nowrap;"><?php echo format_currency(round($this_month_collection)); ?></h3>
                                <p>This Month Collection</p>
                            </div>
                        </div>

                        <!-- CARD 6 (Row 2): This Month Expenses - Placed under Card 2 -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3 style="white-space: nowrap;"><?php echo format_currency(round($this_month_expenses)); ?></h3>
                                <p>This Month Expenses</p>
                            </div>
                        </div>

                        <!-- CARD 7 (Row 2): This Month Profit - Placed under Card 3 -->
                        <div class="stat-card" style="width: 100%; min-width: 0;">
                            <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-content" style="min-width: 0;">
                                <h3 style="white-space: nowrap;"><?php echo format_currency(round($this_month_net_profit)); ?></h3>
                                <p>This Month Profit</p>
                            </div>
                        </div>

                        <!-- Empty Slot under Card 4 (Keeps layout perfect) -->
                        <div></div>

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
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>