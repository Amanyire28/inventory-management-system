<?php
/**
 * TOPINV - User Class
 * Handles user authentication and authorization
 */

class User {
    private $conn;
    private $table = 'users';
    
    public $id;
    public $username;
    public $email;
    public $name;
    public $role;
    public $password;
    public $active;
    public $created_at;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Create user table (run once during setup)
     */
    public function createTable() {
        $query = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE,
            name VARCHAR(100) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'cashier') NOT NULL,
            active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY (role),
            KEY (active)
        )";
        
        return $this->conn->query($query);
    }
    
    /**
     * Register new user
     */
    public function register($username, $email, $name, $password, $role = 'cashier') {
        // Check if user exists
        if ($this->userExists($username)) {
            return false;
        }
        
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        $query = "INSERT INTO {$this->table} 
                  (username, email, name, password_hash, role, active)
                  VALUES (?, ?, ?, ?, ?, 1)";
        
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('sssss', $username, $email, $name, $password_hash, $role);
        
        return $stmt->execute();
    }
    
    /**
     * Authenticate user
     */
    public function authenticate($username, $password) {
        $query = "SELECT id, username, email, name, role, password_hash, active 
                  FROM {$this->table} 
                  WHERE username = ? AND active = 1";
        
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('s', $username);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        $row = $result->fetch_assoc();
        
        if (password_verify($password, $row['password_hash'])) {
            $this->id = $row['id'];
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->name = $row['name'];
            $this->role = $row['role'];
            $this->active = $row['active'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($id) {
        $query = "SELECT id, username, email, name, role, active, created_at 
                  FROM {$this->table} 
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Get all users
     */
    public function getAllUsers() {
        $query = "SELECT id, username, email, name, role, active, created_at 
                  FROM {$this->table} 
                  ORDER BY created_at DESC";
        
        $result = $this->conn->query($query);
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Check if user exists
     */
    private function userExists($username) {
        $query = "SELECT id FROM {$this->table} WHERE username = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $username);
        $stmt->execute();
        
        return $stmt->get_result()->num_rows > 0;
    }
    
    /**
     * Update user
     */
    public function updateUser($id, $name, $email, $role) {
        $query = "UPDATE {$this->table} 
                  SET name = ?, email = ?, role = ? 
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('sssi', $name, $email, $role, $id);
        
        return $stmt->execute();
    }
    
    /**
     * Deactivate user
     */
    public function deactivateUser($id) {
        $query = "UPDATE {$this->table} SET active = 0 WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        
        return $stmt->execute();
    }
}
?>
