<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: auth/login.php");
    exit();
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
                <a href="create_admin.php" class="dashboard-link">Create Admin Account</a>
                <a href="logs.php" class="dashboard-link">View Logs</a>
                <a href="financial_analytics.php" class="dashboard-link">Financial Analytics</a>
                <a href="pending_deliveries.php" class="dashboard-link" style="background:#ffc107;color:#333;">Pending Deliveries</a>
                <a href="admin_categories.php" class="dashboard-link">Manage Categories</a>
            </div>
        </main>
    </div>
</body>
</html>