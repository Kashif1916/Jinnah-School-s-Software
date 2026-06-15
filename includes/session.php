<?php
/**
 * Session Management
 * School Finance Management System
 */

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
