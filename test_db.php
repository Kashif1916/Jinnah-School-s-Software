<?php
// Test connection
echo "Testing database connection...";
$conn = new mysqli("localhost", "root", "", "school_finance");

if ($conn->connect_error) {
    echo "Connection failed: " . $conn->connect_error;
    exit;
}

echo "Connection successful!";
$conn->close();
?>
