<?php
session_start();
include "../includes/db_connect.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([]);
    exit;
}

// Get all users who have messaged any admin, with unread count
$sql = "
    SELECT 
        u.user_id, 
        u.username, 
        MAX(m.sent_at) AS last_msg,
        SUM(CASE 
            WHEN m.sender_id = u.user_id 
                 AND m.receiver_id IN (SELECT user_id FROM users WHERE role='admin')
                 AND m.is_read = 0
            THEN 1 ELSE 0 END) AS unread_count
    FROM messages m
    JOIN users u ON (u.user_id = m.sender_id OR u.user_id = m.receiver_id)
    WHERE (
        (m.sender_id IN (SELECT user_id FROM users WHERE role = 'admin') AND u.role = 'user')
        OR
        (m.receiver_id IN (SELECT user_id FROM users WHERE role = 'admin') AND u.role = 'user')
    )
    GROUP BY u.user_id, u.username
    ORDER BY last_msg DESC
";
$res = $conn->query($sql);
$users = [];
while ($row = $res->fetch_assoc()) {
    $users[] = [
        'user_id' => $row['user_id'],
        'username' => $row['username'],
        'last_msg' => $row['last_msg'],
        'unread_count' => (int)$row['unread_count']
    ];
}
echo json_encode($users);