<?php
/**
 * Authentication & JWT Handler
 * 
 * Manages user authentication and JWT token generation/validation
 * Enforces role-based access control
 */

class Auth {
    private static $secret = 'topinv-secret-key-change-in-production';
    private static $algorithm = 'HS256';
    
    /**
     * Authenticate user with username and password
     * Returns JWT token and user data on success
     */
    public static function login($username, $password) {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT id, username, password_hash, full_name, role, status FROM users WHERE username = ? AND status = 'active'");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Verify password using password_verify
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        $token = self::generateToken($user);
        
        return [
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ]
        ];
    }
    
    /**
     * Generate JWT token
     */
    private static function generateToken($user) {
        $now = time();
        $expire = $now + (24 * 60 * 60); // 24 hours
        
        $payload = [
            'iat' => $now,
            'exp' => $expire,
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ];
        
        return self::encode($payload);
    }
    
    /**
     * Encode JWT token (simple base64 implementation for demo)
     */
    private static function encode($payload) {
        $header = base64_encode(json_encode(['alg' => self::$algorithm, 'typ' => 'JWT']));
        $payload = base64_encode(json_encode($payload));
        $signature = base64_encode(hash_hmac('sha256', "$header.$payload", self::$secret, true));
        
        return "$header.$payload.$signature";
    }
    
    /**
     * Decode and validate JWT token
     */
    public static function decode($token) {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }
            
            $header = json_decode(base64_decode($parts[0]), true);
            $payload = json_decode(base64_decode($parts[1]), true);
            $signature = $parts[2];
            
            // Verify signature
            $valid_signature = base64_encode(hash_hmac('sha256', "{$parts[0]}.{$parts[1]}", self::$secret, true));
            
            if ($signature !== $valid_signature) {
                return null;
            }
            
            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return null;
            }
            
            return $payload;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get current user from request header
     */
    public static function getCurrentUser() {
        $headers = getallheaders();
        
        if (!isset($headers['Authorization'])) {
            return null;
        }
        
        $auth_header = $headers['Authorization'];
        if (!preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return null;
        }
        
        $token = $matches[1];
        return self::decode($token);
    }
    
    /**
     * Check if user has required role
     */
    public static function hasRole($required_roles) {
        $user = self::getCurrentUser();
        
        if (!$user) {
            return false;
        }
        
        if (is_string($required_roles)) {
            $required_roles = [$required_roles];
        }
        
        return in_array($user['role'], $required_roles);
    }
    
    /**
     * Require authentication and specific role
     */
    public static function requireRole($required_roles) {
        if (!self::hasRole($required_roles)) {
            Response::error('Unauthorized', 401);
        }
    }
}
?>
