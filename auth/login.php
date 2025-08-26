<?php
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'superadmin') {
        header("Location: ../admin/sadashboard.php");
    } elseif ($_SESSION['role'] === 'admin') {
        header("Location: ../admin/dashboard.php");
    } elseif ($_SESSION['role'] === 'supplier') {
        header("Location: ../supplier/supdash.php");
    } else {
        header("Location: ../home.php");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Julie's RTW Shop</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
    <header>
        <a href="../home.php" style="text-decoration: none; color: inherit;">
             <img src="../assets/icons/logo.png" alt="Julie's RTW Shop Logo" class="shop-logo" style="height:50px;">
        </a>
        <a href="../home.php" class="back-button">← Back to Home</a>
    </header>
    <main style="display: flex; justify-content: center; align-items: center; flex-direction: column; min-height: 80vh;">
        <div class="login-container">
            <?php if (isset($_SESSION['checkout_intent'])): ?>
                <div class="info-message" style="background-color: #d1ecf1; color: #0c5460; padding: 10px; border: 1px solid #bee5eb; border-radius: 4px; margin-bottom: 15px;">
                    <p>Please login to continue with your order.</p>
                </div>
            <?php endif; ?>
            <h2>Welcome Back</h2>
            <!-- Error Message from Server -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message" style="display: block; background-color: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 4px; margin-bottom: 15px;">
                    <p><?= htmlspecialchars($_SESSION['error']) ?></p>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            <!-- Error Message Placeholder for JavaScript -->
            <div id="errorMessage" class="error-message" style="display: none;">
                <p>Invalid username or password. Please try again.</p>
            </div>
            <form action="login_process.php" method="POST" id="loginForm">
                <div class="form-group">
                    <input type="text" name="username" placeholder="Enter your username" required aria-label="Username">
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="Enter your password" required aria-label="Password">
                </div>
                <button type="submit" class="login-btn">Login</button>
                <div class="forgot-password">
                    <a href="#" id="forgotPasswordLink">Forgot Password?</a>
                </div>
            </form>
            
            <form id="recoveryForm" style="display: none;">
                <div class="form-group">
                    <input type="email" id="recoveryEmail" name="recoveryEmail" placeholder="Enter your email" required aria-label="Recovery Email">
                </div>
                <button type="submit" class="login-btn">Reset Password</button>
                <div class="back-to-login">
                    <a href="#" id="backToLoginLink">Back to Login</a>
                </div>
            </form>
            <div class="forgot-password">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
            </div>
        </div>
    </main>
    <script src="../assets/js/login.js"></script>
</body>
</html>