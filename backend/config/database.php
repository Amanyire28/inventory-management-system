<?php
/**
 * TOPINV - Database Configuration
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'topinv');

// Try to connect
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8");
    
} catch (Exception $e) {
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}

// Global settings
define('APP_NAME', 'TOPINV');
define('APP_VERSION', '1.0.0');
define('JWT_SECRET', 'topinv_secret_key_change_in_production');
?>
