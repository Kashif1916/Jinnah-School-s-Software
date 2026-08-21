<?php
/**
 * Defaulter Report - PDF Generation
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// Allow Master, Finance, Admission, and Teacher roles to access this report
require_login();
if (!is_master() && !is_finance() && !is_admission() && !is_teacher()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$class_filter = isset($_GET['class']) ? sanitize_input($_GET['class']) : '';
$section_filter = isset($_GET['section']) ? sanitize_input($_GET['section']) : '';
$name_filter = isset($_GET['name']) ? sanitize_input($_GET['name']) : '';
$months_filter = isset($_GET['months']) ? (is_array($_GET['months']) ? $_GET['months'] : [sanitize_input($_GET['months'])]) : [];

// Get defaulters
$defaulters = get_defaulters($class_filter, $section_filter, $months_filter, $name_filter);
$defaulter_list = [];
if ($defaulters) {
    $defaulter_list = $defaulters->fetch_all(MYSQLI_ASSOC);
}

// Generate report
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pending Fees Students Report</title>
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
        .badge-package {
            background-color: #0d6efd;
            color: #ffffff;
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 3px;
            font-weight: bold;
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
            .badge-package {
                background-color: #0d6efd !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
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
                    <h2 style="margin: 0; color: #1f5f46; font-size: 20px; font-weight: bold;"><?php echo SITE_NAME; ?></h2>
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">Pending Fees Students Report</p>
                </div>
            </div>
        </div>
        
        <div class="report-info">
            <p><strong>Report Criteria:</strong></p>
            <p>
                Name: <?php echo !empty($name_filter) ? htmlspecialchars($name_filter) : 'All'; ?> |
                Class: <?php echo !empty($class_filter) ? htmlspecialchars($class_filter) : 'All'; ?> |
                Section: <?php echo !empty($section_filter) ? htmlspecialchars($section_filter) : 'All'; ?> |
                Months: <?php echo !empty($months_filter) ? implode(', ', array_map('htmlspecialchars', $months_filter)) : 'All'; ?>
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
                        <th>Monthly Fee / Package</th> 
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_unpaid = 0;
                    $counter = 1;
                    foreach ($defaulter_list as $defaulter):
                        $unpaid = get_total_unpaid_fees($defaulter['id']);
                        $total_unpaid += $unpaid;
                        
                        // Check if student belongs to a college package class
                        $is_college = is_college_class($defaulter['class']) || (!empty($defaulter['is_package']) && $defaulter['is_package'] == 1);
                    ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td><?php echo htmlspecialchars($defaulter['name']); ?></td>
                            <td><?php echo htmlspecialchars($defaulter['father_name']); ?></td>
                            <td>
                                <?php echo !empty($defaulter['contact_number']) ? '<i class="fas fa-phone"></i> ' . htmlspecialchars($defaulter['contact_number']) . '<br>' : ''; ?>
                                <?php echo !empty($defaulter['whatsapp_number']) ? '<i class="fab fa-whatsapp"></i> ' . htmlspecialchars($defaulter['whatsapp_number']) : ''; ?>
                            </td>
                            <td><?php echo htmlspecialchars($defaulter['class'] . '-' . $defaulter['section']); ?></td>
                            <td>
                                <strong>(<?php echo htmlspecialchars($defaulter['pending_count']); ?> Month)</strong><br>
                                <?php echo htmlspecialchars(str_replace(',', ', ', $defaulter['pending_months'])); ?>
                            </td>
                            <td class="amount">
                                <?php if ($is_college): ?>
                                    <span class="badge-package">Yearly Pkg</span><br>
                                    <?php 
                                        $pkg_amt = floatval($defaulter['package_amount'] ?? 0);
                                        $concession = floatval($defaulter['concession_amount'] ?? 0);
                                        $net_pkg = max(0, $pkg_amt - $concession);
                                        echo format_currency($net_pkg);
                                    ?>
                                <?php else: ?>
                                    <?php echo format_currency($defaulter['monthly_fee']); ?>
                                <?php endif; ?>
                            </td>                           
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