<?php
/**
 * Session Management
 * School Finance Management System
 */

// Force session cookie to expire on browser close
ini_set('session.cookie_lifetime', 0);
ini_set('session.use_only_cookies', 1);

session_start();

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Check if user is Master
 */
function is_master() {
    return is_logged_in() && $_SESSION['role'] === 'master';
}

/**
 * Check if user is Finance
 */
function is_finance() {
    return is_logged_in() && $_SESSION['role'] === 'finance';
}

/**
 * Check if user is Admission
 */
function is_admission() {
    return is_logged_in() && $_SESSION['role'] === 'admission';
}

/**
 * Get current user ID
 */
function get_user_id() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Get current user role
 */
function get_user_role() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

/**
 * Get current username
 */
function get_username() {
    return isset($_SESSION['username']) ? $_SESSION['username'] : null;
}

/**
 * Login user
 */
function login_user($user_id, $username, $role) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time(); // Set initial activity time
}

/**
 * Logout user
 */
function logout_user() {
    session_destroy();
}

/**
 * Require login
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }

    // 30 Minutes Inactivity Timeout (30 * 60 = 1800 seconds)
    $timeout_duration = 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . 'login.php?error=timeout');
        exit();
    }
    $_SESSION['last_activity'] = time(); // Update activity time

    // Check if the logged-in user is frozen in DB
    global $conn;
    if (isset($conn) && isset($_SESSION['user_id'])) {
        $user_id = intval($_SESSION['user_id']);
        $query = "SELECT is_frozen, frozen_until FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($user) {
            $is_frozen = intval($user['is_frozen'] ?? 0);
            $frozen_until = $user['frozen_until'] ?? null;
            
            if ($is_frozen === 1) {
                // Check if current time is >= frozen_until
                if (!empty($frozen_until) && strtotime('now') >= strtotime($frozen_until)) {
                    // Automatically unfreeze!
                    $unfreeze_query = "UPDATE users SET is_frozen = 0, frozen_until = NULL WHERE id = ?";
                    $u_stmt = $conn->prepare($unfreeze_query);
                    $u_stmt->bind_param('i', $user_id);
                    $u_stmt->execute();
                    $u_stmt->close();
                } else {
                    // Still frozen! Terminate session and redirect
                    session_destroy();
                    header('Location: ' . BASE_URL . 'login.php?error=closed');
                    exit();
                }
            }
        }
    }
}

/**
 * Require master role
 */
function require_master() {
    require_login();
    if (!is_master()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}

/**
 * Require finance role
 */
function require_finance() {
    require_login();
    if (!is_finance()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}

/**
 * Require admission role
 */
function require_admission() {
    require_login();
    if (!is_admission()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}

?>
