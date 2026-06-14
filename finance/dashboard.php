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
                    <aside class="stage-panel stage-panel--welcome">
                        <div class="welcome-card">
                            <div class="welcome-card__header">
                                <div class="welcome-avatar">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <div>
                                    <span class="welcome-label">Welcome</span>
                                    <h4><?php echo get_username(); ?></h4>
                                    <p>Finance clerk active</p>
                                </div>
                            </div>

                            <p class="welcome-card__text">
                                Quick access to payments, defaulters, and daily collections. Use the buttons below to continue your work.
                            </p>

                            <div class="welcome-card__highlights">
                                <div>
                                    <span>Collected Today</span>
                                    <strong><?php echo format_currency($today_collection); ?></strong>
                                </div>
                                <div>
                                    <span>Total Payments</span>
                                    <strong><?php echo $total_payments; ?></strong>
                                </div>
                                <div>
                                    <span>Defaulters</span>
                                    <strong><?php echo $total_defaulters; ?></strong>
                                </div>
                            </div>

                            <div class="welcome-card__actions">
                                <a href="fee_payment.php" class="welcome-card__button welcome-card__button--solid">
                                    <i class="fas fa-bolt"></i>
                                    <span>Record Payment</span>
                                </a>
                                <a href="defaulter_list.php" class="welcome-card__button">
                                    <i class="fas fa-list-check"></i>
                                    <span>View Defaulters</span>
                                </a>
                            </div>
                        </div>
                    </aside>
                </div>

                <div class="dashboard-nav-panel">
                    <div class="dashboard-nav-header">
                        <h4>Finance Modules</h4>
                        <p>Use these buttons to move quickly between finance tools.</p>
                    </div>
                    <div class="dashboard-nav-grid dashboard-nav-grid--compact">
                        <a href="dashboard.php" class="dashboard-nav-btn active">
                            <i class="fas fa-chart-bar"></i>
                            <span>Dashboard</span>
                        </a>
                        <a href="fee_payment.php" class="dashboard-nav-btn">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Fee Payment</span>
                        </a>
                        <a href="defaulter_list.php" class="dashboard-nav-btn">
                            <i class="fas fa-list"></i>
                            <span>Defaulters</span>
                        </a>
                    </div>
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
                            <p>Students with Unpaid Fees</p>
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
                            <span>Defaulter List</span>
                        </a>
                    </div>
                </div>
                
                <!-- Recent Payments -->
                <div class="recent-section">
                    <h4>Recent Payments</h4>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Amount</th>
                                <th>For Month</th>
                                <th>Payment Date</th>
                                <th>Received By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = "SELECT p.*, s.name, s.class 
                                     FROM payments p 
                                     JOIN students s ON p.student_id = s.id 
                                     ORDER BY p.payment_date DESC LIMIT 10";
                            $result = $conn->query($query);
                            
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo '<tr>';
                                    echo '<td>' . $row['name'] . '</td>';
                                    echo '<td>' . $row['class'] . '</td>';
                                    echo '<td>' . format_currency($row['amount']) . '</td>';
                                    echo '<td>' . $row['paid_for_month'] . '</td>';
                                    echo '<td>' . format_datetime($row['payment_date']) . '</td>';
                                    echo '<td>' . $row['received_by'] . '</td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="6" class="text-center">No payments yet</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
