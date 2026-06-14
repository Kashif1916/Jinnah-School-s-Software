<?php
$conn = new mysqli('localhost', 'root', '', 'school_finance');
if($conn->connect_error) {
    echo 'Database Error: ' . $conn->connect_error;
} else {
    echo 'Database Connected Successfully' . "\n";
    $result = $conn->query('SELECT COUNT(*) as cnt FROM users');
    if($result) {
        $row = $result->fetch_assoc();
        echo 'Users table has ' . $row['cnt'] . ' records' . "\n";
        
        // List users
        $users = $conn->query('SELECT id, username, role FROM users');
        while($user = $users->fetch_assoc()) {
            echo '  - ' . $user['username'] . ' (' . $user['role'] . ')' . "\n";
        }
    } else {
        echo 'Error querying users table: ' . $conn->error;
    }
}
?>