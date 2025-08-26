<?php
session_start();
include "../includes/db_connect.php";
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch all admins for the dropdown
$admins = [];
$admin_res = $conn->query("SELECT user_id, username FROM users WHERE role='admin' ORDER BY username ASC");
while ($row = $admin_res->fetch_assoc()) {
    $admins[] = $row;
}

// Get selected admin from GET
$selected_admin = isset($_GET['admin_id']) ? intval($_GET['admin_id']) : 0;

// Build logs query
$where = "l.role = 'admin'";
if ($selected_admin) {
    $where .= " AND l.user_id = $selected_admin";
}
$result = $conn->query("SELECT l.*, u.username FROM logs l LEFT JOIN users u ON l.user_id = u.user_id WHERE $where ORDER BY l.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Logs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
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
            <h1>Admin Logs</h1>
            <a href="sadashboard.php" class="btn">← Back to Dashboard</a>
        </header>
        <main class="admin-main">
            <form method="get" style="margin-bottom: 24px; display: flex; align-items: center; gap: 16px;">
                <label for="admin_id" style="font-weight:bold;color:#c47181;">Show logs for:</label>
                <select name="admin_id" id="admin_id" onchange="this.form.submit()" style="padding:6px 12px;border-radius:6px;border:1px solid #e88b9b;">
                    <option value="0" <?= $selected_admin == 0 ? 'selected' : '' ?>>All Admins</option>
                    <?php foreach ($admins as $admin): ?>
                        <option value="<?= $admin['user_id'] ?>" <?= $selected_admin == $admin['user_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($admin['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>IP</th>
                            <th>User Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                            <td><?= htmlspecialchars($row['username']) ?> (<?= htmlspecialchars($row['role']) ?>)</td>
                            <td><?= htmlspecialchars($row['action']) ?></td>
                            <td><?= htmlspecialchars($row['ip_address']) ?></td>
                            <td style="max-width:300px;overflow-wrap:anywhere;"><?= htmlspecialchars($row['user_agent']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>