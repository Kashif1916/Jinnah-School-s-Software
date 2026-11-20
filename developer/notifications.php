<?php
/**
 * Developer Notifications Redirect to Master
 */
require_once '../config/config.php';
require_once '../includes/session.php';

require_login();
if (!is_developer()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

header('Location: ' . BASE_URL . 'master/notifications.php');
exit();
