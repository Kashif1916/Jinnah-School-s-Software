<?php
/**
 * Edit Student - Finance Module (Restricted)
 */
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_finance();

$error = '';
$success = '';
$student = null;
$search_results = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'search') {
        $search_name = sanitize_input($_POST['search_name'] ?? '');
        $search_class = sanitize_input($_POST['search_class'] ?? '');
        
        if (!empty($search_name) || !empty($search_class)) {
            $query = "SELECT * FROM students WHERE status = 'active'";
            if (!empty($search_name)) $query .= " AND name LIKE '%$search_name%'";
            if (!empty($search_class)) $query .= " AND class = '$search_class'";
            
            $search_results = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'update') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $name = sanitize_input($_POST['name'] ?? '');
        $father_name = sanitize_input($_POST['father_name'] ?? '');
        $class = sanitize_input($_POST['class'] ?? '');
        $section = sanitize_input($_POST['section'] ?? '');
        $contact_number = sanitize_input($_POST['contact_number'] ?? '');
        $contact_number2 = sanitize_input($_POST['contact_number2'] ?? '');
        $whatsapp_number = sanitize_input($_POST['whatsapp_number'] ?? '');

        if (!empty($name) && !empty($father_name)) {
            $query = "UPDATE students SET name = ?, father_name = ?, class = ?, section = ?, contact_number = ?, contact_number2 = ?, whatsapp_number = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('sssssssi', $name, $father_name, $class, $section, $contact_number, $contact_number2, $whatsapp_number, $student_id);
            
            if ($stmt->execute()) {
                $success = 'Student info updated successfully! (Financial fields remained locked)';
                $student = get_student($student_id);
            } else {
                $error = 'Error updating student.';
            }
            $stmt->close();
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
    <title>Edit Student - Finance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <div class="topbar">
                <div class="topbar-left"><h2>Edit Student</h2><span>Finance Panel</span></div>
                <div class="topbar-right"><a href="../logout.php" class="btn-secondary">Logout</a></div>
            </div>
            <div class="content">
                <div class="module-nav-panel">
                    <div class="module-nav-row">
                        <a href="student_record.php" class="module-nav-btn"><i class="fas fa-arrow-left"></i> Back to Records</a>
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
                                    <label class="form-label">Concession Amount</label>
                                    <input type="text" class="form-control bg-light" value="<?php echo $student['concession_amount']; ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Concession Reason</label>
                                    <input type="text" class="form-control bg-light" value="<?php echo $student['concession_reason']; ?>" readonly>
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
</body>
</html>