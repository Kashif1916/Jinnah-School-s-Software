<?php
/**
 * Drop Student (Updated UI & Colors Matching Search Button)
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
$dropped_students = [];

// Determine current view mode ('drop_mode' or 'see_mode')
$view_mode = $_GET['view'] ?? 'drop_mode';

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'bulk_drop') {
    $student_ids = $_POST['student_ids'] ?? [];
    $drop_reason = sanitize_input($_POST['drop_reason'] ?? '');
    $dropped_by = get_username();
    
    if (empty($student_ids)) {
        $error = 'Please select at least one student to drop.';
    } elseif (empty($drop_reason)) {
        $error = 'Drop reason is required.';
    } else {
        $conn->begin_transaction();
        try {
            $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
            
            // Update students status
            $query = "UPDATE students SET status = 'dropped', drop_reason = ? WHERE id IN ($placeholders)";
            $stmt = $conn->prepare($query);
            
            $types = 's' . str_repeat('i', count($student_ids));
            $bind_params = array_merge([$drop_reason], array_map('intval', $student_ids));
            $stmt->bind_param($types, ...$bind_params);
            $stmt->execute();
            $stmt->close();
            
            // Insert log records
            $log_query = "INSERT INTO dropped_students (student_id, dropped_by, drop_reason) VALUES (?, ?, ?)";
            $log_stmt = $conn->prepare($log_query);
            foreach ($student_ids as $id) {
                $student_id = intval($id);
                $log_stmt->bind_param('iss', $student_id, $dropped_by, $drop_reason);
                $log_stmt->execute();
            }
            $log_stmt->close();
            
            $conn->commit();
            $success = count($student_ids) . ' student(s) marked as dropped successfully!';
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Error dropping students: ' . $e->getMessage();
        }
    }
}

// --- LOGIC FOR DROP MODE (ACTIVE STUDENTS) ---
$search_name = sanitize_input($_GET['search_name'] ?? '');
$search_class = sanitize_input($_GET['search_class'] ?? '');
$search_section = sanitize_input($_GET['search_section'] ?? '');

if ($view_mode === 'drop_mode') {
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
}

// --- LOGIC FOR SEE MODE (DROPPED STUDENTS HISTORY WITH YEAR & CLASS) ---
$filter_year = sanitize_input($_GET['filter_year'] ?? '');
$filter_class = sanitize_input($_GET['filter_class'] ?? '');

if ($view_mode === 'see_mode') {
    $query = "SELECT ds.*, s.name, s.class, s.section 
              FROM dropped_students ds 
              JOIN students s ON ds.student_id = s.id WHERE 1=1";
    $params = [];
    $param_types = '';

    if (!empty($filter_year)) {
        $query .= " AND YEAR(ds.dropped_at) = ?";
        $params[] = intval($filter_year);
        $param_types .= 'i';
    }
    if (!empty($filter_class)) {
        $query .= " AND s.class = ?";
        $params[] = $filter_class;
        $param_types .= 's';
    }

    $query .= " ORDER BY ds.dropped_at DESC";
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $dropped_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
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
    <style>
        /* Custom Modern Tabs UI with Exact Matching Active Color */
        .mode-container {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 50px;
            display: inline-flex;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);
        }
        .mode-btn {
            border-radius: 40px !important;
            font-weight: 600;
            padding: 10px 28px;
            font-size: 15px;
            transition: all 0.3s ease;
            border: none !important;
        }
        /* Color matching exactly with btn-primary / search button styling */
        .mode-btn.active-mode {
            background-color: #24493a !important;
            color: #fff !important;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        .mode-btn:not(.active-mode) {
            color: #7f8c8d;
            background: transparent;
        }
        .mode-btn:not(.active-mode):hover {
            color: #333;
            background: rgba(0,0,0,0.04);
        }
    </style>
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <a href="dashboard.php"><?php echo render_system_logo('topbar-logo'); ?></a>
                    <div class="panel-brand">
                        <h2>Drop Student </h2>
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
                        <a href="data_entry.php" class="module-nav-btn">
                            <i class="fas fa-keyboard"></i> Data Entry
                        </a>
                        <a href="student_record.php" class="module-nav-btn ">
                            <i class="fas fa-address-book"></i> Student Record
                        </a>
                        <a href="defaulter_list.php" class="module-nav-btn">
                            <i class="fas fa-list"></i> Pending List
                        </a>
                        <a href="promotion.php" class="module-nav-btn ">
                            <i class="fas fa-arrow-up"></i> Promotion
                        </a>
                        <a href="drop_student.php" class="module-nav-btn active">
                            <i class="fas fa-trash"></i> Drop Student
                        </a>
                        <a href="../help.php" class="module-nav-btn">
                            <i class="fas fa-question-circle text-success"></i> Help & About
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
                    
                    <!-- --- BOTH BUTTONS HAVE SAME ACTIVE COLOR NOW --- -->
                    <div class="text-center mb-4">
                        <div class="mode-container">
                            <a href="drop_student.php?view=drop_mode" class="btn mode-btn <?php echo $view_mode === 'drop_mode' ? 'active-mode' : ''; ?>">
                                <i class="fas fa-user-minus me-2"></i> Want to Drop Student
                            </a>
                            <a href="drop_student.php?view=see_mode" class="btn mode-btn <?php echo $view_mode === 'see_mode' ? 'active-mode' : ''; ?>">
                                <i class="fas fa-eye me-2"></i> Want to See Drop Student
                            </a>
                        </div>
                    </div>
                    
                    <hr class="mb-4 text-muted opacity-25">

                    <!-- ==================== MODE 1: WANT TO DROP STUDENT ==================== -->
                    <?php if ($view_mode === 'drop_mode'): ?>
                        <div class="search-section mb-4">
                            <h4>Search Active Students to Drop</h4>
                            <form method="GET" class="row g-3">
                                <input type="hidden" name="view" value="drop_mode">
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
                                    <button type="submit" class="btn btn-primary w-100 py-2">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="search-results mt-4">
                            <h5>Active Students (Total: <?php echo count($search_results); ?>)</h5>
                            <?php if (count($search_results) > 0): ?>
                                <form id="bulkDropForm" method="POST" onsubmit="return handleBulkDropSubmit(this);">
                                    <input type="hidden" name="action" value="bulk_drop">
                                    <input type="hidden" name="drop_reason" id="bulkDropReason" value="">
                                    
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th width="40px">
                                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                                </th>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Father Name</th>
                                                <th>Class</th>
                                                <th>Section</th>
                                                <th>Contact</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($search_results as $res): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="student_ids[]" value="<?php echo $res['id']; ?>" class="form-check-input student-checkbox">
                                                    </td>
                                                    <td><?php echo $res['id']; ?></td>
                                                    <td><strong><?php echo htmlspecialchars($res['name']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($res['father_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($res['class']); ?></td>
                                                    <td><?php echo htmlspecialchars($res['section']); ?></td>
                                                    <td><?php echo htmlspecialchars($res['contact_number']); ?></td>
                                                    <td><span class="badge bg-success">Active</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-danger px-4 py-2">
                                            <i class="fas fa-user-times"></i> Drop Selected Students
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-info">No active students found with these filters!</div>
                            <?php endif; ?>
                        </div>

                    <!-- ==================== MODE 2: WANT TO SEE DROP STUDENT ==================== -->
                    <?php else: ?>
                        <div class="search-section mb-4">
                            <h4>Search Dropped Students History</h4>
                            <form method="GET" class="row g-3">
                                <input type="hidden" name="view" value="see_mode">
                                <div class="col-md-5">
                                    <label class="form-label">Year</label>
                                    <input type="number" name="filter_year" class="form-control" value="<?php echo htmlspecialchars($filter_year); ?>" placeholder="e.g. 2026" min="2000" max="2099">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Class</label>
                                    <select name="filter_class" class="form-select">
                                        <option value="">All Classes</option>
                                        <?php foreach ($CLASSES as $cls): ?>
                                            <option value="<?php echo $cls; ?>" <?php echo $filter_class == $cls ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Match exact same design and color layout as shared -->
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100 py-2">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="dropped-section mt-4">
                            <h5>Dropped Students Records (Total: <?php echo count($dropped_students); ?>)</h5>
                            <?php if (count($dropped_students) > 0): ?>
                                <table class="table table-hover align-middle">
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
                                                <td><strong><?php echo htmlspecialchars($dropped['name']); ?></strong></td>
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
                                    <i class="fas fa-info-circle"></i> No dropped students found with current filters.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    
    <script>
        // Checkbox Select All functionality (Only in Drop Mode)
        const selectAllCheckbox = document.getElementById('selectAll');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.student-checkbox');
                checkboxes.forEach(cb => cb.checked = this.checked);
            });
        }

        // Bulk Drop Validation and Prompter
        function handleBulkDropSubmit(form) {
            const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert("Please select at least one student from the check ticks to drop!");
                return false;
            }
            
            const reason = prompt("Are you sure you want to drop " + checkedBoxes.length + " selected student(s)?\nPlease enter the reason for dropping:");
            if (reason === null) {
                return false; // User cancelled prompt
            }
            if (reason.trim() === '') {
                alert("Drop reason is required to process bulk drop.");
                return false;
            }
            
            document.getElementById('bulkDropReason').value = reason.trim();
            return true;
        }
    </script>
</body>
</html>