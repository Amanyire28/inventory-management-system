# Clinic Inventory Management System - Dashboard Design Specification

**System Philosophy**: Transaction-append-only with audit trails, role-based separation of duties, and locked historical periods.

---

## Part 1: Design Principles & Architecture

### Core Principles
1. **Data Integrity First**: Stock values are derived from transactions, never manually edited
2. **Transparent Auditability**: Every action is logged with timestamp, user, and reason
3. **Role Separation**: Cashier ≠ Admin responsibilities (enforced at UI and backend)
4. **Historical Immutability**: Closed periods are read-only; corrections append new transactions
5. **Prevent Invalid Actions**: UI disables actions before users attempt them

### Transaction Flow Model
```
PURCHASES (Admin only)
    ↓
SYSTEM STOCK (calculated)
    ↓
SALES (Cashier records)
    ↓
STOCK TAKING (Admin reconciliation)
    ↓
ADJUSTMENTS (if variance)
    ↓
PERIOD CLOSURE (Admin locks)
    ↓
AUDIT TRAIL (immutable log)
```

### Access Control Matrix

| Action | Cashier | Admin |
|--------|---------|-------|
| Search Products | ✅ | ✅ |
| View Current Stock | ✅ | ✅ |
| Record Sales | ✅ | ❌ |
| View Sales History | Limited (own) | ✅ Full |
| Record Purchases | ❌ | ✅ |
| Add/Edit Products | ❌ | ✅ |
| Stock Taking | ❌ | ✅ |
| Period Closure | ❌ | ✅ |
| Void Transactions | ✅ (time-limited) | ✅ (with reason) |
| View Audit Log | ❌ | ✅ |

---

## Part 2: CASHIER DASHBOARD

### 2.1 Dashboard Overview

**Purpose**: Fast, focused sales entry with minimum friction and maximum safety.

**Main Screen Layout**:
```
┌─────────────────────────────────────────────────────────┐
│  TOPINV Cashier Dashboard          [Cashier: John Doe]  │
├─────────────────────────────────────────────────────────┤
│                                                           │
│  ┌─ QUICK SALE ENTRY ──────────────────────────────┐   │
│  │                                                  │   │
│  │  🔍 Search Products: [_______________]  [🔍]    │   │
│  │     (search by name or product code)            │   │
│  │                                                  │   │
│  │  Selected: Paracetamol 500mg (PC-500) ✓         │   │
│  │  Selling Price: 50.00 (read-only)               │   │
│  │  Stock Available: 245 units                      │   │
│  │                                                  │   │
│  │  Quantity: [__5__]  ⬆ ⬇                         │   │
│  │                                                  │   │
│  │  Line Total: 250.00                             │   │
│  │                                                  │   │
│  │  [+ Add Another] [Remove]  [Clear All]          │   │
│  │                                                  │   │
│  └──────────────────────────────────────────────────┘   │
│                                                           │
│  ┌─ SALE ITEMS ──────────────────────────────────────┐  │
│  │ Product      | Qty | Price | Total | Actions     │  │
│  │ Paracetamol  |  5  | 50.00 | 250   | [✎] [✗]    │  │
│  │              |     |       |       |             │  │
│  └──────────────────────────────────────────────────────┘ │
│                                                           │
│  ┌─ SALE SUMMARY ────────────────────────────────────┐  │
│  │ Subtotal:          250.00                        │  │
│  │ Tax (if applicable): 0.00                        │  │
│  │ TOTAL:             250.00                        │  │
│  │                                                  │  │
│  │ Payment Method: [Dropdown: Cash / Card / Check] │  │
│  │                                                  │  │
│  │ [⏳ VOID SALE - 15 min]  [✓ COMPLETE SALE]     │  │
│  └──────────────────────────────────────────────────────┘ │
│                                                           │
└─────────────────────────────────────────────────────────┘

SIDEBAR (Left Navigation):
┌────────────────┐
│ Cashier Menu   │
├────────────────┤
│ 📋 New Sale    │ (active)
│ 🔍 Search      │
│ 📜 My Receipt  │
│ 📊 Today's     │
│    Summary     │
│ 👤 Account     │
│ 🚪 Logout      │
└────────────────┘
```

### 2.2 Key UI Sections

#### A. Product Search Section
- **Input Field**: "Search by product name or code"
- **Live Suggestions**: Dropdown with matching products (name, code, stock status)
- **Display on Select**:
  - Product name and code
  - Current stock level
  - Selling price (locked/read-only, shows "as per system")
  - Stock status indicator (🟢 In Stock / 🟡 Low / 🔴 Out of Stock)

#### B. Quantity Input
- **Input Field**: Accept only positive integers
- **Visual Controls**: ⬆ ⬇ buttons to adjust
- **Validation**:
  - Max quantity = current stock
  - If user enters qty > stock: Red border + warning "Not enough stock. Max: 245"
  - Clear validation: Green border + checkmark when valid
- **Error States**:
  - Quantity = 0: "Remove this item instead"
  - Quantity > Stock: "Cannot exceed available stock"

#### C. Sales Items Table
- **Columns**: Product | Qty | Price | Total | Actions
- **Edit Icon** (✎): Opens inline qty editor (not price)
- **Delete Icon** (✗): Removes item from draft
- **Running Total**: Updates automatically

#### D. Sale Summary Panel
- **Display Only**:
  - Subtotal (sum of all line items)
  - Tax (if calculated automatically)
  - Grand Total
- **Payment Method Selector**: Dropdown (Cash / Card / Check / Other)
- **Never shows discount fields** (admins control pricing, not cashiers)

#### E. Action Buttons
```
[⏳ VOID SALE - 15 min]       [✓ COMPLETE SALE]
(conditional: appears if     (always available after
sale is already recorded      adding one item)
within last 15 minutes)
```

### 2.3 UX Flows

#### Flow 1: Record a Sale (Happy Path)
```
1. Cashier clicks "New Sale" (default page)
2. Searches product "Paracetamol"
3. Clicks on result from dropdown
   → Product auto-fills with stock indicator
4. Enters quantity: 5
   → System validates (5 ≤ 245 stock) ✓
   → Green checkmark appears
5. Clicks "+ Add Another" (optional)
   → Adds another product to same sale
6. Reviews Sale Summary (total: 250.00)
7. Selects Payment Method: "Cash"
8. Clicks "✓ COMPLETE SALE"
   → System shows success message
   → Receipt preview or print dialog
   → Sale recorded in database with timestamp
   → Receipt returned to cashier
   → Draft cleared automatically
   → Display confirmation: "Sale #SAL-2026-0247 completed"
```

#### Flow 2: Correct Before Completing (Draft Correction)
```
1. Cashier enters 5 units of product
2. Realizes it should be 3 units
3. Clicks [✎] edit icon next to product
4. Changes qty to 3
5. Total updates: 150.00
6. Clicks "✓ COMPLETE SALE"
   → Proceeds normally
   → No "void" needed - just corrected draft
```

#### Flow 3: Stock Insufficient
```
1. Cashier searches "Antibiotic X"
2. Clicks on product (stock shows: 2 units)
3. Enters quantity: 10
   → Red border appears on qty field
   → Warning message: "❌ Not enough stock"
   → Warning text: "Available: 2 units. Max qty: 2"
   → "✓ COMPLETE SALE" button DISABLED
4. Cashier adjusts qty to 2
   → Green checkmark, warning disappears
   → Button ENABLED
5. Proceeds to complete
```

#### Flow 4: Void a Recently Completed Sale
```
1. Sale completed 8 minutes ago (shown in receipt)
2. Cashier clicks "My Receipt" or views today's sales
3. Finds recent sale in list
4. Clicks [⏳ VOID] button (only visible if < 15 min old)
5. System shows dialog:
   "Confirm void this sale?
    Item: Paracetamol x5 (250.00)
    Recorded: 10:35 AM (8 min ago)
    ⚠️ You can void for the next 7 minutes
    [Cancel] [Void Transaction]"
6. Cashier clicks [Void Transaction]
   → Stock reversal recorded
   → Sale marked as voided in system
   → New transaction created: "VOID-SAL-2026-0247"
   → Confirmation: "Sale voided. Stock returned to inventory."
```

### 2.4 Status Indicators & Warnings

| Indicator | Meaning | Color |
|-----------|---------|-------|
| 🟢 In Stock | Stock > minimum threshold | Green |
| 🟡 Low Stock | Stock between min and 0 | Yellow |
| 🔴 Out of Stock | Stock = 0 | Red |

**Warning Messages** (non-technical, action-oriented):
- ❌ "Not enough stock" (instead of "SKU qty exceeds available")
- ⚠️ "This sale can only be voided for 15 minutes"
- ✓ "Sale completed successfully. Receipt ready."

### 2.5 Today's Sales Summary (Optional)

**Sidebar Widget or Separate Tab**:
```
┌─ TODAY'S PERSONAL SALES ─────────┐
│ Total Transactions: 24           │
│ Total Revenue:      12,450.00    │
│ Time Period:        06:00 - Now  │
│                                  │
│ Recent Sales:                    │
│ 10:35 - Paracetamol x5  250.00  │
│ 10:40 - Aspirin x10     150.00  │
│ 10:42 - Antibiotic x3   450.00  │
│ ... (show last 10)               │
└──────────────────────────────────┘
```

### 2.6 Restrictions Enforced at UI Level

| Action | Display | Behavior |
|--------|---------|----------|
| Edit Price | ❌ Hidden | Selling price is locked read-only |
| Create Product | ❌ Hidden | No "New Product" option |
| Edit Stock Manually | ❌ Hidden | Stock is auto-calculated |
| Delete Sale | ❌ Hidden after window | Only void available (which creates new transaction) |
| Edit Old Sale | ❌ Hidden | Only voiding allowed (time-limited) |

---

## Part 3: ADMIN DASHBOARD

### 3.1 Dashboard Overview

**Purpose**: Full system control, visibility, and accountability.

**Main Screen Layout**:
```
┌──────────────────────────────────────────────────────────────┐
│ TOPINV Admin Dashboard          [Admin: Dr. Manager]         │
├──────────────────────────────────────────────────────────────┤
│                                                                │
│ ┌─ QUICK NAVIGATION TABS ──────────────────────────────────┐ │
│ │ 📊 Overview | 📦 Inventory | 🛒 Purchases | 💳 Sales   │ │
│ │ 📋 Stock Taking | 📅 Period Mgmt | 🔍 Audit Log        │ │
│ └──────────────────────────────────────────────────────────┘ │
│                                                                │
│ ┌─ ACTIVE PERIOD: January 2026 ─────────────────────────┐   │
│ │ Status: [🟢 OPEN] [Lock Period]  [Close Month]        │   │
│ │ Days Running: 6  |  Last Stock Taking: Jan 5, 2026   │   │
│ └────────────────────────────────────────────────────────────┘ │
│                                                                │
│ ┌─ KEY METRICS (Today) ──────────────────────────────────┐   │
│ │ ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌────────┐ │   │
│ │ │ Sales $  │  │ Purchases│  │ Units    │  │ Adjust │ │   │
│ │ │ 2,450    │  │ 1,200    │  │ Voided   │  │ Made   │ │   │
│ │ │          │  │          │  │ 12       │  │ 3      │ │   │
│ │ └──────────┘  └──────────┘  └──────────┘  └────────┘ │   │
│ └────────────────────────────────────────────────────────────┘ │
│                                                                │
│ ┌─ INVENTORY ALERTS ────────────────────────────────────┐    │
│ │ ⚠️  Low Stock (8 products):                           │    │
│ │     • Paracetamol 500mg: 5 units (reorder level: 50)  │    │
│ │     • Aspirin 100mg: 8 units (reorder level: 25)      │    │
│ │                                                        │    │
│ │ 🔴 Out of Stock (2 products):                         │    │
│ │     • Antibiotic X: 0 units                           │    │
│ │     • Vitamin D: 0 units                              │    │
│ │                                                        │    │
│ │ ⏰ Near Expiry (3 batches):                            │    │
│ │     • Exp: Feb 10, Syrup Y, Batch #SY-001: 15 units  │    │
│ └────────────────────────────────────────────────────────────┘ │
│                                                                │
│ ┌─ RECENT TRANSACTIONS ─────────────────────────────────┐    │
│ │ Type  | Date/Time          | Product     | Qty | By   │    │
│ │ Sale  | Jan 6, 2026 10:35  | Paracetamol | 5   | John │    │
│ │ Void  | Jan 6, 2026 10:43  | Paracetamol | 5   | John │    │
│ │ Purch | Jan 6, 2026 09:15  | Aspirin     | 100 | Self │    │
│ │ Adj   | Jan 5, 2026 14:20  | Vitamin C   | -2  | Self │    │
│ └────────────────────────────────────────────────────────────┘ │
│                                                                │
└──────────────────────────────────────────────────────────────┘

SIDEBAR (Left Navigation):
┌──────────────────┐
│ Admin Menu       │
├──────────────────┤
│ 📊 Dashboard     │ (active)
│ 📦 Products      │
│ 🛒 Purchases     │
│ 💳 Sales         │
│ 📋 Stock Taking  │
│ 📅 Period Mgmt   │
│ 🔍 Audit Log     │
│ ⚙️  Settings     │
│ 👤 Account       │
│ 🚪 Logout        │
└──────────────────┘
```

### 3.2 Dashboard Sections

#### A. Inventory Overview Tab (📦)

**Current Stock View**:
```
┌─ INVENTORY OVERVIEW ──────────────────────────────────────┐
│ Filter: [Status ▼] [Category ▼] [Search: _________]      │
│ Export: [CSV] [PDF]                                      │
│                                                           │
│ Product | Code | Qty | Reorder Level | Status | Actions  │
│─────────────────────────────────────────────────────────│
│ Paracetamol 500mg | PC-500 | 245 | 50 | 🟢 | [Details] │
│ Aspirin 100mg | AS-100 | 32 | 25 | 🟡 | [Details] │
│ Antibiotic X | AB-X | 0 | 20 | 🔴 | [Details] │
│ ... (sortable, filterable)                              │
│                                                           │
│ [Pagination: 1-25 of 156 products]                       │
└─────────────────────────────────────────────────────────────┘
```

**Product Detail Popup/Modal**:
```
┌─ PARACETAMOL 500MG (PC-500) ─────────────────────┐
│                                                    │
│ Current System Stock: 245 units                  │
│ Last Updated: Jan 6, 2026, 10:43 AM              │
│                                                    │
│ ┌─ Stock Breakdown ─────────────────────────────┐│
│ │ Jan 1 Start: 200                              ││
│ │ + Purchases: +100                             ││
│ │ - Sales: -55                                  ││
│ │ - Adjustments: -0                             ││
│ │ = Current: 245 ✓                              ││
│ └────────────────────────────────────────────────┘│
│                                                    │
│ [View Transaction History] [Export]              │
│                                                    │
│ [Close]                                          │
└────────────────────────────────────────────────────┘
```

#### B. Product Management Tab

**Products List**:
```
┌─ PRODUCT MANAGEMENT ───────────────────────────────────┐
│ [+ ADD NEW PRODUCT]                                    │
│                                                        │
│ Prod | Code | Selling | Cost | Reorder | Status | Act │
│──────────────────────────────────────────────────────│
│ Para | PC-500 | 50.00 | 30.00 | 50 | 🟢 Active | [✎][★]│
│ Aspir| AS-100 | 40.00 | 25.00 | 25 | 🟢 Active | [✎][★]│
│ Antic| AB-X | 150.00 | 100.00 | 20 | 🔴 Inactive | [✎][★]│
│ ... (show all products, active + inactive)             │
└────────────────────────────────────────────────────────┘

Actions:
[✎] = Edit product details
[★] = Toggle active/inactive (deactivate, not delete)
```

**Add/Edit Product Form**:
```
┌─ ADD NEW PRODUCT ─────────────────────────┐
│                                           │
│ Product Name: [________________]          │
│ Product Code: [________________]          │
│ Category: [Dropdown: Pain Relief / ...] │
│ Selling Price: [________]                │
│ Cost Price: [________]                   │
│ Reorder Level: [____] units              │
│ Expiry Tracking: [Dropdown: Required / Optional] │
│ Active: [✓ Toggle]                       │
│                                           │
│ [Cancel]  [Save Product]                 │
│                                           │
│ Note: Cannot edit once products          │
│ are involved in transactions              │
└───────────────────────────────────────────┘
```

**Edit Restrictions**:
- ✅ Can edit: Name, Selling Price, Cost Price, Reorder Level, Category
- ❌ Cannot edit (locked): Product Code (once created), Category (if used in transactions)
- ✅ Can deactivate/reactivate product
- ❌ Cannot hard-delete (maintains audit trail)

#### C. Purchases Module Tab (🛒)

**Purchase List**:
```
┌─ PURCHASES ────────────────────────────────────────────┐
│ Filter: [Date Range] [Supplier] [Status ▼]            │
│ [+ RECORD NEW PURCHASE]                               │
│                                                        │
│ Date | Product | Qty | Supplier | Cost | Status | Act │
│───────────────────────────────────────────────────────│
│ 01/06 | Paracetamol | 100 | Pharma Ltd | 3000 | ✓ | [V]│
│ 01/05 | Aspirin | 50 | MedSupply | 1250 | ✓ | [V] │
│ 01/02 | Vitamin C | 200 | ChemCo | 2000 | ✓ | [V] │
│ ...                                                     │
│                                                        │
│ [V] = View details                                     │
└────────────────────────────────────────────────────────┘
```

**Record Purchase Form**:
```
┌─ RECORD PURCHASE ─────────────────────────────┐
│                                               │
│ Purchase Date: [Jan 6, 2026] [📅]            │
│ Product: [Dropdown: Search products]         │
│ Quantity Received: [____] units              │
│ Cost per Unit: [____]                        │
│ Total Cost: [auto-calculated] (read-only)   │
│ Supplier: [Text: MedSupply / ________]       │
│ Batch/Lot Number: [____________]             │
│ Expiry Date (if applicable): [____/____/____]│
│ Invoice Reference: [____________]            │
│ Notes: [_______________________________]     │
│                                               │
│ [Cancel]  [Record Purchase]                  │
│                                               │
│ ✓ Stock will be updated automatically        │
└───────────────────────────────────────────────┘
```

**Purchase View (Locked)**:
```
Any displayed purchase:
[🔒 This purchase is locked and cannot be edited]
[View original receipt] [View transaction impact]
```

#### D. Sales Management Tab (💳)

**Sales List with Filters**:
```
┌─ SALES MANAGEMENT ──────────────────────────────────────┐
│ Filters: [Date Range] [Cashier ▼] [Product ▼]          │
│ [Export: CSV] [Print Report]                            │
│                                                          │
│ # | Date/Time | Cashier | Product | Qty | Total | Stat │
│──────────────────────────────────────────────────────────│
│ 1 | 06/01 10:35 | John | Paracetamol | 5 | 250 | ✓ Sale │
│ 2 | 06/01 10:40 | John | Aspirin | 10 | 400 | ✓ Sale │
│ V | 06/01 10:43 | John | Paracetamol | 5 | 250 | 🔄 Void│
│ 3 | 05/01 14:20 | Mary | Vitamin | 2 | 100 | ✓ Sale │
│ ... (show all with status indicator)                    │
│                                                          │
│ [Details: Click any row]                                │
└──────────────────────────────────────────────────────────┘
```

**Sale Details (Read-Only)**:
```
┌─ SALE #SAL-2026-0247 ──────────────────────┐
│ Status: ✓ COMPLETED                        │
│ Date/Time: Jan 6, 2026 - 10:35 AM         │
│ Cashier: John Doe                          │
│ Payment: Cash                              │
│                                            │
│ ┌─ Items ────────────────────────────────┐│
│ │ Paracetamol 500mg                      ││
│ │   Qty: 5 units                         ││
│ │   Price: 50.00 per unit                ││
│ │   Total: 250.00                        ││
│ └────────────────────────────────────────┘│
│                                            │
│ Subtotal: 250.00                          │
│ Tax: 0.00                                 │
│ Total: 250.00                             │
│                                            │
│ Audit Trail:                              │
│ • Created: Jan 6, 10:35 by John Doe       │
│ • Stock impact: -5 units (Paracetamol)   │
│                                            │
│ [Print Receipt] [View Void History]       │
│ [Reverse Sale] (shows dialog below)       │
└────────────────────────────────────────────┘

Optional: Reverse Sale Dialog
┌─ REVERSE SALE #SAL-2026-0247 ──────────────┐
│ ⚠️  This will create a new reversal        │
│    transaction, not delete the original.   │
│                                            │
│ Reason for Reversal:                       │
│ [Dropdown: Wrong product / Customer       │
│  returned / Price error / Other ▼]        │
│                                            │
│ Additional Notes:                          │
│ [_________________________________]        │
│                                            │
│ [Cancel]  [Confirm Reversal]              │
└────────────────────────────────────────────┘
```

**Voidable vs. Reversible**:
- **Void** (if < 15 min old): Single "VOID-" transaction negates the original
- **Reverse** (if older): Creates new "REV-" transaction + corrected transaction; both linked

#### E. Stock Taking Module Tab (📋)

**Stock Taking Workflow**:

**Step 1: Initialize Stock Taking**
```
┌─ START STOCK TAKING ──────────────────────┐
│                                           │
│ Period: January 2026                      │
│ Stock Taking Date: [Jan 5, 2026] [📅]   │
│ Technician: [Self] (read-only)           │
│                                           │
│ ⚠️  Stock taking will:                    │
│ • Lock sales recording temporarily        │
│ • Create adjustment transactions if var   │
│ • Must be reviewed & locked before use   │
│                                           │
│ [Start Stock Taking]                      │
└───────────────────────────────────────────┘
```

**Step 2: Physical Count Entry**
```
┌─ PHYSICAL COUNT - STOCK TAKING SESSION ──────┐
│ Status: IN PROGRESS                          │
│ Items counted: 15 / 156                      │
│ Time Started: Jan 5, 14:00                   │
│                                              │
│ Filter: [Category ▼] [Search: ___________]  │
│                                              │
│ Product | Code | System Qty | Physical | Var│
│────────────────────────────────────────────│
│ Paracetamol | PC-500 | 245 | [____] | --   │
│ Aspirin | AS-100 | 32 | [____] | --   │
│ Vitamin C | VC-250 | 156 | [____] | --   │
│ ...                                          │
│                                              │
│ [Save Progress] [Submit Counting]           │
│ [⚠️  Exit without saving]                    │
└──────────────────────────────────────────────┘
```

**Step 3: Variance Review & Adjustment**
```
┌─ VARIANCE ANALYSIS ───────────────────────────┐
│ Total Products: 156                          │
│ Variances Found: 3                           │
│                                              │
│ Product | Sys Qty | Phys | Var | Reason    │
│─────────────────────────────────────────────│
│ Aspirin | 32 | 30 | -2 | [Dropdown:       │
│ | | | | Damage/Expiry/Other] │
│ Vitamin | 156 | 160 | +4 | [Found in      │
│ | | | | storage]             │
│ Antibiotic | 0 | 0 | 0 | [Matches]       │
│                                              │
│ [Generate Adjustment Transactions]          │
│ [Review Before Confirm]                     │
│ [Cancel & Recount]                          │
└───────────────────────────────────────────────┘
```

**Step 4: Adjustment Confirmation**
```
┌─ CONFIRM ADJUSTMENTS ────────────────────────┐
│                                              │
│ Adjustments to Record:                       │
│ • Aspirin: -2 units (Damage)                │
│ • Vitamin: +4 units (Found)                 │
│                                              │
│ Adjustment Reason (global):                  │
│ [Dropdown: Stock Taking / Physical Loss /   │
│  Inventory Error / Other ▼]                  │
│                                              │
│ Notes:                                       │
│ [_________________________________]          │
│                                              │
│ [Cancel] [Lock & Confirm Stock Taking]      │
│                                              │
│ ✓ Once locked, cannot be changed             │
│ ✓ Adjustment transactions created            │
│ ✓ Period can now be closed                   │
└──────────────────────────────────────────────┘
```

**Stock Taking History**:
```
┌─ STOCK TAKING HISTORY ──────────────┐
│ Date | Technician | Status | Variances │
│─────────────────────────────────────│
│ Jan 5 | Self | ✓ Locked | 3 items │
│ Dec 31 | Self | ✓ Locked | 0 items │
│ Nov 30 | Mary | ✓ Locked | 5 items │
│ ... (show all records)               │
└─────────────────────────────────────┘
```

#### F. Period Management Tab (📅)

**Period Control Panel**:
```
┌─ INVENTORY PERIOD MANAGEMENT ──────────────────┐
│                                                │
│ ┌─ ACTIVE PERIOD ────────────────────────────┐│
│ │ Period: January 2026 🟢 OPEN              ││
│ │ Start Date: Jan 1, 2026                   ││
│ │ Days Running: 6                           ││
│ │                                            ││
│ │ Last Stock Taking: Jan 5, 2026 (✓ Locked)││
│ │ Adjustments Made: 3 transactions          ││
│ │                                            ││
│ │ [Perform Stock Taking] [Close Month]      ││
│ └────────────────────────────────────────────┘│
│                                                │
│ ┌─ PREVIOUS PERIODS ─────────────────────────┐│
│ │ Dec 2025 🔒 CLOSED                         ││
│ │   Start: Dec 1 | End: Dec 31 | Status: ✓  ││
│ │   Closing Stock: Dec 31 @ 23:59            ││
│ │   [View Details] [Reopen for Correction]   ││
│ │                                            ││
│ │ Nov 2025 🔒 CLOSED                         ││
│ │   Start: Nov 1 | End: Nov 30 | Status: ✓  ││
│ │   Closing Stock: Nov 30 @ 23:59            ││
│ │   [View Details] [Export Snapshot]         ││
│ │                                            ││
│ │ (Older periods not shown; use archive)    ││
│ └────────────────────────────────────────────┘│
└────────────────────────────────────────────────┘
```

**Close Month Workflow**:
```
Step 1: Confirm Stock Taking Locked
┌─ CLOSE JANUARY 2026 ───────────────────┐
│ ⚠️  PRE-CLOSE CHECKLIST:               │
│ ✓ Stock Taking: Completed & Locked    │
│ ✓ All Adjustments: Recorded           │
│ ? All Sales: Reconciled?              │
│ ? All Purchases: Recorded?            │
│                                       │
│ System Status:                        │
│ • Total Sales: 250,000 (125 txn)      │
│ • Total Purchases: 120,000 (45 txn)   │
│ • Total Adjustments: +1,200 (3 txn)   │
│                                       │
│ [Cancel] [Proceed to Close]           │
└────────────────────────────────────────┘

Step 2: Create Closing Snapshot
┌─ GENERATE CLOSING SNAPSHOT ───────────┐
│                                       │
│ Closing Date/Time: Jan 31, 2026       │
│                                  23:59│
│ Snapshot Type: End-of-Month           │
│                                       │
│ Snapshot will include:                │
│ • All products & quantities           │
│ • All transaction totals              │
│ • All transaction details             │
│ • Variance analysis                   │
│                                       │
│ [Cancel] [Create & Lock]              │
└────────────────────────────────────────┘

Step 3: Period Locked
┌─ JANUARY 2026 NOW LOCKED ─────────────┐
│ 🔒 Status: CLOSED                    │
│ Locked at: Jan 31, 2026 23:59        │
│ Locked by: Admin User                │
│                                       │
│ • All transactions: Read-only         │
│ • Opening stock for Feb: Set to       │
│   Jan closing stock                   │
│ • New transactions: Go to Feb 2026    │
│                                       │
│ [View Snapshot] [Archive] [Export]   │
└────────────────────────────────────────┘
```

#### G. Audit & Logs Tab (🔍)

**Audit Log View**:
```
┌─ AUDIT LOG & TRANSACTION HISTORY ────────────────┐
│ Filter: [Date Range] [User ▼] [Type ▼] [Period] │
│ [Export: CSV] [Print]                            │
│                                                  │
│ Timestamp | User | Type | Details | IP | Status │
│───────────────────────────────────────────────────│
│ 10:43 | John D | Sale VOID | SAL-0247 | 192... | ✓ │
│ 10:35 | John D | Sale | SAL-0247 | 192... | ✓ │
│ 10:15 | Admin | Purchase | PUR-156 | 192... | ✓ │
│ 09:50 | Admin | Adj | ADJ-045 (Stock) | 192... │ ✓ │
│ 09:30 | John D | Sale | SAL-0246 | 192... | ✓ │
│ ... (sortable, searchable)                       │
│                                                  │
│ [Row Click = Full Details]                       │
└──────────────────────────────────────────────────┘
```

**Detailed Audit Entry**:
```
┌─ TRANSACTION DETAIL: SAL-2026-0247 ────────────┐
│                                                │
│ Transaction ID: SAL-2026-0247                  │
│ Type: SALE                                     │
│ Status: ✓ COMPLETED                           │
│ Created: Jan 6, 2026 - 10:35:42 AM            │
│ User: John Doe (cashier_john)                  │
│ IP Address: 192.168.1.150                      │
│ Session ID: sess_abc123def456                  │
│                                                │
│ Transaction Details:                           │
│ • Product: Paracetamol 500mg                  │
│ • Quantity: 5 units                           │
│ • Price: 50.00 per unit                       │
│ • Total: 250.00                               │
│ • Payment: Cash                               │
│                                                │
│ Stock Impact:                                  │
│ Before: 250 units                             │
│ After: 245 units                              │
│ Change: -5 units                              │
│                                                │
│ Related Transactions:                          │
│ • VOID: VOID-SAL-2026-0247 (Jan 6, 10:43)    │
│   [Created by same user 8 minutes later]       │
│                                                │
│ [Close]                                        │
└────────────────────────────────────────────────┘
```

### 3.3 Admin-Only Actions & Restrictions

| Action | Allowed | Notes |
|--------|---------|-------|
| Add Product | ✅ Yes | Creates with code & prices |
| Edit Product Details | ✅ Yes | Name, prices, reorder level |
| Deactivate Product | ✅ Yes | Soft delete, keeps history |
| Record Purchases | ✅ Yes | Adds stock automatically |
| Record Adjustments | ✅ Yes | Via stock taking or manual |
| View All Sales | ✅ Yes | By any cashier, filtered |
| Void Recent Sales | ✅ Yes | Unrestricted (vs cashier 15 min) |
| Reverse Old Sales | ✅ Yes | Creates new transaction |
| Perform Stock Taking | ✅ Yes | Generate snapshots |
| Close Periods | ✅ Yes | Lock months |
| View Audit Log | ✅ Yes | All user activity |
| Delete Transaction | ❌ No | Only void/reverse |
| Edit Locked Period | ❌ No | Read-only |
| Manual Stock Edit | ❌ No | Only via transaction |

---

## Part 4: Navigation & Access Control

### 4.1 Role-Based Menu Structure

**CASHIER MENU** (Left Sidebar):
```
┌─ Cashier Portal ─────┐
│ 📋 New Sale          │
│ 🔍 Search            │
│ 📜 My Receipts       │
│ 📊 Today Summary     │
│ 👤 My Account        │
│ 🚪 Logout            │
└──────────────────────┘
```

**ADMIN MENU** (Left Sidebar):
```
┌─ Admin Portal ────────┐
│ 📊 Dashboard         │
│ 📦 Products          │
│ 🛒 Purchases         │
│ 💳 Sales             │
│ 📋 Stock Taking      │
│ 📅 Period Mgmt       │
│ 🔍 Audit Log         │
│ ⚙️  Settings         │
│ 👤 My Account        │
│ 🚪 Logout            │
└──────────────────────┘
```

### 4.2 Route Protection & Redirects

**Cashier attempting admin routes**:
```
URL: /admin/products
→ Redirects to: /cashier/dashboard
Display: "You do not have permission to access this page."
```

**Admin accessing cashier routes**:
```
URL: /cashier/new-sale
→ Allowed (can record sales as admin for testing)
But with notice: "You are viewing cashier interface"
```

### 4.3 Session Management

**Login Screen**:
```
┌─ TOPINV LOGIN ──────────────────┐
│                                 │
│ Username: [______________]      │
│ Password: [______________] 👁️   │
│                                 │
│ [Remember Me]                   │
│                                 │
│ [Login]                         │
│                                 │
│ Forgot Password? [Link]         │
│                                 │
│ System Status: 🟢 Online        │
│ Last backup: Jan 6, 23:00       │
└─────────────────────────────────┘
```

**Session Timeout**:
- After 30 minutes of inactivity: Auto-logout
- Warning at 25 minutes: "Your session will expire in 5 minutes"
- On relogin, show last activity timestamp

---

## Part 5: Data Integrity Controls (UI Level)

### 5.1 Time-Based Restrictions

**Edit Window Enforcement**:
```
IF transaction age < 15 minutes:
  → Show [Void] button + time remaining

ELSE IF transaction age < period close:
  → Show [Reverse] button (admin only)

ELSE IF period is closed:
  → Show 🔒 "This period is locked"
  → No editing allowed
  → Show lock date/time
```

**Visual Indicators**:
```
Recent Transaction (< 15 min):
┌─────────────────────────────┐
│ ⏳ VOIDAL UNTIL: 10:50 AM (7 min) │
│ [VOID]                      │
└─────────────────────────────┘

Old Transaction (> 15 min, < period close):
┌─────────────────────────────┐
│ Recorded: 10:35 AM (22 min ago) │
│ [REVERSE]                   │
└─────────────────────────────┘

Locked Period Transaction:
┌─────────────────────────────┐
│ 🔒 LOCKED (Period Closed)  │
│ Period closed: Dec 31, 2025 │
│ No edits allowed            │
└─────────────────────────────┘
```

### 5.2 Stock Validation

**Before Recording Sale**:
```
IF requested qty > current system stock:
  1. Disable [Complete Sale] button
  2. Show red border on qty field
  3. Display warning: "Not enough stock"
  4. Show: "Available: X units, Max qty: X"

IF requested qty = current system stock:
  1. Show yellow warning: "Only X units available"
  2. Allow completion (last units)

IF requested qty < current system stock:
  1. Show green checkmark
  2. Normal confirmation
```

**Before Recording Purchase**:
```
IF qty + current stock > max capacity:
  1. Show warning
  2. Show: "This will exceed max storage"
  3. Allow with admin confirmation
```

### 5.3 Multi-Step Confirmations

**For Destructive Actions**:
```
Action: Void Sale / Reverse Sale / Close Period

Show: 2-step confirmation
1. "Are you sure?" with details
2. Reason selection (required)
3. [Cancel] [Confirm]

System then:
1. Creates transaction record
2. Updates stock
3. Logs action with reason
4. Returns confirmation with ID
```

---

## Part 6: UX Flows & Interaction Patterns

### 6.1 Complete Sale Flow (Detailed)

```
START: Cashier Dashboard (New Sale)
  ↓
[1] User searches "Paracetamol"
  ↓ System returns dropdown: 
    - Paracetamol 500mg (250 in stock) ✓
    - Paracetamol 250mg (12 in stock) ✓
  ↓
[2] User clicks "Paracetamol 500mg"
  ↓ System displays:
    - Product name, code, price (locked)
    - Stock: 250 ✓
    - Qty input field: [__]
  ↓
[3] User enters qty: "5"
  ↓ System validates:
    - 5 ≤ 250? ✓ YES
    - Qty field border: GREEN
    - Line total: 50 × 5 = 250
  ↓
[4] User clicks [+ Add Another] (or skips)
  ↓ System adds row to sales table
  ↓
[5] User reviews sale summary
  ↓ System displays:
    - Subtotal: 250
    - Total: 250
    - Payment method: [Cash ▼]
  ↓
[6] User selects payment: "Cash"
  ↓
[7] User clicks [✓ COMPLETE SALE]
  ↓ System:
    1. Validates all items again
    2. Records sale transaction
    3. Updates stock (250 - 5 = 245)
    4. Logs: timestamp, user, items, total
    5. Generates receipt
  ↓
[8] System shows success:
  ✓ "Sale #SAL-2026-0247 completed"
  Receipt displayed (print/email options)
  ↓
[9] Draft cleared for next sale
  ↓
END: Ready for next customer
```

### 6.2 Void Sale Flow (After Recording)

```
START: Cashier views "My Receipts"
  ↓
[1] System shows today's sales list
  - Sale #SAL-2026-0247 (Paracetamol x5, 10:35 AM) ✓
  - [⏳ VOID - 15 min remaining]
  ↓
[2] User clicks [⏳ VOID] button
  ↓ System shows dialog:
  "Confirm Void?
   Item: Paracetamol x5 (250.00)
   Recorded: 10:35 (5 min ago)
   Can void for 10 more minutes
   [Cancel] [Void]"
  ↓
[3] User clicks [Void]
  ↓ System:
    1. Creates new transaction: VOID-SAL-2026-0247
    2. Reverses stock: 245 + 5 = 250
    3. Links void to original sale
    4. Logs action with timestamp
    5. Shows confirmation
  ↓
[4] System displays:
  ✓ "Sale voided successfully"
  Receipt status: "VOIDED"
  ↓
END: Stock restored, transaction locked in history
```

### 6.3 Reverse Old Sale Flow (Admin)

```
START: Admin views "Sales" tab
  ↓
[1] Admin finds old sale (> 15 min)
  - Sale #SAL-2025-1234 (Jan 2, 2026, 14:35)
  - [REVERSE] button visible
  ↓
[2] Admin clicks [REVERSE]
  ↓ System shows form:
  "Reverse Sale #SAL-2025-1234
   Item: Aspirin x10 (400.00)
   Original date: Jan 2, 2026, 14:35
   
   Reason: [Dropdown: Wrong price / Duplicate / ...]
   Notes: [Text field]
   
   ⚠️ This creates:
   1. REV- transaction (negates sale)
   2. New corrected sale (optional)
   [Cancel] [Create Reversal]"
  ↓
[3] Admin selects reason: "Wrong price"
  ↓
[4] Admin enters notes: "Customer charged 40 instead of 50"
  ↓
[5] Admin clicks [Create Reversal]
  ↓ System:
    1. Creates REV-SAL-2025-1234
    2. Reverses stock: -10 units
    3. Stores reason in audit trail
    4. Shows: "Create corrected sale now?"
  ↓
[6] Optional: Admin creates corrected sale
  ↓ System shows form:
  "Record Corrected Sale
   Product: Aspirin (auto-filled)
   Qty: 10 (auto-filled)
   Price: 50 (corrected from 40)
   [Create]"
  ↓
[7] System creates: SAL-2025-1234-CORR
  ↓
[8] Audit trail now shows:
  - Original: SAL-2025-1234
  - Reversal: REV-SAL-2025-1234
  - Corrected: SAL-2025-1234-CORR
  ↓
END: All transactions linked and transparent
```

### 6.4 Admin Close Period Flow

```
START: Admin navigates to "Period Mgmt"
  ↓
[1] System displays:
  Active Period: January 2026 (OPEN)
  Last Stock Taking: Jan 5 (✓ Locked)
  ↓
[2] Admin clicks [Close Month]
  ↓ System shows pre-close checklist:
  ✓ Stock Taking: Locked
  ✓ Adjustments: Recorded
  ? Other items reconciled?
  [Cancel] [Proceed]
  ↓
[3] Admin reviews summary:
  - Total Sales: 250,000 (125 transactions)
  - Total Purchases: 120,000 (45 transactions)
  - Adjustments: +1,200 (3 transactions)
  ↓
[4] Admin clicks [Proceed to Close]
  ↓ System shows confirmation:
  "Generate closing snapshot?
   - Locks entire January 2026
   - Creates immutable snapshot
   - Opening stock for Feb = Jan closing stock
   - Cannot undo without reopen request
   [Cancel] [Create & Lock]"
  ↓
[5] Admin clicks [Create & Lock]
  ↓ System:
    1. Creates end-of-month snapshot
    2. Marks all transactions as locked
    3. Prevents any edits in period
    4. Carries closing stock to next period
    5. Logs closure with timestamp
  ↓
[6] System displays:
  ✓ "January 2026 closed and locked"
  Snapshot ID: SNAP-JAN-2026
  Locked: Jan 31, 23:59
  ↓
[7] Previous period now shows:
  Jan 2026 🔒 CLOSED [View Snapshot] [Export]
  ↓
END: Period immutable; new transactions go to Feb
```

### 6.5 Stock Taking Flow (Detailed)

```
START: Admin navigates to "Stock Taking"
  ↓
[1] Admin clicks [Start Stock Taking]
  ↓ System shows:
  "Initialize Stock Taking
   Date: Jan 5, 2026
   Period: January 2026
   [Start]"
  ↓
[2] Admin clicks [Start]
  ↓ System:
    1. Creates stock-taking session
    2. Locks sales recording (optional)
    3. Displays count form
  ↓
[3] Admin (or warehouse staff) enters physical counts
  Product | System Qty | Physical | Action
  ─────────────────────────────────────
  Paracetamol | 245 | [___] | Enter count
  Aspirin | 32 | [___] |
  ... (repeat for each product)
  ↓
[4] As counts entered, system calculates:
  Paracetamol: 245 (system) vs 243 (physical) = -2 variance
  ↓
[5] When all products counted, admin clicks [Submit]
  ↓ System shows variance analysis:
  Products with variance:
  - Aspirin: -2 (expected: 32, found: 30)
  - Vitamin C: +4 (expected: 156, found: 160)
  
  [Need adjustment reasons]
  ↓
[6] Admin selects reason for each variance:
  Aspirin -2: [Damage / Expired / Other]
  Vitamin C +4: [Found in storage / Transfer error]
  ↓
[7] Admin clicks [Generate Adjustments]
  ↓ System creates:
    - ADJ-001: Aspirin -2 (Damage)
    - ADJ-002: Vitamin C +4 (Found)
  ↓
[8] System shows confirmation:
  ✓ Adjustments recorded
  Stock now reflects physical count
  ✓ Stock taking can be locked
  [Lock & Close Stock Taking]
  ↓
[9] Admin clicks [Lock]
  ↓ System:
    1. Finalizes all adjustments
    2. Marks stock taking as locked
    3. Period ready for closure
    4. Sales recording unlocked (if was locked)
  ↓
END: Stock reconciled; audit trail complete
```

---

## Part 7: System Timestamps & Audit

### 7.1 Every Transaction Includes

```
{
  transaction_id: "SAL-2026-0247",
  type: "SALE",
  timestamp_created: "2026-01-06 10:35:42",
  timestamp_locked: null,  // Set on period close
  user_id: "cashier_john",
  user_name: "John Doe",
  ip_address: "192.168.1.150",
  session_id: "sess_abc123",
  
  details: {
    product_id: "PC-500",
    product_name: "Paracetamol 500mg",
    quantity: 5,
    unit_price: 50.00,
    total_amount: 250.00
  },
  
  stock_impact: {
    product_id: "PC-500",
    qty_before: 250,
    qty_after: 245,
    change: -5
  },
  
  audit_trail: [
    { action: "created", by: "cashier_john", at: "10:35:42" },
    { action: "voided", by: "cashier_john", at: "10:43:15", reason: null }
  ]
}
```

### 7.2 Void Transaction Record

```
{
  transaction_id: "VOID-SAL-2026-0247",
  type: "VOID",
  relates_to: "SAL-2026-0247",
  timestamp_created: "2026-01-06 10:43:15",
  user_id: "cashier_john",
  
  details: {
    original_transaction: "SAL-2026-0247",
    reason: null,  // Auto-void doesn't require reason
    notes: null
  },
  
  stock_impact: {
    product_id: "PC-500",
    qty_before: 245,
    qty_after: 250,
    change: +5  // Reverses original
  }
}
```

### 7.3 Reversal Transaction Record

```
{
  transaction_id: "REV-SAL-2025-1234",
  type: "REVERSAL",
  relates_to: "SAL-2025-1234",
  timestamp_created: "2026-01-06 14:20:35",
  user_id: "admin_user",
  
  details: {
    original_transaction: "SAL-2025-1234",
    reason: "wrong_price",
    notes: "Customer charged 40 instead of 50"
  },
  
  stock_impact: {
    product_id: "AS-100",
    qty_before: 32,
    qty_after: 42,
    change: +10  // Reverses original sale
  }
}
```

---

## Part 8: Error Handling & Messages

### 8.1 User-Friendly Error Messages

| System Error | User-Facing Message |
|--------------|-------------------|
| Insufficient stock | "Not enough stock. Available: X units." |
| Duplicate entry | "This product is already in this sale. Use qty field instead." |
| Invalid quantity | "Please enter a number between 1 and available stock." |
| Session expired | "Your session expired. Please login again." |
| Stock taking incomplete | "Cannot close period until stock taking is locked." |
| Product not found | "No products match your search. Try another term." |
| Period locked | "This period is locked. Cannot make changes." |
| Transaction too old | "Can only void sales within 15 minutes. Use reverse instead." |

### 8.2 Form Validation (UI + Backend)

**Cashier Sale Entry**:
```
Field: Quantity
Input: "abc"
→ UI shows: Red border, "Please enter a number"
→ [Complete Sale] disabled until fixed

Field: Quantity
Input: "251"
→ UI shows: Red border, "Available: 250. Max qty: 250"
→ [Complete Sale] disabled

Field: Product
Input: "" (empty/not selected)
→ UI shows: "Select a product to proceed"
→ [Complete Sale] disabled
```

**Admin Product Entry**:
```
Field: Selling Price
Input: "-50"
→ UI shows: "Price must be positive"

Field: Code
Input: "" (empty)
→ UI shows: "Product code is required"

Field: Reorder Level
Input: "not_a_number"
→ UI shows: "Please enter a valid quantity"
```

### 8.3 Permission-Based Messages

```
Cashier attempts to access Admin Panel:
↓ UI shows:
"You don't have permission to access this.
You are logged in as: Cashier
[Go to Cashier Dashboard]"
```

---

## Part 9: Dashboard Implementation Checklist

### Phase 1: Core Infrastructure
- [ ] User authentication & role assignment
- [ ] Session management (timeout at 30 min)
- [ ] Database transaction schema
- [ ] Audit logging system
- [ ] API endpoints (role-protected)

### Phase 2: Cashier Dashboard
- [ ] Product search functionality
- [ ] Quantity input with validation
- [ ] Sale draft & storage
- [ ] Complete sale recording
- [ ] Void transaction (time-limited)
- [ ] Receipt generation/printing
- [ ] My receipts view

### Phase 3: Admin Dashboard
- [ ] Inventory overview
- [ ] Product management (add/edit/deactivate)
- [ ] Purchase recording
- [ ] Sales view & filtering
- [ ] Sale reversal workflow
- [ ] Stock taking workflow
- [ ] Period management & closure

### Phase 4: Audit & Controls
- [ ] Audit log view
- [ ] Timestamp display on all records
- [ ] Edit window enforcement
- [ ] Period lock enforcement
- [ ] Transaction linking (void → original, reversal → original)

### Phase 5: Integration & Testing
- [ ] Role-based access control tests
- [ ] Stock calculation verification
- [ ] Void/reversal transaction accuracy
- [ ] Period closure impact
- [ ] Audit trail completeness
- [ ] Time-window enforcement
- [ ] Concurrent transaction handling

---

## Part 10: Key Design Rules Summary

### Non-Negotiable Rules
1. ✅ **All stock changes → Transactions only** (never direct edits)
2. ✅ **Every action → Timestamp + user logged** (audit trail)
3. ✅ **Historical data → Read-only after period close** (immutability)
4. ✅ **Roles separate** (no mixing responsibilities)
5. ✅ **UI prevents invalid actions** (buttons disabled before users try)
6. ✅ **Corrections → New transactions** (append-only, never overwrite)
7. ✅ **Void time-limited** (15 min for cashier, unrestricted for admin)
8. ✅ **Prices locked for cashiers** (only admin sets)
9. ✅ **Stock read-only for cashiers** (only transaction-driven changes)
10. ✅ **Periods lockable** (once closed, immutable)

### When in Doubt
- "Can this be a shortcut?" → NO, use transactions
- "Should we let user edit this?" → Is it after period close? NO
- "Is this action logged?" → Must be logged
- "Can cashier do this?" → Only if listed in Cashier Dashboard section
- "Should we delete this?" → Never; deactivate or void instead
