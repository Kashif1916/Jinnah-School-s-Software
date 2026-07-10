<?php
/**
 * Student Fee Statement - Printable
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// Allow Master, Finance, and Admission roles
require_login();

$student_id = intval($_GET['id'] ?? 0);
$from_month = sanitize_input($_GET['from_month'] ?? '');
$to_month = sanitize_input($_GET['to_month'] ?? '');

$student = get_student($student_id);
if (!$student) {
    die("Student not found!");
}

if (empty($from_month) || empty($to_month)) {
    die("Month range is required!");
}

// Fetch all fee records in the given range
$query = "SELECT * FROM fee_records 
          WHERE student_id = ? 
          AND STR_TO_DATE(CONCAT('01-', month), '%d-%b-%Y') BETWEEN STR_TO_DATE(CONCAT('01-', ?), '%d-%b-%Y') AND STR_TO_DATE(CONCAT('01-', ?), '%d-%b-%Y')
          ORDER BY STR_TO_DATE(CONCAT('01-', month), '%d-%b-%Y')";
$stmt = $conn->prepare($query);
$stmt->bind_param('iss', $student_id, $from_month, $to_month);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_months = count($records);
$paid_count = 0;
$unpaid_count = 0;
$total_paid_amount = 0;
$total_unpaid_amount = 0;

foreach ($records as $key => $r) {
    // Get total paid for this month
    $pay_query = "SELECT SUM(amount) as paid_sum FROM payments WHERE student_id = ? AND paid_for_month = ?";
    $p_stmt = $conn->prepare($pay_query);
    $p_stmt->bind_param('is', $student_id, $r['month']);
    $p_stmt->execute();
    $pay_res = $p_stmt->get_result()->fetch_assoc();
    $p_stmt->close();
    
    $paid_for_this_month = floatval($pay_res['paid_sum'] ?? 0);
    $remaining_for_this_month = floatval($r['amount']);
    
    // The historical monthly fee that was set for this month
    $original_fee_for_this_month = $paid_for_this_month + $remaining_for_this_month;
    
    // Fallback if both are 0
    if ($original_fee_for_this_month == 0) {
        $original_fee_for_this_month = floatval($student['monthly_fee']);
    }
    
    $records[$key]['display_amount'] = $original_fee_for_this_month;
    $records[$key]['paid_amount'] = $paid_for_this_month;
    
    if ($r['status'] == 'paid') {
        $paid_count++;
        $total_paid_amount += $paid_for_this_month;
    } else {
        $unpaid_count++;
        $total_unpaid_amount += $remaining_for_this_month;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Statement - <?php echo htmlspecialchars($student['name']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 10mm;
            background: white;
            color: #333;
        }
        .statement-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 1mm;
            border: 1px solid #ddd;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1f5f46;
            padding-bottom: 2mm;
            margin-bottom: 4mm;
        }
        /* FIX: Logo container ko CSS se force kiya taake size chota ho sake */
        .logo-wrapper img {
            width: 120px !important; /* Agar 10px boht chota ho to aap isay yahan se adjust kar sakte hain */
            height: 120px;
        }
        .header h1 {
            margin: 0 0 1mm;
            color: #1f5f46;
            font-size: 24px;
        }
        .header p {
            margin: 1mm 0;
            color: #666;
            font-size: 12px;
        }
        .student-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px;
            margin-bottom: 4mm;
            background: #f9f9f9;
            padding: 3mm;
            border-radius: 5px;
            font-size: 13px;
        }
        .student-info p {
            margin: 1mm 0;
        }
        .student-info strong {
            color: #1f5f46;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8mm;
            font-size: 12px;
        }
        table th {
            background: #1f5f46;
            color: white;
            border: 1px solid #1f5f46;
            padding: 3mm;
            text-align: left;
            font-weight: bold;
        }
        table td {
            border: 1px solid #ddd;
            padding: 3mm;
        }
        table tr:nth-child(even) {
            background: #fdfdfd;
        }
        .badge {
            display: inline-block;
            padding: 1mm 2mm;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 10mm;
            font-size: 13px;
        }
        .summary-card {
            background: #f5f5f5;
            padding: 4mm;
            border-radius: 4px;
            border-left: 4px solid #1f5f46;
        }
        .summary-card.danger {
            border-left-color: #e74c3c;
        }
        .summary-card h4 {
            margin: 0 0 2mm;
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        .summary-card p {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        .footer {
            margin-top: 15mm;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 12px;
        }
        .signature-line {
            width: 50mm;
            border-top: 1px solid #333;
            text-align: center;
            padding-top: 2mm;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .statement-container {
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="statement-container">
        <div class="header">
            <div class="logo-wrapper">
                <?php echo render_system_logo('report-logo'); ?>
            </div>
            <h1><?php echo SITE_NAME; ?></h1>
            <p>Student Fee Account Statement</p>
            <p>Range: <strong><?php echo htmlspecialchars($from_month); ?></strong> to <strong><?php echo htmlspecialchars($to_month); ?></strong></p>
        </div>

        <div class="student-info">
            <div>
                <p><strong>Student Name:</strong> <?php echo htmlspecialchars($student['name']); ?></p>
                <p><strong>Father's Name:</strong> <?php echo htmlspecialchars($student['father_name']); ?></p>
                <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($student['contact_number']); ?></p>
            </div>
            <div>
                <p><strong>Class - Section:</strong> <?php echo htmlspecialchars($student['class'] . '-' . $student['section']); ?></p>
                <p><strong>Monthly Fee:</strong> <?php echo format_currency($student['monthly_fee']); ?></p>
                <p><strong>Statement Date:</strong> <?php echo date('d-m-Y h:i A'); ?></p>
            </div>
        </div>

        <h3>Fee Account Details</h3>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Monthly Fee</th>
                    <th>Amount Paid</th>
                    <th>Status</th>
                    <th>Payment Date / Status Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($records) > 0): ?>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td><strong><?php echo $r['month']; ?></strong></td>
                            <td><?php echo format_currency($r['display_amount']); ?></td>
                            <td><?php echo format_currency($r['paid_amount']); ?></td>
                            <td>
                                <?php if ($r['status'] == 'paid'): ?>
                                    <span class="badge badge-success">Paid</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if ($r['status'] == 'paid') {
                                    echo !empty($r['payment_date']) ? format_datetime($r['payment_date']) : 'Recorded';
                                } else {
                                    echo '<span style="color:#721c24;">Not Paid</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #666;">No fee records found for the selected range.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        

        <div class="footer">
            <div>
                <p>Printed By: <?php echo get_username(); ?></p>
            </div>
            <div class="signature-line">
                Authorized Signature
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>