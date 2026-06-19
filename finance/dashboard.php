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

// Get Statistics for Finance view
$today_collection = get_daily_collection(date('Y-m-d'));
$total_payments = $conn->query("SELECT COUNT(*) as count FROM payments")->fetch_assoc()['count'];
$total_defaulters = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM fee_records WHERE status = 'unpaid'")->fetch_assoc()['count'];
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
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
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
                        <i class="fas fa-user-circle"></i> <?php echo get_username(); ?> 
                        <small>(Finance Clerk)</small>
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
                                <h4 style="color: white;"><?php echo get_username(); ?></h4>
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

                

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #f0f4ef;">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo format_currency($today_collection); ?></h3>
                            <p>Today's Collection</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea;">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $total_payments; ?></h3>
                            <p>Total Payments</p>
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
                
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h4>Quick Actions</h4>
                    <div class="actions-grid">
                        <a href="fee_payment.php" class="action-btn">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Record Payment</span>
                        </a>
                        <a href="student_record.php" class="action-btn">
                            <i class="fas fa-address-book"></i>
                            <span>Student Record</span>
                        </a>
                        
                        <a href="defaulter_list.php" class="action-btn">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Pending List</span>
                        </a>
                        <a href="payment_analytics.php" class="action-btn">
                            <i class="fas fa-chart-line"></i>
                            <span>Analytics</span>
                        </a>
                        <a href="expenses.php" class="action-btn">
                            <i class="fas fa-wallet"></i>
                            <span>Expenses</span>
                        </a>
                    </div>
                </div>
                
                
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
