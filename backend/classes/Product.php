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
    public $category;
    public $selling_price;
    public $cost_price;
    public $opening_stock;
    public $current_stock;
    public $reorder_level;
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
            category VARCHAR(50),
            selling_price DECIMAL(10, 2) NOT NULL,
            cost_price DECIMAL(10, 2) NOT NULL,
            opening_stock INT NOT NULL DEFAULT 0,
            current_stock INT DEFAULT 0,
            reorder_level INT DEFAULT 50,
            active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
     * Create product
     */
    public function create($name, $selling_price, $cost_price, $opening_stock = 0, $reorder_level = 50, $category = null) {
        $query = "INSERT INTO {$this->table} 
                  (name, category, selling_price, cost_price, opening_stock, current_stock, reorder_level, active)
                  VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
        
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('ssdddii', $name, $category, $selling_price, $cost_price, $opening_stock, $opening_stock, $reorder_level);
        
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
     * Search products by name
     */
    public function search($searchTerm, $limit = 5, $active_only = true) {
        $searchTerm = '%' . $searchTerm . '%';
        
        $query = "SELECT * FROM {$this->table} 
                  WHERE name LIKE ?";
        
        if ($active_only) {
            $query .= " AND active = 1";
        }
        
        $query .= " ORDER BY name ASC LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('si', $searchTerm, $limit);
        $stmt->execute();
        
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>
