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
?>
