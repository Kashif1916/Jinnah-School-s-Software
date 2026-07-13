<?php
/**
 * Receipt Note Settings
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master(); // Enforces Master permission

$success = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_receipt_note'])) {
    $receipt_note = $_POST['receipt_note'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('receipt_note', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param('ss', $receipt_note, $receipt_note);
    if ($stmt->execute()) {
        $success = "Receipt Note updated successfully!";
    } else {
        $error = "Failed to update Receipt Note: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch current Receipt Note
$receipt_note = '';
$res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'receipt_note'");
if ($res && $row = $res->fetch_assoc()) {
    $receipt_note = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt Note Settings - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <!-- Top Bar -->
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <a href="dashboard.php"><?php echo render_system_logo('topbar-logo'); ?></a>
                    <div class="panel-brand">
                        <h2>Receipt Note</h2>
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
                <!-- Navigation Menu -->
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
                        <a href="fee_schedule.php" class="module-nav-btn">
                            <i class="fas fa-calendar-alt"></i> Fee Schedule
                        </a>
                        <a href="fee_management.php" class="module-nav-btn">
                            <i class="fas fa-money-bill-wave"></i> Fee Management
                        </a>
                        <a href="defaulter_list.php" class="module-nav-btn">
                            <i class="fas fa-list"></i> Defaulters
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
                        <a href="receipt_note.php" class="module-nav-btn active">
                            <i class="fas fa-sticky-note"></i> Receipt Note
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

                    <div class="card p-4 border rounded shadow-sm bg-white">
                        <h4 class="text-success mb-3" style="color: #1f5f46 !important;"><i class="fas fa-sticky-note"></i> Receipt Note Settings</h4>
                        <p class="text-muted">Enter a custom note in English or Urdu. This note will appear at the bottom of every printed receipt.</p>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="receipt_note" class="form-label fw-bold">Note Content</label>
                                <textarea id="receipt_note" name="receipt_note" class="form-control" rows="5" placeholder="e.g. فیس جمع کروانے کی آخری تاریخ ہر ماہ کی 10 ہے۔" style="font-size: 16px; border: 1px solid #ced4da;"><?php echo htmlspecialchars($receipt_note); ?></textarea>
                            </div>
                            <button type="submit" name="update_receipt_note" class="btn btn-primary" style="background: linear-gradient(135deg, #1f5f46 0%, #10161b 100%); border: none; padding: 10px 25px;">
                                <i class="fas fa-save"></i> Save Receipt Note
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
