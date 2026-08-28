<?php
/**
 * Edit Student - Core Module
 * School Finance Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';

require_login();
if (!is_master() && !is_finance() && !is_admission()) {
    header('Location: ../login.php');
    exit();
}

if (!is_master() && !has_edit_access()) {
    header('Location: student_record.php');
    exit();
}

$error = '';
$success = '';
$student = null;
$search_results = [];

// Fetch class fee schedule
$class_fees = [];
$fee_res = $conn->query("SELECT class, fixed_monthly_fee FROM fee_schedule");
if ($fee_res) {
    while ($row = $fee_res->fetch_assoc()) {
        $class_fees[$row['class']] = floatval($row['fixed_monthly_fee']);
    }
}

// Handle GET request if student ID is passed directly
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $student_id = intval($_GET['id']);
    $student = get_student($student_id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'search') {
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
    } elseif ($action === 'update') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $name = sanitize_input($_POST['name'] ?? '');
        $father_name = sanitize_input($_POST['father_name'] ?? '');
        $class = sanitize_input($_POST['class'] ?? '');
        $section = sanitize_input($_POST['section'] ?? '');
        $contact_number = sanitize_input($_POST['contact_number'] ?? '');
        $contact_number2 = sanitize_input($_POST['contact_number2'] ?? '');
        $whatsapp_number = sanitize_input($_POST['whatsapp_number'] ?? '');
        $concession_amount = floatval($_POST['concession_amount'] ?? 0);
        $concession_reason = sanitize_input($_POST['concession_reason'] ?? '');
        $selected_months = $_POST['concession_months'] ?? [];

        $is_package = is_college_class($class) ? 1 : 0;
        if ($is_package) {
            $package_amount = floatval($_POST['package_amount'] ?? 0);
            $fixed_monthly_fee = 0;
        } else {
            $package_amount = 0;
            $fixed_monthly_fee = floatval($_POST['monthly_fee'] ?? 0);
        }

        $fee_to_validate = $is_package ? $package_amount : $fixed_monthly_fee;

        if (!empty($name) && !empty($father_name) && $fee_to_validate > 0) {
            $paid_months_selected = [];
            if (!empty($selected_months)) {
                foreach ($selected_months as $m_val) {
                    $stmt_chk = $conn->prepare("SELECT status FROM fee_records WHERE student_id = ? AND month = ?");
                    $stmt_chk->bind_param('is', $student_id, $m_val);
                    $stmt_chk->execute();
                    $res_chk = $stmt_chk->get_result()->fetch_assoc();
                    $stmt_chk->close();

                    if ($res_chk && strtolower($res_chk['status']) === 'paid') {
                        $paid_months_selected[] = $m_val;
                    }
                }
            }

            if (!empty($paid_months_selected)) {
                $error = 'Error: Fee for month(s) (' . implode(', ', $paid_months_selected) . ') is already paid for this student! Please uncheck paid month(s) before applying concession.';
                $student = get_student($student_id);
            } else {
                $net_fee = $fixed_monthly_fee - $concession_amount;
                if ($net_fee < 0) $net_fee = 0;

                $query = "UPDATE students SET name = ?, father_name = ?, class = ?, section = ?, 
                          fixed_monthly_fee = ?, package_amount = ?, is_package = ?, contact_number = ?, contact_number2 = ?, whatsapp_number = ?, concession_amount = ?, concession_reason = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ssssddisssdsi', $name, $father_name, $class, $section, $fixed_monthly_fee, $package_amount, $is_package, $contact_number, $contact_number2, $whatsapp_number, $concession_amount, $concession_reason, $student_id);

                if ($stmt->execute()) {
                    if (!empty($selected_months)) {
                        foreach ($selected_months as $m_val) {
                            $stmt_rec = $conn->prepare("SELECT status FROM fee_records WHERE student_id = ? AND month = ?");
                            $stmt_rec->bind_param('is', $student_id, $m_val);
                            $stmt_rec->execute();
                            $rec_m = $stmt_rec->get_result()->fetch_assoc();
                            $stmt_rec->close();

                            if ($rec_m) {
                                if (strtolower($rec_m['status']) === 'unpaid') {
                                    $stmt_upd = $conn->prepare("UPDATE fee_records SET amount = ? WHERE student_id = ? AND month = ? AND status = 'unpaid'");
                                    $stmt_upd->bind_param('dis', $net_fee, $student_id, $m_val);
                                    $stmt_upd->execute();
                                    $stmt_upd->close();
                                }
                            } else {
                                $stmt_ins = $conn->prepare("INSERT INTO fee_records (student_id, month, amount, status) VALUES (?, ?, ?, 'unpaid')");
                                $stmt_ins->bind_param('isd', $student_id, $m_val, $net_fee);
                                $stmt_ins->execute();
                                $stmt_ins->close();
                            }
                        }
                    }

                    if ($is_package) {
                        $net_pkg = $package_amount - $concession_amount;
                        if ($net_pkg < 0) $net_pkg = 0;

                        // Check if student has an existing unpaid package record
                        $stmt_pkg = $conn->prepare("SELECT id, amount FROM fee_records WHERE student_id = ? AND (month LIKE '%Package%' OR month = 'Yearly Package') AND status = 'unpaid'");
                        $stmt_pkg->bind_param('i', $student_id);
                        $stmt_pkg->execute();
                        $pkg_res = $stmt_pkg->get_result()->fetch_assoc();
                        $stmt_pkg->close();

                        if ($pkg_res) {
                            $stmt_paid_pkg = $conn->prepare("SELECT SUM(amount) as total_paid FROM payments WHERE student_id = ? AND (paid_for_month LIKE '%Package%' OR paid_for_month = 'Yearly Package')");
                            $stmt_paid_pkg->bind_param('i', $student_id);
                            $stmt_paid_pkg->execute();
                            $paid_row = $stmt_paid_pkg->get_result()->fetch_assoc();
                            $stmt_paid_pkg->close();
                            $already_paid = floatval($paid_row['total_paid'] ?? 0);

                            $new_unpaid_amount = max(0, $net_pkg - $already_paid);
                            $pkg_rec_id = $pkg_res['id'];
                            $conn->query("UPDATE fee_records SET amount = $new_unpaid_amount WHERE id = $pkg_rec_id");
                        }
                    } else {
                        sync_unpaid_fee_amounts($student_id, $net_fee);
                        auto_generate_fee_buffer($student_id, $net_fee);
                    }

                    $success = 'Student record updated successfully!';
                    $student = get_student($student_id);
                } else {
                    $error = 'Error updating student record: ' . $stmt->error;
                    $student = get_student($student_id);
                }
                $stmt->close();
            }
        } else {
            $error = 'Please fill in all required fields!';
            $student = get_student($student_id);
        }
    }
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
        <main class="main-content">
            <?php render_role_topbar_and_nav('Edit Student Record', 'student_record'); ?>

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
                
                <!-- Search Results -->
                <?php if (!empty($search_results)): ?>
                    <div class="table-section mb-4">
                        <h4>Search Results (<?php echo count($search_results); ?>)</h4>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Father Name</th>
                                        <th>Class-Sec</th>
                                        <th>Monthly Fee</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($search_results as $res): ?>
                                        <tr>
                                            <td><?php echo $res['id']; ?></td>
                                            <td><?php echo htmlspecialchars($res['name']); ?></td>
                                            <td><?php echo htmlspecialchars($res['father_name']); ?></td>
                                            <td><?php echo htmlspecialchars($res['class']) . '-' . htmlspecialchars($res['section']); ?></td>
                                            <td><?php echo format_currency($res['fixed_monthly_fee']); ?></td>
                                            <td>
                                                <a href="edit_student.php?id=<?php echo $res['id']; ?>" class="btn-primary btn-sm">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Edit Form -->
                <?php if ($student): ?>
                    <div class="form-container">
                        <h4 class="mb-4"><i class="fas fa-user-edit me-2"></i>Edit Student: <?php echo htmlspecialchars($student['name']); ?> (ID: <?php echo $student['id']; ?>)</h4>
                        <form method="POST" class="student-form" id="editStudentForm">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="name">Student Name *</label>
                                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($student['name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="father_name">Father's Name *</label>
                                    <input type="text" id="father_name" name="father_name" class="form-control" value="<?php echo htmlspecialchars($student['father_name']); ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="class">Class *</label>
                                    <select id="class" name="class" class="form-select" required>
                                        <?php foreach ($CLASSES as $cls): ?>
                                            <option value="<?php echo $cls; ?>" <?php echo ($student['class'] === $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="section">Section *</label>
                                    <select id="section" name="section" class="form-select" required>
                                        <?php foreach ($SECTIONS as $sec): ?>
                                            <option value="<?php echo $sec; ?>" <?php echo ($student['section'] === $sec) ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <?php $is_std_pkg = is_college_class($student['class']) || !empty($student['is_package']); ?>
                            <div class="row mb-3">
                                <div class="col-md-6 <?php echo $is_std_pkg ? 'd-none' : ''; ?>" id="monthly_fee_container">
                                    <label class="form-label" for="monthly_fee">Fixed Monthly Fee *</label>
                                    <input type="number" id="monthly_fee" name="monthly_fee" class="form-control" step="0.01" min="0" value="<?php echo $student['fixed_monthly_fee']; ?>" <?php echo !$is_std_pkg ? 'required' : ''; ?>>
                                </div>
                                <div class="col-md-6 <?php echo !$is_std_pkg ? 'd-none' : ''; ?>" id="package_fee_container">
                                    <label class="form-label" for="package_amount">Total Package Amount (Rs.) *</label>
                                    <input type="number" id="package_amount" name="package_amount" class="form-control" step="0.01" min="0" value="<?php echo $student['package_amount']; ?>" <?php echo $is_std_pkg ? 'required' : ''; ?>>
                                    <small class="text-muted"><i class="fas fa-info-circle"></i> Yearly Package scheduled as a single fee (supports partial payments)</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="contact_number">Contact Number 1</label>
                                    <input type="tel" id="contact_number" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($student['contact_number'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="contact_number2" class="form-label">Contact Number 2</label>
                                    <input type="tel" id="contact_number2" name="contact_number2" class="form-control" value="<?php echo htmlspecialchars($student['contact_number2'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="whatsapp_number" class="form-label">WhatsApp Number</label>
                                    <input type="tel" id="whatsapp_number" name="whatsapp_number" class="form-control" value="<?php echo htmlspecialchars($student['whatsapp_number'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="concession_amount">Concession Amount</label>
                                    <input type="number" id="concession_amount" name="concession_amount" class="form-control" value="<?php echo $student['concession_amount'] ?? 0; ?>" step="0.01" min="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="concession_reason">Concession Reason</label>
                                    <select id="concession_reason" name="concession_reason" class="form-select">
                                        <option value="" <?php echo ($student['concession_reason'] ?? '') === '' ? 'selected' : ''; ?>>None</option>
                                        <option value="Sibling" <?php echo ($student['concession_reason'] ?? '') === 'Sibling' ? 'selected' : ''; ?>>Sibling</option>
                                        <option value="Hafiz" <?php echo ($student['concession_reason'] ?? '') === 'Hafiz' ? 'selected' : ''; ?>>Hafiz</option>
                                        <option value="Orphan" <?php echo ($student['concession_reason'] ?? '') === 'Orphan' ? 'selected' : ''; ?>>Orphan</option>
                                        <option value="S.C" <?php echo ($student['concession_reason'] ?? '') === 'S.C' ? 'selected' : ''; ?>>S.C</option>
                                        <option value="EMP" <?php echo ($student['concession_reason'] ?? '') === 'EMP' ? 'selected' : ''; ?>>EMP</option>
                                        <option value="T.Son" <?php echo ($student['concession_reason'] ?? '') === 'T.Son' ? 'selected' : ''; ?>>T.Son</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label fw-bold"><i class="fas fa-calendar-check me-1"></i> Select Previous Unpaid Month(s) to Apply Concession</label>
                                    <div class="months-checkbox-container p-3 border rounded bg-light" style="max-height: 180px; overflow-y: auto;">
                                        <div class="row">
                                            <?php 
                                            $stmt_unpaid = $conn->prepare("SELECT DISTINCT month FROM fee_records WHERE student_id = ? AND LOWER(status) = 'unpaid' ORDER BY id ASC");
                                            $stmt_unpaid->bind_param('i', $student['id']);
                                            $stmt_unpaid->execute();
                                            $res_unpaid = $stmt_unpaid->get_result();

                                            $current_first_day = date('Y-m-01');
                                            $previous_unpaid_found = false;

                                            if ($res_unpaid && $res_unpaid->num_rows > 0):
                                                while ($row_u = $res_unpaid->fetch_assoc()):
                                                    $m_name = $row_u['month'];
                                                    $m_time = strtotime($m_name);
                                                    
                                                    if ($m_time !== false && date('Y-m-01', $m_time) < $current_first_day):
                                                        $previous_unpaid_found = true;
                                            ?>
                                                <div class="col-md-3 col-6 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input concession-month-cb" type="checkbox" name="concession_months[]" value="<?php echo htmlspecialchars($m_name); ?>" id="m_cb_<?php echo htmlspecialchars($m_name); ?>">
                                                        <label class="form-check-label" for="m_cb_<?php echo htmlspecialchars($m_name); ?>">
                                                            <?php echo htmlspecialchars($m_name); ?>
                                                            <span class="badge bg-danger ms-1">Unpaid</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php 
                                                    endif;
                                                endwhile;
                                            endif;

                                            if (!$previous_unpaid_found):
                                            ?>
                                                <div class="col-12">
                                                    <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i> No previous unpaid months available for this student.</p>
                                                </div>
                                            <?php 
                                            endif; 
                                            $stmt_unpaid->close();
                                            ?>
                                        </div>
                                    </div>
                                    <small class="text-muted"><i class="fas fa-info-circle"></i> Note: Concession automatically applies to current & future unpaid months. Check boxes above only if you want to apply concession on <strong>previous unpaid months</strong>.</small>
                                </div>
                            </div>
                            
                            <div class="form-actions mt-4">
                                <button type="submit" class="btn-primary me-2">
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
    <script>
        const classFees = <?php echo json_encode($class_fees); ?>;
        const collegeClasses = ['11', '12', 'passed-12', '11th', '12th', 'f.sc', 'fa', 'ics', 'i.com'];

        function isCollegeClass(cls) {
            if (!cls) return false;
            return collegeClasses.includes(cls.trim().toLowerCase());
        }

        const classSelect = document.getElementById('class');
        if (classSelect) {
            classSelect.addEventListener('change', function() {
                const cls = this.value;
                const monthlyBox = document.getElementById('monthly_fee_container');
                const packageBox = document.getElementById('package_fee_container');
                const monthlyInput = document.getElementById('monthly_fee');
                const packageInput = document.getElementById('package_amount');

                if (isCollegeClass(cls)) {
                    monthlyBox.classList.add('d-none');
                    packageBox.classList.remove('d-none');
                    monthlyInput.removeAttribute('required');
                    packageInput.setAttribute('required', 'required');
                } else {
                    packageBox.classList.add('d-none');
                    monthlyBox.classList.remove('d-none');
                    packageInput.removeAttribute('required');
                    monthlyInput.setAttribute('required', 'required');

                    if (classFees[cls] !== undefined) {
                        monthlyInput.value = classFees[cls];
                    }
                }
            });
        }
    </script>
</body>
</html>
