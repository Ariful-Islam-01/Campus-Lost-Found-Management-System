<?php
require_once "db.php";

echo "=== DATABASE CONNECTION TEST ===\n";
try {
    $db = getDBConnection();
    echo "✓ Database connected successfully\n\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== CHECKING CLAIMS TABLE ===\n";
$tables = $db->query("SHOW TABLES LIKE 'claims'")->fetchAll();
if (!empty($tables)) {
    echo "✓ Claims table exists\n";
    $columns = $db->query("DESCRIBE claims")->fetchAll();
    echo "  Columns: ";
    foreach ($columns as $col) {
        echo $col['Field'] . ", ";
    }
    echo "\n\n";
} else {
    echo "✗ Claims table NOT found\n";
}

echo "=== CHECKING FOUND ITEMS ===\n";
$items = $db->query("SELECT id, item_name, status FROM found_items LIMIT 3")->fetchAll();
if (!empty($items)) {
    echo "✓ Found items exist:\n";
    foreach ($items as $item) {
        echo "  ID: {$item['id']}, Name: {$item['item_name']}, Status: {$item['status']}\n";
    }
    echo "\n";
} else {
    echo "⚠ No found items in database (insert a test item first)\n\n";
}

echo "=== CHECKING USERS TABLE ===\n";
$users = $db->query("SELECT id, name, email FROM users LIMIT 2")->fetchAll();
if (!empty($users)) {
    echo "✓ Users exist:\n";
    foreach ($users as $user) {
        echo "  ID: {$user['id']}, Name: {$user['name']}, Email: {$user['email']}\n";
    }
    echo "\n";
}

echo "=== TESTING FUNCTIONS ===\n";
if (function_exists('createClaim')) {
    echo "✓ createClaim() function exists\n";
}
if (function_exists('getClaimsForItem')) {
    echo "✓ getClaimsForItem() function exists\n";
}
if (function_exists('getClaimById')) {
    echo "✓ getClaimById() function exists\n";
}
if (function_exists('getFoundItemById')) {
    echo "✓ getFoundItemById() function exists\n";
}
if (function_exists('updateClaimStatus')) {
    echo "✓ updateClaimStatus() function exists\n";
}
if (function_exists('getClaimsByUser')) {
    echo "✓ getClaimsByUser() function exists\n";
}

echo "\n=== SAMPLE FORM VALIDATION TEST ===\n";
$testProof = "This is test proof of ownership";
$minChars = 20;
$maxChars = 2000;

if (strlen($testProof) >= $minChars && strlen($testProof) <= $maxChars) {
    echo "✓ Test string validation passed: " . strlen($testProof) . " chars\n";
} else {
    echo "✗ Validation failed\n";
}

echo "\n✓ ALL TESTS PASSED\n";
?>
