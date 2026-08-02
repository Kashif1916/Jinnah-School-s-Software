<?php
/**
 * Paid Students List - Finance Module
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_finance();

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

// Get paid students
$paid_res = get_paid_students($class_filter, $section_filter, $months_filter, $name_filter);
$all_paid_list = [];
if ($paid_res) {
    $all_paid_list = $paid_res->fetch_all(MYSQLI_ASSOC);
}
$total_paid_students = count($all_paid_list);
$total_pages = ceil($total_paid_students / $limit);

// ONLY APPLY LIMIT 20 IF NO FILTER IS ACTIVE
if (!$is_filtered) {
    $paid_list = array_slice($all_paid_list, $offset, $limit);
} else {
    $paid_list = $all_paid_list;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paid Students List - <?php echo SITE_NAME; ?></title>
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
        @media print {
            .topbar, .module-nav-panel, .filter-section, .btn, .pagination, .no-print {
                display: none !important;
            }
            .wrapper, .main-content, .content, .table-section {
                padding: 0 !important;
                margin: 0 !important;
                background: white !important;
            }
            table {
                width: 100% !important;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <a href="dashboard.php"><?php echo render_system_logo('topbar-logo'); ?></a>
                    <div class="panel-brand">
                        <h2>Paid Students List</h2>
                        <span>Finance / Clerk Panel</span>
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
                            <i class="fas fa-list"></i> Add Student
                        </a>
                        <a href="student_record.php" class="module-nav-btn">
                            <i class="fas fa-address-book"></i> Student Record
                        </a>
                        <a href="fee_payment.php" class="module-nav-btn">
                            <i class="fas fa-money-bill-wave"></i> Fee Payment
                        </a>
                        <a href="defaulter_list.php" class="module-nav-btn">
                            <i class="fas fa-list"></i> Pending List
                        </a>
                        <a href="paid_students.php" class="module-nav-btn active">
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
                         <a href="drop_student.php" class="module-nav-btn">
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
                    <div class="filter-section">
                        <h4>Filter Paid Students</h4>
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
                                
                                <div class="form-group">
                                    <button type="submit" class="btn-primary" style="margin-top: 30px;">
                                        <i class="fas fa-filter"></i> Filter
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <div class="table-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0 text-success"><i class="fas fa-check-double me-2"></i>Paid Fee Records (<?php echo $total_paid_students; ?> Students)</h4>
                            
                        </div>
                        
                        <?php if (count($paid_list) > 0): ?>
                            <table class="table table-striped table-hover align-middle">
                                <thead class="table-success">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Father Name</th>
                                        <th>Contact Number(s)</th>
                                        <th>Class-Sec</th>
                                        <th>Paid Month(s)</th>
                                        <th>Monthly Fee</th>
                                        <th>Last Payment Date</th>
                                       
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paid_list as $student): ?>
                                        <tr>
                                            <td><strong><?php echo str_pad($student['id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                                            <td>
                                                <?php echo !empty($student['contact_number']) ? '<i class="fas fa-phone"></i> ' . htmlspecialchars($student['contact_number']) . '<br>' : ''; ?>
                                                <?php echo !empty($student['whatsapp_number']) ? '<i class="fab fa-whatsapp"></i> ' . htmlspecialchars($student['whatsapp_number']) : ''; ?>
                                            </td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($student['class']) . '-' . htmlspecialchars($student['section']); ?></span></td>
                                            <td style="max-width: 220px; font-size: 12px;">
                                                <strong class="text-success">(<?php echo htmlspecialchars($student['paid_count']); ?> Paid)</strong><br>
                                                <?php echo htmlspecialchars(str_replace(',', ', ', $student['paid_months'])); ?>
                                            </td>
                                            <td><?php echo format_currency($student['monthly_fee']); ?></td>
                                            <td>
                                                <?php 
                                                if (!empty($student['last_payment_date'])) {
                                                    echo date('d M Y, h:i A', strtotime($student['last_payment_date']));
                                                } else {
                                                    echo '<span class="text-muted">N/A</span>';
                                                }
                                                ?>
                                            </td>
                                            
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <!-- PAGINATION BUTTONS -->
                            <div class="no-print">
                                <?php render_pagination($page, $total_pages, '', $is_filtered); ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> No paid students found matching the selected filter criteria!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
