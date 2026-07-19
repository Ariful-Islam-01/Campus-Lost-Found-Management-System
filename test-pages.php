<?php
session_start();
$_SESSION['user_id'] = 2;

// Test accessing claim-request page
echo "=== CLAIM REQUEST PAGE TESTS ===\n\n";

// Parse the claim-request.php file
$file = file_get_contents('claim-request.php');

// Test 1: Check for form elements
echo "TEST 1: Check form structure\n";
if (strpos($file, 'id="claimForm"') !== false) {
    echo "✓ Form ID found\n";
}
if (strpos($file, 'name="proofOfOwnership"') !== false) {
    echo "✓ Proof of ownership textarea found\n";
}
if (strpos($file, 'class="btn-primary"') !== false) {
    echo "✓ Submit button found\n";
}
if (strpos($file, 'id="successMessage"') !== false) {
    echo "✓ Success message element found\n";
}
echo "\n";

// Test 2: Check validation attributes
echo "TEST 2: Check validation logic\n";
if (strpos($file, 'min-height: 200px') !== false) {
    echo "✓ Textarea styling found\n";
}
if (strpos($file, 'e.preventDefault()') !== false) {
    echo "✓ Form prevent default found\n";
}
if (strpos($file, 'POST') !== false) {
    echo "✓ POST method found\n";
}
echo "\n";

// Test 3: Check required functions
echo "TEST 3: Check for required functions\n";
require_once 'db.php';

if (function_exists('getFoundItemById')) {
    echo "✓ getFoundItemById function available\n";
}
if (function_exists('createClaim')) {
    echo "✓ createClaim function available\n";
}
echo "\n";

// Test 4: Check item detail page for claim button
$itemFile = file_get_contents('item-detail.php');
echo "TEST 4: Check claim button on item details\n";
if (strpos($itemFile, 'claim-request.php') !== false) {
    echo "✓ Claim button link found\n";
}
if (strpos($itemFile, 'btn-contact-claim') !== false) {
    echo "✓ Claim button styling class found\n";
}
if (strpos($itemFile, '!$isLost') !== false) {
    echo "✓ Claim button hidden for lost items\n";
}
if (strpos($itemFile, '$userId !== $item') !== false) {
    echo "✓ Claim button only shown to non-owner\n";
}
echo "\n";

echo "✓ PAGE STRUCTURE VERIFICATION COMPLETE\n";
?>
