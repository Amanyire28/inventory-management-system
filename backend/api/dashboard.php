<?php
/**
 * TOPINV - Dashboard API
 * 
 * Provides dashboard metrics, alerts, and summary data
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/auth_middleware.php';

// Verify authentication
$user = verifyAuth();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$db = getDBConnection();
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Route to appropriate handler
switch ($action) {
    case 'metrics':
        getMetrics($db, $user);
        break;
    case 'alerts':
        getInventoryAlerts($db);
        break;
    case 'recent-transactions':
        getRecentTransactions($db, $user);
        break;
    case 'period-status':
        getPeriodStatus($db);
        break;
    case 'user-summary':
        getUserSummary($db, $user);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

/**
 * Get Dashboard Metrics
 */
function getMetrics($db, $user) {
    try {
        // Get current OPEN period
        $periodQuery = "SELECT id, start_date, end_date FROM periods WHERE status = 'OPEN' LIMIT 1";
        $periodResult = $db->query($periodQuery);
        $currentPeriod = $periodResult ? $periodResult->fetch_assoc() : null;
        
        // Define date range for filtering
        if ($currentPeriod) {
            // Filter by current period
            $startDate = $currentPeriod['start_date'] ?: date('Y-m-01'); // Default to start of month
            $endDate = $currentPeriod['end_date'] ?: date('Y-m-d 23:59:59'); // Default to today
            $periodId = $currentPeriod['id'];
        } else {
            // No open period - show today's data only
            $today = date('Y-m-d');
            $startDate = $today . ' 00:00:00';
            $endDate = $today . ' 23:59:59';
            $periodId = null;
        }
        
        // Get sales revenue for current period
        $salesQuery = "SELECT 
            COALESCE(SUM(total_amount), 0) as revenue,
            COUNT(*) as transaction_count
        FROM transactions 
        WHERE type = 'SALE' 
            AND status = 'COMMITTED'
            AND transaction_date BETWEEN ? AND ?";
        
        // Add period filter if available
        if ($periodId) {
            $salesQuery .= " AND period_id = ?";
        }
        
        $stmt = $db->prepare($salesQuery);
        if ($periodId) {
            $stmt->bind_param('ssi', $startDate, $endDate, $periodId);
        } else {
            $stmt->bind_param('ss', $startDate, $endDate);
        }
        $stmt->execute();
        $salesResult = $stmt->get_result()->fetch_assoc();
        
        // Get purchases for current period
        $purchasesQuery = "SELECT 
            COALESCE(SUM(total_amount), 0) as amount,
            COUNT(*) as purchase_count
        FROM transactions 
        WHERE type = 'PURCHASE' 
            AND status = 'COMMITTED'
            AND transaction_date BETWEEN ? AND ?";
        
        if ($periodId) {
            $purchasesQuery .= " AND period_id = ?";
        }
        
        $stmt = $db->prepare($purchasesQuery);
        if ($periodId) {
            $stmt->bind_param('ssi', $startDate, $endDate, $periodId);
        } else {
            $stmt->bind_param('ss', $startDate, $endDate);
        }
        $stmt->execute();
        $purchasesResult = $stmt->get_result()->fetch_assoc();
        
        // Get voided units for current period
        $voidsQuery = "SELECT 
            COALESCE(SUM(ABS(quantity)), 0) as voided_units,
            COUNT(*) as void_count
        FROM transactions 
        WHERE type IN ('VOID', 'REVERSAL')
            AND status = 'COMMITTED'
            AND transaction_date BETWEEN ? AND ?";
        
        if ($periodId) {
            $voidsQuery .= " AND period_id = ?";
        }
        
        $stmt = $db->prepare($voidsQuery);
        if ($periodId) {
            $stmt->bind_param('ssi', $startDate, $endDate, $periodId);
        } else {
            $stmt->bind_param('ss', $startDate, $endDate);
        }
        $stmt->execute();
        $voidsResult = $stmt->get_result()->fetch_assoc();
        
        // Get adjustments for current period
        $today = date('Y-m-d');
        $adjustmentsQuery = "SELECT 
            COUNT(*) as adjustment_count,
            COALESCE(SUM(ABS(variance)), 0) as total_variance
        FROM stock_adjustments 
        WHERE created_at BETWEEN ? AND ?";
        
        if ($periodId) {
            $adjustmentsQuery .= " AND period_id = ?";
        }
        
        $stmt = $db->prepare($adjustmentsQuery);
        if ($periodId) {
            $stmt->bind_param('ssi', $startDate, $endDate, $periodId);
        } else {
            $stmt->bind_param('ss', $startDate, $endDate);
        }
        $stmt->execute();
        $adjustmentsResult = $stmt->get_result()->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'period' => $currentPeriod ? [
                    'id' => $currentPeriod['id'],
                    'start_date' => $currentPeriod['start_date'],
                    'end_date' => $currentPeriod['end_date']
                ] : null,
                'sales' => [
                    'revenue' => floatval($salesResult['revenue']),
                    'transaction_count' => intval($salesResult['transaction_count'])
                ],
                'purchases' => [
                    'amount' => floatval($purchasesResult['amount']),
                    'purchase_count' => intval($purchasesResult['purchase_count'])
                ],
                'voids' => [
                    'units' => intval($voidsResult['voided_units']),
                    'void_count' => intval($voidsResult['void_count'])
                ],
                'adjustments' => [
                    'count' => intval($adjustmentsResult['adjustment_count']),
                    'total_variance' => intval($adjustmentsResult['total_variance'])
                ]
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get metrics: ' . $e->getMessage()]);
    }
}

/**
 * Get Inventory Alerts
 */
function getInventoryAlerts($db) {
    try {
        // Low stock products
        $lowStockQuery = "SELECT 
            id, name, code, current_stock, reorder_level
        FROM products 
        WHERE current_stock > 0 
            AND current_stock <= reorder_level 
            AND status = 'active'
        ORDER BY current_stock ASC
        LIMIT 20";
        
        $lowStockResult = $db->query($lowStockQuery);
        $lowStock = [];
        while ($row = $lowStockResult->fetch_assoc()) {
            $lowStock[] = $row;
        }
        
        // Out of stock products
        $outOfStockQuery = "SELECT 
            id, name, code, current_stock, reorder_level
        FROM products 
        WHERE current_stock = 0 
            AND status = 'active'
        ORDER BY name ASC
        LIMIT 20";
        
        $outOfStockResult = $db->query($outOfStockQuery);
        $outOfStock = [];
        while ($row = $outOfStockResult->fetch_assoc()) {
            $outOfStock[] = $row;
        }
        
        // Note: Near expiry would require batch/expiry tracking which isn't in current schema
        // For now, we'll return empty array
        $nearExpiry = [];
        
        echo json_encode([
            'success' => true,
            'data' => [
                'low_stock' => $lowStock,
                'out_of_stock' => $outOfStock,
                'near_expiry' => $nearExpiry
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get inventory alerts: ' . $e->getMessage()]);
    }
}

/**
 * Get Recent Transactions
 */
function getRecentTransactions($db, $user) {
    try {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
        
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
        
        $stmt = $db->prepare($query);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = [
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
        }
        
        echo json_encode([
            'success' => true,
            'data' => $transactions
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get recent transactions: ' . $e->getMessage()]);
    }
}

/**
 * Get Period Status
 */
function getPeriodStatus($db) {
    try {
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
        
        $result = $db->query($query);
        $period = $result->fetch_assoc();
        
        if (!$period) {
            echo json_encode([
                'success' => true,
                'data' => null
            ]);
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
        
        $stmt = $db->prepare($stockTakingQuery);
        $stmt->bind_param('i', $period['id']);
        $stmt->execute();
        $stockTakingResult = $stmt->get_result()->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $period['id'],
                'period_name' => $period['period_name'],
                'status' => $period['status'],
                'start_date' => $period['start_date'],
                'end_date' => $period['end_date'],
                'days_running' => $daysRunning,
                'last_stock_taking' => $stockTakingResult['last_stock_taking']
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get period status: ' . $e->getMessage()]);
    }
}

/**
 * Get User Summary (for cashiers - their daily stats)
 */
function getUserSummary($db, $user) {
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
        
        $stmt = $db->prepare($salesQuery);
        $stmt->bind_param('iss', $user['user_id'], $todayStart, $todayEnd);
        $stmt->execute();
        $salesResult = $stmt->get_result()->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user['user_id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ],
                'today_stats' => [
                    'sales_revenue' => floatval($salesResult['revenue']),
                    'transaction_count' => intval($salesResult['transaction_count'])
                ]
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get user summary: ' . $e->getMessage()]);
    }
}
?>
