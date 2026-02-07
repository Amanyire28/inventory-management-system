<?php
/**
 * TOPINV - Sales API
 * 
 * Provides sales history and management
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
$requestUri = $_SERVER['REQUEST_URI'];

// Parse the request
if (strpos($requestUri, '/history') !== false) {
    getSalesHistory($db, $user);
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    getSales($db, $user);
}

/**
 * Get sales history (grouped by transaction date/session)
 */
function getSalesHistory($db, $user) {
    try {
        $periodId = isset($_GET['period_id']) ? intval($_GET['period_id']) : null;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        
        $whereConditions = ["t.type = 'SALE'", "t.status = 'COMMITTED'"];
        $params = [];
        $types = '';
        
        if ($periodId) {
            $whereConditions[] = "t.period_id = ?";
            $params[] = $periodId;
            $types .= 'i';
        }
        
        // Non-admin can only see their own sales
        if ($user['role'] !== 'admin') {
            $whereConditions[] = "t.created_by = ?";
            $params[] = $user['id'];
            $types .= 'i';
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Group sales by transaction date to get unique sales transactions
        $query = "SELECT 
            t.id,
            t.transaction_date,
            t.total_amount,
            COUNT(*) as items,
            GROUP_CONCAT(p.name SEPARATOR ', ') as product_names
        FROM transactions t
        JOIN products p ON t.product_id = p.id
        WHERE $whereClause
        GROUP BY t.transaction_date, t.id
        ORDER BY t.transaction_date DESC
        LIMIT ?";
        
        $stmt = $db->prepare($query);
        
        // Add limit to params
        $params[] = $limit;
        $types .= 'i';
        
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $sales = [];
        while ($row = $result->fetch_assoc()) {
            $sales[] = [
                'id' => $row['id'],
                'transaction_date' => $row['transaction_date'],
                'total_amount' => floatval($row['total_amount']),
                'items' => intval($row['items']),
                'products' => $row['product_names']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'sales' => $sales
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get sales history: ' . $e->getMessage()]);
    }
}

/**
 * Get detailed sales transactions
 */
function getSales($db, $user) {
    try {
        $periodId = isset($_GET['period_id']) ? intval($_GET['period_id']) : null;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        
        $whereConditions = ["t.type = 'SALE'", "t.status = 'COMMITTED'"];
        $params = [];
        $types = '';
        
        if ($periodId) {
            $whereConditions[] = "t.period_id = ?";
            $params[] = $periodId;
            $types .= 'i';
        }
        
        // Non-admin can only see their own sales
        if ($user['role'] !== 'admin') {
            $whereConditions[] = "t.created_by = ?";
            $params[] = $user['id'];
            $types .= 'i';
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $query = "SELECT 
            t.id,
            t.quantity,
            t.unit_price,
            t.total_amount,
            t.transaction_date,
            p.name as product_name,
            p.code as product_code,
            u.full_name as cashier_name,
            u.username as cashier_username
        FROM transactions t
        JOIN products p ON t.product_id = p.id
        JOIN users u ON t.created_by = u.id
        WHERE $whereClause
        ORDER BY t.transaction_date DESC
        LIMIT ? OFFSET ?";
        
        $stmt = $db->prepare($query);
        
        // Add limit and offset to params
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $sales = [];
        while ($row = $result->fetch_assoc()) {
            $sales[] = [
                'id' => $row['id'],
                'quantity' => intval($row['quantity']),
                'unit_price' => floatval($row['unit_price']),
                'total_amount' => floatval($row['total_amount']),
                'transaction_date' => $row['transaction_date'],
                'product_name' => $row['product_name'],
                'product_code' => $row['product_code'],
                'cashier' => $row['cashier_name'] ?: $row['cashier_username']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'sales' => $sales,
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get sales: ' . $e->getMessage()]);
    }
}
?>
