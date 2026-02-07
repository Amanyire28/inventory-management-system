<?php
/**
 * Standardized API Response Handler
 * 
 * Ensures consistent response format across all endpoints
 */

class Response {
    
    /**
     * Send success response
     */
    public static function success($data = [], $message = 'Success', $statusCode = 200) {
        // Clean any output buffer to ensure only JSON is sent
        if (ob_get_level()) ob_clean();
        
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Send error response
     */
    public static function error($message = 'Error', $statusCode = 400, $errors = null) {
        // Clean any output buffer to ensure only JSON is sent
        if (ob_get_level()) ob_clean();
        
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($errors) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * Send validation error response
     */
    public static function validation($errors) {
        self::error('Validation failed', 422, $errors);
    }
    
    /**
     * Send not found response
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }
    
    /**
     * Send unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }
    
    /**
     * Send forbidden response
     */
    public static function forbidden($message = 'Access denied') {
        self::error($message, 403);
    }
    
    /**
     * Send conflict response
     */
    public static function conflict($message = 'Resource conflict') {
        self::error($message, 409);
    }
}

/**
 * Audit Logging - Immutable audit trail
 * 
 * Records all state-changing actions with user, timestamp, and changes
 */

class AuditLog {
    
    public static function log($action, $entity_type, $entity_id, $old_value = null, $new_value = null) {
        try {
            $user = Auth::getCurrentUser();
            $user_id = $user ? $user['user_id'] : null;
            
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            
            $stmt = $conn->prepare(
                "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_value, new_value, ip_address) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            
            $old_value_json = $old_value ? json_encode($old_value) : null;
            $new_value_json = $new_value ? json_encode($new_value) : null;
            
            $stmt->bind_param('issiiss', $user_id, $action, $entity_type, $entity_id, $old_value_json, $new_value_json, $ip_address);
            $stmt->execute();
            
            return true;
        } catch (Exception $e) {
            error_log('Audit log failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get audit logs for entity
     */
    public static function getEntityLogs($entity_type, $entity_id, $limit = 100) {
        $db = Database::getInstance();
        
        return $db->fetchAll(
            "SELECT * FROM audit_logs 
             WHERE entity_type = ? AND entity_id = ? 
             ORDER BY timestamp DESC 
             LIMIT ?",
            [$entity_type, $entity_id, $limit]
        );
    }
}
?>
