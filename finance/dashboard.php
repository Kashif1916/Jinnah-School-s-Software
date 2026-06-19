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

// Logged-in user ka username aur aaj ki date nikalna
$current_user = get_username();
$today_date = date('Y-m-d');

// 1. Filtered Today's Collection: Aaj ki date aur sirf is specific clerk ne jo amount receive kiya ho
$stmt_coll = $conn->prepare("SELECT SUM(amount) as total FROM payments WHERE received_by = ? AND DATE(payment_date) = ?");
$stmt_coll->bind_param('ss', $current_user, $today_date);
$stmt_coll->execute();
$today_collection = $stmt_coll->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_coll->close();

// 2. Today's Total Receipts Count: Aaj is clerk ne total kitni receipts/transactions handle keen
$stmt_rec = $conn->prepare("SELECT COUNT(*) as count FROM payments WHERE received_by = ? AND DATE(payment_date) = ?");
$stmt_rec->bind_param('ss', $current_user, $today_date);
$stmt_rec->execute();
$today_receipts = $stmt_rec->get_result()->fetch_assoc()['count'] ?? 0;
$stmt_rec->close();

// 3. Total Students Paid: Un unique students ka count jinki payment ho chuki hai
$total_students_paid = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM payments")->fetch_assoc()['count'] ?? 0;

// 4. Total Defaulters (Sahi count pure school ka unpaid status ke mutabik)
$total_defaulters = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM fee_records WHERE status = 'unpaid'")->fetch_assoc()['count'] ?? 0;
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
                        <a href="student_record.php" class="module-nav-btn ">
                            <i class="fas fa-address-book"></i> Student Record
                        </a>
                        <a href="fee_payment.php" class="module-nav-btn">
                            <i class="fas fa-money-bill-wave"></i> Fee Payment
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
                    </div>
                </div>
                <div class="dashboard-stage dashboard-stage--single">
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

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea;">
                            <i class="fas fa-calendar-day" style="color: #198754;"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo format_currency($today_collection); ?></h3>
                            <p>My Today's Collection</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea;">
                            <i class="fas fa-receipt" style="color: #198754;"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $today_receipts; ?></h3>
                            <p>Today's Total Receipts</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea;">
                            <i class="fas fa-user-check" style="color: #198754;"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $total_students_paid; ?></h3>
                            <p>Total Students Paid</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #edf4f0;">
                            <i class="fas fa-circle-exclamation"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $total_defaulters; ?></h3>
                            <p>Students with Pending Fees</p>
                        </div>
                    </div>
                </div>
                
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>