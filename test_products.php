<?php
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/backend/core/Database.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance();
    $products = $db->fetchAll("SELECT * FROM products");
    
    echo json_encode([
        'success' => true,
        'count' => count($products),
        'products' => $products
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
