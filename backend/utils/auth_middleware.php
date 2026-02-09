<?php
/**
 * Authentication Middleware
 * 
 * Provides authentication verification for API endpoints
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';

/**
 * Verify authentication from request headers
 * Returns user data if valid, false otherwise
 */
function verifyAuth() {
    // Get authorization header
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : 
                  (isset($headers['authorization']) ? $headers['authorization'] : null);
    
    if (!$authHeader) {
        return false;
    }
    
    // Extract token (format: "Bearer <token>")
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
    } else {
        return false;
    }
    
    // Decode and validate token
    $payload = Auth::decode($token);
    
    if (!$payload) {
        return false;
    }
    
    // Return user data from token
    return [
        'user_id' => $payload['user_id'],
        'username' => $payload['username'],
        'role' => $payload['role'],
        'full_name' => isset($payload['full_name']) ? $payload['full_name'] : null
    ];
}

/**
 * Require specific role(s) for access
 */
function requireRole($user, $allowedRoles) {
    if (!$user || !isset($user['role'])) {
        return false;
    }
    
    if (is_array($allowedRoles)) {
        return in_array($user['role'], $allowedRoles);
    }
    
    return $user['role'] === $allowedRoles;
}
