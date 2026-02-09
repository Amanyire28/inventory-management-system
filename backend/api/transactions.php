<?php
/**
 * TOPINV - Transactions API
 * 
 * Provides transaction history and management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/auth_middleware.php';

// Verify authentication
$user = verifyAuth();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$db = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    getTransactions($db, $user);
}

/**
 * Get transactions with optional filters
 */
function getTransactions($db, $user) {
    try {
        $periodId = isset($_GET['period_id']) ? intval($_GET['period_id']) : null;
        $type = isset($_GET['type']) ? $_GET['type'] : null;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        
        $whereConditions = ["t.status = 'COMMITTED'"];
        $params = [];
        $types = '';
        
        if ($periodId) {
            $whereConditions[] = "t.period_id = ?";
            $params[] = $periodId;
            $types .= 'i';
        }
        
        if ($type) {
            $whereConditions[] = "t.type = ?";
            $params[] = $type;
            $types .= 's';
        }
        
        // Non-admin can only see their own transactions
        if ($user['role'] !== 'admin') {
            $whereConditions[] = "t.created_by = ?";
            $params[] = $user['id'];
            $types .= 'i';
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $query = "SELECT 
            t.id,
            t.type,
            t.quantity,
            t.unit_price,
            t.total_amount,
            t.transaction_date,
            t.status,
            t.reversal_reason,
            p.name as product_name,
            p.code as product_code,
            u.full_name as created_by_name,
            u.username as created_by_username,
            per.period_name
        FROM transactions t
        JOIN products p ON t.product_id = p.id
        JOIN users u ON t.created_by = u.id
        LEFT JOIN periods per ON t.period_id = per.id
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
                'created_by' => $row['created_by_name'] ?: $row['created_by_username'],
                'period_name' => $row['period_name'],
                'reversal_reason' => $row['reversal_reason']
            ];
        }
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total 
                       FROM transactions t 
                       WHERE $whereClause";
        
        $countStmt = $db->prepare($countQuery);
        
        if ($types) {
            // Remove last two params (limit and offset) for count query
            $countTypes = substr($types, 0, -2);
            $countParams = array_slice($params, 0, -2);
            if ($countTypes) {
                $countStmt->bind_param($countTypes, ...$countParams);
            }
        }
        
        $countStmt->execute();
        $countResult = $countStmt->get_result()->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'transactions' => $transactions,
                'total' => intval($countResult['total']),
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get transactions: ' . $e->getMessage()]);
    }
}
?>
