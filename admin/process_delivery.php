<?php
session_start();
include "../includes/db_connect.php";

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delivery_id'])) {
    $delivery_id = intval($_POST['delivery_id']);
    $selling_price = isset($_POST['selling_price']) ? floatval($_POST['selling_price']) : null;
    // Get delivery info
    $stmt = $conn->prepare("SELECT * FROM deliveries WHERE delivery_id=? AND status='Pending'");
    $stmt->bind_param("i", $delivery_id);
    $stmt->execute();
    $delivery = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($delivery && $selling_price !== null) {
        $cost_price = $delivery['total_price'] / $delivery['quantity'];
        // Add to product_batches
        $stmt = $conn->prepare("INSERT INTO product_batches (product_id, quantity, cost_price, selling_price, arrival_date, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param(
            "iidds",
            $delivery['product_id'],
            $delivery['quantity'],
            $cost_price,
            $selling_price,
            $delivery['delivery_date']
        );
        if (!$stmt->execute()) {
            $_SESSION['error'] = "Batch upload failed: " . $stmt->error;
            $stmt->close();
            header("Location: pending_deliveries.php");
            exit();
        }
        $stmt->close();

        // Mark delivery as processed
        $conn->query("UPDATE deliveries SET status='Processed' WHERE delivery_id=$delivery_id");

        // Optionally update product stock
        $sum = $conn->query("SELECT SUM(quantity) as total FROM product_batches WHERE product_id={$delivery['product_id']} AND status='active'")->fetch_assoc()['total'] ?? 0;
        $conn->query("UPDATE products SET stock=$sum WHERE product_id={$delivery['product_id']}");

        $_SESSION['message'] = "Delivery added as batch!";
    } else {
        $_SESSION['error'] = "Delivery not found or already processed.";
    }
    header("Location: pending_deliveries.php");
    exit();
}
header("Location: pending_deliveries.php");
exit();