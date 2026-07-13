<?php
/**
 * Student Record List
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master();

$search_name = sanitize_input($_GET['search_name'] ?? '');
$search_class = sanitize_input($_GET['search_class'] ?? '');
$search_section = sanitize_input($_GET['search_section'] ?? '');

// Query to fetch students based on filters
$query = "SELECT * FROM students WHERE 1=1";
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
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records - <?php echo SITE_NAME; ?></title>
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
                        <h2>Student Records</h2>
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
                        <a href="student_record.php" class="module-nav-btn active">
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
                    </div>
                </div>

                <div class="search-section mb-4">
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

                <div class="table-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4>Total Students: <?php echo count($students); ?></h4>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Father Name</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Monthly Fee (Fixed)</th>
                                    <th>Monthly Fee (Net)</th>
                                    <th>Concession</th>
                                    <th>Contact Number(s)</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($students) > 0): ?>
                                    <?php foreach ($students as $s): ?>
                                        <tr>
                                            <td><?php echo $s['id']; ?></td>
                                            <td><strong><?php echo $s['name']; ?></strong></td>
                                            <td><?php echo $s['father_name']; ?></td>
                                            <td><?php echo $s['class']; ?></td>
                                            <td><?php echo $s['section']; ?></td>
                                            <td><?php echo format_currency($s['fixed_monthly_fee']); ?></td>
                                            <td><?php echo format_currency($s['monthly_fee']); ?></td>
                                            <td><?php echo format_currency($s['concession_amount']); ?></td>
                                            <td>
                                                <?php echo !empty($s['contact_number']) ? $s['contact_number'] . '<br>' : ''; ?>
                                                <?php echo !empty($s['contact_number2']) ? $s['contact_number2'] . '<br>' : ''; ?>
                                                <?php echo !empty($s['whatsapp_number']) ? $s['whatsapp_number'] : ''; ?>
                                                <span class="badge <?php echo $s['status'] == 'active' ? 'bg-success' : 'bg-danger'; ?>" style="margin-top: 5px; display: block;">
                                                    Status: <?php echo ucfirst($s['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="edit_student.php?id=<?php echo $s['id']; ?>" class="btn-action">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No students found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>