<?php
session_start();
include "../includes/db_connect.php";

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_id = intval($_POST['payment_id']);
    $order_id = intval($_POST['order_id']);
    $action = $_POST['action'];
    $tracking_number = trim($_POST['tracking_number'] ?? '');

    if ($action === 'verify') {
        // Mark payment as verified
        $stmt = $conn->prepare("UPDATE payments SET status='Verified' WHERE payment_id=?");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();

        // Update order status to Shipped and set tracking number
        $stmt2 = $conn->prepare("UPDATE orders SET status='Shipped', tracking_number=? WHERE order_id=?");
        $stmt2->bind_param("si", $tracking_number, $order_id);
        $stmt2->execute();

        $_SESSION['message'] = "Payment verified and order marked as shipped.";
    } elseif ($action === 'reject') {
        // Mark payment as rejected
        $stmt = $conn->prepare("UPDATE payments SET status='Rejected' WHERE payment_id=?");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();

        $_SESSION['message'] = "Payment rejected.";
    }
    header("Location: view_order.php?order_id=$order_id");
    exit();
} else {
    header("Location: orders.php");
    exit();
}
?>