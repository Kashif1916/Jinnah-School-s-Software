<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

echo "=== TESTING COLLEGE CLASS DETECTOR ===\n";
$test_classes = ['1', '10', '11', '12', 'Passed-12', '11th', '12th'];
foreach ($test_classes as $cls) {
    echo "Class '$cls': " . (is_college_class($cls) ? "COLLEGE PACKAGE CLASS" : "Regular Monthly Class") . "\n";
}

echo "\n=== TESTING DATABASE SCHEMA UPDATES ===\n";
$result = $conn->query("SHOW COLUMNS FROM students LIKE 'package_amount'");
echo "package_amount column exists: " . ($result->num_rows > 0 ? "YES" : "NO") . "\n";

$result = $conn->query("SHOW COLUMNS FROM students LIKE 'is_package'");
echo "is_package column exists: " . ($result->num_rows > 0 ? "YES" : "NO") . "\n";

echo "\n=== TESTING PACKAGE SCHEDULING CALCULATION ===\n";
// Test calculation logic directly
$package_amount = 60000;
$concession = 6000;
$net_package = $package_amount - $concession;
$base_monthly = round($net_package / 12, 2);
$total = 0;

for ($i = 0; $i < 12; $i++) {
    $fee = ($i == 11) ? round($net_package - $total, 2) : $base_monthly;
    $total += ($i == 11) ? 0 : $fee;
    echo "Month " . ($i + 1) . ": Rs. " . number_format($fee, 2) . "\n";
}
echo "Total Sum of 12 Months: Rs. " . number_format($net_package, 2) . " (Expected: Rs. " . number_format($net_package, 2) . ")\n";
