# TOPINV Backend API Documentation

## Overview

This is a production-grade backend for a clinic inventory management system built with **strict data integrity principles**:

- ✅ **Append-only transactions** - Never delete or edit transactions
- ✅ **Derived stock values** - Calculate from transaction history, never edit directly
- ✅ **Role-based access control** - Separate permissions for cashiers and admins
- ✅ **Period locking** - Closed periods are read-only
- ✅ **Atomic operations** - All-or-nothing with rollback on failure
- ✅ **Immutable audit logs** - Complete traceability of all actions
- ✅ **Time integrity** - Server-side timestamps, no back-dating

## Architecture

### Directory Structure

```
backend/
├── api/
│   ├── index.php          # Main API router (all endpoints)
│   └── .htaccess          # URL rewriting rules
├── core/
│   ├── Database.php       # Database connection & utilities
│   ├── Auth.php          # Authentication & JWT tokens
│   ├── Response.php       # Standardized response handler & audit logging
│   └── Router.php         # Request routing
├── services/
│   ├── TransactionService.php    # Append-only transaction management
│   ├── SalesService.php          # Sales workflow (draft → commit)
│   ├── PurchaseService.php       # Purchase transactions
│   ├── ProductService.php        # Product data management
│   ├── PeriodService.php         # Period locking
│   └── StockTakingService.php    # Physical inventory counts
├── database.sql                   # Database schema
├── setup.php                      # Database initialization script
└── config.php                     # Database configuration (auto-generated)
```

## API Endpoints

### Authentication

#### Login
```
POST /api/auth/login
Content-Type: application/json

{
  "username": "cashier1",
  "password": "password"
}

Response:
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "eyJhbGc...",
    "user": {
      "id": 1,
      "username": "cashier1",
      "full_name": "John Cashier",
      "role": "cashier"
    }
  }
}
```

**Include token in all requests:**
```
Authorization: Bearer <token>
```

---

### Products

#### Get All Products
```
GET /api/products?status=active

Response:
{
  "success": true,
  "data": {
    "products": [
      {
        "id": 1,
        "name": "Paracetamol 500mg",
        "selling_price": 25.00,
        "cost_price": 15.00,
        "opening_stock": 100,
        "current_stock": 95,
        "reorder_level": 20,
        "status": "active"
      }
    ]
  }
}
```

#### Get Product with History
```
GET /api/products/1

Response:
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Paracetamol 500mg",
    ...
    "stock_history": [
      {
        "id": 15,
        "type": "SALE",
        "quantity": 5,
        "unit_price": 25.00,
        "transaction_date": "2026-02-07 12:30:45",
        "created_by_name": "John Cashier"
      }
    ]
  }
}
```

#### Create Product (Admin only)
```
POST /api/products
Authorization: Bearer <token>
Content-Type: application/json

{
  "name": "New Medicine",
  "code": "MED-NEW",
  "selling_price": 50.00,
  "cost_price": 30.00,
  "opening_stock": 100,
  "reorder_level": 15
}

Response:
{
  "success": true,
  "message": "Product created",
  "data": {
    "product_id": 7
  }
}
```

**Key Principle:** `opening_stock` is immutable. `current_stock` is calculated from transactions.

#### Update Product (Admin only)
```
PUT /api/products/1
Authorization: Bearer <token>
Content-Type: application/json

{
  "name": "Updated Name",
  "selling_price": 30.00,
  "reorder_level": 25
}

Note: opening_stock and current_stock CANNOT be edited directly.
If you need to correct stock, use reversals or adjustments.
```

---

### Sales (Cashier Workflow)

#### Create Draft Sale
```
POST /api/sales/draft
Authorization: Bearer <token>

Response:
{
  "success": true,
  "data": {
    "draft_id": 1,
    "session_id": "sale_123abc..."
  }
}
```

#### Add Item to Draft Sale
```
POST /api/sales/draft/1/items
Authorization: Bearer <token>
Content-Type: application/json

{
  "product_id": 1,
  "quantity": 5,
  "unit_price": 25.00
}

Response:
{
  "success": true,
  "data": {
    "item_id": 1
  }
}
```

#### Get Draft Sale
```
GET /api/sales/draft/1
Authorization: Bearer <token>

Response:
{
  "success": true,
  "data": {
    "id": 1,
    "session_id": "sale_123abc...",
    "total_amount": 125.00,
    "item_count": 1,
    "items": [
      {
        "id": 1,
        "product_id": 1,
        "product_name": "Paracetamol 500mg",
        "quantity": 5,
        "unit_price": 25.00,
        "line_total": 125.00,
        "current_stock": 95
      }
    ]
  }
}
```

#### Remove Item from Draft
```
DELETE /api/sales/draft/1/items/1
Authorization: Bearer <token>
Content-Type: application/json

{
  "item_id": 1
}
```

#### Commit Draft Sale (Creates SALE Transactions)
```
POST /api/sales/commit
Authorization: Bearer <token>
Content-Type: application/json

{
  "draft_id": 1,
  "period_id": 1
}

Response:
{
  "success": true,
  "message": "Sale committed",
  "data": {
    "success": true,
    "transaction_ids": [15, 16, 17],
    "item_count": 3
  }
}
```

**Key Points:**
- Draft sales have no stock impact
- Only when committed do SALE transactions get created
- Stock is atomically updated when transactions are created
- Draft is deleted after commitment

#### Get Sales History
```
GET /api/sales/history?period_id=1
Authorization: Bearer <token>

Response:
{
  "success": true,
  "data": {
    "sales": [...]
  }
}
```

#### Reverse a Sale (Admin only)
```
POST /api/sales/15/reverse
Authorization: Bearer <token>
Content-Type: application/json

{
  "reason": "Customer returned product"
}

Response:
{
  "success": true,
  "data": {
    "reversal_id": 25
  }
}
```

**Reversal Logic:**
- Original SALE transaction immutable
- Creates new REVERSAL transaction that negates original
- Stock automatically recalculated
- All actions logged in audit trail

---

### Purchases

#### Record Purchase
```
POST /api/purchases
Authorization: Bearer <token>
Content-Type: application/json

{
  "product_id": 1,
  "quantity": 50,
  "unit_cost": 15.00,
  "period_id": 1,
  "supplier": "PharmaCorp"
}

Response:
{
  "success": true,
  "data": {
    "transaction_id": 20
  }
}
```

#### Get Purchase History
```
GET /api/purchases/history?period_id=1
Authorization: Bearer <token>

Response:
{
  "success": true,
  "data": {
    "purchases": [...]
  }
}
```

#### Reverse Purchase (Admin only)
```
POST /api/purchases/20/reverse
Authorization: Bearer <token>
Content-Type: application/json

{
  "reason": "Supplier returned"
}
```

---

### Transactions (Immutable Log)

#### Get All Transactions
```
GET /api/transactions?product_id=1&period_id=1&type=SALE
Authorization: Bearer <token>

Filters:
- product_id: Filter by product
- period_id: Filter by accounting period
- type: PURCHASE, SALE, ADJUSTMENT, REVERSAL, VOID

Response:
{
  "success": true,
  "data": {
    "transactions": [
      {
        "id": 15,
        "type": "SALE",
        "product_id": 1,
        "product_name": "Paracetamol 500mg",
        "quantity": 5,
        "unit_price": 25.00,
        "total_amount": 125.00,
        "transaction_date": "2026-02-07 12:30:45",
        "period_id": 1,
        "created_by": 1,
        "created_by_name": "John Cashier",
        "status": "COMMITTED"
      }
    ]
  }
}
```

#### Get Single Transaction
```
GET /api/transactions/15
Authorization: Bearer <token>
```

---

### Periods (Financial Control)

#### Get All Periods
```
GET /api/periods
Authorization: Bearer <token>

Response:
{
  "success": true,
  "data": {
    "periods": [
      {
        "id": 1,
        "period_name": "January 2026",
        "status": "OPEN",
        "start_date": "2026-01-01",
        "end_date": "2026-01-31"
      }
    ]
  }
}
```

#### Get Current Open Period
```
GET /api/periods/current
Authorization: Bearer <token>
```

#### Create Period (Admin only)
```
POST /api/periods
Authorization: Bearer <token>
Content-Type: application/json

{
  "period_name": "February 2026",
  "start_date": "2026-02-01",
  "end_date": "2026-02-28"
}
```

#### Close Period (Admin only, IRREVERSIBLE)
```
POST /api/periods/1/close
Authorization: Bearer <token>

After closing:
- No new transactions can be recorded in this period
- No edits allowed
- Only reversals for corrections are permitted
```

#### Get Period Summary
```
GET /api/periods/1/summary
Authorization: Bearer <token>

Response:
{
  "success": true,
  "data": {
    "total_transactions": 42,
    "total_sales_qty": 150,
    "total_purchases_qty": 200,
    "total_sales_amount": 3750.00,
    "total_purchases_amount": 5000.00
  }
}
```

---

### Stock Taking & Inventory

#### Record Physical Count
```
POST /api/inventory/physical-count
Authorization: Bearer <token>
Content-Type: application/json

{
  "product_id": 1,
  "physical_count": 92,
  "period_id": 1
}

Response:
{
  "success": true,
  "data": {
    "adjustment_id": 5,
    "product_id": 1,
    "product_name": "Paracetamol 500mg",
    "system_stock": 95,
    "physical_count": 92,
    "variance": -3,
    "variance_status": "SHORTAGE"
  }
}
```

**Important:**
- If variance > 0: Surplus (creates positive ADJUSTMENT transaction)
- If variance < 0: Shortage (creates negative ADJUSTMENT transaction)
- Stock is NEVER overwritten, adjustments are recorded as transactions

#### Get Stock Adjustments
```
GET /api/inventory/adjustments?period_id=1
Authorization: Bearer <token>

Response:
{
  "success": true,
  "data": {
    "adjustments": [...]
  }
}
```

#### Get Variance Report
```
GET /api/inventory/variance-report?period_id=1
Authorization: Bearer <token>

Response:
{
  "success": true,
  "data": {
    "report": [
      {
        "id": 1,
        "name": "Paracetamol 500mg",
        "system_quantity": 95,
        "physical_quantity": 92,
        "variance": -3,
        "status": "SHORTAGE",
        "recorded_by": "Admin User",
        "created_at": "2026-02-07 14:20:00"
      }
    ]
  }
}
```

---

### Audit Logs (Immutable Traceability)

#### Get Entity Audit Logs
```
GET /api/audit-logs?entity_type=products&entity_id=1
Authorization: Bearer <token>

Response:
{
  "success": true,
  "data": {
    "logs": [
      {
        "id": 1,
        "user_id": 1,
        "action": "UPDATE_PRODUCT",
        "entity_type": "products",
        "entity_id": 1,
        "old_value": {...},
        "new_value": {...},
        "ip_address": "192.168.1.100",
        "timestamp": "2026-02-07 14:20:00"
      }
    ]
  }
}
```

#### Get Recent Logs
```
GET /api/audit-logs
Authorization: Bearer <token>

Returns last 100 audit entries
```

---

## Data Integrity Rules

### 1. Stock Calculation (NEVER Direct Edit)

```
current_stock = opening_stock + SUM(transaction effects)

Where transaction effects:
  PURCHASE     → +quantity
  SALE         → -quantity
  ADJUSTMENT   → ±quantity
  REVERSAL     → negative of referenced transaction
  VOID         → no effect
```

### 2. Transaction Types

| Type | Effect | When |
|------|--------|------|
| PURCHASE | +stock | When buying from supplier |
| SALE | -stock | When selling to customer (committed) |
| ADJUSTMENT | ±stock | Stock taking variance correction |
| REVERSAL | negative of original | Correction for previous transaction |
| VOID | none | Mark transaction as invalid |

### 3. Period Locking

**OPEN Period:**
- Can record all transaction types
- Can edit draft sales
- Can create products

**CLOSED Period:**
- Cannot record new transactions
- Cannot edit or delete anything
- Can ONLY record reversals for corrections
- All data is read-only

### 4. Sales Workflow

1. **Draft Phase** (no stock impact)
   - Create draft
   - Add items
   - Edit/delete items
   - Cancel draft

2. **Commitment** (atomic operation)
   - Validate stock availability
   - Create SALE transactions for each item
   - Atomically update product stock
   - Delete draft

3. **Correction** (post-commitment)
   - Create REVERSAL transaction
   - Original SALE remains immutable
   - Stock automatically recalculated

### 5. Role Permissions

**Cashier:**
- Create draft sales
- Add/remove sale items
- Commit sales
- View products and stock
- View transaction history

**Admin:**
- Everything cashier can do
- Create/update/deactivate products
- Record purchases
- Reverse any transaction
- Manage periods (create, close)
- view audit logs
- Stock taking

---

## Error Handling

### Response Formats

**Success:**
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {...},
  "timestamp": "2026-02-07 12:30:45"
}
```

**Error:**
```json
{
  "success": false,
  "message": "Error description",
  "timestamp": "2026-02-07 12:30:45"
}
```

**Validation Error:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field_name": "Error message"
  },
  "timestamp": "2026-02-07 12:30:45"
}
```

### HTTP Status Codes

- `200` - Success
- `201` - Created
- `400` - Bad request
- `401` - Unauthorized (not authenticated)
- `403` - Forbidden (missing permission)
- `404` - Not found
- `409` - Conflict (e.g., insufficient stock)
- `422` - Validation error
- `500` - Server error

---

## Security & Best Practices

### Authentication

- JWT tokens valid for 24 hours
- Include token in `Authorization: Bearer <token>` header
- Server re-validates role on every request

### Data Protection

- All stock-affecting operations are atomic
- Transactions immediately logged
- Failed operations trigger rollback
- No silent failures

### Audit Trail

Every state-changing action is logged with:
- User responsible
- Action performed
- Timestamp (server-side)
- Old value → New value
- IP address

---

## Testing the API

### Using cURL

```bash
# Login
curl -X POST http://localhost/topinv/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"cashier1","password":"password"}'

# Get token from response, then use it

# Get products
curl http://localhost/topinv/api/products \
  -H "Authorization: Bearer <token>"

# Create draft sale
curl -X POST http://localhost/topinv/api/sales/draft \
  -H "Authorization: Bearer <token>"

# Add item to draft
curl -X POST http://localhost/topinv/api/sales/draft/1/items \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"product_id":1,"quantity":5,"unit_price":25.00}'

# Commit sale
curl -X POST http://localhost/topinv/api/sales/commit \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"draft_id":1,"period_id":1}'
```

---

## Frontend Integration

The API is designed to work with the Vue/React frontend. Frontend updates from the API:

```javascript
// Example: Get current user
async function loginAndGetToken(username, password) {
  const response = await fetch('/topinv/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username, password })
  });
  const data = await response.json();
  localStorage.setItem('token', data.data.token);
  return data.data.user;
}

// Example: Create draft sale
async function createDraftSale() {
  const token = localStorage.getItem('token');
  const response = await fetch('/topinv/api/sales/draft', {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${token}` }
  });
  return await response.json();
}
```

---

## Environment

- **PHP:** 7.4+
- **MySQL/MariaDB:** 10.4+
- **Server:** Apache 2.4+ with mod_rewrite
- **Database:** `topinv` (auto-created by setup.php)

---

## Support & Troubleshooting

For issues:
1. Check error message in API response
2. Review audit logs: `GET /api/audit-logs`
3. Verify period status: `GET /api/periods`
4. Check stock calculations: `GET /api/products/:id`
