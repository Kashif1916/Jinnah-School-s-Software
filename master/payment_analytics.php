<?php
/**
 * Consolidated Payment Analytics & Clerk Reconciliation - Master Module
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master();

// Get date filters. Default to today's date if not set.
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? sanitize_input($_GET['start_date']) : date('Y-m-d');
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? sanitize_input($_GET['end_date']) : $start_date;

// Ensure start_date is not after end_date
if (strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// Get clerk filter. Default to 'all' for consolidated statement
$clerk_filter = isset($_GET['clerk']) ? sanitize_input($_GET['clerk']) : 'all';

// Fetch list of unique clerks/users who exist in the database or have transactions
$clerk_list = [];
$clerk_query = $conn->query("
    SELECT DISTINCT username FROM users 
    UNION 
    SELECT DISTINCT received_by AS username FROM payments 
    UNION 
    SELECT DISTINCT username FROM expenses 
    ORDER BY username ASC
");
if ($clerk_query) {
    while ($row = $clerk_query->fetch_assoc()) {
        if (!empty($row['username'])) {
            $clerk_list[] = $row['username'];
        }
    }
}

// Fetch payments based on clerk filter
if ($clerk_filter === 'all') {
    $query_payments = "SELECT p.*, s.name, s.father_name, s.class, s.section FROM payments p 
                       JOIN students s ON p.student_id = s.id 
                       WHERE DATE(p.payment_date) BETWEEN ? AND ? 
                       ORDER BY p.payment_date ASC";
    $stmt = $conn->prepare($query_payments);
    $stmt->bind_param('ss', $start_date, $end_date);
} else {
    $query_payments = "SELECT p.*, s.name, s.father_name, s.class, s.section FROM payments p 
                       JOIN students s ON p.student_id = s.id 
                       WHERE p.received_by = ? AND DATE(p.payment_date) BETWEEN ? AND ? 
                       ORDER BY p.payment_date ASC";
    $stmt = $conn->prepare($query_payments);
    $stmt->bind_param('sss', $clerk_filter, $start_date, $end_date);
}
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch expenses based on clerk filter
if ($clerk_filter === 'all') {
    $query_expenses = "SELECT * FROM expenses 
                       WHERE DATE(created_at) BETWEEN ? AND ? 
                       ORDER BY created_at ASC, id ASC";
    $stmt_exp = $conn->prepare($query_expenses);
    $stmt_exp->bind_param('ss', $start_date, $end_date);
} else {
    $query_expenses = "SELECT * FROM expenses 
                       WHERE username = ? AND DATE(created_at) BETWEEN ? AND ? 
                       ORDER BY created_at ASC, id ASC";
    $stmt_exp = $conn->prepare($query_expenses);
    $stmt_exp->bind_param('sss', $clerk_filter, $start_date, $end_date);
}
$stmt_exp->execute();
$expenses = $stmt_exp->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_exp->close();

// Calculations
$total_received = 0;
$total_cash = 0;
$total_bank_account = 0;

foreach ($payments as $p) {
    $amount = floatval($p['amount']);
    $total_received += $amount;
    
    $mode = strtolower(trim($p['payment_mode']));
    if ($mode === 'cash') {
        $total_cash += $amount;
    } else {
        $total_bank_account += $amount;
    }
}

$total_expenses = 0;
foreach ($expenses as $e) {
    $total_expenses += floatval($e['amount']);
}

// Cash in hand = Total Cash Payments - Total Expenses
$cash_remaining = $total_cash - $total_expenses;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consolidated Reconciliation & Analytics - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        /* Screen styling */
        .reconciliation-math-card {
            background: linear-gradient(135deg, #ffffff 0%, #f9fbf9 100%);
            border-left: 5px solid var(--primary-color);
            border-radius: 12px;
            box-shadow: var(--shadow-medium);
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .reconciliation-math-card::after {
            content: "\f53d";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            right: 20px;
            bottom: -10px;
            font-size: 120px;
            color: rgba(31, 95, 70, 0.04);
            pointer-events: none;
        }

        .math-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px dashed rgba(0,0,0,0.08);
            font-size: 1.05rem;
        }

        .math-line.subtraction {
            color: var(--danger-color);
        }

        .math-line.subtotal {
            border-bottom: 2px solid var(--border-color);
            font-weight: 600;
            color: var(--dark-text);
        }

        .math-line.final-total {
            border-bottom: none;
            padding-top: 20px;
            margin-top: 10px;
        }

        .net-cash-large-box {
            text-align: right;
            padding: 15px 25px;
            background: linear-gradient(135deg, #1f5f46 0%, #10161b 100%);
            color: #ffffff;
            border-radius: 8px;
            box-shadow: var(--shadow-medium);
            display: inline-block;
        }

        .net-cash-large-box h2 {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
        }

        .net-cash-large-box span {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
            display: block;
            margin-bottom: 4px;
        }

        .section-sub-title {
            font-weight: 600;
            color: var(--primary-color);
            border-bottom: 2px solid rgba(31, 95, 70, 0.1);
            padding-bottom: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .print-only-header {
            display: none;
        }

        /* -------------------------------------------------------------
           PREMIUM PRINT STYLING - KEEPS EXACT WEB LOOK & SIDE-BY-SIDE
           ------------------------------------------------------------- */
        /* -------------------------------------------------------------
           PREMIUM PRINT STYLING - FIXED FOR DRAWER STATEMENT
           ------------------------------------------------------------- */
        /* -------------------------------------------------------------
           PREMIUM PRINT STYLING - EXTRA COMPACT & FITTED
           ------------------------------------------------------------- */
        @media print {
            @page {
                size: A4 portrait;
                margin: 0.3cm !important; /* Margin kam kar diya taaki zyada jagah mile */
            }

            body {
                background: #ffffff !important;
                color: #000000 !important;
                font-family: 'Segoe UI', Arial, sans-serif !important;
                font-size: 9.5px !important; /* Default font size chota kiya */
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* 1. Hiding ALL unnecessary components (Including Stats Cards) */
            .topbar, .module-nav-panel, .no-print, button, form, 
            .form-text, .alert-success, .stats-grid, .stat-card {
                display: none !important;
            }

            /* 2. Show print header cleanly */
            .print-only-header {
                display: block !important;
                border-bottom: 2px solid #1f5f46 !important;
                padding-bottom: 3px !important;
                margin-bottom: 10px !important;
            }

            .print-only-header h1 {
                font-size: 16px !important;
                color: #1f5f46 !important;
                font-weight: bold !important;
                margin: 0 !important;
            }

            .print-only-header h3 {
                font-size: 11px !important;
                color: #333 !important;
                margin: 3px 0 !important;
            }

            .print-meta-grid {
                display: flex !important;
                justify-content: space-between !important;
                font-size: 9px !important;
                margin-top: 3px !important;
            }

            /* 3. Columns Layout Structure */
            .row {
                display: flex !important;
                flex-direction: row !important;
                flex-wrap: nowrap !important;
                gap: 12px !important;
                width: 100% !important;
            }

            /* Left side: Tables (Payments & Expenses) */
            .col-lg-7 {
                width: 60% !important;
                flex: 0 0 60% !important;
                max-width: 60% !important;
            }

            /* Right side: Math Sheet Box */
            .col-lg-5 {
                width: 38% !important;
                flex: 0 0 38% !important;
                max-width: 38% !important;
            }

            /* 4. Table Formatting for Print */
            .table-responsive {
                overflow: visible !important;
            }

            table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-bottom: 10px !important;
                font-size: 9px !important; /* Table data text chota kiya */
            }

            table th, table td {
                padding: 4px 5px !important; /* Tables ki spacing tight ki */
                border: 1px solid #ddd !important;
            }

            /* 5. Drawer Math Card - Extra Compact & Small Text */
            .reconciliation-math-card {
                border: 1px solid #ccc !important;
                border-left: 4px solid #1f5f46 !important;
                padding: 10px !important; /* Card ke andar ka gap kam kiya */
                background: #fdfdfd !important;
                box-shadow: none !important;
            }

            /* Har line ki spacing aur font size ko chota kiya */
            .math-line, .reconciliation-math-card div, .reconciliation-math-card p {
                padding: 4px 0 !important; 
                font-size: 8.5px !important; /* Text chota kiya taaki lamba na ho */
                line-height: 1.2 !important;
            }

            /* Net Cash Large Box ko compact kiya */
            .net-cash-large-box {
                background: #1f5f46 !important;
                color: #ffffff !important;
                padding: 6px 10px !important;
                border-radius: 4px !important;
                margin-top: 8px !important;
            }

            .net-cash-large-box h2, .net-cash-large-box .h2 {
                font-size: 13px !important; /* Main amount ka size chota kiya */
                font-weight: 700 !important;
                margin: 0 !important;
            }

            .net-cash-large-box span, .net-cash-large-box small {
                font-size: 8px !important;
            }

            tr, .reconciliation-math-card {
                page-break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <!-- Screen Top Bar -->
            <div class="topbar no-print">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <a href="dashboard.php"><?php echo render_system_logo('topbar-logo'); ?></a>
                    <div class="panel-brand">
                        <h2>Consolidated Analytics</h2>
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

            <!-- Print Only Spreadsheet Header -->
            <div class="print-only-header">
                <h1><?php echo SITE_NAME; ?></h1>
                <h3>
                    <?php 
                    if ($clerk_filter === 'all') {
                        echo "Principal's Consolidated Reconciliation Statement (All Clerks)";
                    } else {
                        echo "Clerk Cash Reconciliation Statement (Clerk: " . htmlspecialchars($clerk_filter) . ")";
                    }
                    ?>
                </h3>
                <div class="print-meta-grid">
                    <div class="print-meta-col">
                        <strong>Reporting User:</strong> Principal (master)
                    </div>
                    <div class="print-meta-col text-center">
                        <strong>Reconciliation Period:</strong> 
                        <?php 
                        if ($start_date === $end_date) {
                            echo date('d-m-Y', strtotime($start_date)) . ' (Single Day)';
                        } else {
                            echo date('d-m-Y', strtotime($start_date)) . ' to ' . date('d-m-Y', strtotime($end_date));
                        }
                        ?>
                    </div>
                    <div class="print-meta-col text-end">
                        <strong>Print Date:</strong> <?php echo date('d-m-Y h:i A'); ?>
                    </div>
                </div>
            </div>

            <div class="content">
                <!-- Navigation Tab Bar -->
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
                        <a href="fee_management.php" class="module-nav-btn">
                            <i class="fas fa-money-bill-wave"></i> Fee Management
                        </a>
                        <a href="defaulter_list.php" class="module-nav-btn">
                            <i class="fas fa-list"></i> Pending List
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

                <!-- Active Date & Clerk Heading Alert -->
                <div class="alert alert-success d-flex align-items-center justify-content-between mb-4 no-print">
                    <div>
                        <i class="fas fa-calendar-day me-2"></i>
                        Showing calculations for 
                        <strong>
                            <?php echo $clerk_filter === 'all' ? 'All Clerks (Consolidated Statement)' : 'Clerk: ' . htmlspecialchars($clerk_filter); ?>
                        </strong>
                        from: 
                        <strong>
                            <?php 
                            if ($start_date === $end_date) {
                                if ($start_date === date('Y-m-d')) {
                                    echo "Today (" . date('d-m-Y', strtotime($start_date)) . ")";
                                } else {
                                    echo date('d-m-Y', strtotime($start_date));
                                }
                            } else {
                                echo date('d-m-Y', strtotime($start_date)) . " to " . date('d-m-Y', strtotime($end_date));
                            }
                            ?>
                        </strong>
                    </div>
                    <span class="badge bg-success text-white">
                        <i class="fas fa-eye me-1"></i> Principal View
                    </span>
                </div>

                <!-- Date & Clerk Filter Form (Screen Only) -->
                <div class="search-section mb-4 no-print">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-dark">Starting Date</label>
                            <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-dark">Ending Date (Optional)</label>
                            <input type="date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>" class="form-control" placeholder="Select end date...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-dark">Select Clerk / User</label>
                            <select name="clerk" class="form-select">
                                <option value="all" <?php echo $clerk_filter === 'all' ? 'selected' : ''; ?>>All Clerks (Combined)</option>
                                <?php foreach ($clerk_list as $clerk_uname): ?>
                                    <option value="<?php echo htmlspecialchars($clerk_uname); ?>" <?php echo $clerk_filter === $clerk_uname ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($clerk_uname); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn-primary flex-grow-1">
                                <i class="fas fa-sync"></i> Refresh Report
                            </button>
                            <button type="button" onclick="window.print()" class="btn-secondary">
                                <i class="fas fa-print"></i> Print Statement
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Reconciliation Summary Cards (Screen Only) -->
                <div class="stats-grid mb-4 no-print">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f1ea;">
                            <i class="fas fa-receipt text-success"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo format_currency($total_received); ?></h3>
                            <p>Gross Collection (<?php echo count($payments); ?> payments)</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fdf5e6;">
                            <i class="fas fa-university text-warning"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo format_currency($total_bank_account); ?></h3>
                            <p>Bank/Account Payments</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fde8e8;">
                            <i class="fas fa-wallet text-danger"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo format_currency($total_expenses); ?></h3>
                            <p>Expenses Recorded</p>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Detailed Lists -->
                    <div class="col-lg-7">
                        <!-- Fee Payments Table -->
                        <div class="mb-4">
                            <h5 class="section-sub-title">
                                <i class="fas fa-receipt"></i>
                                Fee Payments Received (<?php echo $clerk_filter === 'all' ? 'All Clerks Consolidated' : htmlspecialchars($clerk_filter); ?>)
                            </h5>
                            <div class="table-responsive">
                                <?php if (count($payments) > 0): ?>
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Class</th>
                                                <th>Month</th>
                                                <th>Mode</th>
                                                <?php if ($clerk_filter === 'all'): ?>
                                                    <th>Received By</th>
                                                <?php endif; ?>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $p): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                                                        <div class="text-muted small">F/Name: <?php echo htmlspecialchars($p['father_name']); ?></div>
                                                        <div class="text-muted small print-only"><?php echo date('d-m-Y H:i', strtotime($p['payment_date'])); ?></div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($p['class'] . '-' . $p['section']); ?></td>
                                                    <td><?php echo htmlspecialchars($p['paid_for_month']); ?></td>
                                                    <td>
                                                        <?php 
                                                        $mode_lower = strtolower($p['payment_mode']);
                                                        if ($mode_lower === 'cash') {
                                                            echo '<span class="badge bg-success-subtle text-success no-print"><i class="fas fa-coins me-1"></i>Cash</span>';
                                                            echo '<span class="print-only">CASH</span>';
                                                        } else {
                                                            echo '<span class="badge bg-primary-subtle text-primary no-print"><i class="fas fa-university me-1"></i>' . htmlspecialchars($p['payment_mode']) . '</span>';
                                                            echo '<span class="print-only">' . strtoupper(htmlspecialchars($p['payment_mode'])) . '</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <?php if ($clerk_filter === 'all'): ?>
                                                        <td>
                                                            <span class="badge bg-dark-subtle text-dark no-print">
                                                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($p['received_by']); ?>
                                                            </span>
                                                            <span class="print-only" style="font-weight: 600; text-transform: uppercase;">
                                                                <?php echo htmlspecialchars($p['received_by']); ?>
                                                            </span>
                                                        </td>
                                                    <?php endif; ?>
                                                    <td><strong><?php echo format_currency($p['amount']); ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-light">
                                                <td colspan="<?php echo $clerk_filter === 'all' ? '5' : '4'; ?>" class="text-end text-success"><strong>Cash Payments Subtotal:</strong></td>
                                                <td class="text-success"><strong><?php echo format_currency($total_cash); ?></strong></td>
                                            </tr>
                                            <tr class="table-light">
                                                <td colspan="<?php echo $clerk_filter === 'all' ? '5' : '4'; ?>" class="text-end text-primary"><strong>Bank/Account Payments Subtotal:</strong></td>
                                                <td class="text-primary"><strong><?php echo format_currency($total_bank_account); ?></strong></td>
                                            </tr>
                                            <tr class="table-dark">
                                                <td colspan="<?php echo $clerk_filter === 'all' ? '5' : '4'; ?>" class="text-end"><strong>Gross Sum:</strong></td>
                                                <td><strong><?php echo format_currency($total_received); ?></strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                <?php else: ?>
                                    <div class="alert alert-info py-3 mb-0">
                                        <i class="fas fa-info-circle me-2"></i> No fee payments found for the selected criteria.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Expenses Table -->
                        <div>
                            <h5 class="section-sub-title">
                                <i class="fas fa-wallet"></i>
                                Expenses Logged (<?php echo $clerk_filter === 'all' ? 'All Clerks Consolidated' : htmlspecialchars($clerk_filter); ?>)
                            </h5>
                            <div class="table-responsive">
                                <?php if (count($expenses) > 0): ?>
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Reason</th>
                                                <th>Date & Time</th>
                                                <?php if ($clerk_filter === 'all'): ?>
                                                    <th>Logged By</th>
                                                <?php endif; ?>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($expenses as $e): ?>
                                                <tr>
                                                    <td>
                                                        <div class="text-wrap" style="max-width: 250px;">
                                                            <?php echo htmlspecialchars($e['reason']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="text-muted small">
                                                            <?php echo date('d-m-Y H:i', strtotime($e['created_at'])); ?>
                                                        </span>
                                                    </td>
                                                    <?php if ($clerk_filter === 'all'): ?>
                                                        <td>
                                                            <span class="badge bg-secondary-subtle text-secondary no-print">
                                                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($e['username']); ?>
                                                            </span>
                                                            <span class="print-only" style="font-weight: 600; text-transform: uppercase;">
                                                                <?php echo htmlspecialchars($e['username']); ?>
                                                            </span>
                                                        </td>
                                                    <?php endif; ?>
                                                    <td><strong class="text-danger">- <?php echo format_currency($e['amount']); ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-light">
                                                <td colspan="<?php echo $clerk_filter === 'all' ? '3' : '2'; ?>" class="text-end"><strong>Total Expenses:</strong></td>
                                                <td><strong class="text-danger">- <?php echo format_currency($total_expenses); ?></strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                <?php else: ?>
                                    <div class="alert alert-info py-3 mb-0">
                                        <i class="fas fa-info-circle me-2"></i> No expenses found for the selected criteria.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Reconciliation Calculations Card -->
                    <div class="col-lg-5">
                        <div class="reconciliation-math-card">
                            <h5 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-calculator me-2"></i>
                                Drawer Reconciliation Statement
                            </h5>
                            
                            <div class="math-line">
                                <span>Gross Collection Received:</span>
                                <strong><?php echo format_currency($total_received); ?></strong>
                            </div>
                            
                            <div class="math-line subtraction">
                                <span>Minus Bank/Account Payments:</span>
                                <strong>- <?php echo format_currency($total_bank_account); ?></strong>
                            </div>
                            
                            <div class="math-line subtotal">
                                <span>Net Cash Collected:</span>
                                <strong><?php echo format_currency($total_cash); ?></strong>
                            </div>
                            
                            <div class="math-line subtraction">
                                <span>Minus Expenses Incurred:</span>
                                <strong>- <?php echo format_currency($total_expenses); ?></strong>
                            </div>
                            
                            <div class="math-line final-total">
                                <div class="w-100 d-flex flex-column align-items-end">
                                    <div class="net-cash-large-box">
                                        <span>Reconciled Cash Remaining</span>
                                        <h2><?php echo format_currency($cash_remaining); ?></h2>
                                    </div>
                                    <div class="form-text mt-2 text-muted no-print">
                                        Expected physical cash balance in drawer(s).
                                    </div>
                                </div>
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
