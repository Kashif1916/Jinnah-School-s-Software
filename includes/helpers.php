<?php
/**
 * Helper Functions
 * School Finance Management System
 */

/**
 * Sanitize input
 */
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

/**
 * Format currency
 */
function format_currency($amount) {
    return 'Rs. ' . number_format($amount, 2);
}

/**
 * Generate month string
 */
function get_month_string($month_num) {
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return $months[$month_num - 1] . '-' . date('Y');
}

/**
 * Get current month year
 */
function get_current_month() {
    return date('M-Y');
}

/**
 * Format date
 */
function format_date($datetime) {
    if (empty($datetime)) return '-';
    return date('d-m-Y', strtotime($datetime));
}

/**
 * Format datetime
 */
function format_datetime($datetime) {
    if (empty($datetime)) return '-';
    return date('d-m-Y h:i A', strtotime($datetime));
}

/**
 * Get student by ID
 */
function get_student($student_id) {
    global $conn;
    $query = "SELECT * FROM students WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Get fee record
 */
function get_fee_record($student_id, $month) {
    global $conn;
    $query = "SELECT * FROM fee_records WHERE student_id = ? AND month = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('is', $student_id, $month);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Check if a class is a College level package class (11, 12, Passed-12)
 */
function is_college_class($class) {
    if (empty($class)) return false;
    $college_classes = ['11', '12', 'passed-12', '11th', '12th', 'f.sc', 'fa', 'ics', 'i.com'];
    return in_array(strtolower(trim($class)), $college_classes);
}

/**
 * Create fee records for new student (Handles Monthly Fee or Single Yearly Package Fee)
 */
function create_annual_fees($student_id, $fixed_monthly_fee, $concession_amount = 0, $admission_fee = 0, $package_amount = 0, $is_package = 0, $class = '') {
    global $conn;
    
    // First, schedule the admission fee if it is greater than 0
    if ($admission_fee > 0) {
        $query = "INSERT INTO fee_records (student_id, month, amount, status) VALUES (?, 'Admission', ?, 'unpaid')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('id', $student_id, $admission_fee);
        $stmt->execute();
        $stmt->close();
    }

    if ($is_package || $package_amount > 0) {
        // College Package system: Single fee record for Yearly Package (handles partial payments)
        $net_package = floatval($package_amount) - floatval($concession_amount);
        if ($net_package < 0) $net_package = 0;

        $month_label = 'Yearly Package';
        // Ensure no duplicate if already exists for this student
        $chk = $conn->prepare("SELECT id FROM fee_records WHERE student_id = ? AND month = ?");
        $chk->bind_param('is', $student_id, $month_label);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $month_label = !empty($class) ? substr('Package-' . trim($class), 0, 20) : 'Yearly Package 2';
        }
        $chk->close();

        $query = "INSERT INTO fee_records (student_id, month, amount, status) VALUES (?, ?, ?, 'unpaid')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('isd', $student_id, $month_label, $net_package);
        $stmt->execute();
        $stmt->close();
    } else {
        // Standard Monthly Fee system: 12 months
        $day = intval(date('d'));
        if ($day >= 20) {
            $start_date = strtotime('+1 month', strtotime(date('Y-m-01')));
        } else {
            $start_date = strtotime(date('Y-m-01'));
        }

        $monthly_fee = floatval($fixed_monthly_fee) - floatval($concession_amount);
        if ($monthly_fee < 0) $monthly_fee = 0;

        for ($i = 0; $i < 12; $i++) {
            $month = date('M-Y', strtotime("+$i months", $start_date));
            $query = "INSERT INTO fee_records (student_id, month, amount, status) VALUES (?, ?, ?, 'unpaid')";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('isd', $student_id, $month, $monthly_fee);
            $stmt->execute();
            $stmt->close();
        }
    }
}

/**
 * Automatically generate next 5 months of fees if unpaid months are 5 or less
 */
function auto_generate_fee_buffer($student_id, $monthly_fee) {
    global $conn;
    
    // Check if student is in college package class (11, 12, Passed-12) or is_package
    $student = get_student($student_id);
    if ($student) {
        $cls = $student['class'] ?? '';
        $is_pkg = intval($student['is_package'] ?? 0);
        if ($is_pkg === 1 || is_college_class($cls)) {
            // College package classes (11, 12, Passed-12) should NOT have fees auto-scheduled!
            return;
        }
    }

    // Count current unpaid months
    $query = "SELECT COUNT(*) as unpaid_count FROM fee_records WHERE student_id = ? AND status = 'unpaid'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['unpaid_count'];
    $stmt->close();

    if ($count <= 5) {
        // Find the last generated month for this student (exclude 'Admission')
        $query = "SELECT month FROM fee_records WHERE student_id = ? AND month != 'Admission' ORDER BY STR_TO_DATE(CONCAT('01-', month), '%d-%b-%Y') DESC LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $last_month_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $start_date = $last_month_row ? strtotime("01-" . $last_month_row['month']) : strtotime(date('Y-m-01'));
        
        for ($i = 1; $i <= 5; $i++) {
            $next_month = date('M-Y', strtotime("+$i month", $start_date));
            $query = "INSERT IGNORE INTO fee_records (student_id, month, amount, status) VALUES (?, ?, ?, 'unpaid')";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('isd', $student_id, $next_month, $monthly_fee);
            $stmt->execute();
            $stmt->close();
        }
    }
}

/**
 * Update all UNPAID records from current month onwards with new fee structure
 */
function sync_unpaid_fee_amounts($student_id, $new_monthly_fee) {
    global $conn;
    $current_month_start = date('Y-m-01');
    
    $query = "UPDATE fee_records SET amount = ? 
              WHERE student_id = ? AND status = 'unpaid' AND month != 'Admission' AND month NOT LIKE '%Package%'
              AND STR_TO_DATE(CONCAT('01-', month), '%d-%b-%Y') >= ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('dis', $new_monthly_fee, $student_id, $current_month_start);
    $stmt->execute();
    $stmt->close();
}

/**
 * Get total unpaid fees for student
 */
function get_total_unpaid_fees($student_id) {
    global $conn;
    $query = "SELECT SUM(amount) as total FROM fee_records WHERE student_id = ? AND status = 'unpaid'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total'] ?? 0;
}

/**
 * Get total paid fees for student
 */
function get_total_paid_fees($student_id) {
    global $conn;
    $query = "SELECT SUM(amount) as total FROM fee_records WHERE student_id = ? AND status = 'paid'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total'] ?? 0;
}

/**
 * Get defaulters list
 */
function get_defaulters($class = '', $section = '', $months = [], $name = '') {
    global $conn;
    
    // If no months are specified, default to previous 12 months (inclusive of current month) plus Admission, Pre_Year, and Package
    if (empty($months)) {
        $months = [];
        $start_date = strtotime(date('Y-m-01'));
        for ($i = 0; $i < 12; $i++) {
            $months[] = date('M-Y', strtotime("-$i months", $start_date));
        }
        $months[] = 'Admission';
        $months[] = 'Pre_Year';
        $months[] = 'Prev-Year';
        $months[] = 'Pre-Year';
        $months[] = 'Yearly Package';
        $months[] = 'Package';
    }
    
    $query = "SELECT s.id, s.name, s.father_name, s.class, s.section, s.fixed_monthly_fee, s.monthly_fee, 
                     s.package_amount, s.is_package, s.concession_amount,
                     s.contact_number, s.contact_number2, s.whatsapp_number,
                     GROUP_CONCAT(f.month ORDER BY CASE WHEN f.month = 'Admission' THEN 1 WHEN f.month IN ('Pre_Year', 'Prev-Year', 'Pre-Year') THEN 2 WHEN f.month LIKE '%Package%' THEN 3 ELSE 4 END, STR_TO_DATE(CONCAT('01-', f.month), '%d-%b-%Y')) as pending_months,
                     COUNT(f.id) as pending_count,
                     SUM(f.amount) as filtered_unpaid_amount
              FROM students s 
              INNER JOIN fee_records f ON s.id = f.student_id 
              WHERE s.status = 'active' AND f.status = 'unpaid'";
    
    if (!empty($class)) {
        $query .= " AND s.class = '" . $conn->real_escape_string($class) . "'";
    }
    
    if (!empty($section)) {
        $query .= " AND s.section = '" . $conn->real_escape_string($section) . "'";
    }

    if (!empty($name)) {
        $query .= " AND s.name LIKE '%" . $conn->real_escape_string($name) . "%'";
    }
    
    if (!empty($months)) {
        if (!is_array($months)) $months = [$months];
        $expanded_months = [];
        foreach ($months as $m) {
            $expanded_months[] = $m;
            if ($m === 'Pre_Year' || $m === 'Prev-Year' || $m === 'Pre-Year') {
                $expanded_months[] = 'Pre_Year';
                $expanded_months[] = 'Prev-Year';
                $expanded_months[] = 'Pre-Year';
            }
            if ($m === 'Yearly Package' || $m === 'Package') {
                $expanded_months[] = 'Yearly Package';
                $expanded_months[] = 'Package';
            }
        }
        $expanded_months = array_unique($expanded_months);
        $escaped_months = array_map(function($m) use ($conn) { 
            return "'" . $conn->real_escape_string($m) . "'"; 
        }, $expanded_months);
        
        $has_package_filter = in_array('Yearly Package', $expanded_months) || in_array('Package', $expanded_months);
        if ($has_package_filter) {
            $query .= " AND (f.month IN (" . implode(',', $escaped_months) . ") OR f.month LIKE '%Package%')";
        } else {
            $query .= " AND f.month IN (" . implode(',', $escaped_months) . ")";
        }
    }
    
    $query .= " GROUP BY s.id ORDER BY s.class, s.section, s.name";
    
    return $conn->query($query);
}

/**
 * Get paid students list
 */
function get_paid_students($class = '', $section = '', $months = [], $name = '') {
    global $conn;
    
    // If no months are specified, default to previous 12 months (inclusive of current month) plus Admission, Pre_Year, and Package
    if (empty($months)) {
        $months = [];
        $start_date = strtotime(date('Y-m-01'));
        for ($i = 0; $i < 12; $i++) {
            $months[] = date('M-Y', strtotime("-$i months", $start_date));
        }
        $months[] = 'Admission';
        $months[] = 'Pre_Year';
        $months[] = 'Prev-Year';
        $months[] = 'Pre-Year';
        $months[] = 'Yearly Package';
        $months[] = 'Package';
    }
    
    $query = "SELECT s.id, s.name, s.father_name, s.class, s.section, s.fixed_monthly_fee, s.monthly_fee, 
                     s.contact_number, s.contact_number2, s.whatsapp_number,
                     GROUP_CONCAT(f.month ORDER BY CASE WHEN f.month = 'Admission' THEN 1 WHEN f.month IN ('Pre_Year', 'Prev-Year', 'Pre-Year') THEN 2 WHEN f.month LIKE '%Package%' THEN 3 ELSE 4 END, STR_TO_DATE(CONCAT('01-', f.month), '%d-%b-%Y')) as paid_months,
                     COUNT(f.id) as paid_count,
                     MAX(f.payment_date) as last_payment_date
              FROM students s 
              INNER JOIN fee_records f ON s.id = f.student_id 
              WHERE s.status = 'active' AND f.status = 'paid'";
    
    if (!empty($class)) {
        $query .= " AND s.class = '" . $conn->real_escape_string($class) . "'";
    }
    
    if (!empty($section)) {
        $query .= " AND s.section = '" . $conn->real_escape_string($section) . "'";
    }

    if (!empty($name)) {
        $query .= " AND s.name LIKE '%" . $conn->real_escape_string($name) . "%'";
    }
    
    if (!empty($months)) {
        if (!is_array($months)) $months = [$months];
        $expanded_months = [];
        foreach ($months as $m) {
            $expanded_months[] = $m;
            if ($m === 'Pre_Year' || $m === 'Prev-Year' || $m === 'Pre-Year') {
                $expanded_months[] = 'Pre_Year';
                $expanded_months[] = 'Prev-Year';
                $expanded_months[] = 'Pre-Year';
            }
            if ($m === 'Yearly Package' || $m === 'Package') {
                $expanded_months[] = 'Yearly Package';
                $expanded_months[] = 'Package';
            }
        }
        $expanded_months = array_unique($expanded_months);
        $escaped_months = array_map(function($m) use ($conn) { 
            return "'" . $conn->real_escape_string($m) . "'"; 
        }, $expanded_months);
        
        $has_package_filter = in_array('Yearly Package', $expanded_months) || in_array('Package', $expanded_months);
        if ($has_package_filter) {
            $query .= " AND (f.month IN (" . implode(',', $escaped_months) . ") OR f.month LIKE '%Package%')";
        } else {
            $query .= " AND f.month IN (" . implode(',', $escaped_months) . ")";
        }
    }
    
    $query .= " GROUP BY s.id ORDER BY s.class, s.section, s.name";
    
    return $conn->query($query);
}

/**
 * Get daily collection
 */
function get_daily_collection($date) {
    global $conn;
    $query = "SELECT SUM(amount) as total FROM payments WHERE DATE(payment_date) = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total'] ?? 0;
}

/**
 * Get monthly collection
 */
function get_monthly_collection($month_year) {
    global $conn;
    $query = "SELECT SUM(amount) as total FROM payments WHERE DATE_FORMAT(payment_date, '%b-%Y') = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $month_year);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total'] ?? 0;
}

/**
 * Record payment
 */
function record_payment($student_id, $amount, $month, $received_by, $payment_mode = 'cash', $receipt_number = null) {
    global $conn;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        $payment_date = date('Y-m-d H:i:s');

        // Special handling for Fine / Late Fee and Other Payments
        if ($month === 'Fine' || $month === 'Other' || $month === 'Other Payment') {
            $query = "INSERT INTO payments (student_id, receipt_number, amount, paid_for_month, payment_date, received_by, payment_mode) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('isdssss', $student_id, $receipt_number, $amount, $month, $payment_date, $received_by, $payment_mode);
            $stmt->execute();
            $payment_id = $conn->insert_id;
            $stmt->close();

            if (empty($receipt_number)) {
                $auto_receipt = sprintf('%06d', $payment_id);
                $conn->query("UPDATE payments SET receipt_number = '$auto_receipt' WHERE id = $payment_id");
            }
            
            $conn->commit();
            return $payment_id;
        }

        // Get current balance for the specific fee record
        // Assuming $month is in 'Mon-YYYY' format and $student_id is integer
        $query = "SELECT id, amount, month FROM fee_records WHERE student_id = ? AND month = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('is', $student_id, $month);
        $stmt->execute();
        $current_record = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$current_record) {
            throw new Exception("Fee record not found for student_id: $student_id, month: $month");
        }

        $fee_record_id = $current_record['id'];
        $current_balance = floatval($current_record['amount']);
        $new_balance = $current_balance - $amount;

        // Record payment
        $query = "INSERT INTO payments (student_id, receipt_number, amount, paid_for_month, payment_date, received_by, payment_mode) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('isdssss', $student_id, $receipt_number, $amount, $month, $payment_date, $received_by, $payment_mode);
        $stmt->execute();
        $payment_id = $conn->insert_id;
        $stmt->close();

        if (empty($receipt_number)) {
            $auto_receipt = sprintf('%06d', $payment_id);
            $conn->query("UPDATE payments SET receipt_number = '$auto_receipt' WHERE id = $payment_id");
        }
        
        // Update fee record: if balance is 0 or less, mark as paid. Otherwise update remaining amount.
        if ($new_balance <= 0) {
            $query = "UPDATE fee_records SET status = 'paid', payment_date = ?, amount = 0 WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('si', $payment_date, $fee_record_id);
        } else {
            $query = "UPDATE fee_records SET amount = ?, payment_date = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('dsi', $new_balance, $payment_date, $fee_record_id);
        }
        $stmt->execute();
        $stmt->close();
        
        // Auto check for buffer after payment
        $student = get_student($student_id);
        $net_fee = floatval($student['fixed_monthly_fee']) - floatval($student['concession_amount']);
        auto_generate_fee_buffer($student_id, $net_fee);

        $conn->commit();
        return $payment_id;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Get 12 months (current + past 11 months) status for a student
 */
function get_student_concession_months_status($student_id) {
    global $conn;
    $result = [];
    $start_date = strtotime(date('Y-m-01'));
    
    // Fetch fee records for this student
    $fee_records = [];
    $stmt = $conn->prepare("SELECT month, status, amount FROM fee_records WHERE student_id = ?");
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $fee_records[$row['month']] = $row;
    }
    $stmt->close();

    for ($i = 0; $i < 12; $i++) {
        $month_str = date('M-Y', strtotime("-$i month", $start_date));
        $is_paid = false;
        $status = 'unpaid';
        if (isset($fee_records[$month_str])) {
            $status = $fee_records[$month_str]['status'];
            if (strtolower($status) === 'paid') {
                $is_paid = true;
            }
        }
        $result[] = [
            'month' => $month_str,
            'status' => $status,
            'is_paid' => $is_paid
        ];
    }
    return $result;
}

/**
 * Show message
 */
function show_message($type, $message) {
    echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">';
    echo $message;
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

/**
 * Render system logo
 */
function render_system_logo($class = '') {
    $class_attr = !empty($class) ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';
    return '<img src="' . BASE_URL . 'images/logo.jfif" alt="' . htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') . '"' . $class_attr . '>';
}


/**
 * Render Smart Truncated Pagination Links
 */
function render_pagination($page, $total_pages, $extra_params = '', $is_filtered = false) {
    if ($is_filtered || $total_pages <= 1) {
        return;
    }
    
    // Process query string
    $query_params = [];
    if (is_array($extra_params)) {
        $query_params = $extra_params;
    } elseif (is_string($extra_params) && !empty($extra_params)) {
        parse_str(ltrim($extra_params, '?'), $query_params);
    }
    
    echo '<nav class="mt-4 no-print">';
    echo '<ul class="pagination justify-content-center flex-wrap">';
    
    // Build link helper
    $get_url = function($p) use ($query_params) {
        $params = array_merge($query_params, ['page' => $p]);
        return '?' . http_build_query($params);
    };

    // Previous Link
    if ($page <= 1) {
        echo '<li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left me-1"></i> Prev</span></li>';
    } else {
        echo '<li class="page-item"><a class="page-link" href="' . $get_url($page - 1) . '"><i class="fas fa-chevron-left me-1"></i> Prev</a></li>';
    }
    
    $range = 2; // Window range around current page
    $start_page = max(1, $page - $range);
    $end_page = min($total_pages, $page + $range);

    if ($start_page > 1) {
        echo '<li class="page-item ' . ($page == 1 ? 'active' : '') . '"><a class="page-link" href="' . $get_url(1) . '">1</a></li>';
        if ($start_page > 2) {
            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == 1 && $start_page > 1) continue;
        if ($i == $total_pages && $end_page < $total_pages) continue;
        $active = ($page == $i) ? 'active' : '';
        echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . $get_url($i) . '">' . $i . '</a></li>';
    }
    
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        echo '<li class="page-item ' . ($page == $total_pages ? 'active' : '') . '"><a class="page-link" href="' . $get_url($total_pages) . '">' . $total_pages . '</a></li>';
    }
    
    // Next Link
    if ($page >= $total_pages) {
        echo '<li class="page-item disabled"><span class="page-link">Next <i class="fas fa-chevron-right ms-1"></i></span></li>';
    } else {
        echo '<li class="page-item"><a class="page-link" href="' . $get_url($page + 1) . '">Next <i class="fas fa-chevron-right ms-1"></i></a></li>';
    }
    
    echo '</ul>';
    echo '</nav>';
}

/**
 * Render Header Topbar and Navigation Panel dynamically based on current user role
 */
function render_role_topbar_and_nav($page_title, $active_page) {
    $role = get_user_role();
    $username = get_username();
    
    $role_title = 'User Panel';
    if ($role === 'master') {
        $role_title = 'Principal Panel';
    } elseif ($role === 'finance') {
        $role_title = 'Finance / Clerk Panel';
    } elseif ($role === 'admission') {
        $role_title = 'Admission Panel';
    } elseif ($role === 'teacher') {
        $role_title = 'Teacher Panel';
    }
    ?>
    <div class="topbar">
        <div class="topbar-left d-flex align-items-center gap-3">
            <a href="dashboard.php"><?php echo render_system_logo('topbar-logo'); ?></a>
            <div class="panel-brand">
                <h2><?php echo htmlspecialchars($page_title); ?></h2>
                <span><?php echo htmlspecialchars($role_title); ?></span>
            </div>
        </div>
        <div class="topbar-right">
            <span class="user-info">
                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($username); ?>
            </span>
            <a href="../logout.php" class="btn-secondary">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <div class="content">
        <div class="module-nav-panel">
            <div class="module-nav-row">
                <?php if ($role === 'master'): ?>
                    <a href="dashboard.php" class="module-nav-btn <?php echo ($active_page === 'dashboard') ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i> Dashboard
                    </a>
                    <a href="add_student.php" class="module-nav-btn <?php echo ($active_page === 'add_student') ? 'active' : ''; ?>">
                        <i class="fas fa-user-plus"></i> Add Student
                    </a>
                    <a href="student_record.php" class="module-nav-btn <?php echo ($active_page === 'student_record') ? 'active' : ''; ?>">
                        <i class="fas fa-address-book"></i> Student Record
                    </a>
                    <a href="student_add_details.php" class="module-nav-btn <?php echo ($active_page === 'student_add_details') ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i> Add Log
                    </a>
                    <a href="fee_schedule.php" class="module-nav-btn <?php echo ($active_page === 'fee_schedule') ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> Fee Schedule
                    </a>
                    <a href="fee_management.php" class="module-nav-btn <?php echo ($active_page === 'fee_management') ? 'active' : ''; ?>">
                        <i class="fas fa-money-bill-wave"></i> Fee Management
                    </a>
                    <a href="defaulter_list.php" class="module-nav-btn <?php echo ($active_page === 'defaulter_list') ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> Pending List
                    </a>
                    <a href="paid_students.php" class="module-nav-btn <?php echo ($active_page === 'paid_students') ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle text-success"></i> Paid Students
                    </a>
                    <a href="payment_analytics.php" class="module-nav-btn <?php echo ($active_page === 'payment_analytics') ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i> Analytics
                    </a>
                    <a href="receipt_analysis.php" class="module-nav-btn <?php echo ($active_page === 'receipt_analysis') ? 'active' : ''; ?>">
                        <i class="fas fa-receipt"></i> Receipt Analysis
                    </a>
                    <a href="expenses.php" class="module-nav-btn <?php echo ($active_page === 'expenses') ? 'active' : ''; ?>">
                        <i class="fas fa-wallet"></i> Expenses
                    </a>
                    <a href="data_correction.php" class="module-nav-btn <?php echo ($active_page === 'data_correction') ? 'active' : ''; ?>">
                        <i class="fas fa-edit"></i> Data Correction
                    </a>
                    <a href="promotion.php" class="module-nav-btn <?php echo ($active_page === 'promotion') ? 'active' : ''; ?>">
                        <i class="fas fa-arrow-up"></i> Promotion
                    </a>
                    <a href="drop_student.php" class="module-nav-btn <?php echo ($active_page === 'drop_student') ? 'active' : ''; ?>">
                        <i class="fas fa-trash"></i> Drop Student
                    </a>
                    <a href="delete_student.php" class="module-nav-btn <?php echo ($active_page === 'delete_student') ? 'active' : ''; ?>">
                        <i class="fas fa-user-minus text-success"></i> Delete Student
                    </a>
                    <a href="users.php" class="module-nav-btn <?php echo ($active_page === 'users') ? 'active' : ''; ?>">
                        <i class="fas fa-users-cog"></i> Users
                    </a>
                    <a href="receipt_note.php" class="module-nav-btn <?php echo ($active_page === 'receipt_note') ? 'active' : ''; ?>">
                        <i class="fas fa-sticky-note"></i> Custom Note
                    </a>
                    <a href="../help.php" class="module-nav-btn">
                        <i class="fas fa-question-circle text-success"></i> Help & About
                    </a>
                <?php elseif ($role === 'finance'): ?>
                    <a href="dashboard.php" class="module-nav-btn <?php echo ($active_page === 'dashboard') ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i> Dashboard
                    </a>
                    <a href="add_student.php" class="module-nav-btn <?php echo ($active_page === 'add_student') ? 'active' : ''; ?>">
                        <i class="fas fa-user-plus"></i> Add Student
                    </a>
                    <a href="student_record.php" class="module-nav-btn <?php echo ($active_page === 'student_record') ? 'active' : ''; ?>">
                        <i class="fas fa-address-book"></i> Student Record
                    </a>
                    <a href="fee_payment.php" class="module-nav-btn <?php echo ($active_page === 'fee_payment') ? 'active' : ''; ?>">
                        <i class="fas fa-money-bill-wave"></i> Fee Payment
                    </a>
                    <a href="defaulter_list.php" class="module-nav-btn <?php echo ($active_page === 'defaulter_list') ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> Pending List
                    </a>
                    <a href="paid_students.php" class="module-nav-btn <?php echo ($active_page === 'paid_students') ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle text-success"></i> Paid Students
                    </a>
                    <a href="payment_analytics.php" class="module-nav-btn <?php echo ($active_page === 'payment_analytics') ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i> Analytics
                    </a>
                    <a href="receipt_analysis.php" class="module-nav-btn <?php echo ($active_page === 'receipt_analysis') ? 'active' : ''; ?>">
                        <i class="fas fa-receipt"></i> Receipt Analysis
                    </a>
                    <a href="expenses.php" class="module-nav-btn <?php echo ($active_page === 'expenses') ? 'active' : ''; ?>">
                        <i class="fas fa-wallet"></i> Expenses
                    </a>
                    <a href="account_close.php" class="module-nav-btn <?php echo ($active_page === 'account_close') ? 'active' : ''; ?>">
                        <i class="fas fa-lock"></i> Account Close
                    </a>
                    <a href="drop_student.php" class="module-nav-btn <?php echo ($active_page === 'drop_student') ? 'active' : ''; ?>">
                        <i class="fas fa-trash"></i> Drop Student
                    </a>
                    <a href="../help.php" class="module-nav-btn">
                        <i class="fas fa-question-circle text-success"></i> Help & About
                    </a>
                <?php elseif ($role === 'admission'): ?>
                    <a href="add_student.php" class="module-nav-btn <?php echo ($active_page === 'add_student') ? 'active' : ''; ?>">
                        <i class="fas fa-user-plus"></i> Add Student
                    </a>
                    <a href="data_entry.php" class="module-nav-btn <?php echo ($active_page === 'data_entry') ? 'active' : ''; ?>">
                        <i class="fas fa-keyboard"></i> Data Entry
                    </a>
                    <a href="student_record.php" class="module-nav-btn <?php echo ($active_page === 'student_record') ? 'active' : ''; ?>">
                        <i class="fas fa-address-book"></i> Student Record
                    </a>
                    <a href="defaulter_list.php" class="module-nav-btn <?php echo ($active_page === 'defaulter_list') ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> Pending List
                    </a>
                    <a href="promotion.php" class="module-nav-btn <?php echo ($active_page === 'promotion') ? 'active' : ''; ?>">
                        <i class="fas fa-arrow-up"></i> Promotion
                    </a>
                    <a href="drop_student.php" class="module-nav-btn <?php echo ($active_page === 'drop_student') ? 'active' : ''; ?>">
                        <i class="fas fa-trash"></i> Drop Student
                    </a>
                    <a href="../help.php" class="module-nav-btn">
                        <i class="fas fa-question-circle text-success"></i> Help & About
                    </a>
                <?php elseif ($role === 'teacher'): ?>
                    <a href="student_record.php" class="module-nav-btn <?php echo ($active_page === 'student_record') ? 'active' : ''; ?>">
                        <i class="fas fa-address-book"></i> Student Record
                    </a>
                    <a href="defaulter_list.php" class="module-nav-btn <?php echo ($active_page === 'defaulter_list') ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> Pending List
                    </a>
                    <a href="drop_student.php" class="module-nav-btn <?php echo ($active_page === 'drop_student') ? 'active' : ''; ?>">
                        <i class="fas fa-trash"></i> Drop Student
                    </a>
                    <a href="../help.php" class="module-nav-btn">
                        <i class="fas fa-question-circle text-success"></i> Help & About
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php
}

/**
 * Create fee schedule for promoted student (Single Yearly Package or 12 Monthly Fees)
 */
function schedule_promotion_annual_fees($student_id, $fixed_monthly_fee, $concession_amount = 0, $package_amount = 0, $is_package = 0, $to_class = '') {
    global $conn;

    if ($is_package || $package_amount > 0) {
        $net_package = floatval($package_amount) - floatval($concession_amount);
        if ($net_package < 0) $net_package = 0;

        $month_label = 'Yearly Package';
        // If student already has 'Yearly Package' from previous class, differentiate by class name (e.g. Package-12)
        $chk = $conn->prepare("SELECT id FROM fee_records WHERE student_id = ? AND month = ?");
        $chk->bind_param('is', $student_id, $month_label);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $month_label = !empty($to_class) ? substr('Package-' . trim($to_class), 0, 20) : 'Yearly Package 2';
        }
        $chk->close();

        $ins = $conn->prepare("INSERT INTO fee_records (student_id, month, amount, status) VALUES (?, ?, ?, 'unpaid')");
        $ins->bind_param('isd', $student_id, $month_label, $net_package);
        $ins->execute();
        $ins->close();
    } else {
        // Find the latest existing fee month for this student (exclude 'Admission' and Package records)
        $query = "SELECT month FROM fee_records WHERE student_id = ? AND month NOT LIKE '%Package%' AND month != 'Admission' AND month NOT IN ('Pre_Year', 'Prev-Year', 'Pre-Year') ORDER BY STR_TO_DATE(CONCAT('01-', month), '%d-%b-%Y') DESC LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $last_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($last_row && !empty($last_row['month']) && strtotime("01-" . $last_row['month']) !== false) {
            $last_date = strtotime("01-" . $last_row['month']);
            $start_date = strtotime("+1 month", $last_date);
        } else {
            $day = intval(date('d'));
            if ($day >= 20) {
                $start_date = strtotime('+1 month', strtotime(date('Y-m-01')));
            } else {
                $start_date = strtotime(date('Y-m-01'));
            }
        }

        $monthly_fee = floatval($fixed_monthly_fee) - floatval($concession_amount);
        if ($monthly_fee < 0) $monthly_fee = 0;

        for ($i = 0; $i < 12; $i++) {
            $month = date('M-Y', strtotime("+$i months", $start_date));
            $ins = $conn->prepare("INSERT IGNORE INTO fee_records (student_id, month, amount, status) VALUES (?, ?, ?, 'unpaid')");
            $ins->bind_param('isd', $student_id, $month, $monthly_fee);
            $ins->execute();
            $ins->close();
        }
    }
}

/**
 * Calculate late fee fine for a month (Rs. 20 per day starting from 11th date of month, active from August 2026 onwards)
 * Note: Months before August 2026 (e.g. Jan-Jul 2026, Pre_Year, Admission, Package) have NO fine (0).
 */
function calculate_month_late_fine($month, $today_date = null) {
    if (empty($month)) return 0;
    
    // Exclude non-monthly fee records
    $excluded = ['Admission', 'Pre_Year', 'Prev-Year', 'Pre-Year', 'Yearly Package', 'Fine', 'Other', 'Other Payment'];
    if (in_array($month, $excluded) || strpos($month, 'Package') !== false) {
        return 0;
    }
    
    $month_time = strtotime("01-" . $month);
    if ($month_time === false) return 0;
    
    // Fine system start cutoff: August 2026 (01-Aug-2026)
    // No fine should be charged on any month prior to August 2026
    $fine_system_start_month = strtotime("2026-09-01");
    if ($month_time < $fine_system_start_month) {
        return 0;
    }
    
    // Standard due date: 10th of the fee month (e.g. 10-Aug-2026, 10-Sep-2026)
    $due_time = strtotime("10-" . $month);
    if ($due_time === false) return 0;
    
    $today_time = $today_date ? strtotime(date('Y-m-d', strtotime($today_date))) : strtotime(date('Y-m-d'));
    
    // Fine starts from 11th date of the month (i.e. strictly after 10th)
    if ($today_time > $due_time) {
        $late_days = intval(floor(($today_time - $due_time) / 86400));
        return $late_days * 20;
    }
    
    return 0;
}

/**
 * Calculate total late fine for a batch of cart items:
 * For EACH unique student in the cart, find that student's oldest eligible fee month (Aug 2026 onwards),
 * calculate that student's late fine (Rs. 20/day starting from 11th of their oldest overdue month),
 * and sum the fines across all students in the batch.
 */
function calculate_batch_late_fine($cart_items, $today_date = null) {
    if (empty($cart_items) || !is_array($cart_items)) return 0;
    
    $excluded = ['Admission', 'Pre_Year', 'Prev-Year', 'Pre-Year', 'Yearly Package', 'Fine', 'Other', 'Other Payment'];
    $fine_system_start_month = strtotime("2026-09-01");
    
    // Group cart items by student_id to find each student's oldest eligible month (Aug 2026 onwards)
    $student_oldest_month = [];
    
    foreach ($cart_items as $item) {
        $student_id = $item['student_id'] ?? 0;
        $month = $item['month'] ?? '';
        
        if (empty($month) || in_array($month, $excluded) || strpos($month, 'Package') !== false) {
            continue;
        }
        
        $m_time = strtotime("01-" . $month);
        if ($m_time !== false && $m_time >= $fine_system_start_month) {
            if (!isset($student_oldest_month[$student_id]) || $m_time < $student_oldest_month[$student_id]['time']) {
                $student_oldest_month[$student_id] = [
                    'time' => $m_time,
                    'month' => $month
                ];
            }
        }
    }
    
    $total_fine = 0;
    foreach ($student_oldest_month as $stud_id => $data) {
        $total_fine += calculate_month_late_fine($data['month'], $today_date);
    }
    
    return $total_fine;
}

?>
