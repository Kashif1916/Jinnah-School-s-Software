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
                <div class="topbar-left">
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
                <div class="dashboard-stage dashboard-stage--single">
                    <aside class="stage-panel stage-panel--welcome" style="background: linear-gradient(135deg, #163325 0%, #1f5f46 100%); color: white; border-radius: 12px;">
                        <div class="welcome-card">
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
                                Quick access to payments, defaulters, and daily collections. Use the buttons below to continue your work.
                            </p>

                            

                            <div class="welcome-card__actions">
                                <a href="fee_payment.php" class="welcome-card__button welcome-card__button--solid" style="background: white; color: #1f5f46;">
                                    <i class="fas fa-bolt"></i>
                                    <span>Record Payment</span>
                                </a>
                                <a href="defaulter_list.php" class="welcome-card__button" style="border-color: white; color: white;">
                                    <i class="fas fa-list-check"></i>
                                    <span>View Pending List</span>
                                </a>
                            </div>
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
                        <a href="dashboard.php" class="action-btn">
                            <i class="fas fa-chart-bar"></i>
                            <span>Dashboard</span>
                        </a>
                        <a href="defaulter_list.php" class="action-btn">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Pending List</span>
                        </a>
                        <a href="payment_analytics.php" class="action-btn">
                            <i class="fas fa-chart-line"></i>
                            <span>Analytics</span>
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
