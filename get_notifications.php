<?php
/**
 * Get Active Notifications for current user (AJAX)
 * School Finance Management System
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode([]);
    exit();
}

// Developers don't see notifications
if (get_user_role() === 'developer') {
    echo json_encode([]);
    exit();
}

$user_id = get_user_id();

// Get notifications not yet dismissed by this user
$stmt = $conn->prepare("
    SELECT dn.id, dn.title, dn.message, dn.type, dn.created_at, dn.created_by
    FROM developer_notifications dn
    WHERE dn.id NOT IN (
        SELECT nd.notification_id FROM notification_dismissals nd WHERE nd.user_id = ?
    )
    ORDER BY dn.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$notifications = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

echo json_encode($notifications);
