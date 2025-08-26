<?php
session_start();
include "../includes/db_connect.php";

// Get supplier ID from session (adjust if your session uses a different key)
$supplier_id = $_SESSION['user_id'] ?? null; // Use user_id for consistency

// Handle form submission
$successMsg = $errorMsg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'], $_POST['quantity'], $_POST['total_price'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $total_price = floatval($_POST['total_price']);
    $date = date('Y-m-d');
    if ($supplier_id && $product_id && $quantity > 0 && $total_price > 0) {
        $stmt = $conn->prepare("INSERT INTO deliveries (user_id, product_id, quantity, total_price, delivery_date, status, remarks) VALUES (?, ?, ?, ?, ?, 'Pending', NULL)");
        $stmt->bind_param("iiids", $supplier_id, $product_id, $quantity, $total_price, $date);
        if ($stmt->execute()) {
            $successMsg = "Delivery submitted successfully!";
        } else {
            $errorMsg = "Failed to submit delivery.";
        }
        $stmt->close();
    } else {
        $errorMsg = "Please fill in all fields correctly.";
    }
}
 
// Fetch products for dropdown (only those assigned to this supplier)
$products = [];
$res = $conn->prepare("SELECT product_id, name FROM products WHERE supplier_id = ? ORDER BY name ASC");
$res->bind_param("i", $supplier_id);
$res->execute();
$result = $res->get_result();
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$res->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Dashboard</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=2">
    <script defer>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }
        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('overlay').classList.remove('active');
        }
        // Show Product ID when a product is selected
        document.addEventListener('DOMContentLoaded', function() {
            var select = document.getElementById('product-select');
            var idDisplay = document.getElementById('product-id-display');
            select.addEventListener('change', function() {
                idDisplay.value = select.value || '';
            });
        });
    </script>
</head>
<body>
    <div class="overlay" id="overlay" onclick="closeSidebar()" aria-label="Close sidebar"></div>
    <header>
        <h1 class="shop-title">Supplier Dashboard</h1>
        <div class="right-icons">
            <div class="icons">
                <div class="profile-icon">
                    <button aria-label="Profile menu">👤</button>
                    <div class="profile-dropdown">
                        <a href="../auth/logout.php">Logout</a>
                    </div>
                </div>
                <button class="menu-icon" onclick="toggleSidebar()" aria-label="Open sidebar">☰</button>
            </div>
        </div>
    </header>
    <nav class="sidebar" id="sidebar" aria-label="Sidebar navigation">
        <ul>
            <li><a href="supdash.php">Dashboard</a></li>
            <li><a href="#">Deliveries</a></li>
            <li><a href="#">Products</a></li>
            <li><a href="#">Account</a></li>
        </ul>
    </nav>
    <main>
        <section style="max-width:400px;margin:0 auto;background:#fff;padding:24px 20px 20px 20px;border-radius:10px;box-shadow:0 2px 8px rgba(232,139,155,0.08);">
            <h2 style="margin-top:0;">Submit New Delivery</h2>
            <?php if ($successMsg): ?>
                <div style="color:green;"><?= htmlspecialchars($successMsg) ?></div>
            <?php elseif ($errorMsg): ?>
                <div style="color:red;"><?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>
            <form method="POST" style="display:flex;flex-direction:column;gap:14px;">
                <label>
                    Product:
                    <select name="product_id" id="product-select" required>
                        <option value="">Select product</option>
                        <?php foreach ($products as $prod): ?>
                            <option value="<?= $prod['product_id'] ?>"><?= htmlspecialchars($prod['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Product ID:
                    <input type="text" id="product-id-display" value="" readonly>
                </label>
                <label>
                    Quantity:
                    <input type="number" name="quantity" min="1" required>
                </label>
                <label>
                    Total Price for Delivery:
                    <input type="number" name="total_price" min="1" step="0.01" required>
                </label>
                <label>
                    Date Submitted:
                    <input type="text" value="<?= date('Y-m-d') ?>" readonly>
                </label>
                <button type="submit">Submit Delivery</button>
            </form>
        </section>
        <!-- My Deliveries Section -->
        <section style="max-width:700px;margin:32px auto 0 auto;background:#fff;padding:24px 20px 20px 20px;border-radius:10px;box-shadow:0 2px 8px rgba(232,139,155,0.08);">
            <h2 style="margin-top:0;">My Deliveries</h2>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#f7d3db;">
                        <th style="padding:8px;border:1px solid #eee;">Product Name</th>
                        <th style="padding:8px;border:1px solid #eee;">Product ID</th>
                        <th style="padding:8px;border:1px solid #eee;">Quantity</th>
                        <th style="padding:8px;border:1px solid #eee;">Date</th>
                        <th style="padding:8px;border:1px solid #eee;">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $stmt = $conn->prepare(
                    "SELECT d.product_id, p.name AS product_name, d.quantity, d.delivery_date, d.status
                     FROM deliveries d
                     JOIN products p ON d.product_id = p.product_id
                     WHERE d.user_id = ?
                     ORDER BY d.delivery_date DESC, d.delivery_id DESC"
                );
                $stmt->bind_param("i", $supplier_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 0): ?>
                    <tr><td colspan="5" style="text-align:center;color:#888;">No deliveries found.</td></tr>
                <?php else:
                    while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td style="padding:8px;border:1px solid #eee;"><?= htmlspecialchars($row['product_name']) ?></td>
                        <td style="padding:8px;border:1px solid #eee;"><?= $row['product_id'] ?></td>
                        <td style="padding:8px;border:1px solid #eee;"><?= $row['quantity'] ?></td>
                        <td style="padding:8px;border:1px solid #eee;"><?= $row['delivery_date'] ?></td>
                        <td style="padding:8px;border:1px solid #eee;"><?= htmlspecialchars($row['status']) ?></td>
                    </tr>
                <?php endwhile; endif;
                $stmt->close();
                ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>