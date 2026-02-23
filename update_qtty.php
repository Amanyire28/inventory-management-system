<?php
require_once 'backend/config.php'; // Adjust path if needed

$period_id = 1; // Set your period ID here

$conn = Database::getInstance()->getConnection();
$products = $conn->query("SELECT id, opening_stock FROM products WHERE status = 'active'");

$stmt = $conn->prepare("UPDATE period_product_opening_stock SET opening_stock = ? WHERE product_id = ? AND period_id = ?");
while ($row = $products->fetch_assoc()) {
    $stmt->bind_param('dii', $row['opening_stock'], $row['id'], $period_id);
    $stmt->execute();
}

echo "Opening stock updated for period $period_id.";
?>