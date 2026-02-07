<?php
/**
 * Purchase Service
 * 
 * Manages purchase transactions
 * Purchases directly create PURCHASE transactions (simpler than sales workflow)
 * No draft phase needed
 */

class PurchaseService {
    
    /**
     * Record a purchase (creates PURCHASE transaction immediately)
     * Supports backdating via $options['transaction_date']
     */
    public static function recordPurchase($product_id, $quantity, $unit_cost, $period_id, $supplier = '', $options = []) {
        
        $user = Auth::getCurrentUser();
        if (!$user) {
            throw new Exception('User not authenticated');
        }
        
        $db = Database::getInstance();
        $db->beginTransaction();
        
        try {
            // Validate product
            $product = $db->fetch("SELECT id FROM products WHERE id = ?", [$product_id]);
            if (!$product) {
                throw new Exception("Product not found");
            }
            
            // Prepare transaction options (supports backdating)
            $transaction_options = [];
            if (isset($options['transaction_date']) && !empty($options['transaction_date'])) {
                $transaction_options['transaction_date'] = $options['transaction_date'];
            }
            
            // Create PURCHASE transaction
            $transaction_id = TransactionService::createTransaction(
                'PURCHASE',
                $product_id,
                $quantity,
                $unit_cost,
                $period_id,
                $transaction_options
            );
            
            $db->commit();
            
            AuditLog::log('RECORD_PURCHASE', 'transactions', $transaction_id, null, [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'supplier' => $supplier
            ]);
            
            return $transaction_id;
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Reverse a purchase transaction
     */
    public static function reversePurchase($transaction_id, $reason = '') {
        $db = Database::getInstance();
        
        // Validate original transaction
        $original = TransactionService::getTransaction($transaction_id);
        if (!$original) {
            throw new Exception("Transaction not found");
        }
        
        if ($original['type'] !== 'PURCHASE') {
            throw new Exception("Can only reverse PURCHASE transactions");
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
        
        AuditLog::log('REVERSE_PURCHASE', 'transactions', $transaction_id, $original, [
            'reversal_transaction_id' => $reversal_id,
            'reason' => $reason
        ]);
        
        return $reversal_id;
    }
    
    /**
     * Get purchase history
     */
    public static function getPurchaseHistory($period_id = null, $limit = 100) {
        $filters = ['type' => 'PURCHASE'];
        if ($period_id) {
            $filters['period_id'] = $period_id;
        }
        
        return TransactionService::getTransactionHistory($filters);
    }
}
?>
