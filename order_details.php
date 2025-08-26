<?php
session_start();
include "includes/db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$user_id = $_SESSION['user_id'];

// Fetch order and check ownership
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Order not found.");
}

// Fetch order items
$item_stmt = $conn->prepare("SELECT p.name, p.price, oi.quantity, oi.subtotal FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = ?");
$item_stmt->bind_param("i", $order_id);
$item_stmt->execute();
$items = $item_stmt->get_result();

// Fetch payment info
$pay_stmt = $conn->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY submitted_at DESC LIMIT 1");
$pay_stmt->bind_param("i", $order_id);
$pay_stmt->execute();
$payment = $pay_stmt->get_result()->fetch_assoc();

// Handle mark as delivered
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_delivered']) && $order['status'] === 'Shipped') {
    $upd = $conn->prepare("UPDATE orders SET status='Delivered' WHERE order_id=? AND user_id=?");
    $upd->bind_param("ii", $order_id, $user_id);
    $upd->execute();
    header("Location: order_details.php?order_id=$order_id");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Details</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .order-details-container {
            max-width: 700px;
            margin: 40px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(232,139,155,0.13);
            padding: 32px 32px 24px 32px;
        }
        .order-details-container h2 {
            color: #e88b9b;
            margin-top: 0;
        }
        .order-details-container table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .order-details-container th, .order-details-container td {
            border: 1px solid #eee;
            padding: 8px 10px;
            text-align: left;
        }
        .order-details-container th {
            background: #f7d3db;
        }
        .order-details-container .btn {
            margin-top: 18px;
        }
        .payment-proof-img {
            max-width: 180px;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-top: 8px;
        }
        .status {
            padding: 4px 12px;
            border-radius: 6px;
            color: #fff;
            font-weight: bold;
        }
        .status.Pending { background: #ffc107; color: #333; }
        .status.Shipped { background: #17a2b8; }
        .status.Delivered { background: #28a745; }
        .status.Cancelled { background: #dc3545; }
        .status.Verified { background: #28a745; }
        .status.Rejected { background: #dc3545; }
    </style>
</head>
<body>
    <header>
        <h1>Order Details</h1>
        <a href="cs_orders.php" class="back-button">← Back to Orders</a>
    </header>
    <main>
        <div class="order-details-container">
            <h2>Order #<?= htmlspecialchars($order['order_id']) ?></h2>
            <p>Status: <span class="status <?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars($order['status']) ?></span></p>
            <p>Total Price: ₱<?= number_format($order['total_price'], 2) ?></p>
            <p>Order Date: <?= htmlspecialchars($order['created_at']) ?></p>
            <?php if (!empty($order['tracking_number'])): ?>
                <p>Tracking Number: <strong><?= htmlspecialchars($order['tracking_number']) ?></strong></p>
            <?php endif; ?>

            <h3>Items</h3>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Unit Price</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = $items->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td>₱<?= number_format($item['price'], 2) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td>₱<?= number_format($item['subtotal'], 2) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <h3>Payment Info</h3>
            <?php if ($payment): ?>
                <p><strong>GCash Name:</strong> <?= htmlspecialchars($payment['gcash_name']) ?></p>
                <p><strong>GCash Number:</strong> <?= htmlspecialchars($payment['gcash_number']) ?></p>
                <p><strong>Status:</strong> <span class="status <?= htmlspecialchars($payment['status']) ?>"><?= htmlspecialchars($payment['status']) ?></span></p>
                <p><strong>Submitted At:</strong> <?= htmlspecialchars($payment['submitted_at']) ?></p>
                <p><strong>Proof:</strong><br>
                    <a href="assets/payments/<?= htmlspecialchars($payment['proof_image']) ?>" target="_blank">
                        <img src="assets/payments/<?= htmlspecialchars($payment['proof_image']) ?>" class="payment-proof-img" alt="GCash Proof">
                    </a>
                </p>
            <?php else: ?>
                <p>No payment submitted yet.</p>
            <?php endif; ?>

            <?php if ($order['status'] === 'Shipped'): ?>
                <form method="POST" style="margin-top:24px;">
                    <button type="submit" name="mark_delivered" class="btn" style="background:#28a745;">Mark as Delivered</button>
                </form>
            <?php elseif ($order['status'] === 'Delivered'): ?>
                <div class="success-message" style="margin-top:18px;">Thank you for confirming delivery!</div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>