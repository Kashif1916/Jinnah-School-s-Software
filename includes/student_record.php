<?php
/**
 * Student Record List - Core Module
 * School Finance Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';

require_login();
if (!is_master() && !is_finance() && !is_admission() && !is_teacher()) {
    header('Location: ../login.php');
    exit();
}

$search_name = sanitize_input($_GET['search_name'] ?? '');
$search_class = sanitize_input($_GET['search_class'] ?? '');
$search_section = sanitize_input($_GET['search_section'] ?? '');

// Check if user has applied any filter
$is_filtered = (!empty($search_name) || !empty($search_class) || !empty($search_section));

// Pagination Configuration (Only applies when NO filter is used)
$limit = 20; // Default items per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 1. Get Total Count for UI Display & Pagination Calculation
$count_query = "SELECT COUNT(*) as total FROM students WHERE 1=1";
$count_params = [];
$count_types = '';

if (!empty($search_name)) {
    $count_query .= " AND name LIKE ?";
    $count_params[] = '%' . $search_name . '%';
    $count_types .= 's';
}
if (!empty($search_class)) {
    $count_query .= " AND class = ?";
    $count_params[] = $search_class;
    $count_types .= 's';
}
if (!empty($search_section)) {
    $count_query .= " AND section = ?";
    $count_params[] = $search_section;
    $count_types .= 's';
}

$stmt_count = $conn->prepare($count_query);
if (!empty($count_params)) {
    $stmt_count->bind_param($count_types, ...$count_params);
}
$stmt_count->execute();
$total_students = $stmt_count->get_result()->fetch_assoc()['total'];
$stmt_count->close();

$total_pages = ceil($total_students / $limit);

// 2. Fetch Data Query
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

// ONLY APPLY LIMIT 20 IF NO FILTER IS ACTIVE
if (!$is_filtered) {
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $param_types .= 'ii';
}

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
            <?php render_role_topbar_and_nav('Student Records', 'student_record'); ?>
            
            <div class="form-section">
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
                        <h4>Total Students: <?php echo $total_students; ?> </h4>
                        <a href="../master/student_report.php?search_name=<?php echo urlencode($search_name); ?>&search_class=<?php echo urlencode($search_class); ?>&search_section=<?php echo urlencode($search_section); ?>" target="_blank" class="btn btn-success">
                            <i class="fas fa-print"></i> Print List
                        </a>
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
                                    <th>Monthly Fee \ Package (Fixed)</th>
                                    <th>Concession</th>
                                    <th>Monthly Fee \ Package (Net)</th>
                                    <th>Concession Reason</th>
                                    <th>Contact Number(s)</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($students) > 0): ?>
                                    <?php foreach ($students as $s): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($s['id']); ?></td>
                                            <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($s['father_name']); ?></td>
                                            <td><?php echo htmlspecialchars($s['class']); ?></td>
                                            <td><?php echo htmlspecialchars($s['section']); ?></td>
                                            <td>
                                                <?php 
                                                $is_pkg = (!empty($s['is_package']) || floatval($s['package_amount'] ?? 0) > 0 || is_college_class($s['class']));
                                                if ($is_pkg): 
                                                    $raw_pkg = floatval($s['package_amount'] > 0 ? $s['package_amount'] : $s['fixed_monthly_fee']);
                                                ?>
                                                    <strong class="text-primary"><?php echo format_currency($raw_pkg); ?></strong>
                                                    <span class="badge bg-primary text-white ms-1">Yearly Pkg</span>
                                                <?php else: ?>
                                                    <?php echo format_currency($s['fixed_monthly_fee']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo format_currency($s['concession_amount']); ?></td>
                                            <td>
                                                <?php 
                                                if ($is_pkg) {
                                                    $raw_pkg = floatval($s['package_amount'] > 0 ? $s['package_amount'] : $s['fixed_monthly_fee']);
                                                    $net_pkg = max(0, $raw_pkg - floatval($s['concession_amount']));
                                                    echo '<strong class="text-primary">' . format_currency($net_pkg) . '</strong> <span class="badge bg-primary text-white ms-1">Yearly Pkg</span>';
                                                } else {
                                                    echo format_currency(floatval($s['fixed_monthly_fee']) - floatval($s['concession_amount']));
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($s['concession_reason'] ?? ''); ?></td>
                                            <td>
                                                <?php echo !empty($s['contact_number']) ? '<i class="fas fa-phone"></i> ' . htmlspecialchars($s['contact_number']) . '<br>' : ''; ?>
                                                <?php echo !empty($s['contact_number2']) ? '<i class="fas fa-phone"></i> ' . htmlspecialchars($s['contact_number2']) . '<br>' : ''; ?>
                                                <?php echo !empty($s['whatsapp_number']) ? '<i class="fab fa-whatsapp text-success"></i> ' . htmlspecialchars($s['whatsapp_number']) : ''; ?>
                                                <span class="badge <?php echo $s['status'] == 'active' ? 'bg-success' : 'bg-danger'; ?>" style="margin-top: 5px; display: block;">
                                                    Status: <?php echo ucfirst(htmlspecialchars($s['status'])); ?>
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
                                        <td colspan="11" class="text-center">No students found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINATION BUTTONS -->
                    <?php render_pagination($page, $total_pages, '', $is_filtered); ?>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
