<?php
session_start();
include "includes/db_connect.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_POST['message'])) {
    echo json_encode(['success' => false]);
    exit;
}

$user_id = $_SESSION['user_id'];
$message = trim($_POST['message']);

// Get the admin user_id (first admin)
$admin = $conn->query("SELECT user_id FROM users WHERE role='admin' LIMIT 1")->fetch_assoc();
$admin_id = $admin ? $admin['user_id'] : null;

if (!$admin_id || !$message) {
    echo json_encode(['success' => false]);
    exit;
}

// Insert message (from user to admin)
$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, '', ?)");
$stmt->bind_param("iis", $user_id, $admin_id, $message);
$ok = $stmt->execute();

echo json_encode(['success' => $ok]);