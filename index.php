<?php
/**
 * TOPINV - Main Application Entry Point
 * 
 * This file serves as the primary landing page (Login)
 * Based on the working public/index.html code
 */

// Handle logout
if (isset($_GET['logout'], $_GET['token'])) {
    // For JS-based auth, we usually just clear sessionStorage on the client side
    // but we can also handle it here if redirecting
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOPINV - Login</title>
    <!-- Use absolute paths to ensure it works from root -->
    <link rel="stylesheet" href="/topinv/public/css/style.css">
    <style>
        /* Ensure centering works even when loaded in root */
        body.login-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background-color: #e0f2fe; /* light blue background as requested */
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box" style="background: white !important;"> <!-- white form background as requested -->
            <div class="login-header">
                <h1>TOPINV</h1>
                <p>Clinic Inventory Management System</p>
            </div>

            <form id="loginForm" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required placeholder="Enter your username">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required placeholder="Enter your password">
                        <button type="button" class="toggle-password" onclick="togglePassword()">👁️</button>
                    </div>
                </div>

                <div class="form-group checkbox">
                    <input type="checkbox" id="rememberMe" name="rememberMe">
                    <label for="rememberMe">Remember me</label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Login</button>

                <div id="loginError" class="alert alert-danger" style="display: none;"></div>
                <div id="loginSuccess" class="alert alert-success" style="display: none;"></div>
            </form>
        </div>
    </div>

    <!-- Core Scripts -->
    <script>
        // API Configuration
        window.API_BASE = '/topinv/api';
    </script>
    <script src="/topinv/public/js/common.js"></script>
    <script src="/topinv/public/js/auth.js"></script>

    <script>
    // Auto-redirect if already logged in
    document.addEventListener('DOMContentLoaded', function() {
        const token = sessionStorage.getItem('authToken');
        const userStr = sessionStorage.getItem('currentUser');
        
        if (token && userStr) {
            const user = JSON.parse(userStr);
            const target = user.role === 'admin' ? '/topinv/public/admin.html' : '/topinv/public/cashier.html';
            window.location.href = target;
        }
    });
    </script>
</body>
</html>
