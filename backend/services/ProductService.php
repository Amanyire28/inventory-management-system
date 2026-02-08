<?php
/**
 * Product Service
 * 
 * Manages product reference data
 * Stock is CALCULATED from transactions, not edited directly
 */

class ProductService {
    
    /**
     * Get all products with current stock
     */
    public static function getAllProducts($statusFilter = 'active') {
        $db = Database::getInstance();
        
        if ($statusFilter === 'all') {
            return $db->fetchAll(
                "SELECT * FROM products ORDER BY name"
            );
        } else {
            return $db->fetchAll(
                "SELECT * FROM products WHERE status = ? ORDER BY name",
                [$statusFilter]
            );
        }
    }
    
    /**
     * Get single product
     */
    public static function getProduct($id) {
        $db = Database::getInstance();
        
        return $db->fetch("SELECT * FROM products WHERE id = ?", [$id]);
    }
    
    /**
     * Create new product
     * 
     * opening_stock is IMMUTABLE after creation
     * current_stock is calculated from transactions
     */
    public static function createProduct($name, $selling_price, $cost_price, $opening_stock = 0, $reorder_level = 10) {
        
        Auth::requireRole(['admin']);
        
        $db = Database::getInstance();
        
        $db->beginTransaction();
        
        try {
            // Validate inputs
            if (empty($name)) {
                throw new Exception("Product name is required");
            }
            
            if ($selling_price <= 0) {
                throw new Exception("Selling price must be greater than 0");
            }
            
            if ($cost_price < 0) {
                throw new Exception("Cost price must be non-negative");
            }
            
            if ($opening_stock < 0) {
                throw new Exception("Opening stock must be non-negative");
            }
            
            // Insert product
            $stmt = $db->getConnection()->prepare(
                "INSERT INTO products (name, selling_price, cost_price, opening_stock, current_stock, reorder_level, status) 
                 VALUES (?, ?, ?, ?, ?, ?, 'active')"
            );
            
            $stmt->bind_param('sdddii', $name, $selling_price, $cost_price, $opening_stock, $opening_stock, $reorder_level);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create product: " . $stmt->error);
            }
            
            $product_id = $db->getConnection()->insert_id;
            
            AuditLog::log('CREATE_PRODUCT', 'products', $product_id, null, [
                'name' => $name,
                'opening_stock' => $opening_stock
            ]);
            
            $db->commit();
            
            return $product_id;
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Update product (name, prices, reorder level only)
     * 
     * opening_stock is IMMUTABLE
     * current_stock is CALCULATED from transactions
     */
    public static function updateProduct($id, $data) {
        
        Auth::requireRole(['admin']);
        
        $db = Database::getInstance();
        
        // Get current product for audit
        $product = self::getProduct($id);
        if (!$product) {
            throw new Exception("Product not found");
        }
        
        $db->beginTransaction();
        
        try {
            $updates = [];
            $params = [];
            
            // Only allow updating these fields
            $allowed_fields = ['name', 'selling_price', 'cost_price', 'reorder_level', 'status'];
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            // Prevent attempts to change opening_stock or current_stock
            if (isset($data['opening_stock'])) {
                throw new Exception("opening_stock is immutable and cannot be changed");
            }
            
            if (isset($data['current_stock'])) {
                throw new Exception("current_stock is calculated from transactions and cannot be directly edited");
            }
            
            if (empty($updates)) {
                throw new Exception("No valid fields to update");
            }
            
            $params[] = $id;
            $sql = "UPDATE products SET " . implode(', ', $updates) . " WHERE id = ?";
            
            $stmt = $db->getConnection()->prepare($sql);
            
            // Build type string
            $types = '';
            foreach ($data as $key => $value) {
                if (in_array($key, $allowed_fields)) {
                    if ($key === 'name' || $key === 'status') $types .= 's';
                    else $types .= 'd';
                }
            }
            $types .= 'i'; // for id
            
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update product: " . $stmt->error);
            }
            
            AuditLog::log('UPDATE_PRODUCT', 'products', $id, $product, $data);
            
            $db->commit();
            
            return true;
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Deactivate product
     */
    public static function deactivateProduct($id) {
        
        Auth::requireRole(['admin']);
        
        $db = Database::getInstance();
        
        $product = self::getProduct($id);
        if (!$product) {
            throw new Exception("Product not found");
        }
        
        $stmt = $db->getConnection()->prepare("UPDATE products SET status = 'inactive' WHERE id = ?");
        $stmt->bind_param('i', $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to deactivate product: " . $stmt->error);
        }
        
        AuditLog::log('DEACTIVATE_PRODUCT', 'products', $id, $product, ['status' => 'inactive']);
        
        return true;
    }
    
    /**
     * Get product stock history (all transactions affecting this product)
     */
    public static function getProductStockHistory($product_id) {
        $filters = ['product_id' => $product_id];
        
        return TransactionService::getTransactionHistory($filters);
    }
}
?>
