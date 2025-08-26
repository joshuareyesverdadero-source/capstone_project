<?php
session_start();
include "../includes/db_connect.php";

// Check if the user is an admin or superadmin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch product details
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = intval($_GET['id']);
    $query = "SELECT * FROM products WHERE product_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("Product not found.");
    }

    $product = $result->fetch_assoc();

    // Fetch sizes for this product
    $sizes = [
        'XXS' => 0,
        'XS' => 0,
        'S' => 0,
        'M' => 0,
        'XL' => 0,
        'XXL' => 0
    ];
    $size_stmt = $conn->prepare("SELECT size, quantity FROM product_sizes WHERE product_id = ?");
    $size_stmt->bind_param("i", $product_id);
    $size_stmt->execute();
    $size_res = $size_stmt->get_result();
    while ($sz = $size_res->fetch_assoc()) {
        $sizes[$sz['size']] = $sz['quantity'];
    }
    $size_stmt->close();
} else {
    die("Invalid product ID.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $description = trim($_POST['description']);
    $stock = intval($_POST['stock']);
    $category_id = intval($_POST['category_id']);

    if (!empty($_FILES['image']['name'])) {
        $target_dir = "../assets/images/";
        $image_name = basename($_FILES['image']['name']);
        $target_file = $target_dir . $image_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_url = "assets/images/" . $image_name;
        } else {
            die("Failed to upload image.");
        }
    } else {
        $image_url = $product['image_url']; // Retain the existing image URL
    }

    // Update product in the database
    $update_query = "UPDATE products SET name = ?, price = ?, description = ?, stock = ?, category_id = ?, image_url = ? WHERE product_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sdsisii", $name, $price, $description, $stock, $category_id, $image_url, $product_id);

    if ($stmt->execute()) {
        // Update sizes
        if (isset($_POST['sizes']) && is_array($_POST['sizes'])) {
            $sizes_post = $_POST['sizes'];
            $sum = 0;
            foreach ($sizes_post as $size => $qty) {
                $sum += intval($qty);
            }
            if ($sum != $stock) {
                die("Sum of sizes must equal stock quantity.");
            }
            // Remove old sizes
            $conn->query("DELETE FROM product_sizes WHERE product_id = $product_id");
            // Insert new sizes
            foreach ($sizes_post as $size => $qty) {
                $qty = intval($qty);
                if ($qty > 0) {
                    $size_stmt = $conn->prepare("INSERT INTO product_sizes (product_id, size, quantity) VALUES (?, ?, ?)");
                    $size_stmt->bind_param("isi", $product_id, $size, $qty);
                    $size_stmt->execute();
                    $size_stmt->close();
                }
            }
        }
        include_once "../includes/log_action.php";
        logAdminAction($conn, $_SESSION['user_id'], $_SESSION['role'], "Edited product: $name (ID: $product_id)");
        header("Location: products.php");
        exit();
    } else {
        die("Failed to update product: " . $stmt->error);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .form-container h2 {
            text-align: center;
            color: #b0302f;
            margin-bottom: 20px;
        }

        .form-container label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-container input,
        .form-container textarea,
        .form-container select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-container img {
            display: block;
            max-width: 100px;
            max-height: 100px;
            margin: 10px auto;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .form-container button {
            width: 100%;
            padding: 10px;
            background-color: #b0302f;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .form-container button:hover {
            background-color: #912323;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            text-decoration: none;
            color: #b0302f;
            font-weight: bold;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>Edit Product</h1>
            <a href="products.php" class="btn">Back to Products</a>
        </header>
        <main class="admin-main">
            <div class="form-container">
                <h2>Edit Product Details</h2>
                <form method="POST" enctype="multipart/form-data">
                    <label for="name">Product Name:</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>

                    <label for="price">Price:</label>
                    <input type="number" id="price" name="price" step="0.01" value="<?= $product['price'] ?>" required>

                    <label for="description">Description:</label>
                    <textarea id="description" name="description" rows="4" required><?= htmlspecialchars($product['description']) ?></textarea>

                    <label for="stock">Stock:</label>
                    <input type="number" id="stock" name="stock" value="<?= $product['stock'] ?>" required>

                    <fieldset style="margin-bottom:10px;">
                        <legend>Sizes (quantities must sum up to stock quantity):</legend>
                        <label>XXS:</label>
                        <input type="number" name="sizes[XXS]" min="0" value="<?= $sizes['XXS'] ?>" class="size-input"><br>
                        <label>XS:</label>
                        <input type="number" name="sizes[XS]" min="0" value="<?= $sizes['XS'] ?>" class="size-input"><br>
                        <label>S:</label>
                        <input type="number" name="sizes[S]" min="0" value="<?= $sizes['S'] ?>" class="size-input"><br>
                        <label>M:</label>
                        <input type="number" name="sizes[M]" min="0" value="<?= $sizes['M'] ?>" class="size-input"><br>
                        <label>XL:</label>
                        <input type="number" name="sizes[XL]" min="0" value="<?= $sizes['XL'] ?>" class="size-input"><br>
                        <label>XXL:</label>
                        <input type="number" name="sizes[XXL]" min="0" value="<?= $sizes['XXL'] ?>" class="size-input"><br>
                        <div id="sizeSumError" style="color:red;display:none;">Sum of sizes must equal stock quantity.</div>
                    </fieldset>

                    <label for="category_id">Category:</label>
                    <select id="category_id" name="category_id" required>
                        <?php
                        $categories_query = "SELECT * FROM categories";
                        $categories_result = $conn->query($categories_query);
                        while ($category = $categories_result->fetch_assoc()) {
                            $selected = $product['category_id'] == $category['category_id'] ? 'selected' : '';
                            echo "<option value='{$category['category_id']}' $selected>" . htmlspecialchars($category['category_name']) . "</option>";
                        }
                        ?>
                    </select>

                    <label for="image">Image:</label>
                    <input type="file" id="image" name="image">
                    <img src="../<?= htmlspecialchars($product['image_url']) ?>" alt="Current Image">

                    <button type="submit">Update Product</button>
                </form>
                <a href="products.php" class="back-link">Cancel</a>
            </div>
        </main>
    </div>
</body>
</html>

<script>
function updateSizeInputs() {
    const baseStock = parseInt(document.getElementById('stock').value) || 0;
    const sizeInputs = Array.from(document.querySelectorAll('.size-input'));
    let sum = 0;
    sizeInputs.forEach(inp => { sum += parseInt(inp.value) || 0; });

    // Set max for each input so sum can't exceed baseStock
    sizeInputs.forEach(inp => {
        const otherSum = sum - (parseInt(inp.value) || 0);
        inp.max = Math.max(0, baseStock - otherSum);
        if ((parseInt(inp.value) || 0) > inp.max) {
            inp.value = inp.max;
        }
    });

    document.getElementById('sizeSumError').style.display = (sum !== baseStock) ? 'block' : 'none';
}

document.querySelectorAll('.size-input').forEach(inp => {
    inp.addEventListener('input', updateSizeInputs);
});
document.getElementById('stock').addEventListener('input', updateSizeInputs);

document.querySelector('form').addEventListener('submit', function(e) {
    const baseStock = parseInt(document.getElementById('stock').value) || 0;
    let sum = 0;
    document.querySelectorAll('.size-input').forEach(inp => { sum += parseInt(inp.value) || 0; });
    if (sum !== baseStock) {
        document.getElementById('sizeSumError').style.display = 'block';
        e.preventDefault();
    } else {
        document.getElementById('sizeSumError').style.display = 'none';
    }
});
updateSizeInputs();
</script>