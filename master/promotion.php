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

// Variables ko initialize karna taake HTML mai undefined error na aaye
$from_class_selected = '';
$from_section_selected = '';
$to_class_selected = '';
$to_section_selected = '';
$show_student_list = false;
$students_to_promote = [];

// फर्ज करें $CLASSES aur $SECTIONS arrays config.php mai hain, agar nahi hain to hum default set kar dete hain
if (!isset($CLASSES)) { $CLASSES = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10']; }
if (!isset($SECTIONS)) { $SECTIONS = ['A', 'B', 'C', 'D']; }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');

    // STEP 1: Jab user sirf students load karega
    if ($action == 'load_students') {
        $from_class_selected = sanitize_input($_POST['from_class'] ?? '');
        $from_section_selected = sanitize_input($_POST['from_section'] ?? '');

        if (!empty($from_class_selected) && !empty($from_section_selected)) {
            $query = "SELECT id, name, father_name, monthly_fee FROM students WHERE class = ? AND section = ? AND status = 'active'";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ss', $from_class_selected, $from_section_selected);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $students_to_promote[] = $row;
            }
            $stmt->close();
            $show_student_list = true;
        } else {
            $error = 'Please select both Class and Section to load students.';
        }
    }
    
    // STEP 2: Jab user students select karke promote button dabayega
    elseif ($action == 'promote_selected') {
        // Purani values retain rakhne ke liye taake form khali na ho
        $from_class_selected = sanitize_input($_POST['from_class'] ?? '');
        $from_section_selected = sanitize_input($_POST['from_section'] ?? '');
        
        $to_class = sanitize_input($_POST['to_class'] ?? '');
        $to_section = sanitize_input($_POST['to_section'] ?? '');
        $new_fee = sanitize_input($_POST['new_fixed_monthly_fee'] ?? '');
        $student_ids = $_POST['student_ids'] ?? [];

        if (!empty($to_class) && !empty($to_section) && $new_fee !== '' && !empty($student_ids)) {
            $conn->begin_transaction(); // Transaction start taake data secure rahe
            try {
                $promoted_count = 0;
                
                // Aik aik karke select kiye gaye student ko update karna
                $query = "UPDATE students SET class = ?, section = ?, fixed_monthly_fee = ?, concession_amount = 0 WHERE id = ? AND status = 'active'";
                $stmt = $conn->prepare($query);

                foreach ($student_ids as $id) {
                    $student_id = sanitize_input($id);
                    $stmt->bind_param('ssdi', $to_class, $to_section, $new_fee, $student_id);
                    $stmt->execute();
                    if ($stmt->affected_rows > 0) {
                        $promoted_count++;
                    }
                }
                $stmt->close();

                if ($promoted_count > 0) {
                    $conn->commit();
                    $success = $promoted_count . ' student(s) promoted to ' . $to_class . '-' . $to_section . ' successfully with new monthly fee ' . format_currency($new_fee) . '!';
                    $show_student_list = false; // Kaam hone ke baad list chupa dein
                } else {
                    $conn->rollback();
                    $error = 'No students were updated. Please try again.';
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Database Error: ' . $e->getMessage();
            }
        } else {
            $error = 'All fields and at least one student selection are required!';
            $show_student_list = true; // Error pe list wapis dikhayen
        }
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
        <main class="main-content">
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <a href="dashboard.php"><?php echo render_system_logo('topbar-logo'); ?></a>
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
                        <div class="card-header mb-4">
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
                        
                        <form method="POST" id="loadStudentsForm" class="mb-4">
                            <input type="hidden" name="action" value="load_students">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label for="from_class" class="form-label">From Class *</label>
                                    <select id="from_class" name="from_class" class="form-select" required>
                                            <option value="">Select Class</option>
                                            <?php foreach ($CLASSES as $cls): ?>
                                                <option value="<?php echo $cls; ?>" <?php echo ($from_class_selected == $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                </div>
                                    
                                <div class="col-md-4">
                                    <label for="from_section" class="form-label">From Section *</label>
                                    <select id="from_section" name="from_section" class="form-select" required>
                                            <option value="">Select Section</option>
                                            <?php foreach ($SECTIONS as $sec): ?>
                                                <option value="<?php echo $sec; ?>" <?php echo ($from_section_selected == $sec) ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn-primary w-100">
                                        <i class="fas fa-users"></i> Load Students
                                    </button>
                                </div>
                            </div>
                        </form>

                        <?php if ($show_student_list && !empty($students_to_promote)): ?>
                            <hr class="my-4">
                            <form method="POST" id="promoteStudentsForm">
                                <input type="hidden" name="action" value="promote_selected">
                                <input type="hidden" name="from_class" value="<?php echo $from_class_selected; ?>">
                                <input type="hidden" name="from_section" value="<?php echo $from_section_selected; ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="to_class" class="form-label">To Class *</label>
                                        <select id="to_class" name="to_class" class="form-select" required>
                                            <option value="">Select Class</option>
                                            <?php foreach ($CLASSES as $cls): ?>
                                                <option value="<?php echo $cls; ?>"><?php echo $cls; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="to_section" class="form-label">To Section *</label>
                                        <select id="to_section" name="to_section" class="form-select" required>
                                            <option value="">Select Section</option>
                                            <?php foreach ($SECTIONS as $sec): ?>
                                                <option value="<?php echo $sec; ?>"><?php echo $sec; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="new_fixed_monthly_fee" class="form-label">New Fixed Monthly Fee </label>
                                        <input type="number" id="new_fixed_monthly_fee" name="new_fixed_monthly_fee" class="form-control" step="0.01" min="0" >
                                    </div>
                                </div>

                                <h5 class="mb-3">Select Students to Promote (<?php echo count($students_to_promote); ?> found)</h5>
                                <div class="table-responsive mb-4">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th style="width: 50px;">
                                                    <input type="checkbox" id="selectAllStudents" class="form-check-input" checked>
                                                </th>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Father Name</th>
                                                <th>Current Fee</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students_to_promote as $student): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" class="form-check-input student-checkbox" checked>
                                                    </td>
                                                    <td><?php echo $student['id']; ?></td>
                                                    <td><?php echo $student['name']; ?></td>
                                                    <td><?php echo $student['father_name']; ?></td>
                                                    <td><?php echo format_currency($student['monthly_fee']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn-primary btn-lg" onclick="return confirm('Are you sure you want to promote the selected students? This action will update their class, section, and fee structure for the next academic year.')">
                                        <i class="fas fa-check"></i> Promote Selected Students
                                    </button>
                                </div>
                            </form>
                        <?php elseif ($show_student_list && empty($students_to_promote)): ?>
                            <div class="alert alert-info mt-4">No active students found in <?php echo $from_class_selected . '-' . $from_section_selected; ?>.</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="info-box">
                        <h5><i class="fas fa-info-circle"></i> How Promotion Works</h5>
                        <ul>
                            <li>Select the current class and section</li>
                            <li>Select the target class and section</li>
                            <li>Click "Promote Class" to move all active students</li>
                            <li>First, select the current class and section and click "Load Students".</li>
                            <li>A list of active students from that class/section will appear.</li>
                            <li>You can uncheck any student who should not be promoted (e.g., failed students).</li>
                            <li>Select the target class and section for the promoted students.</li>
                            <li>Enter the new fixed monthly fee that will apply to all promoted students in their new class. Their concession amount will be reset to zero.</li>
                            <li>Click "Promote Selected Students" to update their records and generate new fee records for the next academic year.</li>
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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAllStudents');
            const studentCheckboxes = document.querySelectorAll('.student-checkbox');

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    studentCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>