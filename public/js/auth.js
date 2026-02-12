/* ============================================
   AUTHENTICATION - Connected to Backend API
   ============================================ */

// Ensure API_BASE is available
const API_BASE = window.API_BASE || '/api';

function setupLoginForm() {
    const loginForm = document.getElementById('loginForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const rememberMe = document.getElementById('rememberMe').checked;
            
            // Clear previous messages
            document.getElementById('loginError').style.display = 'none';
            document.getElementById('loginSuccess').style.display = 'none';
            
            // Disable button while processing
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Logging in...';
            
            try {
                // Call actual API
                const response = await authenticateUser(username, password);
                
                if (response.success) {
                    // Store auth token and user info for frontend
                    sessionStorage.setItem('authToken', response.data.token);
                    sessionStorage.setItem('currentUser', JSON.stringify(response.data.user));
                    
                    if (rememberMe) {
                        localStorage.setItem('username', username);
                    }
                    
                    // Show success message
                    showLoginSuccess('✓ Login successful! Redirecting...');
                    
                    // Redirect to dashboards in public folder
                    const target = response.data.user.role === 'admin' ? './public/admin.html' : './public/cashier.html';
                    
                    setTimeout(() => {
                        window.location.href = target;
                    }, 1500);
                } else {
                    showLoginError('❌ ' + (response.message || 'Login failed. Please try again.'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Login';
                }
            } catch (error) {
                showLoginError('❌ Connection error. Please check your internet and try again.');
                console.error('Login error:', error);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Login';
            }
        });
        
        // Load remembered username
        const rememberedUsername = localStorage.getItem('username');
        if (rememberedUsername) {
            document.getElementById('username').value = rememberedUsername;
            document.getElementById('rememberMe').checked = true;
        }
    }
}

// Setup form immediately and also on DOMContentLoaded as fallback
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupLoginForm);
} else {
    setupLoginForm();
}

// Call backend API for authentication
async function authenticateUser(username, password) {
    try {
        const response = await fetch(`${API_BASE}/auth/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ username, password })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            return {
                success: false,
                message: data.message || 'Authentication failed'
            };
        }
        
        return data;
    } catch (error) {
        console.error('API error:', error);
        return {
            success: false,
            message: 'Invalid username or password'
        };
    }
}

function showLoginError(message) {
    const errorEl = document.getElementById('loginError');
    errorEl.textContent = '❌ ' + message;
    errorEl.style.display = 'block';
}

function showLoginSuccess(message) {
    const successEl = document.getElementById('loginSuccess');
    successEl.textContent = '✓ ' + message;
    successEl.style.display = 'block';
}
