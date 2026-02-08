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
let dashboardRefreshInterval = null;

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Admin dashboard initializing...');
    console.log('🚀 API_BASE:', window.API_BASE);
    
    // Show Overview section by default
    showSection('overview');
    
    // Initialize datetime inputs to now
    initializeDatetimeInputs();
    
    // Load dashboard data in parallel
    Promise.all([
        loadDashboardMetrics().catch(e => console.error('Metrics error:', e)),
        getCurrentPeriod().catch(e => console.error('Period error:', e)),
        loadAlerts().catch(e => console.error('Alerts error:', e)),
        loadRecentTransactions().catch(e => console.error('Transactions error:', e))
    ]).then(() => {
        console.log('✓ Dashboard data loaded');
    }).catch(err => {
        console.error('Dashboard initialization error:', err);
    });
    
    // Set up auto-refresh for dashboard (every 30 seconds)
    startDashboardAutoRefresh();
    
    console.log('✓ Admin dashboard initialized');
});

function startDashboardAutoRefresh() {
    // Clear any existing interval
    if (dashboardRefreshInterval) {
        clearInterval(dashboardRefreshInterval);
    }
    
    // Refresh dashboard every 30 seconds if on overview section
    dashboardRefreshInterval = setInterval(() => {
        const overviewSection = document.getElementById('overview');
        if (overviewSection && overviewSection.classList.contains('active')) {
            console.log('🔄 Auto-refreshing dashboard...');
            loadDashboardMetrics();
            loadAlerts();
            loadRecentTransactions();
        }
    }, 30000); // 30 seconds
}

function stopDashboardAutoRefresh() {
    if (dashboardRefreshInterval) {
        clearInterval(dashboardRefreshInterval);
        dashboardRefreshInterval = null;
    }
}

function refreshDashboard() {
    console.log('🔄 Manual refresh triggered');
    loadDashboardMetrics();
    loadAlerts();
    loadRecentTransactions();
    
    // Show feedback
    const btn = event?.target;
    if (btn) {
        const originalText = btn.textContent;
        btn.textContent = '✓ Refreshed!';
        btn.disabled = true;
        setTimeout(() => {
            btn.textContent = originalText;
            btn.disabled = false;
        }, 2000);
    }
}

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
        case 'stock-taking':
            loadAdjustments();
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
        if (!token) {
            setDashboardDefaults('No auth token');
            return;
        }

        const response = await fetch(`${API_BASE}/dashboard?action=metrics`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        if (!response.ok) {
            console.warn(`Dashboard API returned ${response.status}`);
            setDashboardDefaults('No data available');
            return;
        }

        const data = await response.json();

        if (!data || !data.data) {
            setDashboardDefaults('Invalid response format');
            return;
        }

        const metrics = data.data;
        
        // Safely update Today's Summary
        safeUpdateElement('todayRevenue', () => 
            `UGX ${safeNumber(metrics?.sales?.revenue || 0).toFixed(0)}`
        );
        safeUpdateElement('todayTransactions', () => 
            `${metrics?.sales?.transaction_count || 0} transactions`
        );
        safeUpdateElement('todayPurchases', () => 
            `UGX ${safeNumber(metrics?.purchases?.amount || 0).toFixed(0)}`
        );
        safeUpdateElement('todayPurchasesCount', () => 
            `${metrics?.purchases?.purchase_count || 0} purchases`
        );
        
        // Safely update Key Metrics Cards
        safeUpdateElement('metricSalesRevenue', () => 
            `UGX ${safeNumber(metrics?.sales?.revenue || 0).toFixed(0)}`
        );
        safeUpdateElement('metricSalesCount', () => 
            `${metrics?.sales?.transaction_count || 0} in period`
        );
        safeUpdateElement('metricPurchasesAmount', () => 
            `UGX ${safeNumber(metrics?.purchases?.amount || 0).toFixed(0)}`
        );
        safeUpdateElement('metricPurchasesCount', () => 
            `${metrics?.purchases?.purchase_count || 0} in period`
        );
        safeUpdateElement('metricVoidedUnits', () => 
            `${metrics?.voids?.units || 0}`
        );
        safeUpdateElement('metricVoidedCount', () => 
            `${metrics?.voids?.void_count || 0} voids`
        );
        
        // Load product metrics separately
        await loadProductMetrics(token);

        console.log('✓ Dashboard metrics loaded');
    } catch (error) {
        console.error('Failed to load dashboard metrics:', error);
        setDashboardDefaults('Error loading data');
    }
}

function setDashboardDefaults(message) {
    const elements = [
        'todayRevenue', 'todayTransactions', 'todayPurchases', 'todayPurchasesCount',
        'metricSalesRevenue', 'metricSalesCount', 'metricPurchasesAmount', 'metricPurchasesCount',
        'metricVoidedUnits', 'metricVoidedCount', 'metricLowStock', 'metricLowStockText'
    ];
    
    elements.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = el.textContent.includes('UGX') ? 'UGX 0' : '0';
        }
    });
    
    console.warn(`Dashboard defaults set: ${message}`);
}

function safeUpdateElement(elementId, fn) {
    try {
        const el = document.getElementById(elementId);
        if (el) {
            el.textContent = fn();
        }
    } catch (error) {
        console.error(`Error updating element ${elementId}:`, error);
    }
}

async function loadProductMetrics(token) {
    try {
        // Get low stock count from alerts
        const alertsResponse = await fetch(`${API_BASE}/dashboard?action=alerts`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        
        const alertsData = await alertsResponse.json();
        
        if (alertsResponse.ok && alertsData.data) {
            const lowStockCount = alertsData.data.low_stock.length + alertsData.data.out_of_stock.length;
            const lowStockEl = document.getElementById('metricLowStock');
            const lowStockTextEl = document.getElementById('metricLowStockText');
            
            if (lowStockEl) {
                lowStockEl.textContent = lowStockCount;
            }
            if (lowStockTextEl) {
                lowStockTextEl.textContent = lowStockCount === 1 ? '1 item needs attention' : `${lowStockCount} items need attention`;
            }
        }
    } catch (error) {
        console.error('Failed to load product metrics:', error);
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
    const tbody = document.getElementById('recentTransactionsBody');
    if (!tbody) return;

    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">Not authenticated</td></tr>';
            return;
        }

        const response = await fetch(`${API_BASE}/dashboard?action=recent-transactions&limit=10`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        if (!response.ok) {
            console.warn('Recent transactions API failed');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">No recent transactions</td></tr>';
            return;
        }

        const data = await response.json();

        if (!data || !data.data) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">No recent transactions</td></tr>';
            return;
        }

        const transactions = data.data;

        // Clear loading message
        tbody.innerHTML = '';

        if (transactions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">No recent transactions</td></tr>';
            return;
        }

        transactions.forEach(txn => {
            const row = document.createElement('tr');
            
            // Format date/time
            const date = new Date(txn.transaction_date);
            const dateStr = date.toLocaleDateString('en-GB');
            const timeStr = date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
            
            // Type badge - normalize type to uppercase for comparison
            const txnType = (txn.type || txn.transaction_type || '').toUpperCase();
            let typeBadge = '';
            
            if (txnType === 'SALE') {
                typeBadge = '<span class="badge badge-success">Sale</span>';
            } else if (txnType === 'PURCHASE') {
                typeBadge = '<span class="badge badge-info">Purchase</span>';
            } else if (txnType === 'VOID' || txnType === 'REVERSAL') {
                typeBadge = '<span class="badge badge-warning">Void</span>';
            } else if (txnType === 'ADJUSTMENT') {
                typeBadge = '<span class="badge badge-secondary">Adjustment</span>';
            } else {
                typeBadge = `<span class="badge">${txnType}</span>`;
            }
            
            // Format amount
            const amount = safeNumber(txn.total_amount).toFixed(0);
            
            // Get user name
            const userName = txn.created_by || txn.user_name || 'System';
            
            row.innerHTML = `
                <td>${dateStr}<br><small>${timeStr}</small></td>
                <td>${typeBadge}</td>
                <td>${txn.product_name || 'N/A'}</td>
                <td>${Math.abs(txn.quantity || 0)}</td>
                <td>UGX ${amount}</td>
                <td>${userName}</td>
            `;
            
            tbody.appendChild(row);
        });

        console.log('✓ Recent transactions loaded:', transactions.length);
    } catch (error) {
        console.error('Failed to load recent transactions:', error);
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">Failed to load transactions</td></tr>';
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

// Debounce function for search
let searchDebounceTimer;
function debounce(func, delay) {
    return function(...args) {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => func.apply(this, args), delay);
    };
}

// Async product search with debouncing
async function filterProducts() {
    const searchInput = document.getElementById('productSearch');
    const searchTerm = searchInput.value.trim();
    
    // If search is empty, load all products
    if (searchTerm.length === 0) {
        hideSearchResults();
        loadProductsTable();
        return;
    }
    
    // Show search results dropdown
    await searchProductsAsync(searchTerm);
}

// Debounced version
const debouncedFilterProducts = debounce(filterProducts, 300);

async function searchProductsAsync(searchTerm) {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        const url = `${API_BASE}/products?action=search&q=${encodeURIComponent(searchTerm)}&limit=5`;
        
        const response = await fetch(url, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to search products');
        }

        const products = data.data || [];
        displaySearchResults(products);
        
    } catch (error) {
        console.error('Failed to search products:', error);
    }
}

function displaySearchResults(products) {
    let resultsContainer = document.getElementById('productSearchResults');
    
    // Create results container if it doesn't exist
    if (!resultsContainer) {
        resultsContainer = document.createElement('div');
        resultsContainer.id = 'productSearchResults';
        resultsContainer.className = 'search-results-dropdown';
        
        const searchInput = document.getElementById('productSearch');
        searchInput.parentNode.style.position = 'relative';
        searchInput.parentNode.appendChild(resultsContainer);
    }
    
    // Clear previous results
    resultsContainer.innerHTML = '';
    
    if (products.length === 0) {
        resultsContainer.innerHTML = '<div class="search-result-item no-results">No products found</div>';
        resultsContainer.style.display = 'block';
        return;
    }
    
    // Display up to 5 products
    products.forEach(product => {
        const item = document.createElement('div');
        item.className = 'search-result-item';
        item.innerHTML = `
            <div class="result-name">${product.name}</div>
            <div class="result-details">
                <span>Stock: ${product.current_stock}</span>
                <span>Price: UGX ${parseFloat(product.selling_price).toFixed(0)}</span>
            </div>
        `;
        item.onclick = () => selectSearchResult(product);
        resultsContainer.appendChild(item);
    });
    
    resultsContainer.style.display = 'block';
}

function selectSearchResult(product) {
    // You can customize this action - for now, it will load the full table filtered
    document.getElementById('productSearch').value = product.name;
    hideSearchResults();
    
    // Load full products table with this product highlighted
    loadProductsTable();
}

function hideSearchResults() {
    const resultsContainer = document.getElementById('productSearchResults');
    if (resultsContainer) {
        resultsContainer.style.display = 'none';
    }
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
            p.name.toLowerCase().includes(searchTerm)
        );
    }
    
    // Display filtered products
    const tbody = document.getElementById('productsTableBody');
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No products found</td></tr>';
        return;
    }

    tbody.innerHTML = filtered.map(p => `
        <tr>
            <td>${p.name}</td>
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
    
    if (!form) {
        console.error('Product form not found');
        return;
    }
    
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
        document.getElementById('productCostPrice').value = product.cost_price;
        document.getElementById('productSellingPrice').value = product.selling_price;
        document.getElementById('productOpeningStock').value = product.opening_stock || 0;
        document.getElementById('productOpeningStock').disabled = true;
        document.getElementById('productMinStock').value = product.reorder_level || '';
        document.getElementById('productStatus').value = product.status;
    } else {
        // Add mode
        title.textContent = 'Add New Product';
        document.getElementById('productOpeningStock').disabled = false;
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
            cost_price: parseFloat(document.getElementById('productCostPrice').value),
            selling_price: parseFloat(document.getElementById('productSellingPrice').value),
            reorder_level: parseInt(document.getElementById('productMinStock').value) || 10
        };
        
        // Only include opening_stock when creating new product
        if (!productId) {
            productData.opening_stock = parseInt(document.getElementById('productOpeningStock').value) || 0;
        }
        
        // Add status only if updating
        if (productId) {
            productData.status = document.getElementById('productStatus').value;
        }
        
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
// IMPORT PRODUCTS
// ============================================
let importedProductsData = [];

function downloadCSVTemplate() {
    const csvContent = `name,opening_stock,selling_price,cost_price,reorder_level
Coca Cola 500ml,100,2000,1500,20
Fanta Orange 500ml,80,2000,1500,20
Sprite 500ml,50,2000,1500,15`;
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', 'product_import_template.csv');
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function openImportProductsModal() {
    // Reset file input and preview
    document.getElementById('importFileInput').value = '';
    document.getElementById('importPreview').style.display = 'none';
    document.getElementById('importProductsBtn').disabled = true;
    importedProductsData = [];
    
    // Set up file change listener
    const fileInput = document.getElementById('importFileInput');
    fileInput.onchange = handleImportFileChange;
    
    openModal('importProductsModal');
}

function handleImportFileChange(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const text = e.target.result;
        parseCSV(text);
    };
    reader.readAsText(file);
}

function parseCSV(text) {
    const lines = text.split('\n').filter(line => line.trim());
    if (lines.length < 2) {
        alert('CSV file must have at least a header row and one data row');
        return;
    }
    
    // Parse header
    const headers = lines[0].split(',').map(h => h.trim().toLowerCase());
    
    // Validate required columns
    const requiredColumns = ['name', 'opening_stock', 'selling_price', 'cost_price'];
    const missingColumns = requiredColumns.filter(col => !headers.includes(col));
    
    if (missingColumns.length > 0) {
        alert(`Missing required columns: ${missingColumns.join(', ')}\n\nRequired columns: name, opening_stock, selling_price, cost_price\nOptional: reorder_level`);
        return;
    }
    
    // Parse data rows
    importedProductsData = [];
    for (let i = 1; i < lines.length; i++) {
        const values = lines[i].split(',').map(v => v.trim());
        if (values.length !== headers.length) continue;
        
        const row = {};
        headers.forEach((header, index) => {
            row[header] = values[index];
        });
        
        // Validate and convert data types
        if (!row.name || !row.opening_stock || !row.selling_price || !row.cost_price) {
            console.warn(`Skipping row ${i + 1}: Missing required fields`);
            continue;
        }
        
        importedProductsData.push({
            name: row.name,
            opening_stock: parseInt(row.opening_stock) || 0,
            selling_price: parseFloat(row.selling_price) || 0,
            cost_price: parseFloat(row.cost_price) || 0,
            reorder_level: parseInt(row.reorder_level) || 10
        });
    }
    
    if (importedProductsData.length === 0) {
        alert('No valid product rows found in CSV file');
        return;
    }
    
    // Show preview
    displayImportPreview();
    document.getElementById('importProductsBtn').disabled = false;
}

function displayImportPreview() {
    const preview = document.getElementById('importPreview');
    const thead = document.getElementById('importPreviewHeader');
    const tbody = document.getElementById('importPreviewBody');
    const stats = document.getElementById('importStats');
    
    // Show preview section
    preview.style.display = 'block';
    
    // Create table header
    thead.innerHTML = `
        <tr>
            <th>Name</th>
            <th>Opening Stock</th>
            <th>Selling Price</th>
            <th>Cost Price</th>
            <th>Reorder Level</th>
        </tr>
    `;
    
    // Show first 5 rows
    const previewRows = importedProductsData.slice(0, 5);
    tbody.innerHTML = previewRows.map(p => `
        <tr>
            <td>${p.name}</td>
            <td>${p.opening_stock}</td>
            <td>UGX ${p.selling_price.toFixed(0)}</td>
            <td>UGX ${p.cost_price.toFixed(0)}</td>
            <td>${p.reorder_level}</td>
        </tr>
    `).join('');
    
    // Show stats
    stats.textContent = `Total products to import: ${importedProductsData.length}`;
}

async function processImportProducts() {
    if (importedProductsData.length === 0) {
        alert('No products to import');
        return;
    }
    
    if (!confirm(`Import ${importedProductsData.length} products? This may take a moment.`)) {
        return;
    }
    
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');
        
        const importBtn = document.getElementById('importProductsBtn');
        importBtn.disabled = true;
        importBtn.textContent = 'Importing...';
        
        let successCount = 0;
        let errorCount = 0;
        const errors = [];
        
        // Import products one by one
        for (let i = 0; i < importedProductsData.length; i++) {
            const product = importedProductsData[i];
            
            try {
                // Create product with opening stock
                const createResponse = await fetch(`${API_BASE}/products`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        name: product.name,
                        selling_price: product.selling_price,
                        cost_price: product.cost_price,
                        opening_stock: product.opening_stock,
                        reorder_level: product.reorder_level
                    })
                });
                
                const createData = await createResponse.json();
                
                if (!createResponse.ok) {
                    throw new Error(createData.message || 'Failed to create product');
                }
                
                successCount++;
                successCount++;
            } catch (error) {
                errorCount++;
                errors.push(`${product.name}: ${error.message}`);
                console.error(`Failed to import product ${product.name}:`, error);
            }
        }
        
        // Show results
        let message = `Import completed!\n\nSuccessful: ${successCount}\nFailed: ${errorCount}`;
        if (errors.length > 0 && errors.length <= 10) {
            message += '\n\nErrors:\n' + errors.join('\n');
        } else if (errors.length > 10) {
            message += '\n\nErrors (first 10):\n' + errors.slice(0, 10).join('\n');
        }
        
        alert(message);
        
        // Close modal and refresh
        closeModal('importProductsModal');
        loadProductsTable();
        loadDashboardMetrics();
        
    } catch (error) {
        console.error('Import failed:', error);
        alert('Import failed: ' + error.message);
        
        const importBtn = document.getElementById('importProductsBtn');
        importBtn.disabled = false;
        importBtn.textContent = 'Import Products';
    }
}

// ============================================
// PURCHASES
// ============================================
async function openRecordPurchaseForm() {
    // Load products if not already loaded
    if (!adminProducts || adminProducts.length === 0) {
        await loadProductsForDropdown();
    }
    
    // Reset form
    const form = document.getElementById('recordPurchaseForm');
    if (form) {
        form.reset();
    }
    
    // Populate products dropdown
    const select = document.getElementById('purchaseProductId');
    if (select) {
        select.innerHTML = '<option value="">-- Select Product --</option>' +
            adminProducts.filter(p => p.status === 'active').map(p => 
                `<option value="${p.id}">${p.name}</option>`
            ).join('');
    }
    
    // Initialize datetime to now
    initializeDatetimeInputs();
    
    openModal('recordPurchaseModal');
}

async function loadProductsForDropdown() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        const url = `${API_BASE}/products?status=all`;
            
        const response = await fetch(url, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to load products');
        }

        adminProducts = data.data.products || [];
    } catch (error) {
        console.error('Failed to load products for dropdown:', error);
        alert('Failed to load products: ' + error.message);
    }
}

async function savePurchase() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');
        
        // Validate period exists before allowing purchase
        if (!currentPeriod || !currentPeriod.id) {
            alert('⚠️ Cannot record purchase: No active period exists.\n\nPlease create an accounting period first.');
            return;
        }
        
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
            period_id: currentPeriod.id  // Use actual period id, not fallback
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
        
        // Load sale transaction details
        const response = await fetch(`${API_BASE}/sales/${saleId}/transactions`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Failed to load sale transactions');
        }
        
        const transactions = data.data.transactions || [];
        if (transactions.length === 0) {
            throw new Error('No transactions found');
        }
        
        currentReversalSaleId = saleId;
        const transaction = transactions[0];
        
        // Display transaction info in modal
        const infoDiv = document.getElementById('reverseSaleInfo');
        infoDiv.innerHTML = `
            <div style="border-left: 4px solid #fd7e14; padding: 10px;">
                <p><strong>Product:</strong> ${transaction.product_name}</p>
                <p><strong>Quantity:</strong> ${transaction.quantity} units</p>
                <p><strong>Unit Price:</strong> UGX ${parseFloat(transaction.unit_price).toFixed(0)}</p>
                <p><strong>Total Amount:</strong> UGX ${(parseFloat(transaction.unit_price) * transaction.quantity).toFixed(0)}</p>
                <p><strong>Date:</strong> ${transaction.transaction_date}</p>
                <p><strong>Recorded By:</strong> ${transaction.created_by_name}</p>
            </div>
        `;
        
        // Clear previous reason
        document.getElementById('reversalReason').value = '';
        
        openModal('reverseSaleModal');
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
        closeModal('reverseSaleModal');
        loadSalesTable();
        loadProductsTable();
        loadDashboardMetrics();
        
        // Reset
        currentReversalSaleId = null;
        document.getElementById('reversalReason').value = '';
        document.getElementById('reverseSaleInfo').innerHTML = '';
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
                <td>${formatDateTime(p.created_at) || 'N/A'}</td>
                <td>
                    ${p.status === 'OPEN' ? 
                        `<button class="btn btn-sm btn-warning" onclick="closePeriod(${p.id})">Close</button>` : 
                        `<button class="btn btn-sm btn-info" onclick="openPeriodPreview(${p.id})">📊 Preview</button>`
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
        if (!token) {
            setPeriodDefault('No period');
            return;
        }

        const response = await fetch(`${API_BASE}/dashboard?action=period-status`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        if (!response.ok) {
            console.warn('Period status endpoint failed');
            setPeriodDefault('No active period');
            return;
        }

        const data = await response.json();

        if (response.ok && data.data) {
            currentPeriod = data.data;
            console.log(`Current period: ${currentPeriod.period_name} (${currentPeriod.status})`);
            
            // Update header display
            const periodNameEl = document.getElementById('currentPeriodName');
            const periodStatusEl = document.getElementById('currentPeriodStatus');
            const noPeriodAlert = document.getElementById('noPeriodAlert');
            
            if (periodNameEl) {
                periodNameEl.textContent = currentPeriod.period_name;
            }
            if (periodStatusEl) {
                periodStatusEl.textContent = currentPeriod.status;
                periodStatusEl.className = 'badge ' + (currentPeriod.status === 'OPEN' ? 'badge-success' : 'badge-secondary');
            }
            
            // Hide alert - period exists
            if (noPeriodAlert) {
                noPeriodAlert.style.display = 'none';
            }
        } else {
            setPeriodDefault('No active period');
        }
    } catch (error) {
        console.error('Failed to load current period:', error);
        setPeriodDefault('Error');
    }
}

function setPeriodDefault(message) {
    const periodNameEl = document.getElementById('currentPeriodName');
    const periodStatusEl = document.getElementById('currentPeriodStatus');
    const noPeriodAlert = document.getElementById('noPeriodAlert');
    
    if (periodNameEl) {
        periodNameEl.textContent = message;
    }
    if (periodStatusEl) {
        periodStatusEl.textContent = '—';
        periodStatusEl.className = 'badge';
    }
    
    // Show "No Period" alert if no active period
    if (message === 'No active period' || message === 'No period' || message === 'Error') {
        if (noPeriodAlert) {
            noPeriodAlert.style.display = 'block';
        }
    } else {
        if (noPeriodAlert) {
            noPeriodAlert.style.display = 'none';
        }
    }
}

async function openNewPeriodForm() {
    try {
        const periodName = prompt('Enter period name (e.g., January 2024):');
        if (!periodName || periodName.trim() === '') {
            return;
        }

        const startDate = prompt('Enter start date (YYYY-MM-DD):');
        if (!startDate || startDate.trim() === '') {
            return;
        }

        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        const response = await fetch(`${API_BASE}/periods`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                period_name: periodName.trim(),
                start_date: startDate.trim()
            })
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to create period');
        }

        alert(`✓ Period "${periodName}" created successfully!`);
        await loadPeriodsTable();
        await getCurrentPeriod();  // Update current period in sidebar
        await loadDashboardMetrics();  // Refresh dashboard cards for new period
        console.log('✓ New period created and dashboard refreshed');
    } catch (error) {
        console.error('Failed to create period:', error);
        alert('Failed to create period: ' + error.message);
    }
}

async function closePeriod(periodId) {
    try {
        if (!confirm('Are you sure you want to close this period? This action cannot be undone.')) {
            return;
        }

        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        const response = await fetch(`${API_BASE}/periods/${periodId}/close`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to close period');
        }

        alert('✓ Period closed successfully!');
        await loadPeriodsTable();
        await getCurrentPeriod();
        console.log('✓ Period closed');
    } catch (error) {
        console.error('Failed to close period:', error);
        alert('Failed to close period: ' + error.message);
    }
}

async function openPeriodPreview(periodId) {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        const response = await fetch(`${API_BASE}/periods/${periodId}/summary`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to load period preview');
        }

        const summary = data.data || data;
        const period = summary.period;
        const txSummary = summary.transaction_summary || {};
        const products = summary.products || [];

        // Populate modal header
        document.getElementById('previewPeriodName').textContent = period.period_name;
        
        // Period info
        const statusEl = document.getElementById('previewPeriodStatus');
        statusEl.innerHTML = `<span class="badge badge-${period.status === 'OPEN' ? 'active' : 'closed'}">${period.status}</span>`;
        document.getElementById('previewStartDate').textContent = period.start_date || 'N/A';
        document.getElementById('previewEndDate').textContent = 
            (period.end_date && period.end_date !== '0000-00-00') ? period.end_date : 'Ongoing';
        document.getElementById('previewCreatedAt').textContent = formatDateTime(period.created_at);

        // Transaction summary
        document.getElementById('previewTotalTransactions').textContent = safeNumber(txSummary.total_transactions, 0);
        document.getElementById('previewTotalSales').textContent = 
            `UGX ${safeNumber(txSummary.total_sales_amount).toFixed(0).toLocaleString()}`;
        document.getElementById('previewTotalPurchases').textContent = 
            `UGX ${safeNumber(txSummary.total_purchases_amount).toFixed(0).toLocaleString()}`;
        document.getElementById('previewTotalAdjustments').textContent = 
            `${safeNumber(txSummary.total_adjustments_qty, 0)} units`;
        document.getElementById('previewReversalCount').textContent = 
            safeNumber(txSummary.reversal_count, 0);

        // Product details
        const tbody = document.getElementById('previewProductsBody');
        if (products.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center">No products in this period</td></tr>';
        } else {
            tbody.innerHTML = products.map(p => {
                const openingStock = safeNumber(p.opening_stock, 0);
                const purchases = safeNumber(p.purchases, 0);
                const sales = safeNumber(p.sales, 0);
                const adjustments = safeNumber(p.adjustments, 0);
                const calculatedClosing = openingStock + purchases - sales + adjustments;
                const actualClosing = safeNumber(p.closing_stock, 0);
                const costValue = actualClosing * safeNumber(p.cost_price, 0);
                const variance = calculatedClosing - actualClosing;

                return `
                    <tr ${variance !== 0 ? 'style="background: #fff3cd;"' : ''}>
                        <td><strong>${p.product_name}</strong></td>
                        <td style="text-align: center;">${openingStock}</td>
                        <td style="text-align: center; color: green;">+${purchases}</td>
                        <td style="text-align: center; color: red;">-${sales}</td>
                        <td style="text-align: center; color: orange;">${adjustments > 0 ? '+' : ''}${adjustments}</td>
                        <td style="text-align: center; font-weight: bold;">${calculatedClosing}</td>
                        <td style="text-align: center; font-weight: bold;">${actualClosing}</td>
                        <td style="text-align: right;">UGX ${costValue.toFixed(0).toLocaleString()}</td>
                    </tr>
                `;
            }).join('');
        }

        // Open modal
        openModal('periodPreviewModal');
        console.log('✓ Period preview loaded');
    } catch (error) {
        console.error('Failed to load period preview:', error);
        alert('Failed to load period preview: ' + error.message);
    }
}

// ============================================
// STOCK TAKING
// ============================================
async function startStockTaking() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        // Load active products
        if (!adminProducts || adminProducts.length === 0) {
            await loadProductsForDropdown();
        }

        // Show stock taking panel
        const panel = document.getElementById('stockTakingPanel');
        if (!panel) {
            alert('Stock Taking panel not found');
            return;
        }

        panel.style.display = 'block';
        
        // Create form for physical count entry
        const form = document.getElementById('stockTakingForm');
        if (!form) return;

        const products = adminProducts.filter(p => p.status === 'active');

        let html = `
            <div style="margin: 20px 0;">
                <p style="color: #666; margin-bottom: 15px;">
                    <strong>Instruction:</strong> For each product below, enter the physical count from your physical inventory.
                    The system will calculate the variance (difference from system stock).
                </p>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>System Stock</th>
                            <th>Physical Count</th>
                            <th>Variance</th>
                        </tr>
                    </thead>
                    <tbody id="stockTakingBody">
        `;

        products.forEach(p => {
            html += `
                <tr>
                    <td>${p.name}</td>
                    <td style="text-align: center;"><strong>${p.current_stock}</strong></td>
                    <td>
                        <input type="number" class="physical-count" data-product-id="${p.id}" 
                               data-system-stock="${p.current_stock}" 
                               style="width: 100%; padding: 5px; border: 1px solid #ddd;" 
                               min="0" placeholder="Enter count" >
                    </td>
                    <td style="text-align: center;"><span class="variance-display" data-product-id="${p.id}">-</span></td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
                <div style="margin-top: 20px;">
                    <button class="btn btn-primary" onclick="submitStockTaking()">✓ Submit Count</button>
                    <button class="btn btn-secondary" onclick="cancelStockTaking()">✗ Cancel</button>
                </div>
            </div>
        `;

        form.innerHTML = html;

        // Add event listeners for variance calculation
        const inputs = document.querySelectorAll('.physical-count');
        inputs.forEach(input => {
            input.addEventListener('change', function() {
                const systemStock = parseInt(this.dataset.systemStock);
                const physicalCount = parseInt(this.value) || 0;
                const variance = physicalCount - systemStock;
                
                const varianceDisplay = document.querySelector(
                    `.variance-display[data-product-id="${this.dataset.productId}"]`
                );
                if (varianceDisplay) {
                    varianceDisplay.textContent = variance >= 0 ? '+' + variance : variance;
                    varianceDisplay.style.color = variance === 0 ? 'green' : variance > 0 ? 'orange' : 'red';
                }
            });
        });

        console.log('✓ Stock taking form initialized');
    } catch (error) {
        console.error('Failed to start stock taking:', error);
        alert('Failed to start stock taking: ' + error.message);
    }
}

async function submitStockTaking() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        const inputs = document.querySelectorAll('.physical-count');
        let hasData = false;
        const counts = [];

        inputs.forEach(input => {
            const physicalCount = parseInt(input.value);
            if (!isNaN(physicalCount)) {
                hasData = true;
                counts.push({
                    product_id: parseInt(input.dataset.productId),
                    physical_count: physicalCount,
                    period_id: currentPeriod ? currentPeriod.id : 1
                });
            }
        });

        if (!hasData) {
            alert('Please enter at least one physical count');
            return;
        }

        // Submit each count
        const results = [];
        let errors = [];
        
        for (const count of counts) {
            try {
                const response = await fetch(`${API_BASE}/inventory/physical-count`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(count)
                });

                // Check if response is ok
                if (!response.ok) {
                    const text = await response.text();
                    console.error('Response status:', response.status);
                    console.error('Response text:', text);
                    errors.push(`Product ${count.product_id}: ${response.status} error`);
                    continue;
                }

                // Try to parse JSON
                let data;
                try {
                    data = await response.json();
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    const text = await response.text();
                    console.error('Response was:', text);
                    errors.push(`Product ${count.product_id}: Invalid server response`);
                    continue;
                }

                if (data.success) {
                    results.push(data.data);
                } else {
                    errors.push(`Product ${count.product_id}: ${data.message || 'Unknown error'}`);
                }
            } catch (error) {
                console.error('Fetch error:', error);
                errors.push(`Product ${count.product_id}: ${error.message}`);
            }
        }

        // Show results
        let message = `✓ Stock taking completed!\n${results.length} products counted.`;
        if (errors.length > 0) {
            message += `\n\n⚠️ Errors:\n${errors.join('\n')}`;
        }
        alert(message);
        
        // Reload adjustments table
        await loadAdjustments();
        
        // Reset form
        cancelStockTaking();
        
        console.log('✓ Stock taking submitted');
    } catch (error) {
        console.error('Failed to submit stock taking:', error);
        alert('Failed to submit: ' + error.message);
    }
}

function cancelStockTaking() {
    const panel = document.getElementById('stockTakingPanel');
    if (panel) {
        panel.style.display = 'none';
    }
    
    const form = document.getElementById('stockTakingForm');
    if (form) {
        form.innerHTML = '';
    }
}

async function loadAdjustments() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        const periodId = currentPeriod ? currentPeriod.id : 1;
        
        const response = await fetch(
            `${API_BASE}/inventory/adjustments?period_id=${periodId}`,
            { headers: { 'Authorization': `Bearer ${token}` } }
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to load adjustments');
        }

        const adjustments = data.data.adjustments || [];
        const tbody = document.getElementById('adjustmentsTableBody');

        if (adjustments.length === 0) {
            tbody.innerHTML = '<tr class="empty-state"><td colspan="5" class="text-center">No adjustments recorded</td></tr>';
            return;
        }

        tbody.innerHTML = adjustments.map(adj => `
            <tr>
                <td>${formatDateTime(adj.created_at)}</td>
                <td>${adj.product_name}</td>
                <td>${adj.variance >= 0 ? '+' : ''}${adj.variance}</td>
                <td>${adj.notes || 'Stock taking'}</td>
                <td>${adj.recorded_by_name || 'Unknown'}</td>
            </tr>
        `).join('');

        console.log(`✓ Loaded ${adjustments.length} adjustments`);
    } catch (error) {
        console.error('Failed to load adjustments:', error);
    }
}

function recordAdjustment() {
    alert('Use "Start Stock Count" to properly record adjustments with system stock comparison.');
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
        const tbody = document.getElementById('auditTableBody');
        
        if (transactions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center">No transactions found</td></tr>';
            return;
        }

        tbody.innerHTML = transactions.map(t => `
            <tr>
                <td>${formatDateTime(t.transaction_date || t.created_at)}</td>
                <td>${t.created_by_name || 'System'}</td>
                <td><span class="badge badge-${getBadgeClass(t.type)}">${t.type}</span></td>
                <td>${t.id}</td>
                <td>${t.product_name || 'N/A'} (${t.quantity > 0 ? '+' : ''}${t.quantity} @ UGX ${safeNumber(t.unit_price).toFixed(0)})</td>
                <td>-</td>
                <td><span class="badge badge-${t.status === 'COMMITTED' ? 'success' : t.status === 'REVERSED' ? 'danger' : 'warning'}">${t.status}</span></td>
                <td>
                    ${t.reference_transaction_id ? `<small>Ref: ${t.reference_transaction_id}</small>` : '-'}
                </td>
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
                                <td>UGX ${safeNumber(t.total_amount).toFixed(0)}</td>
                                <td>${t.reversal_reason || (t.is_backdated ? `Backdated (${t.days_late} days)` : '-')}</td>
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
    const movement = summary.stock_movement || {};
    
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
            <h4>Overall Stock Movement</h4>
            <table class="report-table" style="font-weight: bold; background: #f8f9fa;">
                <tr>
                    <th>Opening Stock</th>
                    <td>${safeNumber(movement.opening_stock, 0).toLocaleString()} units</td>
                </tr>
                <tr style="color: green;">
                    <th>+ Purchases</th>
                    <td>+${safeNumber(movement.purchases, 0).toLocaleString()} units</td>
                </tr>
                <tr style="color: red;">
                    <th>- Sales</th>
                    <td>-${safeNumber(movement.sales, 0).toLocaleString()} units</td>
                </tr>
                <tr style="color: orange;">
                    <th>± Adjustments</th>
                    <td>${safeNumber(movement.adjustments, 0) >= 0 ? '+' : ''}${safeNumber(movement.adjustments, 0).toLocaleString()} units</td>
                </tr>
                <tr style="font-size: 1.1em; background: #e3f2fd; border-top: 2px solid #333;">
                    <th>= Current Stock</th>
                    <td>${safeNumber(movement.calculated_closing, 0).toLocaleString()} units</td>
                </tr>
            </table>
        </div>
        
        <div class="report-products">
            <h4>Product Details with Stock Movement</h4>
            ${enrichedProducts.length === 0 ? '<p>No products found</p>' : `
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Opening</th>
                            <th>Purchases</th>
                            <th>Sales</th>
                            <th>Adjustments</th>
                            <th>Current Stock</th>
                            <th>Value (Cost)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${enrichedProducts.map(p => `
                            <tr>
                                <td>${p.name || 'Unknown'}</td>
                                <td>${safeNumber(p.opening_stock, 0)}</td>
                                <td style="color: green;">+${safeNumber(p.total_purchases, 0)}</td>
                                <td style="color: red;">-${safeNumber(p.total_sales, 0)}</td>
                                <td style="color: orange;">${safeNumber(p.total_adjustments, 0) >= 0 ? '+' : ''}${safeNumber(p.total_adjustments, 0)}</td>
                                <td style="font-weight: bold;">${safeNumber(p.current_stock, 0)}</td>
                                <td>UGX ${p.costValue.toFixed(0)}</td>
                                <td>
                                    <span class="badge badge-${p.stock_status === 'OUT_OF_STOCK' ? 'inactive' : p.stock_status === 'LOW_STOCK' ? 'draft' : 'active'}">
                                        ${p.stock_status || 'N/A'}
                                    </span>
                                </td>
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
    const stockMovement = reportData.stock_movement || {};
    const productMovements = stockMovement.by_product || [];
    
    let html = `
        <div class="report-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <h3>Period Summary Report</h3>
                <p>Period: ${reportData.period_name || 'Unknown'}</p>
                <p>Duration: ${reportData.date_range?.start || 'N/A'} - ${reportData.date_range?.end || 'Ongoing'}</p>
                <p>Status: <span class="badge badge-${reportData.period_status === 'OPEN' ? 'active' : 'closed'}">${reportData.period_status || 'Unknown'}</span></p>
                ${audit.backdated_entries > 0 ? '<p class="warning">⚠️ This period includes ' + audit.backdated_entries + ' backdated transactions</p>' : ''}
            </div>
            <button class="btn btn-secondary" onclick="exportMonthlyReportToPDF('${reportData.period_name || 'Report'}')" style="padding: 8px 16px; white-space: nowrap; margin-top: 5px;">
                📄 Export as PDF
            </button>
        </div>
        
        <div class="report-summary">
            <h4>Period Stock Movement</h4>
            <table class="report-table" style="font-weight: bold; background: #f8f9fa;">
                <tr>
                    <th>Opening Stock</th>
                    <td>${safeNumber(stockMovement.opening_stock, 0).toLocaleString()} units</td>
                </tr>
                <tr style="color: green;">
                    <th>+ Purchases</th>
                    <td>+${safeNumber(stockMovement.purchases, 0).toLocaleString()} units</td>
                </tr>
                <tr style="color: red;">
                    <th>- Sales</th>
                    <td>-${safeNumber(stockMovement.sales, 0).toLocaleString()} units</td>
                </tr>
                <tr style="color: orange;">
                    <th>± Adjustments</th>
                    <td>${safeNumber(stockMovement.adjustments, 0) >= 0 ? '+' : ''}${safeNumber(stockMovement.adjustments, 0).toLocaleString()} units</td>
                </tr>
                <tr style="font-size: 1.1em; background: #e3f2fd; border-top: 2px solid #333;">
                    <th>= Closing Stock</th>
                    <td>${safeNumber(stockMovement.calculated_closing, 0).toLocaleString()} units</td>
                </tr>
                ${stockMovement.variance && Math.abs(stockMovement.variance) > 0 ? `
                <tr style="color: red;">
                    <th>Variance</th>
                    <td>${stockMovement.variance} units (Actual: ${safeNumber(stockMovement.actual_closing, 0)})</td>
                </tr>
                ` : ''}
            </table>
        </div>
        
        ${productMovements.length > 0 ? `
        <div class="report-product-movements">
            <h4>Product-Level Stock Movement</h4>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Opening</th>
                        <th>Purchases</th>
                        <th>Sales</th>
                        <th>Adjustments</th>
                        <th>Calculated Closing</th>
                        <th>Actual Closing</th>
                        <th>Variance</th>
                    </tr>
                </thead>
                <tbody>
                    ${productMovements.map(pm => `
                        <tr ${pm.variance && Math.abs(pm.variance) > 0 ? 'style="background: #fff3cd;"' : ''}>
                            <td>${pm.product_name || 'Unknown'}</td>
                            <td>${safeNumber(pm.opening_stock, 0)}</td>
                            <td style="color: green;">+${safeNumber(pm.purchases, 0)}</td>
                            <td style="color: red;">-${safeNumber(pm.sales, 0)}</td>
                            <td style="color: orange;">${safeNumber(pm.adjustments, 0) >= 0 ? '+' : ''}${safeNumber(pm.adjustments, 0)}</td>
                            <td style="font-weight: bold;">${safeNumber(pm.calculated_closing, 0)}</td>
                            <td>${safeNumber(pm.closing_stock, 0)}</td>
                            <td ${pm.variance && Math.abs(pm.variance) > 0 ? 'style="color: red; font-weight: bold;"' : ''}>
                                ${pm.variance && Math.abs(pm.variance) > 0 ? safeNumber(pm.variance, 0) : '-'}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
        ` : ''}
    `;
    
    container.innerHTML = html;
}

// Export Monthly Report to PDF
function exportMonthlyReportToPDF(periodName) {
    try {
        const element = document.getElementById('monthlyReportContent');
        if (!element) {
            alert('Report not found');
            return;
        }
        
        const opt = {
            margin: 10,
            filename: `period_report_${periodName.replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { orientation: 'landscape', unit: 'mm', format: 'a4' }
        };
        
        // Clone the element to avoid modifying original
        const clone = element.cloneNode(true);
        
        // Remove the export button from PDF
        const buttons = clone.querySelectorAll('button');
        buttons.forEach(btn => {
            if (btn.textContent.includes('Export') || btn.textContent.includes('PDF')) {
                btn.remove();
            }
        });
        
        // Create PDF
        html2pdf().set(opt).from(clone).save();
        
    } catch (error) {
        console.error('PDF generation failed:', error);
        alert('Failed to generate PDF: ' + error.message);
    }
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
    if (modal) {
        modal.classList.add('active');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        // Reset form if it exists
        const form = modal.querySelector('form');
        if (form) form.reset();
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
};

console.log('✓ Admin dashboard loaded');
