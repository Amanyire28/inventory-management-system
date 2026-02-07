/* ========================================
   CASHIER DASHBOARD JAVASCRIPT - TOPINV
   ======================================== */

// Global state for SALE
let currentSale = {
    items: [],
    total: 0,
    lastSaleId: null,
    lastSaleTime: null,
    draftId: null
};

// Global state for PURCHASE  
let currentPurchase = {
    productId: null,
    quantity: 1,
    costPrice: 0,
    supplier: ''
};

let selectedSaleProduct = null;
let selectedPurchaseProduct = null;
let cashierProducts = [];
let currentPeriod = null;

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    loadProductsFromAPI();
    getCurrentPeriod();
    loadCurrentUser();
    initCardToggle();
    initSaleProductSearch();
    initPurchaseProductSearch();
    loadRecentSales();
    
    // Initialize sale transaction datetime to now
    const now = new Date();
    const localDatetime = new Date(now.getTime() - now.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 16);
    document.getElementById('saleTransactionDatetime').value = localDatetime;
    
    // Expand sale card by default
    setTimeout(() => {
        const saleCardContent = document.querySelector('#saleCard .card-content');
        const saleCardStatus = document.querySelector('#saleCard .card-status');
        if (saleCardContent) {
            saleCardContent.classList.add('expanded');
            saleCardStatus.textContent = 'Expanded';
            console.log('✓ Sale card expanded by default');
        }
    }, 100);
});

/* ============================================
   CARD TOGGLE FUNCTIONALITY
   ============================================ */
function initCardToggle() {
    const saleCard = document.getElementById('saleCard');
    const purchaseCard = document.getElementById('purchaseCard');

    saleCard.querySelector('.card-header').addEventListener('click', function() {
        const content = saleCard.querySelector('.card-content');
        const status = saleCard.querySelector('.card-status');
        content.classList.toggle('expanded');
        status.textContent = content.classList.contains('expanded') ? 'Expanded' : 'Collapsed';
    });

    purchaseCard.querySelector('.card-header').addEventListener('click', function() {
        const content = purchaseCard.querySelector('.card-content');
        const status = purchaseCard.querySelector('.card-status');
        content.classList.toggle('expanded');
        status.textContent = content.classList.contains('expanded') ? 'Expanded' : 'Collapsed';
    });
}

// ============================================
// API CALLS
// ============================================
async function loadProductsFromAPI() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        const response = await fetch(`${API_BASE}/products`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to load products');
        }

        cashierProducts = data.data.products.map(p => ({
            id: p.id,
            name: p.name,
            code: p.code || '',
            price: parseFloat(p.selling_price),
            cost: parseFloat(p.cost_price),
            stock: p.current_stock,
            status: p.status
        })).filter(p => p.status === 'active');

        console.log(`✓ Loaded ${cashierProducts.length} active products`);
    } catch (error) {
        console.error('Failed to load products:', error);
        alert('Failed to load products: ' + error.message);
    }
}

// ============================================
// SALE PRODUCT SEARCH
// ============================================
function initSaleProductSearch() {
    const searchInput = document.getElementById('saleProductSearch');
    const dropdown = document.getElementById('saleProductDropdown');

    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim().toLowerCase();

        if (query.length < 2) {
            dropdown.innerHTML = '';
            dropdown.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(() => {
            const results = cashierProducts.filter(p => 
                p.name.toLowerCase().includes(query) || 
                p.code.toLowerCase().includes(query)
            ).slice(0, 15);

            if (results.length === 0) {
                dropdown.innerHTML = '<div class="dropdown-item-empty">No products found</div>';
                dropdown.style.display = 'block';
                return;
            }

            dropdown.innerHTML = results.map(p => `
                <div class="dropdown-item" onclick="selectSaleProduct(${p.id})">
                    <strong>${p.name}</strong>
                    <small>Code: ${p.code} | Stock: ${p.stock} units | Price: UGX ${p.price.toFixed(0)}</small>
                </div>
            `).join('');
            dropdown.style.display = 'block';
        }, 200);
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

function selectSaleProduct(productId) {
    const product = cashierProducts.find(p => p.id === productId);
    if (!product) return;

    selectedSaleProduct = product;

    // Hide dropdown
    document.getElementById('saleProductDropdown').style.display = 'none';

    // Show product info
    document.getElementById('saleProductName').textContent = product.name;
    document.getElementById('saleProductPrice').textContent = 'UGX ' + product.price.toFixed(0);
    document.getElementById('saleProductStock').textContent = product.stock + ' units available';
    document.getElementById('saleProductInfo').style.display = 'block';

    // Show quantity section
    document.getElementById('saleQuantity').value = 1;
    document.getElementById('saleQuantity').max = product.stock;
    document.getElementById('saleQtySection').style.display = 'block';

    // Calculate line total
    updateSaleLineTotal();

    console.log(`Selected product: ${product.name}`);
}

function updateSaleLineTotal() {
    if (!selectedSaleProduct) return;

    const qty = parseInt(document.getElementById('saleQuantity').value) || 0;
    const lineTotal = qty * selectedSaleProduct.price;

    document.getElementById('saleLineTotalAmount').textContent = lineTotal.toFixed(0);
    document.getElementById('saleLineTotal').style.display = 'block';

    // Validate quantity
    const errorEl = document.getElementById('saleQtyError');
    if (qty > selectedSaleProduct.stock) {
        errorEl.textContent = `⚠️ Only ${selectedSaleProduct.stock} units in stock`;
        errorEl.style.display = 'block';
    } else if (qty <= 0) {
        errorEl.textContent = '⚠️ Quantity must be at least 1';
        errorEl.style.display = 'block';
    } else {
        errorEl.style.display = 'none';
    }
}

function increaseSaleQty() {
    const input = document.getElementById('saleQuantity');
    const max = parseInt(input.max);
    const current = parseInt(input.value) || 0;
    if (current < max) {
        input.value = current + 1;
        updateSaleLineTotal();
    }
}

function decreaseSaleQty() {
    const input = document.getElementById('saleQuantity');
    const current = parseInt(input.value) || 0;
    if (current > 1) {
        input.value = current - 1;
        updateSaleLineTotal();
    }
}

// ============================================
// ADD TO SALE
// ============================================
function addSaleProduct() {
    if (!selectedSaleProduct) {
        alert('Please select a product first');
        return;
    }

    const qty = parseInt(document.getElementById('saleQuantity').value) || 0;

    if (qty <= 0) {
        alert('Please enter a valid quantity');
        return;
    }

    if (qty > selectedSaleProduct.stock) {
        alert(`Only ${selectedSaleProduct.stock} units available`);
        return;
    }

    // Add to cart
    currentSale.items.push({
        productId: selectedSaleProduct.id,
        name: selectedSaleProduct.name,
        price: selectedSaleProduct.price,
        quantity: qty
    });

    currentSale.total = currentSale.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);

    // Reset form
    document.getElementById('saleProductSearch').value = '';
    document.getElementById('saleProductInfo').style.display = 'none';
    document.getElementById('saleQtySection').style.display = 'none';
    document.getElementById('saleLineTotal').style.display = 'none';
    selectedSaleProduct = null;

    // Update display
    updateSaleItemsTable();
    updateSaleSummary();

    console.log(`Added ${qty}x ${currentSale.items[currentSale.items.length - 1].name} to sale`);
}

function updateSaleItemsTable() {
    const tbody = document.getElementById('saleItemsBody');
    const table = document.getElementById('saleItemsTable');
    const addButton = document.getElementById('saleAddButton');

    if (currentSale.items.length === 0) {
        tbody.innerHTML = '<tr class="empty-state"><td colspan="5" class="text-center">No items added</td></tr>';
        table.style.display = 'none';
        addButton.style.display = 'flex';
        return;
    }

    tbody.innerHTML = currentSale.items.map((item, idx) => `
        <tr>
            <td>${item.name}</td>
            <td>${item.quantity}</td>
            <td>UGX ${item.price.toFixed(0)}</td>
            <td>UGX ${(item.price * item.quantity).toFixed(0)}</td>
            <td><button class="btn btn-danger btn-sm" onclick="removeSaleItem(${idx})">✕</button></td>
        </tr>
    `).join('');

    table.style.display = 'block';
    addButton.style.display = 'none';
}

function updateSaleSummary() {
    const summaryEl = document.getElementById('saleSummary');
    const completeSaleBtn = document.getElementById('completeSaleBtn');

    if (currentSale.items.length === 0) {
        summaryEl.style.display = 'none';
        return;
    }

    document.getElementById('saleSaleSubtotal').textContent = currentSale.total.toFixed(0);
    document.getElementById('saleTaxAmount').textContent = '0.00';
    document.getElementById('saleTotalAmount').textContent = currentSale.total.toFixed(0);

    summaryEl.style.display = 'block';
    
    // Enable Complete Sale button
    completeSaleBtn.disabled = false;
}

function removeSaleItem(index) {
    currentSale.items.splice(index, 1);
    currentSale.total = currentSale.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    updateSaleItemsTable();
    updateSaleSummary();
}

// ============================================
// COMPLETE SALE
// ============================================
function completeSale() {
    if (currentSale.items.length === 0) {
        alert('Add items to complete the sale');
        return;
    }

    // Disable button during processing
    const button = event.target;
    button.disabled = true;
    button.textContent = 'Processing...';

    // Call API to complete sale
    commitSaleToAPI()
        .then(() => {
            // Reset sale
            currentSale.items = [];
            currentSale.total = 0;
            currentSale.draftId = null;
            
            // Reset form
            document.getElementById('saleItemsTable').style.display = 'none';
            document.getElementById('saleSummary').style.display = 'none';
            document.getElementById('saleAddButton').style.display = 'flex';
            document.getElementById('saleProductSearch').value = '';
            document.getElementById('saleProductInfo').style.display = 'none';
            document.getElementById('saleQtySection').style.display = 'none';
            
            // Reset transaction datetime to now
            const now = new Date();
            const localDatetime = new Date(now.getTime() - now.getTimezoneOffset() * 60000)
                .toISOString()
                .slice(0, 16);
            document.getElementById('saleTransactionDatetime').value = localDatetime;

            // Update receipts
            loadRecentSales();
            loadProductsFromAPI();
            loadCurrentUser();
            
            alert('Sale completed successfully!');
            button.disabled = false;
            button.textContent = 'Complete Sale';
        })
        .catch(error => {
            console.error('Sale completion error:', error);
            alert('Failed to complete sale: ' + error.message);
            button.disabled = false;
            button.textContent = 'Complete Sale';
        });
}

async function commitSaleToAPI() {
    try {
        console.log('Starting sale commit process...');
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        // Get transaction datetime (supports backdating)
        let transactionDate = document.getElementById('saleTransactionDatetime').value;
        if (transactionDate) {
            // Convert from datetime-local format to MySQL datetime format
            transactionDate = transactionDate.replace('T', ' ') + ':00';
        }

        // Create draft sale first
        console.log('Creating draft sale...');
        let draftResponse = await fetch(`${API_BASE}/sales/draft`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });

        const draftText = await draftResponse.text();
        console.log('Draft response text:', draftText);
        
        let draftData;
        try {
            draftData = JSON.parse(draftText);
        } catch (e) {
            console.error('Failed to parse draft response as JSON:', e);
            console.error('Response text was:', draftText);
            throw new Error('Server returned invalid JSON: ' + draftText.substring(0, 100));
        }
        
        console.log('Draft response:', draftData);
        if (!draftResponse.ok) {
            throw new Error(draftData.message || 'Failed to create draft sale');
        }

        const draftId = draftData.data.draft_id;
        console.log('Draft created with ID:', draftId);

        // Add items to draft
        console.log(`Adding ${currentSale.items.length} items to draft...`);
        for (const item of currentSale.items) {
            console.log(`Adding item: ${item.name} x${item.quantity}`);
            const itemResponse = await fetch(`${API_BASE}/sales/draft/${draftId}/items`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_id: item.productId,
                    quantity: item.quantity,
                    unit_price: item.price
                })
            });

            const itemText = await itemResponse.text();
            console.log('Item response text:', itemText);
            
            let itemData;
            try {
                itemData = JSON.parse(itemText);
            } catch (e) {
                console.error('Failed to parse item response as JSON:', e);
                console.error('Response text was:', itemText);
                throw new Error('Server returned invalid JSON: ' + itemText.substring(0, 100));
            }
            
            console.log('Item add response:', itemData);
            if (!itemResponse.ok) {
                throw new Error(itemData.message || 'Failed to add item to draft');
            }
        }

        // Commit the draft sale
        console.log('Committing draft sale...');
        const commitBody = {
            draft_id: draftId,
            period_id: currentPeriod ? currentPeriod.id : 1
        };
        
        if (transactionDate) {
            commitBody.transaction_date = transactionDate;
        }
        
        const commitResponse = await fetch(`${API_BASE}/sales/commit`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(commitBody)
        });

        const commitText = await commitResponse.text();
        console.log('Commit response text:', commitText);
        
        let commitData;
        try {
            commitData = JSON.parse(commitText);
        } catch (e) {
            console.error('Failed to parse commit response as JSON:', e);
            console.error('Response text was:', commitText);
            throw new Error('Server returned invalid JSON: ' + commitText.substring(0, 100));
        }
        
        console.log('Commit response:', commitData);
        if (!commitResponse.ok) {
            throw new Error(commitData.message || 'Failed to commit sale');
        }

        // Show receipt
        displayReceipt(commitData);
        console.log('✓ Sale committed successfully');
    } catch (error) {
        console.error('✗ Sale commit failed:', error);
        throw error;
    }
}

function displayReceipt(saleData) {
    const receipt = `
=============================================
           TOPINV CLINIC RECEIPT
=============================================
Date: ${new Date().toLocaleString()}

ITEMS:
${currentSale.items.map(i => 
    `${i.name}\n  Qty: ${i.quantity} x UGX ${i.price.toFixed(0)} = UGX ${(i.price * i.quantity).toFixed(0)}`
).join('\n\n')}

---------------------------------------------
Subtotal:     UGX ${currentSale.items.reduce((sum, i) => sum + (i.price * i.quantity), 0).toFixed(0)}
Tax (0%):     UGX 0.00

TOTAL:        UGX ${currentSale.total.toFixed(0)}
=============================================
        THANK YOU FOR YOUR BUSINESS!
=============================================
    `;

    console.log(receipt);
    
    // Could add print functionality here
    // window.print();
}

// ============================================
// RECENT SALES
// ============================================
async function loadRecentSales() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) return;

        const response = await fetch(`${API_BASE}/sales/history?limit=10`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await response.json();
        if (response.ok && data.data) {
            console.log(`Loaded ${data.data.sales.length} recent sales`);
        }
    } catch (error) {
        console.error('Failed to load recent sales:', error);
    }
}

// ============================================
// PURCHASE PRODUCT SEARCH (if needed)
// ============================================
function initPurchaseProductSearch() {
    // Similar to sale search if purchase form exists...
    console.log('Purchase search initialized');
}

// ============================================
// CURRENT USER & PERIOD
// ============================================
function safeNumber(value, defaultValue = 0) {
    const num = parseFloat(value);
    return isNaN(num) ? defaultValue : num;
}

async function loadCurrentUser() {
    try {
        const token = sessionStorage.getItem('authToken');
        if (!token) throw new Error('No auth token');

        const response = await fetch(`${API_BASE}/dashboard?action=user-summary`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to load user summary');
        }

        // Update header
        const userNameEl = document.getElementById('currentUserName');
        if (userNameEl) {
            userNameEl.textContent = `Logged in as: ${data.data.user.full_name || data.data.user.username}`;
        }

        // Update today's summary
        updateTodaySummary(data.data.today_stats);
    } catch (error) {
        console.error('Failed to load current user:', error);
    }
}

function updateTodaySummary(todayStats) {
    const revenueEl = document.getElementById('todayRevenue');
    const transactionsEl = document.getElementById('todayTransactions');
    
    if (revenueEl && todayStats) {
        revenueEl.textContent = `UGX ${safeNumber(todayStats.sales_revenue).toFixed(0)}`;
        transactionsEl.textContent = `${todayStats.transaction_count || 0} transactions`;
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
            const periodNameEl = document.getElementById('currentPeriodName');
            const periodStatusEl = document.getElementById('currentPeriodStatus');
            
            if (periodNameEl) {
                periodNameEl.textContent = currentPeriod.period_name;
            }
            if (periodStatusEl) {
                periodStatusEl.textContent = currentPeriod.status;
                periodStatusEl.className = `badge badge-${currentPeriod.status === 'OPEN' ? 'active' : 'closed'}`;
            }
            
            console.log(`Current period: ${currentPeriod.period_name} (${currentPeriod.status})`);
        }
    } catch (error) {
        console.error('Failed to load current period:', error);
    }
}

console.log('✓ Cashier dashboard loaded');
