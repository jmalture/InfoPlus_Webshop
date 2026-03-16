<!DOCTYPE html>
<html>
<head>
    <title>Testing Infoplus Orders API</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; }
        .test-section { background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .test-section h2 { margin-top: 0; color: #e91e8c; }
        pre { background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto; }
        .success { color: #22c55e; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        button { background: #e91e8c; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px; }
        button:hover { background: #c91872; }
        .order-card { background: white; padding: 15px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
        .loading { color: #999; font-style: italic; }
    </style>
</head>
<body>
    <?php
    // Load WordPress
    require_once('../../../../../../wp-load.php');

    // Check if user is logged in
    if (!is_user_logged_in()) {
        echo '<h1 class="error">❌ Please log in to WordPress first</h1>';
        echo '<p><a href="' . wp_login_url($_SERVER['REQUEST_URI']) . '">Login Here</a></p>';
        exit;
    }

    // Load WooCommerce if not loaded
    if (!function_exists('wc_get_orders')) {
        echo '<h1 class="error">❌ WooCommerce is not active</h1>';
        exit;
    }

    $user = wp_get_current_user();
    $user_id = get_current_user_id();
    ?>

    <h1>🧪 Testing Infoplus Orders API</h1>
    
    <div class="test-section">
        <h2>✅ WordPress Authentication</h2>
        <p><strong>Logged in as:</strong> <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)</p>
        <p><strong>User ID:</strong> <?php echo $user_id; ?></p>
        <p><strong>REST API Nonce:</strong> <code id="nonce-value"><?php echo wp_create_nonce('wp_rest'); ?></code></p>
    </div>

    <div class="test-section">
        <h2>📦 WooCommerce Orders (Direct)</h2>
        <?php
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (empty($orders)) {
            echo '<p class="error">❌ No orders found for this user.</p>';
            echo '<p><strong>Debug Info:</strong></p>';
            echo '<pre>';
            echo 'User ID: ' . $user_id . "\n";
            echo 'User Email: ' . $user->user_email . "\n";
            
            // Check if there are ANY orders in the system
            $all_orders = wc_get_orders(['limit' => 5]);
            echo 'Total orders in system: ' . count($all_orders) . "\n";
            
            if (!empty($all_orders)) {
                echo "\nRecent orders (any customer):\n";
                foreach ($all_orders as $order) {
                    echo '  Order #' . $order->get_order_number() . ' - Customer ID: ' . $order->get_customer_id() . ' - Email: ' . $order->get_billing_email() . "\n";
                }
            }
            echo '</pre>';
        } else {
            echo '<p class="success">✅ Found ' . count($orders) . ' order(s)</p>';
            echo '<div id="direct-orders">';
            foreach ($orders as $order) {
                ?>
                <div class="order-card">
                    <strong>Order #<?php echo $order->get_order_number(); ?></strong><br>
                    Date: <?php echo $order->get_date_created()->date('F j, Y'); ?><br>
                    Status: <?php echo $order->get_status(); ?><br>
                    Total: <?php echo $order->get_formatted_order_total(); ?><br>
                    Items: <?php echo count($order->get_items()); ?>
                </div>
                <?php
            }
            echo '</div>';
        }
        ?>
    </div>

    <div class="test-section">
        <h2>🔌 REST API Test</h2>
        <button onclick="testRestAPI()">Test Orders Endpoint</button>
        <div id="rest-api-result" style="margin-top: 15px;"></div>
    </div>

    <div class="test-section">
        <h2>🎨 Elementor Script Test</h2>
        <button onclick="testElementorScript()">Load Orders via Elementor Script</button>
        <div id="elementor-script-result" style="margin-top: 15px;"></div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // REST API configuration
        const restConfig = {
            url: '<?php echo rest_url('infoplus/v1/orders'); ?>',
            nonce: '<?php echo wp_create_nonce('wp_rest'); ?>'
        };

        console.log('REST Config:', restConfig);

        // Test REST API directly
        function testRestAPI() {
            const resultDiv = document.getElementById('rest-api-result');
            resultDiv.innerHTML = '<p class="loading">⏳ Testing REST API...</p>';
            
            console.log('Fetching from:', restConfig.url);
            console.log('Using nonce:', restConfig.nonce);
            
            fetch(restConfig.url, {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'X-WP-Nonce': restConfig.nonce,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                
                if (data.success) {
                    resultDiv.innerHTML = '<p class="success">✅ REST API Working!</p>' +
                        '<p>Found ' + data.total + ' order(s)</p>' +
                        '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                } else {
                    resultDiv.innerHTML = '<p class="error">❌ API returned error</p>' +
                        '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                resultDiv.innerHTML = '<p class="error">❌ Error: ' + error.message + '</p>';
            });
        }

        // Test Elementor script
        function testElementorScript() {
            const resultDiv = document.getElementById('elementor-script-result');
            
            // Check if script is loaded
            if (typeof infoplusUserOrders === 'undefined') {
                resultDiv.innerHTML = '<p class="error">❌ infoplusUserOrders not defined</p>' +
                    '<p>The script may not be loaded on this page. Try testing on a page with Elementor content.</p>';
                return;
            }
            
            resultDiv.innerHTML = '<p class="success">✅ Script loaded!</p>' +
                '<pre>' + JSON.stringify(infoplusUserOrders, null, 2) + '</pre>';
        }

        // Auto-run REST API test on load
        window.addEventListener('load', function() {
            console.log('Page loaded - running auto-test');
            setTimeout(testRestAPI, 500);
        });
    </script>
</body>
</html>
