<?php
/**
 * TOPINV - Audit Logs API
 * 
 * Provides audit log viewing (admin only)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

// Only admins can view audit logs
if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin only.']);
    exit();
}

$db = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    getAuditLogs($db);
}

/**
 * Get audit logs with optional filters
 */
function getAuditLogs($db) {
    try {
        $periodId = isset($_GET['period_id']) ? intval($_GET['period_id']) : null;
        $entityType = isset($_GET['entity_type']) ? $_GET['entity_type'] : null;
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        
        $whereConditions = [];
        $params = [];
        $types = '';
        
        if ($entityType) {
            $whereConditions[] = "a.entity_type = ?";
            $params[] = $entityType;
            $types .= 's';
        }
        
        if ($userId) {
            $whereConditions[] = "a.user_id = ?";
            $params[] = $userId;
            $types .= 'i';
        }
        
        $whereClause = count($whereConditions) > 0 ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "SELECT 
            a.id,
            a.action,
            a.entity_type,
            a.entity_id,
            a.old_value,
            a.new_value,
            a.ip_address,
            a.timestamp,
            u.full_name as user_name,
            u.username as username
        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.id
        $whereClause
        ORDER BY a.timestamp DESC
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
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = [
                'id' => $row['id'],
                'action' => $row['action'],
                'entity_type' => $row['entity_type'],
                'entity_id' => $row['entity_id'],
                'old_value' => $row['old_value'],
                'new_value' => $row['new_value'],
                'ip_address' => $row['ip_address'],
                'timestamp' => $row['timestamp'],
                'user_name' => $row['user_name'] ?: $row['username']
            ];
        }
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM audit_logs a $whereClause";
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
                'logs' => $logs,
                'total' => intval($countResult['total']),
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get audit logs: ' . $e->getMessage()]);
    }
}
?>
