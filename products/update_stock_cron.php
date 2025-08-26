<?php
include "../includes/db_connect.php";
include "products.php"; // Ensure the function is included

$query = "SELECT product_id FROM products WHERE stock = 0";
$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    updateStockFromBatches($conn, $row['product_id']);
}