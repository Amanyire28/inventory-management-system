<?php
// Test what's actually being sent to the API
$token = 'test_token';
$draftId = 1;

$url = "http://localhost/topinv/api/sales/draft/{$draftId}/items";
$data = [
    'product_id' => 1,
    'quantity' => 2,
    'unit_price' => 50.00
];

echo "URL: {$url}\n";
echo "Data: " . json_encode($data) . "\n\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_VERBOSE, true);

// Capture verbose output
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$info = curl_getinfo($ch);

rewind($verbose);
$verboseLog = stream_get_contents($verbose);

echo "HTTP Code: " . $info['http_code'] . "\n";
echo "Response: " . $response . "\n";
echo "\nVerbose Log:\n";
echo $verboseLog;

curl_close($ch);
fclose($verbose);