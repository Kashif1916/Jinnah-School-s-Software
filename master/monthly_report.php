<?php
/**
 * Day-wise Monthly Collection, User Breakdown, Expense Report & Audit Difference
 * School Finance Management System - Master Panel
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master(); // Enforce Principal access

$selected_month = sanitize_input($_GET['month'] ?? date('Y-m')); // Format: YYYY-MM
$timestamp = strtotime($selected_month . '-01');
if (!$timestamp) {
    die("Invalid month selected");
}

$month_num = date('m', $timestamp);
$year_num = date('Y', $timestamp);
$days_in_month = date('t', $timestamp);
$display_month_name = date('F Y', $timestamp);

$start_date = "$selected_month-01 00:00:00";
$end_date = "$selected_month-$days_in_month 23:59:59";
$start_date_only = "$selected_month-01";
$end_date_only = "$selected_month-$days_in_month";

// 1. Fetch ALL Finance & Master Users from Database
$collecting_users = [];
$user_q = $conn->query("SELECT username FROM users WHERE role IN ('master', 'finance') ORDER BY CASE WHEN role = 'master' THEN 1 ELSE 2 END, username ASC");
if ($user_q) {
    while ($u = $user_q->fetch_assoc()) {
        $collecting_users[] = $u['username'];
    }
}

// Fallback: Fetch active payment receivers if users < 4
if (count($collecting_users) < 4) {
    $p_users = $conn->query("SELECT DISTINCT received_by FROM payments WHERE received_by IS NOT NULL AND received_by != ''");
    while ($pu = $p_users->fetch_assoc()) {
        if (!in_array($pu['received_by'], $collecting_users)) {
            $collecting_users[] = $pu['received_by'];
        }
    }
}

// 2. Fetch collections grouped by Date and Received_By User
$user_collections_by_day = []; // Format: [ 'YYYY-MM-DD' => [ 'username' => total ] ]
$p_query = "SELECT DATE(payment_date) as p_date, received_by, SUM(amount) as total FROM payments WHERE payment_date >= ? AND payment_date <= ? GROUP BY DATE(payment_date), received_by";
$stmt = $conn->prepare($p_query);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$p_res = $stmt->get_result();
while ($row = $p_res->fetch_assoc()) {
    $user_collections_by_day[$row['p_date']][$row['received_by']] = floatval($row['total']);
}
$stmt->close();

// 3. Fetch expenses grouped by date
$expenses_by_day = [];
$e_query = "SELECT DATE(created_at) as e_date, SUM(amount) as total FROM expenses WHERE created_at >= ? AND created_at <= ? GROUP BY DATE(created_at)";
$stmt = $conn->prepare($e_query);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$e_res = $stmt->get_result();
while ($row = $e_res->fetch_assoc()) {
    $expenses_by_day[$row['e_date']] = floatval($row['total']);
}
$stmt->close();

// 4. Fetch Calculator Audit Log Differences grouped by date (Sum of all users per day)
$diff_by_day = [];
$diff_query = "SELECT log_date, SUM(difference) as total_diff FROM calculator_audit_logs WHERE log_date BETWEEN ? AND ? GROUP BY log_date";
$stmt = $conn->prepare($diff_query);
$stmt->bind_param('ss', $start_date_only, $end_date_only);
$stmt->execute();
$diff_res = $stmt->get_result();
while ($row = $diff_res->fetch_assoc()) {
    $diff_by_day[$row['log_date']] = floatval($row['total_diff']);
}
$stmt->close();

// Grand Totals Init
$user_grand_totals = array_fill_keys($collecting_users, 0.00);
$grand_total_collection = 0.00;
$grand_total_expenses = 0.00;
$grand_total_profit = 0.00;
$grand_total_diff = 0.00;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Finance Report - <?php echo $display_month_name; ?></title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 5mm;
            background: white;
            color: #111;
        }
        .report-container {
            width: 100%;
            margin: 0 auto;
            background: white;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #1f5f46;
            padding-bottom: 3mm;
            margin-bottom: 4mm;
        }
        .report-logo {
            width: 60px !important;
            height: auto !important;
        }
        
        /* Compact Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            table-layout: auto;
        }
        table th {
            background: #1f5f46;
            color: white;
            border: 1px solid #1f5f46;
            padding: 4px 6px;
            text-align: center;
            font-weight: bold;
            white-space: nowrap;
        }
        table td {
            border: 1px solid #ccc;
            padding: 3px 5px;
            text-align: right;
            white-space: nowrap;
        }
        table td.text-left {
            text-align: left;
        }
        table tr:nth-child(even) {
            background: #f8f9fa;
        }
        .total-row td {
            font-weight: bold;
            background: #e2e8f0 !important;
            font-size: 11.5px;
            border-top: 2px solid #1f5f46;
        }
        .profit-positive {
            color: #1f5f46;
            font-weight: bold;
        }
        .profit-negative {
            color: #c0392b;
            font-weight: bold;
        }
        .diff-positive {
            color: #1f5f46;
            font-weight: bold;
        }
        .diff-negative {
            color: #c0392b;
            font-weight: bold;
        }
        .diff-zero {
            color: #555;
        }
        .user-header {
            background: #154331 !important;
            border-color: #154331 !important;
            font-size: 10.5px;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            @page {
                size: A4 portrait;
                margin: 4mm;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Printable Header -->
        <div class="header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <?php echo render_system_logo('report-logo'); ?>
                <div style="text-align: left;">
                    <h2 style="margin: 0; color: #1f5f46; font-size: 16px; font-weight: bold;">Jinnah School And Intermediate College Khushab</h2>
                    <p style="margin: 2px 0 0 0; color: #555; font-size: 11px;">Daily User Collection Statement Report</p>
                </div>
            </div>
            <div style="text-align: right; font-size: 10.5px; color: #555;">
                <p style="margin: 0;"><strong>Month:</strong> <?php echo $display_month_name; ?></p>
                <p style="margin: 2px 0 0 0;">Issued: <?php echo date('d-m-Y h:i A'); ?></p>
            </div>
        </div>

        <!-- Report Table -->
        <table>
            <thead>
                <tr>
                    <th style="width: 80px;">Date</th>
                    
                    <!-- 4 User Columns -->
                    <?php foreach ($collecting_users as $username): ?>
                        <th>
                            <?php echo htmlspecialchars($username); ?>
                        </th>
                    <?php endforeach; ?>

                    <th>Total Coll.</th>
                    <th>Expenses</th>
                    <th>Net Profit</th>
                    <th>Difference</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                for ($d = 1; $d <= $days_in_month; $d++) {
                    $day_date = sprintf("%04d-%02d-%02d", $year_num, $month_num, $d);
                    $display_date = sprintf("%02d-%s-%04d", $d, date('M', $timestamp), $year_num);
                    
                    $day_total_collection = 0.00;
                    ?>
                    <tr>
                        <td class="text-left"><strong><?php echo $display_date; ?></strong></td>
                        
                        <!-- Print Each User's Collection -->
                        <?php foreach ($collecting_users as $username): 
                            $user_amt = $user_collections_by_day[$day_date][$username] ?? 0.00;
                            $day_total_collection += $user_amt;
                            $user_grand_totals[$username] += $user_amt;
                        ?>
                            <td><?php echo ($user_amt > 0) ? number_format($user_amt, 0) : '-'; ?></td>
                        <?php endforeach; ?>

                        <?php 
                        $expense = $expenses_by_day[$day_date] ?? 0.00;
                        $net_profit = $day_total_collection - $expense;
                        $day_diff = $diff_by_day[$day_date] ?? 0.00;
                        
                        $grand_total_collection += $day_total_collection;
                        $grand_total_expenses += $expense;
                        $grand_total_profit += $net_profit;
                        $grand_total_diff += $day_diff;
                        
                        $profit_class = $net_profit >= 0 ? 'profit-positive' : 'profit-negative';
                        
                        // Difference formatting logic
                        if ($day_diff > 0) {
                            $diff_str = '+' . number_format($day_diff, 0);
                            $diff_class = 'diff-positive';
                        } elseif ($day_diff < 0) {
                            $diff_str = '-' . number_format(abs($day_diff), 0);
                            $diff_class = 'diff-negative';
                        } else {
                            $diff_str = '0';
                            $diff_class = 'diff-zero';
                        }
                        ?>

                        <td><strong><?php echo ($day_total_collection > 0) ? number_format($day_total_collection, 0) : '-'; ?></strong></td>
                        <td><?php echo ($expense > 0) ? number_format($expense, 0) : '-'; ?></td>
                        <td class="<?php echo $profit_class; ?>"><?php echo number_format($net_profit, 0); ?></td>
                        <td class="<?php echo $diff_class; ?>"><?php echo $diff_str; ?></td>
                    </tr>
                <?php } ?>
                
                <!-- Grand Total Row -->
                <tr class="total-row">
                    <td class="text-left">TOTAL</td>
                    
                    <!-- Each User's Monthly Grand Total -->
                    <?php foreach ($collecting_users as $username): ?>
                        <td><?php echo number_format($user_grand_totals[$username], 0); ?></td>
                    <?php endforeach; ?>

                    <td><?php echo number_format($grand_total_collection, 0); ?></td>
                    <td><?php echo number_format($grand_total_expenses, 0); ?></td>
                    <td class="<?php echo $grand_total_profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                        <?php echo number_format($grand_total_profit, 0); ?>
                    </td>
                    <td class="<?php echo $grand_total_diff > 0 ? 'diff-positive' : ($grand_total_diff < 0 ? 'diff-negative' : 'diff-zero'); ?>">
                        <?php 
                        if ($grand_total_diff > 0) {
                            echo '+' . number_format($grand_total_diff, 0);
                        } elseif ($grand_total_diff < 0) {
                            echo '-' . number_format(abs($grand_total_diff), 0);
                        } else {
                            echo '0';
                        }
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <script>
        window.addEventListener('load', function() {
            window.print();
        });
    </script>
</body>
</html>