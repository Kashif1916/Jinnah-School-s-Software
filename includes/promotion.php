<?php
/**
 * Student Promotion - Core Module
 * School Finance Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';

require_login();
if (!is_master() && !is_admission() && !is_finance()) {
    header('Location: ../login.php');
    exit();
}

$success = '';
$error = '';

$from_class_selected = '';
$from_section_selected = '';
$show_student_list = false;
$students_to_promote = [];

// Fetch class fee schedule
$class_fees = [];
$fee_res = $conn->query("SELECT class, fixed_monthly_fee FROM fee_schedule");
if ($fee_res) {
    while ($row = $fee_res->fetch_assoc()) {
        $class_fees[$row['class']] = floatval($row['fixed_monthly_fee']);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');

    // STEP 1: Load students for selected class and section
    if ($action == 'load_students') {
        $from_class_selected = sanitize_input($_POST['from_class'] ?? '');
        $from_section_selected = sanitize_input($_POST['from_section'] ?? '');

        if (!empty($from_class_selected) && !empty($from_section_selected)) {
            $query = "SELECT id, name, father_name, fixed_monthly_fee, concession_amount, is_package, package_amount FROM students WHERE class = ? AND section = ? AND status = 'active'";
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
    
    // STEP 2: Promote selected students
    elseif ($action == 'promote_selected') {
        $from_class_selected = sanitize_input($_POST['from_class'] ?? '');
        $from_section_selected = sanitize_input($_POST['from_section'] ?? '');
        
        $to_class = sanitize_input($_POST['to_class'] ?? '');
        $to_section = sanitize_input($_POST['to_section'] ?? '');
        $keep_concession = isset($_POST['keep_concession']) ? 1 : 0;
        $student_ids = $_POST['student_ids'] ?? [];

        $is_package = is_college_class($to_class) ? 1 : 0;
        if ($is_package) {
            $new_package_amount = floatval($_POST['new_package_amount'] ?? 0);
            $new_monthly_fee = 0;
        } else {
            $new_package_amount = 0;
            $new_monthly_fee = floatval($_POST['new_fixed_monthly_fee'] ?? 0);
        }

        $fee_to_validate = $is_package ? $new_package_amount : $new_monthly_fee;

        if (!empty($to_class) && !empty($to_section) && $fee_to_validate > 0 && !empty($student_ids)) {
            $conn->begin_transaction();
            try {
                $promoted_count = 0;
                
                $query = "UPDATE students SET class = ?, section = ?, fixed_monthly_fee = ?, package_amount = ?, is_package = ?, concession_amount = ?, concession_reason = ? WHERE id = ? AND status = 'active'";
                $stmt = $conn->prepare($query);

                foreach ($student_ids as $id) {
                    $student_id = intval($id);
                    $current_student = get_student($student_id);

                    if ($current_student) {
                        if ($keep_concession === 1) {
                            $st_concession = floatval($current_student['concession_amount'] ?? 0);
                            $st_concession_reason = $current_student['concession_reason'] ?? '';
                        } else {
                            $st_concession = 0;
                            $st_concession_reason = '';
                        }

                        $stmt->bind_param('ssddidsi', $to_class, $to_section, $new_monthly_fee, $new_package_amount, $is_package, $st_concession, $st_concession_reason, $student_id);
                        $stmt->execute();

                        if ($stmt->affected_rows >= 0) {
                            $promoted_count++;

                            // Schedule new fee record(s) for the promoted class session without altering past records
                            schedule_promotion_annual_fees($student_id, $new_monthly_fee, $st_concession, $new_package_amount, $is_package, $to_class);
                        }
                    }
                }
                $stmt->close();

                if ($promoted_count > 0) {
                    $conn->commit();
                    $success = $promoted_count . ' student(s) promoted to ' . $to_class . '-' . $to_section . ' successfully! ' . ($is_package ? 'Yearly Package of Rs. ' . number_format($new_package_amount, 2) . ' applied.' : 'Monthly fee of Rs. ' . number_format($new_monthly_fee, 2) . ' applied.');
                    $show_student_list = false;
                } else {
                    $conn->rollback();
                    $error = 'No students were updated. Please try again.';
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Database Error: ' . $e->getMessage();
            }
        } else {
            $error = 'All required fields and at least one student selection are required!';
            $show_student_list = true;
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
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <?php render_role_topbar_and_nav('Student Promotion', 'promotion'); ?>

            <div class="form-section">
                <div class="promotion-card">
                    <div class="card-header mb-4 h4 text-primary">
                        <i class="fas fa-arrow-up me-2"></i>Promote Class
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
                                    <i class="fas fa-users me-1"></i> Load Students
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if ($show_student_list && !empty($students_to_promote)): ?>
                        <hr class="my-4">
                        <form method="POST" id="promoteStudentsForm">
                            <input type="hidden" name="action" value="promote_selected">
                            <input type="hidden" name="from_class" value="<?php echo htmlspecialchars($from_class_selected); ?>">
                            <input type="hidden" name="from_section" value="<?php echo htmlspecialchars($from_section_selected); ?>">
                            
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
                                <div class="col-md-4" id="monthly_fee_container">
                                    <label for="new_fixed_monthly_fee" class="form-label">New Fixed Monthly Fee *</label>
                                    <input type="number" id="new_fixed_monthly_fee" name="new_fixed_monthly_fee" class="form-control" step="0.01" min="0" required readonly>
                                </div>
                                <div class="col-md-4 d-none" id="package_fee_container">
                                    <label for="new_package_amount" class="form-label">Yearly Package Amount (Rs.) *</label>
                                    <input type="number" id="new_package_amount" name="new_package_amount" class="form-control" step="0.01" min="0" placeholder="Enter Total Package Fee" readonly>
                                    <small class="text-muted"><i class="fas fa-info-circle"></i> Yearly Package scheduled as a single fee (supports partial payments)</small>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="form-check form-switch card p-3 bg-light border-0">
                                        <input class="form-check-input ms-0 me-3" type="checkbox" name="keep_concession" id="keep_concession" value="1" checked style="width: 40px; height: 20px;">
                                        <label class="form-check-label fw-bold text-dark" for="keep_concession">
                                            <i class="fas fa-percent me-1 text-success"></i> With Same Concession (Keep existing student concessions)
                                        </label>
                                        <small class="text-muted ms-5">If checked, each student's current concession amount will be retained upon promotion. If unchecked, concession will be reset to Rs. 0.</small>
                                    </div>
                                </div>
                            </div>

                            <h5 class="mb-3">Select Students to Promote (<?php echo count($students_to_promote); ?> found)</h5>
                            <div class="table-responsive mb-4">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">
                                                <input type="checkbox" id="selectAllStudents" class="form-check-input" checked>
                                            </th>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Father Name</th>
                                            <th>Current Fee / Package</th>
                                            <th>Concession</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students_to_promote as $student): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" class="form-check-input student-checkbox" checked>
                                                </td>
                                                <td><?php echo $student['id']; ?></td>
                                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                                <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                                                <td>
                                                    <?php if (!empty($student['is_package']) || floatval($student['package_amount'] ?? 0) > 0): ?>
                                                        <strong class="text-primary"><?php echo format_currency($student['package_amount']); ?></strong> <span class="badge bg-primary">Pkg</span>
                                                    <?php else: ?>
                                                        <?php echo format_currency($student['fixed_monthly_fee']); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo format_currency($student['concession_amount']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn-primary btn-lg" onclick="return confirm('Are you sure you want to promote the selected students? This action will update their class, section, and fee structure for the next academic year.')">
                                    <i class="fas fa-check me-1"></i> Promote Selected Students
                                </button>
                            </div>
                        </form>
                    <?php elseif ($show_student_list && empty($students_to_promote)): ?>
                        <div class="alert alert-info mt-4">
                            <i class="fas fa-info-circle me-1"></i> No active students found in <?php echo htmlspecialchars($from_class_selected) . '-' . htmlspecialchars($from_section_selected); ?>.
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="info-box">
                    <h5><i class="fas fa-info-circle"></i> How Promotion Works</h5>
                    <ul>
                        <li>Select the current class and section and click "Load Students".</li>
                        <li>Select the target class and section.</li>
                        <li><strong>For Nursery to 10th:</strong> Standard monthly fee structure from Fee Schedule is applied.</li>
                        <li><strong>For 11th, 12th & Passed-12th:</strong> Automatically switches to Yearly Package Mode to schedule the package fee (supports partial / flexible payments).</li>
                        <li><strong>With Same Concession:</strong> Check this option to preserve existing student concessions when promoting to the next class.</li>
                        <li>Click "Promote Selected Students" to update their records and schedule fees for the upcoming academic year.</li>
                    </ul>
                </div>
            </div>
        </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const classFees = <?php echo json_encode($class_fees); ?>;
        const collegeClasses = ['11', '12', 'passed-12', '11th', '12th', 'f.sc', 'fa', 'ics', 'i.com'];

        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAllStudents');
            const studentCheckboxes = document.querySelectorAll('.student-checkbox');
            const toClassSelect = document.getElementById('to_class');
            const monthlyContainer = document.getElementById('monthly_fee_container');
            const packageContainer = document.getElementById('package_fee_container');
            const newFeeInput = document.getElementById('new_fixed_monthly_fee');
            const newPackageInput = document.getElementById('new_package_amount');
            const calcMonthlySpan = document.getElementById('calculated_monthly');

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    studentCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            }

            function isCollegeClass(cls) {
                if (!cls) return false;
                return collegeClasses.includes(cls.trim().toLowerCase());
            }

            function getFeeForClass(cls) {
                if (!cls) return '';
                if (classFees[cls] !== undefined) return classFees[cls];
                const lower = cls.trim().toLowerCase();
                for (const k in classFees) {
                    if (k.trim().toLowerCase() === lower) {
                        return classFees[k];
                    }
                }
                return '';
            }

            if (toClassSelect) {
                toClassSelect.addEventListener('change', function() {
                    const selectedClass = this.value;
                    if (isCollegeClass(selectedClass)) {
                        monthlyContainer.classList.add('d-none');
                        packageContainer.classList.remove('d-none');
                        newFeeInput.removeAttribute('required');
                        newPackageInput.setAttribute('required', 'required');
                        
                        const scheduledFee = getFeeForClass(selectedClass);
                        newPackageInput.value = (scheduledFee !== '' && scheduledFee > 0) ? scheduledFee : '';
                    } else {
                        packageContainer.classList.add('d-none');
                        monthlyContainer.classList.remove('d-none');
                        newPackageInput.removeAttribute('required');
                        newFeeInput.setAttribute('required', 'required');
                        
                        const scheduledFee = getFeeForClass(selectedClass);
                        newFeeInput.value = (scheduledFee !== '' && scheduledFee > 0) ? scheduledFee : '';
                    }
                });
            }
        });
    </script>
</body>
</html>
