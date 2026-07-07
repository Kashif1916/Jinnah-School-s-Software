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

    $query = "SELECT p.*, s.name, s.father_name, s.class, s.section, s.contact_number, s.fixed_monthly_fee, s.concession_amount 
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
    $query = "SELECT f.*, s.name, s.father_name, s.class, s.section, s.monthly_fee, s.fixed_monthly_fee, s.concession_amount, s.contact_number, p.amount as paid_amount, p.payment_date as payment_recorded_date, p.received_by, p.payment_mode
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
        
        if ($stud_id && $p_month) {
            // Hum student details bhi fetch kar rahy hain pending section ke liye
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

if (empty($payments_to_display)) {
    die('Receipt not found');
}

// Generate Receipt content
// Start content generation
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
            width: 80mm; /* Standard Thermal Width */
        }
        .receipt-container {
            width: 76mm; /* Slight margin for printer */
            margin: 0;
            background: white;
            padding: 2mm;
            box-sizing: border-box;
            border: none;
        }
        .header {
            text-align: center;
            border-bottom: 1px dashed #333;
            padding-bottom: 5mm;
            margin-bottom: 5mm;
        }
        .header p {
            margin: 1mm 0;
            color: #666;
            font-size: 10px;
        }
        .receipt-number p {
            margin: 1mm 0;
            font-size: 11px;
            text-align: center;
        }
        .section {
            margin-bottom: 5mm;
        }
        .section-title {
            font-weight: bold;
            color: #333;
            border-bottom: 1px dashed #ddd;
            padding-bottom: 1mm;
            margin-bottom: 2mm;
            font-size: 12px;
        }
        .student-info {
            margin-bottom: 5mm;
            font-size: 11px;
            line-height: 1.4;
        }
        .info-label {
            font-weight: bold;
            color: #333;
            width: 80px;
            display: inline-block;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3mm;
            font-size: 10px;
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
        .signature-section {
            margin-top: 10mm;
            font-size: 10px;
        }
        .signature-line {
            border-top: 1px dashed #333;
            text-align: center;
            padding-top: 2mm;
            margin-top: 10mm;
        }
        .footer {
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px dashed #ddd;
            padding-top: 5mm;
            margin-top: 5mm;
        }
        .paid-stamp {
            text-align: center;
            font-size: 24px;
            color: #1f5f46;
            border: 2px solid #1f5f46;
            display: inline-block;
            padding: 1mm 5mm;
            margin: 5mm auto;
            font-weight: bold;
            opacity: 0.5;
        }
        .amount-display {
            font-weight: bold;
            color: #1f5f46;
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
    <div class="receipt-container" style="position: relative;"> <div class="header">
            <img src="../images/logo.jfif" style="width: 120px !important; height: auto" alt="Logo">
            <h2> Jinnah School & Inter College Khushab </h2>
            <p>Fee Receipt</p>
        </div>

        <div class="paid-stamp" style="
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-15deg);
            font-size: 70px;
            font-weight: bold;
            color: rgba(0, 0, 0, 0.18); /* Bohot halka grey jo thermal print mai piche base banayega */
            border: 5px solid rgba(0, 0, 0, 0.08);
            padding: 10px 20px;
            z-index: 1; /* Isko piche rakhne ke liye */
            pointer-events: none;
            letter-spacing: 5px;
        ">PAID</div>
        
        <div style="position: relative; z-index: 2;">
            <div class="receipt-number">
                <p><strong>Receipt #:</strong> <?php echo str_pad($payments_to_display[0]['id'], 6, '0', STR_PAD_LEFT); ?></p>
                <p><strong>Date:</strong> <?php echo date('d-m-Y H:i'); ?></p>
                <p><strong>Method:</strong> <?php echo strtoupper(str_replace('_', ' ', $payments_to_display[0]['payment_mode'] ?? 'cash')); ?></p>
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
                        <?php foreach ($payments_to_display as $payment): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $payment['name'] . ' / ' . $payment['father_name']; ?></strong><br>
                                    <?php echo $payment['class'] . '-' . $payment['section']; ?> | <?php echo $payment['paid_for_month']; ?>
                                    <?php 
                                    // If there is concession, show standard - concession = payable
                                    if (isset($payment['fixed_monthly_fee']) && isset($payment['concession_amount']) && $payment['concession_amount'] > 0) {
                                        $payable = floatval($payment['fixed_monthly_fee']) - floatval($payment['concession_amount']);
                                        echo "<br><small class='text-muted' style='font-size: 9px; color:#555;'>Fee: " . number_format($payment['fixed_monthly_fee'], 0) . " - " . number_format($payment['concession_amount'], 0) . " = " . number_format($payable, 0) . "</small>";
                                    }
                                    ?>
                                </td>
                                <td class="amount-col"><?php echo number_format($payment['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="font-weight: bold;">
                            <td style="text-align: right; padding-top: 4mm;">TOTAL:</td>
                            <td class="amount-col" style="padding-top: 4mm;"><?php echo format_currency($total_amount_paid); ?></td>
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
    </div>
    <div class="signature-section">
        <div class="signature-line">
            <span>Receiver Signature</span>
        </div>
    </div>
    
    <div class="footer">
        <p>Thank you for your payment!</p>
        <p><?php echo date('d-m-Y H:i:s'); ?></p>
    </div>
    
    <div style="height: 10mm;"></div>
        
    <script>
        window.onload = function() {
            window.print();
        };
    </script>

    
</body>
</html>
<?php
$content = ob_get_clean();

// Send as HTML - users can print as PDF using browser
header('Content-Type: text/html; charset=utf-8');
echo $content;
?>