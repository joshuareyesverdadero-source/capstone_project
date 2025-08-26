<?php
include "../includes/db_connect.php";

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

// Fetch product name for display
$product = null;
if ($product_id) {
    $res = $conn->query("SELECT name FROM products WHERE product_id = $product_id");
    $product = $res->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $cost_price = floatval($_POST['cost_price']);
    $selling_price = floatval($_POST['selling_price']);
    $arrival_date = date('Y-m-d');
    $stmt = $conn->prepare("INSERT INTO product_batches (product_id, quantity, cost_price, selling_price, arrival_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iidds", $product_id, $quantity, $cost_price, $selling_price, $arrival_date);
    $stmt->execute();
    header("Location: products.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Batch</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>Add Batch</h1>
            <a href="products.php" class="btn">Back to Products</a>
        </header>
        <main class="admin-main">
            <form method="POST" style="max-width:400px;margin:0 auto;">
                <input type="hidden" name="product_id" value="<?= $product_id ?>">
                <div>
                    <label>Product:</label>
                    <input type="text" value="<?= htmlspecialchars($product['name'] ?? '') ?>" readonly>
                </div>
                <div>
                    <label>Quantity:</label>
                    <input type="number" name="quantity" min="1" required>
                </div>
                <div>
                    <label>Cost Price (from delivery):</label>
                    <input type="number" id="modal_cost_price" readonly>
                </div>
                <div>
                    <label>Selling Price (set retail price for customers):</label>
                    <input type="number" name="selling_price" id="modal_selling_price" min="1" step="0.01" required>
                </div>
                <button type="submit" class="btn">Add Batch</button>
            </form>
        </main>
    </div>
</body>
</html>