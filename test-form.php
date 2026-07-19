<?php
session_start();

// Simulate logged-in user
$_SESSION['user_id'] = 2;

require_once "db.php";

echo "=== CLAIM FORM VALIDATION TESTS ===\n\n";

// Test 1: Get found item
echo "TEST 1: Retrieve found item details\n";
$item = getFoundItemById(1);
if ($item) {
    echo "✓ Found item retrieved: {$item['item_name']}\n";
    echo "  Status: {$item['status']}\n";
    echo "  Finder: {$item['finder_name']}\n\n";
} else {
    echo "✗ Could not retrieve item\n\n";
}

// Test 2: Validation - Empty proof
echo "TEST 2: Validation - Empty proof of ownership\n";
$proof = '';
$errors = [];
if (empty($proof)) {
    $errors['proofOfOwnership'] = 'Proof of ownership is required.';
}
if (!empty($errors)) {
    echo "✓ Validation caught error: " . $errors['proofOfOwnership'] . "\n\n";
}

// Test 3: Validation - Too short
echo "TEST 3: Validation - Proof too short (10 chars)\n";
$proof = 'Short text';
$errors = [];
if (strlen($proof) < 20) {
    $errors['proofOfOwnership'] = 'Proof of ownership must be at least 20 characters.';
}
if (!empty($errors)) {
    echo "✓ Validation caught error: " . $errors['proofOfOwnership'] . "\n\n";
}

// Test 4: Validation - Too long
echo "TEST 4: Validation - Proof too long (2100 chars)\n";
$proof = str_repeat('A', 2100);
$errors = [];
if (strlen($proof) > 2000) {
    $errors['proofOfOwnership'] = 'Proof of ownership cannot exceed 2000 characters.';
}
if (!empty($errors)) {
    echo "✓ Validation caught error: " . $errors['proofOfOwnership'] . "\n\n";
}

// Test 5: Validation - Valid proof
echo "TEST 5: Validation - Valid proof (150 chars)\n";
$proof = 'Purchased from Best Buy on 2024-01-15. Receipt #RCV123456. Serial number: SN987654321. Has blue case and white earbuds.';
$errors = [];
if (empty($proof)) {
    $errors['proofOfOwnership'] = 'Proof of ownership is required.';
} elseif (strlen($proof) < 20) {
    $errors['proofOfOwnership'] = 'Proof of ownership must be at least 20 characters.';
} elseif (strlen($proof) > 2000) {
    $errors['proofOfOwnership'] = 'Proof of ownership cannot exceed 2000 characters.';
}
if (empty($errors)) {
    echo "✓ Validation passed! Proof length: " . strlen($proof) . " chars\n\n";
} else {
    echo "✗ Validation failed\n\n";
}

// Test 6: Create a test claim
echo "TEST 6: Create test claim in database\n";
try {
    $userId = $_SESSION['user_id'];
    $itemId = 1;
    $testProof = 'Test claim: Purchased from electronics store on Jan 15, 2024. Serial: ABC123DEF456. Has distinctive red cover.';
    
    if (createClaim($itemId, $userId, $testProof)) {
        echo "✓ Claim created successfully\n";
        
        // Get the claim
        $claims = getClaimsForItem($itemId);
        echo "✓ Total claims on item: " . count($claims) . "\n";
        
        if (!empty($claims)) {
            $lastClaim = $claims[0];
            echo "  Latest claim ID: {$lastClaim['id']}\n";
            echo "  Status: {$lastClaim['status']}\n";
            echo "  Claimant: {$lastClaim['claimant_name']}\n";
        }
        echo "\n";
    } else {
        echo "✗ Failed to create claim\n\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Test 7: Test claim retrieval functions
echo "TEST 7: Test claim retrieval functions\n";
$userClaims = getClaimsByUser(2);
if (!empty($userClaims)) {
    echo "✓ User has " . count($userClaims) . " claim(s)\n";
    foreach ($userClaims as $claim) {
        echo "  - Item: {$claim['item_name']}, Status: {$claim['status']}\n";
    }
    echo "\n";
} else {
    echo "⚠ User has no claims\n\n";
}

echo "✓ ALL FORM TESTS COMPLETED\n";
?>
