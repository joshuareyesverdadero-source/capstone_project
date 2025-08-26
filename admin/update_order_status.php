<?php
session_start();
include "../includes/db_connect.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    if (!isset($_POST['order_id']) || !isset($_POST['status'])) {
        die("Order ID or status is missing.");
    }

    $order_id = intval($_POST['order_id']);
    $status = trim($_POST['status']);

    // Validate status value
    $valid_statuses = ['Pending', 'Shipped', 'Delivered', 'Cancelled'];
    if (!in_array($status, $valid_statuses)) {
        die("Invalid status value.");
    }

    // If the status is being changed to "Cancelled," revert the stock
    if ($status === 'Cancelled') {
        $items_query = "SELECT product_id, quantity FROM order_items WHERE order_id = ?";
        $stmt = $conn->prepare($items_query);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $items_result = $stmt->get_result();

        while ($item = $items_result->fetch_assoc()) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];

            // Revert stock in the `products` table
            $revert_stock_query = "UPDATE products SET stock = stock + ? WHERE product_id = ?";
            $stmt = $conn->prepare($revert_stock_query);
            $stmt->bind_param("ii", $quantity, $product_id);

            if (!$stmt->execute()) {
                die("Failed to revert stock: " . $stmt->error);
            }
        }
    }

    // Update the order status
    $update_status_query = "UPDATE orders SET status = ? WHERE order_id = ?";
    $stmt = $conn->prepare($update_status_query);
    $stmt->bind_param("si", $status, $order_id);

    if ($stmt->execute()) {
        // Log only if admin (not superadmin)
        if ($_SESSION['role'] === 'admin') {
            include_once "../includes/log_action.php";
            // Optionally, fetch customer username for more detail
            $user_stmt = $conn->prepare("SELECT u.username FROM orders o JOIN users u ON o.user_id = u.user_id WHERE o.order_id = ?");
            $user_stmt->bind_param("i", $order_id);
            $user_stmt->execute();
            $user_stmt->bind_result($customer_username);
            $user_stmt->fetch();
            $user_stmt->close();

            logAdminAction(
                $conn,
                $_SESSION['user_id'],
                $_SESSION['role'],
                "Updated order #$order_id for customer '$customer_username' to status '$status'"
            );
        }
        $_SESSION['message'] = "Order status updated successfully!";
        header("Location: orders.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to update order status: " . $stmt->error;
        header("Location: orders.php");
        exit();
    }
} else {
    die("Invalid request method.");
}