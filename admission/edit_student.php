<?php
/**
 * Edit Student - Admission Module (Restricted)
 */
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_admission();

if (!has_edit_access()) {
    header('Location: student_record.php');
    exit();
}

$error = '';
$success = '';
$student = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'update') {
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

        // Fetch fixed_monthly_fee from current student info
        $current_student = get_student($student_id);
        $fixed_monthly_fee = floatval($current_student['fixed_monthly_fee'] ?? 0);

        if (!empty($name) && !empty($father_name)) {
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

                $query = "UPDATE students SET name = ?, father_name = ?, class = ?, section = ?, contact_number = ?, contact_number2 = ?, whatsapp_number = ?, concession_amount = ?, concession_reason = ?, monthly_fee = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ssssssssdsi', $name, $father_name, $class, $section, $contact_number, $contact_number2, $whatsapp_number, $concession_amount, $concession_reason, $net_fee, $student_id);
                
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
                    $error = 'Error updating student.';
                }
                $stmt->close();
            }
        }
    }
}

if (isset($_GET['id'])) {
    $student = get_student(intval($_GET['id']));
}
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
                    <?php echo render_system_logo('topbar-logo'); ?>
                    <div class="panel-brand">
                        <h2>Edit Student</h2>
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
                        <a href="student_record.php" class="module-nav-btn active">
                            <i class="fas fa-address-book"></i> Student Record
                        </a>
                        <a href="defaulter_list.php" class="module-nav-btn">
                            <i class="fas fa-list"></i> Pending List
                        </a>
                        <a href="promotion.php" class="module-nav-btn ">
                            <i class="fas fa-arrow-up"></i> Promotion
                        </a>
                        <a href="drop_student.php" class="module-nav-btn">
                            <i class="fas fa-trash"></i> Drop Student
                        </a>
                        <a href="../help.php" class="module-nav-btn">
                            <i class="fas fa-question-circle text-success"></i> Help & About
                        </a>
                    </div>
                </div>

                <div class="form-section">
                    <?php if($success) echo "<div class='alert alert-success'>$success</div>"; ?>
                    <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>

                    <?php if ($student): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Student Name *</label>
                                    <input type="text" name="name" class="form-control" value="<?php echo $student['name']; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Father's Name *</label>
                                    <input type="text" name="father_name" class="form-control" value="<?php echo $student['father_name']; ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Class</label>
                                    <select name="class" class="form-select">
                                        <?php foreach($CLASSES as $c) echo "<option ".($student['class']==$c?'selected':'').">$c</option>"; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Section</label>
                                    <select name="section" class="form-select">
                                        <?php foreach($SECTIONS as $s) echo "<option ".($student['section']==$s?'selected':'').">$s</option>"; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Fixed Monthly Fee</label>
                                    <input type="text" class="form-control bg-light" value="<?php echo $student['fixed_monthly_fee']; ?>" readonly>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Contact 1</label>
                                    <input type="text" name="contact_number" class="form-control" value="<?php echo $student['contact_number']; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Contact 2</label>
                                    <input type="text" name="contact_number2" class="form-control" value="<?php echo $student['contact_number2']; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">WhatsApp</label>
                                    <input type="text" name="whatsapp_number" class="form-control" value="<?php echo $student['whatsapp_number']; ?>">
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
                                            // Fetch ONLY unpaid months from database for this student
                                            $stmt_unpaid = $conn->prepare("SELECT DISTINCT month FROM fee_records WHERE student_id = ? AND LOWER(status) = 'unpaid' ORDER BY id ASC");
                                            $stmt_unpaid->bind_param('i', $student['id']);
                                            $stmt_unpaid->execute();
                                            $res_unpaid = $stmt_unpaid->get_result();

                                            $current_first_day = date('Y-m-01'); // Start of current month
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

                            <div class="form-actions">
                                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p>Please select a student from the record list to edit.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>