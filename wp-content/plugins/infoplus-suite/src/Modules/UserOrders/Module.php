<?php
namespace Infoplus\Suite\Modules\UserOrders;

use Infoplus\Suite\Contracts\ModuleInterface;
use Infoplus\Suite\Plugin;

if (!defined('ABSPATH')) exit;

final class Module implements ModuleInterface
{
    private ?ElementorLoopIntegration $loopIntegration = null;

    public function slug(): string  { return 'user-orders'; }
    public function label(): string { return __('User Orders', 'infoplus-suite'); }

    public function register(): void
    {
        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontend']);
        
        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        
        // Register Elementor dynamic tags
        add_action('elementor/dynamic_tags/register', [$this, 'registerDynamicTags']);
        
        // Register dynamic tag group
        add_action('elementor/dynamic_tags/register', [$this, 'registerDynamicTagGroup'], 1);
        
        // Initialize Elementor Loop Grid integration
        add_action('elementor/init', [$this, 'initLoopIntegration']);
        
        // Register Elementor Widget
        add_action('elementor/widgets/register', [$this, 'registerWidgets']);
        
        // Add test shortcode for debugging
        add_shortcode('infoplus_test_orders', [$this, 'testOrdersShortcode']);
        
        // Enqueue styles for order items
        add_action('wp_enqueue_scripts', [$this, 'enqueueStyles']);
        
        // Register URL rewrite rules for order details page
        add_action('init', [$this, 'registerRewriteRules'], 10);
        add_filter('query_vars', [$this, 'registerQueryVars']);
        
        // Use template_include to load the correct page
        add_filter('template_include', [$this, 'handleOrderDetailsTemplate'], 99);
        
        // Add content to WooCommerce Thank You page (multiple hooks for compatibility)
        add_action('woocommerce_thankyou', [$this, 'renderThankYouContent'], 1);
        add_action('woocommerce_before_thankyou', [$this, 'renderThankYouContent'], 10);
        add_filter('woocommerce_thankyou_order_received_text', [$this, 'filterThankYouText'], 10, 2);
        
        // Shortcode for manual placement: [infoplus_order_received]
        add_shortcode('infoplus_order_received', [$this, 'orderReceivedShortcode']);
    }

    private function assetUrl(string $path): string
    {
        return INFOPLUS_SUITE_URL . 'src/Modules/UserOrders/Assets/' . ltrim($path, '/');
    }

    /**
     * Initialize the Elementor Loop Grid integration
     */
    public function initLoopIntegration(): void
    {
        require_once __DIR__ . '/ElementorLoopIntegration.php';
        $this->loopIntegration = new ElementorLoopIntegration();
        $this->loopIntegration->init();
    }

    /**
     * Register custom Elementor widgets
     */
    public function registerWidgets($widgets_manager): void
    {
        require_once __DIR__ . '/Widgets/UserOrdersGrid.php';
        require_once __DIR__ . '/Widgets/OrderDetails.php';
        
        $widgets_manager->register(new Widgets\UserOrdersGrid());
        $widgets_manager->register(new Widgets\OrderDetails());
    }

    /**
     * Enqueue frontend styles for order display
     */
    public function enqueueStyles(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $ver = Plugin::VERSION;

        wp_enqueue_style(
            'infoplus-user-orders',
            $this->assetUrl('user-orders.css'),
            [],
            $ver
        );
    }

    public function enqueueFrontend(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $ver = Plugin::VERSION;

        wp_enqueue_script(
            'infoplus-user-orders',
            $this->assetUrl('user-orders.js'),
            ['jquery', 'wp-api'],
            $ver,
            true
        );

        wp_localize_script('infoplus-user-orders', 'infoplusUserOrders', [
            'restUrl' => rest_url('infoplus/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentUserId' => get_current_user_id(),
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('infoplus/v1', '/orders', [
            'methods' => 'GET',
            'callback' => [$this, 'getUserOrders'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'args' => [
                'status' => [
                    'default' => 'any',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit' => [
                    'default' => -1,
                    'sanitize_callback' => 'absint',
                ],
                'search' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ]
        ]);

        register_rest_route('infoplus/v1', '/orders/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getSingleOrder'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
    }

    /**
     * Get current user's orders
     */
    public function getUserOrders(\WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        
        if (empty($user_id)) {
            return new \WP_Error('not_logged_in', 'No user logged in', ['status' => 401]);
        }
        
        $status = $request->get_param('status');
        $limit = $request->get_param('limit');
        $search = $request->get_param('search');

        $args = [
            'customer_id' => $user_id,
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if ($status && $status !== 'any') {
            $args['status'] = $status;
        }

        $orders = wc_get_orders($args);

        $formatted_orders = [];
        foreach ($orders as $order) {
            $order_data = $this->formatOrderData($order);
            
            if ($search) {
                $search_lower = strtolower($search);
                $searchable = strtolower(
                    $order_data['order_number'] . ' ' . 
                    $order_data['status'] . ' ' . 
                    implode(' ', array_column($order_data['items'], 'name'))
                );
                
                if (strpos($searchable, $search_lower) === false) {
                    continue;
                }
            }
            
            $formatted_orders[] = $order_data;
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $formatted_orders,
            'total' => count($formatted_orders),
        ], 200);
    }

    /**
     * Get single order details
     */
    public function getSingleOrder(\WP_REST_Request $request)
    {
        $order_id = $request->get_param('id');
        $user_id = get_current_user_id();
        
        $order = wc_get_order($order_id);

        if (!$order) {
            return new \WP_Error('order_not_found', 'Order not found', ['status' => 404]);
        }

        if ($order->get_customer_id() != $user_id) {
            return new \WP_Error('unauthorized', 'You do not have permission to view this order', ['status' => 403]);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $this->formatOrderData($order),
        ], 200);
    }

    /**
     * Format order data for API response
     */
    private function formatOrderData($order)
    {
        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = [
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $order->get_formatted_line_subtotal($item),
                'image' => $product ? wp_get_attachment_url($product->get_image_id()) : '',
                'product_id' => $item->get_product_id(),
            ];
        }

        return [
            'id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'date' => $order->get_date_created()->date('F j, Y'),
            'date_raw' => $order->get_date_created()->date('Y-m-d H:i:s'),
            'status' => $order->get_status(),
            'status_label' => wc_get_order_status_name($order->get_status()),
            'total' => $order->get_formatted_order_total(),
            'total_raw' => $order->get_total(),
            'items' => $items,
            'items_count' => count($items),
            'payment_method' => $order->get_payment_method_title(),
            'view_url' => $order->get_view_order_url(),
        ];
    }

    /**
     * Register dynamic tag group
     */
    public function registerDynamicTagGroup($dynamic_tags_manager): void
    {
        $dynamic_tags_manager->register_group('infoplus-orders', [
            'title' => __('Infoplus Orders', 'infoplus-suite'),
        ]);
    }

    /**
     * Register custom Elementor dynamic tags for orders
     */
    public function registerDynamicTags($dynamic_tags_manager): void
    {
        if (!did_action('elementor/loaded')) {
            return;
        }

        require_once __DIR__ . '/DynamicTags/OrderTagBase.php';
        require_once __DIR__ . '/DynamicTags/OrderNumber.php';
        require_once __DIR__ . '/DynamicTags/OrderDate.php';
        require_once __DIR__ . '/DynamicTags/OrderStatus.php';
        require_once __DIR__ . '/DynamicTags/OrderTotal.php';
        require_once __DIR__ . '/DynamicTags/OrderItemsList.php';
        require_once __DIR__ . '/DynamicTags/OrderViewUrl.php';
        require_once __DIR__ . '/DynamicTags/OrderItemsCount.php';
        require_once __DIR__ . '/DynamicTags/OrderPaymentMethod.php';
        
        $dynamic_tags_manager->register(new DynamicTags\OrderNumber());
        $dynamic_tags_manager->register(new DynamicTags\OrderDate());
        $dynamic_tags_manager->register(new DynamicTags\OrderStatus());
        $dynamic_tags_manager->register(new DynamicTags\OrderTotal());
        $dynamic_tags_manager->register(new DynamicTags\OrderItemsList());
        $dynamic_tags_manager->register(new DynamicTags\OrderViewUrl());
        $dynamic_tags_manager->register(new DynamicTags\OrderItemsCount());
        $dynamic_tags_manager->register(new DynamicTags\OrderPaymentMethod());
    }

    /**
     * Test shortcode to debug orders display
     */
    public function testOrdersShortcode($atts): string
    {
        if (!is_user_logged_in()) {
            return '<div style="padding:20px;background:#fee;border:1px solid #fcc;color:#c00;border-radius:4px;">Please log in to view orders.</div>';
        }

        ob_start();
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $orders = wc_get_orders(['customer_id' => $user_id, 'limit' => -1, 'orderby' => 'date', 'order' => 'DESC']);
        
        ?>
        <div style="font-family:Arial,sans-serif;max-width:1000px;margin:20px auto;">
            <h2 style="color:#e91e8c;">Orders API Test</h2>
            <div style="background:#f5f5f5;padding:20px;border-radius:8px;margin:20px 0;">
                <h3>User Info</h3>
                <p><strong>Name:</strong> <?php echo esc_html($user->display_name); ?></p>
                <p><strong>Email:</strong> <?php echo esc_html($user->user_email); ?></p>
                <p><strong>User ID:</strong> <?php echo $user_id; ?></p>
            </div>
            <div style="background:#f5f5f5;padding:20px;border-radius:8px;margin:20px 0;">
                <h3>WooCommerce Orders (Direct)</h3>
                <?php if (empty($orders)): ?>
                    <p style="color:#c00;">No orders found for this user.</p>
                <?php else: ?>
                    <p style="color:#22c55e;">Found <?php echo count($orders); ?> order(s)</p>
                    <?php foreach ($orders as $order): ?>
                        <div style="background:white;padding:15px;margin:10px 0;border:1px solid #ddd;border-radius:4px;">
                            <strong>Order #<?php echo $order->get_order_number(); ?></strong><br>
                            Date: <?php echo $order->get_date_created()->date('F j, Y'); ?><br>
                            Status: <?php echo $order->get_status(); ?><br>
                            Total: <?php echo $order->get_formatted_order_total(); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Register URL rewrite rules for order details
     */
    public function registerRewriteRules(): void
    {
        // Simple rewrite: /order/12345/ sets our custom query var
        add_rewrite_rule(
            '^order/([0-9]+)/?$',
            'index.php?infoplus_order_id=$matches[1]',
            'top'
        );
        
        // Check if we need to flush rewrite rules
        if (get_option('infoplus_flush_rewrite_rules')) {
            flush_rewrite_rules();
            delete_option('infoplus_flush_rewrite_rules');
        }
    }

    /**
     * Register custom query vars
     */
    public function registerQueryVars(array $vars): array
    {
        $vars[] = 'infoplus_order_id';
        return $vars;
    }

    /**
     * Handle order details template - load the "order" page when query var is set
     */
    public function handleOrderDetailsTemplate(string $template): string
    {
        $order_id = get_query_var('infoplus_order_id');
        
        // Not an order details request
        if (empty($order_id)) {
            return $template;
        }

        // Security: User must be logged in
        if (!is_user_logged_in()) {
            return $this->renderSecurityPage('login');
        }

        // Check if order exists
        $order = wc_get_order($order_id);
        if (!$order) {
            return $this->renderSecurityPage('not_found');
        }

        // Check ownership
        $current_user_id = get_current_user_id();
        if ($order->get_customer_id() != $current_user_id) {
            return $this->renderSecurityPage('denied');
        }

        // Find the "order" page
        $order_page = get_page_by_path('order');
        
        if (!$order_page) {
            // Fallback: try to find by title
            $pages = get_posts([
                'post_type' => 'page',
                'post_status' => 'publish',
                'name' => 'order',
                'posts_per_page' => 1
            ]);
            $order_page = !empty($pages) ? $pages[0] : null;
        }
        
        if ($order_page) {
            // Set up the global post data to use the "order" page
            global $wp_query, $post;
            
            $post = $order_page;
            setup_postdata($post);
            
            $wp_query->is_page = true;
            $wp_query->is_singular = true;
            $wp_query->is_home = false;
            $wp_query->is_archive = false;
            $wp_query->is_category = false;
            $wp_query->is_404 = false;
            $wp_query->queried_object = $order_page;
            $wp_query->queried_object_id = $order_page->ID;
            $wp_query->post = $order_page;
            $wp_query->posts = [$order_page];
            $wp_query->post_count = 1;
            $wp_query->found_posts = 1;
            
            // Return the page template
            $page_template = get_page_template_slug($order_page);
            if ($page_template && locate_template($page_template)) {
                return locate_template($page_template);
            }
            
            // Try Elementor canvas template
            if (class_exists('\Elementor\Plugin')) {
                $elementor_template = get_post_meta($order_page->ID, '_wp_page_template', true);
                if ($elementor_template === 'elementor_canvas') {
                    return ELEMENTOR_PATH . 'modules/page-templates/templates/canvas.php';
                }
                if ($elementor_template === 'elementor_header_footer') {
                    return ELEMENTOR_PATH . 'modules/page-templates/templates/header-footer.php';
                }
            }
            
            return get_page_template();
        }

        // No order page found - show error
        return $this->renderSecurityPage('no_page');
    }

    /**
     * Render a security/error page and exit
     */
    private function renderSecurityPage(string $type): string
    {
        // Use a callback to render our content
        add_filter('the_content', function() use ($type) {
            return $this->getSecurityMessageHtml($type);
        });
        
        // Return a simple page template
        return get_page_template();
    }

    /**
     * Get security message HTML
     */
    private function getSecurityMessageHtml(string $type): string
    {
        $html = '<div style="min-height: 40vh; display: flex; align-items: center; justify-content: center; padding: 40px 20px;">';
        
        switch ($type) {
            case 'login':
                $login_url = wp_login_url(home_url($_SERVER['REQUEST_URI']));
                $html .= '
                <div style="max-width: 500px; text-align: center; padding: 40px; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#e91e8c" stroke-width="2" style="margin-bottom: 20px;">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <h2 style="color: #333; margin-bottom: 16px; font-size: 24px;">Anmeldung erforderlich</h2>
                    <p style="color: #666; margin-bottom: 24px;">Bitte melden Sie sich an, um Ihre Bestelldetails zu sehen.</p>
                    <a href="' . esc_url($login_url) . '" style="display: inline-block; background: #e91e8c; color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 500;">Jetzt anmelden</a>
                </div>';
                break;
                
            case 'not_found':
                $html .= '
                <div style="max-width: 500px; text-align: center; padding: 40px; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" style="margin-bottom: 20px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <h2 style="color: #333; margin-bottom: 16px; font-size: 24px;">Bestellung nicht gefunden</h2>
                    <p style="color: #666; margin-bottom: 24px;">Die angeforderte Bestellung konnte nicht gefunden werden.</p>
                    <a href="' . esc_url(home_url('/dashboard/')) . '" style="display: inline-block; background: #e91e8c; color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 500;">Zurück zum Dashboard</a>
                </div>';
                break;
                
            case 'denied':
                $html .= '
                <div style="max-width: 500px; text-align: center; padding: 40px; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" style="margin-bottom: 20px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                    </svg>
                    <h2 style="color: #333; margin-bottom: 16px; font-size: 24px;">Zugriff verweigert</h2>
                    <p style="color: #666; margin-bottom: 24px;">Sie haben keine Berechtigung, diese Bestellung anzuzeigen.</p>
                    <a href="' . esc_url(home_url('/dashboard/')) . '" style="display: inline-block; background: #e91e8c; color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 500;">Zurück zum Dashboard</a>
                </div>';
                break;
                
            case 'no_page':
                $html .= '
                <div style="max-width: 500px; text-align: center; padding: 40px; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" style="margin-bottom: 20px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <h2 style="color: #333; margin-bottom: 16px; font-size: 24px;">Konfigurationsfehler</h2>
                    <p style="color: #666; margin-bottom: 24px;">Die Seite "order" wurde nicht gefunden. Bitte erstellen Sie eine Seite mit dem Slug "order".</p>
                    <a href="' . esc_url(home_url('/dashboard/')) . '" style="display: inline-block; background: #e91e8c; color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 500;">Zurück zum Dashboard</a>
                </div>';
                break;
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * Activation hook - set flag to flush rewrite rules
     */
    public static function activate(): void
    {
        update_option('infoplus_flush_rewrite_rules', true);
    }

    /**
     * Track if thank you content was already rendered
     */
    private static bool $thankYouRendered = false;

    /**
     * Filter the thank you text to include our content
     */
    public function filterThankYouText($text, $order): string
    {
        if (self::$thankYouRendered) {
            return ''; // Already rendered via action hook
        }
        
        if (!$order) {
            return $text;
        }
        
        ob_start();
        $this->renderThankYouContent($order->get_id());
        $content = ob_get_clean();
        
        return $content;
    }

    /**
     * Shortcode for manual placement on thank you page
     * Usage: [infoplus_order_received]
     */
    public function orderReceivedShortcode($atts): string
    {
        // Get order ID from URL
        $order_id = absint(get_query_var('order-received'));
        
        if (!$order_id) {
            // Try from URL path
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (preg_match('/order-received\/(\d+)/i', $uri, $matches)) {
                $order_id = (int) $matches[1];
            }
        }
        
        if (!$order_id) {
            return '';
        }
        
        // Verify order key for security
        $order_key = isset($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : '';
        $order = wc_get_order($order_id);
        
        if (!$order || !$order->key_is_valid($order_key)) {
            return '<p>Ung&uuml;ltige Bestellung.</p>';
        }
        
        ob_start();
        self::$thankYouRendered = false; // Reset for shortcode
        $this->renderThankYouContent($order_id);
        return ob_get_clean();
    }

    /**
     * Render enhanced Thank You page content
     */
    public function renderThankYouContent($order_id): void
    {
        // Prevent duplicate rendering
        if (self::$thankYouRendered) {
            return;
        }
        
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Mark as rendered
        self::$thankYouRendered = true;

        // Status labels in German
        $status_labels = [
            'pending' => 'Ausstehend',
            'processing' => 'In Bearbeitung',
            'on-hold' => 'In Wartestellung',
            'completed' => 'Abgeschlossen',
            'cancelled' => 'Storniert',
            'refunded' => 'Erstattet',
            'failed' => 'Fehlgeschlagen',
        ];

        // Payment method labels in German
        $payment_labels = [
            'bacs' => 'Überweisung',
            'cod' => 'Nachnahme',
            'cheque' => 'Scheck',
            'paypal' => 'PayPal',
            'stripe' => 'Kreditkarte',
        ];

        $status = $order->get_status();
        $status_label = $status_labels[$status] ?? ucfirst($status);
        $payment_method = $order->get_payment_method();
        $payment_label = $payment_labels[$payment_method] ?? $order->get_payment_method_title();
        
        // Get billing details
        $billing_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $billing_email = $order->get_billing_email();
        $billing_phone = $order->get_billing_phone();
        
        // Get shipping address
        $shipping_address = $order->get_formatted_shipping_address();
        if (empty($shipping_address)) {
            $shipping_address = $order->get_formatted_billing_address();
        }
        
        ?>
        <style>
        
        
            body.page-id-28.woocommerce-order-received .entry-title{
                display: none;
                
            }

            .infoplus-thankyou-container {
                max-width: 900px;
                margin: 0 auto 40px;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            
            .infoplus-thankyou-header {
                text-align: center;
                padding: 40px 20px;
                background: var(--e-global-color-primary);
                border-radius: 16px;
                margin-bottom: 30px;
                color: white;
            }
            
            .infoplus-thankyou-icon {
                width: 80px;
                height: 80px;
                background: rgba(255,255,255,0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
            }
            
            .infoplus-thankyou-icon svg {
                width: 40px;
                height: 40px;
                stroke: white;
                stroke-width: 3;
                fill: none;
            }
            
            .infoplus-thankyou-title {
                font-size: 28px;
                font-weight: 700;
                margin: 0 0 10px;
                color: white;
            }
            
            .infoplus-thankyou-subtitle {
                font-size: 16px;
                opacity: 0.9;
                margin: 0;
            }
            
            .infoplus-thankyou-order-number {
                display: inline-block;
                background: rgba(255,255,255,0.2);
                padding: 8px 20px;
                border-radius: 30px;
                font-weight: 600;
                margin-top: 15px;
            }
            
            .infoplus-thankyou-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 24px;
                margin-bottom: 30px;
            }
            
            @media (max-width: 768px) {
                .infoplus-thankyou-grid {
                    grid-template-columns: 1fr;
                }
            }
            
            .infoplus-thankyou-card {
                background: white;
                border-radius: 12px;
                padding: 24px;
                box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            }
            
            .infoplus-thankyou-card-title {
                font-size: 14px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #666;
                margin: 0 0 16px;
                padding-bottom: 12px;
                border-bottom: 2px solid #f0f0f0;
            }
            
            .infoplus-thankyou-info-row {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid #f5f5f5;
            }
            
            .infoplus-thankyou-info-row:last-child {
                border-bottom: none;
            }
            
            .infoplus-thankyou-info-label {
                color: #666;
                font-size: 14px;
            }
            
            .infoplus-thankyou-info-value {
                font-weight: 500;
                color: #333;
                font-size: 14px;
                text-align: right;
            }
            
            .infoplus-thankyou-status {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 13px;
                font-weight: 500;
                background: #fef3c7;
                color: #d97706;
            }
            
            .infoplus-thankyou-status.completed {
                background: #d1fae5;
                color: #059669;
            }
            
            .infoplus-thankyou-status.processing {
                background: #dbeafe;
                color: #2563eb;
            }
            
            .infoplus-thankyou-address {
                font-size: 14px;
                line-height: 1.7;
                color: #444;
            }
            
            .infoplus-thankyou-items {
                background: white;
                border-radius: 12px;
                padding: 24px;
                box-shadow: 0 2px 12px rgba(0,0,0,0.06);
                margin-bottom: 30px;
            }
            
            .infoplus-thankyou-item {
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 16px 0;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .infoplus-thankyou-item:last-child {
                border-bottom: none;
            }
            
            .infoplus-thankyou-item-image {
                width: 70px;
                height: 70px;
                object-fit: cover;
                border-radius: 8px;
                background: #f5f5f5;
            }
            
            .infoplus-thankyou-item-details {
                flex: 1;
            }
            
            .infoplus-thankyou-item-name {
                font-weight: 500;
                color: #333;
                margin-bottom: 4px;
                font-size: 15px;
            }
            
            .infoplus-thankyou-item-meta {
                font-size: 13px;
                color: #666;
            }
            
            .infoplus-thankyou-item-price {
                font-weight: 600;
                color: #e91e8c;
                font-size: 16px;
            }
            
            .infoplus-thankyou-totals {
                background: #f8f8f8;
                border-radius: 8px;
                padding: 16px 20px;
                margin-top: 20px;
            }
            
            .infoplus-thankyou-total-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                font-size: 14px;
            }
            
            .infoplus-thankyou-total-row.grand-total {
                border-top: 2px solid #e0e0e0;
                margin-top: 8px;
                padding-top: 16px;
                font-size: 18px;
                font-weight: 700;
            }
            
            .infoplus-thankyou-total-row.grand-total .infoplus-thankyou-total-value {
                color: #e91e8c;
            }
            
            .infoplus-thankyou-actions {
                display: flex;
                gap: 16px;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .infoplus-thankyou-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 14px 28px;
                border-radius: 8px;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.2s;
                font-size: 15px;
            }
            
            .infoplus-thankyou-btn-primary {
                background: #e91e8c;
                color: white;
            }
            
            .infoplus-thankyou-btn-primary:hover {
                background: #d11a7d;
                transform: translateY(-2px);
                color: white;
            }
            
            .infoplus-thankyou-btn-secondary {
                background: white;
                color: #e91e8c;
                border: 2px solid #e91e8c;
            }
            
            .infoplus-thankyou-btn-secondary:hover {
                background: #fdf2f8;
                color: #e91e8c;
            }
            
            .infoplus-thankyou-btn svg {
                width: 18px;
                height: 18px;
            }
            
            /* Hide default WooCommerce order details */
            .woocommerce-order-details,
            .woocommerce-customer-details {
                display: none;
            }
        </style>
        
        <div class="infoplus-thankyou-container">
            <!-- Success Header -->
            <div class="infoplus-thankyou-header">
                <div class="infoplus-thankyou-icon">
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                <h2 class="infoplus-thankyou-title">Vielen Dank für Ihre Bestellung!</h2>
                <p class="infoplus-thankyou-subtitle">Wir haben Ihre Bestellung erhalten und bearbeiten sie so schnell wie möglich.</p>
                <span class="infoplus-thankyou-order-number">Bestellung #<?php echo esc_html($order->get_order_number()); ?></span>
            </div>
            
            <!-- Order Info Grid -->
            <div class="infoplus-thankyou-grid">
                <!-- Order Details -->
                <div class="infoplus-thankyou-card">
                    <h3 class="infoplus-thankyou-card-title">Bestelldetails</h3>
                    <div class="infoplus-thankyou-info-row">
                        <span class="infoplus-thankyou-info-label">Bestellnummer</span>
                        <span class="infoplus-thankyou-info-value">#<?php echo esc_html($order->get_order_number()); ?></span>
                    </div>
                    <div class="infoplus-thankyou-info-row">
                        <span class="infoplus-thankyou-info-label">Datum</span>
                        <span class="infoplus-thankyou-info-value"><?php echo esc_html($order->get_date_created()->date_i18n('j. F Y, H:i')); ?> Uhr</span>
                    </div>
                    <div class="infoplus-thankyou-info-row">
                        <span class="infoplus-thankyou-info-label">Status</span>
                        <span class="infoplus-thankyou-info-value">
                            <span class="infoplus-thankyou-status <?php echo esc_attr($status); ?>"><?php echo esc_html($status_label); ?></span>
                        </span>
                    </div>
                    <div class="infoplus-thankyou-info-row">
                        <span class="infoplus-thankyou-info-label">Zahlungsmethode</span>
                        <span class="infoplus-thankyou-info-value"><?php echo esc_html($payment_label); ?></span>
                    </div>
                    <div class="infoplus-thankyou-info-row">
                        <span class="infoplus-thankyou-info-label">E-Mail</span>
                        <span class="infoplus-thankyou-info-value"><?php echo esc_html($billing_email); ?></span>
                    </div>
                </div>
                
                <!-- Shipping Address -->
                <div class="infoplus-thankyou-card">
                    <h3 class="infoplus-thankyou-card-title">Lieferadresse</h3>
                    <div class="infoplus-thankyou-address">
                        <?php echo wp_kses_post($shipping_address); ?>
                    </div>
                    <?php if ($billing_phone): ?>
                    <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #f0f0f0;">
                        <span class="infoplus-thankyou-info-label">Telefon:</span>
                        <span style="margin-left: 8px; font-weight: 500;"><?php echo esc_html($billing_phone); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Order Items -->
            <div class="infoplus-thankyou-items">
                <h3 class="infoplus-thankyou-card-title">Bestellte Artikel</h3>
                
                <?php foreach ($order->get_items() as $item): 
                    $product = $item->get_product();
                    $image_url = '';
                    if ($product) {
                        $image_id = $product->get_image_id();
                        if ($image_id) {
                            $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                        }
                    }
                ?>
                <div class="infoplus-thankyou-item">
                    <?php if ($image_url): ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="" class="infoplus-thankyou-item-image">
                    <?php else: ?>
                    <div class="infoplus-thankyou-item-image" style="display:flex;align-items:center;justify-content:center;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                    </div>
                    <?php endif; ?>
                    <div class="infoplus-thankyou-item-details">
                        <div class="infoplus-thankyou-item-name"><?php echo esc_html($item->get_name()); ?></div>
                        <div class="infoplus-thankyou-item-meta">Menge: <?php echo esc_html($item->get_quantity()); ?></div>
                    </div>
                    <div class="infoplus-thankyou-item-price"><?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?></div>
                </div>
                <?php endforeach; ?>
                
                <!-- Totals -->
                <div class="infoplus-thankyou-totals">
                    <div class="infoplus-thankyou-total-row">
                        <span>Zwischensumme</span>
                        <span><?php echo wp_kses_post(wc_price($order->get_subtotal())); ?></span>
                    </div>
                    <?php if ($order->get_shipping_total() > 0): ?>
                    <div class="infoplus-thankyou-total-row">
                        <span>Versand</span>
                        <span><?php echo wp_kses_post(wc_price($order->get_shipping_total())); ?></span>
                    </div>
                    <?php else: ?>
                    <div class="infoplus-thankyou-total-row">
                        <span>Versand</span>
                        <span>Kostenlos</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($order->get_total_tax() > 0): ?>
                    <div class="infoplus-thankyou-total-row">
                        <span>MwSt.</span>
                        <span><?php echo wp_kses_post(wc_price($order->get_total_tax())); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="infoplus-thankyou-total-row grand-total">
                        <span>Gesamtsumme</span>
                        <span class="infoplus-thankyou-total-value"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="infoplus-thankyou-actions">
                <a href="<?php echo esc_url(home_url('/account/')); ?>" class="infoplus-thankyou-btn infoplus-thankyou-btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    Zum Dashboard
                </a>
                <a href="<?php echo esc_url(home_url('')); ?>" class="infoplus-thankyou-btn infoplus-thankyou-btn-secondary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    Weiter einkaufen
                </a>
            </div>
        </div>
        <?php
    }
}
