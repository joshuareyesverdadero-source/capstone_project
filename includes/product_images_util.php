<?php
// Utility functions for product images

/**
 * Get all images for a product
 */
function getProductImages($conn, $product_id) {
    $stmt = $conn->prepare("SELECT id, image_url, is_primary, sort_order FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $images = [];
    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }
    $stmt->close();
    
    return $images;
}

/**
 * Get primary image for a product
 */
function getPrimaryProductImage($conn, $product_id) {
    $stmt = $conn->prepare("SELECT image_url FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['image_url'];
    }
    $stmt->close();
    
    // Fallback to first image if no primary is set
    $stmt = $conn->prepare("SELECT image_url FROM product_images WHERE product_id = ? ORDER BY sort_order ASC LIMIT 1");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['image_url'];
    }
    $stmt->close();
    
    return null;
}

/**
 * Add a new image to a product
 */
function addProductImage($conn, $product_id, $image_url, $is_primary = false, $sort_order = 0) {
    $stmt = $conn->prepare("INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isii", $product_id, $image_url, $is_primary, $sort_order);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

/**
 * Update primary image for a product
 */
function setPrimaryProductImage($conn, $product_id, $image_id) {
    // First, remove primary flag from all images for this product
    $stmt = $conn->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stmt->close();
    
    // Then set the specified image as primary
    $stmt = $conn->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?");
    $stmt->bind_param("ii", $image_id, $product_id);
    $success = $stmt->execute();
    $stmt->close();
    
    // Update the main products table for backward compatibility
    if ($success) {
        $stmt = $conn->prepare("SELECT image_url FROM product_images WHERE id = ?");
        $stmt->bind_param("i", $image_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            
            $update_stmt = $conn->prepare("UPDATE products SET image_url = ? WHERE product_id = ?");
            $update_stmt->bind_param("si", $row['image_url'], $product_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
    }
    
    return $success;
}

/**
 * Delete a product image
 */
function deleteProductImage($conn, $image_id, $product_id) {
    // Get image info first
    $stmt = $conn->prepare("SELECT image_url, is_primary FROM product_images WHERE id = ? AND product_id = ?");
    $stmt->bind_param("ii", $image_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $image_url = $row['image_url'];
        $was_primary = $row['is_primary'];
        $stmt->close();
        
        // Delete from database
        $delete_stmt = $conn->prepare("DELETE FROM product_images WHERE id = ? AND product_id = ?");
        $delete_stmt->bind_param("ii", $image_id, $product_id);
        $success = $delete_stmt->execute();
        $delete_stmt->close();
        
        if ($success) {
            // Delete physical file
            $file_path = "../" . $image_url;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // If we deleted the primary image, set a new primary
            if ($was_primary) {
                $new_primary_stmt = $conn->prepare("SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order ASC LIMIT 1");
                $new_primary_stmt->bind_param("i", $product_id);
                $new_primary_stmt->execute();
                $new_primary_result = $new_primary_stmt->get_result();
                
                if ($new_primary_row = $new_primary_result->fetch_assoc()) {
                    setPrimaryProductImage($conn, $product_id, $new_primary_row['id']);
                }
                $new_primary_stmt->close();
            }
        }
        
        return $success;
    }
    
    $stmt->close();
    return false;
}

/**
 * Migrate existing product images from products table to product_images table
 */
function migrateExistingProductImages($conn) {
    $query = "SELECT product_id, image_url FROM products WHERE image_url IS NOT NULL AND image_url != ''";
    $result = $conn->query($query);
    
    $migrated_count = 0;
    while ($row = $result->fetch_assoc()) {
        // Check if this image already exists in product_images table
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM product_images WHERE product_id = ? AND image_url = ?");
        $check_stmt->bind_param("is", $row['product_id'], $row['image_url']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_row = $check_result->fetch_assoc();
        $check_stmt->close();
        
        if ($check_row['count'] == 0) {
            // Add to product_images table
            if (addProductImage($conn, $row['product_id'], $row['image_url'], true, 0)) {
                $migrated_count++;
            }
        }
    }
    
    return $migrated_count;
}
?>
