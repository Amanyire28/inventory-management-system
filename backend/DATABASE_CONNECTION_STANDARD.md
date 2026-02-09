# Database Connection Standard

## ✅ Current Standard (USE THIS)

All database connections now use the **Database singleton pattern**:

```php
// Include the Database class
require_once __DIR__ . '/path/to/core/Database.php';

// Get database connection
$db = Database::getInstance()->getConnection();

// Or for direct usage
$conn = Database::getInstance()->getConnection();
```

## 📁 File Structure

### Active Files:
- **`backend/core/Database.php`** - Main Database singleton class (✅ USE THIS)
- **`backend/config.php`** - Database configuration constants only (DB_HOST, DB_USER, DB_PASS, DB_NAME)

### Deprecated Files:
- **`backend/config/database.php.deprecated`** - Old redundant connection file (❌ DO NOT USE)

## 🔧 How It Works

1. **Database.php** creates a single connection instance (singleton pattern)
2. **config.php** provides the database credentials as constants
3. All API files use `Database::getInstance()->getConnection()`

## 📋 Benefits

✅ **Single connection** - No multiple connections wasting resources  
✅ **Prepared statements** - Helper methods for secure queries  
✅ **Error handling** - Centralized database error management  
✅ **Consistency** - All files use the same pattern  

## 🔄 Migration Complete

The following files have been updated to use the standard:

### API Endpoints (backend/api/)
- ✅ auth.php
- ✅ products.php  
- ✅ dashboard.php
- ✅ transactions.php
- ✅ sales.php
- ✅ periods.php
- ✅ audit-logs.php

### Main API (api/)
- ✅ index.php (already using Database singleton)

## 📝 Code Examples

### Simple Query
```php
$db = Database::getInstance()->getConnection();
$result = $db->query("SELECT * FROM products WHERE status = 'active'");
```

### Prepared Statement (Recommended)
```php
$db = Database::getInstance();
$products = $db->fetchAll(
    "SELECT * FROM products WHERE category = ?",
    [$category]
);
```

### Insert with ID Return
```php
$db = Database::getInstance();
$productId = $db->insert(
    "INSERT INTO products (name, price) VALUES (?, ?)",
    [$name, $price]
);
```

## ⚠️ Important Notes

- **Never** create new mysqli connections directly
- **Always** use `Database::getInstance()`
- **Prefer** the helper methods (fetchAll, fetch, insert, execute) over raw queries
- **Use** prepared statements for all user input

## 🚀 Last Updated

**Date**: February 9, 2026  
**Status**: Database connections standardized  
**Issues Fixed**: 7 files updated, 1 deprecated file removed
