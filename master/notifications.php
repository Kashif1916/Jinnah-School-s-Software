<?php
/**
 * Developer Notifications Management - Master Panel Integration
 * School Finance Management System
 * Only 'developer' role can access this page
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_login();

// STRICT: Only Developer role can access notifications management
if (!is_developer()) {
    header('Location: dashboard.php');
    exit();
}

$success = '';
$error   = '';

// Handle POST: add or delete notification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_notification') {
        $title   = trim($_POST['title']   ?? '');
        $message = trim($_POST['message'] ?? '');
        $type    = trim($_POST['type']    ?? 'info');
        $valid_types = ['info', 'warning', 'success', 'danger'];
        if (!in_array($type, $valid_types)) $type = 'info';

        if (empty($title) || empty($message)) {
            $error = 'Title and Message are required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO developer_notifications (title, message, type, created_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $title, $message, $type, $_SESSION['username']);
            if ($stmt->execute()) {
                $success = "Notification posted successfully! All users will see it until they click 'Got It'.";
            } else {
                $error = "Failed to post notification: " . $conn->error;
            }
            $stmt->close();
        }
    }

    elseif ($action === 'delete_notification') {
        $notif_id = intval($_POST['notif_id'] ?? 0);
        if ($notif_id > 0) {
            $conn->query("DELETE FROM notification_dismissals WHERE notification_id = $notif_id");
            $del = $conn->prepare("DELETE FROM developer_notifications WHERE id = ?");
            $del->bind_param("i", $notif_id);
            if ($del->execute()) {
                $success = "Notification deleted and dismissed for all users.";
            } else {
                $error = "Failed to delete: " . $conn->error;
            }
            $del->close();
        }
    }
}

// Fetch all notifications
$notifications = [];
$res = $conn->query("SELECT dn.*, (SELECT COUNT(*) FROM notification_dismissals nd WHERE nd.notification_id = dn.id) as dismiss_count FROM developer_notifications dn ORDER BY dn.created_at DESC");
if ($res) $notifications = $res->fetch_all(MYSQLI_ASSOC);

// Count total non-developer users
$total_users = intval($conn->query("SELECT COUNT(*) as c FROM users WHERE role != 'developer'")->fetch_assoc()['c'] ?? 0);

// Helper to cleanly display both old and new notification texts
function format_clean_notification($text) {
    $decoded = html_entity_decode((string)$text, ENT_QUOTES, 'UTF-8');
    $decoded = str_replace(['\r\n', '\n', '\r'], "\n", $decoded);
    return htmlspecialchars($decoded, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Notification - <?php echo SITE_NAME; ?></title>
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
                        <h2>Developer Notifications</h2>
                        <span>System Control Panel</span>
                    </div>
                </div>
                <div class="topbar-right">
                    <span class="user-info">
                        <i class="fas fa-user-circle"></i> <?php echo get_username(); ?> 
                        <small>(Developer)</small>
                    </span>
                    <a href="../logout.php" class="btn-secondary">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Full Master Navigation Bar -->
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
                        <a href="fee_schedule.php" class="module-nav-btn">
                            <i class="fas fa-calendar-alt"></i> Fee Schedule
                        </a>
                        <a href="fee_management.php" class="module-nav-btn">
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
                            <i class="fas fa-trash text-success"></i> Drop Student
                        </a>
                        <a href="delete_student.php" class="module-nav-btn">
                            <i class="fas fa-user-minus text-success"></i> Delete Student
                        </a>
                        <a href="users.php" class="module-nav-btn">
                            <i class="fas fa-users-cog"></i> Users
                        </a>
                        <a href="account_close_log.php" class="module-nav-btn">
                            <i class="fas fa-lock"></i> Close Logs
                        </a>
                        <a href="receipt_note.php" class="module-nav-btn">
                            <i class="fas fa-sticky-note"></i> Custom Note
                        </a>
                        <a href="notifications.php" class="module-nav-btn active" style="color: #f59e0b; border-color: #f59e0b;">
                            <i class="fas fa-bell"></i> Post Notification
                        </a>
                        <a href="../help.php" class="module-nav-btn">
                            <i class="fas fa-question-circle text-success"></i> Help &amp; About
                        </a>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-1"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- Post Notification Form -->
                    <div class="col-lg-4">
                        <div class="analytics-section">
                            <h4><i class="fas fa-plus-circle text-success me-2"></i>Post New Notification</h4>
                            <p class="text-muted small">Ye notification sabhi users (Master, Finance, Admission, Teacher) ko unki screen par popup hoga jab tak wo <strong>"Got It"</strong> par click na kar lein.</p>
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="action" value="add_notification">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Notification Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control" required placeholder="e.g. System Maintenance Notice">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Message Details <span class="text-danger">*</span></label>
                                    <textarea name="message" class="form-control" rows="5" required placeholder="Aapka paigham yahan likhein..."></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Notification Type / Alert Style</label>
                                    <select name="type" class="form-select">
                                        <option value="info">ℹ️ Information (Blue Alert)</option>
                                        <option value="warning">⚠️ Warning / Attention (Yellow Alert)</option>
                                        <option value="success">✅ Announcement / Success (Green Alert)</option>
                                        <option value="danger">🚨 Urgent / Important (Red Alert)</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn-primary w-100 py-2">
                                    <i class="fas fa-paper-plane me-1"></i> Post Notification to All Users
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Active Notifications List -->
                    <div class="col-lg-8">
                        <div class="analytics-section">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <h4><i class="fas fa-bell text-warning me-2"></i>Active Posted Notifications</h4>
                                <span class="badge bg-secondary px-3 py-2">Total System Users: <?php echo $total_users; ?></span>
                            </div>

                            <?php if (count($notifications) > 0): ?>
                                <?php foreach ($notifications as $n): ?>
                                    <div class="card mb-3 border-<?php echo htmlspecialchars($n['type']); ?> shadow-sm">
                                        <div class="card-header d-flex justify-content-between align-items-center bg-<?php echo htmlspecialchars($n['type']); ?> bg-opacity-10 py-2">
                                            <div>
                                                <span class="badge bg-<?php echo htmlspecialchars($n['type']); ?> me-2"><?php echo strtoupper($n['type']); ?></span>
                                                <strong style="font-size: 1.05rem;"><?php echo format_clean_notification($n['title']); ?></strong>
                                            </div>
                                            <form method="POST" onsubmit="return confirm('Kya aap is notification ko delete karna chahte hain? Sab users ko show hona band ho jayega.');" class="mb-0">
                                                <input type="hidden" name="action" value="delete_notification">
                                                <input type="hidden" name="notif_id" value="<?php echo $n['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Notification">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-3" style="white-space: pre-line;"><?php echo format_clean_notification($n['message']); ?></p>
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top">
                                                <small class="text-muted">
                                                    <i class="fas fa-user-circle me-1"></i> Posted by: <strong><?php echo htmlspecialchars($n['created_by']); ?></strong>
                                                    &nbsp;|&nbsp;
                                                    <i class="far fa-clock me-1"></i> <?php echo date('d-M-Y h:i A', strtotime($n['created_at'])); ?>
                                                </small>
                                                <span class="badge bg-primary">
                                                    <i class="fas fa-check-double me-1"></i>
                                                    <?php echo $n['dismiss_count']; ?> / <?php echo $total_users; ?> Got It Clicked
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info py-4 text-center">
                                    <i class="fas fa-info-circle fa-2x mb-2 d-block"></i>
                                    Abhi koi active notification nahi hai. Uper form se notification post karein taake sab users ko show ho.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>