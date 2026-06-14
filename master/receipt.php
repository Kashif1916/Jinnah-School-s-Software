<?php
/**
 * Payment Receipt - PDF Generation
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

$fee_id = isset($_GET['fee_id']) ? intval($_GET['fee_id']) : 0;

if (!$fee_id) {
    die('Invalid receipt request');
}

// Get fee record with student details
$query = "SELECT f.*, s.name, s.father_name, s.class, s.section, s.monthly_fee, s.contact_number 
         FROM fee_records f 
         JOIN students s ON f.student_id = s.id 
         WHERE f.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $fee_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Receipt not found');
}

$receipt = $result->fetch_assoc();
$stmt->close();

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
            padding: 10mm;
            background: white;
        }
        .receipt-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 20mm;
            border: 1px solid #333;
            box-sizing: border-box;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1f5f46;
            padding-bottom: 10mm;
            margin-bottom: 10mm;
        }
        .receipt-logo {
            display: block;
            width: 100px;
            height: auto;
            margin: 0 auto 4mm;
        }
        .header p {
            margin: 2mm 0;
            color: #666;
            font-size: 11px;
        }
        .receipt-number {
            float: right;
            text-align: right;
        }
        .receipt-number p {
            margin: 2mm 0;
        }
        .clearfix {
            clear: both;
        }
        .section {
            margin-bottom: 8mm;
        }
        .section-title {
            font-weight: bold;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 2mm;
            margin-bottom: 3mm;
            font-size: 12px;
        }
        .student-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10mm;
            margin-bottom: 8mm;
        }
        .info-item {
            font-size: 11px;
        }
        .info-label {
            font-weight: bold;
            color: #333;
            display: block;
            margin-bottom: 1mm;
        }
        .info-value {
            color: #667;
            display: block;
        }
        .payment-details {
            margin-bottom: 8mm;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5mm;
            font-size: 11px;
        }
        table th {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 3mm;
            text-align: left;
            font-weight: bold;
        }
        table td {
            border: 1px solid #ddd;
            padding: 3mm;
        }
        .signature-section {
            margin-top: 15mm;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20mm;
            font-size: 11px;
        }
        .signature-line {
            border-top: 1px solid #333;
            text-align: center;
            padding-top: 3mm;
        }
        .footer {
            text-align: center;
            font-size: 10px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 5mm;
            margin-top: 10mm;
        }
        .paid-stamp {
            position: absolute;
            top: 50%;
            right: 10%;
            font-size: 60px;
            color: rgba(31, 95, 70, 0.18);
            transform: rotate(-20deg);
            font-weight: bold;
        }
        .amount-display {
            font-size: 14px;
            font-weight: bold;
            color: #1f5f46;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="paid-stamp">PAID</div>
        
        <div class="header">
            <h1><?php echo SITE_NAME; ?></h1>
            <p>School Management Fee Receipt</p>
        </div>
        
        <div class="receipt-number">
            <p><strong>Receipt #:</strong> <?php echo str_pad($fee_id, 6, '0', STR_PAD_LEFT); ?></p>
            <p><strong>Date:</strong> <?php echo date('d-m-Y H:i'); ?></p>
        </div>
        
        <div class="clearfix"></div>
        
        <div class="section">
            <div class="section-title">Student Information</div>
            <div class="student-info">
                <div class="info-item">
                    <span class="info-label">Student Name:</span>
                    <span class="info-value"><?php echo $receipt['name']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Father's Name:</span>
                    <span class="info-value"><?php echo $receipt['father_name']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Class & Section:</span>
                    <span class="info-value"><?php echo $receipt['class'] . '-' . $receipt['section']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Contact Number:</span>
                    <span class="info-value"><?php echo $receipt['contact_number']; ?></span>
                </div>
            </div>
        </div>
        
        <div class="section payment-details">
            <div class="section-title">Payment Details</div>
            <table>
                <tbody>
                    <tr>
                        <td><strong>Fee Month:</strong></td>
                        <td><?php echo $receipt['month']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Amount:</strong></td>
                        <td class="amount-display"><?php echo format_currency($receipt['amount']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Payment Status:</strong></td>
                        <td><?php echo strtoupper($receipt['status']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Payment Date & Time:</strong></td>
                        <td><?php echo format_datetime($receipt['payment_date']); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="signature-section">
            <div class="signature-line">
                <span>Authorized By</span>
            </div>
            <div class="signature-line">
                <span>Received By</span>
            </div>
        </div>
        
        <div class="footer">
            <?php echo render_system_logo('receipt-logo'); ?>
            <p>This is an official receipt issued by <?php echo SITE_NAME; ?>. Please retain this receipt for your records.</p>
            <p>Receipt generated on <?php echo date('d-m-Y H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>
<?php
$content = ob_get_clean();

// Send as HTML - users can print as PDF using browser
header('Content-Type: text/html; charset=utf-8');
echo $content;
?>
