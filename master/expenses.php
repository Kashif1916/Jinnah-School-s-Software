<?php
/**
 * Expenses Management - Master Panel
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master();

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

// Fetch Master and Finance users for filter dropdown
$filter_users = [];
$user_query = "SELECT id, username, role FROM users WHERE role IN ('master', 'finance') ORDER BY username ASC";
$user_result = $conn->query($user_query);
if ($user_result) {
    $filter_users = $user_result->fetch_all(MYSQLI_ASSOC);
}

// Fetch filter inputs
$start_date = sanitize_input($_GET['start_date'] ?? '');
$end_date = sanitize_input($_GET['end_date'] ?? '');
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Check if user has applied any filter
$is_filtered = (!empty($start_date) || !empty($end_date) || $selected_user_id > 0);

// Pagination Configuration (Only applies when NO filter is active)
$limit = 20; // Default items per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 1. Get Total Count for UI Display & Pagination Calculation
$count_query = "SELECT COUNT(*) as total FROM expenses WHERE 1=1";
$count_params = [];
$count_types = '';

if (!empty($start_date) && empty($end_date)) {
    $count_query .= " AND DATE(created_at) = ?";
    $count_params[] = $start_date;
    $count_types .= 's';
} elseif (!empty($start_date) && !empty($end_date)) {
    $count_query .= " AND DATE(created_at) BETWEEN ? AND ?";
    $count_params[] = $start_date;
    $count_params[] = $end_date;
    $count_types .= 'ss';
} elseif (empty($start_date) && !empty($end_date)) {
    $count_query .= " AND DATE(created_at) <= ?";
    $count_params[] = $end_date;
    $count_types .= 's';
}

if ($selected_user_id > 0) {
    $count_query .= " AND user_id = ?";
    $count_params[] = $selected_user_id;
    $count_types .= 'i';
}

$stmt_count = $conn->prepare($count_query);
if (!empty($count_params)) {
    $stmt_count->bind_param($count_types, ...$count_params);
}
$stmt_count->execute();
$total_expenses = $stmt_count->get_result()->fetch_assoc()['total'];
$stmt_count->close();

$total_pages = ceil($total_expenses / $limit);

// 2. Fetch Expenses Data Query
$expenses = [];
$query = "SELECT * FROM expenses WHERE 1=1";
$params = [];
$param_types = "";

if (!empty($start_date) && empty($end_date)) {
    $query .= " AND DATE(created_at) = ?";
    $params[] = $start_date;
    $param_types .= "s";
} elseif (!empty($start_date) && !empty($end_date)) {
    $query .= " AND DATE(created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $param_types .= "ss";
} elseif (empty($start_date) && !empty($end_date)) {
    $query .= " AND DATE(created_at) <= ?";
    $params[] = $end_date;
    $param_types .= "s";
}

if ($selected_user_id > 0) {
    $query .= " AND user_id = ?";
    $params[] = $selected_user_id;
    $param_types .= "i";
}

$query .= " ORDER BY created_at DESC, id DESC";

// ONLY APPLY LIMIT 20 IF NO FILTER IS ACTIVE
if (!$is_filtered) {
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $param_types .= 'ii';
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $expenses = $result->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();
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
                        <span>Principal Panel</span>
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
                        <a href="expenses.php" class="module-nav-btn active">
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
                        <a href="delete_student.php" class="module-nav-btn ">
                            <i class="fas fa-user-minus text-success"></i> Delete Student
                        </a>
                        <a href="users.php" class="module-nav-btn">
                            <i class="fas fa-users-cog"></i> Users
                        </a>
                        <a href="receipt_note.php" class="module-nav-btn">
                            <i class="fas fa-sticky-note"></i> Receipt Note
                        </a>
                        <a href="../help.php" class="module-nav-btn">
                            <i class="fas fa-question-circle text-success"></i> Help & About
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
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h4>Recent Expenses Record (Total: <?php echo $total_expenses; ?>)</h4>
                            </div>
                            
                            <!-- Search Form -->
                            <form method="GET" class="row g-2 mb-3 align-items-end mt-2">
                                <div class="col-sm-3">
                                    <label for="start_date" class="form-label small mb-1" style="font-size: 12px; color: #555;">From Date</label>
                                    <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                                </div>
                                <div class="col-sm-3">
                                    <label for="end_date" class="form-label small mb-1" style="font-size: 12px; color: #555;">To Date</label>
                                    <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                                </div>
                                <div class="col-sm-3">
                                    <label for="user_id" class="form-label small mb-1" style="font-size: 12px; color: #555;">Recorded By User</label>
                                    <select class="form-select form-select-sm" style="height: 40px;" id="user_id" name="user_id">
                                        <option value="0">All Users</option>
                                        <?php foreach ($filter_users as $f_user): ?>
                                            <option value="<?php echo $f_user['id']; ?>" <?php echo ($selected_user_id == $f_user['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($f_user['username']) . ' (' . ucfirst($f_user['role']) . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-3 d-flex gap-1">
                                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1" style="padding: 7px 10px;">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <a href="../master/expenses_report.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&user_id=<?php echo $selected_user_id; ?>" target="_blank" class="btn btn-sm btn-success" style="padding: 7px 10px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                                        <i class="fas fa-print"></i> Print
                                    </a>
                                    <?php if (!empty($start_date) || !empty($end_date) || $selected_user_id > 0): ?>
                                        <a href="expenses.php" class="btn btn-sm btn-secondary" style="padding: 7px 10px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                                            <i class="fas fa-undo"></i> Clear
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>

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

                            <!-- PAGINATION BUTTONS -->
                            <div class="mt-3">
                                <?php render_pagination($page, $total_pages, '', $is_filtered); ?>
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