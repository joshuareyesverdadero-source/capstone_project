<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: auth/login.php");
    exit();
}

include "../includes/db_connect.php";

// Get total unread messages for all users (to any admin)
$unread_count = 0;
$res = $conn->query("
    SELECT COUNT(*) AS cnt
    FROM messages
    WHERE receiver_id IN (SELECT user_id FROM users WHERE role = 'admin')
      AND is_read = 0
      AND sender_id IN (SELECT user_id FROM users WHERE role = 'user')
");
if ($row = $res->fetch_assoc()) {
    $unread_count = (int)$row['cnt'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/styles1.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Admin Dashboard</h1>
            <div class="header-user-section">
                <div class="welcome-message">
                    Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>!
                </div>
                <a href="../auth/logout.php" class="logout-button">Logout</a>
            </div>
        </header>
        <main class="dashboard-main">
            <h2>Manage Your Shop</h2>
            <div class="dashboard-links">
                <a href="admin_add_product.php" class="dashboard-link">Add Product</a>
                <a href="../products/products.php" class="dashboard-link">View Products</a>
                <a href="orders.php" class="dashboard-link">View Orders</a>
                <a href="manage_users.php" class="dashboard-link">Manage Users</a>
                <a href="pending_deliveries.php" class="dashboard-link" style="background:#ffc107;color:#333;">Pending Deliveries</a>
                <a href="admin_categories.php" class="dashboard-link">Manage Categories</a>
                <a href="messages.php" class="dashboard-link">
                    View Messages
                    <span id="unread-badge" style="background:#e88b9b;color:#fff;border-radius:12px;padding:2px 8px;margin-left:8px;font-size:0.95em;<?= $unread_count > 0 ? '' : 'display:none;' ?>">
                        <?= $unread_count ?>
                    </span>
                </a>
            </div>
        </main>
    </div>
    <script>
        function updateUnreadBadge() {
            fetch('unread_count.php')
                .then(res => res.json())
                .then(data => {
                    const badge = document.getElementById('unread-badge');
                    if (badge) {
                        if (data.count > 0) {
                            badge.textContent = data.count;
                            badge.style.display = '';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                });
        }
        document.addEventListener('DOMContentLoaded', function() {
            updateUnreadBadge();
            setInterval(updateUnreadBadge, 5000); // Update every 5 seconds
        });
    </script>
</body>
</html>