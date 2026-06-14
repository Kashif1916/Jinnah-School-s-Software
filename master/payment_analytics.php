<?php
/**
 * Payment Analytics
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master();

$filter_date = isset($_GET['date']) ? sanitize_input($_GET['date']) : date('Y-m-d');
$filter_month = isset($_GET['month']) ? sanitize_input($_GET['month']) : date('Y-m');

// Get daily collection
$daily_collection = get_daily_collection($filter_date);

// Get all payments for the day
$query = "SELECT p.*, s.name, s.class FROM payments p 
         JOIN students s ON p.student_id = s.id 
         WHERE DATE(p.payment_date) = ? 
         ORDER BY p.payment_date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $filter_date);
$stmt->execute();
$daily_payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get monthly collection and summary
$months_data = [];
$query = "SELECT DATE_FORMAT(payment_date, '%b-%Y') as month_year, SUM(amount) as total, COUNT(*) as count 
         FROM payments 
         GROUP BY DATE_FORMAT(payment_date, '%b-%Y')
         ORDER BY DATE_FORMAT(payment_date, '%Y-%m') DESC
         LIMIT 12";
$result = $conn->query($query);
if ($result) {
    $months_data = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Analytics - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <div class="topbar">
                <div class="topbar-left">
                    <div class="panel-brand">
                        <h2>Payment Analytics</h2>
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

            <div class="content">
                <div class="module-nav-panel">
                    <div class="module-nav-row">
                        <a href="dashboard.php" class="module-nav-btn">
                            <i class="fas fa-chart-bar"></i> Dashboard
                        </a>
                        <a href="add_student.php" class="module-nav-btn">
                            <i class="fas fa-user-plus"></i> Add Student
                        </a>
                        <a href="edit_student.php" class="module-nav-btn">
                            <i class="fas fa-user-edit"></i> Edit Student
                        </a>
                        <a href="fee_management.php" class="module-nav-btn">
                            <i class="fas fa-money-bill-wave"></i> Fee Management
                        </a>
                        <a href="defaulter_list.php" class="module-nav-btn">
                            <i class="fas fa-list"></i> Defaulters
                        </a>
                        <a href="payment_analytics.php" class="module-nav-btn active">
                            <i class="fas fa-chart-line"></i> Analytics
                        </a>
                        <a href="promotion.php" class="module-nav-btn">
                            <i class="fas fa-arrow-up"></i> Promotion
                        </a>
                        <a href="drop_student.php" class="module-nav-btn">
                            <i class="fas fa-trash"></i> Drop Student
                        </a>
                    </div>
                </div>

                <!-- Summary Cards -->
                
                <!-- Filter and Daily Collection -->
                <div class="analytics-section">
                    <div class="section-header">
                        <h4>Daily Collection</h4>
                        <div class="filter-controls">
                            <form method="GET" class="date-filter-form">
                                <input type="date" name="date" value="<?php echo $filter_date; ?>" class="form-control">
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <?php if (count($daily_payments) > 0): ?>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Class</th>
                                    <th>Amount</th>
                                    <th>For Month</th>
                                    <th>Payment Time</th>
                                    <th>Received By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($daily_payments as $payment): ?>
                                    <tr>
                                        <td><?php echo $payment['name']; ?></td>
                                        <td><?php echo $payment['class']; ?></td>
                                        <td><strong><?php echo format_currency($payment['amount']); ?></strong></td>
                                        <td><?php echo $payment['paid_for_month']; ?></td>
                                        <td><?php echo date('H:i', strtotime($payment['payment_date'])); ?></td>
                                        <td><?php echo $payment['received_by']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No payments on this date
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Monthly Summary -->
                <div class="analytics-section">
                    <h4>Monthly Collection Summary (Last 12 Months)</h4>
                    
                    <?php if (count($months_data) > 0): ?>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Total Collection</th>
                                    <th>Number of Payments</th>
                                    <th>Average Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($months_data as $month): ?>
                                    <tr>
                                        <td><strong><?php echo $month['month_year']; ?></strong></td>
                                        <td><?php echo format_currency($month['total']); ?></td>
                                        <td><?php echo $month['count']; ?></td>
                                        <td><?php echo format_currency($month['total'] / $month['count']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No payment data available
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
