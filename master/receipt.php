<?php
/**
 * Payment Receipt - PDF Generation
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_login();

$payment_ids_str = isset($_GET['payment_ids']) ? $_GET['payment_ids'] : '';
$fee_id = isset($_GET['fee_id']) ? intval($_GET['fee_id']) : 0; // Keep for backward compatibility or single fee receipt

$payments_to_display = [];
$student_info = null;
$total_amount_paid = 0;

if (!empty($payment_ids_str)) {
    $payment_ids_arr = array_map('intval', explode(',', $payment_ids_str));
    $placeholders = implode(',', array_fill(0, count($payment_ids_arr), '?'));
    $types = str_repeat('i', count($payment_ids_arr));

    $query = "SELECT p.*, s.name, s.father_name, s.class, s.section, s.contact_number, s.fixed_monthly_fee, s.package_amount, s.is_package, s.concession_amount 
              FROM payments p 
              JOIN students s ON p.student_id = s.id 
              WHERE p.id IN ($placeholders) ORDER BY p.payment_date ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$payment_ids_arr);
    $stmt->execute();
    $payments_to_display = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!empty($payments_to_display)) {
        // Assuming all payments are for the same student for a combined receipt
        $student_info = $payments_to_display[0];
        foreach ($payments_to_display as $payment) {
            $total_amount_paid += $payment['amount'];
        }
    }
} elseif ($fee_id) { // Fallback for single fee_id if payment_ids not provided
    $query = "SELECT f.*, s.name, s.father_name, s.class, s.section, s.monthly_fee, s.fixed_monthly_fee, s.package_amount, s.is_package, s.concession_amount, s.contact_number, p.amount as paid_amount, p.payment_date as payment_recorded_date, p.received_by, p.payment_mode
              FROM fee_records f 
              JOIN students s ON f.student_id = s.id 
              LEFT JOIN payments p ON f.student_id = p.student_id AND f.month = p.paid_for_month AND f.payment_date = p.payment_date
              WHERE f.id = ? ORDER BY p.payment_date DESC LIMIT 1"; // Get the latest payment for this fee record
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $fee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $receipt_data = $result->fetch_assoc();
    $stmt->close();

    if ($receipt_data) {
        $payments_to_display[] = [
            'id' => $receipt_data['id'],
            'student_id' => $receipt_data['student_id'],
            'name' => $receipt_data['name'],
            'father_name' => $receipt_data['father_name'],
            'class' => $receipt_data['class'],
            'section' => $receipt_data['section'],
            'contact_number' => $receipt_data['contact_number'],
            'amount' => $receipt_data['paid_amount'] ?? $receipt_data['amount'],
            'paid_for_month' => $receipt_data['month'],
            'payment_date' => $receipt_data['payment_recorded_date'] ?? $receipt_data['payment_date'],
            'received_by' => $receipt_data['received_by'] ?? 'System',
            'payment_mode' => $receipt_data['payment_mode'] ?? 'cash',
            'fixed_monthly_fee' => $receipt_data['fixed_monthly_fee'],
            'package_amount' => $receipt_data['package_amount'] ?? 0,
            'is_package' => $receipt_data['is_package'] ?? 0,
            'concession_amount' => $receipt_data['concession_amount']
        ];
        $student_info = $receipt_data;
        $total_amount_paid = $receipt_data['paid_amount'] ?? $receipt_data['amount'];
    }
}

$pending_balances = [];
if (!empty($payments_to_display)) {
    foreach ($payments_to_display as $payment) {
        $stud_id = $payment['student_id'];
        $p_month = $payment['paid_for_month'] ?? $payment['month'] ?? '';
        
        if ($stud_id && $p_month && !in_array($p_month, ['Fine', 'Other', 'Other Payment'])) {
            // Fetch student details for pending section
            $q = "SELECT f.amount, f.status, s.name, s.father_name 
                  FROM fee_records f 
                  JOIN students s ON f.student_id = s.id 
                  WHERE f.student_id = ? AND f.month = ?";
            $stmt = $conn->prepare($q);
            $stmt->bind_param('is', $stud_id, $p_month);
            $stmt->execute();
            $rec = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($rec && $rec['status'] == 'unpaid' && $rec['amount'] > 0) {
                $pending_balances[] = [
                    'student_name' => $rec['name'],
                    'father_name' => $rec['father_name'],
                    'month' => $p_month,
                    'amount' => $rec['amount']
                ];
            }
        }
    }
}

$grouped_payments = [];
if (!empty($payments_to_display)) {
    foreach ($payments_to_display as $payment) {
        $student_id = $payment['student_id'];
        $paid_month = $payment['paid_for_month'] ?? $payment['month'] ?? '';
        
        $is_admission = (trim($paid_month) === 'Admission');
        $is_prev_year = (trim($paid_month) === 'Prev-Year' || strpos($paid_month, 'Prev-Year') !== false);
        $is_fine = (trim($paid_month) === 'Fine' || strpos($paid_month, 'Fine') !== false);
        $is_other = (trim($paid_month) === 'Other' || trim($paid_month) === 'Other Payment' || strpos($paid_month, 'Other') !== false);
        
        $is_pending = false;
        if ($paid_month && !$is_admission && !$is_prev_year && !$is_fine && !$is_other) {
            $stmt_check = $conn->prepare("SELECT id FROM payments WHERE student_id = ? AND paid_for_month = ? AND id < ? LIMIT 1");
            $stmt_check->bind_param("isi", $student_id, $paid_month, $payment['id']);
            $stmt_check->execute();
            $res_check = $stmt_check->get_result();
            if ($res_check->num_rows > 0) {
                $is_pending = true;
            }
            $stmt_check->close();
        }

        if ($is_admission) {
            $group_key = $student_id . '_admission';
        } elseif ($is_prev_year) {
            $group_key = $student_id . '_prev_year_' . $payment['id'];
        } elseif ($is_pending) {
            $group_key = $student_id . '_pending_' . $payment['id'];
        } elseif ($is_fine) {
            $group_key = $student_id . '_fine_' . $payment['id'];
        } elseif ($is_other) {
            $group_key = $student_id . '_other_' . $payment['id'];
        } else {
            $group_key = $student_id . '_months';
        }
        
        if (!isset($grouped_payments[$group_key])) {
            $grouped_payments[$group_key] = [
                'name' => $payment['name'],
                'father_name' => $payment['father_name'],
                'class' => $payment['class'],
                'section' => $payment['section'],
                'fixed_monthly_fee' => $payment['fixed_monthly_fee'] ?? 0,
                'package_amount' => $payment['package_amount'] ?? 0,
                'is_package' => !empty($payment['is_package']) || is_college_class($payment['class']),
                'concession_amount' => $payment['concession_amount'] ?? 0,
                'is_admission' => $is_admission,
                'is_prev_year' => $is_prev_year,
                'is_pending' => $is_pending,
                'is_fine' => $is_fine,
                'is_other' => $is_other,
                'months' => [],
                'total_amount' => 0.0,
                'payment_mode' => $payment['payment_mode'] ?? 'cash',
            ];
        }
        
        if ($is_pending) {
            $grouped_payments[$group_key]['months'][] = $paid_month . ' Arrears';
        } elseif ($is_fine) {
            $grouped_payments[$group_key]['months'][] = 'Fine / Late Fee';
        } elseif ($is_other) {
            $grouped_payments[$group_key]['months'][] = 'Other Payment';
        } else {
            $grouped_payments[$group_key]['months'][] = $paid_month;
        }
        $grouped_payments[$group_key]['total_amount'] += floatval($payment['amount']);
    }
}

if (empty($payments_to_display)) {
    die('Receipt not found');
}

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: white;
            width: 80mm;
        }
        .receipt-container {
            width: 76mm;
            margin: 0;
            background: white;
            padding: 2mm;
            box-sizing: border-box;
            border: none;
            position: relative;
        }
        .header {
            text-align: center;
            padding-bottom: 1mm;
            margin-bottom: 1mm;
        }
        .header p {
            margin: 1mm 0;
            color: #0c0c0c;
            font-size: 12px;
        }
        .receipt-number p {
            margin: 1mm 0;
            font-size: 11px;
            text-align: center;
        }
        .section {
            margin-bottom: 1mm;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3mm;
            font-size: 11px;
        }
        table th {
            border-bottom: 1px solid #333;
            padding: 2mm 0;
            text-align: left;
            font-weight: bold;
        }
        table td {
            padding: 2mm 0;
            vertical-align: top;
            border-bottom: 1px dashed #eee;
        }
        .amount-col {
            text-align: right;
            white-space: nowrap;
        }
        
        /* Total Row Container for relative positioning */
        .total-row-container {
            position: relative;
            font-weight: bold;
        }
        
        /* PAID Stamp exactly inside Total Row Area */
        .paid-stamp-total {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-8deg);
            font-size: 32px;
            font-weight: 900;
            color: rgba(0, 0, 0, 0.22);
            border: 3px solid rgba(0, 0, 0, 0.22);
            padding: 1px 12px;
            z-index: 1;
            pointer-events: none;
            letter-spacing: 3px;
            white-space: nowrap;
            line-height: 1;
            border-radius: 4px;
        }

        .footer {
            text-align: center;
            font-size: 9px;
            color: #0c0c0c;
            padding-top: 1mm;
            margin-top: 0mm;
            position: relative;
            z-index: 2;
        }
        .footer p {
            margin: 0;
            line-height: 1.3;
        }
        
        .receipt-note {
            margin: 2mm auto;
            padding-top: 1mm;
            font-size: 11px;
            text-align: center;
            line-height: 1.4;
            font-weight: bold;
            white-space: pre-line;
            max-width: 72mm;
            word-wrap: break-word;
            box-sizing: border-box;
            display: block;
            position: relative;
            z-index: 2;
        }
        @media print {
            @page {
                margin: 0;
            }
            body {
                width: 80mm;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container"> 
        <div class="header">
            <img src="../images/logo.jfif" style="width: 80px !important; height: auto" alt="Logo">
            <h4> Jinnah High School & Inter College Khushab </h4>
            <p> <strong>Fee Receipt</strong> </p>
        </div>
        
        <div style="position: relative; z-index: 2;">
            <div class="receipt-number">
                <p><strong>Receipt #:</strong> <?php echo !empty($payments_to_display[0]['receipt_number']) ? htmlspecialchars($payments_to_display[0]['receipt_number']) : str_pad($payments_to_display[0]['id'], 6, '0', STR_PAD_LEFT); ?></p>
                <p><strong>Date:</strong> <?php echo date('d-m-Y h:i A'); ?></p>
                <p><strong>Paid By:</strong> <?php echo htmlspecialchars($payments_to_display[0]['received_by'] ?? 'System'); ?></p>
                <p><strong>Method:</strong> <?php echo strtoupper(str_replace('_', ' ', $payments_to_display[0]['payment_mode'] ?? 'cash')); ?></p>
                <p><strong>Phone:</strong>03096684856</p>
                <p><strong></strong>jinnahschoolandintercollegekhb@gmail.com</p>
            </div>
            
            <div class="section payment-details">
                <table>
                    <thead>
                        <tr>
                            <th>Payment Details</th>
                            <th class="amount-col">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grouped_payments as $stud_id => $group): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($group['is_fine'])): ?>
                                        <strong style="font-size: 14px;">Fine for Late Fee</strong>
                                    <?php elseif (!empty($group['is_other'])): ?>
                                        <strong style="font-size: 14px;">Other Payment / Charges</strong>
                                    <?php else: ?>
                                        <strong style="font-size: 14px;"><?php echo htmlspecialchars($group['name']) . ' / ' . htmlspecialchars($group['father_name']); ?></strong><br>
                                        <?php echo htmlspecialchars($group['class']) . '-' . htmlspecialchars($group['section']); ?> | <?php echo implode(', ', $group['months']); ?>
                                        <?php 
                                        if (empty($group['is_admission']) && empty($group['is_prev_year'])) {
                                            $is_pkg = (!empty($group['is_package']) || is_college_class($group['class']) || in_array('Yearly Package', $group['months']) || strpos(implode(',', $group['months']), 'Package') !== false);
                                            if ($is_pkg) {
                                                $raw_pkg_fee = floatval($group['package_amount'] ?? 0);
                                                if ($raw_pkg_fee <= 0) {
                                                    $raw_pkg_fee = floatval($group['fixed_monthly_fee'] ?? 0);
                                                }
                                                if ($raw_pkg_fee <= 0 && !empty($group['class'])) {
                                                    $fs_stmt = $conn->prepare("SELECT fixed_monthly_fee FROM fee_schedule WHERE class = ?");
                                                    $fs_stmt->bind_param('s', $group['class']);
                                                    $fs_stmt->execute();
                                                    $fs_res = $fs_stmt->get_result()->fetch_assoc();
                                                    if ($fs_res) {
                                                        $raw_pkg_fee = floatval($fs_res['fixed_monthly_fee']);
                                                    }
                                                    $fs_stmt->close();
                                                }
                                                $concession = floatval($group['concession_amount'] ?? 0);
                                                if ($concession > 0) {
                                                    $payable = max(0, $raw_pkg_fee - $concession);
                                                    echo "<br><small class='text-muted' style='font-size: 11px; color: #0c0c0c;'>Fee: " . number_format($raw_pkg_fee, 0) . " - " . number_format($concession, 0) . " = " . number_format($payable, 0) . " (Yearly Package)</small>";
                                                } else {
                                                    echo "<br><small class='text-muted' style='font-size: 11px; color: #0c0c0c;'>Package Fee = " . number_format($raw_pkg_fee, 0) . " (Yearly Package)</small>";
                                                }
                                            } elseif (isset($group['fixed_monthly_fee'])) {
                                                if (isset($group['concession_amount']) && $group['concession_amount'] > 0) {
                                                    $payable = floatval($group['fixed_monthly_fee']) - floatval($group['concession_amount']);
                                                    echo "<br><small class='text-muted' style='font-size: 11px; color: #0c0c0c;'>Fee: " . number_format($group['fixed_monthly_fee'], 0) . " - " . number_format($group['concession_amount'], 0) . " = " . number_format($payable, 0) . " (Per Month)</small>";
                                                } else {
                                                    echo "<br><small class='text-muted' style='font-size: 11px; color: #0c0c0c;'>Fee Per Month = " . number_format($group['fixed_monthly_fee'], 0) . "</small>";
                                                }
                                            }
                                        }
                                        ?>
                                    <?php endif; ?>
                                </td>
                                <td class="amount-col" style="font-size: 11px;"><?php echo number_format($group['total_amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- TOTAL ROW WITH CENTERED PAID WATERMARK -->
                        <tr class="total-row-container">
                            <td colspan="2" style="position: relative; padding: 4mm 0;">
                                <div class="paid-stamp-total">PAID</div>
                                <table style="width: 100%; margin: 0; font-weight: bold; position: relative; z-index: 2;">
                                    <tr>
                                        <td style="border: none; text-align: left; padding: 0;">TOTAL:</td>
                                        <td class="amount-col" style="border: none; width: auto; padding: 0;"><?php echo format_currency($total_amount_paid); ?></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($pending_balances)): ?>
                <div style="margin-top: 2mm; border: 1px dashed #c0392b; padding: 2mm; font-size: 10px; background-color: #fdf2f2; border-radius: 4px;">
                    <strong style="color: #c0392b;"><i class="fas fa-exclamation-triangle"></i> Pending Fee:</strong><br>
                    <?php foreach ($pending_balances as $pending): ?>
                        • <?php echo $pending['student_name'] . ' / ' . $pending['father_name']; ?> (<?php echo $pending['month']; ?>): <strong><?php echo format_currency($pending['amount']); ?></strong><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php
        $receipt_note = '';
        $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'receipt_note'");
        if ($res && $row = $res->fetch_assoc()) {
            $receipt_note = $row['setting_value'];
        }
        ?>
        
        <?php if (!empty($receipt_note)): ?>
            <div class="receipt-note">
                <?php echo htmlspecialchars($receipt_note); ?>
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Thank You For Your Payment!</p>
            <p>KJ Software House Khushab</p>
        </div>
        
        <div style="height: 5mm;"></div>
    </div>
    
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
<?php
$content = ob_get_clean();

header('Content-Type: text/html; charset=utf-8');
echo $content;
?>