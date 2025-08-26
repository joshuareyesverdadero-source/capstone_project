<?php
session_start();
include "includes/db_connect.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id'] ?? 0);
    $gcash_name = trim($_POST['gcash_name'] ?? '');
    $gcash_number = trim($_POST['gcash_number'] ?? '');

    // Validate fields
    if (!$order_id || !$gcash_name || !$gcash_number || !isset($_FILES['proof_image'])) {
        $_SESSION['error'] = "Please fill in all fields and upload your payment screenshot.";
        header("Location: checkout.php");
        exit();
    }

    // Handle file upload
    $target_dir = "assets/payments/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $file_name = uniqid("gcash_", true) . "_" . basename($_FILES["proof_image"]["name"]);
    $target_file = $target_dir . $file_name;

    if (!move_uploaded_file($_FILES["proof_image"]["tmp_name"], $target_file)) {
        $_SESSION['error'] = "Failed to upload payment screenshot.";
        header("Location: checkout.php");
        exit();
    }

    // Insert payment info into database
    $stmt = $conn->prepare("INSERT INTO payments (order_id, gcash_name, gcash_number, proof_image, status) VALUES (?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("isss", $order_id, $gcash_name, $gcash_number, $file_name);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Payment submitted! Please wait for admin verification.";
        header("Location: cs_orders.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to submit payment. Please try again.";
        header("Location: checkout.php");
        exit();
    }
} else {
    header("Location: checkout.php");
    exit();
}
?>