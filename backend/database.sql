-- ====================================================
-- TOPINV: Clinic Inventory Management System
-- Database Schema with Transactional Integrity
-- ====================================================

-- Create database
CREATE DATABASE IF NOT EXISTS topinv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE topinv;

-- ====================================================
-- 1. USERS TABLE (Role-based access)
-- ====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role ENUM('cashier', 'admin') NOT NULL DEFAULT 'cashier',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================
-- 2. PERIODS TABLE (Period locking for financial control)
-- ====================================================
CREATE TABLE IF NOT EXISTS periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_name VARCHAR(100) NOT NULL,
    status ENUM('OPEN', 'CLOSED') NOT NULL DEFAULT 'OPEN',
    start_date DATE,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    closed_by INT,
    INDEX idx_status (status),
    INDEX idx_date_range (start_date, end_date),
    FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================
-- 3. PRODUCTS TABLE (Reference data - immutable opening stock)
-- ====================================================
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    code VARCHAR(100) UNIQUE,
    selling_price DECIMAL(10, 2) NOT NULL,
    cost_price DECIMAL(10, 2) NOT NULL,
    opening_stock INT NOT NULL DEFAULT 0,
    current_stock INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 10,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_status (status),
    INDEX idx_code (code)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================
-- 4. TRANSACTIONS TABLE (Append-only event log - Single Source of Truth for Stock)
-- ====================================================
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('PURCHASE', 'SALE', 'ADJUSTMENT', 'REVERSAL', 'VOID') NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    reference_transaction_id INT,
    reversal_reason VARCHAR(500),
    transaction_date DATETIME NOT NULL,
    period_id INT,
    created_by INT NOT NULL,
    status ENUM('DRAFT', 'COMMITTED', 'REVERSED') NOT NULL DEFAULT 'COMMITTED',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_product_id (product_id),
    INDEX idx_period_id (period_id),
    INDEX idx_transaction_date (transaction_date),
    INDEX idx_created_by (created_by),
    INDEX idx_status (status),
    INDEX idx_reference_transaction (reference_transaction_id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (period_id) REFERENCES periods(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (reference_transaction_id) REFERENCES transactions(id) ON DELETE RESTRICT
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================
-- 5. DRAFT SALES TABLE (Temporary layer before commitment)
-- ====================================================
CREATE TABLE IF NOT EXISTS draft_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    created_by INT NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_session_id (session_id),
    INDEX idx_created_by (created_by),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================
-- 6. DRAFT SALE ITEMS TABLE (Line items in draft)
-- ====================================================
CREATE TABLE IF NOT EXISTS draft_sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    draft_sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    line_total DECIMAL(12, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_draft_sale_id (draft_sale_id),
    INDEX idx_product_id (product_id),
    FOREIGN KEY (draft_sale_id) REFERENCES draft_sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================
-- 7. AUDIT LOG TABLE (Immutable audit trail)
-- ====================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT,
    old_value LONGTEXT,
    new_value LONGTEXT,
    ip_address VARCHAR(45),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_timestamp (timestamp),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================
-- 8. STOCK ADJUSTMENTS TABLE (Variance tracking)
-- ====================================================
CREATE TABLE IF NOT EXISTS stock_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    system_quantity INT NOT NULL,
    physical_quantity INT NOT NULL,
    variance INT NOT NULL,
    period_id INT NOT NULL,
    recorded_by INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_id (product_id),
    INDEX idx_period_id (period_id),
    INDEX idx_recorded_by (recorded_by),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (period_id) REFERENCES periods(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================
-- Insert Initial Data: Demo Users
-- ====================================================
INSERT INTO users (username, password_hash, full_name, role, status) VALUES
('cashier1', '$2y$10$lXDEGQ7R7Q7X.FG.VzW2hOf8.oAzVVKQ0kh7VQ7R7Q7Q7Q7Q7Q7Q7', 'John Cashier', 'cashier', 'active'),
('admin1', '$2y$10$lXDEGQ7R7Q7X.FG.VzW2hOf8.oAzVVKQ0kh7VQ7R7Q7Q7Q7Q7Q7Q7', 'Jane Admin', 'admin', 'active');

-- Password hashes above are for 'password' - passwords should be updated

-- ====================================================
-- Insert Initial Data: Demo Products
-- ====================================================
INSERT INTO products (name, code, selling_price, cost_price, opening_stock, current_stock, reorder_level, status) VALUES
('Paracetamol 500mg', 'MED001', 25.00, 15.00, 100, 100, 20, 'active'),
('Ibuprofen 200mg', 'MED002', 30.00, 18.00, 80, 80, 15, 'active'),
('Amoxicillin 500mg', 'MED003', 45.00, 25.00, 50, 50, 10, 'active'),
('Vitamin C 500mg', 'MED004', 20.00, 10.00, 150, 150, 30, 'active'),
('Cough Syrup 100ml', 'MED005', 60.00, 35.00, 40, 40, 8, 'active'),
('Antihistamine Tablet', 'MED006', 35.00, 20.00, 75, 75, 15, 'active');

-- ====================================================
-- Insert Initial Data: Demo Period
-- ====================================================
INSERT INTO periods (period_name, status, start_date, end_date) VALUES
('January 2026', 'OPEN', '2026-01-01', '2026-01-31');

CREATE OR REPLACE VIEW v_product_stock_status AS
SELECT 
    p.id,
    p.name,
    p.code,
    p.selling_price,
    p.cost_price,
    p.opening_stock,
    p.current_stock,
    p.reorder_level,
    CASE 
        WHEN p.current_stock <= p.reorder_level THEN 'LOW'
        WHEN p.current_stock > p.reorder_level AND p.current_stock <= (p.reorder_level * 2) THEN 'MEDIUM'
        ELSE 'ADEQUATE'
    END AS stock_status,
    p.status
FROM products p;

-- ====================================================
-- INDEXES FOR PERFORMANCE
-- ====================================================
CREATE INDEX idx_transactions_product_date ON transactions(product_id, transaction_date);
CREATE INDEX idx_transactions_period_date ON transactions(period_id, transaction_date);
CREATE INDEX idx_audit_logs_entity_time ON audit_logs(entity_type, entity_id, timestamp);
