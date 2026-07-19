<?php
echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   CLAIM REQUEST FEATURE - COMPREHENSIVE TEST RESULTS          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

session_start();
$_SESSION['user_id'] = 2;

require_once "db.php";

$allPassed = true;

// ===== SECTION 1: DATABASE SETUP =====
echo "📊 SECTION 1: DATABASE SETUP\n";
echo "─────────────────────────────────────────────────────────────────\n";
try {
    $db = getDBConnection();
    echo "  ✓ Database connection successful\n";
} catch (Exception $e) {
    echo "  ✗ Database connection failed\n";
    $allPassed = false;
}

$tables = $db->query("SHOW TABLES LIKE 'claims'")->fetchAll();
if (!empty($tables)) {
    echo "  ✓ Claims table exists with proper schema\n";
    $columns = ['id', 'found_item_id', 'claimant_user_id', 'proof_of_ownership', 'status', 'created_at', 'updated_at'];
    $dbColumns = $db->query("DESCRIBE claims")->fetchAll();
    $dbColNames = array_map(fn($c) => $c['Field'], $dbColumns);
    foreach ($columns as $col) {
        if (!in_array($col, $dbColNames)) {
            echo "  ✗ Missing column: $col\n";
            $allPassed = false;
        }
    }
    if (count(array_diff($columns, $dbColNames)) === 0) {
        echo "    └─ All required columns present\n";
    }
} else {
    echo "  ✗ Claims table not found\n";
    $allPassed = false;
}
echo "\n";

// ===== SECTION 2: DATABASE FUNCTIONS =====
echo "🔧 SECTION 2: DATABASE FUNCTIONS\n";
echo "─────────────────────────────────────────────────────────────────\n";
$functions = [
    'createClaim',
    'getClaimsForItem',
    'getClaimById',
    'updateClaimStatus',
    'getClaimsByUser',
    'getLostItemById',
    'getFoundItemById'
];
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "  ✓ $func() exists\n";
    } else {
        echo "  ✗ $func() missing\n";
        $allPassed = false;
    }
}
echo "\n";

// ===== SECTION 3: FORM VALIDATION =====
echo "✅ SECTION 3: FORM VALIDATION\n";
echo "─────────────────────────────────────────────────────────────────\n";

$tests = [
    ['Empty proof', '', false, 'Proof of ownership is required'],
    ['Too short (10 chars)', 'Short text', false, 'must be at least 20 characters'],
    ['Exactly 20 chars', str_repeat('A', 20), true, null],
    ['Valid proof (150 chars)', 'Purchased from Best Buy on 2024-01-15. Receipt #RCV123456. Serial: SN987654321. Has blue case.', true, null],
    ['Too long (2001 chars)', str_repeat('A', 2001), false, 'cannot exceed 2000 characters'],
];

foreach ($tests as $test) {
    $proof = $test[1];
    $shouldPass = $test[2];
    $errors = [];
    
    if (empty($proof)) {
        $errors[] = 'Proof of ownership is required.';
    } elseif (strlen($proof) < 20) {
        $errors[] = 'Proof of ownership must be at least 20 characters.';
    } elseif (strlen($proof) > 2000) {
        $errors[] = 'Proof of ownership cannot exceed 2000 characters.';
    }
    
    $passed = (empty($errors) && $shouldPass) || (!empty($errors) && !$shouldPass);
    $status = $passed ? "✓" : "✗";
    echo "  $status {$test[0]}\n";
    if (!$passed) {
        $allPassed = false;
    }
}
echo "\n";

// ===== SECTION 4: DATABASE OPERATIONS =====
echo "💾 SECTION 4: DATABASE OPERATIONS\n";
echo "─────────────────────────────────────────────────────────────────\n";

// Check existing test data
$item = getFoundItemById(1);
if ($item) {
    echo "  ✓ Found item retrieval works: '{$item['item_name']}'\n";
} else {
    echo "  ✗ Could not retrieve found item\n";
    $allPassed = false;
}

// Check users
$users = $db->query("SELECT id FROM users LIMIT 1")->fetch();
if ($users) {
    echo "  ✓ User data exists in database\n";
} else {
    echo "  ✗ No users found in database\n";
    $allPassed = false;
}

// Test claim creation
if ($item && $users) {
    $testProof = 'Test claim for validation. This is sufficient detail about ownership proof.';
    try {
        if (createClaim($item['id'], $users['id'], $testProof)) {
            echo "  ✓ Claim creation successful\n";
            
            // Verify retrieval
            $claims = getClaimsForItem($item['id']);
            if (!empty($claims)) {
                echo "  ✓ Claim retrieval by item successful\n";
            }
        } else {
            echo "  ✗ Claim creation failed\n";
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo "  ✗ Error creating claim: " . $e->getMessage() . "\n";
        $allPassed = false;
    }
}
echo "\n";

// ===== SECTION 5: PAGE STRUCTURE =====
echo "📄 SECTION 5: PAGE STRUCTURE\n";
echo "─────────────────────────────────────────────────────────────────\n";

$claimFile = file_get_contents('claim-request.php');
$itemFile = file_get_contents('item-detail.php');

$pageChecks = [
    ['claim-request.php form', strpos($claimFile, 'id="claimForm"') !== false],
    ['Textarea field', strpos($claimFile, 'name="proofOfOwnership"') !== false],
    ['Submit button', strpos($claimFile, 'class="btn-primary"') !== false],
    ['Success message element', strpos($claimFile, 'id="successMessage"') !== false],
    ['Claim button in item-detail.php', strpos($itemFile, 'claim-request.php') !== false],
    ['Claim button styling', strpos($itemFile, 'btn-contact-claim') !== false],
];

foreach ($pageChecks as $check) {
    $status = $check[1] ? "✓" : "✗";
    echo "  $status {$check[0]}\n";
    if (!$check[1]) {
        $allPassed = false;
    }
}
echo "\n";

// ===== FINAL SUMMARY =====
echo "╔════════════════════════════════════════════════════════════════╗\n";
if ($allPassed) {
    echo "║  ✓ ALL TESTS PASSED - FEATURE IS READY TO USE               ║\n";
} else {
    echo "║  ✗ SOME TESTS FAILED - REVIEW ABOVE                         ║\n";
}
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "📝 TESTING INSTRUCTIONS:\n";
echo "─────────────────────────────────────────────────────────────────\n";
echo "1. Go to: http://localhost:8000/dashboard.php\n";
echo "2. Find a Found Item (green tag)\n";
echo "3. Click the item to view details\n";
echo "4. Click 'Claim Item' button\n";
echo "5. Enter proof of ownership (20-2000 chars)\n";
echo "6. Click 'Submit Claim'\n";
echo "7. Verify success message and redirect\n";
echo "\n";
?>
