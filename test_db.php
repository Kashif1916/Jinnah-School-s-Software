<?php
// Test connection
echo "Testing database connection...\n";
$conn = new mysqli("localhost", "root", "", "school_finance");

if ($conn->connect_error) {
    echo "Connection failed: " . $conn->connect_error . "\n";
    exit;
}

echo "Connection successful!\n";

// Get columns
echo "\n--- Columns ---\n";
$res = $conn->query("SHOW COLUMNS FROM users");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

// Get users
echo "\n--- Users ---\n";
$res = $conn->query("SELECT id, username, role FROM users");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

$conn->close();
?>
