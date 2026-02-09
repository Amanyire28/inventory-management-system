<?php
/**
 * Report Service
 * 
 * Generates various reports for the inventory system
 * All reports are generated in real-time (on-demand)
 */

class ReportService {
    
    /**
     * Daily Transactions Report
     * Shows all transactions for a specific date with audit trail
     */
    public static function getDailyTransactions($date) {
        $db = Database::getInstance();
        
        // Validate date format
        $parsed = date_parse($date);
        if ($parsed['error_count'] > 0) {
            throw new Exception("Invalid date format. Use: YYYY-MM-DD");
        }
        
        // Get all transactions for this date
        $transactions = $db->fetchAll(
            "SELECT t.*,
                    p.name as product_name,
                    u.full_name as user_name,
                    DATE(t.transaction_date) as trans_date,
                    DATE(t.created_at) as recorded_date,
                    CASE 
                        WHEN DATE(t.transaction_date) != DATE(t.created_at) 
                        THEN 1 
                        ELSE 0 
                    END as is_backdated,
                    DATEDIFF(t.created_at, t.transaction_date) as days_late
             FROM transactions t
             JOIN products p ON t.product_id = p.id
             JOIN users u ON t.created_by = u.id
             WHERE DATE(t.transaction_date) = ?
             ORDER BY t.transaction_date ASC, t.id ASC",
            [$date]
        );
        
        // Calculate summary
        $total_sales = 0;
        $total_purchases = 0;
        $total_sales_qty = 0;
        $total_purchases_qty = 0;
        $sale_count = 0;
        $purchase_count = 0;
        $backdated_count = 0;
        
        foreach ($transactions as $txn) {
            if ($txn['type'] === 'SALE') {
                $total_sales += $txn['total_amount'];
                $total_sales_qty += $txn['quantity'];
                $sale_count++;
            } elseif ($txn['type'] === 'PURCHASE') {
                $total_purchases += $txn['total_amount'];
                $total_purchases_qty += $txn['quantity'];
                $purchase_count++;
            }
            
            if ($txn['is_backdated']) {
                $backdated_count++;
            }
        }
        
        return [
            'date' => $date,
            'transactions' => $transactions,
            'summary' => [
                'total_transactions' => count($transactions),
                'sales' => [
                    'count' => $sale_count,
                    'quantity' => $total_sales_qty,
                    'amount' => $total_sales
                ],
                'purchases' => [
                    'count' => $purchase_count,
                    'quantity' => $total_purchases_qty,
                    'amount' => $total_purchases
                ],
                'net_cash_flow' => $total_sales - $total_purchases,
                'backdated_entries' => $backdated_count
            ]
        ];
    }
    
    /**
     * Stock Valuation Report
     * Current inventory value and stock levels with movement breakdown
     */
    public static function getStockValuation() {
        $db = Database::getInstance();
        
        $as_of = date('Y-m-d H:i:s');
        
        // Get all products with current stock and movement breakdown
        $products = $db->fetchAll(
            "SELECT p.id,
                    p.name,
                    p.description,
                    p.opening_stock,
                    p.current_stock,
                    p.reorder_level,
                    p.selling_price,
                    p.cost_price,
                    p.status,
                    -- Calculate weighted average cost
                    (SELECT AVG(unit_price) 
                     FROM transactions 
                     WHERE product_id = p.id 
                       AND type = 'PURCHASE' 
                       AND status = 'COMMITTED') as avg_cost,
                    -- Total purchases
                    COALESCE((SELECT SUM(quantity) 
                     FROM transactions 
                     WHERE product_id = p.id 
                       AND type = 'PURCHASE' 
                       AND status = 'COMMITTED'), 0) as total_purchases,
                    -- Total sales (absolute value)
                    COALESCE((SELECT ABS(SUM(quantity)) 
                     FROM transactions 
                     WHERE product_id = p.id 
                       AND type = 'SALE' 
                       AND status = 'COMMITTED'), 0) as total_sales,
                    -- Total adjustments (can be positive or negative)
                    COALESCE((SELECT SUM(quantity) 
                     FROM transactions 
                     WHERE product_id = p.id 
                       AND type = 'ADJUSTMENT' 
                       AND status = 'COMMITTED'), 0) as total_adjustments,
                    -- Last purchase date
                    (SELECT MAX(transaction_date) 
                     FROM transactions 
                     WHERE product_id = p.id 
                       AND type = 'PURCHASE') as last_purchase_date,
                    -- Last sale date
                    (SELECT MAX(transaction_date) 
                     FROM transactions 
                     WHERE product_id = p.id 
                       AND type = 'SALE') as last_sale_date
             FROM products p
             WHERE p.status = 'active'
             ORDER BY p.name ASC"
        );
        
        $total_value = 0;
        $low_stock_count = 0;
        $out_of_stock_count = 0;
        $total_opening = 0;
        $total_purchases = 0;
        $total_sales = 0;
        $total_adjustments = 0;
        
        foreach ($products as &$product) {
            // Verify stock calculation: Opening + Purchases - Sales ± Adjustments = Current
            $calculated_stock = $product['opening_stock'] + $product['total_purchases'] - $product['total_sales'] + $product['total_adjustments'];
            $product['calculated_stock'] = $calculated_stock;
            $product['variance'] = $product['current_stock'] - $calculated_stock;
            
            $avg_cost = $product['avg_cost'] ?? $product['cost_price'];
            $product['average_cost'] = $avg_cost;
            $product['total_value'] = $product['current_stock'] * $avg_cost;
            $total_value += $product['total_value'];
            
            // Aggregate totals for summary
            $total_opening += $product['opening_stock'];
            $total_purchases += $product['total_purchases'];
            $total_sales += $product['total_sales'];
            $total_adjustments += $product['total_adjustments'];
            
            // Stock status
            if ($product['current_stock'] <= 0) {
                $product['stock_status'] = 'OUT_OF_STOCK';
                $out_of_stock_count++;
            } elseif ($product['current_stock'] <= $product['reorder_level']) {
                $product['stock_status'] = 'LOW_STOCK';
                $low_stock_count++;
            } else {
                $product['stock_status'] = 'ADEQUATE';
            }
            
            // Calculate days since last sale
            if ($product['last_sale_date']) {
                $last_sale = new DateTime($product['last_sale_date']);
                $now = new DateTime();
                $product['days_since_last_sale'] = $now->diff($last_sale)->days;
                
                // Movement classification
                if ($product['days_since_last_sale'] <= 7) {
                    $product['movement'] = 'FAST';
                } elseif ($product['days_since_last_sale'] <= 30) {
                    $product['movement'] = 'NORMAL';
                } elseif ($product['days_since_last_sale'] <= 90) {
                    $product['movement'] = 'SLOW';
                } else {
                    $product['movement'] = 'NON_MOVING';
                }
            } else {
                $product['days_since_last_sale'] = null;
                $product['movement'] = 'NO_SALES';
            }
        }
        
        return [
            'as_of_date' => $as_of,
            'total_inventory_value' => $total_value,
            'products' => $products,
            'summary' => [
                'total_products' => count($products),
                'out_of_stock' => $out_of_stock_count,
                'low_stock' => $low_stock_count,
                'adequate_stock' => count($products) - $out_of_stock_count - $low_stock_count,
                'stock_movement' => [
                    'opening_stock' => $total_opening,
                    'purchases' => $total_purchases,
                    'sales' => $total_sales,
                    'adjustments' => $total_adjustments,
                    'calculated_closing' => $total_opening + $total_purchases - $total_sales + $total_adjustments
                ]
            ]
        ];
    }
    
    /**
     * Monthly Period Report
     * Comprehensive report for entire month/period with stock movements
     */
    public static function getPeriodReport($period_id) {
        $db = Database::getInstance();
        
        // Get period details
        $period = $db->fetch(
            "SELECT * FROM periods WHERE id = ?",
            [$period_id]
        );
        
        if (!$period) {
            throw new Exception("Period not found");
        }
        
        // Get stock movements per product for this period
        $productMovements = $db->fetchAll(
            "SELECT p.id as product_id,
                    p.name as product_name,
                    ppos.opening_stock,
                    COALESCE(SUM(CASE WHEN t.type = 'PURCHASE' THEN t.quantity ELSE 0 END), 0) as purchases,
                    COALESCE(ABS(SUM(CASE WHEN t.type = 'SALE' THEN t.quantity ELSE 0 END)), 0) as sales,
                    COALESCE(SUM(CASE WHEN t.type = 'ADJUSTMENT' THEN t.quantity ELSE 0 END), 0) as adjustments,
                    ppos.closing_stock
             FROM products p
             LEFT JOIN period_product_opening_stock ppos ON p.id = ppos.product_id AND ppos.period_id = ?
             LEFT JOIN transactions t ON p.id = t.product_id 
                AND t.period_id = ? 
                AND t.status = 'COMMITTED'
             GROUP BY p.id, p.name, ppos.opening_stock, ppos.closing_stock
             HAVING purchases > 0 OR sales > 0 OR adjustments != 0
             ORDER BY p.name ASC",
            [$period_id, $period_id]
        );
        
        // Get all transactions in this period
        $transactions = $db->fetchAll(
            "SELECT t.*,
                    p.name as product_name,
                    u.full_name as user_name
             FROM transactions t
             JOIN products p ON t.product_id = p.id
             JOIN users u ON t.created_by = u.id
             WHERE t.period_id = ? AND t.status = 'COMMITTED'
             ORDER BY t.transaction_date ASC",
            [$period_id]
        );
        
        // Initialize counters
        $sales_total = 0;
        $sales_qty = 0;
        $sales_count = 0;
        $sales_by_product = [];
        
        $purchases_total = 0;
        $purchases_qty = 0;
        $purchases_count = 0;
        $purchases_by_product = [];
        
        $adjustments = [];
        $reversals = [];
        $backdated_count = 0;
        
        // Process transactions
        foreach ($transactions as $txn) {
            $is_backdated = date('Y-m-d', strtotime($txn['transaction_date'])) != date('Y-m-d', strtotime($txn['created_at']));
            
            if ($is_backdated) {
                $backdated_count++;
            }
            
            switch ($txn['type']) {
                case 'SALE':
                    $sales_total += $txn['total_amount'];
                    $sales_qty += $txn['quantity'];
                    $sales_count++;
                    
                    if (!isset($sales_by_product[$txn['product_id']])) {
                        $sales_by_product[$txn['product_id']] = [
                            'product_name' => $txn['product_name'],
                            'quantity' => 0,
                            'amount' => 0,
                            'count' => 0
                        ];
                    }
                    $sales_by_product[$txn['product_id']]['quantity'] += $txn['quantity'];
                    $sales_by_product[$txn['product_id']]['amount'] += $txn['total_amount'];
                    $sales_by_product[$txn['product_id']]['count']++;
                    break;
                    
                case 'PURCHASE':
                    $purchases_total += $txn['total_amount'];
                    $purchases_qty += $txn['quantity'];
                    $purchases_count++;
                    
                    if (!isset($purchases_by_product[$txn['product_id']])) {
                        $purchases_by_product[$txn['product_id']] = [
                            'product_name' => $txn['product_name'],
                            'quantity' => 0,
                            'amount' => 0,
                            'count' => 0
                        ];
                    }
                    $purchases_by_product[$txn['product_id']]['quantity'] += $txn['quantity'];
                    $purchases_by_product[$txn['product_id']]['amount'] += $txn['total_amount'];
                    $purchases_by_product[$txn['product_id']]['count']++;
                    break;
                    
                case 'ADJUSTMENT':
                    $adjustments[] = $txn;
                    break;
                    
                case 'REVERSAL':
                    $reversals[] = $txn;
                    break;
            }
        }
        
        // Calculate gross profit
        $gross_profit = $sales_total - $purchases_total;
        $margin_percent = $sales_total > 0 ? ($gross_profit / $sales_total) * 100 : 0;
        
        // Calculate aggregate stock movement
        $period_opening = 0;
        $period_purchases = 0;
        $period_sales = 0;
        $period_adjustments = 0;
        $period_closing = 0;
        
        foreach ($productMovements as &$movement) {
            $calculated = $movement['opening_stock'] + $movement['purchases'] - $movement['sales'] + $movement['adjustments'];
            $movement['calculated_closing'] = $calculated;
            $movement['variance'] = $movement['closing_stock'] - $calculated;
            
            $period_opening += $movement['opening_stock'];
            $period_purchases += $movement['purchases'];
            $period_sales += $movement['sales'];
            $period_adjustments += $movement['adjustments'];
            $period_closing += $movement['closing_stock'];
        }
        
        // Sort products by amount (descending)
        uasort($sales_by_product, function($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });
        
        uasort($purchases_by_product, function($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });
        
        return [
            'period_id' => $period_id,
            'period_name' => $period['period_name'],
            'period_status' => $period['status'],
            'date_range' => [
                'start' => $period['start_date'],
                'end' => $period['end_date']
            ],
            
            'sales' => [
                'total_amount' => $sales_total,
                'total_quantity' => $sales_qty,
                'transaction_count' => $sales_count,
                'by_product' => array_values($sales_by_product),
                'top_products' => array_slice(array_values($sales_by_product), 0, 5)
            ],
            
            'purchases' => [
                'total_amount' => $purchases_total,
                'total_quantity' => $purchases_qty,
                'transaction_count' => $purchases_count,
                'by_product' => array_values($purchases_by_product),
                'top_products' => array_slice(array_values($purchases_by_product), 0, 5)
            ],
            
            'profit' => [
                'gross_profit' => $gross_profit,
                'margin_percent' => round($margin_percent, 2)
            ],
            
            'adjustments' => [
                'count' => count($adjustments),
                'items' => $adjustments
            ],
            
            'reversals' => [
                'count' => count($reversals),
                'items' => $reversals
            ],
            
            'audit' => [
                'backdated_entries' => $backdated_count,
                'total_transactions' => count($transactions)
            ],
            
            'stock_movement' => [
                'opening_stock' => $period_opening,
                'purchases' => $period_purchases,
                'sales' => $period_sales,
                'adjustments' => $period_adjustments,
                'calculated_closing' => $period_opening + $period_purchases - $period_sales + $period_adjustments,
                'actual_closing' => $period_closing,
                'variance' => $period_closing - ($period_opening + $period_purchases - $period_sales + $period_adjustments),
                'by_product' => $productMovements
            ]
        ];
    }
    
    /**
     * Get list of available periods for reporting
     */
    public static function getAvailablePeriods() {
        $db = Database::getInstance();
        
        return $db->fetchAll(
            "SELECT id, period_name, status, start_date, end_date, 
                    created_at, closed_at
             FROM periods
             ORDER BY start_date DESC
             LIMIT 24"  // Last 24 periods (2 years if monthly)
        );
    }
}
?>
