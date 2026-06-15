<?php
/**
 * Main Index Page - Dashboard Router
 * School Finance Management System
 */

require_once 'config/config.php';
require_once 'config/db.php';

// Check if database needs to be set up
if (isset($redirect_to_setup) && $redirect_to_setup) {
    header('Location: ' . BASE_URL . 'setup.php');
    exit();
}

require_once 'includes/session.php';
require_once 'includes/helpers.php';

// Check if user is logged in
require_login();

// Route to appropriate dashboard
if (is_master()) {
    header('Location: ' . BASE_URL . 'master/dashboard.php');
} elseif (is_admission()) {
    header('Location: ' . BASE_URL . 'admission/add_student.php');
} elseif (is_finance()) {
    header('Location: ' . BASE_URL . 'finance/dashboard.php');
} else {
    header('Location: ' . BASE_URL . 'login.php');
}
exit();
?>
