<?php
/**
 * Simple debug script to test sync API
 * Place this file in the plugin root directory
 */

// Load WordPress
require_once('../../../wp-config.php');

echo "=== DEBUG SYNC API ===\n";

// Test if plugin is loaded
if (defined('GREETING_ADS_TABLE')) {
    echo "✅ Plugin loaded: " . GREETING_ADS_TABLE . "\n";
} else {
    echo "❌ Plugin not loaded\n";
}

// Test database connection
global $wpdb;
$table_name = $wpdb->prefix . GREETING_ADS_TABLE;

try {
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    echo "✅ Database connection OK: {$count} records\n";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

// Test REST API routes
$routes = rest_get_server()->get_routes();
if (isset($routes['/greeting-ads/v1/sync-data'])) {
    echo "✅ REST API route registered\n";
} else {
    echo "❌ REST API route not registered\n";
    echo "Available routes:\n";
    foreach ($routes as $route => $data) {
        if (strpos($route, 'greeting-ads') !== false) {
            echo "  - {$route}\n";
        }
    }
}

// Test with sample data
echo "\n=== TESTING API CALL ===\n";

$test_data = array(
    'api_key' => 'hutara000',
    'kata_kunci' => 'test keyword debug',
    'grup_iklan' => 'test group debug',
    'id_grup_iklan' => 'test_id_debug',
    'nomor_kata_kunci' => 'debug_' . time(), // unique
    'greeting' => 'debug'
);

// Simulate REST API call internally
$request = new WP_REST_Request('POST', '/greeting-ads/v1/sync-data');
foreach ($test_data as $key => $value) {
    $request->set_param($key, $value);
}

if (function_exists('greeting_ads_sync_data_callback')) {
    try {
        $response = greeting_ads_sync_data_callback($request);
        $data = $response->get_data();
        echo "✅ API Test Result: " . $data['message'] . "\n";
        echo "   Action: " . $data['action'] . "\n";
        echo "   Record ID: " . $data['record_id'] . "\n";
    } catch (Exception $e) {
        echo "❌ API Test Failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ API callback function not found\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
?>