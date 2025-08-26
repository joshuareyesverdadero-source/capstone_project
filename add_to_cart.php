<?php
session_start();
include "includes/db_connect.php";

// Check if the user is logged in - allow guests to add to cart
$is_guest = !isset($_SESSION['user_id']);

// Check if the product ID is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id']) && is_numeric($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);
    $selected_size = $_POST['size'] ?? null;
    $selected_variety = $_POST['variety'] ?? null;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    if (!$selected_size) {
        die("Please select a size.");
    }
    
    if ($quantity < 1) {
        die("Invalid quantity.");
    }

    // Fetch product details from the database
    $query = "SELECT * FROM products WHERE product_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();

        // Check stock availability
        $available_stock = 0;
        $variety_id = null;
        
        if ($selected_variety) {
            // Check stock for specific variety and size
            $stock_query = "
                SELECT pvs.quantity, pv.variety_id 
                FROM product_variety_sizes pvs 
                JOIN product_varieties pv ON pvs.variety_id = pv.variety_id 
                WHERE pv.product_id = ? AND pv.variety_name = ? AND pvs.size = ?
            ";
            $stock_stmt = $conn->prepare($stock_query);
            $stock_stmt->bind_param("iss", $product_id, $selected_variety, $selected_size);
            $stock_stmt->execute();
            $stock_result = $stock_stmt->get_result();
            
            if ($stock_result->num_rows > 0) {
                $stock_row = $stock_result->fetch_assoc();
                $available_stock = $stock_row['quantity'];
                $variety_id = $stock_row['variety_id'];
            }
            $stock_stmt->close();
        } else {
            // Check legacy product_sizes table
            $stock_query = "SELECT quantity FROM product_sizes WHERE product_id = ? AND size = ?";
            $stock_stmt = $conn->prepare($stock_query);
            $stock_stmt->bind_param("is", $product_id, $selected_size);
            $stock_stmt->execute();
            $stock_result = $stock_stmt->get_result();
            
            if ($stock_result->num_rows > 0) {
                $stock_row = $stock_result->fetch_assoc();
                $available_stock = $stock_row['quantity'];
            }
            $stock_stmt->close();
        }
        
        if ($available_stock < $quantity) {
            die("Not enough stock available. Only {$available_stock} items left in this size.");
        }

        // Initialize the cart if it doesn't exist
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        // Create unique item key for cart (considering variety and size)
        $cart_key = $product_id . '_' . ($selected_variety ?? 'no_variety') . '_' . $selected_size;

        // Check if the exact same product (same variety and size) is already in the cart
        $found = false;
        foreach ($_SESSION['cart'] as $key => &$item) {
            if (isset($item['cart_key']) && $item['cart_key'] === $cart_key) {
                // Check if adding more would exceed available stock
                $new_quantity = $item['quantity'] + $quantity;
                if ($new_quantity <= $available_stock) {
                    $item['quantity'] = $new_quantity;
                    $found = true;
                } else {
                    die("Cannot add more items. Total would exceed available stock ({$available_stock} available).");
                }
                break;
            }
        }

        // If the exact product variant is not in the cart, add it
        if (!$found) {
            $_SESSION['cart'][] = [
                'cart_key' => $cart_key,
                'product_id' => $product['product_id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'image_url' => $product['image_url'],
                'size' => $selected_size,
                'variety' => $selected_variety,
                'variety_id' => $variety_id
            ];
        }

        // Redirect to the cart page
        header("Location: cart.php");
        exit();
    } else {
        // Product not found
        die("Product not found.");
    }
} else {
    // Invalid request
    die("Invalid request.");
}
?>