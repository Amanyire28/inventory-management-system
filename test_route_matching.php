<?php
// Quick test of the routing system directly
require_once(__DIR__ . '/backend/core/Database.php');
require_once(__DIR__ . '/backend/core/Auth.php');
require_once(__DIR__ . '/backend/core/Response.php');
require_once(__DIR__ . '/backend/core/Router.php');

// Simulate request
$_SERVER['REQUEST_URI'] = '/topinv/api/sales/draft/1/items';
$_SERVER['REQUEST_METHOD'] = 'POST';

$router = new Router();

// Test matching
echo "Testing route matching...\n";
echo "Request path: " . $_SERVER['REQUEST_URI'] . "\n\n";

// Create test pattern
$pattern = '/sales/draft/:id/items';
echo "Pattern: {$pattern}\n";

// Check if the private method would match
// Let's replicate the logic
$basePath = '/topinv/api';
$requestPath = $_SERVER['REQUEST_URI'];
if (strpos($requestPath, $basePath) === 0) {
    $requestPath = substr($requestPath, strlen($basePath));
}
$requestPath = rtrim($requestPath, '/') ?: '/';

echo "Extracted path: {$requestPath}\n\n";

// Test the matching logic
$marker = '___PARAM_MARKER___';
$modPattern = preg_replace('/:[\w]+/', $marker, $pattern);
echo "After marker replacement: {$modPattern}\n";

$modPattern = preg_quote($modPattern, '#');
echo "After preg_quote: {$modPattern}\n";

$modPattern = str_replace(preg_quote($marker, '#'), '[0-9]+', $modPattern);
echo "After digit replacement: {$modPattern}\n";

$regexPattern = '#^' . $modPattern . '$#';
echo "Final regex: {$regexPattern}\n";

$match = preg_match($regexPattern, $requestPath);
echo "Match result: " . ($match ? 'YES' : 'NO') . "\n";

// Also test parameter extraction
echo "\nParameter Extraction:\n";
$patternSegments = array_filter(explode('/', $pattern));
$pathSegments = array_filter(explode('/', $requestPath));

$patternSegments = array_values($patternSegments);
$pathSegments = array_values($pathSegments);

echo "Pattern segments: " . json_encode($patternSegments) . "\n";
echo "Path segments: " . json_encode($pathSegments) . "\n";

foreach ($patternSegments as $i => $segment) {
    if (strpos($segment, ':') === 0) {
        $paramName = substr($segment, 1);
        echo "  Param '{$paramName}' = '{$pathSegments[$i]}'\n";
    }
}
