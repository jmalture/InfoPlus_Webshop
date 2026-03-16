<?php
/**
 * TEMPORARY TEST FILE - DELETE AFTER TESTING
 * 
 * To use: 
 * 1. Save this file
 * 2. Go to: yoursite.com/wp-content/plugins/infoplus-suite/src/Modules/UserOrders/test-orders.php
 * 3. Make sure you're logged in to WordPress
 */

// Load WordPress
require_once('../../../../../wp-load.php');

// Check if user is logged in
if (!is_user_logged_in()) {
    die('Please log in to WordPress first');
}

// Load WooCommerce if not loaded
if (!function_exists('wc_get_orders')) {
    die('WooCommerce is not active');
}

echo '<h1>Testing Orders API</h1>';
echo '<pre>';

$user_id = get_current_user_id();
echo "Current User ID: $user_id\n\n";

// Get orders using WooCommerce
$orders = wc_get_orders([
    'customer_id' => $user_id,
    'limit' => -1,
    'orderby' => 'date',
    'order' => 'DESC',
]);

echo "Total Orders Found: " . count($orders) . "\n\n";

if (empty($orders)) {
    echo "No orders found for this user.\n";
    echo "User ID: $user_id\n";
    echo "User Email: " . wp_get_current_user()->user_email . "\n";
} else {
    foreach ($orders as $order) {
        echo "Order #" . $order->get_order_number() . "\n";
        echo "Date: " . $order->get_date_created()->date('F j, Y') . "\n";
        echo "Status: " . $order->get_status() . "\n";
        echo "Total: " . $order->get_formatted_order_total() . "\n";
        echo "Items: " . count($order->get_items()) . "\n";
        echo "---\n";
    }
}

echo '</pre>';

echo '<hr>';
echo '<h2>Now Testing REST API</h2>';

// Create a REST request
$request = new WP_REST_Request('GET', '/infoplus/v1/orders');
$response = rest_do_request($request);

echo '<pre>';
echo "REST API Response:\n";
print_r($response->get_data());
echo '</pre>';
