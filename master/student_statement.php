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

$is_package_student = (is_college_class($student['class']) || !empty($student['is_package']));

if ($is_package_student) {
    // ----------------------------------------------------
    // Yearly Package Student Statement (Class 11, 12, Passed-12)
    // ----------------------------------------------------
    $package_amount = floatval($student['package_amount'] ?? 0);
    if ($package_amount <= 0) {
        $package_amount = floatval($student['fixed_monthly_fee'] ?? 0);
    }
    if ($package_amount <= 0 && !empty($student['class'])) {
        $fs_stmt = $conn->prepare("SELECT fixed_monthly_fee FROM fee_schedule WHERE class = ?");
        $fs_stmt->bind_param('s', $student['class']);
        $fs_stmt->execute();
        $fs_res = $fs_stmt->get_result()->fetch_assoc();
        if ($fs_res) {
            $package_amount = floatval($fs_res['fixed_monthly_fee']);
        }
        $fs_stmt->close();
    }
    $concession_amount = floatval($student['concession_amount'] ?? 0);
    $admission_fee = floatval($student['admission_fee'] ?? 0);
    $net_package_amount = max(0, $package_amount - $concession_amount);
    $total_expected = $net_package_amount + $admission_fee;

    // Fetch all payments made by this student from payments table
    $p_stmt = $conn->prepare("SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date ASC, id ASC");
    $p_stmt->bind_param('i', $student_id);
    $p_stmt->execute();
    $package_payments = $p_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $p_stmt->close();

    $total_paid_amount = 0;
    foreach ($package_payments as $p) {
        $total_paid_amount += floatval($p['amount']);
    }

    // Check fee records for outstanding balance
    $fr_stmt = $conn->prepare("SELECT * FROM fee_records WHERE student_id = ?");
    $fr_stmt->bind_param('i', $student_id);
    $fr_stmt->execute();
    $fee_recs = $fr_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $fr_stmt->close();

    $remaining_balance = 0;
    foreach ($fee_recs as $fr) {
        if ($fr['status'] === 'unpaid' && floatval($fr['amount']) > 0) {
            $remaining_balance += floatval($fr['amount']);
        }
    }
    if (empty($fee_recs)) {
        $remaining_balance = max(0, $total_expected - $total_paid_amount);
    }
} else {
    // ----------------------------------------------------
    // Regular Monthly Fee Student Statement
    // ----------------------------------------------------
    if (empty($from_month) || empty($to_month)) {
        $range_stmt = $conn->prepare("SELECT month FROM fee_records WHERE student_id = ? AND month != 'Admission' ORDER BY STR_TO_DATE(CONCAT('01-', month), '%d-%b-%Y') ASC");
        $range_stmt->bind_param('i', $student_id);
        $range_stmt->execute();
        $range_res = $range_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $range_stmt->close();

        if (!empty($range_res)) {
            $from_month = $range_res[0]['month'];
            $to_month = $range_res[count($range_res) - 1]['month'];
        } else {
            $from_month = date('M-Y');
            $to_month = date('M-Y');
        }
    }

    // Fetch fee records in range
    if ($from_month === $to_month || strpos($from_month, 'Package') !== false || strpos($to_month, 'Package') !== false) {
        $query = "SELECT * FROM fee_records 
                  WHERE student_id = ? 
                  AND (month = ? OR month = ?)
                  ORDER BY CASE WHEN month = 'Admission' THEN 1 WHEN month LIKE '%Package%' THEN 2 ELSE 3 END";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iss', $student_id, $from_month, $to_month);
    } else {
        $query = "SELECT * FROM fee_records 
                  WHERE student_id = ? 
                  AND STR_TO_DATE(CONCAT('01-', month), '%d-%b-%Y') BETWEEN STR_TO_DATE(CONCAT('01-', ?), '%d-%b-%Y') AND STR_TO_DATE(CONCAT('01-', ?), '%d-%b-%Y')
                  ORDER BY STR_TO_DATE(CONCAT('01-', month), '%d-%b-%Y')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iss', $student_id, $from_month, $to_month);
    }
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $total_months = count($records);
    $paid_count = 0;
    $unpaid_count = 0;
    $total_paid_amount = 0;
    $total_unpaid_amount = 0;

    foreach ($records as $key => $r) {
        $pay_query = "SELECT SUM(amount) as paid_sum FROM payments WHERE student_id = ? AND paid_for_month = ?";
        $p_stmt = $conn->prepare($pay_query);
        $p_stmt->bind_param('is', $student_id, $r['month']);
        $p_stmt->execute();
        $pay_res = $p_stmt->get_result()->fetch_assoc();
        $p_stmt->close();
        
        $paid_for_this_month = floatval($pay_res['paid_sum'] ?? 0);
        $remaining_for_this_month = floatval($r['amount']);
        
        $original_fee_for_this_month = $paid_for_this_month + $remaining_for_this_month;
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
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #1f5f46;
            padding-bottom: 4mm;
            margin-bottom: 6mm;
        }
        .report-logo {
            width: 80px !important;
            height: auto !important;
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
            <div style="display: flex; align-items: center; gap: 15px;">
                <?php echo render_system_logo('report-logo'); ?>
                <div style="text-align: left;">
                    <h2 style="margin: 0; color: #1f5f46; font-size: 20px; font-weight: bold;">Jinnah School And Intermediate College Khushab</h2>
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">Student Fee Account Statement</p>
                </div>
            </div>
        </div>

        <div class="student-info">
            <div>
                <p><strong>Student Name:</strong> <?php echo htmlspecialchars($student['name']); ?></p>
                <p><strong>Father's Name:</strong> <?php echo htmlspecialchars($student['father_name']); ?></p>
                <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($student['contact_number']); ?></p>
            </div>
            <div>
                <p><strong>Class - Section:</strong> <?php echo htmlspecialchars($student['class'] . '-' . $student['section']); ?></p>
                <?php if ($is_package_student): ?>
                    <p><strong>Package Fee:</strong> <?php echo format_currency($package_amount); ?> <?php if ($concession_amount > 0) echo '<span style="font-size:11px;color:#c0392b;">(Concession: -' . format_currency($concession_amount) . ')</span>'; ?></p>
                <?php else: ?>
                    <p><strong>Monthly Fee:</strong> <?php echo format_currency($student['monthly_fee']); ?></p>
                <?php endif; ?>
                <p><strong>Statement Date:</strong> <?php echo date('d-m-Y h:i A'); ?></p>
            </div>
        </div>

        <?php if ($is_package_student): ?>
            <!-- Package Payments History Table -->
            <h3>Package Fee Payment History</h3>
            <table>
                <thead>
                    <tr>
                        <th style="width: 35px;">#</th>
                        <th>Receipt #</th>
                        <th>Payment Date</th>
                        <th>Payment Description</th>
                        <th>Mode</th>
                        <th>Received By</th>
                        <th style="text-align: right;">Amount Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($package_payments)): ?>
                        <?php $sr = 1; foreach ($package_payments as $p): ?>
                            <tr>
                                <td><?php echo $sr++; ?></td>
                                <td><strong><?php echo !empty($p['receipt_number']) ? htmlspecialchars($p['receipt_number']) : str_pad($p['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                <td><?php echo format_datetime($p['payment_date']); ?></td>
                                <td>
                                    <?php 
                                    $desc = $p['paid_for_month'];
                                    if (strpos($desc, 'Package') !== false) {
                                        echo '<span class="badge badge-success">Yearly Package Payment</span>';
                                    } elseif ($desc === 'Admission') {
                                        echo '<span class="badge" style="background:#e3f2fd;color:#1565c0;">Admission Fee</span>';
                                    } elseif ($desc === 'Fine') {
                                        echo '<span class="badge badge-danger">Fine / Late Fee</span>';
                                    } else {
                                        echo htmlspecialchars($desc);
                                    }
                                    ?>
                                </td>
                                <td><?php echo strtoupper(str_replace('_', ' ', $p['payment_mode'] ?? 'cash')); ?></td>
                                <td><?php echo htmlspecialchars($p['received_by'] ?? 'System'); ?></td>
                                <td style="text-align: right; font-weight: bold; color: #1f5f46;"><?php echo format_currency($p['amount']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background-color: #f1f8f5; font-weight: bold;">
                            <td colspan="6" style="text-align: right;">Total Amount Paid:</td>
                            <td style="text-align: right; color: #1f5f46;"><?php echo format_currency($total_paid_amount); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #666; padding: 6mm;">No payments recorded yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            

        <?php else: ?>
            <!-- Monthly Fee Details Table -->
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
                    <?php if (!empty($records) && count($records) > 0): ?>
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

            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Total Months</h4>
                    <p><?php echo $total_months; ?></p>
                </div>
                <div class="summary-card">
                    <h4>Total Paid</h4>
                    <p style="color: #1f5f46;"><?php echo format_currency($total_paid_amount); ?></p>
                    <small style="color: #666;"><?php echo $paid_count; ?> Month(s) Paid</small>
                </div>
                <div class="summary-card <?php echo ($total_unpaid_amount > 0) ? 'danger' : ''; ?>">
                    <h4>Total Pending</h4>
                    <p style="color: <?php echo ($total_unpaid_amount > 0) ? '#e74c3c' : '#27ae60'; ?>;"><?php echo format_currency($total_unpaid_amount); ?></p>
                    <small style="color: #666;"><?php echo $unpaid_count; ?> Month(s) Pending</small>
                </div>
            </div>
        <?php endif; ?>

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