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
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

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
} elseif ($selected_user_id > 0) {
    $query .= " AND e.user_id = ?";
    $params[] = $selected_user_id;
    $param_types .= "i";
}

// Date filters matching expenses.php
if (empty($start_date) && empty($end_date)) {
    $query .= " AND DATE(e.created_at) = CURDATE()";
} elseif (!empty($start_date) && empty($end_date)) {
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

// Filter label for display
if (empty($start_date) && empty($end_date)) {
    $filter_period_label = "Today (" . date('d-m-Y') . ")";
} elseif (!empty($start_date) && empty($end_date)) {
    $filter_period_label = "Date: " . date('d-m-Y', strtotime($start_date));
} elseif (!empty($start_date) && !empty($end_date)) {
    $filter_period_label = date('d-m-Y', strtotime($start_date)) . " to " . date('d-m-Y', strtotime($end_date));
} else {
    $filter_period_label = "Up to " . date('d-m-Y', strtotime($end_date));
}

$filter_user_label = "All Users";
if (is_finance()) {
    $filter_user_label = get_username() . " (Personal)";
} elseif ($selected_user_id > 0) {
    $u_stmt = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
    $u_stmt->bind_param('i', $selected_user_id);
    $u_stmt->execute();
    $u_res = $u_stmt->get_result()->fetch_assoc();
    if ($u_res) {
        $filter_user_label = htmlspecialchars($u_res['username']) . " (" . ucfirst($u_res['role']) . ")";
    }
    $u_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expenses Report - <?php echo SITE_NAME; ?></title>
    <style>
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
        .meta-info {
            display: grid;
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
            <div style="display: flex; align-items: center; gap: 15px;">
                <?php echo render_system_logo('report-logo'); ?>
                <div style="text-align: left;">
                    <h2 style="margin: 0; color: #1f5f46; font-size: 20px; font-weight: bold;">Jinnah School And Intermediate College Khushab</h2>
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">Expenses Statement Report</p>
                </div>
            </div>
        </div>

        <div class="meta-info">
            <div>
                <p><strong>Report Generated By:</strong> <?php echo get_username(); ?> (<?php echo ucfirst($current_role); ?>)</p>
                <p><strong>Filter User:</strong> <?php echo $filter_user_label; ?></p>
            </div>
            <div>
                <p><strong>Period:</strong> <?php echo $filter_period_label; ?></p>
                <p><strong>Print Date:</strong> <?php echo date('d-m-Y h:i A'); ?> | <strong>Items:</strong> <?php echo count($expenses); ?></p>
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
                    <th style="text-align: right;">Amount</th>
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
                            <td style="text-align: right; color: #e74c3c; font-weight: bold;">- <?php echo format_currency($exp['amount']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="4" style="text-align: right;">Grand Total:</td>
                        <td style="text-align: right; color: #e74c3c; font-weight: bold;">- <?php echo format_currency($total_amount); ?></td>
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