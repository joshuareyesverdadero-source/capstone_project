<?php
session_start();
include "../includes/db_connect.php";
include_once "../includes/log_action.php"; // <-- Add this line

// Handle Set Supplier action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_supplier_product_id'], $_POST['supplier_id'])) {
    $pid = intval($_POST['set_supplier_product_id']);
    $sid = intval($_POST['supplier_id']);

    // Get product name for logging
    $stmt_name = $conn->prepare("SELECT name FROM products WHERE product_id=?");
    $stmt_name->bind_param("i", $pid);
    $stmt_name->execute();
    $stmt_name->bind_result($product_name);
    $stmt_name->fetch();
    $stmt_name->close();

    // Get supplier username for logging
    $stmt_sup = $conn->prepare("SELECT username FROM users WHERE user_id=?");
    $stmt_sup->bind_param("i", $sid);
    $stmt_sup->execute();
    $stmt_sup->bind_result($supplier_username);
    $stmt_sup->fetch();
    $stmt_sup->close();

    $stmt = $conn->prepare("UPDATE products SET supplier_id=? WHERE product_id=?");
    $stmt->bind_param("ii", $sid, $pid);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Supplier set successfully!";
        // Log the action
        if (in_array($_SESSION['role'], ['admin', 'superadmin'])) {
            $role = $_SESSION['role'];
            $user_id = $_SESSION['user_id'];
            $action = "Set supplier for product: $product_name (ID: $pid) to $supplier_username (ID: $sid)";
            logAdminAction($conn, $user_id, $role, $action);
        }
    } else {
        $_SESSION['error'] = "Failed to set supplier.";
    }
    header("Location: products.php");
    exit();
}

// Get the selected filter option
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'most_sold';

// Get the search term
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category_filter']) ? $_GET['category_filter'] : '';
$stock_filter = isset($_GET['stock_filter']) ? $_GET['stock_filter'] : '';

// Build the search conditions
$conditions = [];
if ($search) {
    $conditions[] = "p.name LIKE '%" . $conn->real_escape_string($search) . "%'";
}
if ($category_filter) {
    $conditions[] = "p.category_id = " . intval($category_filter);
}
if ($stock_filter === 'in_stock') {
    $conditions[] = "p.stock > 0";
} elseif ($stock_filter === 'low_stock') {
    $conditions[] = "p.stock <= 5 AND p.stock > 0";
} elseif ($stock_filter === 'out_of_stock') {
    $conditions[] = "p.stock = 0";
}

$searchCondition = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : '';

switch ($filter) {
    case 'price_high_low':
        $query = "
            SELECT p.*, 
                   COALESCE(SUM(oi.quantity), 0) AS total_sold,
                   u.username AS supplier_name,
                   c.category_name
            FROM products p
            LEFT JOIN order_items oi ON p.product_id = oi.product_id
            LEFT JOIN users u ON p.supplier_id = u.user_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            $searchCondition
            GROUP BY p.product_id
            ORDER BY p.price DESC
        ";
        break;
    case 'price_low_high':
        $query = "
            SELECT p.*, 
                   COALESCE(SUM(oi.quantity), 0) AS total_sold,
                   u.username AS supplier_name,
                   c.category_name
            FROM products p
            LEFT JOIN order_items oi ON p.product_id = oi.product_id
            LEFT JOIN users u ON p.supplier_id = u.user_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            $searchCondition
            GROUP BY p.product_id
            ORDER BY p.price ASC
        ";
        break;
    case 'stock_high_low':
        $query = "
            SELECT p.*, 
                   COALESCE(SUM(oi.quantity), 0) AS total_sold,
                   u.username AS supplier_name,
                   c.category_name
            FROM products p
            LEFT JOIN order_items oi ON p.product_id = oi.product_id
            LEFT JOIN users u ON p.supplier_id = u.user_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            $searchCondition
            GROUP BY p.product_id
            ORDER BY p.stock DESC
        ";
        break;
    case 'stock_low_high':
        $query = "
            SELECT p.*, 
                   COALESCE(SUM(oi.quantity), 0) AS total_sold,
                   u.username AS supplier_name,
                   c.category_name
            FROM products p
            LEFT JOIN order_items oi ON p.product_id = oi.product_id
            LEFT JOIN users u ON p.supplier_id = u.user_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            $searchCondition
            GROUP BY p.product_id
            ORDER BY p.stock ASC
        ";
        break;
    case 'least_sold':
        $query = "
            SELECT p.*, 
                   COALESCE(SUM(oi.quantity), 0) AS total_sold,
                   u.username AS supplier_name,
                   c.category_name
            FROM products p
            LEFT JOIN order_items oi ON p.product_id = oi.product_id
            LEFT JOIN users u ON p.supplier_id = u.user_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            $searchCondition
            GROUP BY p.product_id
            ORDER BY total_sold ASC
        ";
        break;
    case 'name_az':
        $query = "
            SELECT p.*, 
                   COALESCE(SUM(oi.quantity), 0) AS total_sold,
                   u.username AS supplier_name,
                   c.category_name
            FROM products p
            LEFT JOIN order_items oi ON p.product_id = oi.product_id
            LEFT JOIN users u ON p.supplier_id = u.user_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            $searchCondition
            GROUP BY p.product_id
            ORDER BY p.name ASC
        ";
        break;
    case 'name_za':
        $query = "
            SELECT p.*, 
                   COALESCE(SUM(oi.quantity), 0) AS total_sold,
                   u.username AS supplier_name,
                   c.category_name
            FROM products p
            LEFT JOIN order_items oi ON p.product_id = oi.product_id
            LEFT JOIN users u ON p.supplier_id = u.user_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            $searchCondition
            GROUP BY p.product_id
            ORDER BY p.name DESC
        ";
        break;
    case 'most_sold':
    default:
        $query = "
            SELECT p.*, 
                   COALESCE(SUM(oi.quantity), 0) AS total_sold,
                   u.username AS supplier_name,
                   c.category_name
            FROM products p
            LEFT JOIN order_items oi ON p.product_id = oi.product_id
            LEFT JOIN users u ON p.supplier_id = u.user_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            $searchCondition
            GROUP BY p.product_id
            ORDER BY total_sold DESC
        ";
        break;
}

$result = $conn->query($query);

// Fetch all suppliers for the dropdown
$suppliers = [];
$sup_res = $conn->query("SELECT user_id, username FROM users WHERE role='supplier' ORDER BY username ASC");
while ($sup = $sup_res->fetch_assoc()) {
    $suppliers[] = $sup;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin - View Products</title>
    <link rel="stylesheet" href="../assets/css/styles.css" />
    <link rel="stylesheet" href="../assets/css/admin.css" />
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>View Products</h1>
            <a href="<?= ($_SESSION['role'] === 'superadmin') ? '../admin/sadashboard.php' : '../admin/dashboard.php' ?>" class="btn">Back to Dashboard</a>
        </header>
        <main class="admin-main products-main">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="success-message"><?= $_SESSION['message'] ?></div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message"><?= $_SESSION['error'] ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <h2>All Products</h2>

            <!-- Product Statistics -->
            <?php
            $stats_query = "
                SELECT 
                    COUNT(*) as total_products,
                    SUM(stock) as total_stock,
                    COUNT(CASE WHEN stock = 0 THEN 1 END) as out_of_stock,
                    COUNT(CASE WHEN stock <= 5 AND stock > 0 THEN 1 END) as low_stock,
                    AVG(price) as avg_price
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.category_id
                " . str_replace('WHERE', 'WHERE', $searchCondition) . "
            ";
            $stats_result = $conn->query($stats_query);
            $stats = $stats_result->fetch_assoc();
            ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div style="background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 20px; border-radius: 8px; text-align: center;">
                    <h4 style="margin: 0 0 10px 0;">Total Products</h4>
                    <p style="font-size: 2em; margin: 0; font-weight: bold;"><?= $stats['total_products'] ?></p>
                </div>
                <div style="background: linear-gradient(135deg, #28a745, #1e7e34); color: white; padding: 20px; border-radius: 8px; text-align: center;">
                    <h4 style="margin: 0 0 10px 0;">Total Stock</h4>
                    <p style="font-size: 2em; margin: 0; font-weight: bold;"><?= number_format($stats['total_stock']) ?></p>
                </div>
                <div style="background: linear-gradient(135deg, #ffc107, #d39e00); color: white; padding: 20px; border-radius: 8px; text-align: center;">
                    <h4 style="margin: 0 0 10px 0;">Low Stock</h4>
                    <p style="font-size: 2em; margin: 0; font-weight: bold;"><?= $stats['low_stock'] ?></p>
                </div>
                <div style="background: linear-gradient(135deg, #dc3545, #bd2130); color: white; padding: 20px; border-radius: 8px; text-align: center;">
                    <h4 style="margin: 0 0 10px 0;">Out of Stock</h4>
                    <p style="font-size: 2em; margin: 0; font-weight: bold;"><?= $stats['out_of_stock'] ?></p>
                </div>
                <div style="background: linear-gradient(135deg, #6f42c1, #563d7c); color: white; padding: 20px; border-radius: 8px; text-align: center;">
                    <h4 style="margin: 0 0 10px 0;">Avg Price</h4>
                    <p style="font-size: 1.5em; margin: 0; font-weight: bold;">₱<?= number_format($stats['avg_price'], 2) ?></p>
                </div>
            </div>

            <!-- Filter and Search Form -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <form method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: end;">
                    <div>
                        <label for="filter" style="display: block; margin-bottom: 5px; font-weight: bold;">Sort By:</label>
                        <select name="filter" id="filter" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="most_sold" <?= $filter === 'most_sold' ? 'selected' : '' ?>>Most Sold to Least</option>
                            <option value="least_sold" <?= $filter === 'least_sold' ? 'selected' : '' ?>>Least Sold to Most</option>
                            <option value="price_high_low" <?= $filter === 'price_high_low' ? 'selected' : '' ?>>Highest Price to Lowest</option>
                            <option value="price_low_high" <?= $filter === 'price_low_high' ? 'selected' : '' ?>>Lowest Price to Highest</option>
                            <option value="stock_high_low" <?= $filter === 'stock_high_low' ? 'selected' : '' ?>>Highest Stock to Lowest</option>
                            <option value="stock_low_high" <?= $filter === 'stock_low_high' ? 'selected' : '' ?>>Lowest Stock to Highest</option>
                            <option value="name_az" <?= $filter === 'name_az' ? 'selected' : '' ?>>Name A-Z</option>
                            <option value="name_za" <?= $filter === 'name_za' ? 'selected' : '' ?>>Name Z-A</option>
                        </select>
                    </div>

                    <div>
                        <label for="search" style="display: block; margin-bottom: 5px; font-weight: bold;">Search:</label>
                        <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Search products..." style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 200px;" />
                    </div>
                    
                    <div>
                        <label for="category_filter" style="display: block; margin-bottom: 5px; font-weight: bold;">Category:</label>
                        <select name="category_filter" id="category_filter" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">All Categories</option>
                            <?php
                            $category_filter = $_GET['category_filter'] ?? '';
                            $cat_query = "SELECT DISTINCT category_id, category_name FROM categories ORDER BY category_name";
                            $cat_result = $conn->query($cat_query);
                            while ($cat = $cat_result->fetch_assoc()) {
                                $selected = $category_filter == $cat['category_id'] ? 'selected' : '';
                                echo "<option value='{$cat['category_id']}' {$selected}>" . htmlspecialchars($cat['category_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label for="stock_filter" style="display: block; margin-bottom: 5px; font-weight: bold;">Stock Status:</label>
                        <select name="stock_filter" id="stock_filter" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">All Stock Levels</option>
                            <option value="in_stock" <?= ($_GET['stock_filter'] ?? '') === 'in_stock' ? 'selected' : '' ?>>In Stock (>0)</option>
                            <option value="low_stock" <?= ($_GET['stock_filter'] ?? '') === 'low_stock' ? 'selected' : '' ?>>Low Stock (≤5)</option>
                            <option value="out_of_stock" <?= ($_GET['stock_filter'] ?? '') === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock (0)</option>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="btn" style="background-color: #007bff; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">
                             Apply Filters
                        </button>
                        <a href="products.php" class="btn" style="background-color: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; margin-left: 8px;">
                             Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Quick Actions Toolbar -->
            <div style="background: #ffffff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; color: #495057;">Product Management</h3>
                    <p style="margin: 5px 0 0 0; color: #6c757d; font-size: 0.9em;">Manage your product inventory and details</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="../admin/admin_add_product.php" class="btn" style="background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: flex; align-items: center; gap: 8px;">
                         Add New Product
                    </a>
                    <a href="../admin/admin_categories.php" class="btn" style="background-color: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: flex; align-items: center; gap: 8px;">
                         Manage Categories
                    </a>
                </div>
            </div>

            <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                        <tr>
                            <th style="padding: 15px 10px; text-align: left; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Image</th>
                            <th style="padding: 15px 10px; text-align: left; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Product Name</th>
                            <th style="padding: 15px 10px; text-align: left; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Category</th>
                            <th style="padding: 15px 10px; text-align: left; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Price</th>
                            <th style="padding: 15px 10px; text-align: left; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Stock<br><span style="font-weight:normal;font-size:0.95em;">(Varieties & Sizes)</span></th>
                            <th style="padding: 15px 10px; text-align: left; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Total Sold</th>
                            <th style="padding: 15px 10px; text-align: left; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Supplier</th>
                            <th style="padding: 15px 10px; text-align: left; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $result->data_seek(0); while ($row = $result->fetch_assoc()) { ?>
                            <tr style="border-bottom: 1px solid #dee2e6; transition: background-color 0.2s ease;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor=''">
                                <td style="padding: 15px 10px; vertical-align: top;">
                                    <img src="../<?= htmlspecialchars($row['image_url']) ?>" alt="<?= htmlspecialchars($row['name']) ?>" style="max-width: 80px; max-height: 80px; border-radius: 8px; object-fit: cover;">
                                </td>
                                <td style="padding: 15px 10px; vertical-align: top;">
                                    <strong><?= htmlspecialchars($row['name']) ?></strong>
                                    <br><small style="color: #666;"><?= htmlspecialchars(substr($row['description'], 0, 100)) ?><?= strlen($row['description']) > 100 ? '...' : '' ?></small>
                                </td>
                                <td style="padding: 15px 10px; vertical-align: top;">
                                    <span style="background-color: #e9ecef; padding: 4px 8px; border-radius: 12px; font-size: 0.85em;">
                                        <?= htmlspecialchars($row['category_name'] ?? 'No Category') ?>
                                    </span>
                                </td>
                                <td style="padding: 15px 10px; vertical-align: top;">
                                    <span style="font-weight: bold; color: #28a745;">₱<?= number_format($row['price'], 2) ?></span>
                                </td>
                                <td style="padding: 15px 10px; vertical-align: top;">
                                    <span style="font-weight:bold;">Total:</span> <?= $row['stock'] ?>
<?php
// Check if product_varieties table exists before querying
$table_check = $conn->query("SHOW TABLES LIKE 'product_varieties'");
if ($table_check && $table_check->num_rows > 0) {
    // Fetch varieties and their sizes for this product
    $varieties_stmt = $conn->prepare("
        SELECT 
            pv.variety_name,
            pvs.size,
            pvs.quantity
        FROM product_varieties pv
        LEFT JOIN product_variety_sizes pvs ON pv.variety_id = pvs.variety_id
        WHERE pv.product_id = ?
        ORDER BY pv.variety_name, pvs.size
    ");
    $varieties_stmt->bind_param("i", $row['product_id']);
    $varieties_stmt->execute();
    $varieties_res = $varieties_stmt->get_result();
    
    $varieties_data = [];
    $has_varieties = false;
    while ($variety = $varieties_res->fetch_assoc()) {
        if ($variety['variety_name']) {
            $has_varieties = true;
            $variety_name = $variety['variety_name'];
            if (!isset($varieties_data[$variety_name])) {
                $varieties_data[$variety_name] = [];
            }
            if ($variety['size'] && $variety['quantity'] > 0) {
                $varieties_data[$variety_name][$variety['size']] = $variety['quantity'];
            }
        }
    }
    $varieties_stmt->close();
    
    if ($has_varieties && !empty($varieties_data)) {
        echo "<div style='margin-top:6px;font-size:0.95em;color:#666;line-height:1.4;'>";
        echo "<span style='display:block;font-weight:bold;color:#333;'>Varieties & Sizes:</span>";
        foreach ($varieties_data as $variety_name => $sizes) {
            echo "<div style='margin-left:8px;margin-top:4px;'>";
            echo "<span style='font-weight:bold;color:#555;'>" . htmlspecialchars($variety_name) . ":</span>";
            if (!empty($sizes)) {
                foreach ($sizes as $size => $qty) {
                    $color = $qty > 5 ? '#28a745' : ($qty > 0 ? '#ffc107' : '#dc3545');
                    echo "<span style='display:inline-block;margin-left:6px;padding:2px 6px;background-color:{$color};color:white;border-radius:3px;font-size:0.85em;'>";
                    echo htmlspecialchars($size) . ": " . $qty;
                    echo "</span>";
                }
            } else {
                echo "<span style='margin-left:6px;color:#888;font-style:italic;'>No sizes available</span>";
            }
            echo "</div>";
        }
        echo "</div>";
    } else {
        // Fallback to legacy sizes
        $sizes_stmt = $conn->prepare("SELECT size, quantity FROM product_sizes WHERE product_id = ?");
        $sizes_stmt->bind_param("i", $row['product_id']);
        $sizes_stmt->execute();
        $sizes_res = $sizes_stmt->get_result();
        $sizes_arr = [];
        while ($sz = $sizes_res->fetch_assoc()) {
            $color = $sz['quantity'] > 5 ? '#28a745' : ($sz['quantity'] > 0 ? '#ffc107' : '#dc3545');
            $sizes_arr[] = "<span style='display:inline-block;margin-left:6px;padding:2px 6px;background-color:{$color};color:white;border-radius:3px;font-size:0.85em;'>" . htmlspecialchars($sz['size']) . ": " . $sz['quantity'] . "</span>";
        }
        if ($sizes_arr) {
            echo "<div style='margin-top:6px;font-size:0.95em;color:#666;line-height:1.4;'>";
            echo "<span style='display:block;font-weight:bold;color:#333;'>Legacy Sizes:</span>";
            echo "<div style='margin-left:8px;margin-top:4px;'>";
            echo implode('', $sizes_arr);
            echo "</div>";
            echo "</div>";
        }
        $sizes_stmt->close();
    }
} else {
    // Only show legacy sizes if product_varieties table does not exist
    $sizes_stmt = $conn->prepare("SELECT size, quantity FROM product_sizes WHERE product_id = ?");
    $sizes_stmt->bind_param("i", $row['product_id']);
    $sizes_stmt->execute();
    $sizes_res = $sizes_stmt->get_result();
    $sizes_arr = [];
    while ($sz = $sizes_res->fetch_assoc()) {
        $color = $sz['quantity'] > 5 ? '#28a745' : ($sz['quantity'] > 0 ? '#ffc107' : '#dc3545');
        $sizes_arr[] = "<span style='display:inline-block;margin-left:6px;padding:2px 6px;background-color:{$color};color:white;border-radius:3px;font-size:0.85em;'>" . htmlspecialchars($sz['size']) . ": " . $sz['quantity'] . "</span>";
    }
    if ($sizes_arr) {
        echo "<div style='margin-top:6px;font-size:0.95em;color:#666;line-height:1.4;'>";
        echo "<span style='display:block;font-weight:bold;color:#333;'>Legacy Sizes:</span>";
        echo "<div style='margin-left:8px;margin-top:4px;'>";
        echo implode('', $sizes_arr);
        echo "</div>";
        echo "</div>";
    }
    $sizes_stmt->close();
}
?>
</td>
                                <td style="padding: 15px 10px; vertical-align: top;">
                                    <span style="background-color: #007bff; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.9em;">
                                        <?= $row['total_sold'] ?> units
                                    </span>
                                </td>
                                <td style="padding: 15px 10px; vertical-align: top;">
                                    <?php if (!empty($row['supplier_name'])): ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="background-color: #28a745; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.85em;">
                                                <?= htmlspecialchars($row['supplier_name']) ?>
                                            </span>
                                            <button type="button"
                                                class="btn"
                                                style="background:#ffc107;color:#333;padding:4px 8px;border-radius:4px;font-size:0.8em;border:none;cursor:pointer;"
                                                onclick="openSupplierModal(<?= $row['product_id'] ?>)"
                                                title="Change Supplier">
                                                Change
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <button type="button" class="btn" style="background-color:#dc3545;color:white;padding:6px 12px;border-radius:4px;font-size:0.85em;" onclick="openSupplierModal(<?= $row['product_id'] ?>)">Set Supplier</button>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 15px 10px; vertical-align: top;">
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <div style="display: flex; gap: 4px;">
                                            <a href="../viewproduct.php?id=<?= $row['product_id'] ?>" class="btn" style="background-color:#17a2b8;font-size:0.8em;padding:6px 10px;" title="View Product"> View</a>
                                            <a href="edit_product.php?id=<?= $row['product_id'] ?>" class="btn" style="background-color:#ffc107;color:#333;font-size:0.8em;padding:6px 10px;" title="Edit Product"> Edit</a>
                                        </div>
                                        <div style="display: flex; gap: 4px;">
                                            <a href="add_batch.php?product_id=<?= $row['product_id'] ?>" class="btn" style="background-color:#28a745;font-size:0.8em;padding:6px 10px;" title="Add Batch"> Batch</a>
                                            <a href="javascript:void(0);" class="btn" style="background-color:#6f42c1;color:white;font-size:0.8em;padding:6px 10px;" onclick="openBatchModal(<?= $row['product_id'] ?>)" title="View Batches"> View</a>
                                        </div>
                                        <div style="display: flex; gap: 4px;">
                                            <a href="delete_product.php?id=<?= $row['product_id'] ?>" class="btn" style="background-color: #dc3545;font-size:0.8em;padding:6px 10px;" onclick="return confirm('Are you sure you want to delete this product?');" title="Delete Product"> Delete</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

        <!-- Show result count -->
        <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; text-align: center; color: #6c757d;">
            Showing <?= $result->num_rows ?> product(s)
            <?php if (!empty($search) || !empty($category_filter) || !empty($stock_filter)): ?>
                - <a href="products.php" style="color: #007bff; text-decoration: none;">Clear filters to see all products</a>
            <?php endif; ?>
        </div>
        </main>
    </div>

    <div id="supplierModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.3);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;padding:24px 32px;border-radius:8px;max-width:350px;width:90%;box-shadow:0 2px 8px rgba(0,0,0,0.12);position:relative;">
            <h3>Set Supplier</h3>
            <form method="POST" id="setSupplierForm" action="products.php" style="display:flex;flex-direction:column;gap:12px;">
                <input type="hidden" name="set_supplier_product_id" id="set_supplier_product_id">
                <label for="supplier_id">Select Supplier:</label>
                <select name="supplier_id" id="supplier_id" required>
                    <option value="">-- Select Supplier --</option>
                    <?php foreach ($suppliers as $sup): ?>
                        <option value="<?= $sup['user_id'] ?>"><?= htmlspecialchars($sup['username']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" onclick="closeSupplierModal()" style="background:#888;">Cancel</button>
                    <button type="submit" style="background:#17a2b8;">Set</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Batch Modal -->
    <div id="batchModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.3);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;padding:24px 32px;border-radius:8px;max-width:600px;width:95%;box-shadow:0 2px 8px rgba(0,0,0,0.12);position:relative;">
            <h3 style="margin-top:0;">Product Batches</h3>
            <div id="batchModalContent" style="max-height:350px;overflow-y:auto;"></div>
            <button onclick="closeBatchModal()" class="btn" style="margin-top:18px;background:#888;">Close</button>
        </div>
    </div>

    <script>
    function openSupplierModal(productId) {
        document.getElementById('set_supplier_product_id').value = productId;
        document.getElementById('supplierModal').style.display = 'flex';
    }
    function closeSupplierModal() {
        document.getElementById('supplierModal').style.display = 'none';
    }
    function openBatchModal(productId) {
        document.getElementById('batchModal').style.display = 'flex';
        document.getElementById('batchModalContent').innerHTML = '<div style="text-align:center;color:#888;">Loading...</div>';
        fetch('view_batches.php?product_id=' + productId + '&as_modal=1')
            .then(res => res.text())
            .then(html => {
                document.getElementById('batchModalContent').innerHTML = html;
            });
    }
    function closeBatchModal() {
        document.getElementById('batchModal').style.display = 'none';
    }
    
    function exportData() {
        // Create CSV content
        const table = document.querySelector('table');
        let csv = '';
        
        // Add headers
        const headers = ['Product Name', 'Category', 'Price', 'Stock', 'Total Sold', 'Supplier'];
        csv += headers.join(',') + '\n';
        
        // Add data rows
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length > 0) {
                const data = [
                    cells[1].querySelector('strong').textContent.trim(), // Product name
                    cells[2].textContent.trim(), // Category
                    cells[3].textContent.trim(), // Price
                    cells[4].querySelector('span').textContent.trim(), // Stock
                    cells[5].textContent.trim(), // Total sold
                    cells[6].textContent.trim()  // Supplier
                ];
                csv += data.map(field => `"${field.replace(/"/g, '""')}"`).join(',') + '\n';
            }
        });
        
        // Download CSV
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'products_export_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }
    </script>
</body>
</html>
