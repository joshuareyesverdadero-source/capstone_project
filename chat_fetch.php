<?php
session_start();
include "includes/db_connect.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user_id'];
// Get the admin user_id (first admin)
$admin = $conn->query("SELECT user_id FROM users WHERE role='admin' LIMIT 1")->fetch_assoc();
$admin_id = $admin ? $admin['user_id'] : null;

if (!$admin_id) {
    echo json_encode([]);
    exit;
}

// Fetch messages between this user and the admin
$stmt = $conn->prepare("SELECT * FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY sent_at ASC");
$stmt->bind_param("iiii", $user_id, $admin_id, $admin_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
$messages = [];
while ($row = $res->fetch_assoc()) {
    $messages[] = [
        'body' => $row['body'],
        'is_user' => $row['sender_id'] == $user_id
    ];
}
echo json_encode($messages);