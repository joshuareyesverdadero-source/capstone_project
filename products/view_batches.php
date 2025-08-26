<?php
include "../includes/db_connect.php";
if (!isset($_GET['product_id']) || !is_numeric($_GET['product_id'])) {
    echo "<div style='color:#c00;text-align:center;'>No product selected.</div>";
    exit;
}
$product_id = intval($_GET['product_id']);
$res = $conn->query("SELECT * FROM product_batches WHERE product_id=$product_id ORDER BY arrival_date DESC");

echo "<table style='width:100%;border-collapse:collapse;'>";
echo "<tr style='background:#f7d3db;'>
        <th>Batch</th>
        <th>Qty</th>
        <th>Cost</th>
        <th>Selling</th>
        <th>Date</th>
        <th>Status</th>
      </tr>";
if ($res->num_rows === 0) {
    echo "<tr><td colspan='6' style='text-align:center;color:#888;'>No batches found.</td></tr>";
}
while ($row = $res->fetch_assoc()) {
    echo "<tr>
        <td>{$row['batch_id']}</td>
        <td>{$row['quantity']}</td>
        <td>₱" . number_format($row['cost_price'],2) . "</td>
        <td>₱" . number_format($row['selling_price'],2) . "</td>
        <td>{$row['arrival_date']}</td>
        <td>{$row['status']}</td>
    </tr>";
}
echo "</table>";
?>