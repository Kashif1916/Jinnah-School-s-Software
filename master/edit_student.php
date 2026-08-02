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
        $fixed_monthly_fee = floatval($_POST['monthly_fee'] ?? 0); // Note: Form input is named 'monthly_fee' but contains fixed_monthly_fee
        $contact_number = sanitize_input($_POST['contact_number'] ?? '');
        $contact_number2 = sanitize_input($_POST['contact_number2'] ?? '');
        $whatsapp_number = sanitize_input($_POST['whatsapp_number'] ?? '');
        $concession_amount = floatval($_POST['concession_amount'] ?? 0);
        $concession_reason = sanitize_input($_POST['concession_reason'] ?? '');
        $selected_months = $_POST['concession_months'] ?? [];
        
        if (!empty($name) && !empty($father_name) && $fixed_monthly_fee > 0) {
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
                          fixed_monthly_fee = ?, monthly_fee = ?, contact_number = ?, contact_number2 = ?, whatsapp_number = ?, concession_amount = ?, concession_reason = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ssssddsssdsi', $name, $father_name, $class, $section, $fixed_monthly_fee, $net_fee, $contact_number, $contact_number2, $whatsapp_number, $concession_amount, $concession_reason, $student_id);
                
                if ($stmt->execute()) {
                    // Update fee_records for selected unpaid previous months
                    foreach ($selected_months as $m_val) {
                        $stmt_m = $conn->prepare("SELECT id, status FROM fee_records WHERE student_id = ? AND month = ?");
                        $stmt_m->bind_param('is', $student_id, $m_val);
                        $stmt_m->execute();
                        $rec_m = $stmt_m->get_result()->fetch_assoc();
                        $stmt_m->close();
                        
                        if ($rec_m) {
                            if (strtolower($rec_m['status']) === 'unpaid') {
                                $stmt_upd = $conn->prepare("UPDATE fee_records SET amount = ? WHERE id = ?");
                                $stmt_upd->bind_param('di', $net_fee, $rec_m['id']);
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

                    // Automatically sync current & future unpaid records with new fee
                    sync_unpaid_fee_amounts($student_id, $net_fee);
                    auto_generate_fee_buffer($student_id, $net_fee);
                    
                    $success = 'Student info and concession updated successfully!';
                    $student = get_student($student_id);
                } else {
                    $error = 'Error updating student: ' . $stmt->error;
                }
                $stmt->close();
            }
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
        <main class="main-content">
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <a href="dashboard.php"><?php echo render_system_logo('topbar-logo'); ?></a>
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
                        <a href="paid_students.php" class="module-nav-btn">
                            <i class="fas fa-check-circle text-success"></i> Paid Students
                        </a>
                        <a href="payment_analytics.php" class="module-nav-btn">
                            <i class="fas fa-chart-line"></i> Analytics
                        </a>
                        <a href="receipt_analysis.php" class="module-nav-btn">
                            <i class="fas fa-receipt"></i> Receipt Analysis
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
                        <a href="delete_student.php" class="module-nav-btn ">
                            <i class="fas fa-user-minus text-success"></i> Delete Student
                        </a>
                        <a href="users.php" class="module-nav-btn">
                            <i class="fas fa-users-cog"></i> Users
                        </a>
                        <a href="receipt_note.php" class="module-nav-btn">
                            <i class="fas fa-sticky-note"></i> Custom Note
                        </a>
                        <a href="../help.php" class="module-nav-btn">
                            <i class="fas fa-question-circle text-success"></i> Help & About
                        </a>
                    </div>
                </div>

                <div class="form-section">
                    <?php if (!empty($success)) echo "<div class='alert alert-success alert-dismissible fade show'><i class='fas fa-check-circle'></i> $success<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>"; ?>
                    <?php if (!empty($error)) echo "<div class='alert alert-danger alert-dismissible fade show'><i class='fas fa-exclamation-circle'></i> $error<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>"; ?>
                    
                    <?php if ($student === null): ?>
                        <div class="search-section">
                            <h4 class="mb-4">Search Student to Edit</h4>
                            <form method="POST" class="search-form">
                                <input type="hidden" name="action" value="search">
                                <div class="row mb-3 align-items-end">
                                    <div class="col-md-3">
                                        <label for="search_name" class="form-label">Student Name</label>
                                        <input type="text" id="search_name" name="search_name" class="form-control" placeholder="Enter student name (optional)">
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="search_class" class="form-label">Class</label>
                                        <select id="search_class" name="search_class" class="form-select">
                                            <option value="">-- Select Class --</option>
                                            <?php foreach ($CLASSES as $cls): ?>
                                                <option value="<?php echo $cls; ?>"><?php echo $cls; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="search_section" class="form-label">Section</label>
                                        <select id="search_section" name="search_section" class="form-select">
                                            <option value="">-- Select Section --</option>
                                            <?php foreach ($SECTIONS as $sec): ?>
                                                <option value="<?php echo $sec; ?>"><?php echo $sec; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <button type="submit" class="btn-primary w-100">
                                            <i class="fas fa-search"></i> Search
                                        </button>
                                    </div>
                                </div>
                            </form>
                            
                            <?php if (count($search_results) > 0): ?>
                                <div class="search-results mt-4">
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
                                <div class="alert alert-info mt-3">No students found!</div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="edit-form">
                            <h4 class="mb-4">Edit Student: <?php echo $student['name']; ?></h4>
                            <form method="POST" id="editStudentForm">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="name" class="form-label">Student Name *</label>
                                        <input type="text" id="name" name="name" class="form-control" value="<?php echo $student['name']; ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="father_name" class="form-label">Father's Name *</label>
                                        <input type="text" id="father_name" name="father_name" class="form-control" value="<?php echo $student['father_name']; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label for="class" class="form-label">Class *</label>
                                        <select id="class" name="class" class="form-select" required>
                                            <?php foreach ($CLASSES as $cls): ?>
                                                <option value="<?php echo $cls; ?>" <?php echo ($cls === $student['class']) ? 'selected' : ''; ?>>
                                                    <?php echo $cls; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="section" class="form-label">Section *</label>
                                        <select id="section" name="section" class="form-select" required>
                                            <?php foreach ($SECTIONS as $sec): ?>
                                                <option value="<?php echo $sec; ?>" <?php echo ($sec === $student['section']) ? 'selected' : ''; ?>>
                                                    <?php echo $sec; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="monthly_fee" class="form-label">Fixed Monthly Fee </label>
                                        <input type="number" id="monthly_fee" name="monthly_fee" class="form-control" value="<?php echo $student['fixed_monthly_fee']; ?>" required step="0.01" min="0">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="contact_number" class="form-label">Contact Number 1</label>
                                        <input type="tel" id="contact_number" name="contact_number" class="form-control" value="<?php echo $student['contact_number'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="contact_number2" class="form-label">Contact Number 2</label>
                                        <input type="tel" id="contact_number2" name="contact_number2" class="form-control" value="<?php echo $student['contact_number2'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="whatsapp_number" class="form-label">WhatsApp Number</label>
                                        <input type="tel" id="whatsapp_number" name="whatsapp_number" class="form-control" value="<?php echo $student['whatsapp_number'] ?? ''; ?>">
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
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold"><i class="fas fa-calendar-check me-1"></i> Select Previous Unpaid Month(s) to Apply Concession</label>
                                        <div class="months-checkbox-container p-3 border rounded bg-light" style="max-height: 180px; overflow-y: auto;">
                                            <div class="row">
                                                <?php 
                                                // Fetch ONLY unpaid months from database
                                                $stmt_unpaid = $conn->prepare("SELECT DISTINCT month FROM fee_records WHERE student_id = ? AND LOWER(status) = 'unpaid' ORDER BY id ASC");
                                                $stmt_unpaid->bind_param('i', $student['id']);
                                                $stmt_unpaid->execute();
                                                $res_unpaid = $stmt_unpaid->get_result();

                                                $current_first_day = date('Y-m-01'); // Current month starting date (e.g., 2026-07-01)
                                                $previous_unpaid_found = false;

                                                if ($res_unpaid && $res_unpaid->num_rows > 0):
                                                    while ($row_u = $res_unpaid->fetch_assoc()):
                                                        $m_name = $row_u['month'];
                                                        $m_time = strtotime($m_name);
                                                        
                                                        // Filter: Show only months strictly BEFORE current month
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
</body>
</html>