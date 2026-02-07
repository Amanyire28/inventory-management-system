<?php
require_once __DIR__ . '/backend/core/Database.php';

$db = Database::getInstance();

// Check all sales transactions
$sales = $db->fetchAll("
    SELECT 
        t.id,
        t.transaction_date,
        t.type,
        t.status,
        t.created_by,
        u.full_name,
        u.role,
        t.total_amount
    FROM transactions t
    JOIN users u ON t.created_by = u.id
    WHERE t.type = 'SALE' 
        AND t.status = 'COMMITTED'
    ORDER BY t.transaction_date DESC 
    LIMIT 20
");

echo "Total sales found: " . count($sales) . "\n\n";
echo json_encode($sales, JSON_PRETTY_PRINT);
