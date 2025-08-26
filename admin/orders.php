<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../auth/login.php");
    exit();
}

include "../includes/db_connect.php";

// Get the selected status from the query string
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Get the search term for customer name
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Modify the query to filter by status and/or customer name
$order_query = "SELECT o.order_id, o.total_price, o.status, o.created_at, u.username
                FROM orders o
                JOIN users u ON o.user_id = u.user_id";

$conditions = [];
$params = [];
$types = '';

if ($status_filter) {
    $conditions[] = "o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search) {
    $conditions[] = "u.username LIKE ?";
    $params[] = '%' . $search . '%';
    $types .= 's';
}

if (!empty($conditions)) {
    $order_query .= " WHERE " . implode(' AND ', $conditions);
}

$order_query .= " ORDER BY o.created_at DESC";

$stmt = $conn->prepare($order_query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$order_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Orders - Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
    <link rel="stylesheet" href="../assets/css/admin.css" />
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>Orders</h1>
            <a href="<?= ($_SESSION['role'] === 'superadmin') ? 'sadashboard.php' : 'dashboard.php' ?>" class="btn">Back to Dashboard</a>
        </header>
        <main class="admin-main">
            <h2>All Orders</h2>
            <form method="GET" action="orders.php" class="filter-form" style="margin-bottom: 20px;">
                <label for="status">Filter by Status:</label>
                <select name="status" id="status" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Shipped" <?= $status_filter === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                    <option value="Delivered" <?= $status_filter === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="Cancelled" <?= $status_filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>

                <label for="search" style="margin-left: 20px;">Search Customer:</label>
                <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by customer name..." />
                <button type="submit" class="btn">Search</button>
            </form>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="success-message"><?= $_SESSION['message'] ?></div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message"><?= $_SESSION['error'] ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Total Price</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = $order_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $order['order_id'] ?></td>
                            <td><?= htmlspecialchars($order['username']) ?></td>
                            <td>₱<?= number_format($order['total_price'], 2) ?></td>
                            <td><?= $order['status'] ?></td>
                            <td><?= $order['created_at'] ?></td>
                            <td>
                                <a href="view_order.php?order_id=<?= $order['order_id'] ?>" class="btn">View</a>
                                <form method="POST" action="update_order_status.php" style="display: inline;">
                                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>" />
                                    <select name="status" required>
                                        <option value="Pending" <?= $order['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="Shipped" <?= $order['status'] === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                        <option value="Delivered" <?= $order['status'] === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                        <option value="Cancelled" <?= $order['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                    <button type="submit" class="btn">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </main>
    </div>
</body>
</html>
