<?php
/**
 * Data Correction (Temporary Historical Payments Corrector)
 * School Finance Management System - Master Panel
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master(); // Only allow master (Principal)

$error = '';
$success = '';
$student_id = intval($_GET['id'] ?? 0);
$student = null;
$paid_months_in_db = [];

// 12 Months of 2026
$months_2026 = [
    'Jan-2026', 'Feb-2026', 'Mar-2026', 'Apr-2026', 'May-2026', 'Jun-2026',
    'Jul-2026', 'Aug-2026', 'Sep-2026', 'Oct-2026', 'Nov-2026', 'Dec-2026'
];

if ($student_id > 0) {
    $student = get_student($student_id);
    if ($student) {
        $paid_months_in_db = [];
        $pending_amounts_in_db = [];
        // Fetch current fee_records status and amount for 2026
        $res = $conn->query("SELECT month, status, amount FROM fee_records WHERE student_id = $student_id");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if ($row['status'] === 'paid') {
                    $paid_months_in_db[] = $row['month'];
                    $pending_amounts_in_db[$row['month']] = 0.00;
                } else {
                    $pending_amounts_in_db[$row['month']] = floatval($row['amount']);
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'correct' && $student) {
    $pending_amounts = $_POST['pending_amounts'] ?? []; // Map of month => pending_amount
    $net_fee = floatval($student['monthly_fee']);
    
    $conn->begin_transaction();
    try {
        $payment_date = date('Y-m-d H:i:s');
        $received_by = get_username() ?? 'System';
        
        foreach ($months_2026 as $month) {
            $pending = floatval($pending_amounts[$month] ?? $net_fee);
            $pending = max(0, min($net_fee, $pending)); // clamp
            
            $chk = $conn->query("SELECT id, status, amount FROM fee_records WHERE student_id = $student_id AND month = '$month'");
            $exists = ($chk && $chk->num_rows > 0);
            
            if ($pending <= 0) {
                // Fully Paid
                if ($exists) {
                    $conn->query("UPDATE fee_records SET status = 'paid', amount = 0, payment_date = '$payment_date' WHERE student_id = $student_id AND month = '$month'");
                } else {
                    $conn->query("INSERT INTO fee_records (student_id, month, amount, status, payment_date) VALUES ($student_id, '$month', 0, 'paid', '$payment_date')");
                }
                
                $conn->query("DELETE FROM payments WHERE student_id = $student_id AND paid_for_month = '$month'");
                $query_pay = "INSERT INTO payments (student_id, amount, paid_for_month, payment_date, received_by, payment_mode) VALUES (?, ?, ?, ?, ?, 'cash')";
                $stmt_pay = $conn->prepare($query_pay);
                $stmt_pay->bind_param('idsss', $student_id, $net_fee, $month, $payment_date, $received_by);
                $stmt_pay->execute();
                $stmt_pay->close();
                
            } elseif ($pending >= $net_fee) {
                // Fully Unpaid
                if ($exists) {
                    $conn->query("UPDATE fee_records SET status = 'unpaid', amount = $net_fee, payment_date = NULL WHERE student_id = $student_id AND month = '$month'");
                } else {
                    $conn->query("INSERT INTO fee_records (student_id, month, amount, status) VALUES ($student_id, '$month', $net_fee, 'unpaid')");
                }
                
                $conn->query("DELETE FROM payments WHERE student_id = $student_id AND paid_for_month = '$month'");
                
            } else {
                // Partially Paid
                if ($exists) {
                    $conn->query("UPDATE fee_records SET status = 'unpaid', amount = $pending, payment_date = '$payment_date' WHERE student_id = $student_id AND month = '$month'");
                } else {
                    $conn->query("INSERT INTO fee_records (student_id, month, amount, status, payment_date) VALUES ($student_id, '$month', $pending, 'unpaid', '$payment_date')");
                }
                
                $conn->query("DELETE FROM payments WHERE student_id = $student_id AND paid_for_month = '$month'");
                $paid_amount = $net_fee - $pending;
                $query_pay = "INSERT INTO payments (student_id, amount, paid_for_month, payment_date, received_by, payment_mode) VALUES (?, ?, ?, ?, ?, 'cash')";
                $stmt_pay = $conn->prepare($query_pay);
                $stmt_pay->bind_param('idsss', $student_id, $paid_amount, $month, $payment_date, $received_by);
                $stmt_pay->execute();
                $stmt_pay->close();
            }
        }
        
        $conn->commit();
        $success = "Student payment logs corrected successfully!";
        
        // Refresh local variables
        $paid_months_in_db = [];
        $pending_amounts_in_db = [];
        $res = $conn->query("SELECT month, status, amount FROM fee_records WHERE student_id = $student_id");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if ($row['status'] === 'paid') {
                    $paid_months_in_db[] = $row['month'];
                    $pending_amounts_in_db[$row['month']] = 0.00;
                } else {
                    $pending_amounts_in_db[$row['month']] = floatval($row['amount']);
                }
            }
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error correcting logs: " . $e->getMessage();
    }
}

// Student search parameters
$search_name = sanitize_input($_GET['search_name'] ?? '');
$search_class = sanitize_input($_GET['search_class'] ?? '');
$search_section = sanitize_input($_GET['search_section'] ?? '');
$students_list = [];

if ($student_id == 0) {
    $q = "SELECT id, name, father_name, class, section, monthly_fee FROM students WHERE 1=1";
    $params = [];
    $param_types = '';
    
    if (!empty($search_name)) {
        $q .= " AND name LIKE ?";
        $params[] = '%' . $search_name . '%';
        $param_types .= 's';
    }
    
    if (!empty($search_class)) {
        $q .= " AND class = ?";
        $params[] = $search_class;
        $param_types .= 's';
    }
    
    if (!empty($search_section)) {
        $q .= " AND section = ?";
        $params[] = $search_section;
        $param_types .= 's';
    }
    
    $q .= " ORDER BY id DESC";
    
    if (empty($search_name) && empty($search_class) && empty($search_section)) {
        $q .= " LIMIT 20";
    }
    
    $stmt = $conn->prepare($q);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $students_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Entry Correction (Temporary) - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .month-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .month-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            background: #fafafa;
            transition: all 0.2s;
            cursor: pointer;
            user-select: none;
        }
        .month-card:hover {
            border-color: #1f5f46;
            background: #f0f7f4;
        }
        .month-card input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-bottom: 8px;
            accent-color: #1f5f46;
            cursor: pointer;
        }
        .month-card label {
            display: block;
            font-weight: 600;
            color: #333;
            cursor: pointer;
        }
        .student-meta {
            background: #f8fcf9;
            border-left: 4px solid #1f5f46;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <a href="dashboard.php"><?php echo render_system_logo('topbar-logo'); ?></a>
                    <div class="panel-brand">
                        <h2>Data Correction</h2>
                        <span>Principal Panel (Temporary Tool)</span>
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
                        <a href="defaulter_list.php" class="module-nav-btn ">
                            <i class="fas fa-list"></i> Pending List
                        </a>
                        <a href="payment_analytics.php" class="module-nav-btn">
                            <i class="fas fa-chart-line"></i> Analytics
                        </a>
                        <a href="expenses.php" class="module-nav-btn">
                            <i class="fas fa-wallet"></i> Expenses
                        </a>
                        <a href="data_correction.php" class="module-nav-btn active">
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
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Step 1: Select Student -->
                <?php if (!$student): ?>
                    <div class="card p-4 mb-4">
                        <h4 class="mb-3 text-success"><i class="fas fa-filter"></i> Filter Students for Payment Correction</h4>
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Student Name</label>
                                <input type="text" name="search_name" class="form-control" value="<?php echo htmlspecialchars($search_name); ?>" placeholder="Search by name...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Class</label>
                                <select name="search_class" class="form-select">
                                    <option value="">All Classes</option>
                                    <?php foreach ($CLASSES as $cls): ?>
                                        <option value="<?php echo $cls; ?>" <?php echo $search_class == $cls ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Section</label>
                                <select name="search_section" class="form-select">
                                    <option value="">All Sections</option>
                                    <?php foreach ($SECTIONS as $sec): ?>
                                        <option value="<?php echo $sec; ?>" <?php echo $search_section == $sec ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
                            </div>
                        </form>
                    </div>

                    <div class="card p-4">
                        <h4 class="mb-3 text-secondary">
                            <?php 
                            if (!empty($search_name) || !empty($search_class) || !empty($search_section)) {
                                echo 'Filtered Student Results';
                            } else {
                                echo 'Recent Admitted Students';
                            }
                            ?>
                        </h4>
                        <?php if (empty($students_list)): ?>
                            <p class="text-muted">No students found matching your criteria.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Student Name</th>
                                            <th>Father Name</th>
                                            <th>Class - Section</th>
                                            <th>Net Monthly Fee</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students_list as $st): ?>
                                            <tr>
                                                <td><strong><?php echo str_pad($st['id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                                                <td><?php echo htmlspecialchars($st['name']); ?></td>
                                                <td><?php echo htmlspecialchars($st['father_name']); ?></td>
                                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($st['class'] . ' - ' . $st['section']); ?></span></td>
                                                <td><?php echo format_currency($st['monthly_fee']); ?></td>
                                                <td>
                                                    <a href="data_correction.php?id=<?php echo $st['id']; ?>" class="btn btn-sm btn-success text-white px-3">
                                                        <i class="fas fa-check-double"></i> Select Student
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <!-- Step 2: Display Correction Form -->
                <?php else: ?>
                    <div class="card p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="text-success m-0"><i class="fas fa-user-edit"></i> Correct Historical Payments</h4>
                            <a href="data_correction.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Select Different Student</a>
                        </div>

                        <div class="student-meta row">
                            <div class="col-md-3">
                                <strong>Student Name:</strong>
                                <p class="m-0 text-dark fs-5"><?php echo htmlspecialchars($student['name']); ?></p>
                            </div>
                            <div class="col-md-3">
                                <strong>Father's Name:</strong>
                                <p class="m-0 text-dark fs-5"><?php echo htmlspecialchars($student['father_name']); ?></p>
                            </div>
                            <div class="col-md-3">
                                <strong>Class & Section:</strong>
                                <p class="m-0 text-dark fs-5"><?php echo htmlspecialchars($student['class'] . ' (' . $student['section'] . ')'); ?></p>
                            </div>
                            <div class="col-md-3">
                                <strong>Net Monthly Fee:</strong>
                                <p class="m-0 text-success fs-5 fw-bold"><?php echo format_currency($student['monthly_fee']); ?></p>
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="action" value="correct">
                            
                            <div class="d-flex justify-content-between align-items-center mb-3 mt-4 flex-wrap gap-2">
                                <h5 class="text-success mb-0" style="font-weight: 600;">
                                    <i class="fas fa-calendar-check me-2"></i> Monthly Payments & Pending Amounts (2026)
                                </h5>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-success" id="selectAll">Mark All Paid</button>
                                    <button type="button" class="btn btn-outline-secondary" id="deselectAll">Mark All Unpaid</button>
                                </div>
                            </div>
                            <p class="text-muted small mb-3">Check the months that are fully paid. For partially paid months, uncheck the box and enter the remaining pending amount manually.</p>
                            
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered bg-white align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Month</th>
                                            <th style="width: 150px; text-align: center;">Fully Paid?</th>
                                            <th style="width: 250px;">Pending Amount (Rs.)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($months_2026 as $m): ?>
                                            <?php 
                                            $is_paid = in_array($m, $paid_months_in_db);
                                            $pending_val = $pending_amounts_in_db[$m] ?? floatval($student['monthly_fee']);
                                            ?>
                                            <tr>
                                                <td><strong><?php echo $m; ?></strong></td>
                                                <td style="text-align: center;">
                                                    <div class="form-check form-switch d-inline-block">
                                                        <input class="form-check-input month-check" type="checkbox" role="switch" id="check_<?php echo $m; ?>" <?php echo $is_paid ? 'checked' : ''; ?> onchange="toggleMonthPaid('<?php echo $m; ?>')">
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="number" name="pending_amounts[<?php echo $m; ?>]" id="pending_<?php echo $m; ?>" class="form-control form-control-sm pending-input" min="0" step="0.01" value="<?php echo htmlspecialchars($pending_val); ?>">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="form-actions mt-5 text-end">
                                <button type="submit" class="btn btn-success btn-lg px-5 text-white"><i class="fas fa-save"></i> Save & Apply Corrections</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const netFee = <?php echo floatval($student['monthly_fee'] ?? 0); ?>;
        
        function toggleMonthPaid(month) {
            const cb = document.getElementById('check_' + month);
            const input = document.getElementById('pending_' + month);
            if (cb.checked) {
                input.value = 0;
            } else {
                input.value = netFee;
            }
        }

        // Add event listeners to pending-inputs
        document.querySelectorAll('.pending-input').forEach(input => {
            input.addEventListener('input', function() {
                const month = this.id.replace('pending_', '');
                const cb = document.getElementById('check_' + month);
                const val = parseFloat(this.value || 0);
                if (val <= 0) {
                    cb.checked = true;
                } else {
                    cb.checked = false;
                }
                // Clamp max value to net monthly fee
                if (val > netFee) {
                    this.value = netFee;
                }
            });
        });

        // Select All / Deselect All
        document.getElementById('selectAll').addEventListener('click', function() {
            document.querySelectorAll('.month-check').forEach(cb => {
                cb.checked = true;
                const month = cb.id.replace('check_', '');
                document.getElementById('pending_' + month).value = 0;
            });
        });
        
        document.getElementById('deselectAll').addEventListener('click', function() {
            document.querySelectorAll('.month-check').forEach(cb => {
                cb.checked = false;
                const month = cb.id.replace('check_', '');
                document.getElementById('pending_' + month).value = netFee;
            });
        });
    </script>
</body>
</html>
