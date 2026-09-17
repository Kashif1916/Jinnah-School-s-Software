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

// Handle AJAX Save Audit Log (Master Panel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_audit_log') {
    header('Content-Type: application/json');
    $target_clerk = sanitize_input($_POST['target_clerk'] ?? '');
    $log_date = sanitize_input($_POST['log_date'] ?? date('Y-m-d'));
    
    if (empty($target_clerk) || $target_clerk === 'all') {
        echo json_encode(['success' => false, 'message' => 'Please select a specific clerk.']);
        exit();
    }

    $d_5000 = max(0, intval($_POST['d_5000'] ?? 0));
    $d_1000 = max(0, intval($_POST['d_1000'] ?? 0));
    $d_500  = max(0, intval($_POST['d_500'] ?? 0));
    $d_100  = max(0, intval($_POST['d_100'] ?? 0));
    $d_75   = max(0, intval($_POST['d_75'] ?? 0));
    $d_50   = max(0, intval($_POST['d_50'] ?? 0));
    $d_20   = max(0, intval($_POST['d_20'] ?? 0));
    $d_10   = max(0, intval($_POST['d_10'] ?? 0));
    $grand_total = floatval($_POST['grand_total'] ?? 0);
    $cash_collected = floatval($_POST['cash_collected'] ?? 0);
    $difference = floatval($_POST['difference'] ?? 0);

    // Get user ID of target clerk
    $u_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    if ($u_stmt) {
        $u_stmt->bind_param('s', $target_clerk);
        $u_stmt->execute();
        $u_res = $u_stmt->get_result()->fetch_assoc();
        $target_user_id = $u_res['id'] ?? null;
        $u_stmt->close();
    } else {
        $target_user_id = null;
    }

    $stmt_save = $conn->prepare("
        INSERT INTO calculator_audit_logs 
        (user_id, username, log_date, d_5000, d_1000, d_500, d_100, d_75, d_50, d_20, d_10, grand_total, cash_collected, difference) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            d_5000 = VALUES(d_5000),
            d_1000 = VALUES(d_1000),
            d_500 = VALUES(d_500),
            d_100 = VALUES(d_100),
            d_75 = VALUES(d_75),
            d_50 = VALUES(d_50),
            d_20 = VALUES(d_20),
            d_10 = VALUES(d_10),
            grand_total = VALUES(grand_total),
            cash_collected = VALUES(cash_collected),
            difference = VALUES(difference),
            updated_at = NOW()
    ");
    if ($stmt_save) {
        $stmt_save->bind_param('issiiiiiiiiddd', $target_user_id, $target_clerk, $log_date, $d_5000, $d_1000, $d_500, $d_100, $d_75, $d_50, $d_20, $d_10, $grand_total, $cash_collected, $difference);
        $res = $stmt_save->execute();
        $stmt_save->close();
        echo json_encode(['success' => $res, 'message' => $res ? 'Audit log updated.' : 'Database error.']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit();
}

$success = '';
$error = '';

// Handle Close Account Action for specific clerk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_clerk_account'])) {
    $target_clerk = isset($_POST['target_clerk']) ? sanitize_input($_POST['target_clerk']) : '';
    
    if (empty($target_clerk) || $target_clerk === 'all') {
        $error = "Please select a specific clerk first from the filter option above to close their account!";
    } else {
        // Get user ID of the selected clerk
        $user_query = $conn->prepare("SELECT id FROM users WHERE username = ?");
        if ($user_query) {
            $user_query->bind_param('s', $target_clerk);
            $user_query->execute();
            $user_res = $user_query->get_result();
            
            if ($user_res && $user_res->num_rows > 0) {
                $user_data = $user_res->fetch_assoc();
                $clerk_user_id = $user_data['id'];
                $next_midnight = date('Y-m-d 00:00:00', strtotime('tomorrow'));
                
                $query = "UPDATE users SET is_frozen = 1, frozen_until = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                if ($stmt) {
                    $stmt->bind_param('si', $next_midnight, $clerk_user_id);
                    if ($stmt->execute()) {
                        $stmt->close();

                        // Log this account close event
                        $master_username = get_username();
                        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                        $log_stmt = $conn->prepare("INSERT INTO account_close_logs (user_id, username, role, closed_by, closed_at, frozen_until, ip_address, status) VALUES (?, ?, 'finance', ?, NOW(), ?, ?, 'closed_by_master')");
                        if ($log_stmt) {
                            $log_stmt->bind_param('issss', $clerk_user_id, $target_clerk, $master_username, $next_midnight, $ip_address);
                            $log_stmt->execute();
                            $log_stmt->close();
                        }

                        $success = "Account for clerk <strong>" . htmlspecialchars($target_clerk) . "</strong> has been successfully received and closed for today. It will unfreeze at midnight!";
                    } else {
                        $error = 'Failed to close clerk account: ' . $conn->error;
                        $stmt->close();
                    }
                } else {
                    $error = 'Database query error: ' . $conn->error;
                }
            } else {
                $error = "Selected clerk user was not found in database.";
            }
            $user_query->close();
        }
    }
}

// Get date and time filters. Default to today's start and end if not set.
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? sanitize_input($_GET['start_date']) : date('Y-m-d\T00:00');
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? sanitize_input($_GET['end_date']) : date('Y-m-d\T15:00');

// Ensure start_date is not after end_date
if (strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// Get clerk filter. Clean up input for URL decoding (handles spaces and + symbols)
$clerk_filter = isset($_GET['clerk']) ? trim(sanitize_input($_GET['clerk'])) : 'all';
if (empty($clerk_filter)) {
    $clerk_filter = 'all';
}
$selected_log_date = date('Y-m-d', strtotime($start_date));

// Fetch calculator audit log for selected clerk and date
$master_audit_log = null;
if ($clerk_filter !== 'all') {
    $stmt_m_audit = $conn->prepare("SELECT * FROM calculator_audit_logs WHERE username = ? AND log_date = ?");
    if ($stmt_m_audit) {
        $stmt_m_audit->bind_param('ss', $clerk_filter, $selected_log_date);
        $stmt_m_audit->execute();
        $master_audit_log = $stmt_m_audit->get_result()->fetch_assoc();
        $stmt_m_audit->close();
    }
}

// Fetch list of unique clerks/users who exist in the database
$clerk_list = [];
$clerk_query = $conn->query("
    SELECT DISTINCT username FROM users WHERE role IN ('finance', 'master')
    ORDER BY username ASC
");
if ($clerk_query) {
    while ($row = $clerk_query->fetch_assoc()) {
        if (!empty($row['username'])) {
            $clerk_list[] = $row['username'];
        }
    }
}

// Fetch payments based on clerk filter (Using full datetime comparison)
$payments = [];
if ($clerk_filter === 'all') {
    $query_payments = "SELECT p.*, s.name, s.father_name, s.class, s.section FROM payments p 
                       JOIN students s ON p.student_id = s.id 
                       WHERE p.payment_date BETWEEN ? AND ? 
                       ORDER BY p.payment_date ASC";
    $stmt = $conn->prepare($query_payments);
    if ($stmt) {
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} else {
    $query_payments = "SELECT p.*, s.name, s.father_name, s.class, s.section FROM payments p 
                       JOIN students s ON p.student_id = s.id 
                       WHERE p.received_by = ? AND p.payment_date BETWEEN ? AND ? 
                       ORDER BY p.payment_date ASC";
    $stmt = $conn->prepare($query_payments);
    if ($stmt) {
        $stmt->bind_param('sss', $clerk_filter, $start_date, $end_date);
        $stmt->execute();
        $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Fetch expenses based on clerk filter (Using full datetime comparison)
$expenses = [];
if ($clerk_filter === 'all') {
    $query_expenses = "SELECT * FROM expenses 
                       WHERE created_at BETWEEN ? AND ? 
                       ORDER BY created_at ASC, id ASC";
    $stmt_exp = $conn->prepare($query_expenses);
    if ($stmt_exp) {
        $stmt_exp->bind_param('ss', $start_date, $end_date);
        $stmt_exp->execute();
        $expenses = $stmt_exp->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_exp->close();
    }
} else {
    $query_expenses = "SELECT * FROM expenses 
                       WHERE username = ? AND created_at BETWEEN ? AND ? 
                       ORDER BY created_at ASC, id ASC";
    $stmt_exp = $conn->prepare($query_expenses);
    if ($stmt_exp) {
        $stmt_exp->bind_param('sss', $clerk_filter, $start_date, $end_date);
        $stmt_exp->execute();
        $expenses = $stmt_exp->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_exp->close();
    }
}

// Calculations & Receipt Grouping
$total_received = 0;
$total_cash = 0;
$total_bank_account = 0;
$receipts_summary = [];

foreach ($payments as $p) {
    $amount = floatval($p['amount']);
    $total_received += $amount;
    
    $mode = strtolower(trim($p['payment_mode']));
    if ($mode === 'cash') {
        $total_cash += $amount;
    } else {
        $total_bank_account += $amount;
    }

    $r_num = !empty($p['receipt_number']) ? $p['receipt_number'] : sprintf('%06d', $p['id']);
    if (!isset($receipts_summary[$r_num])) {
        $receipts_summary[$r_num] = [
            'receipt_number' => $r_num,
            'payment_date'  => $p['payment_date'],
            'received_by'   => $p['received_by'],
            'payment_mode'  => $p['payment_mode'],
            'total_amount'  => 0,
            'payment_ids'   => []
        ];
    }
    $receipts_summary[$r_num]['total_amount'] += $amount;
    $receipts_summary[$r_num]['payment_ids'][] = $p['id'];
}

$total_expenses = 0;
foreach ($expenses as $e) {
    $total_expenses += floatval($e['amount']);
}

// Cash in hand = Total Cash Payments - Total Expenses
$cash_remaining = $total_cash - $total_expenses;

// Days covered in the selected range
$start_day = date('Y-m-d', strtotime($start_date));
$end_day   = date('Y-m-d', strtotime($end_date));

$period_days = [];
$c_ts = strtotime($start_day);
$e_ts = strtotime($end_day);
while ($c_ts <= $e_ts) {
    $period_days[] = date('Y-m-d', $c_ts);
    $c_ts = strtotime('+1 day', $c_ts);
}
$is_single_day = (count($period_days) === 1);

// Daily cash & expense mapping
$day_cash_payments = [];
$day_bank_payments = [];
$day_clerk_cash = [];

foreach ($payments as $p) {
    $p_day = date('Y-m-d', strtotime($p['payment_date']));
    $p_amt = floatval($p['amount']);
    $p_mode = strtolower(trim($p['payment_mode']));
    $p_clerk = $p['received_by'];
    
    if ($p_mode === 'cash') {
        $day_cash_payments[$p_day] = ($day_cash_payments[$p_day] ?? 0) + $p_amt;
        $day_clerk_cash[$p_day][$p_clerk] = ($day_clerk_cash[$p_day][$p_clerk] ?? 0) + $p_amt;
    } else {
        $day_bank_payments[$p_day] = ($day_bank_payments[$p_day] ?? 0) + $p_amt;
    }
}

$day_expenses_map = [];
$day_clerk_expenses = [];

foreach ($expenses as $e) {
    $e_day = date('Y-m-d', strtotime($e['created_at']));
    $e_amt = floatval($e['amount']);
    $e_clerk = $e['username'];
    
    $day_expenses_map[$e_day] = ($day_expenses_map[$e_day] ?? 0) + $e_amt;
    $day_clerk_expenses[$e_day][$e_clerk] = ($day_clerk_expenses[$e_day][$e_clerk] ?? 0) + $e_amt;
}

// Fetch account close logs for the date range (Fixed parameter binding count)
$account_close_logs_by_date = [];
$account_close_logs_by_date_clerk = [];

if ($clerk_filter !== 'all') {
    $stmt_close_log = $conn->prepare("SELECT id, user_id, username, closed_by, closed_at, status, DATE(closed_at) AS close_date
                                       FROM account_close_logs
                                       WHERE username = ?
                                         AND DATE(closed_at) BETWEEN ? AND ?
                                         AND status IN ('closed', 'closed_by_master', 'frozen_by_master')
                                       ORDER BY closed_at DESC");
    if ($stmt_close_log) {
        $stmt_close_log->bind_param('sss', $clerk_filter, $start_day, $end_day);
        $stmt_close_log->execute();
        $res_cl = $stmt_close_log->get_result();
        while ($row = $res_cl->fetch_assoc()) {
            $cd = $row['close_date'];
            if (!isset($account_close_logs_by_date[$cd])) {
                $account_close_logs_by_date[$cd] = $row;
            }
        }
        $stmt_close_log->close();
    }
} else {
    $stmt_close_log = $conn->prepare("SELECT id, user_id, username, closed_by, closed_at, status, DATE(closed_at) AS close_date
                                       FROM account_close_logs
                                       WHERE DATE(closed_at) BETWEEN ? AND ?
                                         AND status IN ('closed', 'closed_by_master', 'frozen_by_master')
                                       ORDER BY closed_at DESC");
    if ($stmt_close_log) {
        $stmt_close_log->bind_param('ss', $start_day, $end_day);
        $stmt_close_log->execute();
        $res_cl = $stmt_close_log->get_result();
        while ($row = $res_cl->fetch_assoc()) {
            $cd = $row['close_date'];
            $cu = $row['username'];
            if (!isset($account_close_logs_by_date_clerk[$cd][$cu])) {
                $account_close_logs_by_date_clerk[$cd][$cu] = $row;
            }
        }
        $stmt_close_log->close();
    }
}

// Separate days into Received (Account Frozen) vs Not Received
$days_received = [];
$days_not_received = [];

if ($clerk_filter !== 'all') {
    foreach ($period_days as $d) {
        $c_in = $day_cash_payments[$d] ?? 0;
        $exp = $day_expenses_map[$d] ?? 0;
        $net = $c_in - $exp;
        
        if (isset($account_close_logs_by_date[$d])) {
            $days_received[] = [
                'date' => $d,
                'clerk' => $clerk_filter,
                'net_cash' => $net,
                'cash_in' => $c_in,
                'expense' => $exp,
                'log' => $account_close_logs_by_date[$d]
            ];
        } else {
            $days_not_received[] = [
                'date' => $d,
                'clerk' => $clerk_filter,
                'net_cash' => $net,
                'cash_in' => $c_in,
                'expense' => $exp
            ];
        }
    }
} else {
    // Combined / All Clerks mode
    foreach ($period_days as $d) {
        $active_clerks = [];
        if (isset($day_clerk_cash[$d])) {
            foreach (array_keys($day_clerk_cash[$d]) as $cu) $active_clerks[$cu] = true;
        }
        if (isset($day_clerk_expenses[$d])) {
            foreach (array_keys($day_clerk_expenses[$d]) as $cu) $active_clerks[$cu] = true;
        }
        if (isset($account_close_logs_by_date_clerk[$d])) {
            foreach (array_keys($account_close_logs_by_date_clerk[$d]) as $cu) $active_clerks[$cu] = true;
        }

        foreach (array_keys($active_clerks) as $cu) {
            $c_cash = $day_clerk_cash[$d][$cu] ?? 0;
            $c_exp = $day_clerk_expenses[$d][$cu] ?? 0;
            $c_net = $c_cash - $c_exp;
            $is_closed = isset($account_close_logs_by_date_clerk[$d][$cu]);
            
            if ($is_closed) {
                $days_received[] = [
                    'date' => $d,
                    'clerk' => $cu,
                    'net_cash' => $c_net,
                    'cash_in' => $c_cash,
                    'expense' => $c_exp,
                    'log' => $account_close_logs_by_date_clerk[$d][$cu]
                ];
            } else {
                $days_not_received[] = [
                    'date' => $d,
                    'clerk' => $cu,
                    'net_cash' => $c_net,
                    'cash_in' => $c_cash,
                    'expense' => $c_exp
                ];
            }
        }
    }
}

// For backwards compatibility with single-day components
$account_close_log = ($clerk_filter !== 'all' && isset($account_close_logs_by_date[$selected_log_date])) ? $account_close_logs_by_date[$selected_log_date] : null;
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

        /* HIGH-CONTRAST BLACK TEXT STYLING FOR BADGES AND SUMMARY CARDS */
        .audit-badge-dark {
            color: #111111 !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            border: 1px solid rgba(0,0,0,0.2) !important;
        }

        .text-dark-contrast {
            color: #111111 !important;
            font-weight: 600 !important;
        }

        /* CLERK CALCULATOR AUDIT LOG CARD STYLING */
        .denomination-card {
            background: #ffffff;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 12px;
            box-shadow: var(--shadow-medium);
            padding: 20px;
            margin-top: 15px;
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
        .denom-cash-collected-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 5px;
            padding-top: 5px;
            font-weight: 600;
            font-size: 1.05rem;
            color: #555;
        }
        .difference-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed rgba(0,0,0,0.15);
            font-weight: 700;
            font-size: 1.1rem;
        }

        .report-logo {
            width: 80px !important;
            height: auto !important;
        }

        .print-only-header {
            display: none;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0.3cm !important;
            }

            body {
                background: #ffffff !important;
                color: #000000 !important;
                font-family: 'Segoe UI', Arial, sans-serif !important;
                font-size: 9.5px !important;
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .topbar, .module-nav-panel, .no-print, button, form, 
            .form-text, .alert-success, .stats-grid, .stat-card {
                display: none !important;
            }

            .print-only-header {
                display: block !important;
                border-bottom: none !important;
                padding-bottom: 0px !important;
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

            .row {
                display: flex !important;
                flex-direction: row !important;
                flex-wrap: nowrap !important;
                gap: 12px !important;
                width: 100% !important;
            }

            .col-lg-7 {
                width: 60% !important;
                flex: 0 0 60% !important;
                max-width: 60% !important;
            }

            .col-lg-5 {
                width: 38% !important;
                flex: 0 0 38% !important;
                max-width: 38% !important;
            }

            .table-responsive {
                overflow: visible !important;
            }

            table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-bottom: 10px !important;
                font-size: 9px !important;
            }

            table th, table td {
                padding: 4px 5px !important;
                border: 1px solid #ddd !important;
            }

            .reconciliation-math-card {
                border: 1px solid #ccc !important;
                border-left: 4px solid #1f5f46 !important;
                padding: 10px !important;
                background: #fdfdfd !important;
                box-shadow: none !important;
            }

            .math-line, .reconciliation-math-card div, .reconciliation-math-card p {
                padding: 4px 0 !important;
                font-size: 8.5px !important;
                line-height: 1.2 !important;
            }

            .net-cash-large-box {
                background: #1f5f46 !important;
                color: #ffffff !important;
                padding: 6px 10px !important;
                border-radius: 4px !important;
                margin-top: 8px !important;
            }

            .net-cash-large-box h2, .net-cash-large-box .h2 {
                font-size: 13px !important;
                font-weight: 700 !important;
                margin: 0 !important;
            }

            .net-cash-large-box span, .net-cash-large-box small {
                font-size: 8px !important;
            }

            .denomination-card {
                display: block !important;
                border: 1px solid #ddd !important;
                border-radius: 4px !important;
                padding: 8px 12px !important;
                margin-top: 10px !important;
                box-shadow: none !important;
                background: #ffffff !important;
            }
            .denomination-heading {
                font-size: 11px !important;
                margin-bottom: 6px !important;
                padding-bottom: 4px !important;
            }
            .denom-row {
                margin-bottom: 4px !important;
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
                width: 20px !important;
                display: inline-block !important;
                text-align: center !important;
            }
            .denom-input-col {
                width: 50px !important;
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
                font-weight: bold !important;
                color: #000 !important;
                -webkit-appearance: none !important;
                -moz-appearance: textfield !important;
            }
            .denom-equal {
                width: 20px !important;
                display: inline-block !important;
                text-align: center !important;
            }
            .denom-total-output {
                text-align: right !important;
                flex-grow: 1 !important;
                display: inline-block !important;
                font-weight: bold !important;
            }
            .denom-grand-total-row {
                margin-top: 8px !important;
                padding-top: 6px !important;
                font-size: 12px !important;
            }
            .denom-cash-collected-row {
                margin-top: 4px !important;
                padding-top: 4px !important;
                font-size: 11px !important;
            }
            .difference-row {
                margin-top: 6px !important;
                padding-top: 6px !important;
                font-size: 12px !important;
            }

            tr, .reconciliation-math-card, .denomination-card {
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

            <div class="print-only-header">
                <div style="display: flex; align-items: center; gap: 15px; border-bottom: 2px solid #1f5f46; padding-bottom: 8px; margin-bottom: 8px;">
                    <?php echo render_system_logo('report-logo'); ?>
                    <div style="text-align: left;">
                        <h2 style="margin: 0; color: #1f5f46; font-size: 20px; font-weight: bold;">Jinnah School And Intermediate College Khushab</h2>
                        <h3 style="margin: 3px 0 0 0; color: #333; font-size: 13px; font-weight: normal; border: none; padding: 0;">
                            <?php 
                            if ($clerk_filter === 'all') {
                                echo "Principal's Consolidated Reconciliation Statement (All Clerks)";
                            } else {
                                echo "Clerk Cash Reconciliation Statement (Clerk: " . htmlspecialchars($clerk_filter) . ")";
                            }
                            ?>
                        </h3>
                    </div>
                </div>
                <div class="print-meta-grid">
                    <div class="print-meta-col">
                        <strong>Reporting User:</strong> Principal (master)
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
                <div class="print-meta-grid" style="margin-top: 3px; padding-top: 3px; border-top: 1px dashed #bbb;">
                    <div class="print-meta-col" style="grid-column: span 3;">
                        <strong>Account Freeze / Cash Handover Audit:</strong>
                        <?php 
                        if ($clerk_filter !== 'all') {
                            if ($is_single_day) {
                                if (!empty($days_received)) {
                                    echo "<span style='color: #1f5f46; font-weight: bold;'>✓ Amount Received & Account Closed</span> (Closed at " . date('d-M h:i A', strtotime($days_received[0]['log']['closed_at'])) . " by " . htmlspecialchars($days_received[0]['log']['closed_by']) . ")";
                                } else {
                                    echo "<span style='color: #dc3545; font-weight: bold;'>✗ Cash NOT Received (Account Not Frozen for " . date('d-M-Y', strtotime($start_day)) . ")</span>";
                                }
                            } else {
                                echo "<span style='color: #1f5f46; font-weight: bold;'>" . count($days_received) . " Day(s) Received</span> | <span style='color: #dc3545; font-weight: bold;'>" . count($days_not_received) . " Day(s) Cash NOT Received</span>";
                            }
                        } else {
                            echo "<span style='color: #1f5f46; font-weight: bold;'>" . count($days_received) . " Received</span> | <span style='color: #dc3545; font-weight: bold;'>" . count($days_not_received) . " Pending Handover</span>";
                        }
                        ?>
                    </div>
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
                        <a href="payment_analytics.php" class="module-nav-btn active"><i class="fas fa-chart-line"></i> Analytics</a>
                        <a href="receipt_analysis.php" class="module-nav-btn">
                            <i class="fas fa-receipt"></i> Receipt Analysis
                        </a>
                        <a href="expenses.php" class="module-nav-btn"><i class="fas fa-wallet"></i> Expenses</a>
                        <a href="data_correction.php" class="module-nav-btn"><i class="fas fa-edit"></i> Data Correction</a>
                        <a href="promotion.php" class="module-nav-btn"><i class="fas fa-arrow-up"></i> Promotion</a>
                        <a href="drop_student.php" class="module-nav-btn"><i class="fas fa-trash"></i> Drop Student</a>
                        <a href="delete_student.php" class="module-nav-btn ">
                            <i class="fas fa-user-minus text-success"></i> Delete Student
                        </a>
                        <a href="users.php" class="module-nav-btn"><i class="fas fa-users-cog"></i> Users</a>
                        <a href="account_close_log.php" class="module-nav-btn"><i class="fas fa-lock"></i> Close Logs</a>
                        <a href="receipt_note.php" class="module-nav-btn"><i class="fas fa-sticky-note"></i> Custom Note</a>
                        <a href="../help.php" class="module-nav-btn">
                            <i class="fas fa-question-circle text-success"></i> Help & About
                        </a>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

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
                                echo date('d-m-Y h:i A', strtotime($start_date));
                            } else {
                                echo date('d-m-Y h:i A', strtotime($start_date)) . " to " . date('d-m-Y h:i A', strtotime($end_date));
                            }
                            ?>
                        </strong>
                    </div>
                    <span class="badge bg-success text-white">
                        <i class="fas fa-eye me-1"></i> Principal View
                    </span>
                </div>

                <div class="search-section mb-4 no-print">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-dark">Starting Date & Time</label>
                            <input type="datetime-local" name="start_date" value="<?php echo date('Y-m-d\TH:i', strtotime($start_date)); ?>" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-dark">Ending Date & Time</label>
                            <input type="datetime-local" name="end_date" value="<?php echo date('Y-m-d\TH:i', strtotime($end_date)); ?>" class="form-control" required>
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

                <!-- Account Freeze & Cash Handover Audit Section (Screen) -->
                <div class="account-freeze-audit-card mb-4 no-print">
                    <?php if ($clerk_filter !== 'all'): ?>
                        <?php if ($is_single_day): ?>
                            <?php if (!empty($days_received)): 
                                $rec_entry = $days_received[0];
                            ?>
                                <div class="alert alert-success border-2 shadow-sm d-flex align-items-center justify-content-between p-3 mb-0" style="border-radius: 10px; border-left: 6px solid #198754 !important;">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; font-size: 24px; flex-shrink: 0;">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div>
                                            <h5 class="alert-heading mb-1 text-success fw-bold">
                                                <i class="fas fa-calendar-check me-1"></i> Amount Received for this Day (<?php echo date('d-M-Y', strtotime($start_day)); ?>)
                                            </h5>
                                            <div class="text-dark">
                                                Clerk <strong><?php echo htmlspecialchars($clerk_filter); ?></strong>'s account was frozen and cash was received: 
                                                <strong class="text-success fs-5"><?php echo format_currency($rec_entry['net_cash']); ?></strong>.
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-lock me-1"></i> Account closed & frozen at 
                                                <strong><?php echo date('d-M-Y h:i A', strtotime($rec_entry['log']['closed_at'])); ?></strong> 
                                                by <strong><?php echo htmlspecialchars($rec_entry['log']['closed_by']); ?></strong>.
                                            </small>
                                        </div>
                                    </div>
                                    <span class="badge bg-success fs-6 px-3 py-2 text-uppercase shadow-sm">
                                        <i class="fas fa-check-double me-1"></i> Amount Received
                                    </span>
                                </div>
                            <?php else: 
                                $not_rec_entry = $days_not_received[0];
                            ?>
                                <div class="alert alert-danger border-2 shadow-sm d-flex align-items-center justify-content-between p-3 mb-0" style="border-radius: 10px; border-left: 6px solid #dc3545 !important;">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; font-size: 24px; flex-shrink: 0;">
                                            <i class="fas fa-times-circle"></i>
                                        </div>
                                        <div>
                                            <h5 class="alert-heading mb-1 text-danger fw-bold">
                                                <i class="fas fa-exclamation-triangle me-1"></i> Cash NOT Received for this Day (<?php echo date('d-M-Y', strtotime($start_day)); ?>)
                                            </h5>
                                            
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i> Cash has not been handed over yet. Use the "Received and Close Account" button in the Drawer Reconciliation panel below after physically collecting the cash.
                                            </small>
                                        </div>
                                    </div>
                                    <span class="badge bg-danger fs-6 px-3 py-2 text-uppercase shadow-sm">
                                        <i class="fas fa-exclamation-circle me-1"></i> Cash NOT Received
                                    </span>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Multiple Days Selected for Specific Clerk -->
                            <div class="card shadow-sm border-0 overflow-hidden" style="border-radius: 12px; border: 1px solid #dee2e6;">
                                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <h5 class="mb-0 fw-bold text-dark">
                                            <i class="fas fa-shield-alt text-success me-2"></i>
                                            Account Freeze & Cash Handover Audit (<?php echo count($period_days); ?> Days Selected)
                                        </h5>
                                        <small class="text-muted">
                                            Clerk: <strong><?php echo htmlspecialchars($clerk_filter); ?></strong> | 
                                            Range: <strong><?php echo date('d-M-Y', strtotime($start_day)); ?></strong> to <strong><?php echo date('d-M-Y', strtotime($end_day)); ?></strong>
                                        </small>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <span class="badge bg-success text-white px-3 py-2 shadow-sm audit-badge-dark">
                                            <i class="fas fa-check-circle me-1"></i> <?php echo count($days_received); ?> Days Received
                                        </span>
                                        <span class="badge bg-danger text-white px-3 py-2 shadow-sm audit-badge-dark">
                                            <i class="fas fa-times-circle me-1"></i> <?php echo count($days_not_received); ?> Days NOT Received
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body p-3">
                                    <?php if (!empty($days_not_received)): ?>
                                        <div class="alert alert-danger border-danger p-3 mb-3" style="border-radius: 8px; background-color: #f8d7da;">
                                            <div class="fw-bold text-dark-contrast mb-2">
                                                <i class="fas fa-exclamation-triangle me-1 text-danger"></i> Cash NOT Received on the following <?php echo count($days_not_received); ?> day(s):
                                            </div>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($days_not_received as $dnr): ?>
                                                    <span class="badge bg-white border border-danger text-dark px-3 py-2 shadow-sm" style="font-size: 12px; font-weight: 600;">
                                                        <strong style="color:#000;"><?php echo date('d-M-Y (D)', strtotime($dnr['date'])); ?></strong>: 
                                                        Cash Due: <strong style="color:#dc3545;"><?php echo format_currency($dnr['net_cash']); ?></strong> (Not Received)
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($days_received)): ?>
                                        <div class="alert alert-success border-success p-3 mb-3" style="border-radius: 8px; background-color: #d1e7dd;">
                                            <div class="fw-bold text-dark-contrast mb-2">
                                                <i class="fas fa-check-circle me-1 text-success"></i> Amount Received & Account Frozen on the following <?php echo count($days_received); ?> day(s):
                                            </div>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($days_received as $dr): ?>
                                                    <span class="badge bg-white border border-success text-dark px-3 py-2 shadow-sm" style="font-size: 12px; font-weight: 600;">
                                                        <strong style="color:#000;"><?php echo date('d-M-Y (D)', strtotime($dr['date'])); ?></strong>: 
                                                        Received: <strong style="color:#1f5f46;"><?php echo format_currency($dr['net_cash']); ?></strong> 
                                                        <span style="color:#555;">(by <?php echo htmlspecialchars($dr['log']['closed_by']); ?> at <?php echo date('h:i A', strtotime($dr['log']['closed_at'])); ?>)</span>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Day-by-Day Table Breakdown -->
                                    <div class="table-responsive">
                                        <table class="table table-hover table-bordered align-middle mb-0" style="font-size: 12px;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Cash Received</th>
                                                    <th>Expenses</th>
                                                    <th>Net Cash (Drawer)</th>
                                                    <th>Freeze / Handover Status</th>
                                                    <th>Closed By / At</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($period_days as $d): 
                                                    $is_rec = isset($account_close_logs_by_date[$d]);
                                                    $log_item = $is_rec ? $account_close_logs_by_date[$d] : null;
                                                    $c_in = $day_cash_payments[$d] ?? 0;
                                                    $exp = $day_expenses_map[$d] ?? 0;
                                                    $net = $c_in - $exp;
                                                ?>
                                                    <tr class="<?php echo $is_rec ? 'table-success-subtle' : 'table-danger-subtle'; ?>">
                                                        <td><strong><?php echo date('d-M-Y (D)', strtotime($d)); ?></strong></td>
                                                        <td class="text-success fw-bold"><?php echo format_currency($c_in); ?></td>
                                                        <td class="text-danger fw-bold">- <?php echo format_currency($exp); ?></td>
                                                        <td class="fw-bold fs-6"><?php echo format_currency($net); ?></td>
                                                        <td>
                                                            <?php if ($is_rec): ?>
                                                                <span class="badge bg-success text-white">
                                                                    <i class="fas fa-check-circle me-1"></i> Amount Received & Frozen
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger text-white">
                                                                    <i class="fas fa-times-circle me-1"></i> Cash NOT Received
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="small">
                                                            <?php if ($is_rec): ?>
                                                                <span class="text-dark fw-bold">
                                                                    <i class="fas fa-user-check me-1 text-success"></i>
                                                                    <?php echo htmlspecialchars($log_item['closed_by']); ?>
                                                                </span>
                                                                <span class="text-muted d-block" style="font-size: 11px;">
                                                                    <?php echo date('d-M-Y h:i A', strtotime($log_item['closed_at'])); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-danger fw-bold"><i class="fas fa-lock-open me-1"></i> Account Not Frozen</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Combined / All Clerks Mode (HIGH-CONTRAST TEXT UPDATE) -->
                        <div class="card shadow-sm border-0 overflow-hidden" style="border-radius: 12px; border: 1px solid #dee2e6;">
                            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <h5 class="mb-0 fw-bold text-dark">
                                        <i class="fas fa-users-cog text-success me-2"></i>
                                         Cash Handover Audit (All Clerks)
                                    </h5>
                                    <small class="text-muted">
                                        Range: <strong><?php echo date('d-M-Y', strtotime($start_day)); ?></strong> to <strong><?php echo date('d-M-Y', strtotime($end_day)); ?></strong> (<?php echo count($period_days); ?> Days)
                                    </small>
                                </div>
                                <div class="d-flex gap-2">
                                    <span class="badge bg-success text-white px-3 py-2 shadow-sm audit-badge-dark">
                                        <i class="fas fa-check-circle me-1"></i> <?php echo count($days_received); ?> Received
                                    </span>
                                    <span class="badge bg-danger text-white px-3 py-2 shadow-sm audit-badge-dark">
                                        <i class="fas fa-times-circle me-1"></i> <?php echo count($days_not_received); ?> NOT Received
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                <?php if (!empty($days_not_received)): ?>
                                    <div class="alert alert-danger border-danger p-3 mb-3" style="border-radius: 8px; background-color: #f8d7da;">
                                        <div class="fw-bold text-dark-contrast mb-2">
                                            <i class="fas fa-exclamation-triangle me-1 text-danger"></i> Cash NOT Received for the following clerk(s) and day(s):
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($days_not_received as $dnr): ?>
                                                <span class="badge bg-white border border-danger text-dark px-3 py-2 shadow-sm" style="font-size: 12px; font-weight: 600;">
                                                    <strong style="color: #000;"><?php echo date('d-M-Y', strtotime($dnr['date'])); ?></strong> | 
                                                    Clerk: <strong style="color: #000;"><?php echo htmlspecialchars($dnr['clerk']); ?></strong> | 
                                                    Cash Due: <strong style="color: #dc3545;"><?php echo format_currency($dnr['net_cash']); ?></strong> (Not Received)
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($days_received)): ?>
                                    <div class="alert alert-success border-success p-3 mb-3" style="border-radius: 8px; background-color: #d1e7dd;">
                                        <div class="fw-bold text-dark-contrast mb-2">
                                            <i class="fas fa-check-circle me-1 text-success"></i> Amount Received & Closed for the following:
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($days_received as $dr): ?>
                                                <span class="badge bg-white border border-success text-dark px-3 py-2 shadow-sm" style="font-size: 12px; font-weight: 600;">
                                                    <strong style="color: #000;"><?php echo date('d-M-Y', strtotime($dr['date'])); ?></strong> | 
                                                    Clerk: <strong style="color: #000;"><?php echo htmlspecialchars($dr['clerk']); ?></strong> | 
                                                    Received: <strong style="color: #1f5f46;"><?php echo format_currency($dr['net_cash']); ?></strong> 
                                                    <span style="color: #555;">(by <?php echo htmlspecialchars($dr['log']['closed_by']); ?> at <?php echo date('h:i A', strtotime($dr['log']['closed_at'])); ?>)</span>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-info-circle me-1"></i> Tip: Select a specific clerk from the top filter to close their account and manage their denomination audit log.
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>
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

                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="mb-4">
                            <h5 class="section-sub-title">
                                <i class="fas fa-receipt"></i>
                                Fee Payments Received (<?php echo $clerk_filter === 'all' ? 'All Clerks Consolidated' : htmlspecialchars($clerk_filter); ?>)
                            </h5>
                            <div class="table-responsive">
                                <?php if (count($receipts_summary) > 0): ?>
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Receipt #</th>
                                                <th>Date & Time</th>
                                                <th>Mode</th>
                                                <?php if ($clerk_filter === 'all'): ?>
                                                    <th>Received By</th>
                                                <?php endif; ?>
                                                <th>Amount</th>
                                                <th class="text-end no-print">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($receipts_summary as $r_no => $r): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-success-subtle text-success fs-6 fw-bold">
                                                            <i class="fas fa-hashtag me-1"></i><?php echo htmlspecialchars($r_no); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo date('d-m-Y h:i A', strtotime($r['payment_date'])); ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $mode_lower = strtolower($r['payment_mode']);
                                                        if ($mode_lower === 'cash') {
                                                            echo '<span class="badge bg-success-subtle text-success no-print"><i class="fas fa-coins me-1"></i>Cash</span>';
                                                            echo '<span class="print-only">CASH</span>';
                                                        } else {
                                                            echo '<span class="badge bg-primary-subtle text-primary no-print"><i class="fas fa-university me-1"></i>' . htmlspecialchars($r['payment_mode']) . '</span>';
                                                            echo '<span class="print-only">' . strtoupper(htmlspecialchars($r['payment_mode'])) . '</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <?php if ($clerk_filter === 'all'): ?>
                                                        <td>
                                                            <span class="badge bg-dark-subtle text-dark no-print">
                                                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($r['received_by']); ?>
                                                            </span>
                                                            <span class="print-only" style="font-weight: 600; text-transform: uppercase;">
                                                                <?php echo htmlspecialchars($r['received_by']); ?>
                                                            </span>
                                                        </td>
                                                    <?php endif; ?>
                                                    <td><strong><?php echo format_currency($r['total_amount']); ?></strong></td>
                                                    <td class="text-end no-print">
                                                        <a href="receipt.php?payment_ids=<?php echo implode(',', $r['payment_ids']); ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                                            <i class="fas fa-print"></i> Receipt
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-light">
                                                <td colspan="<?php echo $clerk_filter === 'all' ? '4' : '3'; ?>" class="text-end text-success"><strong>Cash Subtotal:</strong></td>
                                                <td colspan="2" class="text-success"><strong><?php echo format_currency($total_cash); ?></strong></td>
                                            </tr>
                                            <tr class="table-light">
                                                <td colspan="<?php echo $clerk_filter === 'all' ? '4' : '3'; ?>" class="text-end text-primary"><strong>Bank Subtotal:</strong></td>
                                                <td colspan="2" class="text-primary"><strong><?php echo format_currency($total_bank_account); ?></strong></td>
                                            </tr>
                                            <tr class="table-dark">
                                                <td colspan="<?php echo $clerk_filter === 'all' ? '4' : '3'; ?>" class="text-end"><strong>Gross Sum:</strong></td>
                                                <td colspan="2"><strong><?php echo format_currency($total_received); ?></strong></td>
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
                                                            <?php echo date('d-m-Y h:i A', strtotime($e['created_at'])); ?>
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

                            <!-- Button under Drawer Reconciliation Statement -->
                            <div class="mt-4 pt-3 border-top text-center no-print">
                                <form method="POST" onsubmit="return handleCloseAccountSubmit(event, '<?php echo $clerk_filter; ?>')">
                                    <input type="hidden" name="target_clerk" value="<?php echo htmlspecialchars($clerk_filter); ?>">
                                    <button type="submit" name="close_clerk_account" class="btn btn-danger btn-lg w-100 py-3 fw-bold" style="border-radius: 10px; box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);">
                                        <i class="fas fa-lock me-2"></i> Received and Close Account
                                    </button>
                                </form>
                                <?php if ($clerk_filter === 'all'): ?>
                                    <small class="text-danger d-block mt-2">
                                        <i class="fas fa-info-circle"></i> Select a specific clerk above to enable close action.
                                    </small>
                                <?php else: ?>
                                    <small class="text-muted d-block mt-2">
                                        Closing account for clerk: <strong><?php echo htmlspecialchars($clerk_filter); ?></strong> until midnight.
                                    </small>
                                <?php endif; ?>
                            </div>

                        </div>

                        <?php if ($clerk_filter !== 'all'): ?>
                            <?php if ($is_single_day): ?>
                                <?php if (!empty($days_received)): 
                                    $single_rec = $days_received[0];
                                ?>
                                    <div class="alert alert-success d-flex align-items-start gap-2 mt-3 mb-4">
                                        <i class="fas fa-check-circle mt-1 text-success fs-5"></i>
                                        <div>
                                            <strong>Amount received and account closed.</strong>
                                            <div>
                                                <?php echo htmlspecialchars($clerk_filter); ?>'s reconciled amount for
                                                <?php echo date('d-M-Y', strtotime($start_day)); ?> was received:
                                                <strong><?php echo format_currency($single_rec['net_cash']); ?></strong>.
                                            </div>
                                            <small class="text-muted">
                                                Closed at <?php echo date('d-M-Y h:i A', strtotime($single_rec['log']['closed_at'])); ?>
                                                by <?php echo htmlspecialchars($single_rec['log']['closed_by']); ?>.
                                            </small>
                                        </div>
                                    </div>
                                <?php else: 
                                    $single_not_rec = $days_not_received[0];
                                ?>
                                    <div class="alert alert-danger d-flex align-items-start gap-2 mt-3 mb-4">
                                        <i class="fas fa-times-circle mt-1 text-danger fs-5"></i>
                                        <div>
                                            <strong>Cash NOT Received </strong>
                                            <div>
                                                <?php echo htmlspecialchars($clerk_filter); ?>'s cash in drawer for
                                                <?php echo date('d-M-Y', strtotime($start_day)); ?> is pending handover:
                                                <strong><?php echo format_currency($single_not_rec['net_cash']); ?></strong>.
                                            </div>
                                            <small class="text-muted">
                                                Click the red button above once cash is physically collected.
                                            </small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- Multiple Days Side Card Summary -->
                                <div class="alert <?php echo empty($days_not_received) ? 'alert-success' : 'alert-warning'; ?> d-flex align-items-start gap-2 mt-3 mb-4">
                                    <i class="fas <?php echo empty($days_not_received) ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-warning'; ?> mt-1 fs-5"></i>
                                    <div>
                                        <strong>Multi-Day Cash Handover Status:</strong>
                                        <div class="small mt-1">
                                            <span class="text-success fw-bold"><i class="fas fa-check me-1"></i><?php echo count($days_received); ?> of <?php echo count($period_days); ?> day(s) received</span>
                                            <?php if (!empty($days_not_received)): ?>
                                                <br><span class="text-danger fw-bold"><i class="fas fa-times me-1"></i><?php echo count($days_not_received); ?> day(s) NOT received</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- Denomination Audit Log Card for Master View -->
                        <div class="denomination-card">
                            <div class="denomination-heading d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-calculator me-1"></i> Clerk Calculator Audit Log
                                    <small class="text-muted d-block" style="font-size: 11px; font-weight: normal;">
                                        <?php if ($clerk_filter !== 'all'): ?>
                                            Clerk: <strong><?php echo htmlspecialchars($clerk_filter); ?></strong> | 
                                        <?php endif; ?>
                                        Date: <strong><?php echo date('d-M-Y', strtotime($selected_log_date)); ?></strong>
                                    </small>
                                </div>
                                <?php if ($clerk_filter !== 'all'): ?>
                                    <span id="audit-save-status" class="badge bg-success-subtle text-success border border-success px-2 py-1" style="font-size: 0.75rem; font-weight: 600; display: none;">
                                        <i class="fas fa-check-circle me-1"></i> Saved
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($clerk_filter === 'all'): ?>
                                <div class="alert alert-info py-3 mb-0" style="font-size: 0.9rem;">
                                    <i class="fas fa-info-circle me-1"></i> Please select a specific clerk from the top filter to view their Calculator Audit Log for <strong><?php echo date('d-M-Y', strtotime($selected_log_date)); ?></strong>.
                                </div>
                            <?php else: ?>
                                <div class="denom-row">
                                    <span class="denom-label">5000</span>
                                    <span class="denom-multiply">×</span>
                                    <div class="denom-input-col">
                                        <input type="number" min="0" class="denom-input" id="input-5000" data-value="5000" oninput="calcDenom(this)" placeholder="0" value="<?php echo (!empty($master_audit_log['d_5000'])) ? intval($master_audit_log['d_5000']) : ''; ?>">
                                    </div>
                                    <span class="denom-equal">=</span>
                                    <span class="denom-total-output" id="total-5000">0.00</span>
                                </div>

                                <div class="denom-row">
                                    <span class="denom-label">1000</span>
                                    <span class="denom-multiply">×</span>
                                    <div class="denom-input-col">
                                        <input type="number" min="0" class="denom-input" id="input-1000" data-value="1000" oninput="calcDenom(this)" placeholder="0" value="<?php echo (!empty($master_audit_log['d_1000'])) ? intval($master_audit_log['d_1000']) : ''; ?>">
                                    </div>
                                    <span class="denom-equal">=</span>
                                    <span class="denom-total-output" id="total-1000">0.00</span>
                                </div>

                                <div class="denom-row">
                                    <span class="denom-label">500</span>
                                    <span class="denom-multiply">×</span>
                                    <div class="denom-input-col">
                                        <input type="number" min="0" class="denom-input" id="input-500" data-value="500" oninput="calcDenom(this)" placeholder="0" value="<?php echo (!empty($master_audit_log['d_500'])) ? intval($master_audit_log['d_500']) : ''; ?>">
                                    </div>
                                    <span class="denom-equal">=</span>
                                    <span class="denom-total-output" id="total-500">0.00</span>
                                </div>

                                <div class="denom-row">
                                    <span class="denom-label">100</span>
                                    <span class="denom-multiply">×</span>
                                    <div class="denom-input-col">
                                        <input type="number" min="0" class="denom-input" id="input-100" data-value="100" oninput="calcDenom(this)" placeholder="0" value="<?php echo (!empty($master_audit_log['d_100'])) ? intval($master_audit_log['d_100']) : ''; ?>">
                                    </div>
                                    <span class="denom-equal">=</span>
                                    <span class="denom-total-output" id="total-100">0.00</span>
                                </div>

                                <div class="denom-row">
                                    <span class="denom-label">75</span>
                                    <span class="denom-multiply">×</span>
                                    <div class="denom-input-col">
                                        <input type="number" min="0" class="denom-input" id="input-75" data-value="75" oninput="calcDenom(this)" placeholder="0" value="<?php echo (!empty($master_audit_log['d_75'])) ? intval($master_audit_log['d_75']) : ''; ?>">
                                    </div>
                                    <span class="denom-equal">=</span>
                                    <span class="denom-total-output" id="total-75">0.00</span>
                                </div>

                                <div class="denom-row">
                                    <span class="denom-label">50</span>
                                    <span class="denom-multiply">×</span>
                                    <div class="denom-input-col">
                                        <input type="number" min="0" class="denom-input" id="input-50" data-value="50" oninput="calcDenom(this)" placeholder="0" value="<?php echo (!empty($master_audit_log['d_50'])) ? intval($master_audit_log['d_50']) : ''; ?>">
                                    </div>
                                    <span class="denom-equal">=</span>
                                    <span class="denom-total-output" id="total-50">0.00</span>
                                </div>

                                <div class="denom-row">
                                    <span class="denom-label">20</span>
                                    <span class="denom-multiply">×</span>
                                    <div class="denom-input-col">
                                        <input type="number" min="0" class="denom-input" id="input-20" data-value="20" oninput="calcDenom(this)" placeholder="0" value="<?php echo (!empty($master_audit_log['d_20'])) ? intval($master_audit_log['d_20']) : ''; ?>">
                                    </div>
                                    <span class="denom-equal">=</span>
                                    <span class="denom-total-output" id="total-20">0.00</span>
                                </div>

                                <div class="denom-row">
                                    <span class="denom-label">10</span>
                                    <span class="denom-multiply">×</span>
                                    <div class="denom-input-col">
                                        <input type="number" min="0" class="denom-input" id="input-10" data-value="10" oninput="calcDenom(this)" placeholder="0" value="<?php echo (!empty($master_audit_log['d_10'])) ? intval($master_audit_log['d_10']) : ''; ?>">
                                    </div>
                                    <span class="denom-equal">=</span>
                                    <span class="denom-total-output" id="total-10">0.00</span>
                                </div>

                                <div class="denom-grand-total-row">
                                    <span>Grand Total:</span>
                                    <span id="denom-grand-total">0.00</span>
                                </div>

                                <div class="denom-cash-collected-row">
                                    <span>Cash Collected:</span>
                                    <span id="audit-cash-collected"><?php echo number_format($cash_remaining, 2); ?></span>
                                </div>

                                <div class="difference-row" id="difference-container">
                                    <span>Difference:</span>
                                    <span id="audit-difference">0.00 (Balanced)</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
    function handleCloseAccountSubmit(event, currentClerk) {
        if (!currentClerk || currentClerk === 'all') {
            alert('Please select a specific clerk first from the top filter!');
            event.preventDefault();
            return false;
        }
        return confirm('Are you sure you want to receive cash and close the account for clerk (' + currentClerk + ')? The account will remain frozen until 12:00 AM midnight.');
    }

    <?php if ($clerk_filter !== 'all'): ?>
    const reconciledCashRemaining = <?php echo floatval($cash_remaining); ?>;
    const targetClerk = '<?php echo addslashes($clerk_filter); ?>';
    const selectedLogDate = '<?php echo $selected_log_date; ?>';
    let autoSaveTimer = null;

    function calcDenom(inputElement, isUserTyping = true) {
        const noteValue = parseInt(inputElement.getAttribute('data-value'));
        const count = parseInt(inputElement.value) || 0;
        
        inputElement.setAttribute('value', count > 0 ? count : '');

        const lineTotal = noteValue * count;
        
        const totalEl = document.getElementById('total-' + noteValue);
        if (totalEl) {
            totalEl.innerText = lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        
        let grandTotal = 0;
        const allInputs = document.querySelectorAll('.denom-input');
        allInputs.forEach(input => {
            const val = parseInt(input.getAttribute('data-value'));
            const qty = parseInt(input.value) || 0;
            grandTotal += (val * qty);
        });
        
        const grandTotalEl = document.getElementById('denom-grand-total');
        if (grandTotalEl) {
            grandTotalEl.innerText = grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        const diffAmount = grandTotal - reconciledCashRemaining;
        const diffElement = document.getElementById('audit-difference');
        
        if (diffElement) {
            if (diffAmount > 0) {
                const formattedVal = Math.abs(diffAmount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                diffElement.innerText = formattedVal + ' Extra';
                diffElement.style.color = '#dc3545';
            } else if (diffAmount < 0) {
                const formattedVal = Math.abs(diffAmount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                diffElement.innerText = formattedVal + ' Less';
                diffElement.style.color = '#dc3545';
            } else {
                diffElement.innerText = '0.00 (Balanced)';
                diffElement.style.color = '#1f5f46';
            }
        }

        if (isUserTyping) {
            triggerAutoSave(grandTotal, diffAmount);
        }
    }

    function triggerAutoSave(grandTotal, diffAmount) {
        const statusEl = document.getElementById('audit-save-status');
        if (statusEl) {
            statusEl.className = 'badge bg-warning-subtle text-warning border border-warning px-2 py-1';
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';
            statusEl.style.display = 'inline-block';
        }

        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(() => {
            const formData = new FormData();
            formData.append('action', 'save_audit_log');
            formData.append('target_clerk', targetClerk);
            formData.append('log_date', selectedLogDate);
            formData.append('d_5000', document.getElementById('input-5000')?.value || 0);
            formData.append('d_1000', document.getElementById('input-1000')?.value || 0);
            formData.append('d_500', document.getElementById('input-500')?.value || 0);
            formData.append('d_100', document.getElementById('input-100')?.value || 0);
            formData.append('d_75', document.getElementById('input-75')?.value || 0);
            formData.append('d_50', document.getElementById('input-50')?.value || 0);
            formData.append('d_20', document.getElementById('input-20')?.value || 0);
            formData.append('d_10', document.getElementById('input-10')?.value || 0);
            formData.append('grand_total', grandTotal);
            formData.append('cash_collected', reconciledCashRemaining);
            formData.append('difference', diffAmount);

            fetch('payment_analytics.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (statusEl) {
                    if (res.success) {
                        statusEl.className = 'badge bg-success-subtle text-success border border-success px-2 py-1';
                        statusEl.innerHTML = '<i class="fas fa-check-circle me-1"></i> Saved';
                        setTimeout(() => {
                            statusEl.style.display = 'none';
                        }, 2000);
                    } else {
                        statusEl.className = 'badge bg-danger-subtle text-danger border border-danger px-2 py-1';
                        statusEl.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> ' + (res.message || 'Error');
                    }
                }
            })
            .catch(err => {
                console.error('Audit save error:', err);
                if (statusEl) {
                    statusEl.className = 'badge bg-danger-subtle text-danger border border-danger px-2 py-1';
                    statusEl.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> Save Failed';
                }
            });
        }, 600);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const allInputs = document.querySelectorAll('.denom-input');
        allInputs.forEach(input => {
            calcDenom(input, false);
        });
    });
    <?php endif; ?>
    </script>
</body>
</html>