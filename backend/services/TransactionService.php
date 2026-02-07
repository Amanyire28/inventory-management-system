<?php
/**
 * Transaction Service
 * 
 * Manages append-only transaction log
 * Single source of truth for stock calculations
 * 
 * Transaction Types:
 * - PURCHASE: Add to stock
 * - SALE: Subtract from stock
 * - ADJUSTMENT: Variance correction (positive or negative)
 * - REVERSAL: Negate previous transaction
 * - VOID: Mark transaction as invalid
 */

class TransactionService {
    
    /**
     * Create a transaction (atomic, append-only)
     * 
     * @param string $type - PURCHASE, SALE, ADJUSTMENT, REVERSAL, VOID
     * @param int $product_id
     * @param int $quantity
     * @param float $unit_price
     * @param int $period_id
     * @param array $options - reference_transaction_id, reversal_reason
     * 
     * @return int Transaction ID
     * @throws Exception
     */
    public static function createTransaction($type, $product_id, $quantity, $unit_price, $period_id, $options = []) {
        
        // Validate transaction type
        $valid_types = ['PURCHASE', 'SALE', 'ADJUSTMENT', 'REVERSAL', 'VOID'];
        if (!in_array($type, $valid_types)) {
            throw new Exception("Invalid transaction type: $type");
        }
        
        $user = Auth::getCurrentUser();
        if (!$user) {
            throw new Exception('User not authenticated');
        }
        
        $db = Database::getInstance();
        
        // Start transaction for atomicity
        $db->beginTransaction();
        
        try {
            // Validate period is open (unless it's a reversal in a closed period)
            if ($type !== 'REVERSAL') {
                $period = $db->fetch("SELECT status FROM periods WHERE id = ?", [$period_id]);
                if (!$period) {
                    throw new Exception("Period not found");
                }
                
                if ($period['status'] === 'CLOSED') {
                    throw new Exception("Cannot create transactions in a closed period");
                }
            }
            
            // Validate product exists
            $product = $db->fetch("SELECT id, current_stock FROM products WHERE id = ?", [$product_id]);
            if (!$product) {
                throw new Exception("Product not found");
            }
            
            // Validate stock availability for SALE
            if ($type === 'SALE' && $product['current_stock'] < $quantity) {
                throw new Exception("Insufficient stock. Available: {$product['current_stock']}, Requested: $quantity");
            }
            
            // Calculate total amount
            $total_amount = $quantity * $unit_price;
            
            // Allow custom transaction date for backdating (defaults to now)
            $transaction_date = $options['transaction_date'] ?? date('Y-m-d H:i:s');
            
            // Validate backdated transaction date format
            if (isset($options['transaction_date'])) {
                $parsed_date = date_parse($transaction_date);
                if ($parsed_date['error_count'] > 0) {
                    throw new Exception("Invalid transaction_date format. Use: YYYY-MM-DD HH:MM:SS");
                }
            }
            
            // Prepare transaction data
            $transaction_data = [
                'type' => $type,
                'product_id' => $product_id,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'total_amount' => $total_amount,
                'transaction_date' => $transaction_date,
                'period_id' => $period_id,
                'created_by' => $user['user_id'],
                'status' => 'COMMITTED'
            ];
            
            // Add optional fields
            if (isset($options['reference_transaction_id'])) {
                $transaction_data['reference_transaction_id'] = $options['reference_transaction_id'];
            }
            
            if (isset($options['reversal_reason'])) {
                $transaction_data['reversal_reason'] = $options['reversal_reason'];
            }
            
            // Insert transaction (APPEND-ONLY)
            $conn = $db->getConnection();
            $stmt = $conn->prepare(
                "INSERT INTO transactions 
                 (type, product_id, quantity, unit_price, total_amount, transaction_date, period_id, created_by, status, reference_transaction_id, reversal_reason)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            
            $reference_id = $options['reference_transaction_id'] ?? null;
            $reversal_reason = $options['reversal_reason'] ?? null;
            
            $stmt->bind_param(
                'sidddsiisis',
                $transaction_data['type'],
                $product_id,
                $quantity,
                $unit_price,
                $total_amount,
                $transaction_data['transaction_date'],
                $period_id,
                $user['user_id'],
                $transaction_data['status'],
                $reference_id,
                $reversal_reason
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create transaction: " . $stmt->error);
            }
            
            $transaction_id = $conn->insert_id;
            
            // Recalculate product stock from all transactions (derived value)
            self::recalculateProductStock($product_id);
            
            // Log audit entry
            AuditLog::log('CREATE_TRANSACTION', 'transaction', $transaction_id, null, $transaction_data);
            
            $db->commit();
            
            return $transaction_id;
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Recalculate product stock from all committed transactions
     * This is the SINGLE SOURCE OF TRUTH for stock values
     * 
     * Formula: opening_stock + SUM(transactions effects)
     */
    public static function recalculateProductStock($product_id) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Get opening stock (immutable)
        $opening = $db->fetch("SELECT opening_stock FROM products WHERE id = ?", [$product_id]);
        if (!$opening) {
            throw new Exception("Product not found");
        }
        
        // Calculate total stock effect from all committed transactions
        $result = $db->fetch(
            "SELECT COALESCE(SUM(
                CASE 
                    WHEN type = 'PURCHASE' THEN quantity
                    WHEN type = 'SALE' THEN -quantity
                    WHEN type = 'ADJUSTMENT' THEN quantity
                    WHEN type = 'REVERSAL' THEN quantity
                    WHEN type = 'VOID' THEN 0
                    ELSE 0
                END
            ), 0) as stock_change
            FROM transactions 
            WHERE product_id = ? AND status = 'COMMITTED'",
            [$product_id]
        );
        
        $stock_change = (int)$result['stock_change'];
        $new_stock = $opening['opening_stock'] + $stock_change;
        
        // Atomically update current_stock (this is the ONLY place stock is updated)
        $stmt = $conn->prepare("UPDATE products SET current_stock = ? WHERE id = ?");
        $stmt->bind_param('ii', $new_stock, $product_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update product stock: " . $stmt->error);
        }
        
        return $new_stock;
    }
    
    /**
     * Get transaction history
     */
    public static function getTransactionHistory($filters = []) {
        $db = Database::getInstance();
        
        $sql = "SELECT t.*, p.name as product_name, u.full_name as created_by_name 
                FROM transactions t
                JOIN products p ON t.product_id = p.id
                JOIN users u ON t.created_by = u.id
                WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['product_id'])) {
            $sql .= " AND t.product_id = ?";
            $params[] = $filters['product_id'];
        }
        
        if (isset($filters['period_id'])) {
            $sql .= " AND t.period_id = ?";
            $params[] = $filters['period_id'];
        }
        
        if (isset($filters['type'])) {
            $sql .= " AND t.type = ?";
            $params[] = $filters['type'];
        }
        
        if (isset($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY t.created_at DESC LIMIT 1000";
        
        return $db->fetchAll($sql, $params);
    }
    
    /**
     * Get single transaction with full details
     */
    public static function getTransaction($id) {
        $db = Database::getInstance();
        
        return $db->fetch(
            "SELECT t.*, p.name as product_name, u.full_name as created_by_name 
             FROM transactions t
             JOIN products p ON t.product_id = p.id
             JOIN users u ON t.created_by = u.id
             WHERE t.id = ?",
            [$id]
        );
    }
    
    /**
     * Find appropriate period for a transaction date
     * Used for backdated transactions to auto-assign correct period
     */
    public static function findPeriodForDate($transaction_date) {
        $db = Database::getInstance();
        
        // Extract just the date part
        $date_only = date('Y-m-d', strtotime($transaction_date));
        
        // Find period where transaction date falls within range
        $period = $db->fetch(
            "SELECT id, period_name, status, start_date, end_date 
             FROM periods 
             WHERE ? BETWEEN start_date AND end_date
             ORDER BY id DESC 
             LIMIT 1",
            [$date_only]
        );
        
        if (!$period) {
            // If no period found, try to find the most recent OPEN period
            $period = $db->fetch(
                "SELECT id, period_name, status, start_date, end_date 
                 FROM periods 
                 WHERE status = 'OPEN'
                 ORDER BY id DESC 
                 LIMIT 1"
            );
        }
        
        return $period;
    }
}
?>
