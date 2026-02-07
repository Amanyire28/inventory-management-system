<?php
/**
 * TOPINV - Transaction Class
 * Handles all transaction types (Sales, Purchases, Adjustments, Voids, Reversals)
 */

class Transaction {
    private $conn;
    private $table = 'transactions';
    
    const TYPE_SALE = 'SALE';
    const TYPE_PURCHASE = 'PURCHASE';
    const TYPE_ADJUSTMENT = 'ADJUSTMENT';
    const TYPE_VOID = 'VOID';
    const TYPE_REVERSAL = 'REVERSAL';
    
    public $id;
    public $transaction_id;
    public $type;
    public $relates_to;
    public $product_id;
    public $quantity;
    public $unit_price;
    public $total_amount;
    public $user_id;
    public $notes;
    public $timestamp_created;
    public $timestamp_locked;
    public $ip_address;
    public $session_id;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Create transactions table
     */
    public function createTable() {
        $query = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id INT PRIMARY KEY AUTO_INCREMENT,
            transaction_id VARCHAR(50) UNIQUE NOT NULL,
            type ENUM('SALE', 'PURCHASE', 'ADJUSTMENT', 'VOID', 'REVERSAL') NOT NULL,
            relates_to VARCHAR(50),
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            unit_price DECIMAL(10, 2),
            total_amount DECIMAL(10, 2),
            user_id INT NOT NULL,
            notes TEXT,
            timestamp_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            timestamp_locked TIMESTAMP NULL,
            ip_address VARCHAR(45),
            session_id VARCHAR(255),
            FOREIGN KEY (product_id) REFERENCES products(id),
            FOREIGN KEY (user_id) REFERENCES users(id),
            KEY (transaction_id),
            KEY (type),
            KEY (product_id),
            KEY (user_id),
            KEY (timestamp_created)
        )";
        
        return $this->conn->query($query);
    }
    
    /**
     * Record a sale
     */
    public function recordSale($product_id, $quantity, $unit_price, $user_id, $ip_address, $session_id) {
        $transaction_id = 'SAL-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $total_amount = $quantity * $unit_price;
        
        $query = "INSERT INTO {$this->table} 
                  (transaction_id, type, product_id, quantity, unit_price, total_amount, user_id, ip_address, session_id)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }
        
        $type = self::TYPE_SALE;
        $stmt->bind_param('ssiiddiss', $transaction_id, $type, $product_id, $quantity, $unit_price, $total_amount, $user_id, $ip_address, $session_id);
        
        if ($stmt->execute()) {
            return $transaction_id;
        }
        
        return false;
    }
    
    /**
     * Record a purchase
     */
    public function recordPurchase($product_id, $quantity, $unit_price, $user_id, $ip_address, $session_id, $notes = null) {
        $transaction_id = 'PUR-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $total_amount = $quantity * $unit_price;
        
        $query = "INSERT INTO {$this->table} 
                  (transaction_id, type, product_id, quantity, unit_price, total_amount, user_id, notes, ip_address, session_id)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }
        
        $type = self::TYPE_PURCHASE;
        $stmt->bind_param('ssiiddsiss', $transaction_id, $type, $product_id, $quantity, $unit_price, $total_amount, $user_id, $notes, $ip_address, $session_id);
        
        if ($stmt->execute()) {
            return $transaction_id;
        }
        
        return false;
    }
    
    /**
     * Record a void (negates a sale)
     */
    public function recordVoid($relates_to, $product_id, $quantity, $unit_price, $user_id, $ip_address, $session_id, $notes = null) {
        $transaction_id = 'VOID-' . $relates_to;
        
        $query = "INSERT INTO {$this->table} 
                  (transaction_id, type, relates_to, product_id, quantity, unit_price, user_id, notes, ip_address, session_id)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }
        
        $type = self::TYPE_VOID;
        $stmt->bind_param('sssiiidiss', $transaction_id, $type, $relates_to, $product_id, $quantity, $unit_price, $user_id, $notes, $ip_address, $session_id);
        
        if ($stmt->execute()) {
            return $transaction_id;
        }
        
        return false;
    }
    
    /**
     * Record a reversal (for corrections)
     */
    public function recordReversal($relates_to, $product_id, $quantity, $unit_price, $user_id, $ip_address, $session_id, $reason, $notes = null) {
        $transaction_id = 'REV-' . date('Ymd-His');
        
        $full_notes = $reason . (($notes) ? ' | ' . $notes : '');
        
        $query = "INSERT INTO {$this->table} 
                  (transaction_id, type, relates_to, product_id, quantity, unit_price, user_id, notes, ip_address, session_id)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }
        
        $type = self::TYPE_REVERSAL;
        $stmt->bind_param('sssiiidiss', $transaction_id, $type, $relates_to, $product_id, $quantity, $unit_price, $user_id, $full_notes, $ip_address, $session_id);
        
        if ($stmt->execute()) {
            return $transaction_id;
        }
        
        return false;
    }
    
    /**
     * Record an adjustment (from stock taking)
     */
    public function recordAdjustment($product_id, $quantity_change, $user_id, $ip_address, $session_id, $reason) {
        $transaction_id = 'ADJ-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $query = "INSERT INTO {$this->table} 
                  (transaction_id, type, product_id, quantity, user_id, notes, ip_address, session_id)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }
        
        $type = self::TYPE_ADJUSTMENT;
        $stmt->bind_param('sssidiss', $transaction_id, $type, $product_id, $quantity_change, $user_id, $reason, $ip_address, $session_id);
        
        if ($stmt->execute()) {
            return $transaction_id;
        }
        
        return false;
    }
    
    /**
     * Get all transactions (with optional filters)
     */
    public function getAll($filters = []) {
        $query = "SELECT * FROM {$this->table} WHERE 1";
        
        if (isset($filters['type'])) {
            $query .= " AND type = '" . $this->conn->real_escape_string($filters['type']) . "'";
        }
        
        if (isset($filters['product_id'])) {
            $query .= " AND product_id = " . intval($filters['product_id']);
        }
        
        if (isset($filters['user_id'])) {
            $query .= " AND user_id = " . intval($filters['user_id']);
        }
        
        if (isset($filters['date_from'])) {
            $query .= " AND DATE(timestamp_created) >= '" . $this->conn->real_escape_string($filters['date_from']) . "'";
        }
        
        if (isset($filters['date_to'])) {
            $query .= " AND DATE(timestamp_created) <= '" . $this->conn->real_escape_string($filters['date_to']) . "'";
        }
        
        $query .= " ORDER BY timestamp_created DESC";
        
        $result = $this->conn->query($query);
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get transaction by ID
     */
    public function getById($transaction_id) {
        $query = "SELECT * FROM {$this->table} WHERE transaction_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $transaction_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Lock transaction (when period closes)
     */
    public function lockTransaction($transaction_id) {
        $query = "UPDATE {$this->table} SET timestamp_locked = NOW() WHERE transaction_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $transaction_id);
        
        return $stmt->execute();
    }
}
?>
