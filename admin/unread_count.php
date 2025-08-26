<?php
session_start();
include "../includes/db_connect.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['count' => 0]);
    exit;
}

$res = $conn->query("
    SELECT COUNT(*) AS cnt
    FROM messages
    WHERE receiver_id IN (SELECT user_id FROM users WHERE role = 'admin')
      AND is_read = 0
      AND sender_id IN (SELECT user_id FROM users WHERE role = 'user')
");
$row = $res->fetch_assoc();
echo json_encode(['count' => (int)$row['cnt']]);