<?php
/**
 * Defaulter Fee Challan Generator - PDF / Printable View
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// Allow Master, Finance, Admission, and Teacher roles
require_login();

$class_filter = isset($_REQUEST['class']) ? sanitize_input($_REQUEST['class']) : '';
$section_filter = isset($_REQUEST['section']) ? sanitize_input($_REQUEST['section']) : '';
$name_filter = isset($_REQUEST['name']) ? sanitize_input($_REQUEST['name']) : '';
$months_filter = isset($_REQUEST['months']) ? (is_array($_REQUEST['months']) ? $_REQUEST['months'] : [sanitize_input($_REQUEST['months'])]) : [];

$selected_student_ids = [];
if (isset($_REQUEST['student_ids'])) {
    if (is_array($_REQUEST['student_ids'])) {
        $selected_student_ids = array_map('intval', $_REQUEST['student_ids']);
    } else {
        $selected_student_ids = array_map('intval', explode(',', $_REQUEST['student_ids']));
    }
} elseif (isset($_REQUEST['student_id'])) {
    $selected_student_ids = [intval($_REQUEST['student_id'])];
}

// Fetch defaulter students
$defaulters_query = get_defaulters($class_filter, $section_filter, $months_filter, $name_filter);
$all_defaulter_list = [];
if ($defaulters_query) {
    $all_defaulter_list = $defaulters_query->fetch_all(MYSQLI_ASSOC);
}

// Filter down to selected students if specific IDs were submitted
$defaulter_list = [];
if (!empty($selected_student_ids)) {
    foreach ($all_defaulter_list as $d) {
        if (in_array(intval($d['id']), $selected_student_ids, true)) {
            $defaulter_list[] = $d;
        }
    }
} else {
    $defaulter_list = $all_defaulter_list;
}

if (empty($defaulter_list)) {
    die('<div style="padding: 20px; font-family: sans-serif; text-align: center;"><h3>No pending fee records found for the selected student(s).</h3><a href="javascript:history.back()">Go Back</a></div>');
}

// DIRECT DB FETCH: Har defaulter student ka exact fee structure fetch kar rahe hain
foreach ($defaulter_list as $key => $student_data) {
    $s_id = intval($student_data['id']);
    $stmt_fee = $conn->prepare("SELECT fixed_monthly_fee, concession_amount, monthly_fee FROM students WHERE id = ?");
    $stmt_fee->bind_param('i', $s_id);
    $stmt_fee->execute();
    $fee_res = $stmt_fee->get_result()->fetch_assoc();
    $stmt_fee->close();

    if ($fee_res) {
        $defaulter_list[$key]['fixed_monthly_fee'] = $fee_res['fixed_monthly_fee'];
        $defaulter_list[$key]['concession_amount'] = $fee_res['concession_amount'];
        $defaulter_list[$key]['monthly_fee']      = $fee_res['monthly_fee'];
    }
}

// Get custom notes from settings
$receipt_note = '';
$challan_note = '';
$setting_res = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('receipt_note', 'challan_note')");
if ($setting_res) {
    while ($row = $setting_res->fetch_assoc()) {
        if ($row['setting_key'] === 'receipt_note') {
            $receipt_note = $row['setting_value'];
        } elseif ($row['setting_key'] === 'challan_note') {
            $challan_note = $row['setting_value'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Fee Challans - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 10px;
            background: #f4f6f9;
            color: #333;
        }

        .no-print-bar {
            max-width: 850px;
            margin: 0 auto 10px auto;
            background: #ffffff;
            padding: 8px 15px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-print {
            background: #1f5f46;
            color: #fff;
            border: none;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print:hover {
            background: #154331;
        }

        .challan-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 850px;
            margin: 0 auto;
        }

        .challan-card {
            background: #ffffff;
            border: 1.5px solid #1f5f46;
            border-radius: 5px;
            padding: 8px 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.03);
            position: relative;
            height: auto;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .challan-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 4px;
            margin-bottom: 5px;
        }

        .header-logo-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .challan-logo {
            width: 36px;
            height: auto;
        }

        .school-info h3 {
            margin: 0;
            color: #1f5f46;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.1;
        }
        .school-info p {
            margin: 1px 0 0 0;
            font-size: 9.5px;
            color: #666;
        }

        .challan-title-badge {
            background: #1f5f46;
            color: #ffffff;
            padding: 3px 8px;
            font-size: 9.5px;
            font-weight: bold;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .student-details-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4px 8px;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            margin-bottom: 6px;
            font-size: 10px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }
        .detail-label {
            font-weight: bold;
            color: #555;
            font-size: 9px;
        }
        .detail-value {
            font-weight: 600;
            color: #111;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pending-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            font-size: 10px;
        }

        .pending-table th {
            background: #1f5f46;
            color: white;
            padding: 3px 6px;
            text-align: left;
            font-size: 9.5px;
        }

        .pending-table td {
            padding: 3px 6px;
            border-bottom: 1px solid #e9ecef;
        }

        .total-row td {
            font-weight: bold;
            font-size: 10.5px;
            background: #eef5f2 !important;
            border-top: 1.5px solid #1f5f46;
            color: #1f5f46;
            padding: 3px 6px;
        }

        .challan-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 6px;
            font-size: 9px;
            color: #666;
        }

        .signature-box {
            text-align: center;
            border-top: 1px solid #333;
            width: 110px;
            padding-top: 2px;
            font-weight: bold;
            color: #333;
            font-size: 9px;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 6mm;
            }
            body {
                background: #fff;
                padding: 0;
            }
            .no-print-bar {
                display: none !important;
            }
            .challan-container {
                gap: 12px;
                display: block;
            }
            .challan-card {
                box-shadow: none;
                border: 1px solid #000;
                padding: 6px 10px;
                height: auto !important;
                margin-bottom: 12px;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            .challan-title-badge {
                background: #000 !important;
                color: #fff !important;
            }
            .pending-table th {
                background: #000 !important;
                color: #fff !important;
            }
            .total-row td {
                background: #f0f0f0 !important;
                color: #000 !important;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <div>
            <h4 style="margin:0; color:#1f5f46;"><i class="fas fa-file-invoice"></i> Pending Fee Challan Slips</h4>
            <small style="color:#666;">Generated <?php echo count($defaulter_list); ?> Challan(s)</small>
        </div>
        <button onclick="window.print()" class="btn-print">
            <i class="fas fa-print"></i> Print / Save PDF
        </button>
    </div>

    <div class="challan-container">
        <?php foreach ($defaulter_list as $student): ?>
            <?php
            $student_id = intval($student['id']);

            // Fetch detailed unpaid records
            $unpaid_query = "SELECT id, month, amount FROM fee_records 
                             WHERE student_id = ? 
                             AND status = 'unpaid' 
                             AND (
                                 month IN ('Admission', 'Pre_Year', 'Prev-Year', 'Pre-Year') 
                                 OR STR_TO_DATE(CONCAT('01-', month), '%d-%b-%Y') <= LAST_DAY(CURRENT_DATE())
                             )";
            
            if (!empty($months_filter)) {
                $expanded_months = [];
                foreach ((array)$months_filter as $m) {
                    $expanded_months[] = $m;
                    if ($m === 'Pre_Year' || $m === 'Prev-Year' || $m === 'Pre-Year') {
                        $expanded_months[] = 'Pre_Year';
                        $expanded_months[] = 'Prev-Year';
                        $expanded_months[] = 'Pre-Year';
                    }
                }
                $expanded_months = array_unique($expanded_months);
                $escaped_months = array_map(function($m) use ($conn) { 
                    return "'" . $conn->real_escape_string($m) . "'"; 
                }, $expanded_months);
                $unpaid_query .= " AND month IN (" . implode(',', $escaped_months) . ")";
            }
            
            $unpaid_query .= " ORDER BY CASE WHEN month = 'Admission' THEN 1 WHEN month IN ('Pre_Year', 'Prev-Year', 'Pre-Year') THEN 2 ELSE 3 END, STR_TO_DATE(CONCAT('01-', month), '%d-%b-%Y')";
            
            $stmt_unpaid = $conn->prepare($unpaid_query);
            $stmt_unpaid->bind_param('i', $student_id);
            $stmt_unpaid->execute();
            $unpaid_records = $stmt_unpaid->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_unpaid->close();

            // Direct Grouping Algorithm
            $grouped_challan = [];
            $total_student_pending = 0;

            foreach ($unpaid_records as $rec) {
                $paid_month = trim($rec['month']);
                $amt = floatval($rec['amount']);
                $total_student_pending += $amt;

                $is_admission = ($paid_month === 'Admission');
                $is_prev_year = (in_array($paid_month, ['Pre_Year', 'Prev-Year', 'Pre-Year']) || strpos($paid_month, 'Prev-Year') !== false);
                
                if ($is_admission) {
                    $group_key = 'admission';
                } elseif ($is_prev_year) {
                    $group_key = 'prev_year_' . $rec['id'];
                } else {
                    $group_key = 'regular_months';
                }

                if (!isset($grouped_challan[$group_key])) {
                    $grouped_challan[$group_key] = [
                        'title' => '',
                        'type' => $group_key,
                        'months' => [],
                        'total_amount' => 0.0
                    ];
                }

                if ($is_admission) {
                    $grouped_challan[$group_key]['title'] = 'Admission Fee';
                } elseif ($is_prev_year) {
                    $grouped_challan[$group_key]['title'] = 'Previous Year Pending Fee (' . $paid_month . ')';
                } else {
                    $grouped_challan[$group_key]['months'][] = $paid_month;
                }

                $grouped_challan[$group_key]['total_amount'] += $amt;
            }

            // Build Title for grouped regular months
            if (isset($grouped_challan['regular_months']) && !empty($grouped_challan['regular_months']['months'])) {
                $grouped_challan['regular_months']['title'] = 'Fee for Months: ' . implode(', ', $grouped_challan['regular_months']['months']);
            }
            ?>
            <div class="challan-card">
                <div class="challan-header">
                    <div class="header-logo-title">
                        <img src="../images/logo.jfif" alt="Logo" class="challan-logo">
                        <div class="school-info">
                            <h3>Jinnah High School & Intermediate College Khushab</h3>
                            <p>Fee Pending Slip / Challan | Issued Date: <?php echo date('d-m-Y'); ?></p>
                        </div>
                    </div>
                    <div class="challan-title-badge">
                        Fee Challan
                    </div>
                </div>

                <div class="student-details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Student Name</span>
                        <span class="detail-value"><?php echo htmlspecialchars($student['name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Father Name</span>
                        <span class="detail-value"><?php echo htmlspecialchars($student['father_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Class & Sec</span>
                        <span class="detail-value"><?php echo htmlspecialchars($student['class'] . '-' . $student['section']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Contact</span>
                        <span class="detail-value"><?php echo htmlspecialchars($student['contact_number'] ?? 'N/A'); ?></span>
                    </div>
                </div>

                <table class="pending-table">
                    <thead>
                        <tr>
                            <th style="width: 30px;">#</th>
                            <th>Pending Month / Particulars</th>
                            <th style="text-align: right;">Amount (Rs.)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sr = 1; foreach ($grouped_challan as $group): ?>
                            <tr>
                                <td><?php echo $sr++; ?></td>
                                <td>
                                    <strong style="font-size: 10px;"><?php echo htmlspecialchars($group['title']); ?></strong>
                                    <?php 
                                    if ($group['type'] === 'regular_months') {
                                        $month_count = count($group['months']);
                                        if ($month_count > 0) {
                                            $fixed_fee  = floatval($student['fixed_monthly_fee'] ?? 0);
                                            $concession = floatval($student['concession_amount'] ?? 0);

                                            if ($concession > 0) {
                                                $payable_per_month = $fixed_fee - $concession;
                                                echo "<br><small style='font-size: 9px; color: #555;'>" 
                                                     . number_format($fixed_fee, 0) . " - " . number_format($concession, 0) . " = " . number_format($payable_per_month, 0) 
                                                     . " (Per Month)</small>";
                                            } else {
                                                $monthly_val = ($fixed_fee > 0) ? $fixed_fee : ($group['total_amount'] / $month_count);
                                                echo "<br><small style='font-size: 9px; color: #555;'>Fee Per Month = " 
                                                     . number_format($monthly_val, 0) 
                                                     . "</small>";
                                            }
                                        }
                                    }
                                    ?>
                                </td>
                                <td style="text-align: right; font-weight: 600;"><?php echo number_format($group['total_amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2" style="text-align: right;">TOTAL REMAINING FEE DUE:</td>
                            <td style="text-align: right; font-size: 11px;">Rs. <?php echo number_format($total_student_pending, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>

                <div class="challan-footer">
                    <div>
                        <p style="margin:0;"><strong>Notice:</strong> Please deposit the pending fee at the earliest.</p>
                        <?php if (!empty($challan_note)): ?>
                            <p style="margin: 1px 0 0 0; color:#1f5f46; font-weight: bold; font-size: 9px; white-space: pre-line;"><?php echo htmlspecialchars($challan_note); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="signature-box">
                        Accounts / Office
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</body>
</html>