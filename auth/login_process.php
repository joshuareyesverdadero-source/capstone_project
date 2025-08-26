<?php
session_start();
include "../includes/db_connect.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Hash the password (adjust if you're using a different hashing method)
    $hashed_password = hash("sha256", $password);

    // Query to check user credentials
    $query = "SELECT user_id, username, role FROM users WHERE username = ? AND password = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $username, $hashed_password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Set session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // Check if there's a redirect after login (for checkout flow)
        if (isset($_SESSION['redirect_after_login'])) {
            $redirect_url = $_SESSION['redirect_after_login'];
            unset($_SESSION['redirect_after_login']); // Clean up
            header("Location: ../" . $redirect_url);
            exit();
        }

        // Redirect based on role
        if ($user['role'] === 'superadmin') {
            header("Location: ../admin/sadashboard.php");
        } elseif ($user['role'] === 'admin') {
            include_once "../includes/log_action.php";
            logAdminAction($conn, $user['user_id'], $user['role'], "Admin logged in.");
            header("Location: ../admin/dashboard.php");
        } elseif ($user['role'] === 'supplier') {
            header("Location: ../supplier/supdash.php");
        } else {
            header("Location: ../home.php");
        }
        exit();
    } else {
        // Invalid credentials
        $_SESSION['error'] = "Invalid username or password.";
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>
