<?php
session_start();
include "includes/db_connect.php";

// Allow both logged-in users and guests to view cart
$is_guest = !isset($_SESSION['user_id']);

// Check if the cart exists
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

// Handle cart updates (e.g., remove item or update quantity)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'remove' && isset($_POST['cart_key'])) {
        // Remove item from cart using cart_key for exact match
        $cart_key = $_POST['cart_key'];
        foreach ($cart as $index => $item) {
            if (isset($item['cart_key']) && $item['cart_key'] === $cart_key) {
                unset($cart[$index]);
                break;
            }
        }
        $_SESSION['cart'] = array_values($cart); // Reindex the cart array
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update' && isset($_POST['cart_key'], $_POST['quantity'])) {
        // Update item quantity using cart_key for exact match
        $cart_key = $_POST['cart_key'];
        $quantity = max(1, intval($_POST['quantity'])); // Ensure quantity is at least 1

        foreach ($cart as &$item) {
            if (isset($item['cart_key']) && $item['cart_key'] === $cart_key) {
                // Check stock availability for this specific variety and size
                $available_stock = 0;
                
                if (isset($item['variety']) && $item['variety']) {
                    // Check variety-specific stock
                    $stock_query = "
                        SELECT pvs.quantity 
                        FROM product_variety_sizes pvs 
                        JOIN product_varieties pv ON pvs.variety_id = pv.variety_id 
                        WHERE pv.product_id = ? AND pv.variety_name = ? AND pvs.size = ?
                    ";
                    $stmt = $conn->prepare($stock_query);
                    $stmt->bind_param("iss", $item['product_id'], $item['variety'], $item['size']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $available_stock = $result->fetch_assoc()['quantity'];
                    }
                    $stmt->close();
                } else {
                    // Check legacy product_sizes stock
                    $stmt = $conn->prepare("SELECT quantity FROM product_sizes WHERE product_id = ? AND size = ?");
                    $stmt->bind_param("is", $item['product_id'], $item['size']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $available_stock = $result->fetch_assoc()['quantity'];
                    }
                    $stmt->close();
                }

                // Restrict quantity to available stock
                if ($quantity > $available_stock) {
                    $quantity = $available_stock;
                    $_SESSION['error'] = "Quantity exceeds available stock. Set to maximum available ({$available_stock}).";
                }

                $item['quantity'] = $quantity;
                break;
            }
        }
        $_SESSION['cart'] = $cart;
    }
}

// Recalculate the cart after updates
$total = 0;
foreach ($cart as $item) {
    $total += $item['price'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <header>
        <h1>Shopping Cart</h1>
        <a href="home.php" class="back-button">← Back to Shop</a>
    </header>
    <main style="display: flex; justify-content: center; align-items: center; flex-direction: column; min-height: 80vh;">
        <section class="cart" style="width: 80%; max-width: 900px;">
            <h2 style="text-align: center;">Your Cart</h2>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message" style="text-align:center;"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            <?php if (empty($cart)): ?>
                <p style="text-align: center;">Your cart is empty.</p>
                <div style="text-align: center;">
                    <a href="home.php" class="btn">Continue Shopping</a>
                </div>
            <?php else: ?>
                <table style="text-align: center; width: 100%;">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Product Details</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart as $item): ?>
                            <tr style="text-align: center;">
                                <td style="text-align: center;">
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                         alt="<?= htmlspecialchars($item['name']) ?>" 
                                         style="max-width: 55px; max-height: 55px; display: block; margin: 0 auto;">
                                </td>
                                <td style="text-align: left; padding: 10px;">
                                    <div style="font-weight: bold; margin-bottom: 5px;">
                                        <?= htmlspecialchars($item['name']) ?>
                                    </div>
                                    <?php if (isset($item['variety']) && $item['variety']): ?>
                                        <div style="font-size: 0.9em; color: #666; margin-bottom: 3px;">
                                            <strong>Variety:</strong> <?= htmlspecialchars($item['variety']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($item['size']) && $item['size']): ?>
                                        <div style="font-size: 0.9em; color: #666;">
                                            <strong>Size:</strong> <?= htmlspecialchars($item['size']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>₱<?= number_format($item['price'], 2) ?></td>
                                <td>
                                    <?php
                                    // Get current stock for this specific variety and size
                                    $available_stock = 0;
                                    
                                    if (isset($item['variety']) && $item['variety']) {
                                        // Check variety-specific stock
                                        $stock_query = "
                                            SELECT pvs.quantity 
                                            FROM product_variety_sizes pvs 
                                            JOIN product_varieties pv ON pvs.variety_id = pv.variety_id 
                                            WHERE pv.product_id = ? AND pv.variety_name = ? AND pvs.size = ?
                                        ";
                                        $stmt = $conn->prepare($stock_query);
                                        $stmt->bind_param("iss", $item['product_id'], $item['variety'], $item['size']);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        if ($result->num_rows > 0) {
                                            $available_stock = $result->fetch_assoc()['quantity'];
                                        }
                                        $stmt->close();
                                    } else {
                                        // Check legacy product_sizes stock
                                        $size_value = $item['size'] ?? '';
                                        $stmt = $conn->prepare("SELECT quantity FROM product_sizes WHERE product_id = ? AND size = ?");
                                        $stmt->bind_param("is", $item['product_id'], $size_value);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        if ($result->num_rows > 0) {
                                            $available_stock = $result->fetch_assoc()['quantity'];
                                        }
                                        $stmt->close();
                                    }
                                    ?>
                                    <form method="POST" action="cart.php" style="display: flex; flex-direction: column; align-items: center; gap: 5px;">
                                        <input type="hidden" name="cart_key" value="<?= htmlspecialchars($item['cart_key'] ?? '') ?>">
                                        <input type="number" name="quantity" value="<?= $item['quantity'] ?>" 
                                               min="1" max="<?= $available_stock ?>" 
                                               style="width: 60px; padding: 5px; text-align: center;">
                                        <span style="font-size:0.8em;color:#888;">Stock: <?= $available_stock ?></span>
                                        <button type="submit" name="action" value="update" 
                                                style="padding: 5px 10px; font-size: 0.8em; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer;">
                                            Update
                                        </button>
                                    </form>
                                </td>
                                <td style="font-weight: bold;">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                <td>
                                    <form method="POST" action="cart.php">
                                        <input type="hidden" name="cart_key" value="<?= htmlspecialchars($item['cart_key'] ?? '') ?>">
                                        <button type="submit" name="action" value="remove" 
                                                style="padding: 8px 12px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer;">
                                            Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="text-align: center;"><strong>Total: ₱<?= number_format($total, 2) ?></strong></p>
                <div style="text-align: center;">
                    <form action="checkout.php" method="get" style="display: inline;">
                        <button type="submit" class="btn">Proceed to Checkout</button>
                    </form>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>