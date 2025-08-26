<?php
session_start();
include "../includes/db_connect.php";

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch categories from the database
$categories_query = "SELECT * FROM categories";
$categories_result = $conn->query($categories_query);

// Organize categories into parent-child structure
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $parent_key = $row['parent_id'] ?? 'root'; // Use 'root' for null parent_id
    $categories[$parent_key][] = $row;
}

$error_message = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_name = $_POST["product_name"];
    $price = $_POST["price"];
    $description = $_POST["description"];
    $category_id = $_POST["category_id"];
    $stock = $_POST["stock"];

    // Validate required fields
    if (empty($product_name) || empty($price) || empty($description) || empty($category_id) || $stock === "" || $stock < 0) {
        $error_message = "Please fill in all required fields and ensure stock is not negative.";
    } else {
        // Check if either varieties or sizes are filled, but not both
        $has_varieties = isset($_POST['varieties']) && is_array($_POST['varieties']) && count($_POST['varieties']) > 0;
        $has_sizes = isset($_POST['sizes']) && is_array($_POST['sizes']) && array_sum($_POST['sizes']) > 0;

        if ($has_varieties && $has_sizes) {
            $error_message = "Please use either Simple Sizes OR Varieties, not both.";
        } elseif (!$has_varieties && !$has_sizes) {
            $error_message = "Please specify either varieties with sizes or simple sizes for the product.";
        } else {
            // Insert product first (without image_url for now)
            $query = "INSERT INTO products (name, price, description, category_id, stock) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sdsii", $product_name, $price, $description, $category_id, $stock);
            if (!$stmt->execute()) {
                die("Product insert failed: " . $stmt->error);
            } else {
                $product_id = $conn->insert_id;

                // Handle multiple image uploads
                $target_dir = "../assets/images/";
                $primary_image_url = "";
                $upload_success = false;
                $uploaded_count = 0;

                if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
                    $total_files = count($_FILES['product_images']['name']);
                    for ($i = 0; $i < $total_files; $i++) {
                        if ($_FILES['product_images']['error'][$i] === UPLOAD_ERR_OK) {
                            $file_extension = strtolower(pathinfo($_FILES['product_images']['name'][$i], PATHINFO_EXTENSION));
                            if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) continue;
                            if ($_FILES['product_images']['size'][$i] > 5 * 1024 * 1024) continue;

                            $unique_name = "product_" . $product_id . "_" . time() . "_" . $i . "." . $file_extension;
                            $target_file = $target_dir . $unique_name;

                            if (move_uploaded_file($_FILES['product_images']['tmp_name'][$i], $target_file)) {
                                $image_url = "assets/images/" . $unique_name;
                                $is_primary = ($i === 0) ? 1 : 0;

                                // Insert into product_images table
                                $img_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)");
                                $img_stmt->bind_param("isii", $product_id, $image_url, $is_primary, $i);
                                $img_stmt->execute();
                                $img_stmt->close();

                                if ($i === 0) {
                                    $primary_image_url = $image_url;
                                    // Update products table with primary image for backward compatibility
                                    $update_stmt = $conn->prepare("UPDATE products SET image_url = ? WHERE product_id = ?");
                                    $update_stmt->bind_param("si", $primary_image_url, $product_id);
                                    $update_stmt->execute();
                                    $update_stmt->close();
                                }

                                $uploaded_count++;
                            }
                        }
                    }
                    if ($uploaded_count > 0) {
                        $upload_success = true;
                    }
                }

                // If no images were uploaded successfully, delete the product
                if (!$upload_success) {
                    $conn->query("DELETE FROM products WHERE product_id = $product_id");
                    $error_message = "Failed to upload any images. Product not created.";
                } else {
                    // Handle varieties and their sizes
                    if ($has_varieties) {
                        $total_variety_qty = 0;
                        foreach ($_POST['varieties'] as $variety_data) {
                            if (isset($variety_data['sizes']) && is_array($variety_data['sizes'])) {
                                foreach ($variety_data['sizes'] as $size => $qty) {
                                    $total_variety_qty += intval($qty);
                                }
                            }
                        }
                        if ($total_variety_qty != intval($stock)) {
                            $error_message = "Total of all variety quantities must equal stock quantity.";
                        } else {
                            foreach ($_POST['varieties'] as $variety_data) {
                                if (!empty(trim($variety_data['name']))) {
                                    $variety_name = trim($variety_data['name']);
                                    $variety_stmt = $conn->prepare("INSERT INTO product_varieties (product_id, variety_name) VALUES (?, ?)");
                                    $variety_stmt->bind_param("is", $product_id, $variety_name);
                                    $variety_stmt->execute();
                                    $variety_id = $conn->insert_id;
                                    $variety_stmt->close();

                                    if (isset($variety_data['sizes']) && is_array($variety_data['sizes'])) {
                                        foreach ($variety_data['sizes'] as $size => $qty) {
                                            $qty = intval($qty);
                                            if ($qty > 0) {
                                                $size_stmt = $conn->prepare("INSERT INTO product_variety_sizes (variety_id, size, quantity) VALUES (?, ?, ?)");
                                                $size_stmt->bind_param("isi", $variety_id, $size, $qty);
                                                $size_stmt->execute();
                                                $size_stmt->close();
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    // Handle legacy sizes (only if varieties weren't used)
                    else if ($has_sizes) {
                        $sizes = $_POST['sizes'];
                        $sum = 0;
                        foreach ($sizes as $size => $qty) {
                            $qty = intval($qty);
                            $sum += $qty;
                        }
                        if ($sum != intval($stock)) {
                            $error_message = "Sum of sizes must equal stock quantity.";
                        } else {
                            foreach ($sizes as $size => $qty) {
                                $qty = intval($qty);
                                if ($qty > 0) {
                                    $size_stmt = $conn->prepare("INSERT INTO product_sizes (product_id, size, quantity) VALUES (?, ?, ?)");
                                    $size_stmt->bind_param("isi", $product_id, $size, $qty);
                                    $size_stmt->execute();
                                    $size_stmt->close();
                                }
                            }
                        }
                    }

                    if (empty($error_message)) {
                        include_once "../includes/log_action.php";
                        logAdminAction($conn, $_SESSION['user_id'], $_SESSION['role'], "Added product: $product_name (ID: $product_id)");
                        $success_message = "Product added successfully! Product ID: " . $product_id . " (Uploaded images: " . $uploaded_count . ")";
                    } else {
                        // If there was a validation error after product insert, delete the product
                        $conn->query("DELETE FROM products WHERE product_id = $product_id");
                    }
                }
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add Product</title>
    <link rel="stylesheet" href="../assets/css/styles1.css">
    <link rel="stylesheet" href="../assets/css/addproduct.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* Override the admin.css padding-top for this page */
        body {
            padding-top: 350px !important;
        }
        
        /* Ensure the admin-container has proper spacing */
        .admin-container {
            margin-top: 0;
        }

        /* Image Upload Styles */
        .image-upload-container {
            margin-bottom: 20px;
        }

        .image-upload-area {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 40px 20px;
            text-align: center;
            background-color: #f9f9f9;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            position: relative;
        }

        .image-upload-area:hover {
            border-color: #b0302f;
            background-color: #f5f5f5;
        }

        .image-upload-area.dragover {
            border-color: #b0302f;
            background-color: #fff5f5;
            transform: scale(1.02);
        }

        .image-upload-area.has-images {
            padding: 20px;
            background-color: #f0f8ff;
            border-color: #007bff;
        }

        .upload-prompt {
            color: #666;
        }

        .upload-icon {
            font-size: 48px;
            margin-bottom: 15px;
            color: #b0302f;
        }

        .upload-note {
            font-size: 12px;
            color: #888;
            margin-top: 8px;
        }

        .upload-status {
            margin-top: 10px;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            display: none;
        }

        .upload-status.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .upload-status.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .image-preview-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .image-preview-item {
            position: relative;
            border: 2px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .image-preview-item.primary {
            border-color: #b0302f;
            box-shadow: 0 0 0 2px rgba(176, 48, 47, 0.2);
        }

        .image-preview-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            display: block;
        }

        .image-preview-controls {
            position: absolute;
            top: 5px;
            right: 5px;
            display: flex;
            gap: 5px;
        }

        .image-preview-btn {
            background: rgba(0,0,0,0.7);
            color: white;
            border: none;
            padding: 5px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.2s;
        }

        .image-preview-btn:hover {
            background: rgba(0,0,0,0.9);
        }

        .image-preview-btn.primary {
            background: rgba(176, 48, 47, 0.9);
        }

        .image-preview-btn.remove {
            background: rgba(220, 53, 69, 0.9);
        }

        .image-preview-label {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 5px;
            font-size: 11px;
            text-align: center;
        }

        .primary-badge {
            background: #b0302f;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>Add Product</h1>
            <a href="<?= ($_SESSION['role'] === 'superadmin') ? 'sadashboard.php' : 'dashboard.php' ?>" class="btn">Back to Dashboard</a>
        </header>
        <main class="admin-main">
            <form method="post" enctype="multipart/form-data" id="addProductForm">
                <label>Product Name:</label>
                <input type="text" name="product_name" required><br>

                <label>Price:</label>
                <input type="number" name="price" step="0.01" required><br>

                <label>Description:</label>
                <textarea name="description" required></textarea><br>

                <label>Category:</label>
                <select name="category_id" required>
                    <option value="" disabled selected>Select a category</option>
                    <?php if (isset($categories['root'])): ?>
                        <?php foreach ($categories['root'] as $parent): ?>
                            <optgroup label="<?= htmlspecialchars($parent['category_name']) ?>">
                                <?php if (isset($categories[$parent['category_id']])): ?>
                                    <?php foreach ($categories[$parent['category_id']] as $child): ?>
                                        <option value="<?= $child['category_id'] ?>">
                                            <?= htmlspecialchars($child['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select><br>

                <label>Stock Quantity:</label>
                <input type="number" name="stock" id="base_stock" min="0" required><br>

                <!-- Add Varieties Section -->
                <fieldset style="margin-bottom:15px; border: 2px solid #e0e0e0; padding: 15px; border-radius: 5px;">
                    <legend style="font-weight: bold; color: #333;">Add Varieties</legend>
                    <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">
                        Add product varieties (colors, materials, etc.) with their sizes. Each variety can have different quantities per size.
                    </p>
                    
                    <div style="margin-bottom: 10px;">
                        <button type="button" onclick="addVariety()" class="btn" style="background: #28a745; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer;">
                            + Add Variety
                        </button>
                        <button type="button" onclick="clearAllVarieties()" class="btn" style="background: #dc3545; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                            Clear All
                        </button>
                    </div>
                    
                    <div id="varieties-container" style="min-height: 50px;">
                        <!-- Varieties will be added here dynamically -->
                    </div>
                    
                    <div id="varietySumError" style="color: #dc3545; background: #f8d7da; padding: 8px; border-radius: 4px; display: none; margin-top: 10px;">
                        Total of all variety quantities must equal stock quantity.
                    </div>
                    <div id="varietyTotal" style="color: #666; font-size: 14px; margin-top: 10px;">
                        Total varieties quantity: <span id="varietyTotalValue">0</span>
                    </div>
                </fieldset>

                <!-- Legacy Sizes Section (Alternative to varieties) -->
                <fieldset style="margin-bottom:15px; border: 2px solid #e0e0e0; padding: 15px; border-radius: 5px;">
                    <legend style="font-weight: bold; color: #333;">OR Use Simple Sizes</legend>
                    <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">
                        Use this for products without varieties. Total quantities must equal stock quantity.
                    </p>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap: 10px; max-width: 500px;">
                        <div style="text-align: center;">
                            <label style="font-size: 12px; font-weight: bold;">XXS</label>
                            <input type="number" name="sizes[XXS]" min="0" value="0" class="size-input" style="width: 100%; text-align: center;">
                        </div>
                        <div style="text-align: center;">
                            <label style="font-size: 12px; font-weight: bold;">XS</label>
                            <input type="number" name="sizes[XS]" min="0" value="0" class="size-input" style="width: 100%; text-align: center;">
                        </div>
                        <div style="text-align: center;">
                            <label style="font-size: 12px; font-weight: bold;">S</label>
                            <input type="number" name="sizes[S]" min="0" value="0" class="size-input" style="width: 100%; text-align: center;">
                        </div>
                        <div style="text-align: center;">
                            <label style="font-size: 12px; font-weight: bold;">M</label>
                            <input type="number" name="sizes[M]" min="0" value="0" class="size-input" style="width: 100%; text-align: center;">
                        </div>
                        <div style="text-align: center;">
                            <label style="font-size: 12px; font-weight: bold;">L</label>
                            <input type="number" name="sizes[L]" min="0" value="0" class="size-input" style="width: 100%; text-align: center;">
                        </div>
                        <div style="text-align: center;">
                            <label style="font-size: 12px; font-weight: bold;">XL</label>
                            <input type="number" name="sizes[XL]" min="0" value="0" class="size-input" style="width: 100%; text-align: center;">
                        </div>
                        <div style="text-align: center;">
                            <label style="font-size: 12px; font-weight: bold;">XXL</label>
                            <input type="number" name="sizes[XXL]" min="0" value="0" class="size-input" style="width: 100%; text-align: center;">
                        </div>
                    </div>
                    
                    <div id="sizeSumError" style="color: #dc3545; background: #f8d7da; padding: 8px; border-radius: 4px; display: none; margin-top: 10px;">
                        Sum of sizes must equal stock quantity.
                    </div>
                    <div id="sizeTotal" style="color: #666; font-size: 14px; margin-top: 10px;">
                        Total sizes quantity: <span id="sizeTotalValue">0</span>
                    </div>
                </fieldset>

                <label>Product Images:</label>
                <div class="image-upload-container">
                    <div class="image-upload-area" id="imageUploadArea">
                        <div class="upload-prompt" id="uploadPrompt">
                            <div class="upload-icon"><img src="../assets/icons/image_icon.png" alt="Upload" style="width: 48px; height: 48px;"></div>
                            <p>Drag & drop images here or click to browse</p>
                            <p class="upload-note">You can upload multiple images. First image will be the main product image.</p>
                            <p class="upload-note">Supported formats: JPG, PNG, GIF | Max size: 5MB per image</p>
                        </div>
                        <input type="file" name="product_images[]" id="productImages" multiple accept="image/*" style="display: none;">
                    </div>
                    <div class="upload-status" id="uploadStatus"></div>
                    <div class="image-preview-container" id="imagePreviewContainer">
                        <!-- Image previews will appear here -->
                    </div>
                </div>

                <button type="submit" class="btn">Upload</button>
                <?php if (isset($success_message)): ?>
                    <div class="success-message"><?= $success_message ?></div>
                <?php elseif (isset($error_message)): ?>
                    <div class="error-message"><?= $error_message ?></div>
                <?php endif; ?>
            </form>
        </main>
    </div>
</body>
<script>
let varietyIndex = 0;
let selectedImages = [];
let primaryImageIndex = 0;

// Image Upload Functionality
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('imageUploadArea');
    const fileInput = document.getElementById('productImages');
    const previewContainer = document.getElementById('imagePreviewContainer');

    // Click to browse files
    uploadArea.addEventListener('click', function() {
        fileInput.click();
    });

    // File input change event
    fileInput.addEventListener('change', function(e) {
        handleFiles(e.target.files);
    });

    // Drag and drop events
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });

    function handleFiles(files) {
        const statusDiv = document.getElementById('uploadStatus');
        const uploadArea = document.getElementById('imageUploadArea');
        let validFiles = 0;
        let invalidFiles = 0;

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            
            // Validate file type
            if (!file.type.startsWith('image/')) {
                invalidFiles++;
                continue;
            }
            
            // Validate file size (5MB limit)
            if (file.size > 5 * 1024 * 1024) {
                invalidFiles++;
                continue;
            }
            
            addImagePreview(file);
            validFiles++;
        }
        
        // Update UI based on upload results
        if (validFiles > 0) {
            uploadArea.classList.add('has-images');
            document.getElementById('uploadPrompt').innerHTML = `
                <div class="upload-icon"><img src="../assets/icons/image_icon.png" alt="Upload" style="width: 48px; height: 48px;"></div>
                <p>Click to add more images or drag & drop</p>
                <p class="upload-note">${selectedImages.length} image(s) selected</p>
            `;
            
            if (invalidFiles > 0) {
                showUploadStatus(`${validFiles} images added successfully. ${invalidFiles} files were skipped (invalid format or too large).`, 'success');
            } else {
                showUploadStatus(`${validFiles} image(s) added successfully!`, 'success');
            }
        } else if (invalidFiles > 0) {
            showUploadStatus(`${invalidFiles} files were rejected. Please upload valid image files (JPG, PNG, GIF) under 5MB.`, 'error');
        }
        
        updateFileInput();
    }

    function addImagePreview(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const imageIndex = selectedImages.length;
            selectedImages.push(file);
            
            const previewItem = document.createElement('div');
            previewItem.className = 'image-preview-item' + (imageIndex === 0 ? ' primary' : '');
            previewItem.dataset.index = imageIndex;
            
            previewItem.innerHTML = `
                <img src="${e.target.result}" alt="Preview">
                <div class="image-preview-controls">
                    ${imageIndex !== 0 ? `<button type="button" class="image-preview-btn primary" onclick="setPrimaryImage(${imageIndex})">★</button>` : ''}
                    <button type="button" class="image-preview-btn remove" onclick="removeImage(${imageIndex})">×</button>
                </div>
                <div class="image-preview-label">
                    ${imageIndex === 0 ? '<span class="primary-badge">PRIMARY</span>' : `Image ${imageIndex + 1}`}
                </div>
            `;
            
            previewContainer.appendChild(previewItem);
        };
        reader.readAsDataURL(file);
    }

    function showUploadStatus(message, type) {
        const statusDiv = document.getElementById('uploadStatus');
        statusDiv.textContent = message;
        statusDiv.className = `upload-status ${type}`;
        statusDiv.style.display = 'block';
        
        // Hide status after 3 seconds
        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, 3000);
    }

    window.setPrimaryImage = function(newPrimaryIndex) {
        // Swap images in array
        const temp = selectedImages[0];
        selectedImages[0] = selectedImages[newPrimaryIndex];
        selectedImages[newPrimaryIndex] = temp;
        
        // Update primary index
        primaryImageIndex = 0;
        
        // Refresh preview
        refreshImagePreviews();
        updateFileInput();
    };

    window.removeImage = function(index) {
        selectedImages.splice(index, 1);
        refreshImagePreviews();
        updateFileInput();
    };

    function refreshImagePreviews() {
        previewContainer.innerHTML = '';
        selectedImages.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewItem = document.createElement('div');
                previewItem.className = 'image-preview-item' + (index === 0 ? ' primary' : '');
                previewItem.dataset.index = index;
                
                previewItem.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <div class="image-preview-controls">
                        ${index !== 0 ? `<button type="button" class="image-preview-btn primary" onclick="setPrimaryImage(${index})">★</button>` : ''}
                        <button type="button" class="image-preview-btn remove" onclick="removeImage(${index})">×</button>
                    </div>
                    <div class="image-preview-label">
                        ${index === 0 ? '<span class="primary-badge">PRIMARY</span>' : `Image ${index + 1}`}
                    </div>
                `;
                
                previewContainer.appendChild(previewItem);
            };
            reader.readAsDataURL(file);
        });
        
        // Update upload area status
        const uploadArea = document.getElementById('imageUploadArea');
        if (selectedImages.length > 0) {
            uploadArea.classList.add('has-images');
            document.getElementById('uploadPrompt').innerHTML = `
                <div class="upload-icon"><img src="../assets/icons/image_icon.png" alt="Upload" style="width: 48px; height: 48px;"></div>
                <p>Click to add more images or drag & drop</p>
                <p class="upload-note">${selectedImages.length} image(s) selected</p>
            `;
        } else {
            uploadArea.classList.remove('has-images');
            document.getElementById('uploadPrompt').innerHTML = `
                <div class="upload-icon"><img src="../assets/icons/image_icon.png" alt="Upload" style="width: 48px; height: 48px;"></div>
                <p>Drag & drop images here or click to browse</p>
                <p class="upload-note">You can upload multiple images. First image will be the main product image.</p>
                <p class="upload-note">Supported formats: JPG, PNG, GIF | Max size: 5MB per image</p>
            `;
        }
    }

    function updateFileInput() {
        const dt = new DataTransfer();
        selectedImages.forEach(file => {
            dt.items.add(file);
        });
        fileInput.files = dt.files;
    }

    // Form validation
    document.getElementById('addProductForm').addEventListener('submit', function(e) {
        if (selectedImages.length === 0) {
            alert('Please upload at least one product image.');
            e.preventDefault();
            return;
        }
    });
});

// Existing variety and size validation functions...

function addVariety() {
    const container = document.getElementById('varieties-container');
    const varietyHtml = `
        <div class="variety-item" data-variety-index="${varietyIndex}" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px; background: #f9f9f9;">
            <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 15px;">
                <input type="text" name="varieties[${varietyIndex}][name]" placeholder="Variety name (e.g., Red, Blue, Cotton)" class="variety-name" required style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                <button type="button" onclick="removeVariety(${varietyIndex})" style="background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">Remove</button>
            </div>
            <div class="variety-sizes">
                <label style="font-size: 14px; margin-bottom: 10px; display: block; font-weight: bold;">Sizes for this variety:</label>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(70px, 1fr)); gap: 8px; max-width: 500px;">
                    <div style="text-align: center;">
                        <label style="font-size: 11px; font-weight: bold;">XXS</label>
                        <input type="number" name="varieties[${varietyIndex}][sizes][XXS]" min="0" value="0" class="variety-size-input" data-variety="${varietyIndex}" style="width: 100%; text-align: center; padding: 4px;">
                    </div>
                    <div style="text-align: center;">
                        <label style="font-size: 11px; font-weight: bold;">XS</label>
                        <input type="number" name="varieties[${varietyIndex}][sizes][XS]" min="0" value="0" class="variety-size-input" data-variety="${varietyIndex}" style="width: 100%; text-align: center; padding: 4px;">
                    </div>
                    <div style="text-align: center;">
                        <label style="font-size: 11px; font-weight: bold;">S</label>
                        <input type="number" name="varieties[${varietyIndex}][sizes][S]" min="0" value="0" class="variety-size-input" data-variety="${varietyIndex}" style="width: 100%; text-align: center; padding: 4px;">
                    </div>
                    <div style="text-align: center;">
                        <label style="font-size: 11px; font-weight: bold;">M</label>
                        <input type="number" name="varieties[${varietyIndex}][sizes][M]" min="0" value="0" class="variety-size-input" data-variety="${varietyIndex}" style="width: 100%; text-align: center; padding: 4px;">
                    </div>
                    <div style="text-align: center;">
                        <label style="font-size: 11px; font-weight: bold;">L</label>
                        <input type="number" name="varieties[${varietyIndex}][sizes][L]" min="0" value="0" class="variety-size-input" data-variety="${varietyIndex}" style="width: 100%; text-align: center; padding: 4px;">
                    </div>
                    <div style="text-align: center;">
                        <label style="font-size: 11px; font-weight: bold;">XL</label>
                        <input type="number" name="varieties[${varietyIndex}][sizes][XL]" min="0" value="0" class="variety-size-input" data-variety="${varietyIndex}" style="width: 100%; text-align: center; padding: 4px;">
                    </div>
                    <div style="text-align: center;">
                        <label style="font-size: 11px; font-weight: bold;">XXL</label>
                        <input type="number" name="varieties[${varietyIndex}][sizes][XXL]" min="0" value="0" class="variety-size-input" data-variety="${varietyIndex}" style="width: 100%; text-align: center; padding: 4px;">
                    </div>
                </div>
                <div style="margin-top: 8px; font-size: 12px; color: #666;">
                    Variety total: <span class="variety-total" data-variety="${varietyIndex}">0</span>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', varietyHtml);
    
    // Add event listeners to new variety inputs
    addVarietyEventListeners();
    varietyIndex++;
}

function removeVariety(index) {
    const varietyItem = document.querySelector(`[data-variety-index="${index}"]`);
    if (varietyItem) {
        varietyItem.remove();
        updateVarietyValidation();
    }
}

function clearAllVarieties() {
    const container = document.getElementById('varieties-container');
    container.innerHTML = '';
    updateVarietyValidation();
}

function addVarietyEventListeners() {
    document.querySelectorAll('.variety-size-input').forEach(inp => {
        inp.removeEventListener('input', updateVarietyValidation);
        inp.addEventListener('input', updateVarietyValidation);
    });
}

function updateVarietyValidation() {
    const baseStock = parseInt(document.getElementById('base_stock').value) || 0;
    let totalVarietySum = 0;
    
    // Calculate total for each variety and overall total
    document.querySelectorAll('.variety-item').forEach(varietyItem => {
        const varietyIndex = varietyItem.getAttribute('data-variety-index');
        let varietySum = 0;
        
        varietyItem.querySelectorAll('.variety-size-input').forEach(inp => {
            varietySum += parseInt(inp.value) || 0;
        });
        
        // Update variety total display
        const varietyTotalSpan = varietyItem.querySelector('.variety-total');
        if (varietyTotalSpan) {
            varietyTotalSpan.textContent = varietySum;
        }
        
        totalVarietySum += varietySum;
    });
    
    // Update main variety total
    document.getElementById('varietyTotalValue').textContent = totalVarietySum;
    
    // Show/hide variety error
    const varietyError = document.getElementById('varietySumError');
    if (totalVarietySum > 0 && totalVarietySum !== baseStock) {
        varietyError.style.display = 'block';
        varietyError.textContent = `Total of all variety quantities (${totalVarietySum}) must equal stock quantity (${baseStock}).`;
    } else {
        varietyError.style.display = 'none';
    }
}

function updateSizeValidation() {
    const baseStock = parseInt(document.getElementById('base_stock').value) || 0;
    let totalSizeSum = 0;
    
    document.querySelectorAll('.size-input').forEach(inp => {
        totalSizeSum += parseInt(inp.value) || 0;
    });
    
    // Update size total
    document.getElementById('sizeTotalValue').textContent = totalSizeSum;
    
    // Show/hide size error
    const sizeError = document.getElementById('sizeSumError');
    if (totalSizeSum > 0 && totalSizeSum !== baseStock) {
        sizeError.style.display = 'block';
        sizeError.textContent = `Sum of sizes (${totalSizeSum}) must equal stock quantity (${baseStock}).`;
    } else {
        sizeError.style.display = 'none';
    }
}

// Add event listeners for size inputs
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.size-input').forEach(inp => {
        inp.addEventListener('input', updateSizeValidation);
    });
    
    document.getElementById('base_stock').addEventListener('input', function() {
        updateSizeValidation();
        updateVarietyValidation();
    });
});

// Prevent form submit if validation fails
document.getElementById('addProductForm').addEventListener('submit', function(e) {
    const baseStock = parseInt(document.getElementById('base_stock').value) || 0;
    
    // Check if images are uploaded
    if (selectedImages.length === 0) {
        alert('Please upload at least one product image.');
        e.preventDefault();
        return;
    }
    
    // Check legacy sizes
    let legacySum = 0;
    document.querySelectorAll('.size-input').forEach(inp => { 
        legacySum += parseInt(inp.value) || 0; 
    });
    
    // Check variety sizes
    let varietySum = 0;
    document.querySelectorAll('.variety-size-input').forEach(inp => { 
        varietySum += parseInt(inp.value) || 0; 
    });
    
    // Only allow submission if either legacy sizes OR varieties match stock (but not both)
    if (legacySum > 0 && varietySum > 0) {
        alert('Please use either Simple Sizes OR Varieties, not both.');
        e.preventDefault();
        return;
    }
    
    if (legacySum > 0 && legacySum !== baseStock) {
        alert(`Sum of sizes (${legacySum}) must equal stock quantity (${baseStock}).`);
        document.getElementById('sizeSumError').style.display = 'block';
        e.preventDefault();
        return;
    }
    
    if (varietySum > 0 && varietySum !== baseStock) {
        alert(`Total of all variety quantities (${varietySum}) must equal stock quantity (${baseStock}).`);
        document.getElementById('varietySumError').style.display = 'block';
        e.preventDefault();
        return;
    }
    
    if (legacySum === 0 && varietySum === 0 && baseStock > 0) {
        alert('Please specify either varieties with sizes or simple sizes for the product.');
        e.preventDefault();
        return;
    }
});
</script>
</html>