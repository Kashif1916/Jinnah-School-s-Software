<?php
/**
 * Defaulter List
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

$class_filter = '';
$section_filter = '';
$months_filter = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $class_filter = sanitize_input($_POST['class'] ?? '');
    $section_filter = sanitize_input($_POST['section'] ?? '');
    $months_filter = $_POST['months'] ?? [];
}

// Get defaulters
$defaulters = get_defaulters($class_filter, $section_filter, $months_filter);
$defaulter_list = [];

if ($defaulters) {
    $defaulter_list = $defaulters->fetch_all(MYSQLI_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending List - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        .months-checkbox-container {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 10px;
            max-height: 120px;
            overflow-y: auto;
            background-color: #fff;
        }
        .month-tick-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 3px 5px;
            cursor: pointer;
            font-size: 14px;
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
                        <h2>Pending List</h2>
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
                        <a href="fee_management.php" class="module-nav-btn">
                            <i class="fas fa-money-bill-wave"></i> Fee Management
                        </a>
                        <a href="defaulter_list.php" class="module-nav-btn active">
                            <i class="fas fa-list"></i> Pending List
                        </a>
                        <a href="payment_analytics.php" class="module-nav-btn">
                            <i class="fas fa-chart-line"></i> Analytics
                        </a>
                        <a href="promotion.php" class="module-nav-btn">
                            <i class="fas fa-arrow-up"></i> Promotion
                        </a>
                        <a href="drop_student.php" class="module-nav-btn">
                            <i class="fas fa-trash"></i> Drop Student
                        </a>
                    </div>
                </div>

                <div class="form-section">
                    <div class="filter-section">
                        <h4>Filter Pending List</h4>
                        <form method="POST" class="filter-form">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="class">Class</label>
                                    <select id="class" name="class" class="form-control">
                                        <option value="">All Classes</option>
                                        <?php foreach ($CLASSES as $cls): ?>
                                            <option value="<?php echo $cls; ?>" <?php echo ($class_filter === $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="section">Section</label>
                                    <select id="section" name="section" class="form-control">
                                        <option value="">All Sections</option>
                                        <?php foreach ($SECTIONS as $sec): ?>
                                            <option value="<?php echo $sec; ?>" <?php echo ($section_filter === $sec) ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Select Month(s)</label>
                                    <div class="months-checkbox-container">
                                        <?php
                                        for ($i = 1; $i <= 12; $i++) {
                                            $month_str = $MONTHS[$i - 1] . '-' . date('Y');
                                            $checked = (in_array($month_str, (array)$months_filter)) ? 'checked' : '';
                                            ?>
                                            <label class="month-tick-item">
                                                <input type="checkbox" name="months[]" value="<?php echo $month_str; ?>" <?php echo $checked; ?>>
                                                <?php echo $month_str; ?>
                                            </label>
                                        <?php } ?>
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
                        <div class="table-header">
                            <h4>Pending Fees (<?php echo count($defaulter_list); ?>)</h4>
                            <?php 
                                $query_data = ['class' => $class_filter, 'section' => $section_filter, 'months' => $months_filter];
                                $report_url = "defaulter_report.php?" . http_build_query($query_data);
                            ?>
                            <a href="<?php echo $report_url; ?>" class="btn-primary" target="_blank">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </a>
                        </div>
                        
                        <?php if (count($defaulter_list) > 0): ?>
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Father Name</th>
                                        <th>Contact Number(s)</th>
                                        <th>Class-Sec</th>
                                        <th>Pending Month(s)</th>
                                        <th>Monthly Fee</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($defaulter_list as $defaulter): ?>
                                        <tr>
                                            <td><?php echo $defaulter['name']; ?></td>
                                            <td><?php echo $defaulter['father_name']; ?></td>
                                            <td>
                                                <?php echo !empty($defaulter['contact_number']) ? $defaulter['contact_number'] . '<br>' : ''; ?>
                                                <?php echo !empty($defaulter['whatsapp_number']) ? '<i class="fab fa-whatsapp"></i> ' . $defaulter['whatsapp_number'] : ''; ?>
                                            </td>
                                            <td><?php echo $defaulter['class'] . '-' . $defaulter['section']; ?></td>
                                            <td style="max-width: 200px; font-size: 11px;">
                                                <strong>(<?php echo $defaulter['pending_count']; ?> Month)</strong><br>
                                                <?php echo str_replace(',', ', ', $defaulter['pending_months']); ?>
                                            </td>
                                            <td><?php echo format_currency($defaulter['monthly_fee']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No pending fees found with the selected filters!
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