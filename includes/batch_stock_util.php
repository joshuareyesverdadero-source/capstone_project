<?php
function deductProductStock($conn, $product_id, $order_qty) {
    // Get all active batches, oldest first
    $batches = $conn->query("SELECT * FROM product_batches WHERE product_id=$product_id AND status='active' ORDER BY arrival_date ASC, batch_id ASC");
    while ($batch = $batches->fetch_assoc()) {
        if ($order_qty <= 0) break;
        $deduct = min($batch['quantity'], $order_qty);
        // Deduct from batch
        $conn->query("UPDATE product_batches SET quantity = quantity - $deduct WHERE batch_id = {$batch['batch_id']}");
        // If batch is used up, mark as used_up
        $conn->query("UPDATE product_batches SET status='used_up' WHERE batch_id = {$batch['batch_id']} AND quantity = 0");
        $order_qty -= $deduct;
    }
    // Optionally, update products.stock as a sum of all active batches
    $sum = $conn->query("SELECT SUM(quantity) as total FROM product_batches WHERE product_id=$product_id AND status='active'")->fetch_assoc()['total'] ?? 0;
    $conn->query("UPDATE products SET stock=$sum WHERE product_id=$product_id");
}