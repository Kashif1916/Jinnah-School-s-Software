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
function create_annual_fees($student_id, $monthly_fee, $concession_amount = 0) {
    global $conn;
    
    $months = array_map(function($m) {
        return $m . '-' . date('Y');
    }, ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);
    
    foreach ($months as $month) {
        $amount = floatval($monthly_fee) - floatval($concession_amount);
        if ($amount < 0) $amount = 0;
        $query = "INSERT INTO fee_records (student_id, month, amount, status) VALUES (?, ?, ?, 'unpaid')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('isd', $student_id, $month, $amount);
        $stmt->execute();
        $stmt->close();
    }
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
function get_defaulters($class = '', $section = '', $month = '') {
    global $conn;
    
    $query = "SELECT DISTINCT s.id, s.name, s.father_name, s.class, s.section, s.monthly_fee, s.contact_number 
              FROM students s 
              INNER JOIN fee_records f ON s.id = f.student_id 
              WHERE s.status = 'active' AND f.status = 'unpaid'";
    
    if (!empty($class)) {
        $query .= " AND s.class = '" . escape_string($class) . "'";
    }
    
    if (!empty($section)) {
        $query .= " AND s.section = '" . escape_string($section) . "'";
    }
    
    if (!empty($month)) {
        $query .= " AND f.month = '" . escape_string($month) . "'";
    }
    
    $query .= " ORDER BY s.class, s.section, s.name";
    
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
