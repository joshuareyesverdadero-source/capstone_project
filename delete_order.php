<?php
session_start();
include "includes/db_connect.php";

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cs_orders.php");
    exit();
}

$order_id = intval($_POST['order_id']);
$user_id = $_SESSION['user_id'];

// Check order ownership, status, and payment
$stmt = $conn->prepare("SELECT status FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$stmt->bind_result($status);
if (!$stmt->fetch()) {
    $stmt->close();
    $_SESSION['error'] = "Order not found.";
    header("Location: cs_orders.php");
    exit();
}
$stmt->close();

// Check for payment
$pay_stmt = $conn->prepare("SELECT COUNT(*) FROM payments WHERE order_id = ?");
$pay_stmt->bind_param("i", $order_id);
$pay_stmt->execute();
$pay_stmt->bind_result($payment_count);
$pay_stmt->fetch();
$pay_stmt->close();

if ($status !== 'Pending' || $payment_count > 0) {
    $_SESSION['error'] = "You can only delete pending orders with no payment.";
    header("Location: cs_orders.php");
    exit();
}

// Restore stock for each item
$item_stmt = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
$item_stmt->bind_param("i", $order_id);
$item_stmt->execute();
$item_result = $item_stmt->get_result();
while ($item = $item_result->fetch_assoc()) {
    $update = $conn->prepare("UPDATE products SET stock = stock + ? WHERE product_id = ?");
    $update->bind_param("ii", $item['quantity'], $item['product_id']);
    $update->execute();
    $update->close();
}
$item_stmt->close();

// Delete order items
$conn->query("DELETE FROM order_items WHERE order_id = $order_id");
// Delete order
$conn->query("DELETE FROM orders WHERE order_id = $order_id");

$_SESSION['message'] = "Order deleted successfully.";
header("Location: cs_orders.php");
exit();
?>