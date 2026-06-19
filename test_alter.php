<?php
require_once 'config/db.php';
$res = $conn->query("SELECT * FROM fee_records WHERE student_id = 20");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
