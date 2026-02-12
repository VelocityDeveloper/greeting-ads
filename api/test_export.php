<?php

/**
 * Test script for export endpoint
 */

// Include WordPress (adjust path as needed)
// For testing, we'll skip WordPress inclusion and use direct API calls
// require_once('../../wp-config.php');

// API configuration
$base_url = 'https://velocitydeveloper.com/wp-json/greeting/v1';
$token = 'c2e1a7f62f8147e48a1c3f960bdcb176'; // Replace with your actual token

/**
 * Test export endpoint
 */
function test_export($type, $format = 'json') {
    global $base_url, $token;

    $url = $base_url . '/export?type=' . $type . '&format=' . $format;

    echo "Testing: $url\n";
    echo "Method: GET\n";
    echo "Authorization: Bearer $token\n\n";

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    echo "HTTP Status: $http_code\n";

    if ($error) {
        echo "CURL Error: $error\n";
        return false;
    }

    // Handle different formats
    if ($format === 'csv') {
        // Save CSV to file
        $filename = 'export_test_' . $type . '_' . date('Y-m-d_H-i-s') . '.csv';
        file_put_contents($filename, $response);
        echo "CSV saved to: $filename\n";
        echo "File size: " . strlen($response) . " bytes\n";

        // Show first few lines
        $lines = explode("\n", $response);
        echo "\nFirst 10 lines:\n";
        for ($i = 0; $i < min(10, count($lines)); $i++) {
            echo ($i + 1) . ": " . $lines[$i] . "\n";
        }
    } else {
        // Display JSON response
        $data = json_decode($response, true);
        if ($data === null) {
            echo "Invalid JSON response:\n";
            echo substr($response, 0, 500) . "...\n";
        } else {
            echo "Response:\n";
            echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
        }
    }

    echo "\n" . str_repeat("-", 80) . "\n\n";
    return true;
}

/**
 * Test all export scenarios
 */
echo "=== Export Endpoint Test Suite ===\n\n";
echo "Base URL: $base_url\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Test 1: Export today's data as JSON
echo "Test 1: Export Today (JSON)\n";
test_export('today', 'json');

// Test 2: Export today's data as CSV
echo "Test 2: Export Today (CSV)\n";
test_export('today', 'csv');

// Test 3: Export all data as JSON
echo "Test 3: Export Full (JSON)\n";
test_export('full', 'json');

// Test 4: Export all data as CSV
echo "Test 4: Export Full (CSV)\n";
test_export('full', 'csv');

// Test 5: Invalid type (should return error)
echo "Test 5: Invalid Type (should error)\n";
test_export('invalid', 'json');

// Test 6: Invalid token (should return error)
echo "Test 6: Invalid Token (should error)\n";
$invalid_token = 'invalid_token_123';
$ch = curl_init();
$url = $base_url . '/export?type=today';

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $invalid_token,
        'Content-Type: application/json'
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $http_code\n";
echo "Response: " . substr($response, 0, 200) . "...\n";
echo "\n" . str_repeat("-", 80) . "\n\n";

echo "=== Test Complete ===\n";
echo "Check for CSV files in the current directory.\n";

?>