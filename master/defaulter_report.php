<?php
/**
 * Defaulter Fee Summary Report - PDF / Printable View
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// Allow Master, Finance, Admission, and Teacher roles
require_login();
if (!is_master() && !is_finance() && !is_admission() && !is_teacher()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

// Multi-select Class & Section Arrays Handling
$class_filter = $_REQUEST['class'] ?? [];
if (!is_array($class_filter)) {
    $class_filter = !empty($class_filter) ? [sanitize_input($class_filter)] : [];
}

$section_filter = $_REQUEST['section'] ?? [];
if (!is_array($section_filter)) {
    $section_filter = !empty($section_filter) ? [sanitize_input($section_filter)] : [];
}

$student_id_filter = sanitize_input($_REQUEST['student_id'] ?? '');
$name_filter = sanitize_input($_REQUEST['name'] ?? '');
$father_name_filter = sanitize_input($_REQUEST['father_name'] ?? '');
$months_filter = isset($_REQUEST['months']) ? (is_array($_REQUEST['months']) ? $_REQUEST['months'] : [sanitize_input($_REQUEST['months'])]) : [];
$min_2_months = (isset($_REQUEST['min_2_months']) && $_REQUEST['min_2_months'] == '1') ? 1 : 0;
$min_3_months = (isset($_REQUEST['min_3_months']) && $_REQUEST['min_3_months'] == '1') ? 1 : 0;
$arrears_only = (isset($_REQUEST['arrears_only']) && $_REQUEST['arrears_only'] == '1') ? 1 : 0;

// Fetch defaulter students
$defaulters_query = get_defaulters($class_filter, $section_filter, $months_filter, $name_filter, $father_name_filter, $student_id_filter);
$all_defaulter_list = [];
if ($defaulters_query) {
    $all_defaulter_list = $defaulters_query->fetch_all(MYSQLI_ASSOC);
}

// Apply 2+ months, 3+ months, or arrears filter
$defaulter_list = filter_defaulters_by_criteria($all_defaulter_list, $min_2_months, $min_3_months, $arrears_only, $months_filter);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pending Fee Summary Report - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 10mm; background: white; }
        .report-container { max-width: 210mm; margin: 0 auto; background: white; padding: 10mm; border: 1px solid #333; }
        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #1f5f46; padding-bottom: 5mm; margin-bottom: 5mm; }
        .report-logo { width: 80px; height: auto; }
        .report-info { background: #f5f5f5; padding: 4mm; margin-bottom: 5mm; font-size: 11px; border-radius: 3px; }
        .report-info p { margin: 2mm 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 5mm; font-size: 10px; }
        table th { background: #1f5f46; color: white; border: 1px solid #555; padding: 5px 6px; text-align: left; font-weight: bold; }
        table td { border: 1px solid #ddd; padding: 4px 6px; }
        .contact-item { white-space: nowrap; line-height: 1.3; }
        table tr:nth-child(even) { background: #f9f9f9; }
        .footer { text-align: center; font-size: 9px; color: #999; border-top: 1px solid #ddd; padding-top: 4mm; margin-top: 5mm; }
        .amount { text-align: right; font-weight: bold; }

        @media print {
            body { margin: 0; padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .report-container { border: none; box-shadow: none; max-width: 100%; padding: 5mm; }
            thead { display: table-row-group; }
            tr { page-break-inside: avoid; }
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
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">Pending Fee Summary Report</p>
                </div>
            </div>
        </div>
        
        <div class="report-info">
            <p><strong>Report Criteria:</strong></p>
            <p>
                <strong>Student ID:</strong> <?php echo !empty($student_id_filter) ? htmlspecialchars($student_id_filter) : 'All'; ?> |
                <strong>Name:</strong> <?php echo !empty($name_filter) ? htmlspecialchars($name_filter) : 'All'; ?> |
                <strong>Class(es):</strong> 
                <?php 
                    if (!empty($class_filter)) {
                        echo htmlspecialchars(is_array($class_filter) ? implode(', ', $class_filter) : $class_filter);
                    } else {
                        echo 'All';
                    }
                ?> |
                <strong>Section(s):</strong> 
                <?php 
                    if (!empty($section_filter)) {
                        echo htmlspecialchars(is_array($section_filter) ? implode(', ', $section_filter) : $section_filter);
                    } else {
                        echo 'All';
                    }
                ?> |
                <strong>Month(s) / Fees:</strong> 
                <?php 
                    if (!empty($months_filter)) {
                        echo htmlspecialchars(is_array($months_filter) ? implode(', ', $months_filter) : $months_filter);
                    } else {
                        echo 'All';
                    }
                ?>
                <?php if ($min_3_months === 1): ?>
                    | <strong>Filter:</strong> 3+ Months Pending Only
                <?php elseif ($min_2_months === 1): ?>
                    | <strong>Filter:</strong> 2+ Months Pending Only
                <?php elseif ($arrears_only === 1): ?>
                    | <strong>Filter:</strong> Arrears Only
                <?php endif; ?>
            </p>
            <p><strong>Total Pending Students:</strong> <?php echo count($defaulter_list); ?></p>
        </div>
        
        <?php if (count($defaulter_list) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 25px;">#</th>
                        <th style="width: 45px;">ID</th>
                        <th>Student Name</th>
                        <th>Father Name</th>
                        <th>Class-Sec</th>
                        <th>Contact Number(s)</th>
                        <th>Pending Particulars / Months</th>
                        <th style="text-align: right;">Monthly / Net Fee</th>
                        <th style="text-align: right;">Pending Dues (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    $grand_total_pending = 0;

                    foreach ($defaulter_list as $d):
                        $s_id = intval($d['id']);
                        $unpaid_amount = floatval($d['filtered_unpaid_amount'] ?? 0);
                        $s_fine = calculate_student_fine($s_id);
                        $total_student_due = $unpaid_amount + $s_fine;
                        $grand_total_pending += $total_student_due;

                        $is_pkg = (!empty($d['is_package']) || floatval($d['package_amount'] ?? 0) > 0 || is_college_class($d['class']) || strpos($d['pending_months'] ?? '', 'Package') !== false);
                    ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td><strong><?php echo htmlspecialchars($d['id']); ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($d['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($d['father_name']); ?></td>
                            <td><?php echo htmlspecialchars($d['class'] . '-' . $d['section']); ?></td>
                            <td>
                                <?php echo !empty($d['contact_number']) ? '<div class="contact-item"><i class="fas fa-phone"></i> ' . htmlspecialchars($d['contact_number']) . '</div>' : ''; ?>
                                <?php echo !empty($d['whatsapp_number']) ? '<div class="contact-item"><i class="fab fa-whatsapp" style="color:#25D366;"></i> ' . htmlspecialchars($d['whatsapp_number']) . '</div>' : ''; ?>
                            </td>
                            <td style="font-size: 9.5px; max-width: 180px; word-break: break-word;">
                                <strong>(<?php echo htmlspecialchars($d['pending_count']); ?> Fee)</strong><br>
                                <?php echo htmlspecialchars(str_replace(',', ', ', $d['pending_months'])); ?>
                                <?php if ($s_fine > 0): ?>
                                    <br><span style="color: #dc3545; font-weight: bold;">Late Fine: <?php echo format_currency($s_fine); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="amount">
                                <?php 
                                if ($is_pkg) {
                                    $raw_pkg = floatval($d['package_amount'] ?? 0);
                                    if ($raw_pkg <= 0) {
                                        $raw_pkg = floatval($d['fixed_monthly_fee'] ?? 0);
                                    }
                                    $concession = floatval($d['concession_amount'] ?? 0);
                                    $net_pkg = max(0, $raw_pkg - $concession);
                                    echo format_currency($net_pkg) . '<br><small style="color: #0d6efd;">(Yearly Pkg)</small>';
                                } else {
                                    echo format_currency($d['monthly_fee']);
                                }
                                ?>
                            </td>
                            <td class="amount" style="color: #1f5f46; font-size: 11px;">
                                <?php echo format_currency($total_student_due); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #eef5f2; font-weight: bold; font-size: 11px; color: #1f5f46;">
                        <td colspan="8" style="text-align: right; padding: 6px;">GRAND TOTAL PENDING DUES:</td>
                        <td class="amount" style="padding: 6px; font-size: 12px; border-top: 2px solid #1f5f46;">
                            <?php echo format_currency($grand_total_pending); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        <?php else: ?>
            <p style="text-align: center; color: #666; margin: 30px 0;">No pending fee records found with the selected filter criteria.</p>
        <?php endif; ?>
        
        <div class="footer">
            <p>This report has been generated automatically by <?php echo SYSTEM_NAME; ?></p>
            <p>For official purposes - Printed by <?php echo get_username(); ?> on <?php echo date('d-m-Y h:i A'); ?></p>
        </div>
    </div>
    
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 250);
        };
    </script>
</body>
</html>