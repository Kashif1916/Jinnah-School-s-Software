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

// Get date filters. Default to today's date if not set.
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? sanitize_input($_GET['start_date']) : date('Y-m-d');
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? sanitize_input($_GET['end_date']) : $start_date;

// Ensure start_date is not after end_date
if (strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

$username = get_username();
$user_id = get_user_id();

// Fetch payments received by this user in the date range
$query_payments = "SELECT p.*, s.name, s.father_name, s.class, s.section FROM payments p 
                   JOIN students s ON p.student_id = s.id 
                   WHERE p.received_by = ? AND DATE(p.payment_date) BETWEEN ? AND ? 
                   ORDER BY p.payment_date ASC";
$stmt = $conn->prepare($query_payments);
$stmt->bind_param('sss', $username, $start_date, $end_date);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch expenses logged by this user in the date range
$query_expenses = "SELECT * FROM expenses 
                   WHERE username = ? AND DATE(created_at) BETWEEN ? AND ? 
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
            padding: 30px;
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

        /* -------------------------------------------------------------
           WORKING UI CALCULATOR STYLING (SCREEN ONLY)
           ------------------------------------------------------------- */
        .clerk-calc-block {
            background: #22252a;
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            max-width: 100%;
        }
        .calc-screen {
            background: #111316;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 15px;
            text-align: right;
        }
        .calc-history {
            font-size: 0.8rem;
            color: #7d848f;
            min-height: 18px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-family: monospace;
        }
        .calc-display {
            font-size: 1.8rem;
            color: #ffffff;
            font-weight: bold;
            font-family: monospace;
            word-wrap: break-word;
            line-height: 1.2;
        }
        .calc-buttons {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }
        .calc-btn {
            background: #2e343d;
            border: none;
            color: white;
            padding: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.1s ease;
        }
        .calc-btn:hover { background: #3d4550; }
        .calc-btn.operator { background: #1f5f46; color: white; }
        .calc-btn.operator:hover { background: #2a7e5d; }
        .calc-btn.clear { background: #d90429; color: white; }
        .calc-btn.clear:hover { background: #ef233c; }
        .calc-btn.equal { grid-column: span 2; background: #e67e22; color: white; }
        .calc-btn.equal:hover { background: #f39c12; }

        /* Print elements layout preset */
        .print-only-header, .print-calc-output-wrapper {
            display: none;
        }

        /* -------------------------------------------------------------
           PREMIUM PRINT STYLING - SIDE-BY-SIDE SIDEBAR & COMPACT TEXT
           ------------------------------------------------------------- */
        @media print {
            @page {
                size: A4 portrait;
                margin: 0.4cm !important;
            }

            body {
                background: #ffffff !important;
                color: #000000 !important;
                font-family: 'Segoe UI', Arial, sans-serif !important;
                font-size: 10px !important;
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Unwanted elements ko clear hide karna */
            .topbar, .module-nav-panel, .no-print, button, form, .form-text, .alert .badge, .clerk-calc-block {
                display: none !important;
            }

            .print-only-header {
                display: block !important;
                margin-bottom: 15px !important;
                border-bottom: 2px solid #333 !important;
                padding-bottom: 5px !important;
            }
            .print-only-header h1 { font-size: 18px !important; margin: 0 0 2px 0 !important; }
            .print-only-header h3 { font-size: 12px !important; margin: 0 0 8px 0 !important; color: #444 !important; }
            
            .print-meta-grid {
                display: table !important;
                width: 100% !important;
                margin-bottom: 5px !important;
                font-size: 9.5px !important;
            }
            .print-meta-col {
                display: table-cell !important;
                width: 33.33% !important;
            }

            /* Side-by-Side Blueprint */
            .row {
                display: block !important;
                width: 100% !important;
                clear: both !important;
            }
            
            /* Left Side (Tables) */
            .col-lg-7 {
                width: 60% !important;
                float: left !important;
                padding-right: 15px !important;
                box-sizing: border-box !important;
            }
            
            /* Right Side (Calculator Boxes) */
            .col-lg-5 {
                width: 40% !important;
                float: right !important;
                padding-left: 5px !important;
                box-sizing: border-box !important;
            }

            /* Tables styling */
            .table-responsive {
                overflow: visible !important;
            }
            table {
                width: 100% !important;
                font-size: 9.5px !important;
                margin-bottom: 15px !important;
                border-collapse: collapse !important;
            }
            th, td {
                padding: 4px 6px !important;
                border: 1px solid #ddd !important;
            }
            thead th {
                background-color: #f2f2f2 !important;
                color: #000 !important;
            }

            .section-sub-title {
                font-size: 11px !important;
                margin-bottom: 8px !important;
                padding-bottom: 4px !important;
                margin-top: 5px !important;
            }

            /* Right side calculations block adjustments */
            .reconciliation-math-card {
                padding: 15px !important;
                background: #fdfdfd !important;
                border: 1px solid #ccc !important;
                border-left: 4px solid #1f5f46 !important;
                box-shadow: none !important;
                border-radius: 6px !important;
                margin-bottom: 15px !important;
            }
            .reconciliation-math-card::after {
                display: none !important;
            }

            .math-line {
                padding: 6px 0 !important;
                font-size: 10px !important;
            }

            .net-cash-large-box {
                padding: 10px 15px !important;
                background: #1f5f46 !important;
                color: #ffffff !important;
                border-radius: 4px !important;
                margin-top: 5px !important;
                text-align: right !important;
                display: block !important;
                width: 100% !important;
            }
            .net-cash-large-box h2 { font-size: 1.3rem !important; font-weight: bold !important; }
            .net-cash-large-box span { font-size: 8px !important; }

            /* Calculator Audit Log Section for Print */
            .print-calc-output-wrapper {
                display: block !important;
                background: #fafafa !important;
                border: 1px solid #b5b5b5 !important;
                border-top: 3px solid #e67e22 !important;
                border-radius: 6px !important;
                padding: 10px !important;
                margin-top: 15px !important;
            }
            .print-calc-heading {
                font-size: 10px !important;
                font-weight: bold !important;
                color: #333 !important;
                margin-bottom: 6px !important;
                border-bottom: 1px solid #ddd !important;
                padding-bottom: 3px !important;
            }
            .print-calc-history-box {
                font-family: 'Courier New', Courier, monospace !important;
                font-size: 10px !important;
                line-height: 1.4 !important;
                white-space: pre-wrap !important;
                background: #fff !important;
                padding: 6px !important;
                border: 1px dashed #999 !important;
                color: #000 !important;
            }

            /* Clearfix */
            .content::after {
                content: "";
                display: table !important;
                clear: both !important;
            }
            
            tr, .reconciliation-math-card, .print-calc-output-wrapper {
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

            <!-- Print Only Spreadsheet Header -->
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
                        <a href="student_record.php" class="module-nav-btn">
                            <i class="fas fa-address-book"></i> Student Record
                        </a>
                        <a href="fee_payment.php" class="module-nav-btn">
                            <i class="fas fa-money-bill-wave"></i> Fee Payment
                        </a>
                        <a href="defaulter_list.php" class="module-nav-btn">
                            <i class="fas fa-list"></i> Pending List
                        </a>
                        <a href="payment_analytics.php" class="module-nav-btn active">
                            <i class="fas fa-chart-line"></i> Analytics
                        </a>
                        <a href="expenses.php" class="module-nav-btn">
                            <i class="fas fa-wallet"></i> Expenses
                        </a>
                    </div>
                </div>

                <!-- Active Date Heading Alert -->
                <div class="alert alert-success d-flex align-items-center justify-content-between mb-4 no-print">
                    <div>
                        <i class="fas fa-calendar-day me-2"></i>
                        Showing reconciliations for: 
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
                    <span class="badge bg-success-subtle text-success border border-success">
                        <i class="fas fa-circle-check me-1"></i> Checked
                    </span>
                </div>

                <!-- Date Filter Form (Screen Only) -->
                <div class="search-section mb-4 no-print">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark">Starting Date</label>
                            <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark">Ending Date (Optional)</label>
                            <input type="date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>" class="form-control" placeholder="Select end date...">
                            <div class="form-text">Leave blank to view a single day's analytics.</div>
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

                <div class="row">
                    <!-- Detailed Lists (Left Side in Print) -->
                    <div class="col-lg-7">
                        <!-- Fee Payments Table -->
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
                                        <i class="fas fa-info-circle me-2"></i> No fee payments found for this date.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Expenses Table -->
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
                                                            <?php echo date('d-m-Y H:i', strtotime($e['created_at'])); ?>
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
                                        <i class="fas fa-info-circle me-2"></i> No expenses found for this date.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Reconciliation Calculations Card (Right Side in Print) -->
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

                        <!-- LIVE UI WORKING POCKET CALCULATOR -->
                        <div class="clerk-calc-block no-print">
                            <div class="calc-screen">
                                <div id="calcHistory" class="calc-history"></div>
                                <div id="calcDisplay" class="calc-display">0</div>
                            </div>
                            <div class="calc-buttons">
                                <button type="button" class="calc-btn clear" onclick="clearCalc()">C</button>
                                <button type="button" class="calc-btn operator" onclick="appendOp('/')">÷</button>
                                <button type="button" class="calc-btn operator" onclick="appendOp('*')">×</button>
                                <button type="button" class="calc-btn operator" onclick="appendOp('-')">-</button>
                                
                                <button type="button" class="calc-btn" onclick="appendNum('7')">7</button>
                                <button type="button" class="calc-btn" onclick="appendNum('8')">8</button>
                                <button type="button" class="calc-btn" onclick="appendNum('9')">9</button>
                                <button type="button" class="calc-btn operator" onclick="appendOp('+')">+</button>
                                
                                <button type="button" class="calc-btn" onclick="appendNum('4')">4</button>
                                <button type="button" class="calc-btn" onclick="appendNum('5')">5</button>
                                <button type="button" class="calc-btn" onclick="appendNum('6')">6</button>
                                <button type="button" class="calc-btn" onclick="deleteLast()"><i class="fas fa-backspace"></i></button>
                                
                                <button type="button" class="calc-btn" onclick="appendNum('1')">1</button>
                                <button type="button" class="calc-btn" onclick="appendNum('2')">2</button>
                                <button type="button" class="calc-btn" onclick="appendNum('3')">3</button>
                                <button type="button" class="calc-btn" onclick="appendNum('.')">.</button>
                                
                                <button type="button" class="calc-btn" onclick="appendNum('0')">0</button>
                                <button type="button" class="calc-btn equal" style="grid-column: span 3;" onclick="calculateResult()">=</button>
                            </div>
                        </div>

                        <!-- PRINT LAYOUT AUTOMATIC CALCULATOR TAPE -->
                        <div class="print-calc-output-wrapper">
                            <div class="print-calc-heading">
                                <i class="fas fa-calculator"></i> Clerk Calculator Audit Log
                            </div>
                            <div id="printCalcHistoryBox" class="print-calc-history-box">No calculator operations performed.</div>
                        </div>
                    </div>
                </div> <!-- Row End -->
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        // JS Logic for Live Working Calculator with Print Audit Trail
        let currentInput = "0";
        let fullHistoryStr = "";
        let sessionCalculations = [];

        const display = document.getElementById("calcDisplay");
        const historyView = document.getElementById("calcHistory");
        const printBox = document.getElementById("printCalcHistoryBox");

        function updateScreen() {
            display.innerText = currentInput;
            historyView.innerText = fullHistoryStr;
        }

        function appendNum(num) {
            if (currentInput === "0" && num !== ".") {
                currentInput = num;
            } else {
                if (num === "." && currentInput.includes(".")) return;
                currentInput += num;
            }
            updateScreen();
        }

        function appendOp(op) {
            let lastChar = fullHistoryStr.trim().slice(-1);
            if (currentInput === "0" && fullHistoryStr !== "") {
                if (["+", "-", "*", "/"].includes(lastChar)) {
                    fullHistoryStr = fullHistoryStr.trim().slice(0, -1) + " " + op + " ";
                    updateScreen();
                    return;
                }
            }
            
            fullHistoryStr += currentInput + " " + op + " ";
            currentInput = "0";
            updateScreen();
        }

        function clearCalc() {
            currentInput = "0";
            fullHistoryStr = "";
            updateScreen();
        }

        function deleteLast() {
            if (currentInput.length > 1) {
                currentInput = currentInput.slice(0, -1);
            } else {
                currentInput = "0";
            }
            updateScreen();
        }

        function calculateResult() {
            if (fullHistoryStr === "") return;
            
            let expression = fullHistoryStr + currentInput;
            try {
                let result = eval(expression.replace(/×/g, '*').replace(/÷/g, '/'));
                
                let auditLine = expression + " = " + result;
                sessionCalculations.push(auditLine);
                
                printBox.innerHTML = sessionCalculations.join("\n");
                
                currentInput = result.toString();
                fullHistoryStr = "";
                updateScreen();
            } catch (e) {
                display.innerText = "Error";
                setTimeout(clearCalc, 1000);
            }
        }
    </script>
</body>
</html>