/* ============================================
   COMMON JAVASCRIPT - Shared Functions
   ============================================ */

// Global variables
let currentUser = null;
let sessionToken = null;
// API_BASE is defined in index.php - use window.API_BASE

// Update current time
function updateCurrentTime() {
    const timeEl = document.getElementById('currentTime');
    if (!timeEl) return; // Element doesn't exist on this page
    
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    timeEl.textContent = timeStr;
}

// Only set up timer if currentTime element exists
if (document.getElementById('currentTime')) {
    setInterval(updateCurrentTime, 1000);
    updateCurrentTime();
}

// Toggle password visibility
function togglePassword() {
    const passwordField = document.getElementById('password');
    const button = event.target;
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        button.textContent = '👁️‍🗨️';
    } else {
        passwordField.type = 'password';
        button.textContent = '👁️';
    }
}

// Logout function
function logout() {
    console.log('Logout function triggered');
    if (confirm('Are you sure you want to logout?')) {
        sessionStorage.clear();
        localStorage.clear();
        // Redirect to root login (relative path works anywhere)
        window.location.href = '../';
    }
}

// Modal functions
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
    }
}

// Close modal on background click
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
});

// Alert functions
function showAlert(elementId, message, type = 'info') {
    const alertEl = document.getElementById(elementId);
    if (alertEl) {
        alertEl.className = `alert alert-${type}`;
        alertEl.textContent = message;
        alertEl.style.display = 'block';
        
        setTimeout(() => {
            alertEl.style.display = 'none';
        }, 5000);
    }
}

// Format currency
function formatCurrency(amount) {
    return 'UGX ' + new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}

// Format date
function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Format datetime
function formatDateTime(date) {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Navigation - switch sections
function initNavigation() {
    const navItems = document.querySelectorAll('.nav-item[data-section]');
    
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            
            const sectionId = item.getAttribute('data-section');
            const section = document.getElementById(sectionId);
            
            if (section) {
                // Hide all sections
                document.querySelectorAll('.section').forEach(s => {
                    s.classList.remove('active');
                });
                
                // Remove active class from all nav items
                document.querySelectorAll('.nav-item').forEach(nav => {
                    nav.classList.remove('active');
                });
                
                // Show selected section
                section.classList.add('active');
                item.classList.add('active');
                
                // Update page title
                const titleEl = document.getElementById('pageTitle');
                if (titleEl) {
                    titleEl.textContent = item.textContent.trim();
                }
            }
        });
    });
}

// API call function
async function apiCall(endpoint, method = 'GET', data = null) {
    const url = `${API_BASE}${endpoint}`;
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${sessionToken}`
        }
    };
    
    if (data) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'API Error');
        }
        
        return result;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// Initialize dashboard
function initDashboard() {
    // Load auth token and user from sessionStorage
    const userStr = sessionStorage.getItem('currentUser');
    const token = sessionStorage.getItem('authToken');
    
    if (!userStr || !token) {
        // Not authenticated - redirect to login (relative path)
        window.location.href = '../';
        return;
    }
    
    currentUser = JSON.parse(userStr);
    sessionToken = token;
    
    // Update user display
    if (document.getElementById('userName')) {
        document.getElementById('userName').textContent = currentUser.full_name || currentUser.username;
    }
    
    const userEl = document.getElementById('currentUser');
    if (userEl) {
        userEl.textContent = `${currentUser.role}: ${currentUser.name || currentUser.full_name}`;
    }
    
    initNavigation();
}

// Print function
function printReceipt() {
    const printContent = document.getElementById('receiptContent').innerHTML;
    const printWindow = window.open('', '', 'height=500,width=500');
    printWindow.document.write('<pre>' + printContent + '</pre>');
    printWindow.document.close();
    printWindow.print();
}

// Export to CSV
function exportToCSV(data, filename = 'export.csv') {
    let csv = '';
    
    // Headers
    if (data.length > 0) {
        csv = Object.keys(data[0]).join(',') + '\n';
        
        // Data rows
        data.forEach(row => {
            csv += Object.values(row).join(',') + '\n';
        });
    }
    
    // Create blob and download
    const blob = new Blob([csv], { type: 'text/csv' });
    const link = document.createElement('a');
    link.href = window.URL.createObjectURL(blob);
    link.download = filename;
    link.click();
}

// Number formatting
function formatNumber(num) {
    return num.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Validation functions
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePhone(phone) {
    const re = /^(\+\d{1,2}\s)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}$/;
    return re.test(phone);
}

// Debounce function for search
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/* ============================================
   MOBILE RESPONSIVENESS
   ============================================ */

// Detect if device is mobile
function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

// Handle touch events for better mobile UX
function initMobileUI() {
    // Prevent pinch zoom for better UX
    document.addEventListener('gesturestart', function(e) {
        e.preventDefault();
    });

    // Handle sidebar scroll on mobile
    const sidebar = document.querySelector('.sidebar');
    if (sidebar && window.innerWidth <= 768) {
        let isScrolling = false;
        sidebar.addEventListener('touchstart', function() {
            isScrolling = true;
        });
        sidebar.addEventListener('touchend', function() {
            isScrolling = false;
        });
    }

    // Improve touch targets - ensure buttons are at least 44x44px
    const buttons = document.querySelectorAll('button, .btn, a.nav-item');
    buttons.forEach(btn => {
        const style = window.getComputedStyle(btn);
        const height = parseFloat(style.height);
        const width = parseFloat(style.width);
        
        // If button is smaller than recommended touch target, add padding
        if (height < 44 || width < 44) {
            if (isMobileDevice()) {
                btn.style.minHeight = '44px';
                btn.style.minWidth = '44px';
                btn.style.display = 'flex';
                btn.style.alignItems = 'center';
                btn.style.justifyContent = 'center';
            }
        }
    });

    // Add touch feedback for interactive elements
    document.addEventListener('touchstart', function(e) {
        if (e.target.matches('button, .btn, a, input[type="submit"]')) {
            e.target.style.opacity = '0.8';
        }
    });

    document.addEventListener('touchend', function(e) {
        if (e.target.matches('button, .btn, a, input[type="submit"]')) {
            e.target.style.opacity = '1';
        }
    });
}

// Hide address bar and optimize viewport on mobile
function optimizeViewport() {
    // Prevent zoom on input focus (iOS)
    if (/iPhone|iPad|iPod/.test(navigator.userAgent)) {
        document.addEventListener('touchend', function() {
            document.activeElement.blur();
        });
    }
}

// Handle window resize for responsive adjustments
function handleResponsiveLayout() {
    const width = window.innerWidth;
    const sidebar = document.querySelector('.sidebar');
    const contentWrapper = document.querySelector('.content-wrapper');

    if (width <= 768 && sidebar && contentWrapper) {
        // Mobile layout adjustments
        document.body.style.fontSize = '14px';
    } else if (width > 1024) {
        // Desktop layout
        document.body.style.fontSize = '14px';
    }
}

// Add keyboard support for better mobile accessibility
function initKeyboardSupport() {
    document.addEventListener('keydown', function(e) {
        // ESC key to close modals
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal.active');
            modals.forEach(modal => {
                modal.classList.remove('active');
            });
        }

        // Tab key for form navigation
        if (e.key === 'Tab') {
            const form = e.target.closest('form');
            if (form) {
                const inputs = form.querySelectorAll('input, select, textarea, button');
                const index = Array.from(inputs).indexOf(e.target);
                if (index === inputs.length - 1 && !e.shiftKey) {
                    e.preventDefault();
                    inputs[0].focus();
                }
            }
        }
    });
}

// Initialize mobile optimizations
window.addEventListener('load', function() {
    if (isMobileDevice()) {
        initMobileUI();
        optimizeViewport();
    }
    handleResponsiveLayout();
    initKeyboardSupport();
});

// Re-check layout on window resize
window.addEventListener('resize', function() {
    handleResponsiveLayout();
});

// Initialize on page load - only for dashboard pages
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize dashboard if we have dashboard elements (not on login page)
    const hasDashboardElements = document.querySelector('.dashboard-container') || 
                                  document.querySelector('.sidebar') ||
                                  document.getElementById('mainContent');
    
    if (hasDashboardElements) {
        initDashboard();
    }
});
