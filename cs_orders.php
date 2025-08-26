<?php
session_start();
include "includes/db_connect.php";

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Fetch orders for the logged-in user
$query = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Orders</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <header>
        <h1>Your Orders</h1>
        <a href="home.php" class="back-button">← Back to Home</a>
    </header>
    <main>
        <?php if (isset($_SESSION['message'])): ?>
            <div class="success-message"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if ($result->num_rows > 0): ?>
            <ul class="orders-list">
                <?php while ($order = $result->fetch_assoc()): ?>
                    <li>
                        <h3>Order #<?= htmlspecialchars($order['order_id']) ?></h3>
                        <p>Status: <strong><?= htmlspecialchars($order['status']) ?></strong></p>
                        <p>Total Price: ₱<?= number_format($order['total_price'], 2) ?></p>
                        <p>Order Date: <?= htmlspecialchars($order['created_at']) ?></p>
                        <?php if (!empty($order['tracking_number'])): ?>
                            <p>Tracking Number: <strong><?= htmlspecialchars($order['tracking_number']) ?></strong></p>
                        <?php endif; ?>
                        <a href="order_details.php?order_id=<?= $order['order_id'] ?>" class="btn">View Details</a>
                        <?php
                        // Check if payment exists for this order
                        $pay_stmt = $conn->prepare("SELECT COUNT(*) FROM payments WHERE order_id = ?");
                        $pay_stmt->bind_param("i", $order['order_id']);
                        $pay_stmt->execute();
                        $pay_stmt->bind_result($payment_count);
                        $pay_stmt->fetch();
                        $pay_stmt->close();
                        ?>
                        <?php if ($order['status'] === 'Pending' && $payment_count == 0): ?>
                            <form method="POST" action="delete_order.php" style="display:inline;">
                                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                <button type="submit" class="btn" style="background:#dc3545;margin-left:8px;" onclick="return confirm('Are you sure you want to delete this order?');">Delete</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p>You have no orders yet.</p>
        <?php endif; ?>
    </main>
</body>
</html>