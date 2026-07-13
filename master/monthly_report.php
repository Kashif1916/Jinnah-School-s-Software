<?php
/**
 * Day-wise Monthly Collection and Expense Report
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

// Fetch collections grouped by date
$collections_by_day = [];
$p_query = "SELECT DATE(payment_date) as p_date, SUM(amount) as total FROM payments WHERE payment_date >= ? AND payment_date <= ? GROUP BY DATE(payment_date)";
$stmt = $conn->prepare($p_query);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$p_res = $stmt->get_result();
while ($row = $p_res->fetch_assoc()) {
    $collections_by_day[$row['p_date']] = floatval($row['total']);
}
$stmt->close();

// Fetch expenses grouped by date
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

// Grand Totals
$grand_total_collection = 0.00;
$grand_total_expenses = 0.00;
$grand_total_profit = 0.00;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Finance Report - <?php echo $display_month_name; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 10mm;
            background: white;
            color: #333;
        }
        .report-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 1mm;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #1f5f46;
            padding-bottom: 5mm;
            margin-bottom: 10mm;
        }
        .report-logo {
            width: 80px !important;
            height: auto !important;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8mm;
            font-size: 13px;
        }
        table th {
            background: #1f5f46;
            color: white;
            border: 1px solid #1f5f46;
            padding: 2.5mm;
            text-align: left;
            font-weight: bold;
        }
        table td {
            border: 1px solid #ddd;
            padding: 2.5mm;
        }
        table tr:nth-child(even) {
            background: #fdfdfd;
        }
        .total-row {
            font-weight: bold;
            background: #eaeaea !important;
            font-size: 14px;
        }
        .profit-positive {
            color: #1f5f46;
            font-weight: bold;
        }
        .profit-negative {
            color: #c0392b;
            font-weight: bold;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .report-container {
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Printable Header -->
        <div class="header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <?php echo render_system_logo('report-logo'); ?>
                <div style="text-align: left;">
                    <h2 style="margin: 0; color: #1f5f46; font-size: 20px; font-weight: bold;">Jinnah School And Intermediate College Khushab</h2>
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">Daily Finance Statement Report</p>
                </div>
            </div>
            <div style="text-align: right; font-size: 11px; color: #666;">
                <p style="margin: 0;"><strong>Month:</strong> <?php echo $display_month_name; ?></p>
                <p style="margin: 5px 0 0 0;">Generated: <?php echo date('d-m-Y h:i A'); ?></p>
            </div>
        </div>

        <!-- Report Table -->
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Total Collection</th>
                    <th>Total Expenses</th>
                    <th>Net Profit</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                for ($d = 1; $d <= $days_in_month; $d++) {
                    $day_date = sprintf("%04d-%02d-%02d", $year_num, $month_num, $d);
                    $display_date = sprintf("%02d-%s-%04d", $d, date('M', $timestamp), $year_num);
                    
                    $collection = $collections_by_day[$day_date] ?? 0.00;
                    $expense = $expenses_by_day[$day_date] ?? 0.00;
                    $net_profit = $collection - $expense;
                    
                    $grand_total_collection += $collection;
                    $grand_total_expenses += $expense;
                    $grand_total_profit += $net_profit;
                    
                    $profit_class = $net_profit >= 0 ? 'profit-positive' : 'profit-negative';
                    ?>
                    <tr>
                        <td><strong><?php echo $display_date; ?></strong></td>
                        <td><?php echo format_currency($collection); ?></td>
                        <td><?php echo format_currency($expense); ?></td>
                        <td class="<?php echo $profit_class; ?>"><?php echo format_currency($net_profit); ?></td>
                    </tr>
                <?php } ?>
                
                <!-- Grand Total Row -->
                <tr class="total-row">
                    <td>TOTAL</td>
                    <td><?php echo format_currency($grand_total_collection); ?></td>
                    <td><?php echo format_currency($grand_total_expenses); ?></td>
                    <td class="<?php echo $grand_total_profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                        <?php echo format_currency($grand_total_profit); ?>
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
