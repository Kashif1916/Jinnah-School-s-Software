<?php
/**
 * Student Addition Log / Add Details - Master Module
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master(); // Enforces Master role

$start_date = sanitize_input($_GET['start_date'] ?? '');
$end_date = sanitize_input($_GET['end_date'] ?? '');
$search_admitted_by = sanitize_input($_GET['search_admitted_by'] ?? '');

// Check if user has applied any filter
$is_filtered = (!empty($start_date) || !empty($end_date) || !empty($search_admitted_by));

// Pagination Configuration (Only applies when NO filter is used)
$limit = 20; // Default items per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Fetch list of unique users who have added students for search dropdown
$admitted_by_users = [];
$users_query = $conn->query("SELECT DISTINCT username FROM users WHERE role IN ('admission', 'master') ORDER BY username ASC");
if ($users_query) {
    while ($row = $users_query->fetch_assoc()) {
        $admitted_by_users[] = $row['username'];
    }
}

// 1. Get Total Count for UI Display & Pagination Calculation
$count_query = "SELECT COUNT(*) as total FROM students WHERE 1=1";
$count_params = [];
$count_types = '';

if (!empty($start_date)) {
    $count_query .= " AND DATE(created_at) >= ?";
    $count_params[] = $start_date;
    $count_types .= 's';
}

if (!empty($end_date)) {
    $count_query .= " AND DATE(created_at) <= ?";
    $count_params[] = $end_date;
    $count_types .= 's';
}

if (!empty($search_admitted_by)) {
    $count_query .= " AND created_by = ?";
    $count_params[] = $search_admitted_by;
    $count_types .= 's';
}

$stmt_count = $conn->prepare($count_query);
if (!empty($count_params)) {
    $stmt_count->bind_param($count_types, ...$count_params);
}
$stmt_count->execute();
$total_students = $stmt_count->get_result()->fetch_assoc()['total'];
$stmt_count->close();

$total_pages = ceil($total_students / $limit);

// 2. Query to fetch students based on filters
$query = "SELECT * FROM students WHERE 1=1";
$params = [];
$param_types = '';

if (!empty($start_date)) {
    $query .= " AND DATE(created_at) >= ?";
    $params[] = $start_date;
    $param_types .= 's';
}

if (!empty($end_date)) {
    $query .= " AND DATE(created_at) <= ?";
    $params[] = $end_date;
    $param_types .= 's';
}

if (!empty($search_admitted_by)) {
    $query .= " AND created_by = ?";
    $params[] = $search_admitted_by;
    $param_types .= 's';
}

$query .= " ORDER BY created_at DESC";

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
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Addition Details - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .print-only-header {
            display: none;
        }
        .report-logo {
            width: 80px !important;
            height: auto !important;
        }
        @media print {
            @page {
                size: landscape;
                margin: 10mm;
            }
            body {
                background: white !important;
                color: black !important;
                font-size: 10px !important;
            }
            .wrapper, .main-content, .content, .table-section, .table-responsive {
                margin: 0 !important;
                padding: 0 !important;
                background: none !important;
                overflow: visible !important;
                height: auto !important;
                min-height: auto !important;
                display: block !important;
            }
            .no-print, .topbar, .module-nav-panel, .search-section, .btn-action, button, a, nav {
                display: none !important;
            }
            .table-section {
                border: none !important;
                box-shadow: none !important;
                background: none !important;
                padding: 0 !important;
            }
            table {
                width: 100% !important;
                border-collapse: collapse !important;
            }
            table th {
                background-color: #1f5f46 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                border: 1px solid #555 !important;
                padding: 4px !important;
            }
            table td {
                border: 1px solid #ddd !important;
                padding: 4px !important;
            }
            .print-only-header {
                display: block !important;
                margin-bottom: 20px;
                border-bottom: 2px solid #1f5f46;
                padding-bottom: 10px;
            }
            .print-only-header h2 {
                color: #1f5f46;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <div class="topbar no-print">
                <div class="topbar-left d-flex align-items-center gap-3">
                   <a href="dashboard.php"><?php echo render_system_logo('topbar-logo'); ?></a>
                    <div class="panel-brand">
                        <h2>Student Addition Details</h2>
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
                <div class="print-only-header">
                    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #1f5f46; padding-bottom: 5mm; margin-bottom: 10mm; width: 100%;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <?php echo render_system_logo('report-logo'); ?>
                            <div style="text-align: left;">
                                <h2 style="margin: 0; color: #1f5f46; font-size: 20px; font-weight: bold;">Jinnah School And Intermediate College Khushab</h2>
                                <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">Student Addition Details Log</p>
                            </div>
                        </div>
                        <div style="text-align: right; font-size: 11px; color: #666;">
                            <p style="margin: 0;">Generated on: <?php echo date('d-M-Y h:i A'); ?></p>
                        </div>
                    </div>
                </div>

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
                        <a href="student_add_details.php" class="module-nav-btn active">
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
                            <i class="fas fa-trash"></i> Drop Student
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

                <div class="search-section mb-4 no-print">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">From Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">To Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Admitted By (User)</label>
                            <select name="search_admitted_by" class="form-select">
                                <option value="">All Users</option>
                                <?php foreach ($admitted_by_users as $usr): ?>
                                    <option value="<?php echo htmlspecialchars($usr); ?>" <?php echo $search_admitted_by == $usr ? 'selected' : ''; ?>><?php echo htmlspecialchars($usr); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button type="submit" class="btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <button type="button" onclick="window.print()" class="btn-secondary">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </form>
                </div>

                <div class="table-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4>Total Students: <?php echo $total_students; ?> </h4>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Father Name</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Monthly Fee (Fixed)</th>
                                    <th>Concession</th>
                                    <th>Monthly Fee (Net)</th>
                                    <th>Contact Number(s)</th>
                                    <th>Admitted By</th>
                                    <th>Admitted At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($students) > 0): ?>
                                    <?php foreach ($students as $s): ?>
                                        <tr>
                                            <td><?php echo $s['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($s['father_name']); ?></td>
                                            <td><?php echo htmlspecialchars($s['class']); ?></td>
                                            <td><?php echo htmlspecialchars($s['section']); ?></td>
                                            <td><?php echo format_currency($s['fixed_monthly_fee']); ?></td>
                                            <td><?php echo format_currency($s['concession_amount']); ?></td>
                                            <td><?php echo format_currency($s['monthly_fee']); ?></td>
                                            <td>
                                                <?php echo !empty($s['contact_number']) ? htmlspecialchars($s['contact_number']) . '<br>' : ''; ?>
                                                <?php echo !empty($s['contact_number2']) ? htmlspecialchars($s['contact_number2']) . '<br>' : ''; ?>
                                                <?php echo !empty($s['whatsapp_number']) ? htmlspecialchars($s['whatsapp_number']) : ''; ?>
                                                <span class="badge <?php echo $s['status'] == 'active' ? 'bg-success' : 'bg-danger'; ?> no-print" style="margin-top: 5px; display: block;">
                                                    Status: <?php echo ucfirst($s['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-dark" style="font-size: 0.85rem;">
                                                    <i class="fas fa-user-edit me-1"></i>
                                                    <?php echo !empty($s['created_by']) ? htmlspecialchars($s['created_by']) : 'Legacy System'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('d-M-Y h:i A', strtotime($s['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="text-center">No student addition records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINATION BUTTONS -->
                    <?php render_pagination($page, $total_pages, '', $is_filtered); ?>

                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>