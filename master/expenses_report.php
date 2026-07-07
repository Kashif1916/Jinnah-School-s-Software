<?php
/**
 * Expenses Statement/Report - Printable
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// Allow Master and Finance roles
require_login();
if (!is_master() && !is_finance()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$start_date = sanitize_input($_GET['start_date'] ?? '');
$end_date = sanitize_input($_GET['end_date'] ?? '');

// Users table k sath JOIN lagaya taake username mil sake
$query = "SELECT e.*, u.username FROM expenses e 
          LEFT JOIN users u ON e.user_id = u.id 
          WHERE 1=1";
$params = [];
$param_types = "";

// If the user is a finance user, they can only view and print their own expenses
if (is_finance()) {
    $my_user_id = get_user_id();
    $query .= " AND e.user_id = ?";
    $params[] = $my_user_id;
    $param_types .= "i";
}

// Date filters
if (!empty($start_date) && empty($end_date)) {
    $query .= " AND DATE(e.created_at) = ?";
    $params[] = $start_date;
    $param_types .= "s";
} elseif (!empty($start_date) && !empty($end_date)) {
    $query .= " AND DATE(e.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $param_types .= "ss";
} elseif (empty($start_date) && !empty($end_date)) {
    $query .= " AND DATE(e.created_at) <= ?";
    $params[] = $end_date;
    $param_types .= "s";
}

$query .= " ORDER BY e.created_at DESC, e.id DESC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$expenses = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_amount = 0;
foreach ($expenses as $exp) {
    $total_amount += floatval($exp['amount']);
}

$current_role = $_SESSION['role'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expenses Report - <?php echo SITE_NAME; ?></title>
    <style>
        /* MATCHED: Student Fee Statement ke mutabik tight layout */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 10mm;
            background: white;
            color: #333;
        }
        .report-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 1mm; /* Spacing kam kar di */
            border: 1px solid #ddd;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1f5f46;
            padding-bottom: 2mm; /* Compact padding */
            margin-bottom: 4mm;  /* Compact margin */
        }
        /* MATCHED: Logo container and image size */
        .logo-wrapper img {
            width: 120px !important; 
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
        .meta-info {
            display: grid; /* Table layout se grid/flex styling clear ki */
            grid-template-columns: 1fr 1fr;
            gap: 2px;
            margin-bottom: 4mm;
            background: #f9f9f9;
            padding: 3mm;
            border-radius: 5px;
            font-size: 13px;
        }
        .meta-info p {
            margin: 1mm 0;
        }
        h3 {
            margin-top: 0;
            margin-bottom: 2mm;
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
        .total-row {
            font-weight: bold;
            background: #eaeaea !important;
            font-size: 13px;
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
            .report-container {
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="header">
            <div class="logo-wrapper">
                <?php echo render_system_logo('report-logo'); ?>
            </div>
            <h1><?php echo SITE_NAME; ?></h1>
            <p>Expenses Statement Report</p>
            <p>
                <?php if (!empty($start_date) && empty($end_date)): ?>
                    For Date: <strong><?php echo date('d-m-Y', strtotime($start_date)); ?></strong>
                <?php elseif (!empty($start_date) && !empty($end_date)): ?>
                    Date Range: <strong><?php echo date('d-m-Y', strtotime($start_date)); ?></strong> to <strong><?php echo date('d-m-Y', strtotime($end_date)); ?></strong>
                <?php else: ?>
                    Showing: <strong>All Records</strong>
                <?php endif; ?>
            </p>
        </div>

        <div class="meta-info">
            <div>
                <p><strong>Report Generated By:</strong> <?php echo get_username(); ?> (<?php echo ucfirst($current_role); ?>)</p>
                <p><strong>Scope:</strong> <?php echo is_finance() ? 'Personal Recorded Expenses' : 'All Users Expenses'; ?></p>
            </div>
            <div>
                <p><strong>Print Date:</strong> <?php echo date('d-m-Y H:i'); ?></p>
                <p><strong>Total Recorded Items:</strong> <?php echo count($expenses); ?></p>
            </div>
        </div>

        <h3>Expense Details</h3>
        <table>
            <thead>
                <tr>
                    <th>Sr. No.</th>
                    <th>Date & Time</th>
                    <th>Recorded By</th>
                    <th>Reason / Details</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($expenses) > 0): ?>
                    <?php 
                    $sr = 1;
                    foreach ($expenses as $exp): 
                    ?>
                        <tr>
                            <td><?php echo $sr++; ?></td>
                            <td><?php echo format_datetime($exp['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($exp['username'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($exp['reason']); ?></td>
                            <td><?php echo format_currency($exp['amount']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="4" style="text-align: right;">Grand Total:</td>
                        <td><?php echo format_currency($total_amount); ?></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #666;">No expense records found matching the filter criteria.</td>
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