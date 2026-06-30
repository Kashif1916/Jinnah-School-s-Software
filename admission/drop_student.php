<?php
/**
 * Drop Student
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_admission();

$error = '';
$success = '';
$search_results = [];

// Get Advanced Filters from URL (GET Method)
$search_name = sanitize_input($_GET['search_name'] ?? '');
$search_class = sanitize_input($_GET['search_class'] ?? '');
$search_section = sanitize_input($_GET['search_section'] ?? '');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'drop') {
    $student_id = intval($_POST['student_id'] ?? 0);
    $drop_reason = sanitize_input($_POST['drop_reason'] ?? '');
    $dropped_by = get_username();
    
    $conn->begin_transaction();
    try {
        $query = "UPDATE students SET status = 'dropped', drop_reason = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('si', $drop_reason, $student_id);
        $stmt->execute();
        $stmt->close();
        
        $log_query = "INSERT INTO dropped_students (student_id, dropped_by, drop_reason) VALUES (?, ?, ?)";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->bind_param('iss', $student_id, $dropped_by, $drop_reason);
        $log_stmt->execute();
        $log_stmt->close();
        
        $conn->commit();
        $success = 'Student marked as dropped successfully!';
    } catch (Exception $e) {
        $conn->rollback();
        $error = 'Error dropping student: ' . $e->getMessage();
    }
}

// Fetch Active Students based on Advanced Filters
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

$query .= " ORDER BY id DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$search_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drop Student - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <?php echo render_system_logo('topbar-logo'); ?>
                    <div class="panel-brand">
                        <h2>Drop Student</h2>
                        <span>Admission Panel</span>
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
                        <a href="add_student.php" class="module-nav-btn">
                            <i class="fas fa-user-plus"></i> Add Student
                        </a>
                        <a href="student_record.php" class="module-nav-btn ">
                            <i class="fas fa-address-book"></i> Student Record
                        </a>
                        <a href="promotion.php" class="module-nav-btn ">
                            <i class="fas fa-arrow-up"></i> Promotion
                        </a>
                       
                        <a href="drop_student.php" class="module-nav-btn active">
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
                    
                    <div class="search-section mb-4">
                        <h4>Search Active Students to Drop</h4>
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Student Name</label>
                                <input type="text" name="search_name" class="form-control" value="<?php echo htmlspecialchars($search_name); ?>" placeholder="Search by name...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Class</label>
                                <select name="search_class" class="form-select">
                                    <option value="">All Classes</option>
                                    <?php foreach ($CLASSES as $cls): ?>
                                        <option value="<?php echo $cls; ?>" <?php echo $search_class == $cls ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Section</label>
                                <select name="search_section" class="form-select">
                                    <option value="">All Sections</option>
                                    <?php foreach ($SECTIONS as $sec): ?>
                                        <option value="<?php echo $sec; ?>" <?php echo $search_section == $sec ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn-primary w-100">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="search-results mt-4">
                        <h5>Active Students (Total: <?php echo count($search_results); ?>)</h5>
                        <?php if (count($search_results) > 0): ?>
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Father Name</th>
                                        <th>Class</th>
                                        <th>Section</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($search_results as $res): ?>
                                        <tr>
                                            <td><?php echo $res['id']; ?></td>
                                            <td><strong><?php echo $res['name']; ?></strong></td>
                                            <td><?php echo $res['father_name']; ?></td>
                                            <td><?php echo $res['class']; ?></td>
                                            <td><?php echo $res['section']; ?></td>
                                            <td><?php echo $res['contact_number']; ?></td>
                                            <td>
                                                <span class="badge bg-success">Active</span>
                                            </td>
                                            <td>
                                                <form method="POST" style="display:inline;" onsubmit="return handleDropSubmit(this, '<?php echo htmlspecialchars($res['name'], ENT_QUOTES); ?>')">
                                                    <input type="hidden" name="action" value="drop">
                                                    <input type="hidden" name="student_id" value="<?php echo $res['id']; ?>">
                                                    <input type="hidden" name="drop_reason" class="drop-reason-input" value="">
                                                    <button type="submit" class="btn-danger-small">
                                                        <i class="fas fa-trash"></i> Drop
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="alert alert-info">No active students found with these filters!</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="dropped-section" style="margin-top: 50px;">
                        <h4>Dropped Students History</h4>
                        <?php
                        $query = "SELECT ds.*, s.name, s.class, s.section 
                                  FROM dropped_students ds 
                                  JOIN students s ON ds.student_id = s.id 
                                  ORDER BY ds.dropped_at DESC";
                        $result = $conn->query($query);
                        $dropped_students = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        
                        if (count($dropped_students) > 0):
                        ?>
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Class</th>
                                        <th>Section</th>
                                        <th>Dropped Date</th>
                                        <th>Dropped By</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dropped_students as $dropped): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($dropped['name']); ?></td>
                                            <td><?php echo htmlspecialchars($dropped['class']); ?></td>
                                            <td><?php echo htmlspecialchars($dropped['section']); ?></td>
                                            <td><?php echo format_datetime($dropped['dropped_at']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($dropped['dropped_by']); ?></span></td>
                                            <td><?php echo htmlspecialchars($dropped['drop_reason']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No dropped students log found.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <style>
        .btn-danger-small {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        
        .btn-danger-small:hover {
            background: #c0392b;
            text-decoration: none;
            color: white;
        }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        function handleDropSubmit(form, studentName) {
            const reason = prompt("Are you sure you want to drop " + studentName + "?\nPlease enter the reason for dropping this student:");
            if (reason === null) {
                return false; // User cancelled
            }
            if (reason.trim() === '') {
                alert("Drop reason is required!");
                return false;
            }
            form.querySelector('.drop-reason-input').value = reason.trim();
            return true;
        }
    </script>
</body>
</html>