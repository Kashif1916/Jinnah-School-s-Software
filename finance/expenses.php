<?php
/**
 * Expenses Management - Finance Module
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_finance();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_expense') {
        $amount = floatval($_POST['amount'] ?? 0);
        $reason = sanitize_input($_POST['reason'] ?? '');
        $user_id = get_user_id();
        $username = get_username();

        if ($amount <= 0) {
            $error = 'Amount must be greater than zero.';
        } elseif (empty($reason)) {
            $error = 'Please provide a reason for the expense.';
        } else {
            $stmt = $conn->prepare("INSERT INTO expenses (amount, reason, user_id, username) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("dsis", $amount, $reason, $user_id, $username);
            if ($stmt->execute()) {
                $success = 'Expense of ' . format_currency($amount) . ' recorded successfully.';
            } else {
                $error = 'Failed to record expense: ' . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Fetch all expenses
$expenses = [];
$result = $conn->query("SELECT * FROM expenses ORDER BY created_at DESC, id DESC");
if ($result) {
    $expenses = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses Management - <?php echo SITE_NAME; ?></title>
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
                        <h2>Expenses</h2>
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
                        <a href="expenses.php" class="module-nav-btn active">
                            <i class="fas fa-wallet"></i> Expenses
                        </a>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- Form Section -->
                    <div class="col-lg-4">
                        <div class="analytics-section">
                            <h4>Record New Expense</h4>
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="action" value="add_expense">
                                
                                <div class="mb-3">
                                    <label for="amount" class="form-label">Expense Amount (Rs.) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rs.</span>
                                        <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" required placeholder="0.00">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="reason" class="form-label">Expense Reason / Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="reason" name="reason" rows="3" required placeholder="Write the reason for expense..."></textarea>
                                </div>
                                
                                <button type="submit" class="btn-primary w-100 mt-2">
                                    <i class="fas fa-save"></i> Save Expense
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- List Section -->
                    <div class="col-lg-8">
                        <div class="analytics-section">
                            <h4>Recent Expenses Record</h4>
                            <div class="table-responsive mt-3">
                                <?php if (count($expenses) > 0): ?>
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Amount</th>
                                                <th>Reason</th>
                                                <th>Recorded By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($expenses as $expense): ?>
                                                <tr>
                                                    <td>
                                                        <span class="text-muted small">
                                                            <i class="far fa-calendar-alt me-1"></i>
                                                            <?php echo date('d-m-Y', strtotime($expense['created_at'])); ?>
                                                        </span>
                                                        <br>
                                                        <span class="text-muted small">
                                                            <i class="far fa-clock me-1"></i>
                                                            <?php echo date('h:i A', strtotime($expense['created_at'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <strong class="text-danger">
                                                            - <?php echo format_currency($expense['amount']); ?>
                                                        </strong>
                                                    </td>
                                                    <td>
                                                        <div class="text-wrap" style="max-width: 300px;">
                                                            <?php echo htmlspecialchars($expense['reason']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-user me-1"></i>
                                                            <?php echo htmlspecialchars($expense['username']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <div class="alert alert-info py-3 mb-0">
                                        <i class="fas fa-info-circle me-2"></i> No expenses recorded yet.
                                    </div>
                                <?php endif; ?>
                            </div>
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
