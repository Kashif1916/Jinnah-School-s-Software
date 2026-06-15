<?php
/**
 * Drop Student
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master();

$error = '';
$success = '';
$search_results = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'search') {
        // Search students
        $search = sanitize_input($_POST['search'] ?? '');
        $search_by = sanitize_input($_POST['search_by'] ?? 'name');
        
        if (!empty($search)) {
            if ($search_by == 'name') {
                $query = "SELECT * FROM students WHERE name LIKE ? AND status = 'active'";
                $search_param = '%' . $search . '%';
            } elseif ($search_by == 'class') {
                $query = "SELECT * FROM students WHERE class = ? AND status = 'active'";
                $search_param = $search;
            } else {
                $query = "SELECT * FROM students WHERE section = ? AND status = 'active'";
                $search_param = $search;
            }
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('s', $search_param);
            $stmt->execute();
            $search_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'drop') {
        // Drop student
        $student_id = intval($_POST['student_id'] ?? 0);
        
        $query = "UPDATE students SET status = 'dropped' WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $student_id);
        
        if ($stmt->execute()) {
            $success = 'Student marked as dropped successfully!';
            $search_results = []; // Clear search results
        } else {
            $error = 'Error dropping student: ' . $stmt->error;
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
    <title>Drop Student - <?php echo SITE_NAME; ?></title>
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
                        <h2>Drop Student</h2>
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
                        <a href="promotion.php" class="module-nav-btn">
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
                    
                    <!-- Search Section -->
                    <div class="search-section">
                        <h4>Search Student to Drop</h4>
                        <form method="POST" class="search-form">
                            <input type="hidden" name="action" value="search">
                            <div class="search-grid">
                                <div class="form-group">
                                    <label for="search_by">Search By</label>
                                    <select id="search_by" name="search_by" class="form-control">
                                        <option value="name">Name</option>
                                        <option value="class">Class</option>
                                        <option value="section">Section</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="search">Search Term</label>
                                    <input type="text" id="search" name="search" class="form-control" 
                                           placeholder="Enter search term" required>
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
                                <h5>Active Students Found</h5>
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
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
                                                <td><?php echo $res['name']; ?></td>
                                                <td><?php echo $res['father_name']; ?></td>
                                                <td><?php echo $res['class']; ?></td>
                                                <td><?php echo $res['section']; ?></td>
                                                <td><?php echo $res['contact_number']; ?></td>
                                                <td>
                                                    <span class="badge bg-success">Active</span>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="drop">
                                                        <input type="hidden" name="student_id" value="<?php echo $res['id']; ?>">
                                                        <button type="submit" class="btn-danger-small" 
                                                               onclick="return confirm('Are you sure you want to drop <?php echo $res['name']; ?>?\n\nThis action will mark the student as dropped.')">
                                                            <i class="fas fa-trash"></i> Drop
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php elseif (isset($_POST['action']) && $_POST['action'] == 'search'): ?>
                            <div class="alert alert-info">No active students found!</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Dropped Students List -->
                    <div class="dropped-section" style="margin-top: 50px;">
                        <h4>Dropped Students</h4>
                        <?php
                        $query = "SELECT * FROM students WHERE status = 'dropped' ORDER BY name";
                        $result = $conn->query($query);
                        $dropped_students = $result->fetch_all(MYSQLI_ASSOC);
                        
                        if (count($dropped_students) > 0):
                        ?>
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Class</th>
                                        <th>Section</th>
                                        <th>Dropped Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dropped_students as $dropped): ?>
                                        <tr>
                                            <td><?php echo $dropped['name']; ?></td>
                                            <td><?php echo $dropped['class']; ?></td>
                                            <td><?php echo $dropped['section']; ?></td>
                                            <td><?php echo format_datetime($dropped['updated_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No dropped students
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
</body>
</html>
