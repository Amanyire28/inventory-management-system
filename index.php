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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>TOPINV - Login</title>
    <!-- Use relative paths for hosting compatibility -->
    <link rel="stylesheet" href="public/css/style.css">
    <style>
        /* Ensure centering works even when loaded in root */
        body.login-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background-color: #e0f2fe;
            position: fixed;
            width: 100%;
            height: 100%;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Mobile-specific login styles */
        @media (max-width: 768px) {
            body.login-page {
                padding: 20px 0;
                align-items: flex-start;
            }

            .login-container {
                width: 100%;
                max-width: 100%;
                padding: 15px;
                margin: auto 0;
            }

            .login-box {
                padding: 30px 20px !important;
                border-radius: 12px !important;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1) !important;
            }

            .login-header h1 {
                font-size: 26px !important;
            }

            .login-header p {
                font-size: 13px !important;
            }

            .form-group input {
                font-size: 16px !important;
                padding: 12px 14px !important;
            }

            .btn-block {
                padding: 14px !important;
                font-size: 16px !important;
            }
        }

        @media (max-width: 480px) {
            body.login-page {
                padding: 15px 0;
            }

            .login-container {
                padding: 10px;
            }

            .login-box {
                padding: 25px 15px !important;
                border-radius: 10px !important;
            }

            .login-header h1 {
                font-size: 22px !important;
            }

            .login-header p {
                font-size: 12px !important;
            }

            .form-group {
                margin-bottom: 18px !important;
            }

            .form-group label {
                font-size: 13px !important;
            }
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box" style="background: white !important;"> <!-- white form background as requested -->
            <div class="login-header">
                <h1>TOP MEDICAL CLINIC</h1>
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
        // API Configuration - Relative path for any hosting environment
        window.API_BASE = './api';
    </script>
    <script src="public/js/common.js"></script>
    <script src="public/js/auth.js"></script>

    <script>
    // Auto-redirect if already logged in
    document.addEventListener('DOMContentLoaded', function() {
        const token = sessionStorage.getItem('authToken');
        const userStr = sessionStorage.getItem('currentUser');
        
        if (token && userStr) {
            const user = JSON.parse(userStr);
            const target = user.role === 'admin' ? './public/admin.html' : './public/cashier.html';
            window.location.href = target;
        }
    });
    </script>
</body>
</html>
