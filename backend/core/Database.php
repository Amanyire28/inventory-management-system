<?php
/**
 * Database Connection & Utilities
 * 
 * Provides secure database access with prepared statements
 * and error handling
 */

class Database {
    private static $instance;
    private $conn;
    
    private function __construct() {
        require_once __DIR__ . '/../config.php';
        
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($this->conn->connect_error) {
            throw new Exception('Database connection failed: ' . $this->conn->connect_error);
        }
        
        $this->conn->set_charset('utf8mb4');
    }
    
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Execute prepared statement with parameters
     */
    public function execute($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) $types .= 'i';
                elseif (is_float($param)) $types .= 'd';
                elseif (is_bool($param)) $types .= 'i';
                else $types .= 's';
            }
            
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        return $stmt;
    }
    
    /**
     * Fetch single row as associative array
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    /**
     * Fetch all rows as associative array
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Insert and return last insert ID
     */
    public function insert($table, $data) {
        $columns = array_keys($data);
        $values = array_values($data);
        
        $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . 
               implode(', ', array_fill(0, count($values), '?')) . ")";
        
        $stmt = $this->execute(str_replace('?', '%s', $sql), $values);
        
        return $this->conn->insert_id;
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->conn->begin_transaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->conn->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->conn->rollback();
    }
}
?>
