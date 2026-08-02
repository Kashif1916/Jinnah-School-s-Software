<?php
/**
 * Defaulter List - Teacher Module
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_teacher(); // Enforces Teacher permission

$class_filter = sanitize_input($_REQUEST['class'] ?? '');
$section_filter = sanitize_input($_REQUEST['section'] ?? '');
$name_filter = sanitize_input($_REQUEST['name'] ?? '');
$months_filter = $_REQUEST['months'] ?? [];

// Check if user has applied any filter
$is_filtered = (!empty($class_filter) || !empty($section_filter) || !empty($name_filter) || !empty($months_filter));

// Pagination Configuration (Only applies when NO filter is used)
$limit = 20; // Default items per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Get defaulters
$defaulters = get_defaulters($class_filter, $section_filter, $months_filter, $name_filter);
$all_defaulter_list = [];
if ($defaulters) {
    $all_defaulter_list = $defaulters->fetch_all(MYSQLI_ASSOC);
}
$total_defaulters = count($all_defaulter_list);
$total_pages = ceil($total_defaulters / $limit);

// ONLY APPLY LIMIT 20 IF NO FILTER IS ACTIVE
if (!$is_filtered) {
    $defaulter_list = array_slice($all_defaulter_list, $offset, $limit);
} else {
    $defaulter_list = $all_defaulter_list;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Pending List - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">

    <style>
        .months-checkbox-container {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 10px;
            max-height: 150px;
            overflow-y: auto;
            background-color: #fff;
        }
        .month-tick-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 6px;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 2px;
        }
        .month-tick-item:hover {
            background-color: #f8f9fa;
        }
        .month-tick-item input {
            cursor: pointer;
            width: 16px;
            height: 16px;
        }
    </style>
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <?php echo render_system_logo('topbar-logo'); ?>
                    <div class="panel-brand">
                        <h2>Teacher Dashboard</h2>
                        <span>Pending Fee List</span>
                    </div>
                </div>
                <div class="topbar-right">
                    <span class="user-info">
                        <i class="fas fa-chalkboard-teacher"></i> <?php echo get_username(); ?> (Teacher)
                    </span>
                    <a href="../logout.php" class="btn-secondary">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <div class="content">
                <div class="module-nav-panel">
                    <div class="module-nav-row">
                        <a href="defaulter_list.php" class="module-nav-btn active">
                            <i class="fas fa-list"></i> Pending List
                        </a>
                        <a href="student_record.php" class="module-nav-btn">
                            <i class="fas fa-address-book"></i> Student Record
                        </a>
                         <a href="drop_student.php" class="module-nav-btn ">
                            <i class="fas fa-trash text-success"></i> Drop Student
                        </a>
                        <a href="../help.php" class="module-nav-btn">
                            <i class="fas fa-question-circle text-success"></i> Help & About
                        </a>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="filter-section mb-4">
                        <h4 class="mb-3"><i class="fas fa-filter text-success me-2"></i>Filter Pending List</h4>
                        <form method="POST" class="filter-form">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="name">Student Name</label>
                                    <input type="text" id="name" name="name" class="form-control" placeholder="Search by name..." value="<?php echo htmlspecialchars($name_filter); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="class">Class</label>
                                    <select id="class" name="class" class="form-control">
                                        <option value="">All Classes</option>
                                        <?php foreach ($CLASSES as $cls): ?>
                                            <option value="<?php echo $cls; ?>" 
                                                <?php echo ($class_filter === $cls) ? 'selected' : ''; ?>>
                                                <?php echo $cls; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="section">Section</label>
                                    <select id="section" name="section" class="form-control">
                                        <option value="">All Sections</option>
                                        <?php foreach ($SECTIONS as $sec): ?>
                                            <option value="<?php echo $sec; ?>" 
                                                <?php echo ($section_filter === $sec) ? 'selected' : ''; ?>>
                                                <?php echo $sec; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Select Month(s)</label>
                                    <div class="months-checkbox-container">
                                        <?php
                                         $start_date = new DateTime('first day of this month');
                                         for ($i = 0; $i < 12; $i++) {
                                             $date = clone $start_date;
                                             $date->modify("-$i month");
                                             
                                             $month_name = $date->format('M'); 
                                             $year_val   = $date->format('Y'); 
                                             $month_str  = $month_name . '-' . $year_val;

                                             $checked = (in_array($month_str, (array)$months_filter)) ? 'checked' : '';
                                             ?>
                                             <label class="month-tick-item">
                                                 <input type="checkbox" name="months[]" value="<?php echo $month_str; ?>" <?php echo $checked; ?>>
                                                 <?php echo $month_str; ?>
                                             </label>
                                             <?php 
                                         } 
                                        ?>
                                        <label class="month-tick-item">
                                            <input type="checkbox" name="months[]" value="Admission" <?php echo (in_array('Admission', (array)$months_filter)) ? 'checked' : ''; ?>>
                                            Admission
                                        </label>
                                        <label class="month-tick-item">
                                            <input type="checkbox" name="months[]" value="Pre_Year" <?php echo (in_array('Pre_Year', (array)$months_filter) || in_array('Prev-Year', (array)$months_filter) || in_array('Pre-Year', (array)$months_filter)) ? 'checked' : ''; ?>>
                                            Pre_Year
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="form-group d-flex align-items-end">
                                    <button type="submit" class="btn-primary w-100" style="height: 45px;">
                                        <i class="fas fa-filter"></i> Apply Filters
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <div class="table-section mt-4">
                        <form method="POST" action="../master/export_defaulter_challan.php" target="_blank" id="defaulterChallanForm">
                            <input type="hidden" name="class" value="<?php echo htmlspecialchars($class_filter); ?>">
                            <input type="hidden" name="section" value="<?php echo htmlspecialchars($section_filter); ?>">
                            <input type="hidden" name="name" value="<?php echo htmlspecialchars($name_filter); ?>">
                            <?php foreach ((array)$months_filter as $m_f): ?>
                                <input type="hidden" name="months[]" value="<?php echo htmlspecialchars($m_f); ?>">
                            <?php endforeach; ?>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="mb-0"><i class="fas fa-list-ol text-success me-2"></i>Pending Fees (<?php echo $total_defaulters; ?>)</h4>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-file-pdf me-1"></i> Export Selected Challans (PDF)
                                    </button>
                                    <?php 
                                        $query_data = ['class' => $class_filter, 'section' => $section_filter, 'name' => $name_filter, 'months' => $months_filter];
                                        $report_url = "../master/defaulter_report.php?" . http_build_query($query_data);
                                    ?>
                                    <a href="<?php echo $report_url; ?>" class="btn-primary" target="_blank">
                                        <i class="fas fa-list-alt me-1"></i> Export Summary PDF
                                    </a>
                                </div>
                            </div>
                            
                            <?php if (count($defaulter_list) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th style="width: 40px;">
                                                    <input type="checkbox" id="selectAllStudents" title="Select All">
                                                </th>
                                                <th>Name</th>
                                                <th>Father Name</th>
                                                <th>Contact Number(s)</th>
                                                <th>Class-Sec</th>
                                                <th>Pending Month(s)</th>
                                                <th>Monthly Fee</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($defaulter_list as $defaulter): ?>
                                                <?php 
                                                $single_challan_url = "../master/export_defaulter_challan.php?student_id=" . $defaulter['id'] . "&" . http_build_query(['class' => $class_filter, 'section' => $section_filter, 'name' => $name_filter, 'months' => $months_filter]);
                                                ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="student_ids[]" value="<?php echo $defaulter['id']; ?>" class="student-cb">
                                                    </td>
                                                    <td><strong><?php echo htmlspecialchars($defaulter['name']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($defaulter['father_name']); ?></td>
                                                    <td>
                                                        <?php echo !empty($defaulter['contact_number']) ? '<i class="fas fa-phone text-muted me-1"></i>' . htmlspecialchars($defaulter['contact_number']) . '<br>' : ''; ?>
                                                        <?php echo !empty($defaulter['whatsapp_number']) ? '<i class="fab fa-whatsapp text-success me-1"></i>' . htmlspecialchars($defaulter['whatsapp_number']) : ''; ?>
                                                    </td>
                                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($defaulter['class']) . '-' . htmlspecialchars($defaulter['section']); ?></span></td>
                                                    <td style="max-width: 250px; font-size: 11px;">
                                                        <span class="text-danger fw-bold">(<?php echo htmlspecialchars($defaulter['pending_count']); ?> Month)</span><br>
                                                        <?php echo htmlspecialchars(str_replace(',', ', ', $defaulter['pending_months'])); ?>
                                                    </td>
                                                    <td><strong><?php echo format_currency($defaulter['monthly_fee']); ?></strong></td>
                                                    <td>
                                                        <a href="<?php echo $single_challan_url; ?>" class="btn btn-sm btn-outline-success text-nowrap" target="_blank">
                                                            <i class="fas fa-file-pdf"></i> Print Challan
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- PAGINATION BUTTONS -->
                                <?php render_pagination($page, $total_pages, '', $is_filtered); ?>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> No pending fees found with the selected filters!
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var selectAll = document.getElementById('selectAllStudents');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                var checkboxes = document.querySelectorAll('.student-cb');
                checkboxes.forEach(function(cb) {
                    cb.checked = selectAll.checked;
                });
            });
        }
    });
    </script>
</body>
</html>
