<?php
session_start();
include "../includes/db_connect.php";

// Check if the user is an admin or superadmin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Handle delete request (only for superadmin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id']) && $_SESSION['role'] === 'superadmin') {
    $delete_user_id = intval($_POST['delete_user_id']);
    $confirm_password = $_POST['confirm_password'] ?? '';
    // Prevent superadmin from deleting themselves
    if ($delete_user_id !== $_SESSION['user_id']) {
        // Verify password
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();
        if (hash('sha256', $confirm_password) === $hashed_password) {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $delete_user_id);
            $stmt->execute();
            $stmt->close();
            header("Location: manage_users.php");
            exit();
        } else {
            // Password incorrect, show error (handled via modal JS)
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    openPasswordModal($delete_user_id);
                    document.getElementById('modalError').textContent = 'Incorrect password. Please try again.';
                    document.getElementById('modalError').style.display = 'block';
                });
            </script>";
        }
    }
}

// Fetch admin accounts
$admin_query = "SELECT user_id, username, email, created_at FROM users WHERE role = 'admin'";
$admin_result = $conn->query($admin_query);

// Fetch user accounts
$user_query = "SELECT user_id, username, email, created_at FROM users WHERE role = 'user'";
$user_result = $conn->query($user_query);

// Fetch supplier accounts (include contact_number)
$supplier_query = "SELECT user_id, username, email, contact_number, created_at FROM users WHERE role = 'supplier'";
$supplier_result = $conn->query($supplier_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/tabs.css">
    <style>
        /* Override the admin.css padding-top for this page */
        body {
            padding-top: 100px !important;
        }
        
        /* Ensure the admin-container has proper spacing */
        .admin-container {
            margin-top: 20;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>Manage Users</h1>
            <a href="<?= ($_SESSION['role'] === 'superadmin') ? 'sadashboard.php' : 'dashboard.php' ?>" class="btn">Back to Dashboard</a>
        </header>
        <main class="admin-main">
            <!-- Tabs -->
            <div class="user-tabs">
                <button class="user-tab active" data-tab="admins">Admins</button>
                <button class="user-tab" data-tab="users">Users</button>
                <button class="user-tab" data-tab="suppliers">Suppliers</button>
            </div>
            <!-- Admin Accounts Table -->
            <section class="user-table-section active" id="tab-admins">
                <h2>Admin Accounts</h2>
                <table>
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Created At</th>
                            <?php if ($_SESSION['role'] === 'superadmin'): ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $admin_result->data_seek(0); while ($admin = $admin_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($admin['user_id']) ?></td>
                                <td><?= htmlspecialchars($admin['username']) ?></td>
                                <td><?= htmlspecialchars($admin['email']) ?></td>
                                <td><?= htmlspecialchars($admin['created_at']) ?></td>
                                <?php if ($_SESSION['role'] === 'superadmin'): ?>
                                    <td>
                                        <?php if ($admin['user_id'] !== $_SESSION['user_id']): ?>
                                            <button type="button" class="btn delete-user-btn" data-userid="<?= $admin['user_id'] ?>" style="background:#dc3545;">Delete</button>
                                        <?php else: ?>
                                            <span style="color:#888;">(You)</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </section>
            <!-- User Accounts Table -->
            <section class="user-table-section" id="tab-users">
                <h2>User Accounts</h2>
                <table>
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Created At</th>
                            <?php if ($_SESSION['role'] === 'superadmin'): ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $user_result->data_seek(0); while ($user = $user_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['user_id']) ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['created_at']) ?></td>
                                <?php if ($_SESSION['role'] === 'superadmin'): ?>
                                    <td>
                                        <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                            <button type="button" class="btn delete-user-btn" data-userid="<?= $user['user_id'] ?>" style="background:#dc3545;">Delete</button>
                                        <?php else: ?>
                                            <span style="color:#888;">(You)</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </section>
            <!-- Supplier Accounts Table -->
            <section class="user-table-section" id="tab-suppliers">
                <h2>Supplier Accounts</h2>
                <table>
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Contact Number</th>
                            <th>Created At</th>
                            <?php if ($_SESSION['role'] === 'superadmin'): ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $supplier_result->data_seek(0); while ($supplier = $supplier_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($supplier['user_id']) ?></td>
                                <td><?= htmlspecialchars($supplier['username']) ?></td>
                                <td><?= htmlspecialchars($supplier['email']) ?></td>
                                <td><?= htmlspecialchars($supplier['contact_number']) ?></td>
                                <td><?= htmlspecialchars($supplier['created_at']) ?></td>
                                <?php if ($_SESSION['role'] === 'superadmin'): ?>
                                    <td>
                                        <?php if ($supplier['user_id'] !== $_SESSION['user_id']): ?>
                                            <button type="button" class="btn delete-user-btn" data-userid="<?= $supplier['user_id'] ?>" style="background:#dc3545;">Delete</button>
                                        <?php else: ?>
                                            <span style="color:#888;">(You)</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
    <!-- Password Confirmation Modal -->
    <div id="passwordModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;padding:24px 32px;border-radius:8px;max-width:350px;width:90%;box-shadow:0 2px 8px rgba(0,0,0,0.12);position:relative;">
            <h3>Confirm Deletion</h3>
            <form id="confirmDeleteForm" method="POST" style="display:flex;flex-direction:column;gap:12px;">
                <input type="hidden" name="delete_user_id" id="modalDeleteUserId">
                <label for="modalPassword">Enter your password to confirm:</label>
                <input type="password" name="confirm_password" id="modalPassword" required autocomplete="current-password">
                <div id="modalError" style="color:red;font-size:0.95em;display:none;"></div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" onclick="closePasswordModal()" style="background:#888;">Cancel</button>
                    <button type="submit" style="background:#dc3545;">Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    // Tab switching logic
    document.querySelectorAll('.user-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.user-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.user-table-section').forEach(sec => sec.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('tab-' + this.dataset.tab).classList.add('active');
        });
    });
    // Password modal logic
    function openPasswordModal(userId) {
        document.getElementById('modalDeleteUserId').value = userId;
        document.getElementById('modalPassword').value = '';
        document.getElementById('modalError').style.display = 'none';
        document.getElementById('passwordModal').style.display = 'flex';
        setTimeout(() => document.getElementById('modalPassword').focus(), 100);
    }
    function closePasswordModal() {
        document.getElementById('passwordModal').style.display = 'none';
    }
    document.querySelectorAll('.delete-user-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            openPasswordModal(this.dataset.userid);
        });
    });
    </script>
</body>
</html>