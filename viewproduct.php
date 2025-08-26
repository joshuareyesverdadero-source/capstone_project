<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include "includes/db_connect.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid product ID.");
}

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

// Fetch all images for this product
$images_stmt = $conn->prepare("SELECT image_url, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC");
$images_stmt->bind_param("i", $product['product_id']);
$images_stmt->execute();
$images_res = $images_stmt->get_result();
$product_images = [];
while ($img = $images_res->fetch_assoc()) {
    $product_images[] = $img;
}
$images_stmt->close();

// If no images in product_images table, fall back to the main image_url
if (empty($product_images) && !empty($product['image_url'])) {
    $product_images[] = ['image_url' => $product['image_url'], 'is_primary' => 1];
}

// Fetch varieties and their sizes for this product
$varieties_stmt = $conn->prepare("
    SELECT 
        pv.variety_id, 
        pv.variety_name,
        pvs.size,
        pvs.quantity
    FROM product_varieties pv
    LEFT JOIN product_variety_sizes pvs ON pv.variety_id = pvs.variety_id
    WHERE pv.product_id = ?
    ORDER BY pv.variety_name, pvs.size
");
$varieties_stmt->bind_param("i", $product['product_id']);
$varieties_stmt->execute();
$varieties_res = $varieties_stmt->get_result();

$varieties = [];
$has_varieties = false;
while ($variety = $varieties_res->fetch_assoc()) {
    $has_varieties = true;
    $variety_name = $variety['variety_name'];
    if (!isset($varieties[$variety_name])) {
        $varieties[$variety_name] = [
            'variety_id' => $variety['variety_id'],
            'sizes' => []
        ];
    }
    if ($variety['size'] && $variety['quantity'] > 0) {
        $varieties[$variety_name]['sizes'][$variety['size']] = $variety['quantity'];
    }
}
$varieties_stmt->close();

// Fallback to legacy product_sizes if no varieties exist
$legacy_sizes = [];
if (!$has_varieties) {
    $sizes_stmt = $conn->prepare("SELECT size, quantity FROM product_sizes WHERE product_id = ?");
    $sizes_stmt->bind_param("i", $product['product_id']);
    $sizes_stmt->execute();
    $sizes_res = $sizes_stmt->get_result();
    while ($sz = $sizes_res->fetch_assoc()) {
        $legacy_sizes[$sz['size']] = $sz['quantity'];
    }
    $sizes_stmt->close();
}

$sizes_list = ['XXS', 'XS', 'S', 'M', 'XL', 'XXL'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> | Julie's RTW Shop</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        /* Product Image Gallery Styles */
        .product-image-gallery {
            position: relative;
        }

        .main-product-image {
            width: 100%;
            max-width: 100%;
            height: 500px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .gallery-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            transition: background 0.3s ease;
            z-index: 10;
        }

        .gallery-nav:hover {
            background: rgba(0,0,0,0.7);
        }

        .gallery-nav.prev {
            left: 10px;
        }

        .gallery-nav.next {
            right: 10px;
        }

        .image-counter {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
        }

        /* Product Details Styles */
        .product-details {
            display: flex;
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .left-side {
            flex: 1;
        }

        .right-side {
            flex: 1;
            padding: 20px;
        }

        .product-price {
            font-size: 24px;
            color: #b0302f;
            font-weight: bold;
            margin: 10px 0;
        }

        .product-stock {
            color: #666;
            margin-bottom: 20px;
        }

        /* Variety and Size Selection Styles */
        .variety-option {
            margin-bottom: 15px;
        }

        .variety-btn, .size-btn {
            display: inline-block;
            padding: 10px 16px;
            margin: 4px;
            border: 2px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: white;
            text-decoration: none;
            font-size: 14px;
            text-align: center;
            min-width: 50px;
        }

        .variety-btn:hover, .size-btn:hover {
            border-color: #b0302f;
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .variety-btn.active, .size-btn.active {
            background-color: #b0302f;
            border-color: #b0302f;
            color: white;
        }

        .size-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .add-to-cart {
            background-color: #b0302f;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
            width: 100%;
            max-width: 300px;
        }

        .add-to-cart:hover {
            background-color: #8a2426;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .add-to-cart:disabled {
            background-color: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Error messages */
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .product-details {
                flex-direction: column;
                padding: 10px;
            }

            .size-selector {
                justify-content: center;
            }

            .variety-btn, .size-btn {
                padding: 8px 12px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <header>
        <button onclick="history.back()" class="back-button">← Back</button>
        <div class="right-icons">
            <div class="icons">
                <a href="cart.php" class="cart-icon" aria-label="View cart">
                    <img src="assets/icons/atc.png" alt="Cart" style="height:28px;width:28px;">
                </a>
                <a href="cs_orders.php" class="orders-icon" aria-label="Your orders">
                    <img src="assets/icons/orders.png" alt="Orders" style="height:28px;width:28px;">
                </a>
                <div class="profile-icon" style="position:relative;">
                    <button aria-label="Profile menu" style="background:none;border:none;padding:0;">
                        <img src="assets/icons/user.png" alt="Profile" style="height:28px;width:28px;">
                    </button>
                    <div class="profile-dropdown">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="auth/logout.php">Logout</a>
                        <?php else: ?>
                            <a href="auth/login.php">Login</a>
                            <a href="auth/register.php">Register</a>
                        <?php endif; ?>
                    </div>
                </div>
                <button class="menu-icon" onclick="toggleSidebar()" aria-label="Open sidebar" style="background:none;border:none;padding:0;">
                    <img src="assets/icons/menu.png" alt="Menu" style="height:28px;width:28px;">
                </button>
            </div>
        </div>
    </header>
    <div class="sidebar" id="sidebar">
        <ul>
            <li><a href="home.php">Home</a></li>
            <li><a href="#">Contact</a></li>
            <li><a href="#">About</a></li>
        </ul>
    </div>
    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>
    <main>
        <div class="product-details">
            <div class="left-side">
                <div class="product-image-gallery">
                    <?php if (!empty($product_images)): ?>
                        <?php if (count($product_images) > 1): ?>
                            <button class="gallery-nav prev" onclick="changeImage(-1)">‹</button>
                            <button class="gallery-nav next" onclick="changeImage(1)">›</button>
                            <div class="image-counter">
                                <span id="currentImageIndex">1</span> / <?= count($product_images) ?>
                            </div>
                        <?php endif; ?>
                        
                        <img id="mainProductImage" 
                             src="<?= htmlspecialchars($product_images[0]['image_url']) ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             class="main-product-image">
                        
                    <?php else: ?>
                        <img src="assets/images/default.png" 
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             class="main-product-image">
                    <?php endif; ?>
                </div>
                <p class="product-description"><?= htmlspecialchars($product['description']) ?></p>
            </div>
            <div class="right-side">
                <h2><?= htmlspecialchars($product['name']) ?></h2>
                <p class="product-price">₱<?= number_format($product['price'], 2) ?></p>
                <p class="product-stock">Stock: <?= $product['stock'] ?> left</p>
                <form method="POST" action="add_to_cart.php" id="addToCartForm">
                    <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                    
                    <?php if ($has_varieties): ?>
                        <!-- Variety Selection -->
                        <label for="variety" style="margin-bottom: 8px; font-weight: bold;">Choose Variety:</label>
                        <div class="variety-selector" style="margin-bottom: 20px;">
                            <?php foreach ($varieties as $variety_name => $variety_data): ?>
                                <div class="variety-option" style="margin-bottom: 10px;">
                                    <input type="radio" id="variety-<?= htmlspecialchars($variety_name) ?>" 
                                           name="variety" value="<?= htmlspecialchars($variety_name) ?>" 
                                           data-variety-id="<?= $variety_data['variety_id'] ?>"
                                           onchange="updateSizeOptions('<?= htmlspecialchars($variety_name) ?>')">
                                    <label for="variety-<?= htmlspecialchars($variety_name) ?>" class="variety-btn" 
                                           style="display: inline-block; padding: 8px 16px; margin-left: 8px; border: 2px solid #ddd; border-radius: 4px; cursor: pointer; transition: all 0.3s ease;">
                                        <?= htmlspecialchars($variety_name) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div id="varietyError" style="color:#dc3545;display:none;margin-bottom:10px;">Please select a variety first.</div>

                        <!-- Size Selection (Dynamic based on variety) -->
                        <label for="size" style="margin-bottom: 8px; font-weight: bold;">Size:</label>
                        <div class="size-selector" id="sizeSelector" style="display: flex; gap: 12px; margin-bottom: 18px; opacity: 0.5;">
                            <p style="color: #888; font-style: italic;">Please select a variety first</p>
                        </div>
                        
                    <?php else: ?>
                        <!-- Legacy Size Selection (when no varieties) -->
                        <label for="size" style="margin-bottom: 8px; font-weight: bold;">Size:</label>
                        <div class="size-selector" style="display: flex; gap: 12px; margin-bottom: 18px;">
                            <?php foreach ($sizes_list as $size): ?>
                                <?php if (isset($legacy_sizes[$size]) && $legacy_sizes[$size] > 0): ?>
                                    <div style="display: flex; flex-direction: column; align-items: center; min-width: 54px;">
                                        <input type="radio" id="size-<?= $size ?>" name="size" value="<?= $size ?>">
                                        <label for="size-<?= $size ?>" class="size-btn" 
                                               style="padding: 8px 12px; border: 2px solid #ddd; border-radius: 4px; cursor: pointer; transition: all 0.3s ease;">
                                            <?= $size ?>
                                        </label>
                                        <span style="font-size:0.85em; color:#888; margin-top:3px;"><?= $legacy_sizes[$size] ?> left</span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div id="sizeError" style="color:#dc3545;display:none;margin-bottom:10px;">Please select a size before adding to cart.</div>
                    
                    <!-- Quantity Selection -->
                    <label for="quantity" style="margin-bottom: 8px; font-weight: bold;">Quantity:</label>
                    <div style="margin-bottom: 20px;">
                        <input type="number" id="quantity" name="quantity" value="1" min="1" max="1" 
                               style="width: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <span id="maxQuantityText" style="margin-left: 10px; color: #888; font-size: 0.9em;">Max: 1</span>
                    </div>
                    
                    <button type="submit" class="add-to-cart" 
                            style="background-color: #b0302f; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; transition: background-color 0.3s ease;">
                        Add to Cart
                    </button>
                </form>
            </div>
        </div>
    </main>
    <script>
        let currentImageIndex = 0;
        const productImages = <?= json_encode(array_column($product_images, 'image_url')) ?>;
        const varietiesData = <?= json_encode($varieties) ?>;
        const hasVarieties = <?= json_encode($has_varieties) ?>;

        function showImage(index) {
            if (index >= 0 && index < productImages.length) {
                currentImageIndex = index;
                
                // Update main image
                document.getElementById('mainProductImage').src = productImages[index];
                
                // Update counter
                const counter = document.getElementById('currentImageIndex');
                if (counter) {
                    counter.textContent = index + 1;
                }
            }
        }

        function changeImage(direction) {
            const newIndex = currentImageIndex + direction;
            if (newIndex >= 0 && newIndex < productImages.length) {
                showImage(newIndex);
            } else if (newIndex < 0) {
                showImage(productImages.length - 1); // Go to last image
            } else if (newIndex >= productImages.length) {
                showImage(0); // Go to first image
            }
        }

        function updateSizeOptions(varietyName) {
            const sizeSelector = document.getElementById('sizeSelector');
            const varietyData = varietiesData[varietyName];
            
            if (!varietyData || !varietyData.sizes) {
                sizeSelector.innerHTML = '<p style="color: #888; font-style: italic;">No sizes available for this variety</p>';
                return;
            }
            
            const sizes = ['XXS', 'XS', 'S', 'M', 'XL', 'XXL'];
            let sizeHtml = '';
            let hasAvailableSizes = false;
            
            sizes.forEach(size => {
                if (varietyData.sizes[size] && varietyData.sizes[size] > 0) {
                    hasAvailableSizes = true;
                    sizeHtml += `
                        <div style="display: flex; flex-direction: column; align-items: center; min-width: 54px;">
                            <input type="radio" id="size-${size}" name="size" value="${size}" 
                                   data-max-qty="${varietyData.sizes[size]}" onchange="updateQuantityLimit(${varietyData.sizes[size]})">
                            <label for="size-${size}" class="size-btn" 
                                   style="padding: 8px 12px; border: 2px solid #ddd; border-radius: 4px; cursor: pointer; transition: all 0.3s ease; text-align: center;">
                                ${size}
                            </label>
                            <span style="font-size:0.85em; color:#888; margin-top:3px;">${varietyData.sizes[size]} left</span>
                        </div>
                    `;
                }
            });
            
            if (!hasAvailableSizes) {
                sizeHtml = '<p style="color: #888; font-style: italic;">No sizes available for this variety</p>';
            }
            
            sizeSelector.innerHTML = sizeHtml;
            sizeSelector.style.opacity = '1';
            
            // Reset quantity when variety changes
            updateQuantityLimit(0);
            
            // Add click handlers for size buttons
            document.querySelectorAll('.size-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    // Remove active class from all size buttons
                    document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                });
            });
        }

        function updateQuantityLimit(maxQty) {
            const quantityInput = document.getElementById('quantity');
            const maxQuantityText = document.getElementById('maxQuantityText');
            
            if (maxQty > 0) {
                quantityInput.max = maxQty;
                quantityInput.value = Math.min(quantityInput.value, maxQty);
                maxQuantityText.textContent = `Max: ${maxQty}`;
                quantityInput.disabled = false;
            } else {
                quantityInput.max = 1;
                quantityInput.value = 1;
                maxQuantityText.textContent = 'Max: 1';
                quantityInput.disabled = true;
            }
        }

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') {
                changeImage(-1);
            } else if (e.key === 'ArrowRight') {
                changeImage(1);
            }
        });

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('overlay').classList.remove('active');
        }

        // Form validation
        document.getElementById('addToCartForm').addEventListener('submit', function(e) {
            let isValid = true;
            
            if (hasVarieties) {
                // Check if variety is selected
                const varietySelected = document.querySelector('input[name="variety"]:checked');
                if (!varietySelected) {
                    document.getElementById('varietyError').style.display = 'block';
                    isValid = false;
                } else {
                    document.getElementById('varietyError').style.display = 'none';
                }
            }
            
            // Check if size is selected
            const sizeSelected = document.querySelector('input[name="size"]:checked');
            if (!sizeSelected) {
                document.getElementById('sizeError').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('sizeError').style.display = 'none';
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });

        // Add variety button styling
        document.addEventListener('DOMContentLoaded', function() {
            // Style variety buttons
            document.querySelectorAll('.variety-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    // Remove active class from all variety buttons
                    document.querySelectorAll('.variety-btn').forEach(b => {
                        b.style.backgroundColor = '';
                        b.style.borderColor = '#ddd';
                        b.style.color = '';
                    });
                    // Add active style to clicked button
                    this.style.backgroundColor = '#b0302f';
                    this.style.borderColor = '#b0302f';
                    this.style.color = 'white';
                });
            });
            
            // Add hover effects
            document.querySelectorAll('.variety-btn, .size-btn').forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    if (!this.classList.contains('active') && this.style.backgroundColor !== 'rgb(176, 48, 47)') {
                        this.style.borderColor = '#b0302f';
                    }
                });
                btn.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('active') && this.style.backgroundColor !== 'rgb(176, 48, 47)') {
                        this.style.borderColor = '#ddd';
                    }
                });
            });
        });
    </script>
</body>
</html>