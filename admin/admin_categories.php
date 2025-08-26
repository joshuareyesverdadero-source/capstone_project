<?php
session_start();
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')
) {
    header("Location: ../auth/login.php");
    exit();
}
include "../includes/db_connect.php";

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['category_name']);
    $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO categories (category_name, parent_id) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $parent_id);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_categories.php?success=1");
        exit();
    }
}

// Handle Delete Category
if (isset($_GET['delete'])) {
    $cat_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $cat_id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_categories.php?deleted=1");
    exit();
}

// Handle Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $cat_id = intval($_POST['edit_id']);
    $name = trim($_POST['edit_name']);
    $parent_id = !empty($_POST['edit_parent_id']) ? intval($_POST['edit_parent_id']) : null;
    $stmt = $conn->prepare("UPDATE categories SET category_name=?, parent_id=? WHERE category_id=?");
    $stmt->bind_param("sii", $name, $parent_id, $cat_id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_categories.php?updated=1");
    exit();
}

// Fetch categories for display and dropdowns
$categories = [];
$res = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");
while ($row = $res->fetch_assoc()) {
    $categories[] = $row;
}

// For edit form
$edit_category = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM categories WHERE category_id=?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_category = $result->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Categories</title>
    <link rel="stylesheet" href="../assets/css/styles1.css">
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
            <h1>Manage Categories</h1>
            <a href="<?= ($_SESSION['role'] === 'superadmin') ? 'sadashboard.php' : 'dashboard.php' ?>" class="btn">Back to Dashboard</a>
        </header>
        <main class="admin-main">
            <?php if (isset($_GET['success'])): ?>
                <div class="success-message">Category added successfully!</div>
            <?php elseif (isset($_GET['deleted'])): ?>
                <div class="success-message">Category deleted.</div>
            <?php elseif (isset($_GET['updated'])): ?>
                <div class="success-message">Category updated.</div>
            <?php endif; ?>

            <section style="max-width:500px;margin:0 auto 32px auto;">
                <h2 style="margin-top:0;"><?= $edit_category ? "Edit Category" : "Add New Category" ?></h2>
                <form method="POST" style="display:flex;flex-direction:column;gap:16px;">
                    <input type="hidden" name="<?= $edit_category ? 'edit_category' : 'add_category' ?>" value="1">
                    <?php if ($edit_category): ?>
                        <input type="hidden" name="edit_id" value="<?= $edit_category['category_id'] ?>">
                    <?php endif; ?>
                    <label>
                        Category Name:
                        <input type="text" name="<?= $edit_category ? 'edit_name' : 'category_name' ?>" required value="<?= htmlspecialchars($edit_category['category_name'] ?? '') ?>">
                    </label>
                    <label>
                        Parent Category:
                        <select name="<?= $edit_category ? 'edit_parent_id' : 'parent_id' ?>">
                            <option value="">None</option>
                            <?php foreach ($categories as $cat): ?>
                                <?php
                                // Prevent selecting self as parent
                                if ($edit_category && $cat['category_id'] == $edit_category['category_id']) continue;
                                ?>
                                <option value="<?= $cat['category_id'] ?>" <?= ($edit_category && $cat['category_id'] == $edit_category['parent_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div>
                        <button type="submit" class="btn"><?= $edit_category ? "Update" : "Add" ?> Category</button>
                        <?php if ($edit_category): ?>
                            <a href="admin_categories.php" class="btn" style="background:#888;">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <h2 style="margin-top:0;">All Categories</h2>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Parent</th>
                    <th>Actions</th>
                </tr>
                <?php
                foreach ($categories as $cat) {
                    $parent = null;
                    foreach ($categories as $p) {
                        if ($p['category_id'] == $cat['parent_id']) {
                            $parent = $p['category_name'];
                            break;
                        }
                    }
                    echo "<tr>
                        <td>{$cat['category_id']}</td>
                        <td>" . htmlspecialchars($cat['category_name']) . "</td>
                        <td>" . ($parent ? htmlspecialchars($parent) : 'None') . "</td>
                        <td>
                            <a href='admin_categories.php?edit={$cat['category_id']}' class='btn'>Edit</a>
                            <a href='admin_categories.php?delete={$cat['category_id']}' class='btn' style='background:#dc3545;' onclick='return confirm(\"Delete this category?\")'>Delete</a>
                        </td>
                    </tr>";
                }
                ?>
            </table>
        </main>
    </div>
</body>
</html>