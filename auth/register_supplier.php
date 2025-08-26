<?php
session_start();
include "../includes/db_connect.php";

$name = $email = $password = $contact = "";
$success = $error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $contact = trim($_POST["contact"]);

    // Basic validation
    if (!$name || !$email || !$password) {
        $error = "Please fill in all required fields.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Email is already registered.";
        } else {
            // Hash password
            $hashed = hash('sha256', $password);
            $role = 'supplier';
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, contact_number) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $hashed, $role, $contact);
            if ($stmt->execute()) {
                $success = "Registration successful! You may now <a href='login.php'>login</a> as a supplier.";
                $name = $email = $contact = "";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register as Supplier - Julie's RTW Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/login.css?v=2">
</head>
<body>
    <header>
        <img src="../assets/images/logo.png" alt="Julie's RTW Shop Logo" class="shop-logo" style="height:50px;">
        <a href="../home.php" class="back-button">← Back to Home</a>
    </header>
    <main style="display: flex; justify-content: center; align-items: center; flex-direction: column; min-height: 80vh;">
        <div class="login-container">
            <h2>Register as a Supplier</h2>
            <?php if ($success): ?>
                <div class="success-message"><?= $success ?></div>
            <?php elseif ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="name">Name<span style="color:red">*</span></label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" placeholder="Enter your name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email<span style="color:red">*</span></label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="Enter your email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password<span style="color:red">*</span></label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <div class="form-group">
                    <label for="contact">Contact Number</label>
                    <input type="text" id="contact" name="contact" value="<?= htmlspecialchars($contact) ?>" placeholder="Enter your contact number (optional)">
                </div>
                <button type="submit" class="login-btn">Register</button>
            </form>
            <div class="back-to-login">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </main>
</body>
</html>