<?php
/**
 * Stock Taking Service
 * 
 * Manages physical inventory counts
 * Variance is recorded as ADJUSTMENT transactions
 * Stock is never overwritten - adjustments are recorded as transactions
 */

class StockTakingService {
    
    /**
     * Record physical count for a product
     * 
     * @param int $product_id
     * @param int $physical_count - Count from physical inventory
     * @param int $period_id
     * 
     * @return array Variance analysis
     */
    public static function recordPhysicalCount($product_id, $physical_count, $period_id) {
        
        Auth::requireRole(['admin']);
        
        $db = Database::getInstance();
        
        $user = Auth::getCurrentUser();
        
        $db->beginTransaction();
        
        try {
            // Get product
            $product = ProductService::getProduct($product_id);
            if (!$product) {
                throw new Exception("Product not found");
            }
            
            $system_stock = $product['current_stock'];
            $variance = $physical_count - $system_stock;
            
            // Record the physical count
            $stmt = $db->getConnection()->prepare(
                "INSERT INTO stock_adjustments (product_id, system_quantity, physical_quantity, variance, period_id, recorded_by) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            
            $stmt->bind_param('iiiii', $product_id, $system_stock, $physical_count, $variance, $period_id, $user['user_id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to record count: " . $stmt->error);
            }
            
            $adjustment_id = $db->getConnection()->insert_id;
            
            // If variance exists, create ADJUSTMENT transaction to correct stock
            if ($variance !== 0) {
                // Create adjustment transaction
                $unit_price = $product['cost_price'];
                
                TransactionService::createTransaction(
                    'ADJUSTMENT',
                    $product_id,
                    abs($variance), // Quantity is always positive
                    $unit_price,
                    $period_id
                );
            }
            
            AuditLog::log('RECORD_PHYSICAL_COUNT', 'stock_adjustments', $adjustment_id, null, [
                'product_id' => $product_id,
                'system_stock' => $system_stock,
                'physical_count' => $physical_count,
                'variance' => $variance
            ]);
            
            $db->commit();
            
            return [
                'adjustment_id' => $adjustment_id,
                'product_id' => $product_id,
                'product_name' => $product['name'],
                'system_stock' => $system_stock,
                'physical_count' => $physical_count,
                'variance' => $variance,
                'variance_status' => $variance === 0 ? 'MATCH' : ($variance > 0 ? 'SURPLUS' : 'SHORTAGE')
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get stock adjustment records
     */
    public static function getAdjustments($period_id = null) {
        $db = Database::getInstance();
        
        $sql = "SELECT sa.*, p.name as product_name, u.full_name as recorded_by_name 
                FROM stock_adjustments sa
                JOIN products p ON sa.product_id = p.id
                JOIN users u ON sa.recorded_by = u.id
                WHERE 1=1";
        
        $params = [];
        
        if ($period_id) {
            $sql .= " AND sa.period_id = ?";
            $params[] = $period_id;
        }
        
        $sql .= " ORDER BY sa.created_at DESC";
        
        return $db->fetchAll($sql, $params);
    }
    
    /**
     * Get variance analysis for period
     */
    public static function getPeriodVarianceReport($period_id) {
        $db = Database::getInstance();
        
        return $db->fetchAll(
            "SELECT 
                p.id,
                p.name,
                p.code,
                sa.system_quantity,
                sa.physical_quantity,
                sa.variance,
                CASE 
                    WHEN sa.variance > 0 THEN 'SURPLUS'
                    WHEN sa.variance < 0 THEN 'SHORTAGE'
                    ELSE 'MATCH'
                END as status,
                u.full_name as recorded_by,
                sa.created_at
            FROM stock_adjustments sa
            JOIN products p ON sa.product_id = p.id
            JOIN users u ON sa.recorded_by = u.id
            WHERE sa.period_id = ?
            ORDER BY ABS(sa.variance) DESC",
            [$period_id]
        );
    }
}
?>
