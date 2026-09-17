<?php
/**
 * Defaulter List - Core Module
 * School Finance Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';

// Allow Master, Finance, Admission, and Teacher roles
require_login();
if (!is_master() && !is_finance() && !is_admission() && !is_teacher()) {
    header('Location: ../login.php');
    exit();
}

// Multi-select Class & Section Filter inputs (arrays)
$class_filter = $_REQUEST['class'] ?? [];
if (!is_array($class_filter)) {
    $class_filter = !empty($class_filter) ? [$class_filter] : [];
}

$section_filter = $_REQUEST['section'] ?? [];
if (!is_array($section_filter)) {
    $section_filter = !empty($section_filter) ? [$section_filter] : [];
}

$name_filter = sanitize_input($_REQUEST['name'] ?? '');
$father_name_filter = sanitize_input($_REQUEST['father_name'] ?? '');
$months_filter = $_REQUEST['months'] ?? [];
$min_2_months = isset($_REQUEST['min_2_months']) ? 1 : 0; // 2+ Months Checkbox value
$min_3_months = isset($_REQUEST['min_3_months']) ? 1 : 0; // 3+ Months Checkbox value
$arrears_only = isset($_REQUEST['arrears_only']) ? 1 : 0; // Arrears (Partial Payment) Checkbox value

// Check if user has applied any filter
$is_filtered = (!empty($class_filter) || !empty($section_filter) || !empty($name_filter) || !empty($father_name_filter) || !empty($months_filter) || $min_2_months === 1 || $min_3_months === 1 || $arrears_only === 1);

// Pagination Configuration (Only applies when NO filter is used)
$limit = 20; // Default items per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Get defaulters (updated to accept multi-select class & section arrays)
$defaulters = get_defaulters($class_filter, $section_filter, $months_filter, $name_filter, $father_name_filter);
$all_defaulter_list = [];
if ($defaulters) {
    $all_defaulter_list = $defaulters->fetch_all(MYSQLI_ASSOC);
}

// Apply 2+ months, 3+ months, or arrears filter
$all_defaulter_list = filter_defaulters_by_criteria($all_defaulter_list, $min_2_months, $min_3_months, $arrears_only, $months_filter);

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
    <title>Pending List - <?php echo SITE_NAME; ?></title>
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
            padding: 4px 5px;
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
        .checkbox-card {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-weight: 600;
        }
        .checkbox-card.card-warning {
            color: #fd7e14;
        }
        .checkbox-card.card-danger {
            color: #dc3545;
        }
        .checkbox-card.card-info {
            color: #0dcaf0;
        }
    </style>
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <?php render_role_topbar_and_nav('Pending List', 'defaulter_list'); ?>

            <div class="form-section">
                <div class="filter-section">
                    <h4>Filter Pending List</h4>
                    <form method="POST" class="filter-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Student Name</label>
                                <input type="text" id="name" name="name" class="form-control" placeholder="Search by name..." value="<?php echo htmlspecialchars($name_filter); ?>">
                            </div>

                            <div class="form-group">
                                <label for="father_name">Father Name</label>
                                <input type="text" id="father_name" name="father_name" class="form-control" placeholder="Search by father name..." value="<?php echo htmlspecialchars($father_name_filter); ?>">
                            </div>

                            <!-- Multi-select Class Filter -->
                            <div class="form-group">
                                <label>Select Class(es)</label>
                                <div class="months-checkbox-container">
                                    <?php foreach ($CLASSES as $cls): 
                                        $checked_cls = in_array($cls, (array)$class_filter) ? 'checked' : '';
                                    ?>
                                        <label class="month-tick-item">
                                            <input type="checkbox" name="class[]" value="<?php echo htmlspecialchars($cls); ?>" <?php echo $checked_cls; ?>>
                                            <?php echo htmlspecialchars($cls); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Multi-select Section Filter -->
                            <div class="form-group">
                                <label>Select Section(s)</label>
                                <div class="months-checkbox-container">
                                    <?php foreach ($SECTIONS as $sec): 
                                        $checked_sec = in_array($sec, (array)$section_filter) ? 'checked' : '';
                                    ?>
                                        <label class="month-tick-item">
                                            <input type="checkbox" name="section[]" value="<?php echo htmlspecialchars($sec); ?>" <?php echo $checked_sec; ?>>
                                            Section <?php echo htmlspecialchars($sec); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Select Month(s) / Fee Types</label>
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
                                     <label class="month-tick-item ">
                                        <input type="checkbox" name="months[]" value="Other" <?php echo (in_array('Other', (array)$months_filter) || in_array('Other Fee', (array)$months_filter)) ? 'checked' : ''; ?>>
                                        Other / Custom Fees 
                                    </label>
                                    <label class="month-tick-item">
                                        <input type="checkbox" name="months[]" value="Admission" <?php echo (in_array('Admission', (array)$months_filter)) ? 'checked' : ''; ?>>
                                        Admission
                                    </label>
                                    <label class="month-tick-item">
                                        <input type="checkbox" name="months[]" value="Yearly Package" <?php echo (in_array('Yearly Package', (array)$months_filter) || in_array('Package', (array)$months_filter)) ? 'checked' : ''; ?>>
                                        Yearly Package
                                    </label>
                                    <label class="month-tick-item">
                                        <input type="checkbox" name="months[]" value="Pre_Year" <?php echo (in_array('Pre_Year', (array)$months_filter) || in_array('Prev-Year', (array)$months_filter) || in_array('Pre-Year', (array)$months_filter)) ? 'checked' : ''; ?>>
                                        Pre_Year
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Pending Filter</label>
                                <div class="d-flex flex-column gap-2">
                                    <!-- 2+ Months Pending Checkbox -->
                                    <label class="checkbox-card card-warning">
                                        <input type="checkbox" name="min_2_months" id="min_2_months" value="1" style="width: 18px; height: 18px;" <?php echo ($min_2_months === 1) ? 'checked' : ''; ?>>
                                        <span><i class="fas fa-exclamation-triangle me-1"></i> 2+ Months Pending Only</span>
                                    </label>

                                    <!-- 3+ Months Pending Checkbox -->
                                    <label class="checkbox-card card-danger">
                                        <input type="checkbox" name="min_3_months" id="min_3_months" value="1" style="width: 18px; height: 18px;" <?php echo ($min_3_months === 1) ? 'checked' : ''; ?>>
                                        <span><i class="fas fa-exclamation-circle me-1"></i> 3+ Months Pending Only</span>
                                    </label>

                                    <!-- Arrears Only Checkbox -->
                                    <label class="checkbox-card card-info">
                                        <input type="checkbox" name="arrears_only" id="arrears_only" value="1" style="width: 18px; height: 18px;" <?php echo ($arrears_only === 1) ? 'checked' : ''; ?>>
                                        <span class="text-dark"><i class="fas fa-coins text-info me-1"></i> Arrears Only (Partial Payments)</span>
                                    </label>
                                </div>
                            </div>

                           <div class="form-group">
                                <button type="submit" class="btn-primary" style="margin-top: 30px; width: 80%;">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="table-section">
                    <form method="POST" action="../master/export_defaulter_challan.php" target="_blank" id="defaulterChallanForm">
                        <?php foreach ((array)$class_filter as $c_f): ?>
                            <input type="hidden" name="class[]" value="<?php echo htmlspecialchars($c_f); ?>">
                        <?php endforeach; ?>
                        <?php foreach ((array)$section_filter as $s_f): ?>
                            <input type="hidden" name="section[]" value="<?php echo htmlspecialchars($s_f); ?>">
                        <?php endforeach; ?>
                        <input type="hidden" name="name" value="<?php echo htmlspecialchars($name_filter); ?>">
                        <input type="hidden" name="father_name" value="<?php echo htmlspecialchars($father_name_filter); ?>">
                        <?php if ($min_2_months === 1): ?>
                            <input type="hidden" name="min_2_months" value="1">
                        <?php endif; ?>
                        <?php if ($min_3_months === 1): ?>
                            <input type="hidden" name="min_3_months" value="1">
                        <?php endif; ?>
                        <?php if ($arrears_only === 1): ?>
                            <input type="hidden" name="arrears_only" value="1">
                        <?php endif; ?>
                        <?php foreach ((array)$months_filter as $m_f): ?>
                            <input type="hidden" name="months[]" value="<?php echo htmlspecialchars($m_f); ?>">
                        <?php endforeach; ?>

                        <div class="table-header d-flex justify-content-between align-items-center mb-3">
                            <h4>Pending Fees (<?php echo $total_defaulters; ?>)</h4>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success" id="exportChallansBtn">
                                    <i class="fas fa-file-pdf me-1"></i> Export Challans (Whole List) (PDF)
                                </button>
                                <?php 
                                    $query_data = [
                                        'class' => $class_filter, 
                                        'section' => $section_filter, 
                                        'name' => $name_filter, 
                                        'father_name' => $father_name_filter,
                                        'months' => $months_filter,
                                        'min_2_months' => $min_2_months,
                                        'min_3_months' => $min_3_months,
                                        'arrears_only' => $arrears_only
                                    ];
                                    $report_url = "../master/defaulter_report.php?" . http_build_query($query_data);
                                ?>
                                <a href="<?php echo $report_url; ?>" class="btn-primary" target="_blank">
                                    <i class="fas fa-list-alt me-1"></i> Export Summary PDF
                                </a>
                            </div>
                        </div>
                        
                        <?php if (count($defaulter_list) > 0): ?>
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
                                        $single_challan_url = "../master/export_defaulter_challan.php?student_id=" . $defaulter['id'] . "&" . http_build_query([
                                            'class' => $class_filter, 
                                            'section' => $section_filter, 
                                            'name' => $name_filter, 
                                            'father_name' => $father_name_filter,
                                            'months' => $months_filter,
                                            'min_2_months' => $min_2_months,
                                            'min_3_months' => $min_3_months,
                                            'arrears_only' => $arrears_only
                                        ]);
                                        ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="student_ids[]" value="<?php echo $defaulter['id']; ?>" class="student-cb">
                                            </td>
                                            <td><?php echo htmlspecialchars($defaulter['name']); ?></td>
                                            <td><?php echo htmlspecialchars($defaulter['father_name']); ?></td>
                                            <td>
                                                <?php echo !empty($defaulter['contact_number']) ? '<i class="fas fa-phone"></i> ' . htmlspecialchars($defaulter['contact_number']) . '<br>' : ''; ?>
                                                <?php echo !empty($defaulter['whatsapp_number']) ? '<i class="fab fa-whatsapp"></i> ' . htmlspecialchars($defaulter['whatsapp_number']) : ''; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($defaulter['class']) . '-' . htmlspecialchars($defaulter['section']); ?></td>
                                            <td style="max-width: 200px; font-size: 11px;">
                                                <strong class="text-danger">(<?php echo htmlspecialchars($defaulter['pending_count']); ?> Fee)</strong><br>
                                                <?php echo htmlspecialchars(str_replace(',', ', ', $defaulter['pending_months'])); ?>
                                                <?php 
                                                $s_fine = calculate_student_fine($defaulter['id']);
                                                if ($s_fine > 0) {
                                                    echo '<br><span class="badge bg-danger mt-1" style="font-size: 10px;"><i class="fas fa-exclamation-circle me-1"></i>Fine: ' . format_currency($s_fine) . '</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $is_pkg = (!empty($defaulter['is_package']) || floatval($defaulter['package_amount'] ?? 0) > 0 || is_college_class($defaulter['class']) || strpos($defaulter['pending_months'] ?? '', 'Package') !== false);
                                                if ($is_pkg) {
                                                    $raw_pkg = floatval($defaulter['package_amount'] ?? 0);
                                                    if ($raw_pkg <= 0) {
                                                        $raw_pkg = floatval($defaulter['fixed_monthly_fee'] ?? 0);
                                                    }
                                                    $concession = floatval($defaulter['concession_amount'] ?? 0);
                                                    $net_pkg = max(0, $raw_pkg - $concession);
                                                    echo '<strong>' . format_currency($net_pkg) . '</strong> <span class="badge bg-primary text-white ms-1">Yearly Package</span>';
                                                } else {
                                                    echo format_currency($defaulter['monthly_fee']);
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo $single_challan_url; ?>" class="btn btn-sm btn-outline-success text-nowrap" target="_blank">
                                                    <i class="fas fa-file-pdf"></i> Print Challan
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <!-- PAGINATION BUTTONS -->
                            <?php render_pagination($page, $total_pages, '', $is_filtered); ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No pending fees found with the selected filters!
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var selectAll = document.getElementById('selectAllStudents');
        var exportBtn = document.getElementById('exportChallansBtn');

        function updateExportButtonText() {
            if (!exportBtn) return;
            var checkedBoxes = document.querySelectorAll('.student-cb:checked');
            if (checkedBoxes.length > 0) {
                exportBtn.innerHTML = '<i class="fas fa-file-pdf me-1"></i> Export Selected Challans (' + checkedBoxes.length + ') (PDF)';
            } else {
                exportBtn.innerHTML = '<i class="fas fa-file-pdf me-1"></i> Export Challans (Whole List) (PDF)';
            }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                var checkboxes = document.querySelectorAll('.student-cb');
                checkboxes.forEach(function(cb) {
                    cb.checked = selectAll.checked;
                });
                updateExportButtonText();
            });
        }

        document.querySelectorAll('.student-cb').forEach(function(cb) {
            cb.addEventListener('change', function() {
                updateExportButtonText();
            });
        });

        updateExportButtonText();

        // Mutual exclusion between 2+, 3+, and Arrears checkboxes
        var min2Cb = document.getElementById('min_2_months');
        var min3Cb = document.getElementById('min_3_months');
        var arrCb  = document.getElementById('arrears_only');

        if (min2Cb && min3Cb && arrCb) {
            min2Cb.addEventListener('change', function() {
                if (this.checked) {
                    min3Cb.checked = false;
                    arrCb.checked = false;
                }
            });
            min3Cb.addEventListener('change', function() {
                if (this.checked) {
                    min2Cb.checked = false;
                    arrCb.checked = false;
                }
            });
            arrCb.addEventListener('change', function() {
                if (this.checked) {
                    min2Cb.checked = false;
                    min3Cb.checked = false;
                }
            });
        }
    });
    </script>
</body>
</html>