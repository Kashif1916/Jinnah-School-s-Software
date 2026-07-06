<?php
/**
 * Login Page
 * School Finance Management System
 */

require_once 'config/config.php';
require_once 'config/db.php';
require_once 'includes/helpers.php';

// Check if database needs to be set up
if (isset($redirect_to_setup) && $redirect_to_setup) {
    header('Location: ' . BASE_URL . 'setup.php');
    exit();
}

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$error = '';
if (isset($_GET['error']) && $_GET['error'] === 'closed') {
    $error = 'Your account is closed/frozen for today. It will activate automatically at 12:00 AM.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    // Validation
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required!';
    } else {
        // Query user
        $query = "SELECT id, username, password, role, is_frozen, frozen_until FROM users WHERE username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Check freeze status
            $is_frozen = intval($user['is_frozen'] ?? 0);
            $frozen_until = $user['frozen_until'] ?? null;
            
            if ($is_frozen === 1) {
                if (!empty($frozen_until) && strtotime('now') >= strtotime($frozen_until)) {
                    // Auto-unfreeze
                    $unfreeze_query = "UPDATE users SET is_frozen = 0, frozen_until = NULL WHERE id = ?";
                    $u_stmt = $conn->prepare($unfreeze_query);
                    $u_stmt->bind_param('i', $user['id']);
                    $u_stmt->execute();
                    $u_stmt->close();
                    
                    $is_frozen = 0; // Unfrozen
                } else {
                    $error = 'Your account is closed/frozen for today. It will activate automatically at 12:00 AM.';
                }
            }
            
            if ($is_frozen === 0) {
                // Direct password comparison (as requested, no hashing)
                if ($password === $user['password']) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    
                    header('Location: ' . BASE_URL . 'index.php');
                    exit();
                } else {
                    $error = 'Invalid username or password!';
                }
            }
        } else {
            $error = 'Invalid username or password!';
        }
        
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            max-width: 450px;
            padding: 20px;
        }
        
        .login-box {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
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
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo {
            display: block;
            width: 90px;
            height: auto;
            margin: 0 auto 12px;
        }
        
        .login-header p {
            color: #999;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            color: #333;
            font-weight: 500;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-control {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #1f5f46;
            box-shadow: 0 0 0 3px rgba(31, 95, 70, 0.12);
            outline: none;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #1f5f46 0%, #10161b 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(31, 95, 70, 0.35);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: #fee;
            color: #c33;
            padding: 12px 15px;
        }
        
        .credentials-info {
            background: #f5f5f5;
            border-left: 4px solid #1f5f46;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 13px;
        }
        
        .credentials-info h6 {
            color: #1f5f46;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .credentials-info p {
            color: #666;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <?php echo render_system_logo('login-logo'); ?>
                <p>Jinnah School Finance Management System</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           required placeholder="Enter your username">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           required placeholder="Enter your password">
                </div>
                
                <button type="submit" class="btn-login">Login</button>
            </form>
            
            <div class="credentials-info">
                <h6>📋 Test Credentials:</h6>
                <p><strong>Master (Admin):</strong> master / 1234</p>
                <p><strong>Finance (Clerk):</strong> finance / 1234</p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>
