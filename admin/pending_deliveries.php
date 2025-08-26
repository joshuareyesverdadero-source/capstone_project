<?php
session_start();
include "../includes/db_connect.php";

// Only allow admin/superadmin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../auth/login.php");
    exit();
}

$res = $conn->query("
    SELECT d.*, p.name AS product_name, u.username AS supplier_name
    FROM deliveries d
    JOIN products p ON d.product_id = p.product_id
    JOIN users u ON d.user_id = u.user_id
    WHERE d.status = 'Pending'
    ORDER BY d.delivery_date DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Pending Deliveries</title>
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
    <script>
        function openBatchPriceModal(deliveryId, costPrice) {
            document.getElementById('batchPriceModal').style.display = 'flex';
            document.getElementById('modal_delivery_id').value = deliveryId;
            document.getElementById('modal_cost_price').value = costPrice;
            document.getElementById('modal_selling_price').value = costPrice;
        }
        function closeBatchPriceModal() {
            document.getElementById('batchPriceModal').style.display = 'none';
        }
    </script>
</head>
<body>
<div class="admin-container">
    <header class="admin-header">
        <h1>Pending Deliveries</h1>
        <a href="dashboard.php" class="btn">Back to Dashboard</a>
    </header>
    <main class="admin-main">
        <table>
            <tr>
                <th>Supplier</th>
                <th>Product</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
            <?php while ($row = $res->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td><?= $row['quantity'] ?></td>
                    <td>₱<?= number_format($row['unit_price'],2) ?></td>
                    <td><?= $row['delivery_date'] ?></td>
                    <td>
                        <button type="button" class="btn" style="background:#28a745;" onclick="openBatchPriceModal(<?= $row['delivery_id'] ?>, <?= $row['unit_price'] ?>)">Add as Batch</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </main>
</div>

<!-- Batch Price Modal -->
<div id="batchPriceModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.3);z-index:10000;align-items:center;justify-content:center;">
    <div style="background:#fff;padding:24px 32px;border-radius:8px;max-width:400px;width:95%;box-shadow:0 2px 8px rgba(0,0,0,0.12);position:relative;">
        <h3 style="margin-top:0;">Set Selling Price</h3>
        <form method="POST" action="process_delivery.php">
            <input type="hidden" name="delivery_id" id="modal_delivery_id">
            <label>Cost Price (from delivery):</label>
            <input type="number" id="modal_cost_price" readonly style="width:100%;margin-bottom:10px;">
            <label>Selling Price:</label>
            <input type="number" name="selling_price" id="modal_selling_price" min="1" step="0.01" required style="width:100%;margin-bottom:10px;">
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="closeBatchPriceModal()" class="btn" style="background:#888;">Cancel</button>
                <button type="submit" class="btn" style="background:#28a745;">Add Batch</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>