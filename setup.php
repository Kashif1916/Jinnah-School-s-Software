<?php
/**
 * Database Setup Script
 * This script will create the database automatically if it doesn't exist
 */

// Disable display_errors initially to catch fatal errors
ini_set('display_errors', 0);

// Connect without selecting database first
$conn = new mysqli("localhost", "root", "");

if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

// Check if database already exists
$result = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'school_finance'");
if ($result && $result->num_rows > 0) {
    // Database already exists
    $setup_success = true;
    $database_already_exists = true;
    $conn->close();
} else {
    // Read the SQL file
    $sqlFile = __DIR__ . '/database.sql';
    if (!file_exists($sqlFile)) {
        die("Database SQL file not found at: " . $sqlFile);
    }

    $sql = file_get_contents($sqlFile);

    // Execute the SQL file
    if ($conn->multi_query($sql)) {
        // Clear any remaining queries
        while ($conn->next_result()) {;}
        
        // Show success page
        $setup_success = true;
        $database_already_exists = false;
    } else {
        $setup_error = "Error setting up database: " . $conn->error;
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - School Finance Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
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
        
        .setup-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 50px;
            max-width: 500px;
            text-align: center;
        }
        
        .setup-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        .setup-icon.success {
            color: #27ae60;
        }
        
        .setup-icon.error {
            color: #e74c3c;
        }
        
        h1 {
            color: #333;
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        p {
            color: #666;
            margin-bottom: 10px;
            line-height: 1.6;
        }
        
        .credentials {
            background: #f5f5f5;
            border-left: 4px solid #1f5f46;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: left;
        }
        
        .credentials h6 {
            color: #1f5f46;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .credentials p {
            margin: 5px 0;
            font-size: 14px;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #1f5f46 0%, #10161b 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(31, 95, 70, 0.35);
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <?php if (isset($setup_success)): ?>
            <div class="setup-icon success">✓</div>
            <h1><?php echo isset($database_already_exists) && $database_already_exists ? 'Database Ready!' : 'Setup Complete!'; ?></h1>
            <p><?php echo isset($database_already_exists) && $database_already_exists ? 'The database is already initialized and ready to use.' : 'The database has been successfully created with all tables and initial data.'; ?></p>
            
            <div class="credentials">
                <h6>📋 Default Credentials</h6>
                <p><strong>Master (Admin):</strong> master / 1234</p>
                <p><strong>Finance (Clerk):</strong> finance / 1234</p>
            </div>
            
            <p>You can now login to the system. Click the button below to proceed.</p>
            
            <a href="login.php" class="btn-login">Go to Login</a>
        <?php elseif (isset($setup_error)): ?>
            <div class="setup-icon error">✕</div>
            <h1>Setup Failed</h1>
            <p><?php echo $setup_error; ?></p>
            <p>Please contact the administrator or check the database connection.</p>
        <?php else: ?>
            <div class="setup-icon" style="animation: spin 1s linear infinite; display: inline-block;">⚙️</div>
            <h1>Setting Up Database...</h1>
            <p>Please wait while the database is being created.</p>
            <script>
                setTimeout(function() {
                    location.reload();
                }, 2000);
            </script>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</body>
</html>
