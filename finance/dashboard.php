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

// Active students count (Excluding Passed Out Classes)
$total_students_query = "SELECT COUNT(*) as count FROM students WHERE status = 'active' AND class NOT IN ('Passed-10', 'Passed-12')";
$total_students = $conn->query($total_students_query)->fetch_assoc()['count'] ?? 0;

// Section B (Boys) Count
$total_boys_query = "SELECT COUNT(*) as count FROM students WHERE status = 'active' AND section = 'B' AND class NOT IN ('Passed-10', 'Passed-12')";
$total_boys = $conn->query($total_boys_query)->fetch_assoc()['count'] ?? 0;

// Section G (Girls) Count
$total_girls_query = "SELECT COUNT(*) as count FROM students WHERE status = 'active' AND section = 'G' AND class NOT IN ('Passed-10', 'Passed-12')";
$total_girls = $conn->query($total_girls_query)->fetch_assoc()['count'] ?? 0;

// Handle AJAX Request for Paid Students Filter
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_paid_students') {
    header('Content-Type: application/json');
    $selected_month = sanitize_input($_GET['month'] ?? date('M-Y'));
    
    // Total Paid Count
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT student_id) as count FROM fee_records WHERE month = ? AND status = 'paid'");
    $stmt->bind_param('s', $selected_month);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
    $stmt->close();

    // Boys Paid Count (Section B)
    $stmt_b = $conn->prepare("SELECT COUNT(DISTINCT f.student_id) as count FROM fee_records f JOIN students s ON f.student_id = s.id WHERE f.month = ? AND f.status = 'paid' AND s.section = 'B' AND s.status = 'active' AND s.class NOT IN ('Passed-10', 'Passed-12')");
    $stmt_b->bind_param('s', $selected_month);
    $stmt_b->execute();
    $boys_count = $stmt_b->get_result()->fetch_assoc()['count'] ?? 0;
    $stmt_b->close();

    // Girls Paid Count (Section G)
    $stmt_g = $conn->prepare("SELECT COUNT(DISTINCT f.student_id) as count FROM fee_records f JOIN students s ON f.student_id = s.id WHERE f.month = ? AND f.status = 'paid' AND s.section = 'G' AND s.status = 'active' AND s.class NOT IN ('Passed-10', 'Passed-12')");
    $stmt_g->bind_param('s', $selected_month);
    $stmt_g->execute();
    $girls_count = $stmt_g->get_result()->fetch_assoc()['count'] ?? 0;
    $stmt_g->close();

    // Calculate Percentages
    $percentage = ($total_students > 0) ? round(($count / $total_students) * 100, 1) : 0;
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

// Logged-in user ka username aur aaj ki date nikalna
$current_user = get_username();
$today_date = date('Y-m-d');

// 1. Filtered Today's Collection
$stmt_coll = $conn->prepare("SELECT SUM(amount) as total FROM payments WHERE received_by = ? AND DATE(payment_date) = ?");
$stmt_coll->bind_param('ss', $current_user, $today_date);
$stmt_coll->execute();
$today_collection = $stmt_coll->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_coll->close();

// 2. Today's Total Receipts Count
$stmt_rec = $conn->prepare("SELECT COUNT(DISTINCT payment_date) as count FROM payments WHERE received_by = ? AND DATE(payment_date) = ?");
$stmt_rec->bind_param('ss', $current_user, $today_date);
$stmt_rec->execute();
$today_receipts = $stmt_rec->get_result()->fetch_assoc()['count'] ?? 0;
$stmt_rec->close();

// 3. Default Current Month Paid Students
$current_month_str = date('M-Y');
$stmt_paid_curr = $conn->prepare("SELECT COUNT(DISTINCT student_id) as count FROM fee_records WHERE month = ? AND status = 'paid'");
$stmt_paid_curr->bind_param('s', $current_month_str);
$stmt_paid_curr->execute();
$total_students_paid_current = $stmt_paid_curr->get_result()->fetch_assoc()['count'] ?? 0;
$stmt_paid_curr->close();

// Boys Paid Current Month
$stmt_b_curr = $conn->prepare("SELECT COUNT(DISTINCT f.student_id) as count FROM fee_records f JOIN students s ON f.student_id = s.id WHERE f.month = ? AND f.status = 'paid' AND s.section = 'B' AND s.status = 'active' AND s.class NOT IN ('Passed-10', 'Passed-12')");
$stmt_b_curr->bind_param('s', $current_month_str);
$stmt_b_curr->execute();
$boys_paid_current = $stmt_b_curr->get_result()->fetch_assoc()['count'] ?? 0;
$stmt_b_curr->close();

// Girls Paid Current Month
$stmt_g_curr = $conn->prepare("SELECT COUNT(DISTINCT f.student_id) as count FROM fee_records f JOIN students s ON f.student_id = s.id WHERE f.month = ? AND f.status = 'paid' AND s.section = 'G' AND s.status = 'active' AND s.class NOT IN ('Passed-10', 'Passed-12')");
$stmt_g_curr->bind_param('s', $current_month_str);
$stmt_g_curr->execute();
$girls_paid_current = $stmt_g_curr->get_result()->fetch_assoc()['count'] ?? 0;
$stmt_g_curr->close();

// Calculate Current Month Percentages
$current_paid_percentage = ($total_students > 0) ? round(($total_students_paid_current / $total_students) * 100, 1) : 0;
$current_boys_percentage = ($total_boys > 0) ? round(($boys_paid_current / $total_boys) * 100, 1) : 0;
$current_girls_percentage = ($total_girls > 0) ? round(($girls_paid_current / $total_girls) * 100, 1) : 0;

// Dynamic Month List (Current Month + Previous 11 Months)
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
            gap: 15px;
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
            font-size: 1rem;
            font-weight: 600;
            color: #198754;
            margin-left: 6px;
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
                    <!-- Row 1: Block 1 -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea;">
                            <i class="fas fa-user-graduate" style="color: #198754;"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $total_students; ?></h3>
                            <p>Total Active Students</p>
                        </div>
                    </div>

                    <!-- Row 1: Block 2 -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea;">
                            <i class="fas fa-mars" style="color: #198754;"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $total_boys; ?></h3>
                            <p>Boys (Section B)</p>
                        </div>
                    </div>

                    <!-- Row 1: Block 3 -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea;">
                            <i class="fas fa-venus" style="color: #198754;"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $total_girls; ?></h3>
                            <p>Girls (Section G)</p>
                        </div>
                    </div>

                    <!-- Row 1: Block 4 -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea;">
                            <i class="fas fa-receipt" style="color: #198754;"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $today_receipts; ?></h3>
                            <p>Today's Total Receipts</p>
                        </div>
                    </div>

                    <!-- Row 2: Block 5 -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea;">
                            <i class="fas fa-calendar-day" style="color: #198754;"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo format_currency($today_collection); ?></h3>
                            <p>My Today's Collection</p>
                        </div>
                    </div>

                    <!-- Row 2: Block 6 (Boys Paid Card) -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea;">
                            <i class="fas fa-mars" style="color: #198754;"></i>
                        </div>
                        <div class="stat-content">
                            <h3>
                                <span id="boys_paid_count"><?php echo $boys_paid_current; ?></span>
                                <span class="stat-percentage" id="boys_paid_percentage">(<?php echo $current_boys_percentage; ?>%)</span>
                            </h3>
                            <p id="boys_paid_label" class="mb-0">Boys Paid (<?php echo date('M Y'); ?>)</p>
                        </div>
                    </div>

                    <!-- Row 2: Block 7 (Girls Paid Card) -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea;">
                            <i class="fas fa-venus" style="color: #198754;"></i>
                        </div>
                        <div class="stat-content">
                            <h3>
                                <span id="girls_paid_count"><?php echo $girls_paid_current; ?></span>
                                <span class="stat-percentage" id="girls_paid_percentage">(<?php echo $current_girls_percentage; ?>%)</span>
                            </h3>
                            <p id="girls_paid_label" class="mb-0">Girls Paid (<?php echo date('M Y'); ?>)</p>
                        </div>
                    </div>

                    <!-- Row 2: Block 8 (Total Paid Students with Dropdown Filter) -->
                    <div class="stat-card stat-card--dropdown">
                        <div class="stat-card__content-wrapper">
                            <div class="stat-icon" style="background: #e3f1ea;">
                                <i class="fas fa-user-check" style="color: #198754;"></i>
                            </div>
                            <div class="stat-content">
                                <h3>
                                    <span id="paid_students_count"><?php echo $total_students_paid_current; ?></span>
                                    <span class="stat-percentage" id="paid_students_percentage">(<?php echo $current_paid_percentage; ?>%)</span>
                                </h3>
                                <p id="paid_students_label" class="mb-0">Students Paid (<?php echo date('M Y'); ?>)</p>
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
                            // Update Overall Paid Card
                            document.getElementById('paid_students_count').innerText = data.count;
                            document.getElementById('paid_students_percentage').innerText = `(${data.percentage}%)`;
                            document.getElementById('paid_students_label').innerText = `Students Paid (${displayLabel})`;

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