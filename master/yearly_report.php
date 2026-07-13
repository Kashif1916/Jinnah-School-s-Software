<?php
/**
 * Month-wise Yearly Collection and Expense Report
 * School Finance Management System - Master Panel
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master(); // Enforce Principal access

$selected_years_str = sanitize_input($_GET['years'] ?? date('Y'));
$years_array = array_map('intval', explode(',', $selected_years_str));

// Exclude invalid years
$years_array = array_filter($years_array, function($yr) {
    return $yr >= 2000 && $yr <= 2100;
});

if (empty($years_array)) {
    $years_array = [intval(date('Y'))];
}

// Sort years chronologically
sort($years_array);

$months_of_year = [
    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
    '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
    '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Yearly Finance Report - <?php echo implode(', ', $years_array); ?></title>
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
        .year-section {
            margin-bottom: 15mm;
            page-break-inside: avoid;
        }
        .year-title {
            color: #1f5f46;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 3mm;
            border-bottom: 1px solid #1f5f46;
            padding-bottom: 1mm;
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
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">Year-wise Financial Statements Report</p>
                </div>
            </div>
            <div style="text-align: right; font-size: 11px; color: #666;">
                <p style="margin: 0;"><strong>Selected Year(s):</strong> <?php echo implode(', ', $years_array); ?></p>
                <p style="margin: 5px 0 0 0;">Generated: <?php echo date('d-m-Y h:i A'); ?></p>
            </div>
        </div>

        <?php foreach ($years_array as $year): ?>
            <?php
            // Fetch monthly collections for this year
            $collections_by_month = [];
            $p_query = "SELECT DATE_FORMAT(payment_date, '%m') as p_month, SUM(amount) as total FROM payments WHERE YEAR(payment_date) = ? GROUP BY DATE_FORMAT(payment_date, '%m')";
            $stmt = $conn->prepare($p_query);
            $stmt->bind_param('i', $year);
            $stmt->execute();
            $p_res = $stmt->get_result();
            while ($row = $p_res->fetch_assoc()) {
                $collections_by_month[$row['p_month']] = floatval($row['total']);
            }
            $stmt->close();

            // Fetch monthly expenses for this year
            $expenses_by_month = [];
            $e_query = "SELECT DATE_FORMAT(created_at, '%m') as e_month, SUM(amount) as total FROM expenses WHERE YEAR(created_at) = ? GROUP BY DATE_FORMAT(created_at, '%m')";
            $stmt = $conn->prepare($e_query);
            $stmt->bind_param('i', $year);
            $stmt->execute();
            $e_res = $stmt->get_result();
            while ($row = $e_res->fetch_assoc()) {
                $expenses_by_month[$row['e_month']] = floatval($row['total']);
            }
            $stmt->close();

            $year_total_collection = 0.00;
            $year_total_expenses = 0.00;
            $year_total_profit = 0.00;
            ?>
            
            <div class="year-section">
                <div class="year-title">Financial Summary for Year: <?php echo $year; ?></div>
                <table>
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Total Collection</th>
                            <th>Total Expenses</th>
                            <th>Net Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($months_of_year as $m_num => $m_name): ?>
                            <?php
                            $collection = $collections_by_month[$m_num] ?? 0.00;
                            $expense = $expenses_by_month[$m_num] ?? 0.00;
                            $net_profit = $collection - $expense;

                            $year_total_collection += $collection;
                            $year_total_expenses += $expense;
                            $year_total_profit += $net_profit;

                            $profit_class = $net_profit >= 0 ? 'profit-positive' : 'profit-negative';
                            ?>
                            <tr>
                                <td><strong><?php echo $m_name; ?></strong></td>
                                <td><?php echo format_currency($collection); ?></td>
                                <td><?php echo format_currency($expense); ?></td>
                                <td class="<?php echo $profit_class; ?>"><?php echo format_currency($net_profit); ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Yearly Total Row -->
                        <tr class="total-row">
                            <td>TOTAL FOR <?php echo $year; ?></td>
                            <td><?php echo format_currency($year_total_collection); ?></td>
                            <td><?php echo format_currency($year_total_expenses); ?></td>
                            <td class="<?php echo $year_total_profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                                <?php echo format_currency($year_total_profit); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        window.addEventListener('load', function() {
            window.print();
        });
    </script>
</body>
</html>
