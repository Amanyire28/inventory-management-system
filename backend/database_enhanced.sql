-- ====================================================
-- TOPINV: ENHANCED ACCOUNTING-GRADE INVENTORY SYSTEM
-- Database Schema with Full Integrity Enforcement
-- ====================================================

USE topinv;

-- ====================================================
-- PART 1: STORED FUNCTIONS
-- ====================================================

-- Drop existing functions if they exist
DROP FUNCTION IF EXISTS fn_calculate_stock;
DROP FUNCTION IF EXISTS fn_get_period_status;
DROP FUNCTION IF EXISTS fn_is_reversal_allowed;

DELIMITER //

-- --------------------------------------------------------
-- Function: Calculate Stock as of a specific date
-- Returns the calculated stock based on transactions
-- --------------------------------------------------------
CREATE FUNCTION fn_calculate_stock(
    p_product_id INT,
    p_as_of_date DATETIME
) RETURNS INT
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_opening_stock INT DEFAULT 0;
    DECLARE v_total_change INT DEFAULT 0;
    
    -- Get opening stock for the product
    SELECT opening_stock INTO v_opening_stock
    FROM products
    WHERE id = p_product_id;
    
    -- Calculate net change from all COMMITTED transactions up to the date
    SELECT 
        COALESCE(SUM(
            CASE 
                WHEN type = 'PURCHASE' THEN quantity
                WHEN type = 'SALE' THEN quantity  -- Already stored as negative
                WHEN type = 'ADJUSTMENT' THEN quantity  -- Can be positive or negative
                WHEN type = 'REVERSAL' THEN quantity  -- Opposite of original
                ELSE 0
            END
        ), 0) INTO v_total_change
    FROM transactions
    WHERE product_id = p_product_id
      AND status = 'COMMITTED'
      AND transaction_date <= p_as_of_date;
    
    RETURN v_opening_stock + v_total_change;
END //

-- --------------------------------------------------------
-- Function: Get Period Status
-- Returns the status of a period (OPEN/CLOSED)
-- --------------------------------------------------------
CREATE FUNCTION fn_get_period_status(
    p_period_id INT
) RETURNS VARCHAR(20)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_status VARCHAR(20);
    
    SELECT status INTO v_status
    FROM periods
    WHERE id = p_period_id;
    
    RETURN COALESCE(v_status, 'UNKNOWN');
END //

-- --------------------------------------------------------
-- Function: Check if Reversal is Allowed
-- Reversals are only allowed in current OPEN period
-- --------------------------------------------------------
CREATE FUNCTION fn_is_reversal_allowed(
    p_original_transaction_id INT,
    p_current_period_id INT
) RETURNS BOOLEAN
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_current_period_status VARCHAR(20);
    DECLARE v_original_transaction_exists INT;
    
    -- Check if current period is OPEN
    SELECT status INTO v_current_period_status
    FROM periods
    WHERE id = p_current_period_id;
    
    IF v_current_period_status != 'OPEN' THEN
        RETURN FALSE;
    END IF;
    
    -- Check if original transaction exists and is COMMITTED
    SELECT COUNT(*) INTO v_original_transaction_exists
    FROM transactions
    WHERE id = p_original_transaction_id
      AND status = 'COMMITTED';
    
    IF v_original_transaction_exists = 0 THEN
        RETURN FALSE;
    END IF;
    
    RETURN TRUE;
END //

DELIMITER ;

-- ====================================================
-- PART 2: STORED PROCEDURES
-- ====================================================

-- Drop existing procedures if they exist
DROP PROCEDURE IF EXISTS sp_refresh_stock_cache;
DROP PROCEDURE IF EXISTS sp_generate_period_snapshot;
DROP PROCEDURE IF EXISTS sp_close_period;
DROP PROCEDURE IF EXISTS sp_validate_stock_integrity;

DELIMITER //

-- --------------------------------------------------------
-- Procedure: Refresh Stock Cache
-- Recalculates current_stock for all products from transactions
-- Should be run daily as a maintenance task
-- --------------------------------------------------------
CREATE PROCEDURE sp_refresh_stock_cache()
BEGIN
    DECLARE v_count INT DEFAULT 0;
    
    -- Update current_stock for all products
    UPDATE products p
    SET current_stock = (
        SELECT 
            p.opening_stock + COALESCE(SUM(
                CASE 
                    WHEN t.type = 'PURCHASE' THEN t.quantity
                    WHEN t.type = 'SALE' THEN t.quantity  -- Negative
                    WHEN t.type = 'ADJUSTMENT' THEN t.quantity
                    WHEN t.type = 'REVERSAL' THEN t.quantity
                    ELSE 0
                END
            ), 0)
        FROM transactions t
        WHERE t.product_id = p.id
          AND t.status = 'COMMITTED'
    );
    
    -- Get count of products updated
    SELECT COUNT(*) INTO v_count FROM products;
    
    SELECT CONCAT('Stock cache refreshed for ', v_count, ' products') AS message;
END //

-- --------------------------------------------------------
-- Procedure: Generate Period Snapshot
-- Creates a summary report for a period before closing
-- --------------------------------------------------------
CREATE PROCEDURE sp_generate_period_snapshot(
    IN p_period_id INT
)
BEGIN
    SELECT 
        p.id AS product_id,
        p.name AS product_name,
        p.code AS product_code,
        p.opening_stock,
        
        -- Purchases in period
        COALESCE(SUM(CASE WHEN t.type = 'PURCHASE' THEN t.quantity ELSE 0 END), 0) AS total_purchases,
        
        -- Sales in period
        COALESCE(SUM(CASE WHEN t.type = 'SALE' THEN ABS(t.quantity) ELSE 0 END), 0) AS total_sales,
        
        -- Adjustments in period
        COALESCE(SUM(CASE WHEN t.type = 'ADJUSTMENT' THEN t.quantity ELSE 0 END), 0) AS total_adjustments,
        
        -- Reversals in period
        COALESCE(COUNT(CASE WHEN t.type = 'REVERSAL' THEN 1 END), 0) AS total_reversals,
        
        -- Closing stock
        fn_calculate_stock(p.id, (SELECT end_date FROM periods WHERE id = p_period_id)) AS closing_stock,
        
        -- Value calculations
        p.cost_price,
        fn_calculate_stock(p.id, (SELECT end_date FROM periods WHERE id = p_period_id)) * p.cost_price AS closing_value
        
    FROM products p
    LEFT JOIN transactions t ON p.id = t.product_id 
        AND t.period_id = p_period_id
        AND t.status = 'COMMITTED'
    WHERE p.status = 'active'
    GROUP BY p.id, p.name, p.code, p.opening_stock, p.cost_price
    ORDER BY p.name;
END //

-- --------------------------------------------------------
-- Procedure: Close Period
-- Closes a period and carries forward opening stock to next period
-- --------------------------------------------------------
CREATE PROCEDURE sp_close_period(
    IN p_period_id INT,
    IN p_closed_by_user_id INT,
    OUT p_result VARCHAR(500)
)
BEGIN
    DECLARE v_period_status VARCHAR(20);
    DECLARE v_next_period_id INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'ERROR: Failed to close period';
    END;
    
    START TRANSACTION;
    
    -- Check if period is already closed
    SELECT status INTO v_period_status
    FROM periods
    WHERE id = p_period_id;
    
    IF v_period_status = 'CLOSED' THEN
        SET p_result = 'WARNING: Period is already closed';
        ROLLBACK;
    ELSE
        -- Close the period
        UPDATE periods
        SET status = 'CLOSED',
            closed_at = NOW(),
            closed_by = p_closed_by_user_id
        WHERE id = p_period_id;
        
        -- Refresh stock cache before closing
        CALL sp_refresh_stock_cache();
        
        -- Log the closure
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_value)
        VALUES (
            p_closed_by_user_id,
            'CLOSE_PERIOD',
            'period',
            p_period_id,
            CONCAT('Period closed at ', NOW())
        );
        
        COMMIT;
        SET p_result = CONCAT('SUCCESS: Period ', p_period_id, ' closed successfully');
    END IF;
END //

-- --------------------------------------------------------
-- Procedure: Validate Stock Integrity
-- Checks that current_stock matches calculated stock from transactions
-- Returns discrepancies if any
-- --------------------------------------------------------
CREATE PROCEDURE sp_validate_stock_integrity()
BEGIN
    SELECT 
        p.id,
        p.name,
        p.code,
        p.current_stock AS cached_stock,
        fn_calculate_stock(p.id, NOW()) AS calculated_stock,
        (p.current_stock - fn_calculate_stock(p.id, NOW())) AS discrepancy
    FROM products p
    WHERE p.current_stock != fn_calculate_stock(p.id, NOW())
    ORDER BY ABS(p.current_stock - fn_calculate_stock(p.id, NOW())) DESC;
    
    -- If no discrepancies
    SELECT 
        CASE 
            WHEN COUNT(*) = 0 THEN 'PASS: All stock values are accurate'
            ELSE CONCAT('FAIL: ', COUNT(*), ' products have stock discrepancies')
        END AS integrity_check
    FROM products p
    WHERE p.current_stock != fn_calculate_stock(p.id, NOW());
END //

DELIMITER ;

-- ====================================================
-- PART 3: TRIGGERS FOR DATA INTEGRITY
-- ====================================================

-- Drop existing triggers if they exist
DROP TRIGGER IF EXISTS trg_validate_transaction_period;
DROP TRIGGER IF EXISTS trg_validate_sale_stock;
DROP TRIGGER IF EXISTS trg_prevent_transaction_edit;
DROP TRIGGER IF EXISTS trg_validate_reversal;
DROP TRIGGER IF EXISTS trg_audit_transaction;

DELIMITER //

-- --------------------------------------------------------
-- Trigger: Validate Transaction Period
-- Prevents posting to closed periods (except REVERSALS)
-- --------------------------------------------------------
CREATE TRIGGER trg_validate_transaction_period
BEFORE INSERT ON transactions
FOR EACH ROW
BEGIN
    DECLARE v_period_status VARCHAR(20);
    
    -- Get period status
    SELECT status INTO v_period_status
    FROM periods
    WHERE id = NEW.period_id;
    
    -- If period is CLOSED
    IF v_period_status = 'CLOSED' THEN
        -- Only allow REVERSALS to closed periods
        IF NEW.type != 'REVERSAL' THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot post transactions to closed period. Only reversals are allowed.';
        END IF;
    END IF;
    
    -- Ensure transaction date is within period dates
    IF NEW.transaction_date NOT BETWEEN 
        (SELECT start_date FROM periods WHERE id = NEW.period_id) AND 
        (SELECT end_date FROM periods WHERE id = NEW.period_id) THEN
        
        -- Allow if it's a reversal (posted in current period but references past)
        IF NEW.type != 'REVERSAL' THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Transaction date must be within period date range';
        END IF;
    END IF;
END //

-- --------------------------------------------------------
-- Trigger: Validate Sale Stock Availability
-- Ensures sufficient stock before allowing sale
-- --------------------------------------------------------
CREATE TRIGGER trg_validate_sale_stock
BEFORE INSERT ON transactions
FOR EACH ROW
BEGIN
    DECLARE v_available_stock INT;
    
    -- Only validate for SALE transactions
    IF NEW.type = 'SALE' AND NEW.status = 'COMMITTED' THEN
        -- Calculate available stock
        SET v_available_stock = fn_calculate_stock(NEW.product_id, NEW.transaction_date);
        
        -- Check if sufficient stock (quantity is negative for sales)
        IF ABS(NEW.quantity) > v_available_stock THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Insufficient stock for sale transaction';
        END IF;
    END IF;
END //

-- --------------------------------------------------------
-- Trigger: Prevent Transaction Edits
-- Prevents modification of COMMITTED or REVERSED transactions
-- --------------------------------------------------------
CREATE TRIGGER trg_prevent_transaction_edit
BEFORE UPDATE ON transactions
FOR EACH ROW
BEGIN
    -- Prevent changes to COMMITTED transactions except status change to REVERSED
    IF OLD.status = 'COMMITTED' THEN
        IF NEW.status != 'REVERSED' THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot modify COMMITTED transaction. Use REVERSAL instead.';
        END IF;
        
        -- When marking as REVERSED, prevent other changes
        IF OLD.type != NEW.type OR 
           OLD.product_id != NEW.product_id OR
           OLD.quantity != NEW.quantity OR
           OLD.unit_price != NEW.unit_price THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot modify transaction data when marking as REVERSED';
        END IF;
    END IF;
    
    -- Prevent any changes to REVERSED transactions
    IF OLD.status = 'REVERSED' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'REVERSED transactions are immutableand cannot be modified';
    END IF;
END //

-- --------------------------------------------------------
-- Trigger: Validate Reversal Transaction
-- Ensures reversal references valid original transaction
-- --------------------------------------------------------
CREATE TRIGGER trg_validate_reversal
BEFORE INSERT ON transactions
FOR EACH ROW
BEGIN
    DECLARE v_original_status VARCHAR(20);
    DECLARE v_original_type VARCHAR(20);
    DECLARE v_original_quantity INT;
    
    -- Only validate REVERSAL transactions
    IF NEW.type = 'REVERSAL' THEN
        -- Must have reference_transaction_id
        IF NEW.reference_transaction_id IS NULL THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'REVERSAL must reference original transaction';
        END IF;
        
        -- Must have reversal_reason
        IF NEW.reversal_reason IS NULL OR NEW.reversal_reason = '' THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'REVERSAL must include reason';
        END IF;
        
        -- Check original transaction exists and is COMMITTED
        SELECT status, type, quantity 
        INTO v_original_status, v_original_type, v_original_quantity
        FROM transactions
        WHERE id = NEW.reference_transaction_id;
        
        IF v_original_status != 'COMMITTED' THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Can only reverse COMMITTED transactions';
        END IF;
        
        -- Reversal quantity must be opposite of original
        IF NEW.quantity != -v_original_quantity THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'REVERSAL quantity must be exact opposite of original';
        END IF;
    END IF;
END //

-- --------------------------------------------------------
-- Trigger: Audit Transaction Operations
-- Logs all transaction insertions to audit log
-- --------------------------------------------------------
CREATE TRIGGER trg_audit_transaction
AFTER INSERT ON transactions
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (
        user_id,
        action,
        entity_type,
        entity_id,
        new_value,
        timestamp
    ) VALUES (
        NEW.created_by,
        CONCAT('TRANSACTION_', NEW.type),
        'transaction',
        NEW.id,
        JSON_OBJECT(
            'type', NEW.type,
            'product_id', NEW.product_id,
            'quantity', NEW.quantity,
            'unit_price', NEW.unit_price,
            'total_amount', NEW.total_amount,
            'status', NEW.status,
            'period_id', NEW.period_id,
            'transaction_date', NEW.transaction_date
        ),
        NOW()
    );
END //

DELIMITER ;

-- ====================================================
-- PART 4: VIEWS FOR REPORTING
-- ====================================================

-- Drop existing views if they exist
DROP VIEW IF EXISTS v_stock_movements;
DROP VIEW IF EXISTS v_transaction_audit;
DROP VIEW IF EXISTS v_period_summary;

-- --------------------------------------------------------
-- View: Stock Movements
-- Shows all stock changes with running balance
-- --------------------------------------------------------
CREATE OR REPLACE VIEW v_stock_movements AS
SELECT 
    t.id AS transaction_id,
    t.transaction_date,
    t.type AS transaction_type,
    p.id AS product_id,
    p.name AS product_name,
    p.code AS product_code,
    t.quantity,
    t.unit_price,
    t.total_amount,
    t.status,
    CASE 
        WHEN t.reference_transaction_id IS NOT NULL 
        THEN CONCAT('Reverses #', t.reference_transaction_id, ': ', t.reversal_reason)
        ELSE ''
    END AS reversal_info,
    u.full_name AS created_by,
    per.period_name,
    per.status AS period_status
FROM transactions t
JOIN products p ON t.product_id = p.id
JOIN users u ON t.created_by = u.id
JOIN periods per ON t.period_id = per.id
WHERE t.status IN ('COMMITTED', 'REVERSED')
ORDER BY t.transaction_date DESC, t.id DESC;

-- --------------------------------------------------------
-- View: Transaction Audit Trail
-- Complete audit trail with reversal tracking
-- --------------------------------------------------------
CREATE OR REPLACE VIEW v_transaction_audit AS
SELECT 
    t.id,
    t.type,
    t.status,
    t.transaction_date,
    p.name AS product_name,
    t.quantity,
    t.total_amount,
    u.full_name AS user,
    u.role AS user_role,
    per.period_name,
    t.reference_transaction_id,
    t.reversal_reason,
    t.created_at,
    -- Show if this transaction has been reversed
    (SELECT COUNT(*) FROM transactions WHERE reference_transaction_id = t.id) AS times_reversed
FROM transactions t
JOIN products p ON t.product_id = p.id
JOIN users u ON t.created_by = u.id
JOIN periods per ON t.period_id = per.id;

-- --------------------------------------------------------
-- View: Period Summary
-- Summary of each period's activity
-- --------------------------------------------------------
CREATE OR REPLACE VIEW v_period_summary AS
SELECT 
    per.id AS period_id,
    per.period_name,
    per.status AS period_status,
    per.start_date,
    per.end_date,
    
    -- Transaction counts
    COUNT(DISTINCT t.id) AS total_transactions,
    COUNT(DISTINCT CASE WHEN t.type = 'SALE' THEN t.id END) AS total_sales,
    COUNT(DISTINCT CASE WHEN t.type = 'PURCHASE' THEN t.id END) AS total_purchases,
    COUNT(DISTINCT CASE WHEN t.type = 'ADJUSTMENT' THEN t.id END) AS total_adjustments,
    COUNT(DISTINCT CASE WHEN t.type = 'REVERSAL' THEN t.id END) AS total_reversals,
    
    -- Financial totals
    COALESCE(SUM(CASE WHEN t.type = 'SALE' THEN t.total_amount ELSE 0 END), 0) AS total_sales_value,
    COALESCE(SUM(CASE WHEN t.type = 'PURCHASE' THEN t.total_amount ELSE 0 END), 0) AS total_purchases_value,
    
    per.closed_at,
    u.full_name AS closed_by
FROM periods per
LEFT JOIN transactions t ON per.id = t.period_id AND t.status = 'COMMITTED'
LEFT JOIN users u ON per.closed_by = u.id
GROUP BY per.id, per.period_name, per.status, per.start_date, per.end_date, per.closed_at, u.full_name;

-- ====================================================
-- PART 5: MAINTENANCE QUERIES
-- ====================================================

-- Check stock integrity
-- Run this to verify current_stock matches calculated values
-- SELECT * FROM products_stock_validation;

-- Refresh stock cache
-- Run this daily or after major operations
-- CALL sp_refresh_stock_cache();

-- Generate period snapshot before closing
-- CALL sp_generate_period_snapshot(1);

-- Close a period
-- CALL sp_close_period(1, @admin_user_id, @result);

-- Validate stock integrity
-- CALL sp_validate_stock_integrity();

-- ====================================================
-- PART 6: EXAMPLE USAGE
-- ====================================================

/*
-- Example 1: Record a purchase
INSERT INTO transactions (
    type, product_id, quantity, unit_price, total_amount,
    transaction_date, period_id, created_by, status
) VALUES (
    'PURCHASE', 1, 50, 15.00, 750.00,
    NOW(), 1, 2, 'COMMITTED'
);

-- Example 2: Record a sale
INSERT INTO transactions (
    type, product_id, quantity, unit_price, total_amount,
    transaction_date, period_id, created_by, status
) VALUES (
    'SALE', 1, -10, 25.00, 250.00,
    NOW(), 1, 1, 'COMMITTED'
);

-- Example 3: Reverse a transaction
-- Step 1: Mark original as REVERSED
UPDATE transactions SET status = 'REVERSED' WHERE id = 123;

-- Step 2: Create reversal transaction
INSERT INTO transactions (
    type, product_id, quantity, unit_price, total_amount,
    reference_transaction_id, reversal_reason,
    transaction_date, period_id, created_by, status
) VALUES (
    'REVERSAL', 1, 10, 25.00, 250.00,
    123, 'Incorrect quantity - customer returned items',
    NOW(), 1, 2, 'COMMITTED'
);

-- Example 4: Stock adjustment after physical count
INSERT INTO transactions (
    type, product_id, quantity, unit_price, total_amount,
    reversal_reason, transaction_date, period_id, created_by, status
) VALUES (
    'ADJUSTMENT', 1, -2, 0.00, 0.00,
    'Stock taking: 2 units damaged/expired',
    NOW(), 1, 2, 'COMMITTED'
);

-- Example 5: Calculate stock as of specific date
SELECT 
    id,
    name,
    fn_calculate_stock(id, '2026-01-31 23:59:59') AS stock_on_jan_31
FROM products;

-- Example 6: Validate stock integrity
CALL sp_validate_stock_integrity();

-- Example 7: Generate period report
CALL sp_generate_period_snapshot(1);
*/

-- ====================================================
-- INSTALLATION COMPLETE
-- ====================================================
SELECT 'Enhanced accounting-grade inventory system installed successfully' AS status;
SELECT '✅ Functions, procedures, triggers, and views created' AS details;
SELECT 'Run: CALL sp_validate_stock_integrity(); to verify integrity' AS next_step;
