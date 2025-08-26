<?php
session_start();
include "../includes/db_connect.php";

// Check if the user is an admin or superadmin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Check if the product ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = intval($_GET['id']);

    // Get product name before deleting
    $name_stmt = $conn->prepare("SELECT name FROM products WHERE product_id = ?");
    $name_stmt->bind_param("i", $product_id);
    $name_stmt->execute();
    $name_stmt->bind_result($product_name);
    $name_stmt->fetch();
    $name_stmt->close();

    // Delete related batches first
    $del_batches = $conn->prepare("DELETE FROM product_batches WHERE product_id = ?");
    $del_batches->bind_param("i", $product_id);
    $del_batches->execute();
    $del_batches->close();

    // Delete the product from the database
    $query = "DELETE FROM products WHERE product_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);

    if ($stmt->execute()) {
        // Log only if admin (not superadmin)
        if ($_SESSION['role'] === 'admin') {
            include_once "../includes/log_action.php";
            logAdminAction($conn, $_SESSION['user_id'], $_SESSION['role'], "Deleted product: $product_name (ID: $product_id)");
        }
        $_SESSION['message'] = "Product deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete product: " . $stmt->error;
    }

    // Redirect back to the products page
    header("Location: products.php");
    exit();
} else {
    $_SESSION['error'] = "Invalid product ID.";
    header("Location: products.php");
    exit();
}
?>