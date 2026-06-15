<?php
/**
 * Database Backup Utility - Finance
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_finance();

// Get all tables
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$sql_dump = "-- SFMS Database Backup (Finance Panel)\n";
$sql_dump .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
$sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    // Table structure
    $res = $conn->query("SHOW CREATE TABLE `$table`");
    $row = $res->fetch_row();
    $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n" . $row[1] . ";\n\n";

    // Table data
    $res = $conn->query("SELECT * FROM `$table`");
    while ($row = $res->fetch_assoc()) {
        $keys = array_keys($row);
        $values = array_values($row);
        $val_str = implode(", ", array_map(function($v) use ($conn) {
            if ($v === null) return "NULL";
            return "'" . $conn->real_escape_string($v) . "'";
        }, $values));
        
        $sql_dump .= "INSERT INTO `$table` (`" . implode("`, `", $keys) . "`) VALUES ($val_str);\n";
    }
    $sql_dump .= "\n\n";
}

$sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

$filename = "sfms_finance_backup_" . date("Y-m-d_H-i-s") . ".sql";
header('Content-Type: application/octet-stream');
header("Content-Transfer-Encoding: Binary");
header("Content-disposition: attachment; filename=\"" . $filename . "\"");
echo $sql_dump;
exit;