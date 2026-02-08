<?php
/**
 * Period Service
 * 
 * Manages financial periods (OPEN/CLOSED)
 * Closed periods are read-only - no edits or deletions allowed
 * Only additive corrections (reversals) are allowed in closed periods
 */

class PeriodService {
    
    /**
     * Get all periods
     */
    public static function getAllPeriods() {
        $db = Database::getInstance();
        
        return $db->fetchAll(
            "SELECT * FROM periods ORDER BY start_date DESC"
        );
    }
    
    /**
     * Get single period
     */
    public static function getPeriod($id) {
        $db = Database::getInstance();
        
        return $db->fetch("SELECT * FROM periods WHERE id = ?", [$id]);
    }
    
    /**
     * Get current open period
     */
    public static function getCurrentPeriod() {
        $db = Database::getInstance();
        
        return $db->fetch("SELECT * FROM periods WHERE status = 'OPEN' ORDER BY start_date DESC LIMIT 1");
    }
    
    /**
     * Create new period
     * Auto-populates opening stocks from previous period's closing stocks
     * Only one OPEN period allowed at a time
     */
    public static function createPeriod($period_name, $start_date, $end_date) {
        
        Auth::requireRole(['admin']);
        
        $db = Database::getInstance();
        $db->beginTransaction();
        
        try {
            if (empty($period_name)) {
                throw new Exception("Period name is required");
            }
            
            // Check if there's already an OPEN period
            $openPeriod = $db->fetch(
                "SELECT id, period_name FROM periods WHERE status = 'OPEN'"
            );
            
            if ($openPeriod) {
                throw new Exception("Cannot create new period. Period '{$openPeriod['period_name']}' is still OPEN. Please close it first.");
            }
            
            // Create the period
            $stmt = $db->getConnection()->prepare(
                "INSERT INTO periods (period_name, status, start_date, end_date) 
                 VALUES (?, 'OPEN', ?, ?)"
            );
            
            $stmt->bind_param('sss', $period_name, $start_date, $end_date);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create period: " . $stmt->error);
            }
            
            $period_id = $db->getConnection()->insert_id;
            
            // Get all active products
            $products = $db->fetchAll("SELECT id FROM products WHERE status = 'active'");
            
            // Get previous period
            $previousPeriod = $db->fetch(
                "SELECT id FROM periods WHERE status = 'CLOSED' AND id != ? ORDER BY id DESC LIMIT 1",
                [$period_id]
            );
            
            if ($previousPeriod) {
                // Copy closing stocks from previous period as opening for new period
                $previousPeriodId = $previousPeriod['id'];
                
                foreach ($products as $product) {
                    $productId = $product['id'];
                    
                    // Get closing stock from previous period
                    $prevClosing = $db->fetch(
                        "SELECT closing_stock FROM period_product_opening_stock 
                         WHERE period_id = ? AND product_id = ?",
                        [$previousPeriodId, $productId]
                    );
                    
                    if ($prevClosing) {
                        $openingStock = $prevClosing['closing_stock'];
                    } else {
                        // Fallback: calculate from product's immutable opening_stock
                        $prod = ProductService::getProduct($productId);
                        $openingStock = $prod['opening_stock'] ?? 0;
                    }
                    
                    // Insert opening stock for new period
                    $insertStmt = $db->getConnection()->prepare(
                        "INSERT INTO period_product_opening_stock (period_id, product_id, opening_stock, closing_stock) 
                         VALUES (?, ?, ?, ?)"
                    );
                    
                    $insertStmt->bind_param('iiii', $period_id, $productId, $openingStock, $openingStock);
                    
                    if (!$insertStmt->execute()) {
                        throw new Exception("Failed to set opening stock for product: " . $insertStmt->error);
                    }
                }
            } else {
                // No previous period - use immutable opening_stock for products
                foreach ($products as $product) {
                    $productId = $product['id'];
                    $prod = ProductService::getProduct($productId);
                    $openingStock = $prod['opening_stock'] ?? 0;
                    
                    $insertStmt = $db->getConnection()->prepare(
                        "INSERT INTO period_product_opening_stock (period_id, product_id, opening_stock, closing_stock) 
                         VALUES (?, ?, ?, ?)"
                    );
                    
                    $insertStmt->bind_param('iiii', $period_id, $productId, $openingStock, $openingStock);
                    
                    if (!$insertStmt->execute()) {
                        throw new Exception("Failed to set opening stock for product: " . $insertStmt->error);
                    }
                }
            }
            
            $db->commit();
            
            AuditLog::log('CREATE_PERIOD', 'periods', $period_id, null, [
                'period_name' => $period_name,
                'start_date' => $start_date,
                'end_date' => $end_date
            ]);
            
            return $period_id;
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Close period (CRITICAL: Admin only, irreversible)
     * Calculates and stores closing stocks before locking period
     * 
     * Once closed:
     * - No new transactions can be recorded
     * - No edits allowed (enforced at transaction level)
     * - Only reversals for corrections are allowed
     */
    public static function closePeriod($id) {
        
        Auth::requireRole(['admin']);
        
        $db = Database::getInstance();
        $db->beginTransaction();
        
        try {
            // Validate period exists and is open
            $period = self::getPeriod($id);
            if (!$period) {
                throw new Exception("Period not found");
            }
            
            if ($period['status'] === 'CLOSED') {
                throw new Exception("Period is already closed");
            }
            
            $user = Auth::getCurrentUser();
            $user_id = $user['user_id'];
            $closed_at = date('Y-m-d H:i:s');
            
            // Calculate closing stock for each product in this period
            $products = $db->fetchAll("SELECT id FROM products WHERE status = 'active'");
            
            // Use period end_date if available, otherwise use current date
            $asOfDate = $period['end_date'] ?: date('Y-m-d H:i:s');
            
            foreach ($products as $product) {
                $productId = $product['id'];
                
                // Get opening stock for this period
                $periodStockRecord = $db->fetch(
                    "SELECT opening_stock FROM period_product_opening_stock 
                     WHERE period_id = ? AND product_id = ?",
                    [$id, $productId]
                );
                
                if ($periodStockRecord) {
                    $openingStock = $periodStockRecord['opening_stock'];
                    
                    // Calculate closing stock = opening + purchases - sales + adjustments
                    $transactionSum = $db->fetch(
                        "SELECT COALESCE(SUM(
                            CASE 
                                WHEN type = 'PURCHASE' THEN quantity
                                WHEN type = 'SALE' THEN quantity
                                WHEN type = 'ADJUSTMENT' THEN quantity
                                WHEN type = 'REVERSAL' THEN quantity
                                ELSE 0
                            END
                        ), 0) as total_change
                        FROM transactions
                        WHERE product_id = ? 
                          AND period_id = ?
                          AND status = 'COMMITTED'",
                        [$productId, $id]
                    );
                    
                    $closingStockValue = $openingStock + intval($transactionSum['total_change'] ?? 0);
                    
                    // Update closing_stock in period_product_opening_stock
                    $updateStmt = $db->getConnection()->prepare(
                        "UPDATE period_product_opening_stock 
                         SET closing_stock = ? 
                         WHERE period_id = ? AND product_id = ?"
                    );
                    
                    $updateStmt->bind_param('iii', $closingStockValue, $id, $productId);
                    
                    if (!$updateStmt->execute()) {
                        throw new Exception("Failed to update closing stock: " . $updateStmt->error);
                    }
                }
            }
            
            // Update period status to CLOSED
            // Also set end_date if not already set
            $endDate = ($period['end_date'] && $period['end_date'] !== '0000-00-00') 
                ? $period['end_date'] 
                : date('Y-m-d');
            
            $stmt = $db->getConnection()->prepare(
                "UPDATE periods SET status = 'CLOSED', end_date = ?, closed_by = ?, closed_at = ? WHERE id = ?"
            );
            
            $stmt->bind_param('sisi', $endDate, $user_id, $closed_at, $id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to close period: " . $stmt->error);
            }
            
            $db->commit();
            
            AuditLog::log('CLOSE_PERIOD', 'periods', $id, $period, [
                'status' => 'CLOSED',
                'closed_by' => $user_id,
                'closed_at' => $closed_at
            ]);
            
            return true;
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get opening stock for a product in a specific period
     * Returns period-specific opening stock if available, otherwise product's immutable opening_stock
     */
    public static function getPeriodOpeningStock($product_id, $period_id) {
        $db = Database::getInstance();
        
        $periodStockRecord = $db->fetch(
            "SELECT opening_stock FROM period_product_opening_stock 
             WHERE period_id = ? AND product_id = ?",
            [$period_id, $product_id]
        );
        
        if ($periodStockRecord) {
            return $periodStockRecord['opening_stock'];
        }
        
        // Fallback to product's immutable opening_stock
        $product = $db->fetch("SELECT opening_stock FROM products WHERE id = ?", [$product_id]);
        return $product ? $product['opening_stock'] : 0;
    }
    
    /**
     * Get closing stock for a product in a specific period
     */
    public static function getPeriodClosingStock($product_id, $period_id) {
        $db = Database::getInstance();
        
        $periodStockRecord = $db->fetch(
            "SELECT closing_stock FROM period_product_opening_stock 
             WHERE period_id = ? AND product_id = ?",
            [$period_id, $product_id]
        );
        
        return $periodStockRecord ? $periodStockRecord['closing_stock'] : 0;
    }
    
    /**
     * Get comprehensive period summary with product-level opening/closing stocks
     * Used for period preview before closing or after closing
     */
    public static function getPeriodSummary($period_id) {
        $db = Database::getInstance();
        
        // Get period info
        $period = $db->fetch(
            "SELECT * FROM periods WHERE id = ?",
            [$period_id]
        );
        
        if (!$period) {
            throw new Exception("Period not found");
        }
        
        // Get overall transaction count
        $transactionSummary = $db->fetch(
            "SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN type = 'SALE' THEN quantity ELSE 0 END) as total_sales_qty,
                SUM(CASE WHEN type = 'PURCHASE' THEN quantity ELSE 0 END) as total_purchases_qty,
                SUM(CASE WHEN type = 'SALE' THEN total_amount ELSE 0 END) as total_sales_amount,
                SUM(CASE WHEN type = 'PURCHASE' THEN total_amount ELSE 0 END) as total_purchases_amount,
                SUM(CASE WHEN type = 'ADJUSTMENT' THEN quantity ELSE 0 END) as total_adjustments_qty,
                COUNT(DISTINCT CASE WHEN type = 'REVERSAL' THEN 1 END) as reversal_count
            FROM transactions 
            WHERE period_id = ? AND status = 'COMMITTED'",
            [$period_id]
        );
        
        // Get product-level opening/closing stocks
        $products = $db->fetchAll(
            "SELECT 
                ppos.period_id,
                ppos.product_id,
                ppos.opening_stock,
                ppos.closing_stock,
                p.name as product_name,
                p.cost_price,
                p.selling_price,
                COALESCE(SUM(CASE WHEN t.type = 'PURCHASE' THEN t.quantity ELSE 0 END), 0) as purchases,
                COALESCE(SUM(CASE WHEN t.type = 'SALE' THEN t.quantity ELSE 0 END), 0) as sales,
                COALESCE(SUM(CASE WHEN t.type = 'ADJUSTMENT' THEN t.quantity ELSE 0 END), 0) as adjustments
            FROM period_product_opening_stock ppos
            JOIN products p ON ppos.product_id = p.id
            LEFT JOIN transactions t ON ppos.product_id = t.product_id 
                AND ppos.period_id = t.period_id 
                AND t.status = 'COMMITTED'
            WHERE ppos.period_id = ?
            GROUP BY ppos.product_id, p.name, p.cost_price, p.selling_price
            ORDER BY p.name",
            [$period_id]
        );
        
        return [
            'period' => $period,
            'transaction_summary' => $transactionSummary,
            'products' => $products
        ];
    }
}
?>
