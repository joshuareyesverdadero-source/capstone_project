<?php
function logAdminAction($conn, $user_id, $role, $action) {
    if ($role !== 'admin') return; // Only log admins
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Agent';
    $stmt = $conn->prepare("INSERT INTO logs (user_id, role, action, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $role, $action, $ip_address, $user_agent);
    $stmt->execute();
}