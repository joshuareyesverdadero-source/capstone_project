<?php
session_start();
if (isset($_SESSION['user_id'])) {
    session_destroy();
    header("Location: ../home.php"); // Adjusted path to ensure correct redirection
    exit();
} else {
    header("Location: ../home.php"); // Redirect if already logged out
    exit();
}
?>