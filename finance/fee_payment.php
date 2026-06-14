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

$CLASSES = $CLASSES ?? ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
$SECTIONS = $SECTIONS ?? ['A', 'B', 'C', 'D', 'E'];

$error = '';
$success = '';
$student_fees = [];
$student = null;
$search_results = [];

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
    } elseif (isset($_POST['action']) && $_POST['action'] == 'mark_paid') {
        // Mark fee as paid
        $fee_record_id = intval($_POST['fee_record_id'] ?? 0);
        $student_id = intval($_POST['student_id'] ?? 0);
        
        // Get fee record
        $query = "SELECT * FROM fee_records WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $fee_record_id);
        $stmt->execute();
        $fee_record = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($fee_record) {
            $payment_id = record_payment($student_id, $fee_record['amount'], $fee_record['month'], get_username());
            
            if ($payment_id) {
                $success = 'Payment recorded successfully! Payment ID: ' . $payment_id;
                // Reload student fees
                $student = get_student($student_id);
            } else {
                $error = 'Error recording payment!';
            }
        } else {
            $error = 'Fee record not found!';
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
                <div class="topbar-left">
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
                        <a href="fee_payment.php" class="module-nav-btn active">
                            <i class="fas fa-money-bill-wave"></i> Fee Payment
                        </a>
                        <a href="defaulter_list.php" class="module-nav-btn">
                            <i class="fas fa-list"></i> Defaulters
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
                            <h4>Search Student to Record Payment</h4>
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
                                                <th>Monthly Fee</th>
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
                                                    <td><?php echo format_currency($res['monthly_fee']); ?></td>
                                                    <td><strong><?php echo format_currency($unpaid); ?></strong></td>
                                                    <td>
                                                        <a href="?id=<?php echo $res['id']; ?>" class="btn-action">
                                                            <i class="fas fa-file-invoice"></i> View Fees
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
                        <!-- Fee Details Section -->
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
                            
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Payment Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($student_fees as $fee): ?>
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
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="mark_paid">
                                                        <input type="hidden" name="fee_record_id" value="<?php echo $fee['id']; ?>">
                                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                        <button type="submit" class="btn-action" onclick="return confirm('Record payment for this month?')">
                                                            <i class="fas fa-check"></i> Record Payment
                                                        </button>
                                                    </form>
                                                    <a href="../master/receipt.php?fee_id=<?php echo $fee['id']; ?>" class="btn-action" target="_blank">
                                                        <i class="fas fa-file-pdf"></i> Receipt
                                                    </a>
                                                <?php else: ?>
                                                    <a href="../master/receipt.php?fee_id=<?php echo $fee['id']; ?>" class="btn-action" target="_blank">
                                                        <i class="fas fa-file-pdf"></i> Receipt
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <div class="form-actions">
                                <a href="fee_payment.php" class="btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Search
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
