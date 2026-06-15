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
$month_filter = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $class_filter = sanitize_input($_POST['class'] ?? '');
    $section_filter = sanitize_input($_POST['section'] ?? '');
    $month_filter = sanitize_input($_POST['month'] ?? '');
}

// Get defaulters
$defaulters = get_defaulters($class_filter, $section_filter, $month_filter);
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

                        <a href="edit_student.php" class="module-nav-btn">
                            <i class="fas fa-user-edit"></i> Edit Student
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
                                    <label for="month">Month</label>
                                    <select id="month" name="month" class="form-control">
                                        <option value="">All Months</option>

                                        <?php
                                        for ($i = 1; $i <= 12; $i++) {
                                            $month_str = $MONTHS[$i - 1] . '-' . date('Y');

                                            $selected = ($month_filter === $month_str) ? 'selected' : '';
                                            echo "<option value='$month_str' $selected>$month_str</option>";
                                        }

                                        ?>
                                    </select>
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

                            <a href="defaulter_report.php<?php echo (($class_filter || $section_filter || $month_filter) ? '?class=' . urlencode($class_filter) . '&section=' . urlencode($section_filter) . '&month=' . urlencode($month_filter) : ''); ?>" 
                               class="btn-primary" target="_blank">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </a>

                        </div>
                        
                        <?php if (count($defaulter_list) > 0): ?>
                            <table class="table table-striped table-hover">
                                <thead>

                                    <tr>
                                        <th>Name</th>
                                        <th>Father Name</th>
                                        <th>Class</th>

                                        <th>Section</th>
                                        <th>Contact</th>
                                        <th>Monthly Fee</th>
                                        <th>Unpaid Amount</th>

                                    </tr>
                                </thead>
                                <tbody>

                                    <?php foreach ($defaulter_list as $defaulter):
                                        $unpaid = get_total_unpaid_fees($defaulter['id']);
                                    ?>

                                        <tr>
                                            <td><?php echo $defaulter['name']; ?></td>
                                            <td><?php echo $defaulter['father_name']; ?></td>

                                            <td><?php echo $defaulter['class']; ?></td>
                                            <td><?php echo $defaulter['section']; ?></td>
                                            <td><?php echo $defaulter['contact_number']; ?></td>

                                            <td><?php echo format_currency($defaulter['monthly_fee']); ?></td>
                                            <td><strong style="color: #e74c3c;"><?php echo format_currency($unpaid); ?></strong></td>
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
