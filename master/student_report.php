<?php
/**
 * Student Records Report - PDF / Printable Generation
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// Allow Master, Finance, and Admission roles to access this report
require_login();
if (!is_master() && !is_finance() && !is_admission() && !is_teacher()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$search_name = sanitize_input($_GET['search_name'] ?? '');
$search_class = sanitize_input($_GET['search_class'] ?? '');
$search_section = sanitize_input($_GET['search_section'] ?? '');

// Query to fetch students based on filters
$query = "SELECT * FROM students WHERE 1=1";
$params = [];
$param_types = '';

if (!empty($search_name)) {
    $query .= " AND name LIKE ?";
    $params[] = '%' . $search_name . '%';
    $param_types .= 's';
}

if (!empty($search_class)) {
    $query .= " AND class = ?";
    $params[] = $search_class;
    $param_types .= 's';
}

if (!empty($search_section)) {
    $query .= " AND section = ?";
    $params[] = $search_section;
    $param_types .= 's';
}

$query .= " ORDER BY id DESC";

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

        /* PRINT STYLES FIX */
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .report-container {
                border: none;
                box-shadow: none;
            }
            /* Prevent table header (th) from repeating on every page */
            thead {
                display: table-row-group;
            }
            tr {
                page-break-inside: avoid;
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
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">Student Records Report</p>
                </div>
            </div>
        </div>
        
        <div class="report-info">
            <p><strong>Report Criteria:</strong></p>
            <p>
                Name: <?php echo !empty($search_name) ? htmlspecialchars($search_name) : 'All'; ?> |
                Class: <?php echo !empty($search_class) ? htmlspecialchars($search_class) : 'All'; ?> |
                Section: <?php echo !empty($search_section) ? htmlspecialchars($search_section) : 'All'; ?>
            </p>
            <p><strong>Total Students:</strong> <?php echo count($students); ?></p>
        </div>
        
        <?php if (count($students) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ID</th>
                        <th>Student Name</th>
                        <th>Father Name</th>
                        <th>Class-Sec</th>
                        <th>Monthly Fee (Fixed)</th>
                        <th>Monthly Fee (Net)</th>
                        <th>Concession</th>
                        <th>Contact Number(s)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    foreach ($students as $s):
                    ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td><strong><?php echo htmlspecialchars($s['id']); ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($s['father_name']); ?></td>
                            <td><?php echo htmlspecialchars($s['class'] . '-' . $s['section']); ?></td>
                            <td class="amount"><?php echo format_currency($s['fixed_monthly_fee']); ?></td>
                            <td class="amount"><?php echo format_currency($s['monthly_fee']); ?></td>
                            <td class="amount"><?php echo format_currency($s['concession_amount']); ?></td>
                            <td>
                                <?php echo !empty($s['contact_number']) ? htmlspecialchars($s['contact_number']) . '<br>' : ''; ?>
                                <?php echo !empty($s['contact_number2']) ? htmlspecialchars($s['contact_number2']) . '<br>' : ''; ?>
                                <?php echo !empty($s['whatsapp_number']) ? htmlspecialchars($s['whatsapp_number']) : ''; ?>
                            </td>
                            <td>
                                <span style="font-weight: bold; color: <?php echo $s['status'] == 'active' ? '#27ae60' : '#e74c3c'; ?>;">
                                    <?php echo ucfirst(htmlspecialchars($s['status'])); ?>
                                </span>
                            </td>
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
            window.print();
        };
    </script>
</body>
</html>