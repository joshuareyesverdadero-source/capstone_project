<?php
session_start();
include "includes/db_connect.php";

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Check if the cart exists
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

// Redirect to home if the cart is empty
if (empty($cart)) {
    header("Location: home.php");
    exit();
}

// Calculate the total amount
$total = 0;
foreach ($cart as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Handle form submission for checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (!isset($_SESSION['user_id'])) {
        // Store checkout intent and cart data for post-login continuation
        $_SESSION['checkout_intent'] = true;
        $_SESSION['redirect_after_login'] = 'checkout.php';
        
        // Ensure cart is preserved in session (it should already be there)
        if (empty($_SESSION['cart'])) {
            die("Your cart is empty. Please add items to your cart before checking out.");
        }
        
        // Show a message and redirect to login
        $_SESSION['info_message'] = "Please login to complete your order. Your cart will be preserved.";
        header("Location: auth/login.php");
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $status = 'Pending';

    // Start transaction for data consistency
    $conn->begin_transaction();
    
    try {
        // Insert the order into the `orders` table
        $order_query = "INSERT INTO orders (user_id, total_price, status) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($order_query);
        $stmt->bind_param("ids", $user_id, $total, $status);

        if (!$stmt->execute()) {
            throw new Exception("Failed to place order: " . $stmt->error);
        }

        $order_id = $conn->insert_id; // Get the newly created order ID
        $_SESSION['last_order_id'] = $order_id;

        // Check if order_items table has variety/size columns, if not add them
        $check_columns = $conn->query("SHOW COLUMNS FROM order_items LIKE 'variety'");
        if ($check_columns->num_rows === 0) {
            // Add variety and size columns to order_items table
            $conn->query("ALTER TABLE order_items ADD COLUMN variety VARCHAR(100) NULL AFTER subtotal");
            $conn->query("ALTER TABLE order_items ADD COLUMN size ENUM('XXS','XS','S','M','XL','XXL') NULL AFTER variety");
            $conn->query("ALTER TABLE order_items ADD COLUMN variety_id INT(11) NULL AFTER size");
        }

        // Insert each item into the `order_items` table and decrease stock
        foreach ($cart as $item) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];
            $variety = isset($item['variety']) ? $item['variety'] : null;
            $size = isset($item['size']) ? $item['size'] : null;
            $variety_id = isset($item['variety_id']) ? $item['variety_id'] : null;

            // Insert into `order_items` with variety and size information
            $order_item_query = "INSERT INTO order_items (order_id, product_id, quantity, subtotal, variety, size, variety_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($order_item_query);
            $subtotal = $item['price'] * $quantity;
            $stmt->bind_param("iiidssi", $order_id, $product_id, $quantity, $subtotal, $variety, $size, $variety_id);

            if (!$stmt->execute()) {
                throw new Exception("Failed to add order item: " . $stmt->error);
            }

            // Decrease stock appropriately based on whether item has varieties
            if ($variety && $variety_id) {
                // Update variety-specific stock
                $update_variety_stock = "UPDATE product_variety_sizes SET quantity = quantity - ? WHERE variety_id = ? AND size = ?";
                $stmt = $conn->prepare($update_variety_stock);
                $stmt->bind_param("iis", $quantity, $variety_id, $size);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update variety stock: " . $stmt->error);
                }
                
                // Check if the variety stock went below zero
                $check_stock = "SELECT quantity FROM product_variety_sizes WHERE variety_id = ? AND size = ?";
                $stmt = $conn->prepare($check_stock);
                $stmt->bind_param("is", $variety_id, $size);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $stock_row = $result->fetch_assoc();
                    if ($stock_row['quantity'] < 0) {
                        throw new Exception("Insufficient stock for variety: $variety, size: $size");
                    }
                }
            } else {
                // Update legacy product_sizes stock
                $update_size_stock = "UPDATE product_sizes SET quantity = quantity - ? WHERE product_id = ? AND size = ?";
                $stmt = $conn->prepare($update_size_stock);
                $stmt->bind_param("iis", $quantity, $product_id, $size);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update size stock: " . $stmt->error);
                }
                
                // Check if the size stock went below zero
                $check_stock = "SELECT quantity FROM product_sizes WHERE product_id = ? AND size = ?";
                $stmt = $conn->prepare($check_stock);
                $stmt->bind_param("is", $product_id, $size);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $stock_row = $result->fetch_assoc();
                    if ($stock_row['quantity'] < 0) {
                        throw new Exception("Insufficient stock for size: $size");
                    }
                }
            }

            // Also update the main product stock
            $update_main_stock = "UPDATE products SET stock = stock - ? WHERE product_id = ?";
            $stmt = $conn->prepare($update_main_stock);
            $stmt->bind_param("ii", $quantity, $product_id);

            if (!$stmt->execute()) {
                throw new Exception("Failed to update main product stock: " . $stmt->error);
            }
        }

        // Commit the transaction
        $conn->commit();

        // Clear the cart after successful checkout
        $_SESSION['cart'] = [];
        
        // Clear checkout intent
        unset($_SESSION['checkout_intent']);
        unset($_SESSION['redirect_after_login']);
        
        $success_message = "Thank you for your purchase! Your order has been placed.";
        
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        die("Checkout failed: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" width="device-width, initial-scale=1.0">
    <title>Checkout</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <header>
        <h1>Checkout</h1>
        <a href="cart.php" class="back-button">← Back to Cart</a>
    </header>
    <main style="display: flex; justify-content: center; align-items: center; flex-direction: column; min-height: 80vh;">
        <section class="checkout" style="width: 80%; max-width: 900px;">
            <?php if (isset($_SESSION['checkout_intent'])): ?>
                <div class="info-message" style="background-color: #d1ecf1; color: #0c5460; padding: 10px; border: 1px solid #bee5eb; border-radius: 4px; margin-bottom: 15px; text-align: center;">
                    <p>Welcome back! You can now continue with your order.</p>
                </div>
            <?php endif; ?>
            <h2 style="text-align: center;">Order Summary</h2>
            <div class="product-container">
                <?php foreach ($cart as $item): ?>
                    <div class="product">
                        <a href="viewproduct.php?id=<?= $item['product_id'] ?>">
                            <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                        </a>
                        <div class="product-info" style="text-align: left; padding: 10px;">
                            <p class="product-name" style="font-weight: bold; margin-bottom: 5px;"><?= htmlspecialchars($item['name']) ?></p>
                            <?php if (isset($item['variety']) && $item['variety']): ?>
                                <p style="font-size: 0.9em; color: #666; margin-bottom: 3px;">
                                    <strong>Variety:</strong> <?= htmlspecialchars($item['variety']) ?>
                                </p>
                            <?php endif; ?>
                            <?php if (isset($item['size']) && $item['size']): ?>
                                <p style="font-size: 0.9em; color: #666; margin-bottom: 3px;">
                                    <strong>Size:</strong> <?= htmlspecialchars($item['size']) ?>
                                </p>
                            <?php endif; ?>
                            <p class="product-price">₱<?= number_format($item['price'], 2) ?></p>
                            <p class="product-quantity">Quantity: <?= $item['quantity'] ?></p>
                            <p class="product-total">Total: ₱<?= number_format($item['price'] * $item['quantity'], 2) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p style="text-align: center;"><strong>Total Amount: ₱<?= number_format($total, 2) ?></strong></p>
            <?php if (isset($success_message)): ?>
                <p class="success-message" style="text-align: center; font-size: 1.5em; font-weight: bold; margin-top: 20px;"><?= $success_message ?></p>
                <!-- GCash Payment Form -->
                <div style="margin: 30px auto; max-width: 400px; background: #f7d3db; padding: 24px; border-radius: 10px;">
                    <h3 style="color:#c47181;text-align:center;">GCash Payment Details</h3>
                    <p style="text-align:center;">Send payment to:<br>
                        <strong>GCash Number: 09XXXXXXXXX</strong><br>
                        <strong>Account Name: Julie's RTW Shop</strong>
                    </p>
                    <form method="POST" action="submit_payment.php" enctype="multipart/form-data">
                        <input type="hidden" name="order_id" value="<?= $_SESSION['last_order_id'] ?? '' ?>">
                        <label>GCash Account Name:</label>
                        <input type="text" name="gcash_name" required style="width:100%;margin-bottom:10px;">
                        <label>GCash Number:</label>
                        <input type="text" name="gcash_number" required style="width:100%;margin-bottom:10px;">
                        <label>Upload Payment Screenshot:</label>
                        <input type="file" name="proof_image" accept="image/*" required style="margin-bottom:10px;">
                        <button type="submit" class="btn" style="width:100%;">Submit Payment</button>
                    </form>
                </div>
            <?php else: ?>
                <form method="POST" action="checkout.php" style="text-align: center;">
                    <button type="submit" name="place_order" class="btn">Place Order</button>
                </form>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>