<?php
/**
 * Edit Student
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
    } elseif (isset($_POST['action']) && $_POST['action'] == 'update') {
        // Update student
        $student_id = intval($_POST['student_id'] ?? 0);
        $name = sanitize_input($_POST['name'] ?? '');
        $father_name = sanitize_input($_POST['father_name'] ?? '');
        $class = sanitize_input($_POST['class'] ?? '');
        $section = sanitize_input($_POST['section'] ?? '');
        $monthly_fee = floatval($_POST['monthly_fee'] ?? 0);
        $contact_number = sanitize_input($_POST['contact_number'] ?? '');
        $contact_number2 = sanitize_input($_POST['contact_number2'] ?? '');
        $whatsapp_number = sanitize_input($_POST['whatsapp_number'] ?? '');
        $concession_amount = floatval($_POST['concession_amount'] ?? 0);
        $concession_reason = sanitize_input($_POST['concession_reason'] ?? '');
        
        if (!empty($name) && !empty($father_name) && $monthly_fee > 0) {
            $query = "UPDATE students SET name = ?, father_name = ?, class = ?, section = ?, 
                     monthly_fee = ?, contact_number = ?, contact_number2 = ?, whatsapp_number = ?, concession_amount = ?, concession_reason = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssssdsssdsi', $name, $father_name, $class, $section, $monthly_fee, $contact_number, $contact_number2, $whatsapp_number, $concession_amount, $concession_reason, $student_id);
            
            if ($stmt->execute()) {
                $success = 'Student updated successfully!';
                $student = get_student($student_id);
            } else {
                $error = 'Error updating student: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = 'All required fields must be filled!';
        }
    }
}

// If student ID is in URL, load that student
if (isset($_GET['id'])) {
    $student_id = intval($_GET['id']);
    $student = get_student($student_id);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student - <?php echo SITE_NAME; ?></title>
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
                        <h2>Edit Student</h2>
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
                        <a href="edit_student.php" class="module-nav-btn active">
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
                    
                    <!-- Search Section -->
                    <?php if ($student === null): ?>
                        <div class="search-section">
                            <h4>Search Student to Edit</h4>
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
                                                <th>Father Name</th>
                                                <th>Class</th>
                                                <th>Section</th>
                                                <th>Monthly Fee</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($search_results as $res): ?>
                                                <tr>
                                                    <td><?php echo $res['name']; ?></td>
                                                    <td><?php echo $res['father_name']; ?></td>
                                                    <td><?php echo $res['class']; ?></td>
                                                    <td><?php echo $res['section']; ?></td>
                                                    <td><?php echo format_currency($res['monthly_fee']); ?></td>
                                                    <td>
                                                        <a href="?id=<?php echo $res['id']; ?>" class="btn-action">
                                                            <i class="fas fa-edit"></i> Edit
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
                        <!-- Edit Form -->
                        <div class="edit-form">
                            <h4>Edit Student: <?php echo $student['name']; ?></h4>
                            <form method="POST" id="editStudentForm">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="name">Student Name *</label>
                                        <input type="text" id="name" name="name" class="form-control" 
                                               value="<?php echo $student['name']; ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="father_name">Father's Name *</label>
                                        <input type="text" id="father_name" name="father_name" class="form-control" 
                                               value="<?php echo $student['father_name']; ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="class">Class *</label>
                                        <select id="class" name="class" class="form-control" required>
                                            <?php foreach ($CLASSES as $cls): ?>
                                                <option value="<?php echo $cls; ?>" 
                                                    <?php echo ($cls === $student['class']) ? 'selected' : ''; ?>>
                                                    <?php echo $cls; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="section">Section *</label>
                                        <select id="section" name="section" class="form-control" required>
                                            <?php foreach ($SECTIONS as $sec): ?>
                                                <option value="<?php echo $sec; ?>" 
                                                    <?php echo ($sec === $student['section']) ? 'selected' : ''; ?>>
                                                    <?php echo $sec; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="monthly_fee">Monthly Fee *</label>
                                        <input type="number" id="monthly_fee" name="monthly_fee" class="form-control" 
                                               value="<?php echo $student['monthly_fee']; ?>" required step="0.01" min="0">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="contact_number">Contact Number 1</label>
                                        <input type="tel" id="contact_number" name="contact_number" class="form-control" 
                                               value="<?php echo $student['contact_number'] ?? ''; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="contact_number2">Contact Number 2</label>
                                        <input type="tel" id="contact_number2" name="contact_number2" class="form-control" 
                                               value="<?php echo $student['contact_number2'] ?? ''; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="whatsapp_number">WhatsApp Number</label>
                                        <input type="tel" id="whatsapp_number" name="whatsapp_number" class="form-control" 
                                               value="<?php echo $student['whatsapp_number'] ?? ''; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="concession_amount">Concession Amount</label>
                                        <input type="number" id="concession_amount" name="concession_amount" class="form-control" 
                                               value="<?php echo $student['concession_amount'] ?? 0; ?>" step="0.01" min="0">
                                    </div>

                                    <div class="form-group full-width">
                                        <label for="concession_reason">Concession Reason</label>
                                        <input type="text" id="concession_reason" name="concession_reason" class="form-control" value="<?php echo $student['concession_reason'] ?? ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn-primary">
                                        <i class="fas fa-save"></i> Update Student
                                    </button>
                                    <a href="edit_student.php" class="btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Back
                                    </a>
                                </div>
                            </form>
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
