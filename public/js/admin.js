/* ========================================
   ADMIN DASHBOARD JAVASCRIPT - TOPINV
   ======================================== */

// API Base URL
const API_BASE = window.API_BASE || '/topinv/api';

// Global state
let adminProducts = [];
let adminSales = [];
let currentSaleTransactions = [];
let currentPeriod = null;
let availablePeriods = [];
let currentReversalSaleId = null;

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Admin dashboard initializing...');
    console.log('🚀 API_BASE:', window.API_BASE);
    
    // Load dashboard data
    loadDashboardMetrics();
    getCurrentPeriod();
    
    // Show Overview section by default
    showSection('overview');
    
    // Initialize datetime inputs to now
    initializeDatetimeInputs();
    
    console.log('✓ Admin dashboard initialized');
});

function initializeDatetimeInputs() {
    const now = new Date();
    const localDatetime = new Date(now.getTime() - now.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 16);
    
    const datetimeInputs = ['purchaseTransactionDatetime'];
    datetimeInputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) input.value = localDatetime;
    });
}

// ============================================
// SECTION NAVIGATION
// ============================================
function showSection(sectionName) {
    console.log('🟢 showSection called with:', sectionName);
    
    // Hide all sections
    const sections = document.querySelectorAll('.section');
    sections.forEach(section => section.classList.remove('active'));
    
    // Show selected section
    const targetSection = document.getElementById(sectionName);
    console.log('🟢 Target section element:', targetSection);
    
    if (targetSection) {
        targetSection.classList.add('active');
    }
    
    // Update nav active state
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => item.classList.remove('active'));
    
    // Find and activate matching nav item
    navItems.forEach(item => {
        const onclick = item.getAttribute('onclick');
        if (onclick && onclick.includes(`'${sectionName}'`)) {
            item.classList.add('active');
        }
    });
    
    // Load section data
    console.log('🟢 About to load data for section:', sectionName);
    switch(sectionName) {
        case 'overview':
            loadDashboardMetrics();
            loadAlerts();
            loadRecentTransactions();
            getCurrentPeriod();
            break;
        case 'products':
            console.log('🟢 Loading products table...');
            loadProductsTable();
            break;
        case 'purchases':
            loadPurchasesHistory();
            break;
        case 'sales':
            loadSalesTable();
            break;
        case 'periods':
            loadPeriodsTable();
            break;
        case 'audit':
            loadAuditLog();
            break;
        case 'reports':
            loadAvailablePeriods();
            break;
    }
    
    console.log(`✅ Showed section: ${sectionName}`);
}

// ============================================
// DASHBOARD METRICS
// ============================================
async function loadDashboardMetrics() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        const response = await fetch(`${API_BASE}/dashboard?action=summary`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to load dashboard metrics');
        }

        // Update metrics (safely check if elements exist)
        const totalProductsEl = document.getElementById('dashTotalProducts');
        const todaySalesEl = document.getElementById('todayRevenue');
        const todayPurchasesEl = document.getElementById('todayPurchases');
        const lowStockEl = document.getElementById('dashLowStock');
        
        if (totalProductsEl) {
            totalProductsEl.textContent = data.data.total_products || 0;
        }
        if (todaySalesEl) {
            todaySalesEl.textContent = 'UGX ' + (data.data.today_sales || 0).toFixed(0);
        }
        if (todayPurchasesEl) {
            todayPurchasesEl.textContent = 'UGX ' + (data.data.today_purchases || 0).toFixed(0);
        }
        if (lowStockEl) {
            lowStockEl.textContent = data.data.low_stock_count || 0;
        }
        
        // Update today's summary
        updateTodaySummary(data.data);

        console.log('✓ Dashboard metrics loaded');
    } catch (error) {
        console.error('Failed to load dashboard metrics:', error);
    }
}

function updateTodaySummary(data) {
    if (!data.today) return;
    
    // Update today's revenue in the overview section
    const revenueEl = document.getElementById('todayRevenue');
    const transactionsEl = document.getElementById('todayTransactions');
    
    if (revenueEl) {
        revenueEl.textContent = `UGX ${safeNumber(data.today.revenue).toFixed(0)}`;
    }
    if (transactionsEl) {
        transactionsEl.textContent = `${data.today.transaction_count || 0} transactions`;
    }
}

// ============================================
// ALERTS & RECENT TRANSACTIONS
// ============================================
async function loadAlerts() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        const response = await fetch(`${API_BASE}/dashboard?action=alerts`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to load alerts');
        }

        const container = document.getElementById('alertsContainer');
        if (!container) return;

        const alerts = data.data;
        
        // Clear loading message
        container.innerHTML = '';

        // Check if there are any alerts
        if (alerts.low_stock.length === 0 && alerts.out_of_stock.length === 0) {
            container.innerHTML = '<p class="no-alerts">✓ All products are well stocked</p>';
            return;
        }

        let html = '';

        // Out of stock alerts (critical)
        if (alerts.out_of_stock.length > 0) {
            alerts.out_of_stock.forEach(product => {
                html += `
                    <div class="alert alert-critical">
                        <span class="alert-icon">⚠️</span>
                        <div class="alert-content">
                            <strong>${product.product_name}</strong>
                            <p>OUT OF STOCK - Immediate restock required</p>
                        </div>
                    </div>
                `;
            });
        }

        // Low stock alerts (warning)
        if (alerts.low_stock.length > 0) {
            alerts.low_stock.forEach(product => {
                html += `
                    <div class="alert alert-warning">
                        <span class="alert-icon">⚡</span>
                        <div class="alert-content">
                            <strong>${product.product_name}</strong>
                            <p>Low stock: ${product.current_stock} units (Reorder level: ${product.reorder_level})</p>
                        </div>
                    </div>
                `;
            });
        }

        container.innerHTML = html;
        console.log('✓ Alerts loaded:', alerts.out_of_stock.length + alerts.low_stock.length, 'total');
    } catch (error) {
        console.error('Failed to load alerts:', error);
        const container = document.getElementById('alertsContainer');
        if (container) {
            container.innerHTML = '<p class="error">Failed to load alerts</p>';
        }
    }
}

async function loadRecentTransactions() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        const response = await fetch(`${API_BASE}/dashboard?action=recent-transactions&limit=10`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to load transactions');
        }

        const tbody = document.getElementById('recentTransactionsBody');
        if (!tbody) return;

        const transactions = data.data;

        // Clear loading message
        tbody.innerHTML = '';

        if (transactions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="no-data">No recent transactions</td></tr>';
            return;
        }

        transactions.forEach(txn => {
            const row = document.createElement('tr');
            
            // Format date/time
            const date = new Date(txn.transaction_date);
            const dateStr = date.toLocaleDateString('en-GB');
            const timeStr = date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
            
            // Type badge
            const typeBadge = txn.transaction_type === 'sale' 
                ? '<span class="badge badge-success">Sale</span>'
                : '<span class="badge badge-info">Purchase</span>';
            
            // Format amount
            const amount = safeNumber(txn.total_amount).toFixed(0);
            
            row.innerHTML = `
                <td>${dateStr}<br><small>${timeStr}</small></td>
                <td>${typeBadge}</td>
                <td>${txn.product_name || 'N/A'}</td>
                <td>${txn.quantity || 0}</td>
                <td>UGX ${amount}</td>
                <td>${txn.user_name || 'System'}</td>
            `;
            
            tbody.appendChild(row);
        });

        console.log('✓ Recent transactions loaded:', transactions.length);
    } catch (error) {
        console.error('Failed to load recent transactions:', error);
        const tbody = document.getElementById('recentTransactionsBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="6" class="error">Failed to load transactions</td></tr>';
        }
    }
}

// ============================================
// PRODUCTS / INVENTORY
// ============================================
async function loadProductsTable(statusFilter = 'all') {
    console.log('🔵 loadProductsTable called with filter:', statusFilter);
    
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        // Pass status filter to API
        const url = statusFilter && statusFilter !== 'all' 
            ? `${API_BASE}/products?status=${statusFilter}`
            : `${API_BASE}/products?status=all`;
        
        console.log('🔵 Fetching from URL:', url);
            
        const response = await fetch(url, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await response.json();
        console.log('🔵 API Response:', data);

        if (!response.ok) {
            throw new Error(data.message || 'Failed to load products');
        }

        adminProducts = data.data.products || [];
        console.log('🔵 Products loaded:', adminProducts.length);
        
        // Apply filter
        let filteredProducts = adminProducts;
        if (statusFilter !== 'all') {
            filteredProducts = adminProducts.filter(p => p.status === statusFilter);
        }

        // Display products
        const tbody = document.getElementById('productsTableBody');
        console.log('🔵 Table body element:', tbody);
        
        if (filteredProducts.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center">No products found</td></tr>';
            console.log('⚠️ No products to display');
            return;
        }

        tbody.innerHTML = filteredProducts.map(p => `
            <tr>
                <td>${p.name}</td>
                <td>${p.code || '-'}</td>
                <td>${p.current_stock}</td>
                <td>${p.reorder_level || '-'}</td>
                <td>UGX ${parseFloat(p.selling_price).toFixed(0)}</td>
                <td>UGX ${parseFloat(p.cost_price).toFixed(0)}</td>
                <td><span class="badge badge-${p.status === 'active' ? 'active' : 'inactive'}">${p.status}</span></td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="editProduct(${p.id})">Edit</button>
                    ${p.status === 'active' ? 
                        `<button class="btn btn-sm btn-danger" onclick="deactivateProduct(${p.id})">Deactivate</button>` : 
                        `<button class="btn btn-sm btn-success" onclick="activateProduct(${p.id})">Activate</button>`
                    }
                </td>
            </tr>
        `).join('');

        console.log(`✓ Loaded ${filteredProducts.length} products`);
    } catch (error) {
        console.error('Failed to load products:', error);
        alert('Failed to load products: ' + error.message);
        
        // Show error in table
        const tbody = document.getElementById('productsTableBody');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center" style="color: red;">Failed to load products: ${error.message}</td></tr>`;
        }
    }
}

function filterProducts() {
    const statusFilter = document.getElementById('productStatusFilter').value;
    loadProductsTable(statusFilter);
}

function searchProducts() {
    const searchTerm = document.getElementById('productSearch').value.toLowerCase();
    const statusFilter = document.getElementById('productStatusFilter').value;
    
    if (!adminProducts || adminProducts.length === 0) {
        return;
    }
    
    // Apply both filters
    let filtered = adminProducts;
    
    // Status filter
    if (statusFilter !== 'all') {
        filtered = filtered.filter(p => p.status === statusFilter);
    }
    
    // Search filter
    if (searchTerm) {
        filtered = filtered.filter(p => 
            p.name.toLowerCase().includes(searchTerm) || 
            (p.code && p.code.toLowerCase().includes(searchTerm))
        );
    }
    
    // Display filtered products
    const tbody = document.getElementById('productsTableBody');
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No products found</td></tr>';
        return;
    }

    tbody.innerHTML = filtered.map(p => `
        <tr>
            <td>${p.name}</td>
            <td>${p.code || '-'}</td>
            <td>${p.current_stock}</td>
            <td>${p.reorder_level || '-'}</td>
            <td>UGX ${parseFloat(p.selling_price).toFixed(0)}</td>
            <td>UGX ${parseFloat(p.cost_price).toFixed(0)}</td>
            <td><span class="badge badge-${p.status === 'active' ? 'active' : 'inactive'}">${p.status}</span></td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="editProduct(${p.id})">Edit</button>
                ${p.status === 'active' ? 
                    `<button class="btn btn-sm btn-danger" onclick="deactivateProduct(${p.id})">Deactivate</button>` : 
                    `<button class="btn btn-sm btn-success" onclick="activateProduct(${p.id})">Activate</button>`
                }
            </td>
        </tr>
    `).join('');
}

function openProductForm(productId = null) {
    const modal = document.getElementById('productFormModal');
    const title = document.getElementById('productFormTitle');
    const form = document.getElementById('productForm');
    
    // Reset form
    form.reset();
    document.getElementById('productId').value = '';
    
    if (productId) {
        // Edit mode
        const product = adminProducts.find(p => p.id === productId);
        if (!product) return;
        
        title.textContent = 'Edit Product';
        document.getElementById('productId').value = product.id;
        document.getElementById('productName').value = product.name;
        document.getElementById('productCode').value = product.code || '';
        document.getElementById('productCostPrice').value = product.cost_price;
        document.getElementById('productSellingPrice').value = product.selling_price;
        document.getElementById('productMinStock').value = product.min_stock_level || '';
        document.getElementById('productStatus').value = product.status;
    } else {
        // Add mode
        title.textContent = 'Add New Product';
        document.getElementById('productStatus').value = 'active';
    }
    
    openModal('productFormModal');
}

async function saveProduct() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');
        
        const productId = document.getElementById('productId').value;
        const productData = {
            name: document.getElementById('productName').value.trim(),
            code: document.getElementById('productCode').value.trim(),
            cost_price: parseFloat(document.getElementById('productCostPrice').value),
            selling_price: parseFloat(document.getElementById('productSellingPrice').value),
            min_stock_level: parseInt(document.getElementById('productMinStock').value) || 0,
            status: document.getElementById('productStatus').value
        };
        
        // Validation
        if (!productData.name) {
            alert('Product name is required');
            return;
        }
        if (productData.cost_price <= 0 || productData.selling_price <= 0) {
            alert('Prices must be greater than 0');
            return;
        }
        
        const url = productId ? 
            `${API_BASE}/products/${productId}` :
            `${API_BASE}/products`;
            
        const method = productId ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(productData)
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Failed to save product');
        }
        
        alert(productId ? 'Product updated successfully!' : 'Product added successfully!');
        closeModal('productFormModal');
        loadProductsTable();
        loadDashboardMetrics();
    } catch (error) {
        console.error('Failed to save product:', error);
        alert('Failed to save product: ' + error.message);
    }
}

function editProduct(productId) {
    openProductForm(productId);
}

async function deactivateProduct(productId) {
    if (!confirm('Are you sure you want to deactivate this product?')) return;
    
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');
        
        const product = adminProducts.find(p => p.id === productId);
        if (!product) return;
        
        const response = await fetch(`${API_BASE}/products/${productId}`, {
            method: 'PUT',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                ...product,
                status: 'inactive'
            })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Failed to deactivate product');
        }
        
        alert('Product deactivated successfully!');
        loadProductsTable();
    } catch (error) {
        console.error('Failed to deactivate product:', error);
        alert('Failed to deactivate product: ' + error.message);
    }
}

async function activateProduct(productId) {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');
        
        const product = adminProducts.find(p => p.id === productId);
        if (!product) return;
        
        const response = await fetch(`${API_BASE}/products/${productId}`, {
            method: 'PUT',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                ...product,
                status: 'active'
            })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Failed to activate product');
        }
        
        alert('Product activated successfully!');
        loadProductsTable();
    } catch (error) {
        console.error('Failed to activate product:', error);
        alert('Failed to activate product: ' + error.message);
    }
}

// ============================================
// PURCHASES
// ============================================
function openRecordPurchaseForm() {
    // Reset form
    document.getElementById('recordPurchaseForm').reset();
    
    // Populate products dropdown
    const select = document.getElementById('purchaseProductId');
    select.innerHTML = '<option value="">-- Select Product --</option>' +
        adminProducts.filter(p => p.status === 'active').map(p => 
            `<option value="${p.id}">${p.name} (${p.code || 'No code'})</option>`
        ).join('');
    
    // Initialize datetime to now
    initializeDatetimeInputs();
    
    openModal('recordPurchaseModal');
}

async function savePurchase() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');
        
        const productId = document.getElementById('purchaseProductId').value;
        const quantity = parseInt(document.getElementById('purchaseQuantity').value);
        const costPrice = parseFloat(document.getElementById('purchaseCostPrice').value);
        const supplier = document.getElementById('purchaseSupplier').value.trim();
        const transactionDatetime = document.getElementById('purchaseTransactionDatetime').value;
        
        // Validation
        if (!productId) {
            alert('Please select a product');
            return;
        }
        if (!quantity || quantity <= 0) {
            alert('Please enter a valid quantity');
            return;
        }
        if (!costPrice || costPrice < 0) {
            alert('Please enter a valid cost price');
            return;
        }
        
        // Prepare request
        const purchaseData = {
            product_id: productId,
            quantity: quantity,
            cost_price: costPrice,
            supplier: supplier || 'N/A',
            period_id: currentPeriod ? currentPeriod.id : 1
        };
        
        // Add transaction date if provided (backdating support)
        if (transactionDatetime) {
            purchaseData.transaction_date = transactionDatetime.replace('T', ' ') + ':00';
        }
        
        const response = await fetch(`${API_BASE}/purchases`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(purchaseData)
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Failed to record purchase');
        }
        
        alert('Purchase recorded successfully!');
        closeModal('recordPurchaseModal');
        loadPurchasesHistory();
        loadDashboardMetrics();
        loadProductsTable();
    } catch (error) {
        console.error('Failed to record purchase:', error);
        alert('Failed to record purchase: ' + error.message);
    }
}

async function loadPurchasesHistory() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        const response = await fetch(`${API_BASE}/purchases/history`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to load purchases');
        }

        const purchases = data.data.purchases || [];
        const tbody = document.getElementById('purchasesTableBody');
        
        if (purchases.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center">No purchases found</td></tr>';
            return;
        }

        tbody.innerHTML = purchases.map(p => `
            <tr>
                <td>${p.id}</td>
                <td>${formatDateTime(p.transaction_date || p.created_at)}</td>
                <td>${p.product_name}</td>
                <td>${p.quantity}</td>
                <td>UGX ${parseFloat(p.unit_price || 0).toFixed(0)}</td>
                <td>UGX ${parseFloat(p.total_amount || 0).toFixed(0)}</td>
                <td><span class="badge badge-${p.status === 'COMMITTED' ? 'success' : 'warning'}">${p.status}</span></td>
                <td>-</td>
            </tr>
        `).join('');

        console.log(`✓ Loaded ${purchases.length} purchases`);
    } catch (error) {
        console.error('Failed to load purchases:', error);
        
        // Show error in table
        const tbody = document.getElementById('purchasesTableBody');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center" style="color: red;">Failed to load purchases: ${error.message}</td></tr>`;
        }
    }
}

// ============================================
// SALES HISTORY & REVERSALS
// ============================================
async function loadSalesTable(statusFilter = 'all') {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        const response = await fetch(`${API_BASE}/sales/history`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to load sales');
        }

        adminSales = data.data.sales || [];
        
        // Apply filter
        let filteredSales = adminSales;
        if (statusFilter !== 'all') {
            filteredSales = adminSales.filter(s => s.status === statusFilter);
        }
        
        // Display sales
        const tbody = document.getElementById('salesTableBody');
        if (filteredSales.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center">No sales found</td></tr>';
            return;
        }

        tbody.innerHTML = filteredSales.map(s => `
            <tr>
                <td>${s.id}</td>
                <td>${formatDateTime(s.transaction_date)}</td>
                <td>${s.cashier_name || 'Unknown'}</td>
                <td>${s.product_name || 'N/A'}</td>
                <td>${s.quantity || 0}</td>
                <td>UGX ${safeNumber(s.total_amount).toFixed(0)}</td>
                <td><span class="badge badge-${s.status === 'DRAFT' ? 'warning' : s.status === 'COMMITTED' ? 'success' : s.status === 'REVERSED' ? 'inactive' : 'secondary'}">${s.status}</span></td>
                <td>
                    ${s.status === 'COMMITTED' ? 
                        `<button class="btn btn-sm btn-warning" onclick="reverseSale(${s.id})">Reverse</button>` : 
                        s.status === 'REVERSED' ? 'Reversed' : '-'
                    }
                </td>
            </tr>
        `).join('');

        console.log(`✓ Loaded ${filteredSales.length} sales`);
    } catch (error) {
        console.error('Failed to load sales:', error);
    }
}

function filterSales() {
    const statusFilter = document.getElementById('saleStatusFilter').value;
    loadSalesTable(statusFilter);
}

async function reverseSale(saleId) {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');
        
        // Load sale transactions
        const response = await fetch(`${API_BASE}/sales/${saleId}/transactions`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Failed to load sale transactions');
        }
        
        currentSaleTransactions = data.data.transactions || [];
        currentReversalSaleId = saleId;
        
        // Display transactions in modal
        const tbody = document.getElementById('reversalTransactionsBody');
        tbody.innerHTML = currentSaleTransactions.map(t => `
            <tr>
                <td>${t.product_name}</td>
                <td>${t.quantity}</td>
                <td>UGX ${parseFloat(t.unit_price).toFixed(0)}</td>
                <td>UGX ${(parseFloat(t.unit_price) * t.quantity).toFixed(0)}</td>
            </tr>
        `).join('');
        
        openModal('reversalModal');
    } catch (error) {
        console.error('Failed to load sale details:', error);
        alert('Failed to load sale details: ' + error.message);
    }
}

async function confirmReverseSale() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');
        
        const reason = document.getElementById('reversalReason').value.trim();
        
        if (!reason) {
            alert('Please enter a reason for reversal');
            return;
        }
        
        if (!confirm('Are you sure you want to reverse this sale? This action cannot be undone.')) {
            return;
        }
        
        const response = await fetch(`${API_BASE}/sales/${currentReversalSaleId}/reverse`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                reason: reason,
                period_id: currentPeriod ? currentPeriod.id : 1
            })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Failed to reverse sale');
        }
        
        alert('Sale reversed successfully!');
        closeModal('reversalModal');
        loadSalesTable();
        loadProductsTable();
        loadDashboardMetrics();
        
        // Reset
        currentReversalSaleId = null;
        currentSaleTransactions = [];
        document.getElementById('reversalReason').value = '';
    } catch (error) {
        console.error('Failed to reverse sale:', error);
        alert('Failed to reverse sale: ' + error.message);
    }
}

// ============================================
// PERIODS
// ============================================
async function loadPeriodsTable() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        const response = await fetch(`${API_BASE}/periods`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to load periods');
        }

        const periods = data.data.periods || [];
        availablePeriods = periods;
        
        const tbody = document.getElementById('periodsTableBody');
        
        if (periods.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center">No periods found</td></tr>';
            return;
        }

        tbody.innerHTML = periods.map(p => `
            <tr>
                <td>${p.period_name}</td>
                <td>${p.start_date || 'N/A'}</td>
                <td>${p.end_date || 'Ongoing'}</td>
                <td><span class="badge badge-${p.status === 'OPEN' ? 'active' : 'closed'}">${p.status}</span></td>
                <td>
                    ${p.status === 'OPEN' ? 
                        `<button class="btn btn-sm btn-warning" onclick="closePeriod(${p.id})">Close</button>` : 
                        '-'
                    }
                </td>
            </tr>
        `).join('');

        console.log(`✓ Loaded ${periods.length} periods`);
    } catch (error) {
        console.error('Failed to load periods:', error);
    }
}

async function getCurrentPeriod() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) return;

        const response = await fetch(`${API_BASE}/dashboard?action=period-status`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await response.json();

        if (response.ok && data.data) {
            currentPeriod = data.data;
            console.log(`Current period: ${currentPeriod.period_name} (${currentPeriod.status})`);
            
            // Update header display
            const periodNameEl = document.getElementById('currentPeriodName');
            const periodStatusEl = document.getElementById('currentPeriodStatus');
            
            if (periodNameEl) {
                periodNameEl.textContent = currentPeriod.period_name;
            }
            if (periodStatusEl) {
                periodStatusEl.textContent = currentPeriod.status;
                periodStatusEl.className = 'badge ' + (currentPeriod.status === 'active' ? 'badge-success' : 'badge-secondary');
            }
        }
    } catch (error) {
        console.error('Failed to load current period:', error);
        // Show error state in header
        const periodNameEl = document.getElementById('currentPeriodName');
        if (periodNameEl) {
            periodNameEl.textContent = 'Error loading period';
        }
    }
}

// ============================================
// AUDIT LOG
// ============================================
async function loadAuditLog() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        const response = await fetch(`${API_BASE}/transactions?limit=100`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to load audit log');
        }

        const transactions = data.data.transactions || [];
        const tbody = document.getElementById('auditLogBody');
        
        if (transactions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">No transactions found</td></tr>';
            return;
        }

        tbody.innerHTML = transactions.map(t => `
            <tr>
                <td>${t.id}</td>
                <td>${formatDateTime(t.transaction_date || t.created_at)}</td>
                <td><span class="badge badge-${getBadgeClass(t.type)}">${t.type}</span></td>
                <td>${t.product_name || 'N/A'}</td>
                <td>${t.quantity > 0 ? '+' : ''}${t.quantity}</td>
                <td>${t.notes || '-'}</td>
            </tr>
        `).join('');

        console.log(`✓ Loaded ${transactions.length} audit log entries`);
    } catch (error) {
        console.error('Failed to load audit log:', error);
    }
}

function getBadgeClass(type) {
    switch(type) {
        case 'PURCHASE': return 'success';
        case 'SALE': return 'primary';
        case 'ADJUSTMENT': return 'warning';
        case 'REVERSAL': return 'danger';
        case 'VOID': return 'inactive';
        default: return 'secondary';
    }
}

// ============================================
// REPORTS
// ============================================
function showReport(reportType) {
    // Hide all report contents
    document.querySelectorAll('.report-content').forEach(el => el.style.display = 'none');
    
    // Show selected report
    document.getElementById(`${reportType}Report`).style.display = 'block';
}

async function loadAvailablePeriods() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) return;

        const response = await fetch(`${API_BASE}/reports/periods`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await response.json();

        if (response.ok && data.data) {
            availablePeriods = data.data.periods || [];
            
            // Populate period selects
            const periodHTML = '<option value="">-- Select Period --</option>' + 
                availablePeriods.map(p => `<option value="${p.id}">${p.period_name}</option>`).join('');
            
            document.getElementById('monthlyReportPeriod').innerHTML = periodHTML;
            
            console.log(`✓ Loaded ${availablePeriods.length} periods for reports`);
        }
    } catch (error) {
        console.error('Failed to load periods:', error);
    }
}

async function generateDailyReport() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');
        
        const reportDate = document.getElementById('dailyReportDate').value;
        
        if (!reportDate) {
            alert('Please select a date');
            return;
        }
        
        const response = await fetch(`${API_BASE}/reports/daily-transactions?date=${reportDate}`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Failed to generate report');
        }
        
        displayDailyReport(data.data);
    } catch (error) {
        console.error('Failed to generate daily report:', error);
        alert('Failed to generate report: ' + error.message);
    }
}

function displayDailyReport(reportData) {
    const container = document.getElementById('dailyReportContent');
    
    const transactions = reportData.transactions || [];
    const summary = reportData.summary || {};
    
    // Count transaction types
    const adjustments = transactions.filter(t => t.type === 'ADJUSTMENT');
    const reversals = transactions.filter(t => t.type === 'REVERSAL');
    
    let html = `
        <div class="report-header">
            <h3>Daily Transaction Report</h3>
            <p>Date: ${reportData.date}</p>
            ${reportData.has_backdated || (summary.backdated_entries > 0) ? '<p class="warning">⚠️ This report includes backdated transactions</p>' : ''}
        </div>
        
        <div class="report-summary">
            <h4>Summary</h4>
            <table class="report-table">
                <tr>
                    <th>Total Transactions</th>
                    <td>${safeNumber(summary.total_transactions, 0)}</td>
                </tr>
                <tr>
                    <th>Sales Count</th>
                    <td>${safeNumber(summary.sales?.count, 0)} transactions</td>
                </tr>
                <tr>
                    <th>Total Sales</th>
                    <td>UGX ${safeNumber(summary.sales?.amount).toFixed(0)}</td>
                </tr>
                <tr>
                    <th>Purchases Count</th>
                    <td>${safeNumber(summary.purchases?.count, 0)} transactions</td>
                </tr>
                <tr>
                    <th>Total Purchases</th>
                    <td>UGX ${safeNumber(summary.purchases?.amount).toFixed(0)}</td>
                </tr>
                <tr>
                    <th>Net Cash Flow</th>
                    <td>UGX ${safeNumber(summary.net_cash_flow).toFixed(0)}</td>
                </tr>
                <tr>
                    <th>Adjustments</th>
                    <td>${adjustments.length}</td>
                </tr>
                <tr>
                    <th>Reversals</th>
                    <td>${reversals.length}</td>
                </tr>
            </table>
        </div>
        
        <div class="report-transactions">
            <h4>Transactions</h4>
            ${transactions.length === 0 ? '<p>No transactions found for this date</p>' : `
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Amount</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${transactions.map(t => `
                            <tr>
                                <td>${formatDateTime(t.transaction_date || t.created_at)}</td>
                                <td><span class="badge badge-${getBadgeClass(t.type)}">${t.type}</span></td>
                                <td>${t.product_name || 'N/A'}</td>
                                <td>${t.quantity > 0 ? '+' : ''}${t.quantity}</td>
                                <td>${t.amount ? 'UGX ' + safeNumber(t.amount).toFixed(0) : '-'}</td>
                                <td>${t.notes || '-'}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `}
        </div>
    `;
    
    container.innerHTML = html;
}

async function generateStockReport() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');
        
        const response = await fetch(`${API_BASE}/reports/stock-valuation`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Failed to generate report');
        }
        
        displayStockReport(data.data);
    } catch (error) {
        console.error('Failed to generate stock report:', error);
        alert('Failed to generate report: ' + error.message);
    }
}

function displayStockReport(reportData) {
    const container = document.getElementById('stockReportContent');
    
    const products = reportData.products || [];
    const summary = reportData.summary || {};
    
    // Calculate totals from products
    let totalCostValue = 0;
    let totalRetailValue = 0;
    
    const enrichedProducts = products.map(p => {
        const stock = safeNumber(p.current_stock, 0);
        const costPrice = safeNumber(p.cost_price);
        const sellingPrice = safeNumber(p.selling_price);
        
        const costValue = stock * costPrice;
        const retailValue = stock * sellingPrice;
        const marginPercent = costPrice > 0 ? ((sellingPrice - costPrice) / costPrice * 100) : 0;
        
        totalCostValue += costValue;
        totalRetailValue += retailValue;
        
        return {
            ...p,
            costValue,
            retailValue,
            marginPercent
        };
    });
    
    const potentialProfit = totalRetailValue - totalCostValue;
    
    let html = `
        <div class="report-header">
            <h3>Stock Valuation Report</h3>
            <p>Generated: ${new Date().toLocaleString()}</p>
        </div>
        
        <div class="report-summary">
            <h4>Summary</h4>
            <table class="report-table">
                <tr>
                    <th>Total Products</th>
                    <td>${summary.total_products || 0}</td>
                </tr>
                <tr>
                    <th>Total Stock Value (Cost)</th>
                    <td>UGX ${totalCostValue.toFixed(0)}</td>
                </tr>
                <tr>
                    <th>Total Stock Value (Retail)</th>
                    <td>UGX ${totalRetailValue.toFixed(0)}</td>
                </tr>
                <tr>
                    <th>Potential Profit</th>
                    <td>UGX ${potentialProfit.toFixed(0)}</td>
                </tr>
                <tr>
                    <th>Out of Stock</th>
                    <td>${summary.out_of_stock || 0}</td>
                </tr>
                <tr>
                    <th>Low Stock</th>
                    <td>${summary.low_stock || 0}</td>
                </tr>
            </table>
        </div>
        
        <div class="report-products">
            <h4>Product Details</h4>
            ${enrichedProducts.length === 0 ? '<p>No products found</p>' : `
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Current Stock</th>
                            <th>Cost Price</th>
                            <th>Selling Price</th>
                            <th>Cost Value</th>
                            <th>Retail Value</th>
                            <th>Margin</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${enrichedProducts.map(p => `
                            <tr>
                                <td>${p.name || 'Unknown'}</td>
                                <td>${safeNumber(p.current_stock, 0)}</td>
                                <td>UGX ${safeNumber(p.cost_price).toFixed(0)}</td>
                                <td>UGX ${safeNumber(p.selling_price).toFixed(0)}</td>
                                <td>UGX ${p.costValue.toFixed(0)}</td>
                                <td>UGX ${p.retailValue.toFixed(0)}</td>
                                <td>${p.marginPercent.toFixed(1)}%</td>
                                <td><span class="badge badge-${p.stock_status === 'ADEQUATE' ? 'success' : p.stock_status === 'LOW_STOCK' ? 'warning' : 'danger'}">${p.stock_status || 'N/A'}</span></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `}
        </div>
    `;
    
    container.innerHTML = html;
}

async function generateMonthlyReport() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');
        
        const periodId = document.getElementById('monthlyReportPeriod').value;
        
        if (!periodId) {
            alert('Please select a period');
            return;
        }
        
        const response = await fetch(`${API_BASE}/reports/period-summary?period_id=${periodId}`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Failed to generate report');
        }
        
        displayMonthlyReport(data.data);
    } catch (error) {
        console.error('Failed to generate monthly report:', error);
        alert('Failed to generate report: ' + error.message);
    }
}

function displayMonthlyReport(reportData) {
    const container = document.getElementById('monthlyReportContent');
    
    const sales = reportData.sales || {};
    const purchases = reportData.purchases || {};
    const profit = reportData.profit || {};
    const adjustments = reportData.adjustments || {};
    const reversals = reportData.reversals || {};
    const audit = reportData.audit || {};
    const topProducts = sales.top_products || [];
    
    let html = `
        <div class="report-header">
            <h3>Period Summary Report</h3>
            <p>Period: ${reportData.period_name || 'Unknown'}</p>
            <p>Duration: ${reportData.date_range?.start || 'N/A'} - ${reportData.date_range?.end || 'Ongoing'}</p>
            <p>Status: <span class="badge badge-${reportData.period_status === 'OPEN' ? 'active' : 'closed'}">${reportData.period_status || 'Unknown'}</span></p>
            ${audit.backdated_entries > 0 ? '<p class="warning">⚠️ This period includes ' + audit.backdated_entries + ' backdated transactions</p>' : ''}
        </div>
        
        <div class="report-summary">
            <h4>Financial Summary</h4>
            <table class="report-table">
                <tr>
                    <th>Total Sales Revenue</th>
                    <td>UGX ${safeNumber(sales.total_amount).toFixed(0)}</td>
                </tr>
                <tr>
                    <th>Total Purchase Cost</th>
                    <td>UGX ${safeNumber(purchases.total_amount).toFixed(0)}</td>
                </tr>
                <tr>
                    <th>Gross Profit</th>
                    <td>UGX ${safeNumber(profit.gross_profit).toFixed(0)}</td>
                </tr>
                <tr>
                    <th>Profit Margin</th>
                    <td>${safeNumber(profit.margin_percent).toFixed(1)}%</td>
                </tr>
            </table>
            
            <h4>Transaction Counts</h4>
            <table class="report-table">
                <tr>
                    <th>Total Transactions</th>
                    <td>${safeNumber(audit.total_transactions, 0)}</td>
                </tr>
                <tr>
                    <th>Total Sales</th>
                    <td>${safeNumber(sales.transaction_count, 0)} (${safeNumber(sales.total_quantity, 0)} items)</td>
                </tr>
                <tr>
                    <th>Total Purchases</th>
                    <td>${safeNumber(purchases.transaction_count, 0)} (${safeNumber(purchases.total_quantity, 0)} items)</td>
                </tr>
                <tr>
                    <th>Total Adjustments</th>
                    <td>${safeNumber(adjustments.count, 0)}</td>
                </tr>
                <tr>
                    <th>Total Reversals</th>
                    <td>${safeNumber(reversals.count, 0)}</td>
                </tr>
            </table>
        </div>
        
        <div class="report-top-products">
            <h4>Top Selling Products</h4>
            ${topProducts.length === 0 ? '<p>No sales data available</p>' : `
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Units Sold</th>
                            <th>Revenue</th>
                            <th>Transactions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${topProducts.map(p => `
                            <tr>
                                <td>${p.product_name || 'Unknown'}</td>
                                <td>${safeNumber(p.quantity, 0)}</td>
                                <td>UGX ${safeNumber(p.amount).toFixed(0)}</td>
                                <td>${safeNumber(p.count, 0)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `}
        </div>
    `;
    
    container.innerHTML = html;
}

// ============================================
// UTILITY FUNCTIONS
// ============================================
function safeNumber(value, defaultValue = 0) {
    const num = parseFloat(value);
    return isNaN(num) ? defaultValue : num;
}

function formatDateTime(datetime) {
    if (!datetime) return 'N/A';
    const date = new Date(datetime);
    return date.toLocaleString();
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'block';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        // Reset form if it exists
        const form = modal.querySelector('form');
        if (form) form.reset();
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
};

console.log('✓ Admin dashboard loaded');
