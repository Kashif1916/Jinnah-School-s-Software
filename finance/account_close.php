<?php
/**
 * Account Close Management - Finance Panel
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_finance();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_account'])) {
    $user_id = get_user_id();
    $next_midnight = date('Y-m-d 00:00:00', strtotime('tomorrow'));
    
    $query = "UPDATE users SET is_frozen = 1, frozen_until = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('si', $next_midnight, $user_id);
    if ($stmt->execute()) {
        $stmt->close();
        session_destroy();
        header('Location: ../login.php?error=closed');
        exit();
    } else {
        $error = 'Failed to close account: ' . $conn->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Close Account - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <a href="dashboard.php"><?php echo render_system_logo('topbar-logo'); ?></a>
                    <div class="panel-brand">
                        <h2>Close Account</h2>
                        <span>Finance / Clerk Panel</span>
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
                        <a href="add_student.php" class="module-nav-btn ">
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
                        <a href="payment_analytics.php" class="module-nav-btn">
                            <i class="fas fa-chart-line"></i> Analytics
                        </a>
                        <a href="expenses.php" class="module-nav-btn">
                            <i class="fas fa-wallet"></i> Expenses
                        </a>
                        <a href="account_close.php" class="module-nav-btn active">
                            <i class="fas fa-lock"></i> Close Account
                        </a>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div style="max-width: 650px; margin: 40px auto;">
                    <div class="analytics-section text-center p-5 shadow-lg border-0" style="background: rgba(255,255,255,0.9); border-radius: 15px;">
                        <div class="mb-4">
                            <i class="fas fa-user-shield text-danger" style="font-size: 70px;"></i>
                        </div>
                        <h3 class="mb-3" style="color: #2c3e50; font-weight: 700;">Account Close for Today</h3>
                        
                        <div class="alert alert-warning text-start mb-4" style="line-height: 1.6; border-left: 5px solid #f39c12; background-color: #fef9e7;">
                            <h5 class="alert-heading fw-bold"><i class="fas fa-exclamation-triangle"></i> Important Notice:</h5>
                            <p class="mb-0">
                                Once you close your account for today:
                                <ul class="mt-2 mb-0">
                                    <li>You will be automatically logged out of the system.</li>
                                    <li>Your account will be <strong>frozen</strong> and you will be blocked from logging back in.</li>
                                    <li>Re-activation is automatic at <strong>12:00 AM (midnight)</strong> of the next day.</li>
                                    <li>The Principal (Master) can also manually unfreeze your account at any time if required.</li>
                                </ul>
                            </p>
                        </div>
                        
                        <form method="POST" onsubmit="return confirm('Are you sure you want to close your account for today? You will be logged out immediately and blocked until tomorrow.')">
                            <button type="submit" name="close_account" class="btn btn-danger btn-lg px-5 py-3 fw-bold" style="border-radius: 30px; box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);">
                                <i class="fas fa-lock me-2"></i> Confirm Account Close
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
