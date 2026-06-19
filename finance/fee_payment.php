<?php
/**
 * Fee Payment (Unified wrapper for Finance Panel)
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_finance(); // Enforces Finance permission

$panel_role = 'finance';
$back_url = 'dashboard.php';
$receipt_base_url = '../master/receipt.php';

require_once '../includes/fee_payment_core.php';
?>
