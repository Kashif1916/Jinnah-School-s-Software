<?php
/**
 * Fee Schedule Management - Master Panel
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_fee') {
        $class = sanitize_input($_POST['class'] ?? '');
        $fixed_monthly_fee = floatval($_POST['fixed_monthly_fee'] ?? 0);

        if (empty($class)) {
            $error = 'Please select a class.';
        } elseif ($fixed_monthly_fee <= 0) {
            $error = 'Monthly fee must be greater than zero.';
        } else {
            $stmt = $conn->prepare("INSERT INTO fee_schedule (class, fixed_monthly_fee) VALUES (?, ?) ON DUPLICATE KEY UPDATE fixed_monthly_fee = VALUES(fixed_monthly_fee)");
            $stmt->bind_param("sd", $class, $fixed_monthly_fee);
            if ($stmt->execute()) {
                $success = 'Fee schedule for Class ' . htmlspecialchars($class) . ' saved successfully.';
            } else {
                $error = 'Failed to save fee schedule: ' . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Fetch current fee schedules
$schedules = [];
$res = $conn->query("SELECT * FROM fee_schedule ORDER BY FIELD(class, 'P.G', 'Nursury', 'Pre', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12')");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $schedules[$row['class']] = $row['fixed_monthly_fee'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Schedule - <?php echo SITE_NAME; ?></title>
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
                        <h2>Fee Schedule</h2>
                        <span>Principal Panel</span>
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
                        <a href="fee_schedule.php" class="module-nav-btn active">
                            <i class="fas fa-calendar-alt"></i> Fee Schedule
                        </a>
                        <a href="fee_management.php" class="module-nav-btn">
                            <i class="fas fa-money-bill-wave"></i> Fee Management
                        </a>
                        <a href="defaulter_list.php" class="module-nav-btn">
                            <i class="fas fa-list"></i> Pending List
                        </a>
                        <a href="payment_analytics.php" class="module-nav-btn">
                            <i class="fas fa-chart-line"></i> Analytics
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
                        <a href="users.php" class="module-nav-btn">
                            <i class="fas fa-users-cog"></i> Users
                        </a>
                        <a href="receipt_note.php" class="module-nav-btn">
                            <i class="fas fa-sticky-note"></i> Receipt Note
                        </a>
                        <a href="../help.php" class="module-nav-btn">
                            <i class="fas fa-question-circle text-success"></i> Help & About
                        </a>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- Form Panel -->
                    <div class="col-lg-4">
                        <div class="analytics-section">
                            <h4>Set Class Fee</h4>
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="action" value="save_fee">
                                
                                <div class="mb-3">
                                    <label for="class" class="form-label">Select Class <span class="text-danger">*</span></label>
                                    <select id="class" name="class" class="form-select" required>
                                        <option value="">Choose Class...</option>
                                        <?php foreach ($CLASSES as $cls): ?>
                                            <option value="<?php echo $cls; ?>"><?php echo $cls; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="fixed_monthly_fee" class="form-label">Fixed Monthly Fee (Rs.) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rs.</span>
                                        <input type="number" step="0.01" min="0.01" class="form-control" id="fixed_monthly_fee" name="fixed_monthly_fee" required placeholder="0.00">
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn-primary w-100 mt-2">
                                    <i class="fas fa-save"></i> Save Fee Schedule
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- List Panel -->
                    <div class="col-lg-8">
                        <div class="analytics-section">
                            <h4>Class Fee Schedule List</h4>
                            <div class="table-responsive mt-3">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Class</th>
                                            <th>Fixed Monthly Fee</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($CLASSES as $cls): 
                                            $fee = isset($schedules[$cls]) ? $schedules[$cls] : null;
                                        ?>
                                            <tr>
                                                <td><strong><?php echo $cls; ?></strong></td>
                                                <td>
                                                    <?php if ($fee !== null): ?>
                                                        <strong class="text-success"><?php echo format_currency($fee); ?></strong>
                                                    <?php else: ?>
                                                        <span class="text-muted italic">Not Configured</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($fee !== null): ?>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editFee('<?php echo htmlspecialchars($cls); ?>', <?php echo $fee; ?>)">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-success" onclick="editFee('<?php echo htmlspecialchars($cls); ?>', 0)">
                                                            <i class="fas fa-plus"></i> Configure
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        function editFee(className, feeAmount) {
            document.getElementById('class').value = className;
            document.getElementById('fixed_monthly_fee').value = feeAmount > 0 ? feeAmount : '';
            document.getElementById('fixed_monthly_fee').focus();
        }
    </script>
</body>
</html>
