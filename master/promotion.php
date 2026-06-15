<?php
/**
 * Student Promotion
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $from_class = sanitize_input($_POST['from_class'] ?? '');
    $from_section = sanitize_input($_POST['from_section'] ?? '');
    $to_class = sanitize_input($_POST['to_class'] ?? '');
    $to_section = sanitize_input($_POST['to_section'] ?? '');
    
    if (!empty($from_class) && !empty($from_section) && !empty($to_class) && !empty($to_section)) {
        // Get students to promote
        $query = "SELECT COUNT(*) as count FROM students WHERE class = ? AND section = ? AND status = 'active'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $from_class, $from_section);
        $stmt->execute();
        $count_result = $stmt->get_result()->fetch_assoc();
        $student_count = $count_result['count'];
        $stmt->close();
        
        if ($student_count > 0) {
            // Promote students
            $query = "UPDATE students SET class = ?, section = ? WHERE class = ? AND section = ? AND status = 'active'";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssss', $to_class, $to_section, $from_class, $from_section);
            
            if ($stmt->execute()) {
                $success = $student_count . ' student(s) promoted from ' . $from_class . '-' . $from_section . ' to ' . $to_class . '-' . $to_section . ' successfully!';
            } else {
                $error = 'Error promoting students: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = 'No active students found in ' . $from_class . '-' . $from_section;
        }
    } else {
        $error = 'All fields are required!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotion - <?php echo SITE_NAME; ?></title>
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
                        <h2>Student Promotion</h2>
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
            
            <!-- Dashboard Content -->
            <div class="content">
                <div class="module-nav-panel">
                    <div class="module-nav-row">
                        <a href="dashboard.php" class="module-nav-btn">
                            <i class="fas fa-chart-bar"></i> Dashboard
                        </a>
                        <a href="add_student.php" class="module-nav-btn">
                            <i class="fas fa-user-plus"></i> Add Student
                        </a>
                        <a href="edit_student.php" class="module-nav-btn">
                            <i class="fas fa-user-edit"></i> Edit Student
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
                        <a href="promotion.php" class="module-nav-btn active">
                            <i class="fas fa-arrow-up"></i> Promotion
                        </a>
                        <a href="drop_student.php" class="module-nav-btn">
                            <i class="fas fa-trash"></i> Drop Student
                        </a>
                    </div>
                </div>

                <div class="form-section">
                    <div class="promotion-card">
                        <div class="card-header">
                            <i class="fas fa-arrow-up"></i> Promote Class
                        </div>
                        
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
                        
                        <form method="POST" id="promotionForm">
                            <div class="promotion-form">
                                <div class="promotion-column">
                                    <h5>FROM</h5>
                                    <div class="form-group">
                                        <label for="from_class">Class</label>
                                        <select id="from_class" name="from_class" class="form-control" required>
                                            <option value="">Select Class</option>
                                            <?php foreach ($CLASSES as $cls): ?>
                                                <option value="<?php echo $cls; ?>"><?php echo $cls; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="from_section">Section</label>
                                        <select id="from_section" name="from_section" class="form-control" required>
                                            <option value="">Select Section</option>
                                            <?php foreach ($SECTIONS as $sec): ?>
                                                <option value="<?php echo $sec; ?>"><?php echo $sec; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="promotion-arrow">
                                    <i class="fas fa-arrow-right"></i>
                                </div>
                                
                                <div class="promotion-column">
                                    <h5>TO</h5>
                                    <div class="form-group">
                                        <label for="to_class">Class</label>
                                        <select id="to_class" name="to_class" class="form-control" required>
                                            <option value="">Select Class</option>
                                            <?php foreach ($CLASSES as $cls): ?>
                                                <option value="<?php echo $cls; ?>"><?php echo $cls; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="to_section">Section</label>
                                        <select id="to_section" name="to_section" class="form-control" required>
                                            <option value="">Select Section</option>
                                            <?php foreach ($SECTIONS as $sec): ?>
                                                <option value="<?php echo $sec; ?>"><?php echo $sec; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-primary btn-lg" onclick="return confirm('Are you sure you want to promote this class? This action cannot be undone.')">
                                <i class="fas fa-check"></i> Promote Class
                            </button>
                        </form>
                    </div>
                    
                    <div class="info-box">
                        <h5><i class="fas fa-info-circle"></i> How Promotion Works</h5>
                        <ul>
                            <li>Select the current class and section</li>
                            <li>Select the target class and section</li>
                            <li>Click "Promote Class" to move all active students</li>
                            <li>All students in the selected class and section will be promoted</li>
                            <li>This action cannot be undone - please be careful!</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <style>
        .promotion-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }
        
        .card-header {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 25px;
            color: #1f5f46;
        }
        
        .promotion-form {
            display: flex;
            align-items: flex-end;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .promotion-column {
            flex: 1;
        }
        
        .promotion-arrow {
            font-size: 28px;
            color: #1f5f46;
            margin-bottom: 10px;
        }
        
        .info-box {
            background: #f5f7ff;
            border-left: 4px solid #1f5f46;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
        }
        
        .info-box h5 {
            color: #1f5f46;
            margin-bottom: 15px;
        }
        
        .info-box ul {
            list-style: none;
            padding-left: 0;
        }
        
        .info-box li {
            margin-bottom: 8px;
            color: #666;
            padding-left: 20px;
            position: relative;
        }
        
        .info-box li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #1f5f46;
            font-weight: bold;
        }
        
        .btn-lg {
            padding: 12px 30px;
            font-size: 16px;
        }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
