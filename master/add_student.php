<?php
/**
 * Add Student
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master();

$CLASSES = $CLASSES ?? ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
$SECTIONS = $SECTIONS ?? ['A', 'B', 'C', 'D', 'E'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize_input($_POST['name'] ?? '');
    $father_name = sanitize_input($_POST['father_name'] ?? '');
    $class = sanitize_input($_POST['class'] ?? '');
    $section = sanitize_input($_POST['section'] ?? '');
    $monthly_fee = floatval($_POST['monthly_fee'] ?? 0);
    $description = sanitize_input($_POST['description'] ?? '');
    $contact_number = sanitize_input($_POST['contact_number'] ?? '');
    
    // Validation
    if (empty($name) || empty($father_name) || empty($class) || empty($section) || $monthly_fee <= 0) {
        $error = 'All required fields must be filled correctly!';
    } else {
        // Insert student
        $query = "INSERT INTO students (name, father_name, class, section, monthly_fee, description, contact_number, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssssdss', $name, $father_name, $class, $section, $monthly_fee, $description, $contact_number);
        
        if ($stmt->execute()) {
            $student_id = $conn->insert_id;
            
            // Create annual fee records
            create_annual_fees($student_id, $monthly_fee);
            
            $success = 'Student added successfully! Annual fees created.';
        } else {
            $error = 'Error adding student: ' . $stmt->error;
        }
        
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student - <?php echo SITE_NAME; ?></title>
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
                        <h2>Add New Student</h2>
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
                        <a href="add_student.php" class="module-nav-btn active">
                            <i class="fas fa-user-plus"></i> Add Student
                        </a>
                        <a href="edit_student.php" class="module-nav-btn">
                            <i class="fas fa-user-edit"></i> Edit Student
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
                        <a href="promotion.php" class="module-nav-btn">
                            <i class="fas fa-arrow-up"></i> Promotion
                        </a>
                        <a href="drop_student.php" class="module-nav-btn">
                            <i class="fas fa-trash"></i> Drop Student
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
                    
                    <form method="POST" class="student-form" id="addStudentForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Student Name *</label>
                                <input type="text" id="name" name="name" class="form-control" 
                                       required placeholder="Enter student name">
                            </div>
                            
                            <div class="form-group">
                                <label for="father_name">Father's Name *</label>
                                <input type="text" id="father_name" name="father_name" class="form-control" 
                                       required placeholder="Enter father's name">
                            </div>
                            
                            <div class="form-group">
                                <label for="class">Class *</label>
                                <select id="class" name="class" class="form-control" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($CLASSES as $cls): ?>
                                        <option value="<?php echo $cls; ?>"><?php echo $cls; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="section">Section *</label>
                                <select id="section" name="section" class="form-control" required>
                                    <option value="">Select Section</option>
                                    <?php foreach ($SECTIONS as $sec): ?>
                                        <option value="<?php echo $sec; ?>"><?php echo $sec; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="monthly_fee">Monthly Fee *</label>
                                <input type="number" id="monthly_fee" name="monthly_fee" class="form-control" 
                                       required placeholder="Enter monthly fee" step="0.01" min="0">
                            </div>
                            
                            <div class="form-group">
                                <label for="contact_number">Contact Number</label>
                                <input type="tel" id="contact_number" name="contact_number" class="form-control" 
                                       placeholder="Enter contact number">
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" class="form-control" 
                                         rows="3" placeholder="Enter any additional description"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Add Student
                            </button>
                            <a href="dashboard.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        document.getElementById('addStudentForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const fatherName = document.getElementById('father_name').value.trim();
            const monthlyFee = parseFloat(document.getElementById('monthly_fee').value);
            
            if (!name || !fatherName || monthlyFee <= 0) {
                e.preventDefault();
                alert('Please fill all required fields correctly!');
            }
        });
    </script>
</body>
</html>
