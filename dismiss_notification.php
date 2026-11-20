<?php
/**
 * Notification Dismiss Handler (AJAX)
 * School Finance Management System
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id     = get_user_id();
$notif_id    = intval($_POST['notif_id'] ?? 0);

if ($notif_id <= 0 || !$user_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid']);
    exit();
}

// Insert dismissal (ignore duplicate)
$stmt = $conn->prepare("INSERT IGNORE INTO notification_dismissals (notification_id, user_id) VALUES (?, ?)");
$stmt->bind_param("ii", $notif_id, $user_id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok]);
