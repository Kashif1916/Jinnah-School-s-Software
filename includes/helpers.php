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
    return date('d-m-Y H:i', strtotime($datetime));
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
 * Create fee records for new student for 12 months
 */
function create_annual_fees($student_id, $fixed_monthly_fee, $concession_amount = 0) {
    global $conn;
    $monthly_fee = floatval($fixed_monthly_fee) - floatval($concession_amount);
    if ($monthly_fee < 0) $monthly_fee = 0;

    for ($i = 0; $i < 12; $i++) {
        $month = date('M-Y', strtotime("+$i months"));
        $query = "INSERT INTO fee_records (student_id, month, amount, status) VALUES (?, ?, ?, 'unpaid')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('isd', $student_id, $month, $monthly_fee);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Automatically generate next 5 months of fees if unpaid months are 5 or less
 */
function auto_generate_fee_buffer($student_id, $monthly_fee) {
    global $conn;
    
    // Count current unpaid months
    $query = "SELECT COUNT(*) as unpaid_count FROM fee_records WHERE student_id = ? AND status = 'unpaid'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['unpaid_count'];
    $stmt->close();

    if ($count <= 5) {
        // Find the last generated month for this student
        $query = "SELECT month FROM fee_records WHERE student_id = ? ORDER BY STR_TO_DATE(CONCAT('01-', month), '%d-%b-%Y') DESC LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $last_month_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $start_date = $last_month_row ? strtotime("01-" . $last_month_row['month']) : time();
        
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
              WHERE student_id = ? AND status = 'unpaid' 
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
function get_defaulters($class = '', $section = '', $months = []) {
    global $conn;
    
    $query = "SELECT s.id, s.name, s.father_name, s.class, s.section, s.fixed_monthly_fee, s.monthly_fee, 
                     s.contact_number, s.contact_number2, s.whatsapp_number,
                     GROUP_CONCAT(f.month ORDER BY STR_TO_DATE(CONCAT('01-', f.month), '%d-%b-%Y')) as pending_months,
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
    
    if (!empty($months)) {
        if (!is_array($months)) $months = [$months];
        $escaped_months = array_map(function($m) use ($conn) { 
            return "'" . $conn->real_escape_string($m) . "'"; 
        }, $months);
        $query .= " AND f.month IN (" . implode(',', $escaped_months) . ")";
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
function record_payment($student_id, $amount, $month, $received_by) {
    global $conn;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
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
        $payment_date = date('Y-m-d H:i:s');

        // Record payment
        $query = "INSERT INTO payments (student_id, amount, paid_for_month, payment_date, received_by) 
                 VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('idsss', $student_id, $amount, $month, $payment_date, $received_by);
        $stmt->execute();
        $payment_id = $conn->insert_id;
        $stmt->close();
        
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

?>
