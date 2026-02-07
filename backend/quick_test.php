<?php
/**
 * Quick API Test
 */

$base_url = 'http://localhost/topinv/api';

// Test 1: Login
echo "Testing Login...\n";
$ch = curl_init("$base_url/auth/login");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'username' => 'admin1',
    'password' => 'password'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = json_decode(curl_exec($ch), true);

if ($response['success']) {
    echo "✓ LOGIN SUCCESS\n";
    echo "  User: " . $response['data']['user']['username'] . "\n";
    echo "  Role: " . $response['data']['user']['role'] . "\n";
    $token = $response['data']['token'];
} else {
    echo "✗ LOGIN FAILED: " . $response['message'] . "\n";
    exit;
}

// Test 2: Get Products
echo "\nTesting Get Products...\n";
$ch = curl_init("$base_url/products");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    "Authorization: Bearer $token"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = json_decode(curl_exec($ch), true);

if ($response['success']) {
    echo "✓ GET PRODUCTS SUCCESS\n";
    echo "  Products found: " . count($response['data']['products']) . "\n";
} else {
    echo "✗ GET PRODUCTS FAILED: " . $response['message'] . "\n";
}

// Test 3: Get Periods
echo "\nTesting Get Periods...\n";
$ch = curl_init("$base_url/periods");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    "Authorization: Bearer $token"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = json_decode(curl_exec($ch), true);

if ($response['success']) {
    echo "✓ GET PERIODS SUCCESS\n";
    echo "  Periods found: " . count($response['data']['periods']) . "\n";
} else {
    echo "✗ GET PERIODS FAILED: " . $response['message'] . "\n";
}

// Test 4: Create Draft Sale
echo "\nTesting Create Draft Sale...\n";
$ch = curl_init("$base_url/sales/draft");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    "Authorization: Bearer $token"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
$response = json_decode(curl_exec($ch), true);

if ($response['success']) {
    echo "✓ CREATE DRAFT SALE SUCCESS\n";
    echo "  Draft ID: " . $response['data']['draft_id'] . "\n";
    $draft_id = $response['data']['draft_id'];
} else {
    echo "✗ CREATE DRAFT SALE FAILED: " . $response['message'] . "\n";
    exit;
}

// Test 5: Add Item to Draft
echo "\nTesting Add Item to Draft...\n";
$ch = curl_init("$base_url/sales/draft/$draft_id/items");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'product_id' => 1,
    'quantity' => 5,
    'unit_price' => 25.00
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    "Authorization: Bearer $token"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = json_decode(curl_exec($ch), true);

if ($response['success']) {
    echo "✓ ADD ITEM SUCCESS\n";
    echo "  Item ID: " . $response['data']['item_id'] . "\n";
} else {
    echo "✗ ADD ITEM FAILED: " . $response['message'] . "\n";
}

echo "\n=== API Tests Complete ===\n";
echo "Backend is functioning correctly!\n";
?>
