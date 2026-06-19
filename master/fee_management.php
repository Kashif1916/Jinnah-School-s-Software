<?php
/**
 * Fee Management (Unified wrapper for Master Panel)
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master(); // Enforces Master permission

$panel_role = 'master';
$back_url = 'dashboard.php';
$receipt_base_url = 'receipt.php';

require_once '../includes/fee_payment_core.php';
?>
