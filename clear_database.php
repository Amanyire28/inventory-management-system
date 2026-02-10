<?php
/**
 * Clear Database Script
 * 
 * WARNING: This will delete ALL data from the database including:
 * - All users (except admin which will be recreated)
 * - All products
 * - All transactions
 * - All sales
 * - All periods
 * - All audit logs
 * 
 * USE WITH EXTREME CAUTION!
 */

require_once __DIR__ . '/backend/core/Database.php';

// Confirmation check
echo "\n";
echo "========================================\n";
echo "  DATABASE CLEAR SCRIPT\n";
echo "========================================\n";
echo "\n";
echo "WARNING: This will delete ALL data!\n";
echo "This includes:\n";
echo "  - All users\n";
echo "  - All products\n";
echo "  - All transactions\n";
echo "  - All sales records\n";
echo "  - All periods\n";
echo "  - All audit logs\n";
echo "\n";
echo "A fresh admin user will be created:\n";
echo "  Username: admin\n";
echo "  Password: admin\n";
echo "\n";
echo "Type 'YES' to proceed: ";

$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if ($line !== 'YES') {
    echo "\nOperation cancelled.\n";
    exit(0);
}

echo "\nProceeding with database clear...\n\n";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Disable foreign key checks (though MyISAM doesn't enforce them)
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // Clear tables in order (child tables first)
    $tables = [
        'draft_sale_items',
        'draft_sales',
        'stock_adjustments',
        'audit_logs',
        'transactions',
        'period_product_opening_stock',
        'periods',
        'products',
        'users'
    ];
    
    foreach ($tables as $table) {
        echo "Clearing table: $table...";
        $result = $conn->query("TRUNCATE TABLE $table");
        if ($result) {
            echo " ✓ Done\n";
        } else {
            echo " ✗ Error: " . $conn->error . "\n";
        }
    }
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "\n";
    echo "Creating default admin user...\n";
    
    // Create admin user with password 'admin'
    $password = password_hash('admin', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $username = 'admin';
    $full_name = 'System Administrator';
    $role = 'admin';
    $status = 'active';
    $stmt->bind_param('sssss', $username, $password, $full_name, $role, $status);
    
    if ($stmt->execute()) {
        echo "✓ Admin user created successfully\n";
        echo "  Username: admin\n";
        echo "  Password: admin\n";
        echo "  IMPORTANT: Change this password immediately!\n";
    } else {
        echo "✗ Error creating admin user: " . $stmt->error . "\n";
    }
    
    echo "\n";
    echo "========================================\n";
    echo "  DATABASE CLEARED SUCCESSFULLY!\n";
    echo "========================================\n";
    echo "\n";
    echo "Summary:\n";
    echo "  - All data has been removed\n";
    echo "  - All tables are empty\n";
    echo "  - Auto-increment IDs have been reset\n";
    echo "  - Default admin user created\n";
    echo "\n";
    echo "You can now:\n";
    echo "  1. Login with admin/admin\n";
    echo "  2. Add new products\n";
    echo "  3. Create new users\n";
    echo "  4. Start fresh transactions\n";
    echo "\n";
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
