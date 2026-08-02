<?php
/**
 * Edit Student - Finance Module (Restricted & Secure)
 */
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_finance();

if (!has_edit_access()) {
    header('Location: student_record.php');
    exit();
}

$error = '';
$success = '';
$student = null;
$search_results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action: Search Students Securely
    if ($action === 'search') {
        $search_name = trim($_POST['search_name'] ?? '');
        $search_class = trim($_POST['search_class'] ?? '');
        
        if (!empty($search_name) || !empty($search_class)) {
            $query = "SELECT * FROM students WHERE status = 'active'";
            $params = [];
            $types = "";

            if (!empty($search_name)) {
                $query .= " AND name LIKE ?";
                $params[] = "%" . $search_name . "%";
                $types .= "s";
            }
            if (!empty($search_class)) {
                $query .= " AND class = ?";
                $params[] = $search_class;
                $types .= "s";
            }

            $stmt_search = $conn->prepare($query);
            if (!empty($params)) {
                $stmt_search->bind_param($types, ...$params);
            }
            $stmt_search->execute();
            $search_results = $stmt_search->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_search->close();
        }
    } 
    // Action: Update Student Record & Concessions
    elseif ($action === 'update') {
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
            $net_fee = max(0, $fixed_monthly_fee - $concession_amount);

            $query = "UPDATE students SET name = ?, father_name = ?, class = ?, section = ?, contact_number = ?, contact_number2 = ?, whatsapp_number = ?, concession_amount = ?, concession_reason = ?, monthly_fee = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssssssssdsi', $name, $father_name, $class, $section, $contact_number, $contact_number2, $whatsapp_number, $concession_amount, $concession_reason, $net_fee, $student_id);
            
            if ($stmt->execute()) {
                // Update fee_records for selected unpaid previous months
                if (!empty($selected_months)) {
                    foreach ($selected_months as $m_val) {
                        $stmt_upd = $conn->prepare("UPDATE fee_records SET amount = ? WHERE student_id = ? AND month = ? AND status = 'unpaid'");
                        $stmt_upd->bind_param('dis', $net_fee, $student_id, $m_val);
                        $stmt_upd->execute();
                        $stmt_upd->close();
                    }
                }

                // Automatically sync future unpaid records with new fee
                sync_unpaid_fee_amounts($student_id, $net_fee);
                auto_generate_fee_buffer($student_id, $net_fee);

                $success = 'Student info and concession updated successfully!';
                $student = get_student($student_id);
            } else {
                $error = 'Error updating student.';
            }
            $stmt->close();
        } else {
            $error = 'Name and Father Name are required fields!';
            $student = get_student($student_id);
        }
    }
}

if (isset($_GET['id'])) {
    $student = get_student(intval($_GET['id']));
}

// Fetch ONLY past unpaid months (strictly before current month)
$previous_unpaid_months = [];
if ($student) {
    $stmt_prev = $conn->prepare("
        SELECT month 
        FROM fee_records 
        WHERE student_id = ? 
          AND status = 'unpaid' 
          AND (
              month IN ('Admission', 'Pre_Year', 'Prev-Year', 'Pre-Year') 
              OR STR_TO_DATE(CONCAT('01-', month), '%d-%b-%Y') < DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
          ) 
        ORDER BY 
            CASE 
                WHEN month = 'Admission' THEN 1 
                WHEN month IN ('Pre_Year', 'Prev-Year', 'Pre-Year') THEN 2 
                ELSE 3 
            END, 
            STR_TO_DATE(CONCAT('01-', month), '%d-%b-%Y') ASC
    ");
    $stmt_prev->bind_param('i', $student['id']);
    $stmt_prev->execute();
    $res_prev = $stmt_prev->get_result();
    while ($row = $res_prev->fetch_assoc()) {
        $previous_unpaid_months[] = $row['month'];
    }
    $stmt_prev->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student - Finance</title>
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
                        <span>Finance / Clerk Panel</span>
                    </div>
                </div>
                <div class="topbar-right">
                    <span class="user-info">
                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars(get_username(), ENT_QUOTES, 'UTF-8'); ?>
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
                        <a href="add_student.php" class="module-nav-btn ">
                            <i class="fas fa-list"></i> Add Student
                        </a>
                        <a href="student_record.php" class="module-nav-btn active">
                            <i class="fas fa-address-book"></i> Student Record
                        </a>
                        <a href="fee_payment.php" class="module-nav-btn">
                            <i class="fas fa-money-bill-wave"></i> Fee Payment
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
                         <a href="drop_student.php" class="module-nav-btn ">
                            <i class="fas fa-trash text-success"></i> Drop Student
                        </a>
                        <a href="account_close.php" class="module-nav-btn">
                            <i class="fas fa-lock"></i> Close Account
                        </a>
                        <a href="../help.php" class="module-nav-btn">
                            <i class="fas fa-question-circle text-success"></i> Help & About
                        </a>
                    </div>
                </div>

                <div class="form-section">
                    <?php if($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($student): ?>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student['id'], ENT_QUOTES, 'UTF-8'); ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Student Name *</label>
                                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($student['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Father's Name *</label>
                                    <input type="text" name="father_name" class="form-control" value="<?php echo htmlspecialchars($student['father_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Class</label>
                                    <select name="class" class="form-select">
                                        <?php 
                                        if (isset($CLASSES) && is_array($CLASSES)) {
                                            foreach($CLASSES as $c) {
                                                $selected = ($student['class'] == $c) ? 'selected' : '';
                                                echo "<option value='".htmlspecialchars($c, ENT_QUOTES, 'UTF-8')."' $selected>$c</option>";
                                            }
                                        } 
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Section</label>
                                    <select name="section" class="form-select">
                                        <?php 
                                        if (isset($SECTIONS) && is_array($SECTIONS)) {
                                            foreach($SECTIONS as $s) {
                                                $selected = ($student['section'] == $s) ? 'selected' : '';
                                                echo "<option value='".htmlspecialchars($s, ENT_QUOTES, 'UTF-8')."' $selected>$s</option>";
                                            }
                                        } 
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Fixed Monthly Fee</label>
                                    <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($student['fixed_monthly_fee'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Contact 1</label>
                                    <input type="text" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($student['contact_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Contact 2</label>
                                    <input type="text" name="contact_number2" class="form-control" value="<?php echo htmlspecialchars($student['contact_number2'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">WhatsApp</label>
                                    <input type="text" name="whatsapp_number" class="form-control" value="<?php echo htmlspecialchars($student['whatsapp_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="concession_amount">Concession Amount</label>
                                    <input type="number" id="concession_amount" name="concession_amount" class="form-control" value="<?php echo htmlspecialchars($student['concession_amount'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>" step="0.01" min="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="concession_reason">Concession Reason</label>
                                    <select id="concession_reason" name="concession_reason" class="form-select">
                                        <?php 
                                        $reasons = ['', 'Sibling', 'Hafiz', 'Orphan', 'S.C', 'EMP'];
                                        $current_reason = $student['concession_reason'] ?? '';
                                        foreach ($reasons as $r) {
                                            $label = ($r === '') ? 'None' : $r;
                                            $selected = ($current_reason === $r) ? 'selected' : '';
                                            echo "<option value='".htmlspecialchars($r, ENT_QUOTES, 'UTF-8')."' $selected>$label</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label fw-bold"><i class="fas fa-calendar-check me-1"></i> Apply Concession to Past Unpaid Month(s)</label>
                                    
                                    <?php if (!empty($previous_unpaid_months)): ?>
                                        <div class="months-checkbox-container p-3 border rounded bg-light" style="max-height: 200px; overflow-y: auto;">
                                            <div class="row">
                                                <?php foreach ($previous_unpaid_months as $m_name): 
                                                    $m_name_clean = htmlspecialchars($m_name, ENT_QUOTES, 'UTF-8');
                                                ?>
                                                    <div class="col-md-3 col-6 mb-2">
                                                        <div class="form-check">
                                                            <input class="form-check-input concession-month-cb" type="checkbox" name="concession_months[]" value="<?php echo $m_name_clean; ?>" id="m_cb_<?php echo $m_name_clean; ?>">
                                                            <label class="form-check-label" for="m_cb_<?php echo $m_name_clean; ?>">
                                                                <?php echo $m_name_clean; ?>
                                                                <span class="badge bg-danger ms-1">Unpaid</span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <small class="text-muted"><i class="fas fa-info-circle"></i> Select any previous unpaid month if you want to apply this new concession amount to it.</small>
                                    <?php else: ?>
                                        <div class="alert alert-secondary mb-0">No previous unpaid months found for this student.</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">Please select a student from the record list to edit.</div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>