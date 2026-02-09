<?php
/**
 * User Management Service
 * 
 * Handles user CRUD operations
 * Only admins can manage users
 */

class UserService {
    
    /**
     * Get all users
     */
    public static function getAllUsers($status_filter = 'all') {
        Auth::requireRole(['admin']);
        
        $db = Database::getInstance();
        
        $sql = "SELECT id, username, full_name, role, status, created_at 
                FROM users WHERE 1=1";
        
        $params = [];
        
        if ($status_filter !== 'all') {
            $sql .= " AND status = ?";
            $params[] = $status_filter;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        return $db->fetchAll($sql, $params);
    }
    
    /**
     * Get single user by ID
     */
    public static function getUser($user_id) {
        Auth::requireRole(['admin']);
        
        $db = Database::getInstance();
        
        return $db->fetch(
            "SELECT id, username, full_name, role, status, created_at 
             FROM users WHERE id = ?",
            [$user_id]
        );
    }
    
    /**
     * Create new user
     */
    public static function createUser($username, $full_name, $password, $role) {
        Auth::requireRole(['admin']);
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Validate role
        if (!in_array($role, ['admin', 'cashier'])) {
            throw new Exception("Invalid role. Must be 'admin' or 'cashier'");
        }
        
        // Validate username
        if (empty($username) || strlen($username) < 3) {
            throw new Exception("Username must be at least 3 characters");
        }
        
        // Validate password
        if (empty($password) || strlen($password) < 6) {
            throw new Exception("Password must be at least 6 characters");
        }
        
        // Check if username already exists
        $existing = $db->fetch("SELECT id FROM users WHERE username = ?", [$username]);
        if ($existing) {
            throw new Exception("Username already exists");
        }
        
        $db->beginTransaction();
        
        try {
            // For demo purposes, we're storing plain password
            // In production, use password_hash()
            $stmt = $conn->prepare(
                "INSERT INTO users (username, full_name, password_hash, role, status) 
                 VALUES (?, ?, ?, ?, 'active')"
            );
            
            $stmt->bind_param('ssss', $username, $full_name, $password, $role);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create user: " . $stmt->error);
            }
            
            $user_id = $conn->insert_id;
            
            // Log the action
            AuditLog::log('CREATE_USER', 'users', $user_id, null, [
                'username' => $username,
                'role' => $role
            ]);
            
            $db->commit();
            
            return [
                'id' => $user_id,
                'username' => $username,
                'full_name' => $full_name,
                'role' => $role,
                'status' => 'active'
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Update user
     */
    public static function updateUser($user_id, $full_name, $role, $status) {
        Auth::requireRole(['admin']);
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        $current_user = Auth::getCurrentUser();
        
        // Prevent admin from deactivating themselves
        if ($user_id == $current_user['user_id'] && $status !== 'active') {
            throw new Exception("You cannot deactivate your own account");
        }
        
        // Validate role
        if (!in_array($role, ['admin', 'cashier'])) {
            throw new Exception("Invalid role. Must be 'admin' or 'cashier'");
        }
        
        // Validate status
        if (!in_array($status, ['active', 'inactive'])) {
            throw new Exception("Invalid status. Must be 'active' or 'inactive'");
        }
        
        // Check if user exists
        $user = $db->fetch("SELECT id, username, role, status FROM users WHERE id = ?", [$user_id]);
        if (!$user) {
            throw new Exception("User not found");
        }
        
        $db->beginTransaction();
        
        try {
            $stmt = $conn->prepare(
                "UPDATE users 
                 SET full_name = ?, role = ?, status = ? 
                 WHERE id = ?"
            );
            
            $stmt->bind_param('sssi', $full_name, $role, $status, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update user: " . $stmt->error);
            }
            
            // Log the action
            AuditLog::log('UPDATE_USER', 'users', $user_id, null, [
                'old_role' => $user['role'],
                'new_role' => $role,
                'old_status' => $user['status'],
                'new_status' => $status
            ]);
            
            $db->commit();
            
            return self::getUser($user_id);
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Reset user password
     */
    public static function resetPassword($user_id, $new_password) {
        Auth::requireRole(['admin']);
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Validate password
        if (empty($new_password) || strlen($new_password) < 6) {
            throw new Exception("Password must be at least 6 characters");
        }
        
        // Check if user exists
        $user = $db->fetch("SELECT id, username FROM users WHERE id = ?", [$user_id]);
        if (!$user) {
            throw new Exception("User not found");
        }
        
        $db->beginTransaction();
        
        try {
            // For demo purposes, we're storing plain password
            // In production, use password_hash()
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param('si', $new_password, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to reset password: " . $stmt->error);
            }
            
            // Log the action
            AuditLog::log('RESET_PASSWORD', 'users', $user_id, null, [
                'username' => $user['username']
            ]);
            
            $db->commit();
            
            return ['success' => true, 'message' => 'Password reset successfully'];
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Delete/Deactivate user (soft delete)
     */
    public static function deleteUser($user_id) {
        Auth::requireRole(['admin']);
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        $current_user = Auth::getCurrentUser();
        
        // Prevent admin from deleting themselves
        if ($user_id == $current_user['user_id']) {
            throw new Exception("You cannot delete your own account");
        }
        
        // Check if user exists
        $user = $db->fetch("SELECT id, username FROM users WHERE id = ?", [$user_id]);
        if (!$user) {
            throw new Exception("User not found");
        }
        
        $db->beginTransaction();
        
        try {
            // Soft delete - set status to inactive
            $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete user: " . $stmt->error);
            }
            
            // Log the action
            AuditLog::log('DELETE_USER', 'users', $user_id, null, [
                'username' => $user['username']
            ]);
            
            $db->commit();
            
            return ['success' => true, 'message' => 'User deleted successfully'];
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
}
?>
