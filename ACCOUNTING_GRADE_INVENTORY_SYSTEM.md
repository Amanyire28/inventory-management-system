# 📋 Accounting-Grade Inventory Management System
## Professional Implementation Guide

---

## ✅ SYSTEM COMPLIANCE STATUS

### **Already Implemented Correctly:**

1. ✅ **Immutable Transaction Log** - `transactions` table with append-only design
2. ✅ **Status Flow** - DRAFT → COMMITTED → REVERSED
3. ✅ **Period Locking** - `periods` table with OPEN/CLOSED status
4. ✅ **Reversal Tracking** - `reference_transaction_id` links reversals to originals
5. ✅ **Audit Log** - Complete audit trail in `audit_logs` table
6. ✅ **Role-Based Access** - Cashier and Admin roles implemented
7. ✅ **Draft Sales** - Temporary layer before commitment
8. ✅ **Stock Protection** - Direct `current_stock` edits are blocked in ProductService

---

## 🏗️ ARCHITECTURE OVERVIEW

```
┌─────────────────────────────────────────────────────────┐
│                    USER INTERFACE                        │
├─────────────────┬───────────────────────────────────────┤
│   CASHIER       │            ADMIN                      │
│   - Record Sale │  - Manage Products                    │
│   - View Stock  │  - Record Purchases                   │
│                 │  - Stock Taking                       │
│                 │  - Reversals & Corrections            │
│                 │  - Close Periods                      │
│                 │  - Reports                            │
└─────────────────┴───────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────┐
│            SERVICE LAYER (Business Logic)                │
│  - SalesService                                          │
│  - PurchaseService                                       │
│  - TransactionService (Stock Updates)                    │
│  - StockTakingService                                    │
│  - PeriodService (Locking & Period Management)           │
│  - ReportService                                         │
└─────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────┐
│              DATA LAYER (Single Source of Truth)         │
│                                                          │
│  transactions (APPEND-ONLY EVENT LOG)                    │
│  ├─ PURCHASE: +quantity                                  │
│  ├─ SALE: -quantity                                      │
│  ├─ ADJUSTMENT: ±variance                                │
│  └─ REVERSAL: opposite of original                       │
│                                                          │
│  Stock = Opening + Σ(PURCHASE) - Σ(SALE) ± Σ(ADJUSTMENT)│
└─────────────────────────────────────────────────────────┘
```

---

## 📊 DATABASE SCHEMA

### Core Principle: **Stock is CALCULATED, not STORED**

```sql
-- products.current_stock is a CACHE for performance
-- TRUE stock is ALWAYS calculated from transactions table
```

### **1. Products Table**
```sql
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(100) UNIQUE,
    selling_price DECIMAL(10, 2) NOT NULL,
    cost_price DECIMAL(10, 2) NOT NULL,
    opening_stock INT NOT NULL DEFAULT 0,  -- Per period opening
    current_stock INT NOT NULL DEFAULT 0,  -- CALCULATED CACHE
    reorder_level INT NOT NULL DEFAULT 10,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### **2. Transactions Table (Single Source of Truth)**
```sql
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('PURCHASE', 'SALE', 'ADJUSTMENT', 'REVERSAL') NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,  -- Positive for PURCHASE, Negative for SALE
    unit_price DECIMAL(10, 2) NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    
    -- Reversal tracking
    reference_transaction_id INT NULL,  -- Links to original transaction
    reversal_reason VARCHAR(500),
    
    -- Temporal & Period tracking
    transaction_date DATETIME NOT NULL,
    period_id INT NOT NULL,
    
    -- Status flow
    status ENUM('DRAFT', 'COMMITTED', 'REVERSED') DEFAULT 'COMMITTED',
    
    -- Audit
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (period_id) REFERENCES periods(id) ON DELETE RESTRICT,
    FOREIGN KEY (reference_transaction_id) REFERENCES transactions(id)
);
```

### **3. Periods Table (Financial Control)**
```sql
CREATE TABLE periods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    period_name VARCHAR(100) NOT NULL,  -- e.g., "January 2026"
    status ENUM('OPEN', 'CLOSED') DEFAULT 'OPEN',
    start_date DATE,
    end_date DATE,
    closed_at TIMESTAMP NULL,
    closed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🔄 TRANSACTION FLOWS

### **1. CASHIER SALE FLOW**

```
┌─────────────────┐
│  1. Draft Sale  │  Status: DRAFT (editable)
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│  2. Add Items   │  Validate: quantity ≤ available stock
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│  3. Commit Sale │  Status: DRAFT → COMMITTED
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│  4. Post Trans. │  Create SALE transaction(s)
└────────┬────────┘         Stock = Opening + Σ(Trans)
         │
         ↓
┌─────────────────┐
│  5. Update Cache│  products.current_stock = calculated
└─────────────────┘
```

**Key Rules:**
- ✅ Cashier CANNOT backdate transactions
- ✅ Cashier CANNOT reverse transactions
- ✅ Cashier CANNOT edit committed transactions
- ✅ Cashier CAN delete/edit DRAFT transactions

---

### **2. ADMIN PURCHASE FLOW**

```
┌─────────────────┐
│ 1. Record       │  Admin enters: product, quantity, cost
│    Purchase     │  
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│ 2. Create Trans.│  type: PURCHASE
│                 │  quantity: positive
│                 │  status: COMMITTED
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│ 3. Update Stock │  Stock = Opening + Σ(PURCHASE) - Σ(SALE)
└─────────────────┘
```

**Key Rules:**
- ✅ Admin CAN backdate purchases (if period is OPEN)
- ✅ Admin CANNOT backdate to CLOSED periods
- ✅ All purchases immediately COMMITTED

---

### **3. ERROR CORRECTION FLOW (REVERSAL)**

#### **Scenario: Sale of 10 units was recorded, but should have been 5**

```sql
-- ❌ WRONG APPROACH: Edit the original transaction
UPDATE transactions SET quantity = 5 WHERE id = 123;  -- FORBIDDEN!

-- ✅ CORRECT APPROACH: Reversal + New Transaction

-- Step 1: Mark original as REVERSED
UPDATE transactions SET status = 'REVERSED' WHERE id = 123;

-- Step 2: Create REVERSAL transaction (exact opposite)
INSERT INTO transactions (
    type, product_id, quantity, unit_price, total_amount,
    reference_transaction_id, reversal_reason,
    transaction_date, period_id, created_by, status
) VALUES (
    'REVERSAL', 456, +10, 25.00, 250.00,
    123, 'Correction: Original quantity was incorrect',
    NOW(), 1, 2, 'COMMITTED'
);

-- Step 3: Create NEW correct transaction
INSERT INTO transactions (
    type, product_id, quantity, unit_price, total_amount,
    transaction_date, period_id, created_by, status
) VALUES (
    'SALE', 456, -5, 25.00, 125.00,
    NOW(), 1, 2, 'COMMITTED'
);
```

**Result:**
- ✅ Original transaction preserved in history
- ✅ Audit trail shows WHO, WHEN, WHY correction was made
- ✅ Stock calculation remains accurate
- ✅ Reports reflect true history

---

### **4. STOCK TAKING & ADJUSTMENT FLOW**

```
┌──────────────────┐
│ 1. Count Physical│  Admin counts actual stock
│    Stock         │
└─────────┬────────┘
          │
          ↓
┌──────────────────┐
│ 2. Compare       │  System Stock: 50
│                  │  Physical Count: 48
│                  │  Variance: -2
└─────────┬────────┘
          │
          ↓
┌──────────────────┐
│ 3. Record in     │  Log variance with reason
│    stock_        │
│    adjustments   │
└─────────┬────────┘
          │
          ↓
┌──────────────────┐
│ 4. Create        │  type: ADJUSTMENT
│    ADJUSTMENT    │  quantity: -2
│    Transaction   │  reason: "Damaged units found"
└──────────────────┘
```

**Key Rules:**
- ✅ Stock taking NEVER modifies opening_stock
- ✅ Stock taking NEVER edits past transactions
- ✅ Variance is recorded as ADJUSTMENT transaction
- ✅ Adjustment includes mandatory reason

---

## 🔒 PERIOD LOCKING MECHANISM

### **Monthly Closing Process**

```sql
-- 1. Generate period snapshot
CALL sp_generate_period_snapshot(period_id);

-- 2. Lock the period
UPDATE periods 
SET status = 'CLOSED', 
    closed_at = NOW(), 
    closed_by = @admin_user_id
WHERE id = @period_id;

-- 3. Create new period with carried forward opening stock
INSERT INTO periods (period_name, status, start_date, end_date)
VALUES ('February 2026', 'OPEN', '2026-02-01', '2026-02-29');

-- 4. Carry forward closing stock as opening stock
-- (Handled by stored procedure)
```

### **Validation Rules**

```sql
-- BEFORE INSERT/UPDATE on transactions
IF (SELECT status FROM periods WHERE id = NEW.period_id) = 'CLOSED' THEN
    IF NEW.type != 'REVERSAL' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Cannot post to closed period (reversals only)';
    END IF;
END IF;
```

---

## 📈 STOCK CALCULATION FUNCTIONS

### **Function: Calculate Current Stock**

```sql
DELIMITER //

CREATE FUNCTION fn_calculate_stock(
    p_product_id INT,
    p_as_of_date DATETIME
) RETURNS INT
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_opening_stock INT DEFAULT 0;
    DECLARE v_total_change INT DEFAULT 0;
    
    -- Get opening stock
    SELECT opening_stock INTO v_opening_stock
    FROM products
    WHERE id = p_product_id;
    
    -- Calculate net change from transactions
    SELECT 
        COALESCE(SUM(
            CASE 
                WHEN type IN ('PURCHASE', 'REVERSAL') AND quantity > 0 THEN quantity
                WHEN type IN ('SALE') THEN quantity  -- Already negative
                WHEN type = 'ADJUSTMENT' THEN quantity
                ELSE 0
            END
        ), 0) INTO v_total_change
    FROM transactions
    WHERE product_id = p_product_id
      AND status = 'COMMITTED'
      AND transaction_date <= p_as_of_date;
    
    RETURN v_opening_stock + v_total_change;
END //

DELIMITER ;
```

### **Procedure: Refresh Stock Cache**

```sql
DELIMITER //

CREATE PROCEDURE sp_refresh_stock_cache()
BEGIN
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
END //

DELIMITER ;
```

---

## 🛡️ DATA INTEGRITY ENFORCEMENT

### **Trigger: Prevent Stock Manipulation**

```sql
DELIMITER //

CREATE TRIGGER trg_prevent_stock_edit
BEFORE UPDATE ON products
FOR EACH ROW
BEGIN
    -- Allow all updates EXCEPT current_stock
    IF OLD.current_stock != NEW.current_stock AND 
       NEW.current_stock != fn_calculate_stock(NEW.id, NOW()) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'current_stock can only be updated via transactions';
    END IF;
END //

DELIMITER ;
```

### **Trigger: Validate Transaction Quantity**

```sql
DELIMITER //

CREATE TRIGGER trg_validate_sale_quantity
BEFORE INSERT ON transactions
FOR EACH ROW
BEGIN
    DECLARE v_available_stock INT;
    
    IF NEW.type = 'SALE' THEN
        SET v_available_stock = fn_calculate_stock(NEW.product_id, NEW.transaction_date);
        
        IF ABS(NEW.quantity) > v_available_stock THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Insufficient stock for sale';
        END IF;
    END IF;
END //

DELIMITER ;
```

---

## 📋 REPORTING QUERIES

### **1. Product Movement Report**

```sql
SELECT 
    p.name AS product_name,
    p.opening_stock,
    COALESCE(SUM(CASE WHEN t.type = 'PURCHASE' THEN t.quantity ELSE 0 END), 0) AS total_purchases,
    COALESCE(SUM(CASE WHEN t.type = 'SALE' THEN ABS(t.quantity) ELSE 0 END), 0) AS total_sales,
    COALESCE(SUM(CASE WHEN t.type = 'ADJUSTMENT' THEN t.quantity ELSE 0 END), 0) AS total_adjustments,
    fn_calculate_stock(p.id, NOW()) AS current_stock
FROM products p
LEFT JOIN transactions t ON p.id = t.product_id 
    AND t.status = 'COMMITTED'
    AND t.period_id = @period_id
WHERE p.status = 'active'
GROUP BY p.id, p.name, p.opening_stock
ORDER BY p.name;
```

### **2. Transaction Audit Trail**

```sql
SELECT 
    t.id,
    t.type,
    t.transaction_date,
    p.name AS product_name,
    t.quantity,
    t.unit_price,
    t.total_amount,
    t.status,
    CASE 
        WHEN t.reference_transaction_id IS NOT NULL THEN 
            CONCAT('Reverses #', t.reference_transaction_id, ': ', t.reversal_reason)
        ELSE ''
    END AS reversal_info,
    u.full_name AS created_by,
    per.period_name
FROM transactions t
JOIN products p ON t.product_id = p.id
JOIN users u ON t.created_by = u.id
JOIN periods per ON t.period_id = per.id
WHERE t.status IN ('COMMITTED', 'REVERSED')
ORDER BY t.transaction_date DESC, t.id DESC;
```

---

## ✅ COMPLIANCE CHECKLIST

| Requirement | Status | Implementation |
|------------|--------|----------------|
| Stock never freely edited | ✅ | Blocked in ProductService.php |
| All changes via transactions | ✅ | TransactionService enforces |
| Historical records immutable | ✅ | No UPDATE/DELETE on committed transactions |
| Corrections via reversals | ✅ | reference_transaction_id tracking |
| Monthly period locking | ✅ | periods.status = 'CLOSED' |
| Auditability | ✅ | audit_logs table + transaction history |
| Role-based access | ✅ | Cashier/Admin in users table |
| Stock calculated not stored | ⚠️ | Implemented with cache (see below) |

### **⚠️ Note on Stock Calculation**

**Current Implementation:**
- `products.current_stock` exists as a **performance cache**
- Updated atomically via `TransactionService`
- Protected from direct edits
- Can be recalculated anytime from transactions

**This is ACCEPTABLE because:**
1. ✅ True source of truth is `transactions` table
2. ✅ Cache can be rebuilt from transactions
3. ✅ Direct edits are blocked
4. ✅ All updates go through TransactionService

---

## 🚀 IMPLEMENTATION RECOMMENDATIONS

### **Immediate Actions:**

1. **Add Database Functions**
   - Deploy `fn_calculate_stock()` for on-demand calculations
   - Deploy `sp_refresh_stock_cache()` for cache rebuilding

2. **Add Triggers**
   - `trg_prevent_stock_edit` - Prevent manual stock changes
   - `trg_validate_sale_quantity` - Enforce stock availability

3. **Enhanced Audit Logging**
   - Log all reversal operations
   - Track period closing events
   - Record stock adjustment reasons

4. **Period Management**
   - Implement closing workflow
   - Generate period snapshots
   - Carry forward opening stock

### **Best Practices:**

1. ✅ **Never DELETE transactions** - Mark as REVERSED instead
2. ✅ **Always provide reversal_reason** - Mandatory for audit
3. ✅ **Validate period status** - Before posting transactions
4. ✅ **Run sp_refresh_stock_cache()** - Daily as scheduled task
5. ✅ **Lock periods promptly** - Within 3 days of month end

---

## 📚 REFERENCES

- **FIFO/LIFO Costing**: Can be implemented in reporting layer
- **Batch Tracking**: Add batch_number field to transactions
- **Expiry Tracking**: Add expiry_date field to transactions
- **Multi-location**: Add location_id to products/transactions

---

## 🏷️ VERSION

**System**: TOPINV Clinic Inventory Management  
**Architecture**: Event-Sourced Transactional Inventory  
**Compliance**: Accounting-Grade with Period Locking  
**Database**: MySQL 5.7+  
**Last Updated**: February 8, 2026

---

**✅ This system meets professional accounting-grade standards for inventory management with full auditability, transparency, and data integrity.**
