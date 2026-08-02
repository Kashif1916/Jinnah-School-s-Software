<?php
/**
 * Login Page - Under Maintenance
 * School Finance Management System
 */

require_once 'config/config.php';
require_once 'includes/helpers.php';

session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode - <?php echo defined('SITE_NAME') ? SITE_NAME : 'School Management System'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background:
                radial-gradient(circle at top left, rgba(31, 95, 70, 0.22), transparent 26%),
                radial-gradient(circle at bottom right, rgba(16, 22, 27, 0.28), transparent 28%),
                linear-gradient(135deg, #0f1713 0%, #173326 48%, #1f5f46 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            width: 100%;
            max-width: 480px;
            padding: 20px;
        }
        
        .login-box {
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.25);
            padding: 40px 30px;
            text-align: center;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-logo {
            display: block;
            width: 90px;
            height: auto;
            margin: 0 auto 15px;
        }
        
        .maintenance-icon {
            font-size: 55px;
            color: #1f5f46;
            margin-bottom: 20px;
            animation: gearRotate 6s infinite linear;
        }

        @keyframes gearRotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .maintenance-title {
            color: #10161b;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .maintenance-desc {
            color: #666;
            font-size: 14.5px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .badge-status {
            background: #fdf5e6;
            color: #d97706;
            border: 1px solid #fcd34d;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="mb-3">
                <?php echo render_system_logo('login-logo'); ?>
            </div>
            
            <div class="maintenance-icon">
                <i class="fas fa-tools"></i>
            </div>

            <h4 class="maintenance-title">Software is Under Maintenance</h4>
            
            <p class="maintenance-desc">
                We are currently performing scheduled system updates and maintenance to serve you better. 
                Access to the system is temporarily disabled.
            </p>

            <div class="badge-status mb-2">
                <i class="fas fa-clock"></i> Please try again later
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>