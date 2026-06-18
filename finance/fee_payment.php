<?php
/**
 * Fee Payment - Finance Module
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_finance();

$error = '';
$success = '';
$student_fees = [];
$student = null;
$search_results = [];

// Initialize Session Cart for Batch Payments if not exists
if (!isset($_SESSION['fee_cart'])) {
    $_SESSION['fee_cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'search') {
        // Search students with multiple filters
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
    } elseif (isset($_POST['action']) && $_POST['action'] == 'add_to_cart') {
        // Add selected months to the temporary batch list
        $selected_records = $_POST['selected_fee_records'] ?? [];
        $amounts = $_POST['paid_amount'] ?? [];
        
        foreach ($selected_records as $rec_id) {
            $rec_id = intval($rec_id);
            $paid_amt = floatval($amounts[$rec_id] ?? 0);
            
            // Check if this record is already in the batch to avoid duplicates
            $exists = false;
            foreach ($_SESSION['fee_cart'] as $item) {
                if ($item['record_id'] == $rec_id) { $exists = true; break; }
            }

            if (!$exists && $paid_amt > 0) {
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
    } elseif (isset($_POST['action']) && $_POST['action'] == 'clear_cart') {
        $_SESSION['fee_cart'] = [];
        $success = "Batch list cleared.";
    } elseif (isset($_POST['action']) && $_POST['action'] == 'process_batch') {
        // Process all payments in the cart and generate one receipt
        $generated_payment_ids = [];
        
        if (!empty($_SESSION['fee_cart'])) {
            foreach ($_SESSION['fee_cart'] as $item) {
                $p_id = record_payment($item['student_id'], $item['amount'], $item['month'], get_username());
                if ($p_id) {
                    $generated_payment_ids[] = $p_id;
                } else {
                    $error .= "Error recording fee for " . $item['name'] . " (" . $item['month'] . ") ";
                }
            }

            if (!empty($generated_payment_ids)) {
                $_SESSION['fee_cart'] = []; // Clear cart after success
                $_SESSION['print_receipt_url'] = '../master/receipt.php?payment_ids=' . implode(',', $generated_payment_ids);
                header('Location: fee_payment.php'); // Redirect to clear the search/student screen
                exit();
            }
        } else {
            $error = "Batch list is empty!";
        }
    }
}

// If student ID is in URL, load that student's fees
if (isset($_GET['id'])) {
    $student_id = intval($_GET['id']);
    $student = get_student($student_id);

    if ($student) {
        $query = "SELECT * FROM fee_records WHERE student_id = ? ORDER BY STR_TO_DATE(CONCAT('01-', month), '%d-%b-%Y')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $student_fees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
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
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <?php echo render_system_logo('topbar-logo'); ?>
                    <div class="panel-brand">
                        <h2>Record Fee Payment</h2>
                        <span>Finance / Clerk Panel</span>
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
            
            <!-- Dashboard Content -->
            <div class="content">
                <div class="module-nav-panel">
                    <div class="module-nav-row">
                        <a href="dashboard.php" class="module-nav-btn">
                            <i class="fas fa-chart-bar"></i> Dashboard
                        </a>
                        <a href="student_record.php" class="module-nav-btn ">
                            <i class="fas fa-address-book"></i> Student Record
                        </a>
                        <a href="fee_payment.php" class="module-nav-btn active">
                            <i class="fas fa-money-bill-wave"></i> Fee Payment
                        </a>
                        
                        <a href="defaulter_list.php" class="module-nav-btn">
                            <i class="fas fa-list"></i> Pending List
                        </a>
                        <a href="payment_analytics.php" class="module-nav-btn">
                            <i class="fas fa-chart-line"></i> Analytics
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
                    
                    <!-- Search Section -->
                    <?php if ($student === null): ?>
                        <div class="search-section">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4>Step 1: Search Student</h4>
                                <a href="fee_payment.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-sync"></i> Reset Search</a>
                            </div>
                            <form method="POST" class="search-form">
                                <input type="hidden" name="action" value="search">
                                <div class="search-grid">
                                    <div class="form-group">
                                        <label for="search_name">Student Name</label>
                                        <input type="text" id="search_name" name="search_name" class="form-control" 
                                               placeholder="Enter student name (optional)">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="search_class">Class</label>
                                        <select id="search_class" name="search_class" class="form-control">
                                            <option value="">-- Select Class (optional) --</option>
                                            <?php foreach ($CLASSES as $cls): ?>
                                                <option value="<?php echo $cls; ?>"><?php echo $cls; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="search_section">Section</label>
                                        <select id="search_section" name="search_section" class="form-control">
                                            <option value="">-- Select Section (optional) --</option>
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
                                                <th>Name</th>
                                                <th>Class</th>
                                                <th>Section</th>
                                                <th>Unpaid Amount</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($search_results as $res):
                                                $unpaid = get_total_unpaid_fees($res['id']);
                                            ?>
                                                <tr>
                                                    <td><?php echo $res['name']; ?></td>
                                                    <td><?php echo $res['class']; ?></td>
                                                    <td><?php echo $res['section']; ?></td>
                                                    <td><strong><?php echo format_currency($unpaid); ?></strong></td>
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
                        <!-- Step 2: Fee Selection for found student -->
                        <div class="fee-details">
                            <div class="fee-header">
                                <div>
                                    <h4><?php echo $student['name']; ?> (<?php echo $student['class']; ?>-<?php echo $student['section']; ?>)</h4>
                                    <p>Father: <?php echo $student['father_name']; ?> | Contact: <?php echo $student['contact_number']; ?></p>
                                </div>
                                <div class="fee-summary">
                                    <div class="summary-item">
                                        <span>Monthly Fee:</span>
                                        <strong><?php echo format_currency($student['monthly_fee']); ?></strong>
                                    </div>
                                    <div class="summary-item">
                                        <span>Total Unpaid:</span>
                                        <strong style="color: #e74c3c;"><?php echo format_currency(get_total_unpaid_fees($student['id'])); ?></strong>
                                    </div>
                                    <div class="summary-item">
                                        <span>Total Paid:</span>
                                        <strong style="color: #27ae60;"><?php echo format_currency(get_total_paid_fees($student['id'])); ?></strong>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="POST" id="multiPaymentForm">
                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">Pay</th>
                                        <th>Month</th>
                                        <th>Balance Due</th>
                                        <th>Status</th>
                                        <th>Pay Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($student_fees as $fee): ?>
                                        <tr>
                                            <td>
                                                <?php if ($fee['status'] == 'unpaid'): ?>
                                                    <input type="checkbox" name="selected_fee_records[]" value="<?php echo $fee['id']; ?>" class="form-check-input fee-checkbox">
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $fee['month']; ?></td>
                                            <td><?php echo format_currency($fee['amount']); ?></td>
                                            <td>
                                                <?php if ($fee['status'] == 'paid'): ?>
                                                    <span class="badge bg-success">Paid</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Unpaid</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($fee['status'] == 'unpaid'): ?>                                                    
                                                    <div class="d-flex gap-2 align-items-center">
                                                        <input type="checkbox" name="selected_fee_records[]" value="<?php echo $fee['id']; ?>" class="form-check-input fee-checkbox">
                                                        <input type="number" name="paid_amount[<?php echo $fee['id']; ?>]" class="form-control form-control-sm paid-amount-input" 
                                                               value="<?php echo $fee['amount']; ?>" step="0.01" min="0.01" max="<?php echo $fee['amount']; ?>" style="width: 100px;" disabled>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (!empty($student_fees)): ?>
                                <div class="form-actions mt-3">
                                    <button type="submit" name="action" value="add_to_cart" class="btn-primary" id="addBatchBtn" disabled>
                                        <i class="fas fa-plus"></i> Add to Receipt
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>
                        </div>
                    <?php endif; ?>

                    <!-- Step 3: Global Batch Summary (Always visible if not empty) -->
                    <?php if (!empty($_SESSION['fee_cart'])): ?>
                        <div class="batch-summary mt-5 p-4 border rounded shadow-sm bg-white">
                            <h4 class="text-success mb-3"><i class="fas fa-receipt"></i> Current Receipt Batch List</h4>
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Class</th>
                                        <th>Month</th>
                                        <th>Amount to Pay</th>
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
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-dark">
                                        <td colspan="3" class="text-end"><strong>Grand Total:</strong></td>
                                        <td><strong><?php echo format_currency($batch_total); ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="d-flex gap-3 mt-3">
                                <a href="fee_payment.php" class="btn btn-outline-primary btn-lg">
                                    <i class="fas fa-user-plus"></i> Add New Payment
                                </a>
                                <form method="POST" class="d-inline">
                                    <button type="submit" name="action" value="process_batch" class="btn btn-success btn-lg">
                                        <i class="fas fa-print"></i> Process & Print Combined Receipt
                                    </button>
                                </form>
                                <form method="POST" class="d-inline">
                                    <button type="submit" name="action" value="clear_cart" class="btn btn-link text-danger">
                                        <i class="fas fa-trash"></i> Clear All
                                    </button>
                                </form>
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
            // Check if there is a receipt to be opened in a new tab
            <?php if (isset($_SESSION['print_receipt_url'])): ?>
                const receiptUrl = '<?php echo $_SESSION['print_receipt_url']; ?>';
                window.open(receiptUrl, '_blank');
                <?php unset($_SESSION['print_receipt_url']); // Clear it after opening ?>
            <?php endif; ?>

            const checkboxes = document.querySelectorAll('.fee-checkbox');
            const addBatchBtn = document.getElementById('addBatchBtn');

            checkboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    const input = this.closest('tr').querySelector('.paid-amount-input');
                    if(input) input.disabled = !this.checked;
                    
                    const anyChecked = Array.from(checkboxes).some(c => c.checked);
                    if(addBatchBtn) addBatchBtn.disabled = !anyChecked;
                });
            });
        });
    </script>
</body>
</html>
