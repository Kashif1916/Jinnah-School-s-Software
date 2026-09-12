<?php
/**
 * Account Close Audit Log - Master Module
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master(); // Enforces Master permission

$error = '';
$success = '';

// Handle Manual Unfreeze Action directly from log
if (isset($_GET['unfreeze_user'])) {
    $unfreeze_user_id = intval($_GET['unfreeze_user']);
    $log_id = isset($_GET['log_id']) ? intval($_GET['log_id']) : 0;
    
    $stmt_chk = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt_chk->bind_param("i", $unfreeze_user_id);
    $stmt_chk->execute();
    $user_res = $stmt_chk->get_result();
    
    if ($user_res && $user_res->num_rows > 0) {
        $user_info = $user_res->fetch_assoc();
        $target_username = $user_info['username'];
        
        $up_stmt = $conn->prepare("UPDATE users SET is_frozen = 0, frozen_until = NULL WHERE id = ?");
        $up_stmt->bind_param("i", $unfreeze_user_id);
        if ($up_stmt->execute()) {
            if ($log_id > 0) {
                $conn->query("UPDATE account_close_logs SET status = 'unfrozen_by_master' WHERE id = " . intval($log_id));
            }
            $success = "User <strong>" . htmlspecialchars($target_username) . "</strong> has been manually unfrozen and can now log in immediately!";
        } else {
            $error = "Error unfreezing user: " . $conn->error;
        }
        $up_stmt->close();
    } else {
        $error = "User not found in system.";
    }
    $stmt_chk->close();
}

// Filters
$start_date = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : '';
$user_filter = isset($_GET['user_filter']) ? sanitize_input($_GET['user_filter']) : '';
$status_filter = isset($_GET['status_filter']) ? sanitize_input($_GET['status_filter']) : 'all';

$is_filtered = (!empty($start_date) || !empty($end_date) || !empty($user_filter) || $status_filter !== 'all');

// Pagination
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Fetch unique users for search dropdown
$users_dropdown = [];
$u_res = $conn->query("SELECT DISTINCT username FROM account_close_logs ORDER BY username ASC");
if ($u_res) {
    while ($r = $u_res->fetch_assoc()) {
        $users_dropdown[] = $r['username'];
    }
}

// Base Query Conditions
$where_clauses = ["1=1"];
$params = [];
$types = '';

if (!empty($start_date)) {
    $where_clauses[] = "DATE(l.closed_at) >= ?";
    $params[] = $start_date;
    $types .= 's';
}

if (!empty($end_date)) {
    $where_clauses[] = "DATE(l.closed_at) <= ?";
    $params[] = $end_date;
    $types .= 's';
}

if (!empty($user_filter)) {
    $where_clauses[] = "l.username = ?";
    $params[] = $user_filter;
    $types .= 's';
}

if ($status_filter === 'frozen') {
    $where_clauses[] = "u.is_frozen = 1";
} elseif ($status_filter === 'active') {
    $where_clauses[] = "(u.is_frozen = 0 OR u.is_frozen IS NULL)";
}

$where_sql = implode(" AND ", $where_clauses);

// Count Total Matching Records
$count_sql = "SELECT COUNT(*) as total 
              FROM account_close_logs l 
              LEFT JOIN users u ON l.user_id = u.id 
              WHERE $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $limit);

// Fetch Matching Records (Each freeze event is its own separate record)
$query_sql = "SELECT l.*, u.is_frozen as current_is_frozen, u.frozen_until as current_frozen_until, u.role as user_role 
              FROM account_close_logs l 
              LEFT JOIN users u ON l.user_id = u.id 
              WHERE $where_sql 
              ORDER BY l.closed_at DESC";

if (!$is_filtered) {
    $query_sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
}

$stmt = $conn->prepare($query_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Close Audit Log - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .table-custom {
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow-light);
            background: #ffffff;
        }
        .table-custom thead th {
            background-color: #1f5f46;
            color: #ffffff;
            font-weight: 600;
            border: none;
            padding: 13px 16px;
            font-size: 13.5px;
        }
        .table-custom tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.15s ease;
        }
        .table-custom tbody tr:hover {
            background-color: #f8faf9;
        }
        .table-custom tbody td {
            padding: 13px 16px;
            vertical-align: middle;
            font-size: 13.5px;
        }
        .time-box {
            display: inline-flex;
            flex-direction: column;
        }
        .time-highlight {
            font-size: 14.5px;
            font-weight: 700;
            color: #c0392b;
            letter-spacing: 0.3px;
        }
        .date-subtext {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }
        .print-only-header {
            display: none;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0.8cm !important;
            }
            body {
                background: #ffffff !important;
                color: #000000 !important;
                font-size: 11px !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .no-print, .topbar, .module-nav-panel, .search-section, .btn, .pagination, .alert {
                display: none !important;
            }
            .print-only-header {
                display: block !important;
                margin-bottom: 15px !important;
            }
            .table-custom {
                box-shadow: none !important;
                border: 1px solid #dee2e6 !important;
                width: 100% !important;
            }
            .table-custom thead th {
                background-color: #f1f5f3 !important;
                color: #000000 !important;
                border: 1px solid #dee2e6 !important;
                font-size: 10.5px !important;
                padding: 6px !important;
            }
            .table-custom tbody td {
                border: 1px solid #dee2e6 !important;
                padding: 6px !important;
                font-size: 10.5px !important;
            }
            .time-highlight {
                color: #000 !important;
                font-size: 11px !important;
            }
            .badge {
                border: 1px solid #333 !important;
                color: #000 !important;
                background: transparent !important;
                font-weight: bold !important;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <!-- Topbar -->
            <div class="topbar no-print">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <a href="dashboard.php"><?php echo render_system_logo('topbar-logo'); ?></a>
                    <div class="panel-brand">
                        <h2>Account Close Audit Log</h2>
                        <span>Principal / Master Panel</span>
                    </div>
                </div>
                <div class="topbar-right">
                    <span class="user-info">
                        <i class="fas fa-user-circle"></i> <?php echo get_username(); ?> <small>(Principal)</small>
                    </span>
                    <a href="../logout.php" class="btn-secondary">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <div class="content">
                <!-- Print Header -->
                <div class="print-only-header">
                    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #1f5f46; padding-bottom: 5mm; margin-bottom: 5mm; width: 100%;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <?php echo render_system_logo('report-logo'); ?>
                            <div style="text-align: left;">
                                <h2 style="margin: 0; color: #1f5f46; font-size: 18px; font-weight: bold;">Jinnah School And Intermediate College Khushab</h2>
                                <p style="margin: 3px 0 0 0; color: #666; font-size: 12px;">Finance Account Close & Freeze Audit Trail</p>
                            </div>
                        </div>
                        <div style="text-align: right; font-size: 11px; color: #666;">
                            <p style="margin: 0;"><strong>Printed:</strong> <?php echo date('d-M-Y h:i A'); ?></p>
                            <p style="margin: 0;"><strong>Generated By:</strong> <?php echo htmlspecialchars(get_username()); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Master Navigation Panel -->
                <div class="module-nav-panel no-print">
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
                        <a href="data_correction.php" class="module-nav-btn">
                            <i class="fas fa-edit"></i> Data Correction
                        </a>
                        <a href="promotion.php" class="module-nav-btn">
                            <i class="fas fa-arrow-up"></i> Promotion
                        </a>
                        <a href="drop_student.php" class="module-nav-btn">
                            <i class="fas fa-trash text-success"></i> Drop Student
                        </a>
                        <a href="delete_student.php" class="module-nav-btn">
                            <i class="fas fa-user-minus text-success"></i> Delete Student
                        </a>
                        <a href="users.php" class="module-nav-btn">
                            <i class="fas fa-users-cog"></i> Users
                        </a>
                        <a href="account_close_log.php" class="module-nav-btn active">
                            <i class="fas fa-lock"></i> Close Logs
                        </a>
                        <a href="receipt_note.php" class="module-nav-btn">
                            <i class="fas fa-sticky-note"></i> Custom Note
                        </a>
                        <a href="../help.php" class="module-nav-btn">
                            <i class="fas fa-question-circle text-success"></i> Help & About
                        </a>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters & Search Section -->
                <div class="search-section mb-4 no-print">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-dark">Starting Date</label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-dark">Ending Date</label>
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold text-dark">Clerk / User</label>
                            <select name="user_filter" class="form-select">
                                <option value="">All Users</option>
                                <?php foreach ($users_dropdown as $u): ?>
                                    <option value="<?php echo htmlspecialchars($u); ?>" <?php echo $user_filter === $u ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($u); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold text-dark">Account Status</label>
                            <select name="status_filter" class="form-select">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="frozen" <?php echo $status_filter === 'frozen' ? 'selected' : ''; ?>>Currently Frozen</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active / Unfrozen</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn-primary flex-grow-1">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <?php if ($is_filtered): ?>
                                <a href="account_close_log.php" class="btn btn-secondary" title="Reset Filters">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                            <button type="button" onclick="window.print()" class="btn btn-outline-success" title="Print Log Statement">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Log Table -->
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="fas fa-history text-success me-2"></i> Account Close & Freeze Audit Log
                        </h5>
                        <span class="badge bg-success-subtle text-success border border-success px-3 py-2 fs-6">
                            Total Records: <strong><?php echo number_format($total_records); ?></strong>
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-custom table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">#</th>
                                        <th>Clerk / User</th>
                                        <th>Freeze Time & Date</th>
                                        <th>Frozen Until</th>
                                        <th>Initiated By</th>
                                        <th>Account Status</th>
                                        
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($logs) > 0): ?>
                                        <?php 
                                        $sn = $is_filtered ? 1 : $offset + 1;
                                        foreach ($logs as $log): 
                                            $is_now_frozen = intval($log['current_is_frozen'] ?? 0) === 1;
                                            $freeze_time_display = date('h:i:s A', strtotime($log['closed_at']));
                                            $freeze_date_display = date('d-M-Y (l)', strtotime($log['closed_at']));
                                            $frozen_until_display = !empty($log['frozen_until']) ? date('d-M-Y h:i A', strtotime($log['frozen_until'])) : 'Until Master Unfreeze';
                                        ?>
                                            <tr>
                                                <td class="text-muted fw-bold"><?php echo $sn++; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="avatar-circle" style="width: 36px; height: 36px; border-radius: 50%; background: #eaf2ed; color: #1f5f46; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                                            <?php echo strtoupper(substr($log['username'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <strong class="text-dark"><?php echo htmlspecialchars($log['username']); ?></strong>
                                                            <div class="small text-muted">Role: <?php echo htmlspecialchars(ucfirst($log['role'] ?? 'finance')); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="time-box">
                                                        <span class="time-highlight">
                                                            <i class="far fa-clock me-1"></i> <?php echo $freeze_time_display; ?>
                                                        </span>
                                                        <span class="date-subtext">
                                                            <i class="far fa-calendar-alt me-1"></i> <?php echo $freeze_date_display; ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="text-secondary fw-semibold">
                                                        <i class="fas fa-unlock-alt text-muted me-1"></i> <?php echo $frozen_until_display; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (strtolower($log['closed_by']) === strtolower($log['username'])): ?>
                                                        <span class="badge bg-primary-subtle text-primary border border-primary">
                                                            <i class="fas fa-user me-1"></i> Self (Clerk)
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning-subtle text-warning border border-warning">
                                                            <i class="fas fa-user-shield me-1"></i> <?php echo htmlspecialchars($log['closed_by']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($is_now_frozen): ?>
                                                        <span class="badge bg-danger-subtle text-danger border border-danger">
                                                            <i class="fas fa-lock me-1"></i> Currently Frozen
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success-subtle text-success border border-success">
                                                            <i class="fas fa-check-circle me-1"></i> Active / Unfrozen
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                               
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                <i class="fas fa-shield-alt text-secondary mb-3" style="font-size: 40px;"></i>
                                                <p class="mb-0 fw-bold">No account close events found.</p>
                                                <small class="text-muted">Whenever a finance user clicks "Confirm Account Close", a separate record with the exact freeze time will appear here.</small>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <?php if (!$is_filtered && $total_pages > 1): ?>
                        <div class="card-footer bg-white d-flex flex-column align-items-center justify-content-center py-3 no-print gap-2">
                            
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo ($page === $i) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
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
