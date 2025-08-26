<?php
session_start();
include "../includes/db_connect.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || empty($_POST['reply_message']) || empty($_POST['reply_to_user'])) {
    echo json_encode(['success' => false]);
    exit;
}
$admin_id = $_SESSION['user_id'];
$reply_body = trim($_POST['reply_message']);
$reply_to_user = intval($_POST['reply_to_user']);

if ($reply_body && $reply_to_user) {
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, '', ?)");
    $stmt->bind_param("iis", $admin_id, $reply_to_user, $reply_body);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $ok]);
} else {
    echo json_encode(['success' => false]);
}