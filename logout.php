<?php
/**
 * Logout Page
 * School Finance Management System
 */

require_once 'config/config.php';

session_start();
session_destroy();

header('Location: ' . BASE_URL . 'login.php');
exit();
?>
