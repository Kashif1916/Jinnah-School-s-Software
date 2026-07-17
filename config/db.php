<?php
/**
 * Database Configuration
 * School Finance Management System
 */

// Set default timezone to Pakistan (Islamabad)
date_default_timezone_set('Asia/Karachi');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_finance');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    // Try to create the database
    $tempConn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if (!$tempConn->connect_error) {
        // Database doesn't exist, try to create it
        $result = $tempConn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
        
        if ($result && $result->num_rows == 0) {
            // Need to run setup
            global $redirect_to_setup;
            $redirect_to_setup = true;
        }
        $tempConn->close();
    }
    
    // If not redirecting, show error
    if (!isset($redirect_to_setup)) {
        die("Database Connection Error: " . $conn->connect_error);
    }
}

if (!isset($redirect_to_setup) && $conn && !$conn->connect_error) {
    $conn->set_charset("utf8mb4");
    $conn->query("SET time_zone = '+05:00'");
    
    // Dynamically ensure payment_mode column exists
    $colCheck = $conn->query("SHOW COLUMNS FROM `payments` LIKE 'payment_mode'");
    if ($colCheck && $colCheck->num_rows == 0) {
        $conn->query("ALTER TABLE `payments` ADD COLUMN `payment_mode` VARCHAR(20) DEFAULT 'cash'");
    }

    // Dynamically ensure expenses table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'expenses'");
    if ($tableCheck && $tableCheck->num_rows == 0) {
        $conn->query("CREATE TABLE `expenses` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `amount` DECIMAL(10, 2) NOT NULL,
            `reason` VARCHAR(255) NOT NULL,
            `user_id` INT NOT NULL,
            `username` VARCHAR(50) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // Dynamically ensure drop_reason column exists in students table
    $colCheckDrop = $conn->query("SHOW COLUMNS FROM `students` LIKE 'drop_reason'");
    if ($colCheckDrop && $colCheckDrop->num_rows == 0) {
        $conn->query("ALTER TABLE `students` ADD COLUMN `drop_reason` VARCHAR(255) DEFAULT NULL");
    }

    // Dynamically ensure admission_fee column exists in students table
    $colCheckAdmission = $conn->query("SHOW COLUMNS FROM `students` LIKE 'admission_fee'");
    if ($colCheckAdmission && $colCheckAdmission->num_rows == 0) {
        $conn->query("ALTER TABLE `students` ADD COLUMN `admission_fee` DECIMAL(10, 2) DEFAULT 0.00 AFTER fixed_monthly_fee");
    }

    // Dynamically ensure created_by column exists in students table
    $colCheckCreatedBy = $conn->query("SHOW COLUMNS FROM `students` LIKE 'created_by'");
    if ($colCheckCreatedBy && $colCheckCreatedBy->num_rows == 0) {
        $conn->query("ALTER TABLE `students` ADD COLUMN `created_by` VARCHAR(50) DEFAULT NULL");
    }

    // Dynamically ensure settings table exists
    $tableCheckSettings = $conn->query("SHOW TABLES LIKE 'settings'");
    if ($tableCheckSettings && $tableCheckSettings->num_rows == 0) {
        $conn->query("CREATE TABLE `settings` (
            `setting_key` VARCHAR(50) PRIMARY KEY,
            `setting_value` TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $conn->query("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES ('receipt_note', '')");
    }

    // Dynamically ensure fee_schedule table exists
    $tableCheckFS = $conn->query("SHOW TABLES LIKE 'fee_schedule'");
    if ($tableCheckFS && $tableCheckFS->num_rows == 0) {
        $conn->query("CREATE TABLE `fee_schedule` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `class` VARCHAR(50) UNIQUE NOT NULL,
            `fixed_monthly_fee` DECIMAL(10, 2) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // Dynamically ensure is_frozen and frozen_until columns exist in users table
    $colCheckFrozen = $conn->query("SHOW COLUMNS FROM `users` LIKE 'is_frozen'");
    if ($colCheckFrozen && $colCheckFrozen->num_rows == 0) {
        $conn->query("ALTER TABLE `users` ADD COLUMN `is_frozen` TINYINT DEFAULT 0");
    }
    $colCheckFrozenUntil = $conn->query("SHOW COLUMNS FROM `users` LIKE 'frozen_until'");
    if ($colCheckFrozenUntil && $colCheckFrozenUntil->num_rows == 0) {
        $conn->query("ALTER TABLE `users` ADD COLUMN `frozen_until` DATETIME DEFAULT NULL");
    }

    // Dynamically ensure edit_access column exists in users table
    $colCheckEditAccess = $conn->query("SHOW COLUMNS FROM `users` LIKE 'edit_access'");
    if ($colCheckEditAccess && $colCheckEditAccess->num_rows == 0) {
        $conn->query("ALTER TABLE `users` ADD COLUMN `edit_access` TINYINT DEFAULT 0");
    }

    // Dynamically ensure 'teacher' role exists in the role enum
    $colCheckRole = $conn->query("SHOW COLUMNS FROM `users` LIKE 'role'");
    if ($colCheckRole && $colCheckRole->num_rows > 0) {
        $row = $colCheckRole->fetch_assoc();
        if (isset($row['Type']) && strpos($row['Type'], 'teacher') === false) {
            $conn->query("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('master', 'finance', 'admission', 'teacher') NOT NULL");
        }
    }

    // Dynamically ensure dropped_students table exists
    $tableCheckDrops = $conn->query("SHOW TABLES LIKE 'dropped_students'");
    if ($tableCheckDrops && $tableCheckDrops->num_rows == 0) {
        $conn->query("CREATE TABLE `dropped_students` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT NOT NULL,
            `dropped_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `dropped_by` VARCHAR(50) NOT NULL,
            `drop_reason` VARCHAR(255) NOT NULL,
            FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

/**
 * Function to escape string
 */
function escape_string($string) {
    global $conn;
    if($conn) {
        return $conn->real_escape_string($string);
    }
    return $string;
}

/**
 * Function to execute query
 */
function execute_query($query) {
    global $conn;
    if($conn) {
        return $conn->query($query);
    }
    return false;
}

/**
 * Function to get last insert ID
 */
function get_last_id() {
    global $conn;
    if($conn) {
        return $conn->insert_id;
    }
    return 0;
}
