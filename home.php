<?php
session_start();
include "includes/db_connect.php";

// Check if the user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$username = $isLoggedIn ? $_SESSION['username'] : null;

// Sorting, Category, and Search
$sortOption = $_GET['sort'] ?? 'price_high_low';
$categoryId = isset($_GET['category']) && is_numeric($_GET['category']) ? $_GET['category'] : null;
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : null;

// Build query
$searchFilter = $searchTerm ? "AND name LIKE '%" . $conn->real_escape_string($searchTerm) . "%'" : "";

// Fetch all child category IDs for the selected parent category
if ($categoryId) {
    $childCategoryIds = [$categoryId];
    $childQuery = "SELECT category_id FROM categories WHERE parent_id = ?";
    $stmt = $conn->prepare($childQuery);
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $childResult = $stmt->get_result();
    while ($childRow = $childResult->fetch_assoc()) {
        $childCategoryIds[] = $childRow['category_id'];
    }
    $categoryFilter = "WHERE category_id IN (" . implode(",", $childCategoryIds) . ") $searchFilter";
} else {
    $categoryFilter = $searchFilter ? "WHERE 1=1 $searchFilter" : "";
}

// Add condition to exclude products with 0 stock
$stockFilter = "stock > 0";

// Combine filters properly
$filter = $categoryFilter ? "$categoryFilter AND $stockFilter" : "WHERE $stockFilter";

switch ($sortOption) {
    case 'price_low_high':
        $query = "SELECT * FROM products $filter ORDER BY price ASC";
        break;
    case 'price_high_low':
    default:
        $query = "SELECT * FROM products $filter ORDER BY price DESC";
        break;
}

$result = $conn->query($query);

// Fetch categories dynamically for the sidebar
$categoriesQuery = "SELECT * FROM categories";
$categoriesResult = $conn->query($categoriesQuery);

// Organize categories into parent-child structure
$categories = [];
$parentCategories = [];
while ($row = $categoriesResult->fetch_assoc()) {
    if ($row['parent_id'] === null || $row['parent_id'] === '') {
        $parentCategories[] = $row;
    } else {
        $categories[$row['parent_id']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Julie's RTW Shop - Quality Ready-to-Wear Fashion</title>
    <meta name="description" content="Discover quality ready-to-wear fashion at Julie's RTW Shop. Browse our collection of trendy clothing and accessories.">
    <meta name="keywords" content="ready to wear, fashion, clothing, RTW, Julie's shop">
    <link rel="stylesheet" href="assets/css/styles.css?v=6">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="assets/js/home.js?v=5" defer></script>
</head>
<body>
    <div class="overlay" id="overlay" aria-label="Close sidebar"></div>
    <header>
         <img src="assets/icons/logo.png" alt="Julie's RTW Shop Logo" class="shop-logo">
        <div class="right-icons">
            <div class="icons">
                <?php if ($isLoggedIn): ?>
                    <span class="welcome-msg">Welcome, <?= htmlspecialchars($username); ?>!</span>
                <?php endif; ?>
                <form method="GET" action="home.php" class="search-form" role="search">
                    <input type="text" name="search" placeholder="Search..." class="search-box" value="<?= htmlspecialchars($searchTerm) ?>" aria-label="Search products">
                    <button type="submit" class="search-icon" aria-label="Search">
                        <img src="assets/icons/search.png" alt="Search">
                    </button>
                </form>
                <a href="cart.php" class="cart-icon" aria-label="View cart">
                    <img src="assets/icons/atc.png" alt="Cart">
                </a>
                <a href="cs_orders.php" class="orders-icon" aria-label="Your orders">
                    <img src="assets/icons/orders.png" alt="Orders">
                </a>
                <div class="profile-icon">
                    <button aria-label="Profile menu">
                        <img src="assets/icons/user.png" alt="Profile">
                    </button>
                    <div class="profile-dropdown">
                        <?php if ($isLoggedIn): ?>
                            <a href="auth/logout.php">Logout</a>
                        <?php else: ?>
                            <a href="auth/login.php">Login</a>
                            <a href="auth/register.php">Register</a>
                            <a href="auth/register_supplier.php">Register as a Supplier</a>
                        <?php endif; ?>
                    </div>
                </div>
                <button class="menu-icon" aria-label="Open sidebar" aria-expanded="false">
                    <img src="assets/icons/menu.png" alt="Menu">
                </button>
            </div>
        </div>
    </header>

    <nav class="sidebar" id="sidebar" aria-label="Sidebar navigation">
        <ul>
            <li><a href="home.php">Home</a></li>
            <?php if (!empty($parentCategories)): ?>
                <?php foreach ($parentCategories as $parent): ?>
                    <li>
                        <a href="#" class="submenu-toggle" data-submenu="submenu-<?= $parent['category_id'] ?>" aria-expanded="false">
                            <?= htmlspecialchars($parent['category_name']) ?> ▼
                        </a>
                        <?php if (isset($categories[$parent['category_id']])): ?>
                            <ul id="submenu-<?= $parent['category_id'] ?>" class="submenu">
                                <li>
                                    <a href="home.php?category=<?= $parent['category_id'] ?>">All</a>
                                </li>
                                <?php foreach ($categories[$parent['category_id']] as $child): ?>
                                    <li>
                                        <a href="home.php?category=<?= $child['category_id'] ?>">
                                            <?= htmlspecialchars($child['category_name']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
            <li><a href="#">About</a></li>
        </ul>
    </nav>

    <main>
        <section class="latest">
            <div class="products-header">
                <h2>Products</h2>
                <div class="sort-container">
                    <label for="sort-products">Sort by:</label>
                    <select id="sort-products">
                        <option value="price_high_low" <?= $sortOption === 'price_high_low' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="price_low_high" <?= $sortOption === 'price_low_high' ? 'selected' : '' ?>>Price: Low to High</option>
                    </select>
                </div>
            </div>
            <div class="product-container">
                <?php if ($result->num_rows === 0): ?>
                    <div class="no-products">No products found.</div>
                <?php endif; ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <a href="viewproduct.php?id=<?= $row['product_id'] ?>" class="product-link">
                        <div class="product" tabindex="0">
                            <img src="<?= file_exists($row['image_url']) ? htmlspecialchars($row['image_url']) : 'assets/images/default.png' ?>" alt="<?= htmlspecialchars($row['name']) ?>">
                            <p class="product-name"><?= htmlspecialchars($row['name']) ?></p>
                            <p class="product-price">₱<?= number_format($row['price'], 2) ?></p>
                            <p class="product-stock">Stock: <?= $row['stock'] ?> left</p>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
        </section>
        <!--<button class="camera-btn" aria-label="Open camera">📷</button>-->
        <?php if ($isLoggedIn): ?>
            <button class="help-btn" aria-label="Help / Message Admin">💬</button>
            <div id="chat-popup" class="chat-popup">
                <div class="chat-header">
                    <span>Message Admin</span>
                    <button class="close-chat" aria-label="Close chat">&times;</button>
                </div>
                <div class="chat-messages" id="chat-messages"></div>
                <form id="chat-form" autocomplete="off">
                    <input type="text" id="chat-input" name="message" placeholder="Type a message..." required autocomplete="off" />
                    <button type="submit">Send</button>
                </form>
            </div>
        <?php endif; ?>
    </main>
    <script src="assets/js/chat.js?v=3" defer></script>
</body>
</html>
