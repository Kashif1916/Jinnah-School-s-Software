<?php
/**
 * Student Records Report - PDF / Printable Generation
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_login();
if (!is_master() && !is_finance() && !is_admission() && !is_teacher()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$search_name = sanitize_input($_GET['search_name'] ?? '');
$search_father_name = sanitize_input($_GET['search_father_name'] ?? '');
$search_b_form = sanitize_input($_GET['search_b_form'] ?? '');
$search_classes = isset($_GET['search_classes']) && is_array($_GET['search_classes']) ? $_GET['search_classes'] : [];
$search_sections = isset($_GET['search_sections']) && is_array($_GET['search_sections']) ? $_GET['search_sections'] : [];
$search_reasons = isset($_GET['search_reasons']) && is_array($_GET['search_reasons']) ? $_GET['search_reasons'] : [];

// Dynamic Column Selections
$cols = isset($_GET['cols']) && is_array($_GET['cols']) ? $_GET['cols'] : [
    'id' => 1, 'name' => 1, 'father_name' => 1, 'b_form' => 1, 'class_sec' => 1,
    'fixed_fee' => 1, 'concession' => 1, 'net_fee' => 1, 'concession_reason' => 1, 'contact' => 1, 'address' => 1, 'status' => 1
];

// Query Construction
$where_clauses = ["1=1"];
$params = [];
$param_types = '';

if (!empty($search_name)) {
    $where_clauses[] = "name LIKE ?";
    $params[] = '%' . $search_name . '%';
    $param_types .= 's';
}
if (!empty($search_father_name)) {
    $where_clauses[] = "father_name LIKE ?";
    $params[] = '%' . $search_father_name . '%';
    $param_types .= 's';
}
if (!empty($search_b_form)) {
    $where_clauses[] = "b_form LIKE ?";
    $params[] = '%' . $search_b_form . '%';
    $param_types .= 's';
}
if (!empty($search_classes)) {
    $placeholders = implode(',', array_fill(0, count($search_classes), '?'));
    $where_clauses[] = "class IN ($placeholders)";
    foreach ($search_classes as $c) {
        $params[] = sanitize_input($c);
        $param_types .= 's';
    }
}
if (!empty($search_sections)) {
    $placeholders = implode(',', array_fill(0, count($search_sections), '?'));
    $where_clauses[] = "section IN ($placeholders)";
    foreach ($search_sections as $s) {
        $params[] = sanitize_input($s);
        $param_types .= 's';
    }
}
if (!empty($search_reasons)) {
    $placeholders = implode(',', array_fill(0, count($search_reasons), '?'));
    $where_clauses[] = "concession_reason IN ($placeholders)";
    foreach ($search_reasons as $r) {
        $params[] = sanitize_input($r);
        $param_types .= 's';
    }
}

$where_sql = implode(" AND ", $where_clauses);
$query = "SELECT * FROM students WHERE $where_sql ORDER BY id DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Student Records Report</title>
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
        .amount { text-align: right; }

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
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">Student Records Report</p>
                </div>
            </div>
        </div>
        
        <div class="report-info">
            <p><strong>Report Criteria:</strong></p>
            <p>
                Class(es): <?php echo !empty($search_classes) ? htmlspecialchars(implode(', ', $search_classes)) : 'All'; ?> |
                Section(s): <?php echo !empty($search_sections) ? htmlspecialchars(implode(', ', $search_sections)) : 'All'; ?> |
                Concession Reason(s): <?php echo !empty($search_reasons) ? htmlspecialchars(implode(', ', $search_reasons)) : 'All'; ?>
            </p>
            <p><strong>Total Students:</strong> <?php echo count($students); ?></p>
        </div>
        
        <?php if (count($students) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <?php if (isset($cols['id'])): ?><th>ID</th><?php endif; ?>
                        <?php if (isset($cols['name'])): ?><th>Student Name</th><?php endif; ?>
                        <?php if (isset($cols['father_name'])): ?><th>Father Name</th><?php endif; ?>
                        <?php if (isset($cols['b_form'])): ?><th>B-Form / CNIC</th><?php endif; ?>
                        <?php if (isset($cols['class_sec'])): ?><th>Class-Sec</th><?php endif; ?>
                        <?php if (isset($cols['fixed_fee'])): ?><th>Monthly Fee (Fixed)</th><?php endif; ?>
                        <?php if (isset($cols['concession'])): ?><th>Concession</th><?php endif; ?>
                        <?php if (isset($cols['net_fee'])): ?><th>Monthly Fee (Net)</th><?php endif; ?>
                        <?php if (isset($cols['concession_reason'])): ?><th>Concession Reason</th><?php endif; ?>
                        <?php if (isset($cols['contact'])): ?><th>Contact Number(s)</th><?php endif; ?>
                        <?php if (isset($cols['address'])): ?><th>Address</th><?php endif; ?>
                        <?php if (isset($cols['status'])): ?><th>Status</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    foreach ($students as $s):
                        $is_pkg = (!empty($s['is_package']) || floatval($s['package_amount'] ?? 0) > 0 || is_college_class($s['class']));
                        $raw_fee = floatval($is_pkg ? ($s['package_amount'] > 0 ? $s['package_amount'] : $s['fixed_monthly_fee']) : $s['fixed_monthly_fee']);
                        $concession = floatval($s['concession_amount']);
                        $net_fee = max(0, $raw_fee - $concession);
                    ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <?php if (isset($cols['id'])): ?><td><strong><?php echo htmlspecialchars($s['id']); ?></strong></td><?php endif; ?>
                            <?php if (isset($cols['name'])): ?><td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td><?php endif; ?>
                            <?php if (isset($cols['father_name'])): ?><td><?php echo htmlspecialchars($s['father_name']); ?></td><?php endif; ?>
                            <?php if (isset($cols['b_form'])): ?><td><?php echo !empty($s['b_form']) ? htmlspecialchars($s['b_form']) : '-'; ?></td><?php endif; ?>
                            <?php if (isset($cols['class_sec'])): ?><td><?php echo htmlspecialchars($s['class'] . '-' . $s['section']); ?></td><?php endif; ?>
                            <?php if (isset($cols['fixed_fee'])): ?><td class="amount"><?php echo format_currency($raw_fee); ?></td><?php endif; ?>
                            <?php if (isset($cols['concession'])): ?><td class="amount"><?php echo format_currency($concession); ?></td><?php endif; ?>
                            <?php if (isset($cols['net_fee'])): ?><td class="amount"><?php echo format_currency($net_fee); ?></td><?php endif; ?>
                            <?php if (isset($cols['concession_reason'])): ?><td><?php echo htmlspecialchars($s['concession_reason'] ?? '-'); ?></td><?php endif; ?>
                            <?php if (isset($cols['contact'])): ?>
                                <td>
                                    <?php echo !empty($s['contact_number']) ? '<div class="contact-item"><i class="fas fa-phone"></i> ' . htmlspecialchars($s['contact_number']) . '</div>' : ''; ?>
                                    <?php echo !empty($s['contact_number2']) ? '<div class="contact-item"><i class="fas fa-phone"></i> ' . htmlspecialchars($s['contact_number2']) . '</div>' : ''; ?>
                                    <?php echo !empty($s['whatsapp_number']) ? '<div class="contact-item"><i class="fab fa-whatsapp" style="color:#25D366;"></i> ' . htmlspecialchars($s['whatsapp_number']) . '</div>' : ''; ?>
                                </td>
                            <?php endif; ?>
                            <?php if (isset($cols['address'])): ?><td style="font-size: 10px; max-width: 140px; word-break: break-word;"><?php echo !empty($s['address']) ? htmlspecialchars($s['address']) : '-'; ?></td><?php endif; ?>
                            <?php if (isset($cols['status'])): ?>
                                <td>
                                    <span style="font-weight: bold; color: <?php echo $s['status'] == 'active' ? '#27ae60' : '#e74c3c'; ?>;">
                                        <?php echo ucfirst(htmlspecialchars($s['status'])); ?>
                                    </span>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; color: #666;">No students found with the given criteria.</p>
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