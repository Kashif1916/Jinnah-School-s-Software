<?php
/**
 * Receipt Analysis - Master Module
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master();

$search_receipt = isset($_GET['receipt_number']) ? sanitize_input(trim($_GET['receipt_number'])) : '';
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? sanitize_input($_GET['start_date']) : date('Y-m-d\T00:00');
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? sanitize_input($_GET['end_date']) : date('Y-m-d\T23:59');
$clerk_filter = isset($_GET['clerk']) ? sanitize_input($_GET['clerk']) : 'all';

// Fetch clerk list
$clerk_list = [];
$clerk_query = $conn->query("SELECT DISTINCT username FROM users WHERE role IN ('finance', 'master') ORDER BY username ASC");
if ($clerk_query) {
    while ($row = $clerk_query->fetch_assoc()) {
        if (!empty($row['username'])) $clerk_list[] = $row['username'];
    }
}

// Build query
$query = "SELECT p.*, s.name, s.father_name, s.class, s.section 
          FROM payments p 
          JOIN students s ON p.student_id = s.id 
          WHERE 1=1";
$params = [];
$param_types = '';

if (!empty($search_receipt)) {
    $query .= " AND (p.receipt_number LIKE ? OR LPAD(p.id, 6, '0') LIKE ?)";
    $params[] = '%' . $search_receipt . '%';
    $params[] = '%' . $search_receipt . '%';
    $param_types .= 'ss';
} else {
    $query .= " AND p.payment_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $param_types .= 'ss';
}

if ($clerk_filter !== 'all') {
    $query .= " AND p.received_by = ?";
    $params[] = $clerk_filter;
    $param_types .= 's';
}

$query .= " ORDER BY p.payment_date DESC, p.id DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$payments_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group payments by receipt_number
$receipts = [];
foreach ($payments_list as $p) {
    $r_num = !empty($p['receipt_number']) ? $p['receipt_number'] : sprintf('%06d', $p['id']);
    if (!isset($receipts[$r_num])) {
        $receipts[$r_num] = [
            'receipt_number' => $r_num,
            'payment_date' => $p['payment_date'],
            'received_by' => $p['received_by'],
            'payment_mode' => $p['payment_mode'],
            'total_amount' => 0,
            'payment_ids' => [],
            'items' => []
        ];
    }
    $receipts[$r_num]['total_amount'] += floatval($p['amount']);
    $receipts[$r_num]['payment_ids'][] = $p['id'];
    $receipts[$r_num]['items'][] = $p;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt Analysis - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <div class="topbar no-print">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <a href="dashboard.php"><?php echo render_system_logo('topbar-logo'); ?></a>
                    <div class="panel-brand">
                        <h2>Receipt Analysis</h2>
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
                <div class="module-nav-panel no-print">
                    <div class="module-nav-row">
                        <a href="dashboard.php" class="module-nav-btn"><i class="fas fa-chart-bar"></i> Dashboard</a>
                        <a href="add_student.php" class="module-nav-btn"><i class="fas fa-user-plus"></i> Add Student</a>
                        <a href="student_record.php" class="module-nav-btn"><i class="fas fa-address-book"></i> Student Record</a>
                        <a href="student_add_details.php" class="module-nav-btn"><i class="fas fa-history"></i> Add Log</a>
                        <a href="fee_schedule.php" class="module-nav-btn"><i class="fas fa-calendar-alt"></i> Fee Schedule</a>
                        <a href="fee_management.php" class="module-nav-btn"><i class="fas fa-money-bill-wave"></i> Fee Management</a>
                        <a href="defaulter_list.php" class="module-nav-btn"><i class="fas fa-list"></i> Pending List</a>
                        <a href="paid_students.php" class="module-nav-btn">
                            <i class="fas fa-check-circle text-success"></i> Paid Students
                        </a>
                        <a href="payment_analytics.php" class="module-nav-btn"><i class="fas fa-chart-line"></i> Analytics</a>
                        <a href="receipt_analysis.php" class="module-nav-btn active"><i class="fas fa-receipt"></i> Receipt Analysis</a>
                        <a href="expenses.php" class="module-nav-btn"><i class="fas fa-wallet"></i> Expenses</a>
                        <a href="data_correction.php" class="module-nav-btn"><i class="fas fa-edit"></i> Data Correction</a>
                        <a href="promotion.php" class="module-nav-btn"><i class="fas fa-arrow-up"></i> Promotion</a>
                        <a href="drop_student.php" class="module-nav-btn"><i class="fas fa-trash"></i> Drop Student</a>
                        <a href="delete_student.php" class="module-nav-btn"><i class="fas fa-user-minus text-success"></i> Delete Student</a>
                        <a href="users.php" class="module-nav-btn"><i class="fas fa-users-cog"></i> Users</a>
                        <a href="receipt_note.php" class="module-nav-btn"><i class="fas fa-sticky-note"></i> Custom Note</a>
                        <a href="../help.php" class="module-nav-btn"><i class="fas fa-question-circle text-success"></i> Help & About</a>
                    </div>
                </div>

                <div class="search-section mb-4 no-print">
                    <h4 class="mb-3"><i class="fas fa-search me-2"></i>Search & Filter Receipts</h4>
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Receipt Number</label>
                            <input type="text" name="receipt_number" value="<?php echo htmlspecialchars($search_receipt); ?>" class="form-control" placeholder="Enter receipt # (e.g. 000001)">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Start Date & Time</label>
                            <input type="datetime-local" name="start_date" value="<?php echo date('Y-m-d\TH:i', strtotime($start_date)); ?>" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">End Date & Time</label>
                            <input type="datetime-local" name="end_date" value="<?php echo date('Y-m-d\TH:i', strtotime($end_date)); ?>" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Clerk / User</label>
                            <select name="clerk" class="form-select">
                                <option value="all" <?php echo $clerk_filter === 'all' ? 'selected' : ''; ?>>All Clerks</option>
                                <?php foreach ($clerk_list as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $clerk_filter === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn-primary w-100"><i class="fas fa-filter"></i></button>
                        </div>
                    </form>
                </div>

                <div class="table-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4><i class="fas fa-receipt me-2 text-success"></i>Receipts Found (<?php echo count($receipts); ?>)</h4>
                        <?php if (!empty($search_receipt)): ?>
                            <span class="badge bg-info text-dark fs-6">Filter: Receipt # <?php echo htmlspecialchars($search_receipt); ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary fs-6">Date: Today / Selected Range</span>
                        <?php endif; ?>
                    </div>

                    <?php if (count($receipts) > 0): ?>
                        <?php foreach ($receipts as $r_no => $r): ?>
                            <div class="card mb-4 shadow-sm border-start border-4 border-success">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                                    <div>
                                        <span class="badge bg-success fs-6 me-2"><i class="fas fa-hashtag me-1"></i>Receipt # <?php echo htmlspecialchars($r_no); ?></span>
                                        <span class="text-muted ms-2"><i class="fas fa-clock me-1"></i><?php echo date('d-m-Y h:i A', strtotime($r['payment_date'])); ?></span>
                                        <span class="badge bg-dark-subtle text-dark ms-2"><i class="fas fa-user me-1"></i>Issued by: <?php echo htmlspecialchars($r['received_by']); ?></span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <?php 
                                        $mode_lower = strtolower($r['payment_mode']);
                                        if ($mode_lower === 'cash') {
                                            echo '<span class="badge bg-success-subtle text-success"><i class="fas fa-coins me-1"></i>Cash</span>';
                                        } else {
                                            echo '<span class="badge bg-primary-subtle text-primary"><i class="fas fa-university me-1"></i>' . htmlspecialchars($r['payment_mode']) . '</span>';
                                        }
                                        ?>
                                        <h5 class="mb-0 text-success fw-bold">Total: <?php echo format_currency($r['total_amount']); ?></h5>
                                        <a href="receipt.php?payment_ids=<?php echo implode(',', $r['payment_ids']); ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-print"></i> Re-print Receipt
                                        </a>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-hover mb-0 align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Student Name</th>
                                                <th>Father Name</th>
                                                <th>Class & Sec</th>
                                                <th>Paid Month / Particular</th>
                                                <th class="text-end">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($r['items'] as $item): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($item['father_name']); ?></td>
                                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($item['class'] . '-' . $item['section']); ?></span></td>
                                                    <td><span class="fw-bold text-dark"><?php echo htmlspecialchars($item['paid_for_month']); ?></span></td>
                                                    <td class="text-end fw-bold"><?php echo format_currency($item['amount']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info py-4">
                            <i class="fas fa-info-circle me-2 fs-5"></i> No receipts matching the search criteria were found.
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
