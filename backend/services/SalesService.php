<?php
/**
 * Sales Service
 * 
 * Manages sales workflow:
 * 1. Create draft sale (no stock movement)
 * 2. Add/edit items to draft
 * 3. Commit draft → creates SALE transactions + updates stock atomically
 * 4. Reversals for corrections
 * 
 * Draft sales are temporary, committed sales are immutable via append-only transactions
 */

class SalesService {
    
    /**
     * Create draft sale session
     */
    public static function createDraftSale() {
        $user = Auth::getCurrentUser();
        if (!$user) {
            throw new Exception('User not authenticated');
        }
        
        $db = Database::getInstance();
        
        $session_id = uniqid('sale_', true);
        
        $stmt = $db->getConnection()->prepare(
            "INSERT INTO draft_sales (session_id, created_by) VALUES (?, ?)"
        );
        
        $stmt->bind_param('si', $session_id, $user['user_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create draft sale: " . $stmt->error);
        }
        
        $draft_id = $db->getConnection()->insert_id;
        
        AuditLog::log('CREATE_DRAFT_SALE', 'draft_sale', $draft_id, null, ['session_id' => $session_id]);
        
        return [
            'draft_id' => $draft_id,
            'session_id' => $session_id
        ];
    }
    
    /**
     * Add item to draft sale
     */
    public static function addDraftItem($draft_id, $product_id, $quantity, $unit_price) {
        $db = Database::getInstance();
        
        // Validate draft exists and belongs to current user
        $draft = self::validateDraftOwnership($draft_id);
        if (!$draft) {
            throw new Exception("Draft sale not found");
        }
        
        // Validate product exists
        $product = $db->fetch("SELECT id, current_stock FROM products WHERE id = ?", [$product_id]);
        if (!$product) {
            throw new Exception("Product not found");
        }
        
        // Calculate line total
        $line_total = $quantity * $unit_price;
        
        // Insert item
        $stmt = $db->getConnection()->prepare(
            "INSERT INTO draft_sale_items (draft_sale_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)"
        );
        
        $stmt->bind_param('iiddd', $draft_id, $product_id, $quantity, $unit_price, $line_total);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to add item to draft: " . $stmt->error);
        }
        
        $item_id = $db->getConnection()->insert_id;
        
        // Update draft total
        self::updateDraftTotal($draft_id);
        
        AuditLog::log('ADD_DRAFT_ITEM', 'draft_sale_items', $item_id, null, [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'unit_price' => $unit_price
        ]);
        
        return $item_id;
    }
    
    /**
     * Remove item from draft sale
     */
    public static function removeDraftItem($draft_id, $item_id) {
        $db = Database::getInstance();
        
        // Validate draft ownership
        self::validateDraftOwnership($draft_id);
        
        // Get item data for audit
        $item = $db->fetch("SELECT * FROM draft_sale_items WHERE id = ? AND draft_sale_id = ?", [$item_id, $draft_id]);
        if (!$item) {
            throw new Exception("Item not found in draft");
        }
        
        // Delete item
        $stmt = $db->getConnection()->prepare("DELETE FROM draft_sale_items WHERE id = ?");
        $stmt->bind_param('i', $item_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to remove item: " . $stmt->error);
        }
        
        // Update draft total
        self::updateDraftTotal($draft_id);
        
        AuditLog::log('REMOVE_DRAFT_ITEM', 'draft_sale_items', $item_id, $item, null);
        
        return true;
    }
    
    /**
     * Get draft sale with items
     */
    public static function getDraftSale($draft_id) {
        $db = Database::getInstance();
        
        $draft = $db->fetch("SELECT * FROM draft_sales WHERE id = ?", [$draft_id]);
        if (!$draft) {
            return null;
        }
        
        $items = $db->fetchAll(
            "SELECT dsi.*, p.name as product_name, p.current_stock
             FROM draft_sale_items dsi
             JOIN products p ON dsi.product_id = p.id
             WHERE dsi.draft_sale_id = ?",
            [$draft_id]
        );
        
        $draft['items'] = $items;
        $draft['item_count'] = count($items);
        
        return $draft;
    }
    
    /**
     * Commit draft sale to transactions (CRITICAL ATOMIC OPERATION)
     * 
     * This converts draft items into COMMITTED SALE transactions
     * Stock is updated ONLY when transactions are committed
     * Supports backdating via $options['transaction_date']
     */
    public static function commitDraftSale($draft_id, $period_id, $options = []) {
        $user = Auth::getCurrentUser();
        if (!$user) {
            throw new Exception('User not authenticated');
        }
        
        $db = Database::getInstance();
        
        // Validate draft and ownership
        $draft = self::validateDraftOwnership($draft_id);
        if (!$draft) {
            throw new Exception("Draft sale not found");
        }
        
        // Get items
        $items = $db->fetchAll(
            "SELECT dsi.*, p.current_stock FROM draft_sale_items dsi 
             JOIN products p ON dsi.product_id = p.id 
             WHERE dsi.draft_sale_id = ?",
            [$draft_id]
        );
        
        if (empty($items)) {
            throw new Exception("Cannot commit draft with no items");
        }
        
        // Start transaction for atomicity
        $db->beginTransaction();
        
        try {
            $transaction_ids = [];
            
            // Prepare transaction options (supports backdating)
            $transaction_options = [];
            if (isset($options['transaction_date']) && !empty($options['transaction_date'])) {
                $transaction_options['transaction_date'] = $options['transaction_date'];
            }
            
            // Create SALE transaction for each item
            foreach ($items as $item) {
                $transaction_id = TransactionService::createTransaction(
                    'SALE',
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $period_id,
                    $transaction_options
                );
                
                $transaction_ids[] = $transaction_id;
            }
            
            // Delete draft items and draft sale
            $stmt = $db->getConnection()->prepare("DELETE FROM draft_sale_items WHERE draft_sale_id = ?");
            $stmt->bind_param('i', $draft_id);
            $stmt->execute();
            
            $stmt = $db->getConnection()->prepare("DELETE FROM draft_sales WHERE id = ?");
            $stmt->bind_param('i', $draft_id);
            $stmt->execute();
            
            // Log audit
            AuditLog::log('COMMIT_SALE', 'draft_sale', $draft_id, $draft, [
                'transaction_ids' => $transaction_ids,
                'item_count' => count($items)
            ]);
            
            $db->commit();
            
            return [
                'success' => true,
                'transaction_ids' => $transaction_ids,
                'item_count' => count($items)
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Create reversal for a committed sale transaction
     * 
     * Used for corrections after sale is committed
     * Original transaction remains immutable, reversal creates new transaction
     */
    public static function reverseSale($transaction_id, $reason = '') {
        $db = Database::getInstance();
        
        // Validate original transaction
        $original = TransactionService::getTransaction($transaction_id);
        if (!$original) {
            throw new Exception("Transaction not found");
        }
        
        if ($original['type'] !== 'SALE') {
            throw new Exception("Can only reverse SALE transactions");
        }
        
        // Create REVERSAL transaction
        $reversal_id = TransactionService::createTransaction(
            'REVERSAL',
            $original['product_id'],
            $original['quantity'],
            $original['unit_price'],
            $original['period_id'],
            [
                'reference_transaction_id' => $transaction_id,
                'reversal_reason' => $reason
            ]
        );
        
        AuditLog::log('REVERSE_SALE', 'transactions', $transaction_id, $original, [
            'reversal_transaction_id' => $reversal_id,
            'reason' => $reason
        ]);
        
        return $reversal_id;
    }
    
    /**
     * Get sale transaction details
     * Returns transaction information for display/reversal confirmation
     */
    public static function getSaleTransactions($transaction_id) {
        $db = Database::getInstance();
        
        $transaction = $db->fetch(
            "SELECT t.*, p.name as product_name, u.full_name as created_by_name 
             FROM transactions t
             JOIN products p ON t.product_id = p.id
             JOIN users u ON t.created_by = u.id
             WHERE t.id = ? AND t.type = 'SALE'",
            [$transaction_id]
        );
        
        if (!$transaction) {
            throw new Exception("Sale transaction not found");
        }
        
        // Return as array for consistency with frontend expectation
        return [$transaction];
    }
    
    /**
     * Get sales history
     */
    public static function getSalesHistory($period_id = null, $limit = 100) {
        $db = Database::getInstance();
        $user = Auth::getCurrentUser();
        
        if (!$user) {
            return [];
        }
        
        // Build query to get individual sales transactions
        $sql = "SELECT 
                    t.id,
                    t.transaction_date,
                    t.created_by,
                    u.full_name as cashier_name,
                    p.name as product_name,
                    t.quantity,
                    t.total_amount,
                    t.status,
                    t.period_id
                FROM transactions t
                JOIN users u ON t.created_by = u.id
                JOIN products p ON t.product_id = p.id
                WHERE t.type = 'SALE'";
        
        $params = [];
        
        // If user is a cashier, only show their own sales
        // If user is an admin, show all sales
        if ($user['role'] === 'cashier') {
            $sql .= " AND t.created_by = ?";
            $params[] = $user['user_id'];
        }
        
        if ($period_id) {
            $sql .= " AND t.period_id = ?";
            $params[] = $period_id;
        }
        
        $sql .= " ORDER BY t.transaction_date DESC, t.id DESC
                  LIMIT ?";
        
        $params[] = $limit;
        
        return $db->fetchAll($sql, $params);
    }
    
    // ===== PRIVATE HELPER METHODS =====
    
    /**
     * Validate draft sale ownership (must belong to current user)
     */
    private static function validateDraftOwnership($draft_id) {
        $user = Auth::getCurrentUser();
        if (!$user) {
            return null;
        }
        
        $db = Database::getInstance();
        $draft = $db->fetch(
            "SELECT * FROM draft_sales WHERE id = ? AND created_by = ?",
            [$draft_id, $user['user_id']]
        );
        
        return $draft;
    }
    
    /**
     * Recalculate draft total
     */
    private static function updateDraftTotal($draft_id) {
        $db = Database::getInstance();
        
        $total = $db->fetch(
            "SELECT COALESCE(SUM(line_total), 0) as total FROM draft_sale_items WHERE draft_sale_id = ?",
            [$draft_id]
        );
        
        $stmt = $db->getConnection()->prepare("UPDATE draft_sales SET total_amount = ? WHERE id = ?");
        $stmt->bind_param('di', $total['total'], $draft_id);
        $stmt->execute();
    }
}
?>
