<?php
/**
 * Database Configuration
 * School Finance Management System
 */

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
} else {
    $conn->set_charset("utf8mb4");
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
