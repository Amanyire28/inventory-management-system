<?php
/**
 * TOPINV Backend API
 * 
 * RESTful API for clinic inventory management
 * Enforces data integrity, role-based access, and append-only transactions
 */

// Start output buffering to prevent any warnings/notices from breaking JSON
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (ob_get_level()) ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

// Load core classes
require_once __DIR__ . '/../backend/core/Database.php';
require_once __DIR__ . '/../backend/core/Auth.php';
require_once __DIR__ . '/../backend/core/Response.php';
require_once __DIR__ . '/../backend/core/Router.php';

// Load services
require_once __DIR__ . '/../backend/services/TransactionService.php';
require_once __DIR__ . '/../backend/services/SalesService.php';
require_once __DIR__ . '/../backend/services/PurchaseService.php';
require_once __DIR__ . '/../backend/services/ProductService.php';
require_once __DIR__ . '/../backend/services/PeriodService.php';
require_once __DIR__ . '/../backend/services/StockTakingService.php';
require_once __DIR__ . '/../backend/services/ReportService.php';

// Initialize router
$router = new Router();

// ==========================================
// AUTHENTICATION
// ==========================================

$router->add('POST', '/auth/login', function() {
    $body = Router::getBody();
    
    if (empty($body['username']) || empty($body['password'])) {
        Response::validation([
            'username' => 'Username is required',
            'password' => 'Password is required'
        ]);
    }
    
    $result = Auth::login($body['username'], $body['password']);
    
    if (!$result['success']) {
        Response::unauthorized($result['message']);
    }
    
    Response::success($result, 'Login successful');
});

// ==========================================
// PRODUCTS
// ==========================================

$router->add('GET', '/products', function() {
    try {
        $statusFilter = Router::getParam('status') ?: 'active';
        $products = ProductService::getAllProducts($statusFilter);
        Response::success(['products' => $products]);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('GET', '/products/:id', function() {
    try {
        $id = Router::getIdFromPath();
        $product = ProductService::getProduct($id);
        
        if (!$product) {
            Response::notFound('Product not found');
        }
        
        // Include stock history
        $history = ProductService::getProductStockHistory($id);
        $product['stock_history'] = $history;
        
        Response::success($product);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('POST', '/products', function() {
    try {
        Auth::requireRole('admin');
        
        $body = Router::getBody();
        
        $product_id = ProductService::createProduct(
            $body['name'] ?? '',
            $body['code'] ?? '',
            $body['selling_price'] ?? 0,
            $body['cost_price'] ?? 0,
            $body['opening_stock'] ?? 0,
            $body['reorder_level'] ?? 10
        );
        
        Response::success(['product_id' => $product_id], 'Product created', 201);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('PUT', '/products/:id', function() {
    try {
        Auth::requireRole('admin');
        
        $id = Router::getIdFromPath();
        $body = Router::getBody();
        
        ProductService::updateProduct($id, $body);
        
        Response::success(['product_id' => $id], 'Product updated');
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

// ==========================================
// SALES (Draft → Commit Workflow)
// ==========================================

$router->add('POST', '/sales/draft', function() {
    try {
        Auth::requireRole('cashier');
        
        $result = SalesService::createDraftSale();
        Response::success($result, 'Draft sale created', 201);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('GET', '/sales/draft/:id', function() {
    try {
        Auth::requireRole('cashier');
        
        $id = Router::getIdFromPath();
        $draft = SalesService::getDraftSale($id);
        
        if (!$draft) {
            Response::notFound('Draft sale not found');
        }
        
        Response::success($draft);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('POST', '/sales/draft/:id/items', function() {
    try {
        Auth::requireRole('cashier');
        
        $draft_id = Router::getIdFromPath();
        $body = Router::getBody();
        
        $item_id = SalesService::addDraftItem(
            $draft_id,
            $body['product_id'] ?? 0,
            $body['quantity'] ?? 0,
            $body['unit_price'] ?? 0
        );
        
        Response::success(['item_id' => $item_id], 'Item added to draft', 201);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('DELETE', '/sales/draft/:id/items/:item_id', function() {
    try {
        Auth::requireRole('cashier');
        
        $draft_id = Router::getIdFromPath();
        $body = Router::getBody();
        $item_id = $body['item_id'] ?? Router::getIdFromPath();
        
        SalesService::removeDraftItem($draft_id, $item_id);
        
        Response::success([], 'Item removed from draft');
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('POST', '/sales/commit', function() {
    try {
        Auth::requireRole('cashier');
        
        $body = Router::getBody();
        
        $options = [];
        if (isset($body['transaction_date']) && !empty($body['transaction_date'])) {
            $options['transaction_date'] = $body['transaction_date'];
        }
        
        $result = SalesService::commitDraftSale(
            $body['draft_id'] ?? 0,
            $body['period_id'] ?? 0,
            $options
        );
        
        Response::success($result, 'Sale committed');
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('GET', '/sales/history', function() {
    try {
        $period_id = Router::getParam('period_id');
        
        $sales = SalesService::getSalesHistory($period_id);
        Response::success(['sales' => $sales]);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('POST', '/sales/:id/reverse', function() {
    try {
        Auth::requireRole('admin');
        
        $transaction_id = Router::getIdFromPath();
        $body = Router::getBody();
        
        $reversal_id = SalesService::reverseSale(
            $transaction_id,
            $body['reason'] ?? ''
        );
        
        Response::success(['reversal_id' => $reversal_id], 'Sale reversed');
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

// ==========================================
// PURCHASES
// ==========================================

$router->add('POST', '/purchases', function() {
    try {
        Auth::requireRole(['admin', 'cashier']);
        
        $body = Router::getBody();
        
        $options = [];
        if (isset($body['transaction_date']) && !empty($body['transaction_date'])) {
            $options['transaction_date'] = $body['transaction_date'];
        }
        
        $transaction_id = PurchaseService::recordPurchase(
            $body['product_id'] ?? 0,
            $body['quantity'] ?? 0,
            $body['unit_cost'] ?? 0,
            $body['period_id'] ?? 0,
            $body['supplier'] ?? '',
            $options
        );
        
        Response::success(['transaction_id' => $transaction_id], 'Purchase recorded', 201);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('GET', '/purchases/history', function() {
    try {
        $period_id = Router::getParam('period_id');
        
        $purchases = PurchaseService::getPurchaseHistory($period_id);
        Response::success(['purchases' => $purchases]);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('POST', '/purchases/:id/reverse', function() {
    try {
        Auth::requireRole('admin');
        
        $transaction_id = Router::getIdFromPath();
        $body = Router::getBody();
        
        $reversal_id = PurchaseService::reversePurchase(
            $transaction_id,
            $body['reason'] ?? ''
        );
        
        Response::success(['reversal_id' => $reversal_id], 'Purchase reversed');
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

// ==========================================
// TRANSACTIONS
// ==========================================

$router->add('GET', '/transactions', function() {
    try {
        $filters = [
            'product_id' => Router::getParam('product_id'),
            'period_id' => Router::getParam('period_id'),
            'type' => Router::getParam('type')
        ];
        
        $transactions = TransactionService::getTransactionHistory(array_filter($filters));
        Response::success(['transactions' => $transactions]);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('GET', '/transactions/:id', function() {
    try {
        $id = Router::getIdFromPath();
        $transaction = TransactionService::getTransaction($id);
        
        if (!$transaction) {
            Response::notFound('Transaction not found');
        }
        
        Response::success($transaction);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

// ==========================================
// PERIODS
// ==========================================

$router->add('GET', '/periods', function() {
    try {
        $periods = PeriodService::getAllPeriods();
        Response::success(['periods' => $periods]);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('GET', '/periods/current', function() {
    try {
        $period = PeriodService::getCurrentPeriod();
        
        if (!$period) {
            Response::notFound('No open period found');
        }
        
        Response::success($period);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('POST', '/periods', function() {
    try {
        Auth::requireRole('admin');
        
        $body = Router::getBody();
        
        $period_id = PeriodService::createPeriod(
            $body['period_name'] ?? '',
            $body['start_date'] ?? '',
            $body['end_date'] ?? ''
        );
        
        Response::success(['period_id' => $period_id], 'Period created', 201);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('POST', '/periods/:id/close', function() {
    try {
        Auth::requireRole('admin');
        
        $id = Router::getIdFromPath();
        
        PeriodService::closePeriod($id);
        
        Response::success([], 'Period closed');
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('GET', '/periods/:id/summary', function() {
    try {
        $id = Router::getIdFromPath();
        
        $summary = PeriodService::getPeriodSummary($id);
        
        Response::success($summary);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

// ==========================================
// STOCK TAKING
// ==========================================

$router->add('POST', '/inventory/physical-count', function() {
    try {
        Auth::requireRole('admin');
        
        $body = Router::getBody();
        
        $result = StockTakingService::recordPhysicalCount(
            $body['product_id'] ?? 0,
            $body['physical_count'] ?? 0,
            $body['period_id'] ?? 0
        );
        
        Response::success($result, 'Physical count recorded');
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('GET', '/inventory/adjustments', function() {
    try {
        $period_id = Router::getParam('period_id');
        
        $adjustments = StockTakingService::getAdjustments($period_id);
        Response::success(['adjustments' => $adjustments]);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('GET', '/inventory/variance-report', function() {
    try {
        $period_id = Router::getParam('period_id');
        
        if (!$period_id) {
            Response::error('period_id is required');
        }
        
        $report = StockTakingService::getPeriodVarianceReport($period_id);
        Response::success(['report' => $report]);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

// ==========================================
// AUDIT LOGS
// ==========================================

$router->add('GET', '/audit-logs', function() {
    try {
        $entity_type = Router::getParam('entity_type');
        $entity_id = Router::getParam('entity_id');
        
        if ($entity_type && $entity_id) {
            $logs = AuditLog::getEntityLogs($entity_type, $entity_id);
        } else {
            // Return recent logs
            $db = Database::getInstance();
            $logs = $db->fetchAll(
                "SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT 100"
            );
        }
        
        Response::success(['logs' => $logs]);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

// ==========================================
// DASHBOARD
// ==========================================

$router->add('GET', '/dashboard', function() {
    try {
        $action = Router::getParam('action');
        
        if (!$action) {
            Response::error('Action parameter is required');
            return;
        }
        
        $db = Database::getInstance();
        $user = Auth::getCurrentUser();
        
        switch ($action) {
            case 'summary':
                // Comprehensive dashboard summary
                $today = date('Y-m-d');
                $todayStart = $today . ' 00:00:00';
                $todayEnd = $today . ' 23:59:59';
                
                // Total products count
                $productCountQuery = "SELECT COUNT(*) as total FROM products WHERE status = 'active'";
                $productCount = $db->fetch($productCountQuery);
                
                // Today's sales
                $salesQuery = "SELECT 
                    COALESCE(SUM(total_amount), 0) as amount,
                    COUNT(*) as count,
                    COALESCE(SUM(ABS(quantity)), 0) as items_sold
                FROM transactions 
                WHERE type = 'SALE' 
                    AND status = 'COMMITTED'
                    AND transaction_date BETWEEN ? AND ?";
                $salesResult = $db->fetch($salesQuery, [$todayStart, $todayEnd]);
                
                // Today's purchases
                $purchasesQuery = "SELECT 
                    COALESCE(SUM(total_amount), 0) as amount,
                    COUNT(*) as count
                FROM transactions 
                WHERE type = 'PURCHASE' 
                    AND status = 'COMMITTED'
                    AND transaction_date BETWEEN ? AND ?";
                $purchasesResult = $db->fetch($purchasesQuery, [$todayStart, $todayEnd]);
                
                // Low stock count
                $lowStockQuery = "SELECT COUNT(*) as count FROM products 
                    WHERE current_stock > 0 
                    AND current_stock <= reorder_level 
                    AND status = 'active'";
                $lowStockResult = $db->fetch($lowStockQuery);
                
                Response::success([
                    'total_products' => intval($productCount['total'] ?? 0),
                    'today_sales' => floatval($salesResult['amount'] ?? 0),
                    'today_purchases' => floatval($purchasesResult['amount'] ?? 0),
                    'low_stock_count' => intval($lowStockResult['count'] ?? 0),
                    'today' => [
                        'revenue' => floatval($salesResult['amount'] ?? 0),
                        'transaction_count' => intval($salesResult['count'] ?? 0),
                        'items_sold' => intval($salesResult['items_sold'] ?? 0)
                    ]
                ]);
                break;
                
            case 'metrics':
                $today = date('Y-m-d');
                $todayStart = $today . ' 00:00:00';
                $todayEnd = $today . ' 23:59:59';
                
                // Sales revenue for today
                $salesQuery = "SELECT 
                    COALESCE(SUM(total_amount), 0) as revenue,
                    COUNT(*) as transaction_count
                FROM transactions 
                WHERE type = 'SALE' 
                    AND status = 'COMMITTED'
                    AND transaction_date BETWEEN ? AND ?";
                
                $salesResult = $db->fetch($salesQuery, [$todayStart, $todayEnd]);
                
                // Purchases for today
                $purchasesQuery = "SELECT 
                    COALESCE(SUM(total_amount), 0) as amount,
                    COUNT(*) as purchase_count
                FROM transactions 
                WHERE type = 'PURCHASE' 
                    AND status = 'COMMITTED'
                    AND transaction_date BETWEEN ? AND ?";
                
                $purchasesResult = $db->fetch($purchasesQuery, [$todayStart, $todayEnd]);
                
                // Voided units for today
                $voidsQuery = "SELECT 
                    COALESCE(SUM(ABS(quantity)), 0) as voided_units,
                    COUNT(*) as void_count
                FROM transactions 
                WHERE type IN ('VOID', 'REVERSAL')
                    AND status = 'COMMITTED'
                    AND transaction_date BETWEEN ? AND ?";
                
                $voidsResult = $db->fetch($voidsQuery, [$todayStart, $todayEnd]);
                
                // Adjustments for today
                $adjustmentsQuery = "SELECT 
                    COUNT(*) as adjustment_count,
                    COALESCE(SUM(ABS(variance)), 0) as total_variance
                FROM stock_adjustments 
                WHERE DATE(created_at) = ?";
                
                $adjustmentsResult = $db->fetch($adjustmentsQuery, [$today]);
                
                Response::success([
                    'sales' => [
                        'revenue' => floatval($salesResult['revenue'] ?? 0),
                        'transaction_count' => intval($salesResult['transaction_count'] ?? 0)
                    ],
                    'purchases' => [
                        'amount' => floatval($purchasesResult['amount'] ?? 0),
                        'purchase_count' => intval($purchasesResult['purchase_count'] ?? 0)
                    ],
                    'voids' => [
                        'units' => intval($voidsResult['voided_units'] ?? 0),
                        'void_count' => intval($voidsResult['void_count'] ?? 0)
                    ],
                    'adjustments' => [
                        'count' => intval($adjustmentsResult['adjustment_count'] ?? 0),
                        'total_variance' => intval($adjustmentsResult['total_variance'] ?? 0)
                    ]
                ]);
                break;
                
            case 'alerts':
                // Low stock products
                $lowStockQuery = "SELECT 
                    id, name, code, current_stock, reorder_level
                FROM products 
                WHERE current_stock > 0 
                    AND current_stock <= reorder_level 
                    AND status = 'active'
                ORDER BY current_stock ASC
                LIMIT 20";
                
                $lowStock = $db->fetchAll($lowStockQuery);
                
                // Out of stock products
                $outOfStockQuery = "SELECT 
                    id, name, code, current_stock, reorder_level
                FROM products 
                WHERE current_stock = 0 
                    AND status = 'active'
                ORDER BY name ASC
                LIMIT 20";
                
                $outOfStock = $db->fetchAll($outOfStockQuery);
                
                Response::success([
                    'low_stock' => $lowStock,
                    'out_of_stock' => $outOfStock,
                    'near_expiry' => []
                ]);
                break;
                
            case 'recent-transactions':
                $limit = intval(Router::getParam('limit') ?? 20);
                
                $query = "SELECT 
                    t.id,
                    t.type,
                    t.quantity,
                    t.unit_price,
                    t.total_amount,
                    t.transaction_date,
                    t.status,
                    p.name as product_name,
                    p.code as product_code,
                    u.full_name as created_by_name,
                    u.username as created_by_username
                FROM transactions t
                JOIN products p ON t.product_id = p.id
                JOIN users u ON t.created_by = u.id
                WHERE t.status = 'COMMITTED'
                ORDER BY t.transaction_date DESC
                LIMIT ?";
                
                $transactions = $db->fetchAll($query, [$limit]);
                
                $result = array_map(function($row) {
                    return [
                        'id' => $row['id'],
                        'type' => $row['type'],
                        'quantity' => intval($row['quantity']),
                        'unit_price' => floatval($row['unit_price']),
                        'total_amount' => floatval($row['total_amount']),
                        'transaction_date' => $row['transaction_date'],
                        'product_name' => $row['product_name'],
                        'product_code' => $row['product_code'],
                        'created_by' => $row['created_by_name'] ?: $row['created_by_username']
                    ];
                }, $transactions);
                
                Response::success($result);
                break;
                
            case 'period-status':
                $query = "SELECT 
                    id,
                    period_name,
                    status,
                    start_date,
                    end_date,
                    created_at,
                    closed_at
                FROM periods 
                WHERE status = 'OPEN'
                ORDER BY start_date DESC
                LIMIT 1";
                
                $period = $db->fetch($query);
                
                if (!$period) {
                    Response::success(null);
                    return;
                }
                
                // Calculate days running
                $startDate = new DateTime($period['start_date']);
                $today = new DateTime();
                $daysRunning = $startDate->diff($today)->days;
                
                // Get last stock taking date
                $stockTakingQuery = "SELECT MAX(created_at) as last_stock_taking
                FROM stock_adjustments
                WHERE period_id = ?";
                
                $stockTakingResult = $db->fetch($stockTakingQuery, [$period['id']]);
                
                Response::success([
                    'id' => $period['id'],
                    'period_name' => $period['period_name'],
                    'status' => $period['status'],
                    'start_date' => $period['start_date'],
                    'end_date' => $period['end_date'],
                    'days_running' => $daysRunning,
                    'last_stock_taking' => $stockTakingResult['last_stock_taking'] ?? null
                ]);
                break;
                
            case 'user-summary':
                if (!$user) {
                    Response::unauthorized('Authentication required');
                    break;
                }
                
                try {
                    $today = date('Y-m-d');
                    $todayStart = $today . ' 00:00:00';
                    $todayEnd = $today . ' 23:59:59';
                    
                    // Get today's sales by this user
                    $salesQuery = "SELECT 
                        COALESCE(SUM(total_amount), 0) as revenue,
                        COUNT(*) as transaction_count
                    FROM transactions 
                    WHERE type = 'SALE' 
                        AND status = 'COMMITTED'
                        AND created_by = ?
                        AND transaction_date BETWEEN ? AND ?";
                    
                    $salesResult = $db->fetch($salesQuery, [$user['user_id'], $todayStart, $todayEnd]);
                    
                    if (!$salesResult) {
                        $salesResult = ['revenue' => 0, 'transaction_count' => 0];
                    }
                    
                    Response::success([
                        'user' => [
                            'id' => $user['user_id'],
                            'username' => $user['username'],
                            'full_name' => $user['full_name'] ?? '',
                            'role' => $user['role']
                        ],
                        'today_stats' => [
                            'sales_revenue' => floatval($salesResult['revenue'] ?? 0),
                            'transaction_count' => intval($salesResult['transaction_count'] ?? 0)
                        ]
                    ]);
                } catch (Exception $e) {
                    Response::error('User summary error: ' . $e->getMessage(), 500);
                }
                break;
                
            default:
                Response::error('Invalid action');
        }
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

// ====================================================
// REPORTS - Real-time report generation
// ====================================================

$router->add('GET', '/reports/daily-transactions', function() {
    try {
        Auth::requireRole(['admin', 'cashier']);
        
        $date = Router::getParam('date') ?? date('Y-m-d');
        
        $report = ReportService::getDailyTransactions($date);
        Response::success($report);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('GET', '/reports/stock-valuation', function() {
    try {
        Auth::requireRole(['admin', 'cashier']);
        
        $report = ReportService::getStockValuation();
        Response::success($report);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('GET', '/reports/period-summary', function() {
    try {
        Auth::requireRole(['admin']);
        
        $period_id = Router::getParam('period_id');
        if (!$period_id) {
            throw new Exception('period_id parameter required');
        }
        
        $report = ReportService::getPeriodReport($period_id);
        Response::success($report);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

$router->add('GET', '/reports/periods', function() {
    try {
        Auth::requireRole(['admin', 'cashier']);
        
        $periods = ReportService::getAvailablePeriods();
        Response::success(['periods' => $periods]);
    } catch (Exception $e) {
        Response::error($e->getMessage());
    }
});

// Dispatch request
try {
    $router->dispatch();
} catch (Exception $e) {
    Response::error($e->getMessage(), 500);
}
?>
