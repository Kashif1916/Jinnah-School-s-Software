<?php
/**
 * Defaulter Report - PDF Generation
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master();

$class_filter = isset($_GET['class']) ? sanitize_input($_GET['class']) : '';
$section_filter = isset($_GET['section']) ? sanitize_input($_GET['section']) : '';
$month_filter = isset($_GET['month']) ? sanitize_input($_GET['month']) : '';

// Get defaulters
$defaulters = get_defaulters($class_filter, $section_filter, $month_filter);
$defaulter_list = [];
if ($defaulters) {
    $defaulter_list = $defaulters->fetch_all(MYSQLI_ASSOC);
}

// Generate report
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 10mm;
            background: white;
        }
        .report-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 15mm;
            border: 1px solid #333;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1f5f46;
            padding-bottom: 5mm;
            margin-bottom: 10mm;
        }
        .report-logo {
            display: block;
            width: 90px; /* Matched with login page logo size */
            height: auto;
            margin: 0 auto 4mm;
        }
        .header p {
            margin: 2mm 0;
            color: #666;
            font-size: 11px;
        }
        .report-info {
            background: #f5f5f5;
            padding: 5mm;
            margin-bottom: 10mm;
            font-size: 11px;
            border-radius: 3px;
        }
        .report-info p {
            margin: 2mm 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5mm;
            font-size: 10px;
        }
        table th {
            background: #1f5f46;
            color: white;
            border: 1px solid #555;
            padding: 4mm;
            text-align: left;
            font-weight: bold;
        }
        table td {
            border: 1px solid #ddd;
            padding: 3mm;
        }
        table tr:nth-child(even) {
            background: #f9f9f9;
        }
        .total-row {
            background: #f0f0f0;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 5mm;
            margin-top: 10mm;
        }
        .amount {
            text-align: right;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .report-container {
                border: none;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="header">
            <?php echo render_system_logo('report-logo'); ?>
            <p>Pending Fees Students Report</p>
            <p>Generated on <?php echo date('d-m-Y H:i'); ?></p>
        </div>
        
        <div class="report-info">
            <p><strong>Report Criteria:</strong></p>
            <p>
                Class: <?php echo !empty($class_filter) ? $class_filter : 'All'; ?> | 
                Section: <?php echo !empty($section_filter) ? $section_filter : 'All'; ?> | 
                Month: <?php echo !empty($month_filter) ? $month_filter : 'All'; ?>
            </p>
            <p><strong>Total Pending:</strong> <?php echo count($defaulter_list); ?></p>
        </div>
        
        <?php if (count($defaulter_list) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>Father Name</th>
                        <th>Class</th>
                        <th>Section</th>
                        <th>Contact</th>
                        <th>Monthly Fee</th>
                        <th class="amount">Unpaid Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_unpaid = 0;
                    $counter = 1;
                    foreach ($defaulter_list as $defaulter):
                        $unpaid = get_total_unpaid_fees($defaulter['id']);
                        $total_unpaid += $unpaid;
                    ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td><?php echo $defaulter['name']; ?></td>
                            <td><?php echo $defaulter['father_name']; ?></td>
                            <td><?php echo $defaulter['class']; ?></td>
                            <td><?php echo $defaulter['section']; ?></td>
                            <td><?php echo $defaulter['contact_number']; ?></td>
                            <td class="amount"><?php echo format_currency($defaulter['monthly_fee']); ?></td>
                            <td class="amount"><strong><?php echo format_currency($unpaid); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="7" style="text-align: right;">TOTAL UNPAID:</td>
                        <td class="amount"><?php echo format_currency($total_unpaid); ?></td>
                    </tr>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; color: #666;">No defaulters found with the given criteria.</p>
        <?php endif; ?>
        
        <div class="footer">
            <p>This report has been generated automatically by <?php echo SYSTEM_NAME; ?></p>
            <p>For official purposes - Please verify the data before taking any action</p>
        </div>
    </div>
    
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
