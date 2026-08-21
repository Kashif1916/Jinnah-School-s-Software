<?php
/**
 * Shared Core Fee Payment Logic and UI
 * School Finance Management System
 */

if (!isset($panel_role)) {
    die("Access denied: panel role not specified.");
}

$error = '';
$success = '';
$student_fees = [];
$student = null;
$search_results = [];

if (!isset($_SESSION['fee_cart'])) {
    $_SESSION['fee_cart'] = [];
}
if (!isset($_SESSION['fine_amount'])) {
    $_SESSION['fine_amount'] = 0;
}

$self_url = basename($_SERVER['PHP_SELF']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'add_to_cart') {
        $selected_records = $_POST['selected_fee_records'] ?? [];
        $amounts = $_POST['paid_amount'] ?? [];
        if (isset($_POST['fine_amount'])) {
            $_SESSION['fine_amount'] = floatval($_POST['fine_amount']);
        }
        
        foreach ($selected_records as $rec_id) {
            $rec_id = intval($rec_id);
            $paid_amt = floatval($amounts[$rec_id] ?? 0);
            
            $exists = false;
            foreach ($_SESSION['fee_cart'] as $item) {
                if ($item['record_id'] == $rec_id) { $exists = true; break; }
            }

            if (!$exists && $paid_amt >= 0) {
                $query = "SELECT fr.*, s.name, s.class, s.section FROM fee_records fr 
                          JOIN students s ON fr.student_id = s.id WHERE fr.id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('i', $rec_id);
                $stmt->execute();
                $details = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($details) {
                    $_SESSION['fee_cart'][] = [
                        'record_id' => $rec_id,
                        'student_id' => $details['student_id'],
                        'name' => $details['name'],
                        'month' => $details['month'],
                        'amount' => $paid_amt,
                        'class_info' => $details['class'] . '-' . $details['section']
                    ];
                }
            }
        }
        $success = "Fees added to batch list! You can search another student now.";
    } elseif (isset($_POST['action']) && $_POST['action'] == 'remove_item') {
        $remove_rec_id = intval($_POST['remove_record_id'] ?? 0);
        if (!empty($_SESSION['fee_cart'])) {
            foreach ($_SESSION['fee_cart'] as $key => $item) {
                if ($item['record_id'] == $remove_rec_id) {
                    unset($_SESSION['fee_cart'][$key]);
                    break;
                }
            }
            $_SESSION['fee_cart'] = array_values($_SESSION['fee_cart']);
        }
        if (empty($_SESSION['fee_cart'])) {
            $_SESSION['fine_amount'] = 0;
        } elseif (isset($_POST['fine_amount'])) {
            $_SESSION['fine_amount'] = floatval($_POST['fine_amount']);
        }
        $success = "Item removed from batch list.";
    } elseif (isset($_POST['action']) && $_POST['action'] == 'clear_cart') {
        $_SESSION['fee_cart'] = [];
        $_SESSION['fine_amount'] = 0;
        $success = "Batch list cleared.";
    } elseif (isset($_POST['action']) && $_POST['action'] == 'search') {
        $search_name = sanitize_input($_POST['search_name'] ?? '');
        $search_class = sanitize_input($_POST['search_class'] ?? '');
        $search_section = sanitize_input($_POST['search_section'] ?? '');
        
        if (!empty($search_name) || !empty($search_class) || !empty($search_section)) {
            $query = "SELECT * FROM students WHERE status = 'active'";
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
            
            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($param_types, ...$params);
            }
            $stmt->execute();
            $search_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'process_batch') {
        $generated_payment_ids = [];
        $payment_mode = sanitize_input($_POST['payment_mode'] ?? 'cash');
        $fine_amount = floatval($_POST['fine_amount'] ?? $_SESSION['fine_amount'] ?? 0);
        $_SESSION['fine_amount'] = $fine_amount;
        
        if (!empty($_SESSION['fee_cart'])) {
            $batch_receipt_number = null;
            foreach ($_SESSION['fee_cart'] as $item) {
                $p_id = record_payment($item['student_id'], $item['amount'], $item['month'], get_username(), $payment_mode, $batch_receipt_number);
                if ($p_id) {
                    $generated_payment_ids[] = $p_id;
                    if (empty($batch_receipt_number)) {
                        $r_stmt = $conn->prepare("SELECT receipt_number FROM payments WHERE id = ?");
                        $r_stmt->bind_param('i', $p_id);
                        $r_stmt->execute();
                        $r_res = $r_stmt->get_result()->fetch_assoc();
                        if ($r_res && !empty($r_res['receipt_number'])) {
                            $batch_receipt_number = $r_res['receipt_number'];
                        }
                        $r_stmt->close();
                    }
                } else {
                    $error .= "Error recording fee for " . $item['name'] . " (" . $item['month'] . ") ";
                }
            }

            if ($fine_amount > 0 && !empty($_SESSION['fee_cart'])) {
                $fine_student_id = $_SESSION['fee_cart'][0]['student_id'];
                $fine_p_id = record_payment($fine_student_id, $fine_amount, 'Fine', get_username(), $payment_mode, $batch_receipt_number);
                if ($fine_p_id) {
                    $generated_payment_ids[] = $fine_p_id;
                } else {
                    $error .= "Error recording fine payment. ";
                }
            }

            if (!empty($generated_payment_ids)) {
                $_SESSION['fee_cart'] = [];
                $_SESSION['fine_amount'] = 0;
                $_SESSION['print_receipt_url'] = $receipt_base_url . '?payment_ids=' . implode(',', $generated_payment_ids);
                header('Location: ' . $self_url);
                exit();
            }
        } else {
            $error = "Batch list is empty!";
        }
    }
}

if (isset($_GET['id'])) {
    $student_id = intval($_GET['id']);
    $student = get_student($student_id);
    
    if ($student) {
        $is_college = (is_college_class($student['class']) || !empty($student['is_package']));

        if ($is_college) {
            $query = "SELECT * FROM fee_records WHERE student_id = ? 
                      ORDER BY CASE 
                          WHEN month = 'Admission' THEN 1 
                          WHEN month IN ('Pre_Year', 'Prev-Year', 'Pre-Year') THEN 2 
                          WHEN month LIKE '%Yearly Package%' OR month LIKE '%yearly_package%' OR month LIKE '%yearly-package%' THEN 3 
                          WHEN month LIKE '%Package%' OR month LIKE '%yearly%' THEN 4 
                          ELSE 5 
                      END, id ASC";
        } else {
            $query = "SELECT * FROM fee_records WHERE student_id = ? 
                      ORDER BY CASE 
                          WHEN month = 'Admission' THEN 1 
                          WHEN month IN ('Pre_Year', 'Prev-Year', 'Pre-Year') THEN 2 
                          ELSE 3 
                      END, STR_TO_DATE(CONCAT('01-', month), '%d-%b-%Y'), id ASC";
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $all_fees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $paid_fees = [];
        $unpaid_fees = [];
        foreach ($all_fees as $fee) {
            if ($fee['status'] === 'paid') {
                $paid_fees[] = $fee;
            } else {
                $unpaid_fees[] = $fee;
            }
        }
        
        if (count($paid_fees) > 2) {
            $paid_fees = array_slice($paid_fees, -2);
        }
        
        $student_fees = array_merge($paid_fees, $unpaid_fees);
        
        usort($student_fees, function($a, $b) use ($is_college) {
            if ($a['month'] === 'Admission') return -1;
            if ($b['month'] === 'Admission') return 1;

            $pre_years = ['Pre_Year', 'Prev-Year', 'Pre-Year'];
            if (in_array($a['month'], $pre_years) && !in_array($b['month'], $pre_years)) return -1;
            if (!in_array($a['month'], $pre_years) && in_array($b['month'], $pre_years)) return 1;

            if ($is_college) {
                $a_month = strtolower(trim($a['month']));
                $b_month = strtolower(trim($b['month']));

                // Priority for Class 11 Yearly Package
                $is_a_p1 = (strpos($a_month, 'yearly package') !== false || strpos($a_month, 'yearly_package') !== false || strpos($a_month, 'yearly-package') !== false);
                $is_b_p1 = (strpos($b_month, 'yearly package') !== false || strpos($b_month, 'yearly_package') !== false || strpos($b_month, 'yearly-package') !== false);

                if ($is_a_p1 && !$is_b_p1) return -1;
                if ($is_b_p1 && !$is_a_p1) return 1;

                // Priority for Class 12 Package (Package-12)
                $a_pkg = (strpos($a_month, 'package') !== false || strpos($a_month, 'yearly') !== false);
                $b_pkg = (strpos($b_month, 'package') !== false || strpos($b_month, 'yearly') !== false);

                if ($a_pkg && !$b_pkg) return -1;
                if ($b_pkg && !$a_pkg) return 1;
                if ($a_pkg && $b_pkg) {
                    return $a['id'] - $b['id'];
                }
            }

            $t1 = strtotime("01-" . $a['month']);
            $t2 = strtotime("01-" . $b['month']);
            return $t1 - $t2;
        });
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Payment - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <a href="dashboard.php"><?php echo render_system_logo('topbar-logo'); ?></a>
                    <div class="panel-brand">
                        <h2>Record Fee Payment</h2>
                        <span><?php echo ($panel_role == 'master') ? 'Principal Panel' : 'Finance / Clerk Panel'; ?></span>
                    </div>
                </div>
                <div class="topbar-right">
                    <span class="user-info">
                        <i class="fas fa-user-circle"></i> <?php echo get_username(); ?>
                    </span>
                    <a href="../logout.php" class="btn-secondary">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <div class="content">
                <div class="module-nav-panel">
                    <div class="module-nav-row">
                        <?php if ($panel_role == 'master'): ?>
                            <a href="dashboard.php" class="module-nav-btn">
                                <i class="fas fa-chart-bar"></i> Dashboard
                            </a>
                            <a href="add_student.php" class="module-nav-btn">
                                <i class="fas fa-user-plus"></i> Add Student
                            </a>
                            <a href="student_record.php" class="module-nav-btn">
                                <i class="fas fa-address-book"></i> Student Record
                            </a>
                            <a href="student_add_details.php" class="module-nav-btn">
                                <i class="fas fa-history"></i> Add Log
                            </a>
                            <a href="fee_schedule.php" class="module-nav-btn">
                                <i class="fas fa-calendar-alt"></i> Fee Schedule
                            </a>
                            <a href="fee_management.php" class="module-nav-btn active">
                                <i class="fas fa-money-bill-wave"></i> Fee Management
                            </a>
                            <a href="defaulter_list.php" class="module-nav-btn">
                                <i class="fas fa-list"></i> Pending List
                            </a>
                            <a href="paid_students.php" class="module-nav-btn">
                                <i class="fas fa-check-circle text-success"></i> Paid Students
                            </a>
                            <a href="payment_analytics.php" class="module-nav-btn">
                                <i class="fas fa-chart-line"></i> Analytics
                            </a>
                            <a href="receipt_analysis.php" class="module-nav-btn">
                                <i class="fas fa-receipt"></i> Receipt Analysis
                            </a>
                            <a href="expenses.php" class="module-nav-btn">
                                <i class="fas fa-wallet"></i> Expenses
                            </a>
                            <a href="data_correction.php" class="module-nav-btn">
                                <i class="fas fa-edit"></i> Data Correction
                            </a>
                            <a href="promotion.php" class="module-nav-btn">
                                <i class="fas fa-arrow-up"></i> Promotion
                            </a>
                            <a href="drop_student.php" class="module-nav-btn">
                                <i class="fas fa-trash"></i> Drop Student
                            </a>
                            <a href="delete_student.php" class="module-nav-btn">
                                <i class="fas fa-user-minus text-success"></i> Delete Student
                            </a>
                            <a href="users.php" class="module-nav-btn">
                                <i class="fas fa-users-cog"></i> Users
                            </a>
                            <a href="receipt_note.php" class="module-nav-btn">
                                <i class="fas fa-sticky-note"></i> Receipt Note
                            </a>
                        <?php else: ?>
                            <a href="dashboard.php" class="module-nav-btn">
                                <i class="fas fa-chart-bar"></i> Dashboard
                            </a>
                            <a href="add_student.php" class="module-nav-btn">
                                <i class="fas fa-list"></i> Add Student
                            </a>
                            <a href="student_record.php" class="module-nav-btn">
                                <i class="fas fa-address-book"></i> Student Record
                            </a>
                            <a href="fee_payment.php" class="module-nav-btn active">
                                <i class="fas fa-money-bill-wave"></i> Fee Payment
                            </a>
                            <a href="defaulter_list.php" class="module-nav-btn">
                                <i class="fas fa-list"></i> Pending List
                            </a>
                            <a href="paid_students.php" class="module-nav-btn">
                                <i class="fas fa-check-circle text-success"></i> Paid Students
                            </a>
                            <a href="payment_analytics.php" class="module-nav-btn">
                                <i class="fas fa-chart-line"></i> Analytics
                            </a>
                            <a href="receipt_analysis.php" class="module-nav-btn">
                                <i class="fas fa-receipt"></i> Receipt Analysis
                            </a>
                            <a href="expenses.php" class="module-nav-btn">
                                <i class="fas fa-wallet"></i> Expenses
                            </a>
                            <a href="drop_student.php" class="module-nav-btn">
                                <i class="fas fa-trash text-success"></i> Drop Student
                            </a>
                            <a href="account_close.php" class="module-nav-btn">
                                <i class="fas fa-lock"></i> Close Account
                            </a>
                        <?php endif; ?>
                        <a href="../help.php" class="module-nav-btn">
                            <i class="fas fa-question-circle text-success"></i> Help & About
                        </a>
                    </div>
                </div>

                <div class="form-section">
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($_SESSION['fee_cart'])): ?>
                        <div class="batch-summary mb-5 p-4 border rounded shadow-sm bg-white">
                            <h4 class="text-success mb-3"><i class="fas fa-receipt"></i> Batch List for Receipt</h4>
                            <form method="POST" class="w-100" action="<?php echo $self_url; ?>">
                                <table class="table table-bordered align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Class</th>
                                            <th>Month</th>
                                            <th>Amount</th>
                                            <th class="text-center" style="width: 80px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $batch_total = 0;
                                        foreach ($_SESSION['fee_cart'] as $item): 
                                            $batch_total += $item['amount'];
                                        ?>
                                            <tr>
                                                <td><strong><?php echo $item['name']; ?></strong></td>
                                                <td><?php echo $item['class_info']; ?></td>
                                                <td><?php echo $item['month']; ?></td>
                                                <td><?php echo format_currency($item['amount']); ?></td>
                                                <td class="text-center">
                                                    <button type="submit" name="action" value="remove_item" 
                                                            onclick="document.getElementById('remove_record_id').value='<?php echo $item['record_id']; ?>'; return confirm('Remove <?php echo htmlspecialchars($item['name']); ?> (<?php echo $item['month']; ?>) from batch list?');" 
                                                            class="btn btn-sm btn-outline-danger py-1 px-2" title="Remove Item">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <input type="hidden" name="remove_record_id" id="remove_record_id" value="">
                                        
                                        <tr class="table-warning">
                                            <td><strong class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> Fine / Late Fee</strong></td>
                                            <td>-</td>
                                            <td><span class="badge bg-danger">Fine</span></td>
                                            <td>
                                                <div class="input-group input-group-sm" style="max-width: 170px;">
                                                    <span class="input-group-text fw-bold">Rs.</span>
                                                    <input type="number" name="fine_amount" id="fine_amount_input" class="form-control fw-bold text-danger" 
                                                           min="0" step="0.01" value="<?php echo (isset($_SESSION['fine_amount']) && $_SESSION['fine_amount'] > 0) ? floatval($_SESSION['fine_amount']) : ''; ?>" placeholder="0.00">
                                                </div>
                                            </td>
                                            <td></td>
                                        </tr>

                                        <tr class="fw-bold table-light">
                                            <td colspan="3" class="text-end">Batch Total:</td>
                                            <td id="batch_total_display"><?php echo format_currency($batch_total + floatval($_SESSION['fine_amount'] ?? 0)); ?></td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                                
                                <div class="d-flex flex-wrap gap-4 align-items-end justify-content-between bg-light p-3 rounded border mt-3">
                                    <div class="form-group mb-0" style="min-width: 250px;">
                                        <label for="payment_mode" class="form-label fw-bold text-success mb-2">
                                            <i class="fas fa-wallet"></i> Choose Payment Method:
                                        </label>
                                        <select id="payment_mode" name="payment_mode" class="form-select bg-white" required>
                                            <option value="cash" selected>💵 Cash Payment</option>
                                            <option value="bank_transfer">🏦 Bank / Account Transfer</option>
                                        </select>
                                    </div>
                                    
                                    <div class="d-flex gap-3">
                                        <a href="<?php echo $self_url; ?>" class="btn btn-outline-primary d-flex align-items-center">
                                            <i class="fas fa-user-plus me-1"></i> Search & Add Another
                                        </a>
                                        <button type="submit" name="action" value="process_batch" class="btn btn-success px-4 d-flex align-items-center">
                                            <i class="fas fa-print me-1"></i> Print Receipt
                                        </button>
                                        <button type="submit" name="action" value="clear_cart" class="btn btn-outline-danger d-flex align-items-center" onclick="return confirm('Clear batch list?')">
                                            <i class="fas fa-trash me-1"></i> Clear Batch
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($student === null): ?>
                        <div class="search-section">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4>Step 1: Search Student</h4>
                                <a href="<?php echo $self_url; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-sync"></i> Reset Search</a>
                            </div>
                            <form method="POST" class="search-form" action="<?php echo $self_url; ?>">
                                <input type="hidden" name="action" value="search">
                                <div class="search-grid">
                                    <div class="form-group">
                                        <label for="search_name">Student Name</label>
                                        <input type="text" id="search_name" name="search_name" class="form-control" 
                                               placeholder="Enter Student Name ">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="search_class">Class</label>
                                        <select id="search_class" name="search_class" class="form-control">
                                            <option value="">All Classes</option>
                                            <?php foreach ($CLASSES as $cls): ?>
                                                <option value="<?php echo $cls; ?>"><?php echo $cls; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="search_section">Section</label>
                                        <select id="search_section" name="search_section" class="form-control">
                                            <option value="">All Sections</option>
                                            <?php foreach ($SECTIONS as $sec): ?>
                                                <option value="<?php echo $sec; ?>"><?php echo $sec; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <button type="submit" class="btn-primary" style="margin-top: 30px;">
                                            <i class="fas fa-search"></i> Search
                                        </button>
                                    </div>
                                </div>
                            </form>
                            
                            <?php if (count($search_results) > 0): ?>
                                <div class="search-results">
                                    <h5>Search Results</h5>
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Father Name</th>
                                                <th>Class</th>
                                                <th>Section</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($search_results as $res):
                                                $unpaid = get_total_unpaid_fees($res['id']);
                                            ?>
                                                <tr>
                                                    <td><strong><?php echo $res['id']; ?></strong></td>
                                                    <td><?php echo $res['name']; ?></td>
                                                    <td><?php echo $res['father_name']; ?></td>
                                                    <td><?php echo $res['class']; ?></td>
                                                    <td><?php echo $res['section']; ?></td>
                                                    
                                                    <td>
                                                        <a href="?id=<?php echo $res['id']; ?>" class="btn-action">
                                                            <i class="fas fa-plus-circle"></i> Select Fees
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif (isset($_POST['action']) && $_POST['action'] == 'search'): ?>
                                <div class="alert alert-info">No students found!</div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="fee-details">
                            <div class="fee-header">
                                <div>
                                    <h4><?php echo $student['name']; ?> (<?php echo $student['class']; ?>-<?php echo $student['section']; ?>)</h4>
                                    <p>Father: <?php echo $student['father_name']; ?> | Contact: <?php echo $student['contact_number']; ?></p>
                                </div>
                                <div class="fee-summary">
                                    <?php 
                                    $statement_target_url = ($panel_role === 'master') ? 'student_statement.php' : '../master/student_statement.php';
                                    $is_student_package = (is_college_class($student['class']) || !empty($student['is_package']));
                                    if ($is_student_package): 
                                    ?>
                                        <a href="<?php echo $statement_target_url; ?>?id=<?php echo $student['id']; ?>" target="_blank" class="btn btn-outline-primary">
                                            <i class="fas fa-print"></i> Print Statement
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#statementModal">
                                            <i class="fas fa-print"></i> Print Statement
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <form method="POST" id="multiPaymentForm" action="<?php echo $self_url; ?>?id=<?php echo $student['id']; ?>">
                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Balance Due</th>
                                        <th>Status</th>
                                        <th>Payment Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $unpaid_idx = 0;
                                    foreach ($student_fees as $fee): 
                                    ?>
                                        <tr>
                                            <td><?php echo $fee['month']; ?></td>
                                            <td><?php echo format_currency($fee['amount']); ?></td>
                                            <td>
                                                <?php if ($fee['status'] == 'paid'): ?>
                                                    <span class="badge bg-success">Paid</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Unpaid</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo format_datetime($fee['payment_date']); ?></td>
                                            <td>
                                                <?php if ($fee['status'] == 'unpaid'): ?>
                                                    <div class="d-flex gap-2 align-items-center flex-wrap">
                                                        <input type="checkbox" name="selected_fee_records[]" value="<?php echo $fee['id']; ?>" 
                                                               class="form-check-input fee-checkbox"
                                                               data-index="<?php echo $unpaid_idx; ?>"
                                                               <?php echo ($unpaid_idx > 0) ? 'disabled' : ''; ?>>
                                                        
                                                        <div class="input-group input-group-sm" style="width: 140px;">
                                                            <span class="input-group-text">Rs.</span>
                                                            <input type="number" name="paid_amount[<?php echo $fee['id']; ?>]" 
                                                                   class="form-control paid-amount-input" 
                                                                   data-fee-id="<?php echo $fee['id']; ?>"
                                                                   data-original-val="<?php echo $fee['amount']; ?>"
                                                                   value="<?php echo $fee['amount']; ?>" 
                                                                   step="0.01" min="0" max="<?php echo $fee['amount']; ?>" 
                                                                   disabled>
                                                        </div>
                                                        <span id="remaining_<?php echo $fee['id']; ?>" class="font-monospace text-warning small fw-bold"></span>
                                                    </div>
                                                    <?php $unpaid_idx++; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (!empty($student_fees)): ?>
                                <div class="form-actions mt-3 d-flex justify-content-between">
                                    <a href="<?php echo $self_url; ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left"></i> Back to Search
                                    </a>
                                    <button type="submit" name="action" value="add_to_cart" class="btn btn-primary btn-lg" id="addBatchBtn" disabled>
                                        <i class="fas fa-plus"></i> Add to Receipt
                                    </button>
                                </div>
                            <?php endif; ?>
                            </form>
                        </div>

                        <!-- Print Statement Modal -->
                        <div class="modal fade" id="statementModal" tabindex="-1" aria-labelledby="statementModalLabel" aria-hidden="true">
                          <div class="modal-dialog">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="statementModalLabel" style="color: #1f5f46; font-weight: bold;"><i class="fas fa-print"></i> Generate Student Statement</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                              </div>
                              <div class="modal-body text-start">
                                <form id="statementForm" action="<?php echo $statement_target_url; ?>" method="GET" target="_blank" onsubmit="setTimeout(function() { const modalEl = document.getElementById('statementModal'); if (modalEl) { const modalInstance = bootstrap.Modal.getInstance(modalEl); if (modalInstance) { modalInstance.hide(); } } }, 100);">
                                  <input type="hidden" name="id" value="<?php echo $student['id']; ?>">
                                  
                                  <div class="mb-3">
                                    <label for="from_month" class="form-label" style="font-weight: 600; font-size: 13px;">From Month</label>
                                    <select class="form-select" id="from_month" name="from_month" required>
                                      <?php foreach ($all_fees as $fee): ?>
                                        <?php if ($fee['month'] === 'Admission') continue; ?>
                                        <option value="<?php echo $fee['month']; ?>"><?php echo $fee['month']; ?></option>
                                      <?php endforeach; ?>
                                    </select>
                                  </div>
                                  
                                  <div class="mb-3">
                                    <label for="to_month" class="form-label" style="font-weight: 600; font-size: 13px;">To Month</label>
                                    <select class="form-select" id="to_month" name="to_month" required>
                                      <?php 
                                      $last_idx = count($all_fees) - 1;
                                      foreach ($all_fees as $idx => $fee): 
                                        if ($fee['month'] === 'Admission') continue;
                                      ?>
                                        <option value="<?php echo $fee['month']; ?>" <?php echo ($idx === $last_idx) ? 'selected' : ''; ?>><?php echo $fee['month']; ?></option>
                                      <?php endforeach; ?>
                                    </select>
                                  </div>
                                  
                                  <div class="modal-footer px-0 pb-0">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-print"></i> Print Statement</button>
                                  </div>
                                </form>
                              </div>
                            </div>
                          </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['print_receipt_url'])): ?>
                const receiptUrl = '<?php echo $_SESSION['print_receipt_url']; ?>';
                window.open(receiptUrl, '_blank');
                <?php unset($_SESSION['print_receipt_url']); ?>
            <?php endif; ?>

            const checkboxes = document.querySelectorAll('.fee-checkbox');
            const inputs = document.querySelectorAll('.paid-amount-input');
            const addBatchBtn = document.getElementById('addBatchBtn');

            checkboxes.forEach((cb, index) => {
                cb.addEventListener('change', function() {
                    const feeId = this.value;
                    const input = document.querySelector(`.paid-amount-input[data-fee-id="${feeId}"]`);
                    
                    if (this.checked) {
                        if (input) {
                            input.disabled = false;
                            input.dispatchEvent(new Event('input'));
                        }
                        if (checkboxes[index + 1]) {
                            checkboxes[index + 1].disabled = false;
                        }
                    } else {
                        for (let i = index; i < checkboxes.length; i++) {
                            checkboxes[i].checked = false;
                            if (i > index) {
                                checkboxes[i].disabled = true;
                            }
                            
                            const subFeeId = checkboxes[i].value;
                            const subInput = document.querySelector(`.paid-amount-input[data-fee-id="${subFeeId}"]`);
                            if (subInput) {
                                subInput.value = subInput.getAttribute('data-original-val');
                                subInput.disabled = true;
                            }
                            
                            const remainingSpan = document.getElementById('remaining_' + subFeeId);
                            if (remainingSpan) {
                                remainingSpan.textContent = '';
                            }
                        }
                    }
                    
                    const anyChecked = Array.from(checkboxes).some(c => c.checked);
                    if (addBatchBtn) {
                        addBatchBtn.disabled = !anyChecked;
                    }
                });
            });

            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    const maxVal = parseFloat(this.getAttribute('max'));
                    const feeId = this.getAttribute('data-fee-id');
                    const remainingSpan = document.getElementById('remaining_' + feeId);
                    
                    let val = parseFloat(this.value);
                    if (isNaN(val) || val < 0) {
                        val = 0;
                    }
                    if (val > maxVal) {
                        val = maxVal;
                        this.value = maxVal;
                    }

                    const remaining = maxVal - val;
                    if (remainingSpan) {
                        if (remaining > 0 && val > 0) {
                            remainingSpan.innerHTML = '<span style="color: #f50f0f; font-size: 25px; font-weight: bold;"> Remaining: Rs. ' + remaining.toFixed(0) + '</span>';
                        } else if (remaining === 0) {
                            remainingSpan.textContent = '';
                        } else if (val < 0) {
                            remainingSpan.innerHTML = '<span style="color: #f50f0f; font-size: 25px; font-weight: bold;"> Cannot be negative</span>';
                        } else {
                            remainingSpan.textContent = '';
                        }
                    }
                });
            });

            const fineInput = document.getElementById('fine_amount_input');
            const batchTotalDisplay = document.getElementById('batch_total_display');
            if (fineInput && batchTotalDisplay) {
                const baseTotal = <?php echo floatval($batch_total ?? 0); ?>;
                fineInput.addEventListener('input', function() {
                    let fineVal = parseFloat(this.value);
                    if (isNaN(fineVal) || fineVal < 0) fineVal = 0;
                    const grandTotal = baseTotal + fineVal;
                    batchTotalDisplay.textContent = 'Rs. ' + grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                });
            }
        });
    </script>
</body>
</html>