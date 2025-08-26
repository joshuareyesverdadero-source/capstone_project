<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: auth/login.php");
    exit();
}
include "../includes/db_connect.php";

$date_filter = $_GET['date_filter'] ?? 'all';
$date_condition = '';
switch ($date_filter) {
    case 'year':
        $date_condition = "AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        break;
    case 'month':
        $date_condition = "AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        break;
    case 'week':
        $date_condition = "AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
        break;
    case 'today':
        $date_condition = "AND DATE(o.created_at) = CURDATE()";
        break;
    case 'all':
    default:
        $date_condition = "";
        break;
}

// --- Sales Overview ---
$sales = $conn->query("SELECT SUM(total_price) AS revenue, COUNT(*) AS num_orders FROM orders o WHERE status='Delivered' $date_condition");
$sales_row = $sales->fetch_assoc();
$total_revenue = $sales_row['revenue'] ?? 0;
$num_orders = $sales_row['num_orders'] ?? 0;
$aov = $num_orders > 0 ? ($total_revenue / $num_orders) : 0;

// --- Top-Selling Products ---
$top_products = $conn->query("
    SELECT p.name, SUM(oi.quantity) AS qty_sold, SUM(oi.subtotal) AS revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    JOIN orders o ON oi.order_id = o.order_id
    WHERE o.status='Delivered' $date_condition
    GROUP BY p.product_id
    ORDER BY qty_sold DESC
    LIMIT 5
");

// --- Least-Selling Products ---
$least_products = $conn->query("
    SELECT p.name, COALESCE(SUM(oi.quantity),0) AS qty_sold, COALESCE(SUM(oi.subtotal),0) AS revenue
    FROM products p
    LEFT JOIN order_items oi ON oi.product_id = p.product_id
    LEFT JOIN orders o ON oi.order_id = o.order_id AND o.status='Delivered' $date_condition
    GROUP BY p.product_id
    ORDER BY qty_sold ASC
    LIMIT 5
");

// --- Top Customers ---
$top_customers = $conn->query("
    SELECT u.username, COUNT(o.order_id) AS orders_count, SUM(o.total_price) AS total_spent
    FROM users u
    JOIN orders o ON u.user_id = o.user_id
    WHERE o.status='Delivered' $date_condition
    GROUP BY u.user_id
    ORDER BY total_spent DESC
    LIMIT 5
");

// --- New vs Returning Customers ---
$new_customers = $conn->query("
    SELECT COUNT(*) AS new_count
    FROM (
        SELECT o.user_id
        FROM orders o
        WHERE o.status='Delivered' $date_condition
        GROUP BY o.user_id
        HAVING MIN(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
    ) t
");
$returning_customers = $conn->query("
    SELECT COUNT(*) AS returning_count
    FROM (
        SELECT o.user_id
        FROM orders o
        WHERE o.status='Delivered' $date_condition
        GROUP BY o.user_id
        HAVING MIN(o.created_at) < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
    ) t
");
$new_count = $new_customers ? ($new_customers->fetch_assoc()['new_count'] ?? 0) : 0;
$returning_count = $returning_customers ? ($returning_customers->fetch_assoc()['returning_count'] ?? 0) : 0;
$total_customers = $new_count + $returning_count;
$new_pct = $total_customers > 0 ? round(($new_count / $total_customers) * 100, 1) : 0;
$returning_pct = $total_customers > 0 ? round(($returning_count / $total_customers) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Analytics - Julie's RTW Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/analytics.css">
    <link rel="stylesheet" href="../assets/css/tabs.css">
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
            <h1>Financial Analytics</h1>
            <a href="sadashboard.php" class="btn">← Back to Dashboard</a>
        </header>
        <main class="admin-main">
            <form method="get" style="margin-bottom:24px;display:flex;align-items:center;gap:12px;">
                <label for="date_filter" style="font-weight:bold;color:#912323;">Show data for:</label>
                <select name="date_filter" id="date_filter" onchange="this.form.submit()" style="padding:6px 12px;border-radius:6px;border:1px solid #b0302f;">
                    <option value="all" <?= (!isset($_GET['date_filter']) || $_GET['date_filter'] === 'all') ? 'selected' : '' ?>>All Time</option>
                    <option value="year" <?= (isset($_GET['date_filter']) && $_GET['date_filter'] === 'year') ? 'selected' : '' ?>>Past Year</option>
                    <option value="month" <?= (isset($_GET['date_filter']) && $_GET['date_filter'] === 'month') ? 'selected' : '' ?>>Past Month</option>
                    <option value="week" <?= (isset($_GET['date_filter']) && $_GET['date_filter'] === 'week') ? 'selected' : '' ?>>Past Week</option>
                    <option value="today" <?= (isset($_GET['date_filter']) && $_GET['date_filter'] === 'today') ? 'selected' : '' ?>>Today</option>
                </select>
            </form>
            <!-- Tabs -->
            <div class="user-tabs">
                <button class="user-tab active" data-tab="sales">Sales Overview</button>
                <button class="user-tab" data-tab="product-performance">Product Performance</button>
                <button class="user-tab" data-tab="customers">Customers</button>
                <button class="user-tab" data-tab="other">Other Analytics</button>
            </div>
            <!-- Tab Contents -->
            <section class="user-table-section active" id="tab-sales">
                <div class="analytics-section">
                    <h2>Sales Overview</h2>
                    <p><strong>Total Revenue:</strong> ₱<?= number_format($total_revenue,2) ?></p>
                    <p><strong>Number of Orders:</strong> <?= $num_orders ?></p>
                    <p><strong>Average Order Value:</strong> ₱<?= number_format($aov,2) ?></p>
                </div>
            </section>
            <section class="user-table-section" id="tab-product-performance">
                <div class="analytics-section">
                    <h2>Top-Selling Products</h2>
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $top_products->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= $row['qty_sold'] ?></td>
                                <td>₱<?= number_format($row['revenue'],2) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="analytics-section">
                    <h2>Least-Selling Products</h2>
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $least_products->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= $row['qty_sold'] ?></td>
                                <td>₱<?= number_format($row['revenue'],2) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="user-table-section" id="tab-customers">
                <div class="analytics-section">
                    <h2>Top Customers</h2>
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $top_customers->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td><?= $row['orders_count'] ?></td>
                                <td>₱<?= number_format($row['total_spent'],2) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            </section>
            <section class="user-table-section" id="tab-other">
                <!-- Other Analytics content -->
            </section>
        </main>
    </div>
    <script>
    document.querySelectorAll('.user-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.user-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.user-table-section').forEach(sec => sec.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('tab-' + this.dataset.tab).classList.add('active');
        });
    });
    </script>
</body>
</html>