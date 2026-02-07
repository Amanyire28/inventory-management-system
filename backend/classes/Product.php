<?php
/**
 * TOPINV - Product Class
 * Handles product management and inventory
 */

class Product {
    private $conn;
    private $table = 'products';
    
    public $id;
    public $name;
    public $code;
    public $category;
    public $selling_price;
    public $cost_price;
    public $reorder_level;
    public $current_stock;
    public $active;
    public $created_at;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Create products table
     */
    public function createTable() {
        $query = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(50) UNIQUE NOT NULL,
            category VARCHAR(50),
            selling_price DECIMAL(10, 2) NOT NULL,
            cost_price DECIMAL(10, 2) NOT NULL,
            reorder_level INT DEFAULT 50,
            current_stock INT DEFAULT 0,
            active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY (code),
            KEY (active),
            KEY (category)
        )";
        
        return $this->conn->query($query);
    }
    
    /**
     * Get all products
     */
    public function getAll($active_only = true) {
        $query = "SELECT * FROM {$this->table}";
        
        if ($active_only) {
            $query .= " WHERE active = 1";
        }
        
        $query .= " ORDER BY name ASC";
        
        $result = $this->conn->query($query);
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get product by ID
     */
    public function getById($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = ?";
        
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
     * Get product by code
     */
    public function getByCode($code) {
        $query = "SELECT * FROM {$this->table} WHERE code = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $code);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Create product
     */
    public function create($name, $code, $selling_price, $cost_price, $reorder_level = 50, $category = null) {
        if ($this->codeExists($code)) {
            return false;
        }
        
        $query = "INSERT INTO {$this->table} 
                  (name, code, category, selling_price, cost_price, reorder_level, active)
                  VALUES (?, ?, ?, ?, ?, ?, 1)";
        
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('sssidi', $name, $code, $category, $selling_price, $cost_price, $reorder_level);
        
        return $stmt->execute();
    }
    
    /**
     * Update product
     */
    public function update($id, $name, $selling_price, $cost_price, $reorder_level = null, $category = null) {
        $query = "UPDATE {$this->table} 
                  SET name = ?, selling_price = ?, cost_price = ?";
        
        $params = [$name, $selling_price, $cost_price];
        $types = 'sdd';
        
        if ($reorder_level !== null) {
            $query .= ", reorder_level = ?";
            $params[] = $reorder_level;
            $types .= 'i';
        }
        
        if ($category !== null) {
            $query .= ", category = ?";
            $params[] = $category;
            $types .= 's';
        }
        
        $query .= " WHERE id = ?";
        $params[] = $id;
        $types .= 'i';
        
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param($types, ...$params);
        
        return $stmt->execute();
    }
    
    /**
     * Deactivate product
     */
    public function deactivate($id) {
        $query = "UPDATE {$this->table} SET active = 0 WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        
        return $stmt->execute();
    }
    
    /**
     * Activate product
     */
    public function activate($id) {
        $query = "UPDATE {$this->table} SET active = 1 WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        
        return $stmt->execute();
    }
    
    /**
     * Get low stock products
     */
    public function getLowStock() {
        $query = "SELECT * FROM {$this->table} 
                  WHERE active = 1 AND current_stock <= reorder_level
                  ORDER BY current_stock ASC";
        
        $result = $this->conn->query($query);
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get out of stock products
     */
    public function getOutOfStock() {
        $query = "SELECT * FROM {$this->table} 
                  WHERE active = 1 AND current_stock = 0
                  ORDER BY name ASC";
        
        $result = $this->conn->query($query);
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Check if code exists
     */
    private function codeExists($code) {
        $query = "SELECT id FROM {$this->table} WHERE code = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $code);
        $stmt->execute();
        
        return $stmt->get_result()->num_rows > 0;
    }
}
?>
