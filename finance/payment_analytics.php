<?php
/**
 * Payment Analytics & Cash Reconciliation - Finance Module
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_finance();

// Get date and time filters. Default to today's start and end if not set.
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? sanitize_input($_GET['start_date']) : date('Y-m-d\T00:00');
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? sanitize_input($_GET['end_date']) : date('Y-m-d\T23:59');

// Ensure start_date is not after end_date
if (strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

$username = get_username();
$user_id = get_user_id();

// Fetch payments received by this user in the date range (Using full datetime comparison)
$query_payments = "SELECT p.*, s.name, s.father_name, s.class, s.section FROM payments p 
                   JOIN students s ON p.student_id = s.id 
                   WHERE p.received_by = ? AND p.payment_date BETWEEN ? AND ? 
                   ORDER BY p.payment_date ASC";
$stmt = $conn->prepare($query_payments);
$stmt->bind_param('sss', $username, $start_date, $end_date);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch expenses logged by this user in the date range (Using full datetime comparison)
$query_expenses = "SELECT * FROM expenses 
                   WHERE username = ? AND created_at BETWEEN ? AND ? 
                   ORDER BY created_at ASC, id ASC";
$stmt_exp = $conn->prepare($query_expenses);
$stmt_exp->bind_param('sss', $username, $start_date, $end_date);
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
    <title>Clerk Reconciliation & Analytics - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        /* Screen styling improvements */
        .reconciliation-math-card {
            background: linear-gradient(135deg, #ffffff 0%, #f9fbf9 100%);
            border-left: 5px solid var(--primary-color);
            border-radius: 12px;
            box-shadow: var(--shadow-medium);
            padding: 25px;
            margin-bottom: 20px;
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
            padding: 10px 0;
            border-bottom: 1px dashed rgba(0,0,0,0.08);
            font-size: 1.02rem;
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
            padding-top: 15px;
            margin-top: 5px;
        }

        .net-cash-large-box {
            text-align: right;
            padding: 12px 20px;
            background: linear-gradient(135deg, #1f5f46 0%, #10161b 100%);
            color: #ffffff;
            border-radius: 8px;
            box-shadow: var(--shadow-medium);
            display: inline-block;
        }

        .net-cash-large-box h2 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
        }

        .net-cash-large-box span {
            font-size: 0.8rem;
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

        /* LIVE AUDIT DENOMINATION CARD STYLING */
        .denomination-card {
            background: #ffffff;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 12px;
            box-shadow: var(--shadow-medium);
            padding: 20px;
            margin-top: 10px;
        }
        .denomination-heading {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f5f46;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            padding-bottom: 8px;
        }
        .denom-row {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        .denom-label {
            width: 60px;
            font-weight: 600;
            color: #333;
        }
        .denom-multiply {
            width: 30px;
            color: #888;
            text-align: center;
        }
        .denom-input-col {
            width: 80px;
        }
        .denom-input {
            width: 100%;
            padding: 3px 6px;
            border: 1px solid #ccc;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }
        .denom-input:focus {
            border-color: #1f5f46;
            outline: none;
        }
        .denom-equal {
            width: 30px;
            text-align: center;
            color: #888;
        }
        .denom-total-output {
            flex-grow: 1;
            text-align: right;
            font-weight: 600;
            color: #333;
        }
        .denom-grand-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 12px;
            border-top: 2px solid #1f5f46;
            font-weight: 700;
            font-size: 1.1rem;
            color: #1f5f46;
        }

        .print-only-header {
            display: none;
        }

        /* -------------------------------------------------------------
           OPTIMIZED HIGH-QUALITY PRINT STYLING
           ------------------------------------------------------------- */
        @media print {
            @page {
                size: A4 portrait;
                margin: 0cm !important; /* Maximizes printing canvas, zero margin waste */
            }

            body {
                background: #ffffff !important;
                color: #000000 !important;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
                font-size: 11.5px !important; /* Fixed print text size */
                margin: 0 !important;
                padding: 15px 20px !important; /* Safe padding away from extreme edges */
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .topbar, .module-nav-panel, .no-print, button, form, .form-text, .alert-success, .stats-grid {
                display: none !important;
            }

            .print-only-header {
                display: block !important;
                border-bottom: 2px solid var(--primary-color) !important;
                padding-bottom: 5px !important;
                margin-bottom: 12px !important;
            }

            .print-only-header h1 {
                font-size: 18px !important;
                color: var(--primary-color) !important;
                font-weight: 700 !important;
                margin: 0 0 2px 0 !important;
            }

            .print-only-header h3 {
                font-size: 12px !important;
                color: #444 !important;
                margin: 0 0 5px 0 !important;
            }

            .print-meta-grid {
                display: flex !important;
                justify-content: space-between !important;
                font-size: 11px !important;
                color: #333 !important;
                margin-bottom: 5px !important;
            }

            .row {
                display: flex !important;
                flex-direction: row !important;
                flex-wrap: nowrap !important;
                align-items: flex-start !important; 
                gap: 0px !important; /* ELIMINATED GAP BETWEEN COLUMNS COMPLETELY */
                width: 100% !important;
            }

            .col-lg-7 {
                width: 59% !important;
                flex: 0 0 59% !important;
                max-width: 59% !important;
                padding-right: 10px !important;
            }

            .col-lg-5 {
                width: 41% !important;
                flex: 0 0 41% !important;
                max-width: 41% !important;
                padding-left: 0px !important;
            }

            .table-responsive {
                overflow: visible !important;
            }

            table {
                width: 100% !important;
                margin-bottom: 12px !important;
                font-size: 10.5px !important;
            }

            table th, table td {
                padding: 4px 5px !important;
            }

            .stat-card, .reconciliation-math-card, .net-cash-large-box, .badge, .denomination-card {
                box-shadow: none !important;
                border: 1px solid rgba(0,0,0,0.15) !important;
            }

            /* CASH DRAWER RECONCILIATION BLOCK PRINT CORRECTION */
            .reconciliation-math-card {
                border-left: 4px solid var(--primary-color) !important;
                padding: 12px !important; 
                margin-bottom: 12px !important;
            }
            .reconciliation-math-card h5 {
                font-size: 12px !important;
                margin-bottom: 8px !important;
                padding-bottom: 4px !important;
            }
            .math-line {
                padding: 5px 0 !important;
                font-size: 11px !important;
            }
            .net-cash-large-box {
                padding: 8px 15px !important;
            }
            .net-cash-large-box h2 {
                font-size: 16px !important;
            }
            .net-cash-large-box span {
                font-size: 8.5px !important;
            }

            /* AUDIT LOG SINGLE LINE ALIGNMENT FIX */
            .denomination-card {
                padding: 15px !important;
                margin-top: 10px !important;
            }
            .denomination-heading {
                font-size: 12px !important;
                margin-bottom: 10px !important;
                padding-bottom: 4px !important;
            }
            .denom-row {
                margin-bottom: 6px !important;
                font-size: 11px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: flex-start !important;
            }
            .denom-label {
                width: 50px !important;
                display: inline-block !important;
            }
            .denom-multiply {
                width: 25px !important;
                display: inline-block !important;
                text-align: center !important;
            }
            .denom-input-col {
                width: 60px !important;
                display: inline-block !important;
                text-align: center !important;
            }
            .denom-input {
                border: none !important;
                background: transparent !important;
                padding: 0 !important;
                width: 100% !important;
                text-align: center !important;
                font-size: 11px !important;
            }
            .denom-equal {
                width: 25px !important;
                display: inline-block !important;
                text-align: center !important;
            }
            .denom-total-output {
                text-align: right !important;
                flex-grow: 1 !important;
                display: inline-block !important;
            }
            .denom-grand-total-row {
                margin-top: 10px !important;
                padding-top: 8px !important;
                font-size: 12px !important;
            }

            tr, .stat-card, .reconciliation-math-card, .denomination-card {
                page-break-inside: avoid !important;
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
                        <h2>Analytics & Reconciliation</h2>
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

            <div class="print-only-header">
                <h1><?php echo SITE_NAME; ?></h1>
                <h3>Clerk Cash Reconciliation & Reconciliation Statement</h3>
                <div class="print-meta-grid">
                    <div class="print-meta-col">
                        <strong>Clerk User:</strong> <?php echo htmlspecialchars($username); ?>
                    </div>
                    <div class="print-meta-col text-center">
                        <strong>Reconciliation Period:</strong> 
                        <?php 
                        if ($start_date === $end_date) {
                            echo date('d-m-Y h:i A', strtotime($start_date)) . ' (Single Point)';
                        } else {
                            echo date('d-m-Y h:i A', strtotime($start_date)) . ' to ' . date('d-m-Y h:i A', strtotime($end_date));
                        }
                        ?>
                    </div>
                    <div class="print-meta-col text-end">
                        <strong>Print Date:</strong> <?php echo date('d-m-Y h:i A'); ?>
                    </div>
                </div>
            </div>

            <div class="content">
                <div class="module-nav-panel no-print">
                    <div class="module-nav-row">
                        <a href="dashboard.php" class="module-nav-btn"><i class="fas fa-chart-bar"></i> Dashboard</a>
                        <a href="add_student.php" class="module-nav-btn"><i class="fas fa-list"></i> Add Student</a>
                        <a href="student_record.php" class="module-nav-btn"><i class="fas fa-address-book"></i> Student Record</a>
                        <a href="fee_payment.php" class="module-nav-btn"><i class="fas fa-money-bill-wave"></i> Fee Payment</a>
                        <a href="defaulter_list.php" class="module-nav-btn"><i class="fas fa-list"></i> Pending List</a>
                        <a href="payment_analytics.php" class="module-nav-btn active"><i class="fas fa-chart-line"></i> Analytics</a>
                        <a href="expenses.php" class="module-nav-btn"><i class="fas fa-wallet"></i> Expenses</a>
                        <a href="account_close.php" class="module-nav-btn"><i class="fas fa-lock"></i> Close Account</a>
                    </div>
                </div>

                <div class="alert alert-success d-flex align-items-center justify-content-between mb-4 no-print">
                    <div>
                        <i class="fas fa-calendar-day me-2"></i>
                        Showing reconciliations from: 
                        <strong>
                            <?php 
                            if ($start_date === $end_date) {
                                echo date('d-m-Y h:i A', strtotime($start_date));
                            } else {
                                echo date('d-m-Y h:i A', strtotime($start_date)) . " to " . date('d-m-Y h:i A', strtotime($end_date));
                            }
                            ?>
                        </strong>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success">
                        <i class="fas fa-circle-check me-1"></i> Checked
                    </span>
                </div>

                <div class="search-section mb-4 no-print">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark">Starting Date & Time</label>
                            <input type="datetime-local" name="start_date" value="<?php echo date('Y-m-d\TH:i', strtotime($start_date)); ?>" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark">Ending Date & Time</label>
                            <input type="datetime-local" name="end_date" value="<?php echo date('Y-m-d\TH:i', strtotime($end_date)); ?>" class="form-control" required>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn-primary flex-grow-1">
                                <i class="fas fa-sync"></i> Refresh Report
                            </button>
                            <button type="button" onclick="window.print()" class="btn-secondary">
                                <i class="fas fa-print"></i> Print Statement
                            </button>
                        </div>
                    </form>
                </div>

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

                <div class="row">
                    <div class="col-lg-7">
                        <div class="mb-4">
                            <h5 class="section-sub-title">
                                <i class="fas fa-receipt"></i>
                                Fee Payments Received (<?php echo htmlspecialchars($username); ?>)
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
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $p): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                                                        <div class="text-muted small">F/Name: <?php echo htmlspecialchars($p['father_name']); ?></div>
                                                        <div class="text-muted small print-only"><?php echo date('d-m-Y h:i A', strtotime($p['payment_date'])); ?></div>
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
                                                    <td><?php echo format_currency($p['amount']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-light">
                                                <td colspan="4" class="text-end text-success"><strong>Cash Subtotal:</strong></td>
                                                <td class="text-success"><strong><?php echo format_currency($total_cash); ?></strong></td>
                                            </tr>
                                            <tr class="table-light">
                                                <td colspan="4" class="text-end text-primary"><strong>Bank Subtotal:</strong></td>
                                                <td class="text-primary"><strong><?php echo format_currency($total_bank_account); ?></strong></td>
                                            </tr>
                                            <tr class="table-dark">
                                                <td colspan="4" class="text-end"><strong>Gross Sum:</strong></td>
                                                <td><strong><?php echo format_currency($total_received); ?></strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                <?php else: ?>
                                    <div class="alert alert-info py-3 mb-0">
                                        <i class="fas fa-info-circle me-2"></i> No fee payments found for this criteria.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <h5 class="section-sub-title">
                                <i class="fas fa-wallet"></i>
                                Expenses Logged (<?php echo htmlspecialchars($username); ?>)
                            </h5>
                            <div class="table-responsive">
                                <?php if (count($expenses) > 0): ?>
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Reason</th>
                                                <th>Date & Time</th>
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
                                                            <?php echo date('d-m-Y h:i A', strtotime($e['created_at'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><strong class="text-danger">- <?php echo format_currency($e['amount']); ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-light">
                                                <td colspan="2" class="text-end"><strong>Total Expenses:</strong></td>
                                                <td><strong class="text-danger">- <?php echo format_currency($total_expenses); ?></strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                <?php else: ?>
                                    <div class="alert alert-info py-3 mb-0">
                                        <i class="fas fa-info-circle me-2"></i> No expenses found for this criteria.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="reconciliation-math-card">
                            <h5 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-calculator me-2"></i>
                                Cash Drawer Reconciliation
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
                                </div>
                            </div>
                        </div>

                        <div class="denomination-card">
                            <div class="denomination-heading">
                                <i class="fas fa-calculator me-1"></i> Clerk Calculator Audit Log
                            </div>
                            
                            <div class="denom-row">
                                <span class="denom-label">5,000</span>
                                <span class="denom-multiply">×</span>
                                <div class="denom-input-col">
                                    <input type="number" min="0" class="denom-input" data-value="5000" oninput="calcDenom(this)" placeholder="0">
                                </div>
                                <span class="denom-equal">=</span>
                                <span class="denom-total-output" id="total-5000">0.00</span>
                            </div>

                            <div class="denom-row">
                                <span class="denom-label">1,000</span>
                                <span class="denom-multiply">×</span>
                                <div class="denom-input-col">
                                    <input type="number" min="0" class="denom-input" data-value="1000" oninput="calcDenom(this)" placeholder="0">
                                </div>
                                <span class="denom-equal">=</span>
                                <span class="denom-total-output" id="total-1000">0.00</span>
                            </div>

                            <div class="denom-row">
                                <span class="denom-label">500</span>
                                <span class="denom-multiply">×</span>
                                <div class="denom-input-col">
                                    <input type="number" min="0" class="denom-input" data-value="500" oninput="calcDenom(this)" placeholder="0">
                                </div>
                                <span class="denom-equal">=</span>
                                <span class="denom-total-output" id="total-500">0.00</span>
                            </div>

                            <div class="denom-row">
                                <span class="denom-label">100</span>
                                <span class="denom-multiply">×</span>
                                <div class="denom-input-col">
                                    <input type="number" min="0" class="denom-input" data-value="100" oninput="calcDenom(this)" placeholder="0">
                                </div>
                                <span class="denom-equal">=</span>
                                <span class="denom-total-output" id="total-100">0.00</span>
                            </div>

                            <div class="denom-row">
                                <span class="denom-label">75</span>
                                <span class="denom-multiply">×</span>
                                <div class="denom-input-col">
                                    <input type="number" min="0" class="denom-input" data-value="75" oninput="calcDenom(this)" placeholder="0">
                                </div>
                                <span class="denom-equal">=</span>
                                <span class="denom-total-output" id="total-75">0.00</span>
                            </div>

                            <div class="denom-row">
                                <span class="denom-label">50</span>
                                <span class="denom-multiply">×</span>
                                <div class="denom-input-col">
                                    <input type="number" min="0" class="denom-input" data-value="50" oninput="calcDenom(this)" placeholder="0">
                                </div>
                                <span class="denom-equal">=</span>
                                <span class="denom-total-output" id="total-50">0.00</span>
                            </div>

                            <div class="denom-row">
                                <span class="denom-label">20</span>
                                <span class="denom-multiply">×</span>
                                <div class="denom-input-col">
                                    <input type="number" min="0" class="denom-input" data-value="20" oninput="calcDenom(this)" placeholder="0">
                                </div>
                                <span class="denom-equal">=</span>
                                <span class="denom-total-output" id="total-20">0.00</span>
                            </div>

                            <div class="denom-row">
                                <span class="denom-label">10</span>
                                <span class="denom-multiply">×</span>
                                <div class="denom-input-col">
                                    <input type="number" min="0" class="denom-input" data-value="10" oninput="calcDenom(this)" placeholder="0">
                                </div>
                                <span class="denom-equal">=</span>
                                <span class="denom-total-output" id="total-10">0.00</span>
                            </div>

                            <div class="denom-grand-total-row">
                                <span>Grand Total:</span>
                                <span id="denom-grand-total">0.00</span>
                            </div>
                        </div>
                    </div>
                </div> </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        // Real-time currency denomination calculation logic
        function calcDenom(inputElement) {
            const noteValue = parseInt(inputElement.getAttribute('data-value'));
            const count = parseInt(inputElement.value) || 0;
            const lineTotal = noteValue * count;
            
            // Update individual note total
            document.getElementById('total-' + noteValue).innerText = lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            // Calculate Grand Total
            let grandTotal = 0;
            const allInputs = document.querySelectorAll('.denom-input');
            allInputs.forEach(input => {
                const val = parseInt(input.getAttribute('data-value'));
                const qty = parseInt(input.value) || 0;
                grandTotal += (val * qty);
            });
            
            document.getElementById('denom-grand-total').innerText = grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    </script>
</body>
</html>