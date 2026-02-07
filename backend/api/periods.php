<?php
/**
 * TOPINV - Periods API
 * 
 * Manages accounting periods
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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
if (strpos($requestUri, '/current') !== false) {
    getCurrentPeriod($db);
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    getAllPeriods($db);
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    createPeriod($db, $user);
} else if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    closePeriod($db, $user);
}

/**
 * Get current open period
 */
function getCurrentPeriod($db) {
    try {
        $query = "SELECT 
            id,
            period_name,
            status,
            start_date,
            end_date,
            created_at
        FROM periods 
        WHERE status = 'OPEN'
        ORDER BY start_date DESC
        LIMIT 1";
        
        $result = $db->query($query);
        $period = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'data' => $period
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get current period: ' . $e->getMessage()]);
    }
}

/**
 * Get all periods
 */
function getAllPeriods($db) {
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
        ORDER BY start_date DESC";
        
        $result = $db->query($query);
        
        $periods = [];
        while ($row = $result->fetch_assoc()) {
            $periods[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'periods' => $periods
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get periods: ' . $e->getMessage()]);
    }
}

/**
 * Create a new period
 */
function createPeriod($db, $user) {
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Only admins can create periods']);
        return;
    }
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $periodName = $data['period_name'] ?? null;
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;
        
        if (!$periodName || !$startDate || !$endDate) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            return;
        }
        
        // Check if there's already an open period
        $checkQuery = "SELECT id FROM periods WHERE status = 'OPEN' LIMIT 1";
        $result = $db->query($checkQuery);
        
        if ($result->num_rows > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot create new period while another period is still open']);
            return;
        }
        
        $query = "INSERT INTO periods (period_name, status, start_date, end_date) 
                  VALUES (?, 'OPEN', ?, ?)";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param('sss', $periodName, $startDate, $endDate);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Period created successfully',
            'data' => [
                'id' => $db->insert_id
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create period: ' . $e->getMessage()]);
    }
}

/**
 * Close a period
 */
function closePeriod($db, $user) {
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Only admins can close periods']);
        return;
    }
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $periodId = $data['period_id'] ?? null;
        
        if (!$periodId) {
            http_response_code(400);
            echo json_encode(['error' => 'Period ID is required']);
            return;
        }
        
        $query = "UPDATE periods 
                  SET status = 'CLOSED', 
                      closed_at = CURRENT_TIMESTAMP,
                      closed_by = ?
                  WHERE id = ? AND status = 'OPEN'";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param('ii', $user['id'], $periodId);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Period not found or already closed']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Period closed successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to close period: ' . $e->getMessage()]);
    }
}
?>
