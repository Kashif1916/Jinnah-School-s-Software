<?php
/**
 * Defaulter Report - PDF Generation
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// Allow both Master and Finance roles to access this report
require_login();
if (!is_master() && !is_finance()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$class_filter = isset($_GET['class']) ? sanitize_input($_GET['class']) : '';
$section_filter = isset($_GET['section']) ? sanitize_input($_GET['section']) : '';
$months_filter = isset($_GET['months']) ? (is_array($_GET['months']) ? $_GET['months'] : [sanitize_input($_GET['months'])]) : [];

// Get defaulters
$defaulters = get_defaulters($class_filter, $section_filter, $months_filter);
$defaulter_list = [];
if ($defaulters) {
    $defaulter_list = $defaulters->fetch_all(MYSQLI_ASSOC);
}

// Generate report
?>
<!DOCTYPE html>
<html>
<head>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #1f5f46;
            padding-bottom: 5mm;
            margin-bottom: 10mm;
        }
        .report-logo {
            width: 80px;
            height: auto;
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
            <div style="display: flex; align-items: center; gap: 15px;">
                <?php echo render_system_logo('report-logo'); ?>
                <div style="text-align: left;">
                    <h2 style="margin: 0; color: #1f5f46; font-size: 20px; font-weight: bold;">Jinnah School And Intermediate College Khushab</h2>
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">Pending Fees Students Report</p>
                </div>
            </div>
            
        </div>
        
        <div class="report-info">
            <p><strong>Report Criteria:</strong></p>
            <p>
                Class: <?php echo !empty($class_filter) ? $class_filter : 'All'; ?> |
                Section: <?php echo !empty($section_filter) ? $section_filter : 'All'; ?> |
                Months: <?php echo !empty($months_filter) ? implode(', ', $months_filter) : 'All'; ?>
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
                        <th>Contact Number(s)</th>
                        <th>Class-Sec</th>
                        <th>Pending Month(s)</th>
                        <th>Monthly Fee</th> 
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
                            <td>
                                <?php echo !empty($defaulter['contact_number']) ? '<i class="fas fa-phone"></i> ' . $defaulter['contact_number'] . '<br>' : ''; ?>
                                <?php echo !empty($defaulter['whatsapp_number']) ? '<i class="fab fa-whatsapp"></i> ' . $defaulter['whatsapp_number'] : ''; ?>
                            </td>
                            <td><?php echo $defaulter['class'] . '-' . $defaulter['section']; ?></td>
                            <td>
                                <strong>(<?php echo $defaulter['pending_count']; ?> Month)</strong><br>
                                <?php echo str_replace(',', ', ', $defaulter['pending_months']); ?>
                            </td>
                            <td class="amount"><?php echo format_currency($defaulter['monthly_fee']); ?></td>                           
                        </tr>
                    <?php endforeach; ?>
                    
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
