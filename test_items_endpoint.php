<?php
// Quick test of the /sales/draft/:id/items endpoint
$apiBase = 'http://localhost/topinv/api';

// First login
echo "Testing Add Item endpoint...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/topinv/api/auth/login');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => 'cashier1', 'password' => 'password']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$resp = curl_exec($ch);
$result = json_decode($resp, true);
$token = $result['data']['token'] ?? null;

if (!$token) {
    echo "❌ Login failed\n";
    exit(1);
}

echo "✓ Login successful\n";

// Create draft
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/topinv/api/sales/draft');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$resp = curl_exec($ch);
$result = json_decode($resp, true);
$draftId = $result['data']['draft_id'] ?? null;

if (!$draftId) {
    echo "❌ Draft creation failed: " . json_encode($result) . "\n";
    exit(1);
}

echo "✓ Draft created: " . $draftId . "\n";

// Test Add Item - Try different URLs
$testUrls = [
    "http://localhost/topinv/api/sales/draft/{$draftId}/items",
    "http://localhost/topinv/api/sales/draft/{$draftId}/items/",
];

foreach ($testUrls as $url) {
    echo "\nTesting URL: {$url}\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'product_id' => 1,
        'quantity' => 2,
        'unit_price' => 50.00
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    $result = json_decode($resp, true);
    
    echo "Response: " . json_encode($result) . "\n";
    
    if ($result['success'] ?? false) {
        echo "✓ SUCCESS!\n";
        break;
    }
}
