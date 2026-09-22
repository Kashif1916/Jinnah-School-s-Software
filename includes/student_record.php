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

$search_id = sanitize_input($_GET['search_id'] ?? '');
$search_name = sanitize_input($_GET['search_name'] ?? '');
$search_father_name = sanitize_input($_GET['search_father_name'] ?? '');
$search_b_form = sanitize_input($_GET['search_b_form'] ?? '');
$search_classes = isset($_GET['search_classes']) && is_array($_GET['search_classes']) ? $_GET['search_classes'] : [];
$search_sections = isset($_GET['search_sections']) && is_array($_GET['search_sections']) ? $_GET['search_sections'] : [];
$search_reasons = isset($_GET['search_reasons']) && is_array($_GET['search_reasons']) ? $_GET['search_reasons'] : [];

$CONCESSION_REASONS = ['Sibling', 'Hafiz', 'Orphan', 'S.C', 'EMP', 'T.Son'];

// Check if user has applied any filter
$is_filtered = (!empty($search_id) || !empty($search_name) || !empty($search_father_name) || !empty($search_b_form) || !empty($search_classes) || !empty($search_sections) || !empty($search_reasons));

// Pagination Configuration
$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Build SQL Where Clauses
$where_clauses = ["1=1"];
$params = [];
$param_types = '';

if (!empty($search_id)) {
    $where_clauses[] = "id = ?";
    $params[] = intval($search_id);
    $param_types .= 'i';
}
if (!empty($search_name)) {
    $where_clauses[] = "name LIKE ?";
    $params[] = '%' . $search_name . '%';
    $param_types .= 's';
}
if (!empty($search_father_name)) {
    $where_clauses[] = "father_name LIKE ?";
    $params[] = '%' . $search_father_name . '%';
    $param_types .= 's';
}
if (!empty($search_b_form)) {
    $where_clauses[] = "b_form LIKE ?";
    $params[] = '%' . $search_b_form . '%';
    $param_types .= 's';
}
if (!empty($search_classes)) {
    $placeholders = implode(',', array_fill(0, count($search_classes), '?'));
    $where_clauses[] = "class IN ($placeholders)";
    foreach ($search_classes as $c) {
        $params[] = sanitize_input($c);
        $param_types .= 's';
    }
}
if (!empty($search_sections)) {
    $placeholders = implode(',', array_fill(0, count($search_sections), '?'));
    $where_clauses[] = "section IN ($placeholders)";
    foreach ($search_sections as $s) {
        $params[] = sanitize_input($s);
        $param_types .= 's';
    }
}
if (!empty($search_reasons)) {
    $placeholders = implode(',', array_fill(0, count($search_reasons), '?'));
    $where_clauses[] = "concession_reason IN ($placeholders)";
    foreach ($search_reasons as $r) {
        $params[] = sanitize_input($r);
        $param_types .= 's';
    }
}

$where_sql = implode(" AND ", $where_clauses);

// 1. Get Total Count
$count_query = "SELECT COUNT(*) as total FROM students WHERE $where_sql";
$stmt_count = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt_count->bind_param($param_types, ...$params);
}
$stmt_count->execute();
$total_students = $stmt_count->get_result()->fetch_assoc()['total'];
$stmt_count->close();

$total_pages = ceil($total_students / $limit);

// 2. Fetch Data Query
$query = "SELECT * FROM students WHERE $where_sql ORDER BY id DESC";

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
    <style>
        .filter-checkbox-box {
            background: #ffffff;
            border: 1px solid #ced4da;
            border-radius: 8px;
            padding: 8px 12px;
            height: 120px;
            overflow-y: auto;
        }
        .filter-checkbox-box .form-check {
            margin-bottom: 4px;
            font-size: 13px;
        }
        
        /* 7 Columns Grid Fit */
        .filter-row-7 {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 12px;
            width: 100%;
        }

        @media (max-width: 1400px) {
            .filter-row-7 {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        @media (max-width: 768px) {
            .filter-row-7 {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 576px) {
            .filter-row-7 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <?php render_role_topbar_and_nav('Student Records', 'student_record'); ?>
            
            <div class="form-section">
                <div class="search-section mb-4">
                    <form method="GET">
                        <!-- Top Row: All 7 Inputs Side by Side -->
                        <div class="filter-row-7">
                            <div>
                                <label class="form-label fw-bold">Student ID</label>
                                <input type="number" name="search_id" class="form-control" value="<?php echo htmlspecialchars($search_id); ?>" placeholder="Search ID...">
                            </div>
                            <div>
                                <label class="form-label fw-bold">Student Name</label>
                                <input type="text" name="search_name" class="form-control" value="<?php echo htmlspecialchars($search_name); ?>" placeholder="Search name...">
                            </div>
                            <div>
                                <label class="form-label fw-bold">Father Name</label>
                                <input type="text" name="search_father_name" class="form-control" value="<?php echo htmlspecialchars($search_father_name); ?>" placeholder="Search father...">
                            </div>
                            <div>
                                <label class="form-label fw-bold">B-Form / CNIC</label>
                                <input type="text" name="search_b_form" class="form-control" value="<?php echo htmlspecialchars($search_b_form); ?>" placeholder="Search B-Form...">
                            </div>
                            
                            <!-- Select Class(es) Multi-Checkbox Box -->
                            <div>
                                <label class="form-label fw-bold">Select Class(es)</label>
                                <div class="filter-checkbox-box">
                                    <?php foreach ($CLASSES as $cls): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="search_classes[]" value="<?php echo $cls; ?>" id="cls_<?php echo md5($cls); ?>" <?php echo in_array($cls, $search_classes) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="cls_<?php echo md5($cls); ?>"><?php echo $cls; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Select Section(s) Multi-Checkbox Box -->
                            <div>
                                <label class="form-label fw-bold">Select Section(s)</label>
                                <div class="filter-checkbox-box">
                                    <?php foreach ($SECTIONS as $sec): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="search_sections[]" value="<?php echo $sec; ?>" id="sec_<?php echo md5($sec); ?>" <?php echo in_array($sec, $search_sections) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="sec_<?php echo md5($sec); ?>">Section <?php echo $sec; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Select Concession Reason(s) Multi-Checkbox Box (Shifted Up Here) -->
                            <div>
                                <label class="form-label fw-bold">Concession Reason(s)</label>
                                <div class="filter-checkbox-box">
                                    <?php foreach ($CONCESSION_REASONS as $reason): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="search_reasons[]" value="<?php echo $reason; ?>" id="rsn_<?php echo md5($reason); ?>" <?php echo in_array($reason, $search_reasons) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="rsn_<?php echo md5($reason); ?>"><?php echo $reason; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Button -->
                        <div class="w-100 text-end mt-3">
                            <button type="submit" class="btn-primary px-4">
                                <i class="fas fa-search me-1"></i> Filter / Search Students
                            </button>
                        </div>
                    </form>
                </div>

                <div class="table-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4>Total Students: <?php echo $total_students; ?> </h4>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#printColumnsModal">
                            <i class="fas fa-print"></i> Print List
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Father Name</th>
                                    <th>B-Form / CNIC</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Monthly Fee \ Package (Fixed)</th>
                                    <th>Concession</th>
                                    <th>Monthly Fee \ Package (Net)</th>
                                    <th>Concession Reason</th>
                                    <th>Contact Number(s)</th>
                                    <th>Address</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($students) > 0): ?>
                                    <?php foreach ($students as $s): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($s['id']); ?></strong></td>
                                            <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($s['father_name']); ?></td>
                                            <td><?php echo !empty($s['b_form']) ? htmlspecialchars($s['b_form']) : '<span class="text-muted">-</span>'; ?></td>
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
                                            <td style="max-width: 180px; word-break: break-word; font-size: 13px;"><?php echo !empty($s['address']) ? htmlspecialchars($s['address']) : '<span class="text-muted">-</span>'; ?></td>
                                            <td>
                                                <a href="edit_student.php?id=<?php echo $s['id']; ?>" class="btn-action">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="13" class="text-center">No students found.</td>
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

    <!-- PRINT COLUMN SELECTION MODAL -->
    <div class="modal fade" id="printColumnsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="../master/student_report.php" method="GET" target="_blank">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="fas fa-columns me-2"></i>Select Columns To Print</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Hidden Filter States -->
                        <input type="hidden" name="search_id" value="<?php echo htmlspecialchars($search_id); ?>">
                        <input type="hidden" name="search_name" value="<?php echo htmlspecialchars($search_name); ?>">
                        <input type="hidden" name="search_father_name" value="<?php echo htmlspecialchars($search_father_name); ?>">
                        <input type="hidden" name="search_b_form" value="<?php echo htmlspecialchars($search_b_form); ?>">
                        <?php foreach ($search_classes as $c): ?>
                            <input type="hidden" name="search_classes[]" value="<?php echo htmlspecialchars($c); ?>">
                        <?php endforeach; ?>
                        <?php foreach ($search_sections as $s): ?>
                            <input type="hidden" name="search_sections[]" value="<?php echo htmlspecialchars($s); ?>">
                        <?php endforeach; ?>
                        <?php foreach ($search_reasons as $r): ?>
                            <input type="hidden" name="search_reasons[]" value="<?php echo htmlspecialchars($r); ?>">
                        <?php endforeach; ?>

                        <p class="text-muted small">Select which columns should appear on the printed report:</p>
                        
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="cols[id]" value="1" checked id="col_id">
                                    <label class="form-check-label" for="col_id">Student ID (Roll No.)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="cols[name]" value="1" checked id="col_name">
                                    <label class="form-check-label" for="col_name">Student Name</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="cols[father_name]" value="1" checked id="col_father">
                                    <label class="form-check-label" for="col_father">Father Name</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="cols[b_form]" value="1" checked id="col_bform">
                                    <label class="form-check-label" for="col_bform">B-Form / CNIC</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="cols[class_sec]" value="1" checked id="col_class_sec">
                                    <label class="form-check-label" for="col_class_sec">Class-Sec</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="cols[status]" value="1" checked id="col_status">
                                    <label class="form-check-label" for="col_status">Status</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="cols[fixed_fee]" value="1" checked id="col_fixed">
                                    <label class="form-check-label" for="col_fixed">Monthly Fee (Fixed)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="cols[concession]" value="1" checked id="col_concession">
                                    <label class="form-check-label" for="col_concession">Concession Amount</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="cols[net_fee]" value="1" checked id="col_net">
                                    <label class="form-check-label" for="col_net">Monthly Fee (Net)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="cols[concession_reason]" value="1" checked id="col_concession_reason">
                                    <label class="form-check-label fw-bold text-success" for="col_concession_reason">Concession Reason</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="cols[contact]" value="1" checked id="col_contact">
                                    <label class="form-check-label" for="col_contact">Contact Numbers</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="cols[address]" value="1" checked id="col_address">
                                    <label class="form-check-label" for="col_address">Address</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-print me-1"></i> Generate & Print Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>