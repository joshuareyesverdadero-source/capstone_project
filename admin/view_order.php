<?php
session_start();
include "../includes/db_connect.php";

// Check if the user is an admin or superadmin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Check if order_id is provided
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    die("Invalid or missing order ID.");
}

$order_id = $_GET['order_id'];

// Fetch order details
$order_query = "SELECT o.order_id, o.total_price, o.status, o.created_at, u.username 
                FROM orders o 
                JOIN users u ON o.user_id = u.user_id 
                WHERE o.order_id = ?";
$stmt = $conn->prepare($order_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_result = $stmt->get_result();

if ($order_result->num_rows === 0) {
    die("Order not found.");
}

$order = $order_result->fetch_assoc();

$items_query = "SELECT p.product_id, p.name, p.price, oi.quantity 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.product_id 
                WHERE oi.order_id = ?";
$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();

// Fetch payment info for this order
$payment_stmt = $conn->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY submitted_at DESC LIMIT 1");
$payment_stmt->bind_param("i", $order_id);
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();
$payment = $payment_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>View Order</title>
    <link rel="stylesheet" href="../assets/css/styles.css" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
    <link rel="stylesheet" href="../assets/css/admin.css" />
    <style>
        /* Override the admin.css padding-top for this page */
        body {
            padding-top: 100px !important;
        }
        
        /* Ensure the admin-container has proper spacing */
        .admin-container {
            margin-top: 20;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>Order Details</h1>
            <a href="orders.php" class="btn">← Back to Orders</a>
        </header>
        <main class="admin-main">
            <h2>Order #<?= htmlspecialchars($order['order_id']) ?></h2>
            <div class="order-details">
                <p><strong>Customer:</strong> <?= htmlspecialchars($order['username']) ?></p>
                <p><strong>Total Price:</strong> ₱<?= number_format($order['total_price'], 2) ?></p>
                <p><strong>Status:</strong> <span class="status <?= strtolower($order['status']) ?>"><?= htmlspecialchars($order['status']) ?></span></p>
                <p><strong>Created At:</strong> <?= htmlspecialchars($order['created_at']) ?></p>
            </div>

            <h3>Order Items</h3>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = $items_result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <a href="../products/edit_product.php?id=<?= htmlspecialchars($item['product_id']) ?>">
                                    <?= htmlspecialchars($item['name']) ?>
                                </a>
                            </td>
                            <td>₱<?= number_format($item['price'], 2) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td>₱<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <?php if ($payment): ?>
                <h3>GCash Payment Info</h3>
                <div style="background:#f7d3db;padding:18px;border-radius:8px;max-width:400px;">
                    <p><strong>GCash Name:</strong> <?= htmlspecialchars($payment['gcash_name']) ?></p>
                    <p><strong>GCash Number:</strong> <?= htmlspecialchars($payment['gcash_number']) ?></p>
                    <p><strong>Submitted At:</strong> <?= htmlspecialchars($payment['submitted_at']) ?></p>
                    <p><strong>Status:</strong> <span class="status <?= strtolower($payment['status']) ?>"><?= htmlspecialchars($payment['status']) ?></span></p>
                    <p><strong>Proof:</strong><br>
                        <a href="../assets/payments/<?= htmlspecialchars($payment['proof_image']) ?>" target="_blank">
                            <img src="../assets/payments/<?= htmlspecialchars($payment['proof_image']) ?>" alt="GCash Proof" style="max-width:180px;border:1px solid #ccc;">
                        </a>
                    </p>
                    <?php if ($payment['status'] === 'Pending'): ?>
                        <form method="POST" action="verify_payment.php" style="margin-top:16px;">
                            <input type="hidden" name="payment_id" value="<?= $payment['payment_id'] ?>">
                            <input type="hidden" name="order_id" value="<?= $order_id ?>">
                            <label>Tracking Number (if verifying):</label>
                            <input type="text" name="tracking_number" style="width:100%;margin-bottom:10px;">
                            <button type="submit" name="action" value="verify" class="btn" style="background:#28a745;">Verify & Ship</button>
                            <button type="submit" name="action" value="reject" class="btn" style="background:#dc3545;">Reject</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <h3>No payment submitted yet.</h3>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
