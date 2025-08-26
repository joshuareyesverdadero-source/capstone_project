<?php
session_start();
include "../includes/db_connect.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_GET['user_id'])) {
    echo json_encode([]);
    exit;
}
$user_id = intval($_GET['user_id']);

// Mark all messages from this user to any admin as read
$conn->query("UPDATE messages SET is_read = 1 WHERE sender_id = $user_id AND receiver_id IN (SELECT user_id FROM users WHERE role='admin')");

// Get all admin user_ids
$admin_ids = [];
$res = $conn->query("SELECT user_id FROM users WHERE role='admin'");
while ($row = $res->fetch_assoc()) {
    $admin_ids[] = $row['user_id'];
}
if (empty($admin_ids)) {
    echo json_encode([]);
    exit;
}
$admin_ids_placeholder = implode(',', array_fill(0, count($admin_ids), '?'));

// Build the query to fetch all messages between the selected user and any admin
$sql = "
    SELECT m.*, us.username AS sender_username, us.role AS sender_role
    FROM messages m
    JOIN users us ON m.sender_id = us.user_id
    WHERE (
        (m.sender_id IN ($admin_ids_placeholder) AND m.receiver_id = ?)
        OR
        (m.sender_id = ? AND m.receiver_id IN ($admin_ids_placeholder))
    )
    ORDER BY m.sent_at ASC
";
$stmt = $conn->prepare($sql);

// Bind parameters dynamically
$types = str_repeat('i', count($admin_ids)) . 'i' . 'i' . str_repeat('i', count($admin_ids));
$params = array_merge($admin_ids, [$user_id], [$user_id], $admin_ids);
$stmt->bind_param($types, ...$params);

$stmt->execute();
$res = $stmt->get_result();
$messages = [];
while ($row = $res->fetch_assoc()) {
    $messages[] = [
        'body' => nl2br(htmlspecialchars($row['body'])),
        'is_admin' => $row['sender_role'] === 'admin',
        'username' => htmlspecialchars($row['sender_username']),
        'sent_at' => $row['sent_at']
    ];
}
echo json_encode($messages);