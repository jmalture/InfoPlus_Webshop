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

        // Beautiful empty cart:
        //  - Classic WC cart (shortcode-based): woocommerce_cart_is_empty action
        //  - Block-based WC cart (Gutenberg): render_block filter on woocommerce/cart
        //  - Manual placement shortcode: [infoplus_empty_cart]
        add_action('woocommerce_cart_is_empty', [$this, 'renderEmptyCartContent'], 1);
        add_filter('render_block', [$this, 'filterWooCartBlock'], 10, 2);
        add_filter('render_block', [$this, 'filterWooCheckoutBlock'], 10, 2);
        add_action('wp_head', [$this, 'enqueueCartStyles']);
        add_action('wp_head', [$this, 'enqueueCheckoutStyles']);
        add_shortcode('infoplus_empty_cart', [$this, 'emptyCartShortcode']);

        // Brand customer-facing transactional order emails with plugin colours.
        // Covers both the "Bestellung in Bearbeitung" (processing) and the
        // "Bestellung abgeschlossen" (completed) emails.
        add_filter('woocommerce_email_styles',            [$this, 'brandOrderEmailStyles'],    10, 2);
        add_action('woocommerce_email_before_order_table', [$this, 'renderOrderEmailBanner'],  10, 4);
        add_action('woocommerce_email_header',             [$this, 'fixEmailHeaderInlineStyles'], 10, 2);
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
        $ver = Plugin::VERSION;

        // Order history/dashboard styles — logged-in only
        if (is_user_logged_in()) {
            wp_enqueue_style(
                'infoplus-user-orders',
                $this->assetUrl('user-orders.css'),
                [],
                $ver
            );
        }

        // Cart, checkout and thank-you page styles — available to all users (including guests)
        if (is_cart() || is_checkout() || is_wc_endpoint_url('order-received')) {
            wp_enqueue_style(
                'infoplus-woo-pages',
                $this->assetUrl('woo-pages.css'),
                [],
                $ver
            );
        }

        // Cart & checkout quantity badge — shows "× qty" when native qty selector is missing
        if (is_cart() || is_checkout()) {
            wp_enqueue_script(
                'infoplus-cart-qty-fix',
                $this->assetUrl('cart-qty-fix.js'),
                [],
                $ver,
                true
            );
        }
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

        $logged_in  = is_user_logged_in();
        $login_url  = home_url('/log-in/');
        $register_url = home_url('/log-in/?eael-register=1');

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
            body.woocommerce-order-received .entry-title { display: none; }

            .infoplus-thankyou-container {
                max-width: 900px;
                margin: 0 auto 40px;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }

            .infoplus-thankyou-header {
                text-align: center;
                padding: 40px 20px;
                background: var(--e-global-color-primary, #e91e8c);
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
                .infoplus-thankyou-grid { grid-template-columns: 1fr; }
            }

            .infoplus-thankyou-card {
                background: white;
                border-radius: 12px;
                padding: 28px;
            }

            .infoplus-thankyou-card-title {
                font-size: 13px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #999;
                margin: 0 0 24px;
            }

            .infoplus-thankyou-info-row {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
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

            .infoplus-thankyou-status.completed  { background: #d1fae5; color: #059669; }
            .infoplus-thankyou-status.processing  { background: #dbeafe; color: #2563eb; }

            .infoplus-thankyou-address {
                font-size: 14px;
                line-height: 1.7;
                color: #444;
            }

            .infoplus-thankyou-items {
                background: white;
                border-radius: 12px;
                padding: 28px;
                margin-bottom: 30px;
            }

            .infoplus-thankyou-item {
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 14px 0;
            }

            .infoplus-thankyou-item + .infoplus-thankyou-item {
                margin-top: 4px;
            }

            .infoplus-thankyou-item-image {
                width: 70px;
                height: 70px;
                object-fit: cover;
                border-radius: 8px;
                background: transparent;
                flex-shrink: 0;
            }

            .infoplus-thankyou-item-details { flex: 1; }

            .infoplus-thankyou-item-name {
                font-weight: 500;
                color: #333;
                margin-bottom: 4px;
                font-size: 15px;
            }

            .infoplus-thankyou-item-meta { font-size: 13px; color: #666; }

            .infoplus-thankyou-item-price {
                font-weight: 600;
                color: #e91e8c;
                font-size: 16px;
            }

            .infoplus-thankyou-totals {
                background: #f5f5f5;
                border-radius: 8px;
                padding: 20px 24px;
                margin-top: 24px;
            }

            .infoplus-thankyou-total-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                font-size: 14px;
            }

            .infoplus-thankyou-total-row.grand-total {
                margin-top: 16px;
                padding-top: 8px;
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
                transition: background 0.2s, transform 0.15s;
                font-size: 15px;
            }

            .infoplus-thankyou-btn-primary { background: #e91e8c; color: white; }

            .infoplus-thankyou-btn-primary:hover {
                background: #d11a7d;
                transform: translateY(-1px);
                color: white;
            }

            .infoplus-thankyou-btn-secondary {
                background: #fdf2f8;
                color: #e91e8c;
            }

            .infoplus-thankyou-btn-secondary:hover {
                background: #fce4f3;
                color: #e91e8c;
            }

            .infoplus-thankyou-btn svg { width: 18px; height: 18px; }

            /* Hide default WooCommerce order details — we render our own */
            .woocommerce-order-details,
            .woocommerce-customer-details { display: none; }
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
                    <div style="margin-top: 20px;">
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
                            $image_url = wp_get_attachment_image_url($image_id, 'medium');
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
            
            <!-- Action Buttons — login-aware -->
            <div class="infoplus-thankyou-actions">
                <?php if ($logged_in): ?>
                    <!-- Logged-in: Dashboard (primary) + Weiter einkaufen (secondary) -->
                    <a href="<?php echo esc_url(home_url('/account/')); ?>" class="infoplus-thankyou-btn infoplus-thankyou-btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                        Zum Dashboard
                    </a>
                    <a href="<?php echo esc_url(home_url('/namensschilder/')); ?>" class="infoplus-thankyou-btn infoplus-thankyou-btn-secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        Weiter einkaufen
                    </a>
                <?php else: ?>
                    <!-- Guest: Weiter einkaufen (primary) + Konto erstellen (secondary) -->
                    <a href="<?php echo esc_url(home_url('/namensschilder/')); ?>" class="infoplus-thankyou-btn infoplus-thankyou-btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        Weiter einkaufen
                    </a>
                    <a href="<?php echo esc_url($register_url); ?>" class="infoplus-thankyou-btn infoplus-thankyou-btn-secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                        Konto erstellen
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Empty Cart
    // -------------------------------------------------------------------------

    /**
     * Inject brand-consistent styles into the WooCommerce Blocks cart page.
     * Fires on wp_head, but only outputs anything on the cart page.
     * Overrides the default dark WordPress button styles with the brand pink.
     */
    public function enqueueCartStyles(): void
    {
        if (!function_exists('is_cart') || !is_cart()) {
            return;
        }
        ?>
        <style id="infoplus-cart-styles">
        /* ── Page heading ────────────────────────────────────────────────── */
        .infoplus-page-heading {
            width: 100%;
            margin-bottom: 28px;
        }
        .infoplus-page-heading__title {
            font-size: clamp(1.75rem, 3vw, 2.5rem);
            font-weight: 700;
            color: #e91e8c;
            text-align: left;
            margin: 0;
            line-height: 1.2;
        }

        /* ── Viewport-fill: the main content grows to fill header↔footer gap */
        .site-main, main.site-main, #main, main#main, main {
            min-height: calc(100vh - 684px); /* fallback (83px header + 601px footer) */
        }

        /* ── Buttons (overrides WP default #32373c) ─────────────────────── */
        .wp-block-woocommerce-cart .wp-element-button,
        .wp-block-woocommerce-cart .wp-block-button__link,
        .wp-block-woocommerce-cart .wc-block-cart__submit-button,
        .wp-block-woocommerce-cart .wc-block-components-button:not(.is-link) {
            background-color: #e91e8c !important;
            color: #fff !important;
            border-radius: 8px !important;
            border: none !important;
            font-weight: 600 !important;
            padding: 14px 28px !important;
            transition: background-color 0.2s !important;
        }
        .wp-block-woocommerce-cart .wp-element-button:hover,
        .wp-block-woocommerce-cart .wc-block-cart__submit-button:hover,
        .wp-block-woocommerce-cart .wc-block-components-button:not(.is-link):hover {
            background-color: #d91a7e !important;
            color: #fff !important;
        }

        /* ── Links & coupon toggle ───────────────────────────────────────── */
        .wp-block-woocommerce-cart a,
        .wp-block-woocommerce-cart .wc-block-components-totals-coupon__button,
        .wp-block-woocommerce-cart .wc-block-components-panel__button {
            color: #e91e8c !important;
        }
        .wp-block-woocommerce-cart a:hover {
            color: #d91a7e !important;
        }

        /* ── Product name ────────────────────────────────────────────────── */
        .wp-block-woocommerce-cart .wc-block-cart-item__product-name,
        .wp-block-woocommerce-cart .wc-block-cart-item__product-name a {
            color: #e91e8c !important;
            font-weight: 600;
        }

        /* ── Grand total value ───────────────────────────────────────────── */
        .wp-block-woocommerce-cart .wc-block-components-totals-footer-item
            .wc-block-components-totals-item__value {
            color: #e91e8c !important;
            font-weight: 700 !important;
        }

        /* ── Remove item link ────────────────────────────────────────────── */
        .wp-block-woocommerce-cart .wc-block-cart-item__remove-link {
            color: #e91e8c !important;
        }

        /* ── Section headings ────────────────────────────────────────────── */
        .wp-block-woocommerce-cart .wc-block-components-order-summary__title,
        .wp-block-woocommerce-cart .wp-block-woocommerce-cart-order-summary-heading-block {
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        </style>
        <script>
        (function(){
            var hSels = ['header#masthead','header.site-header','#masthead','.site-header','header'];
            var fSels = ['footer#colophon','footer.site-footer','#colophon','.site-footer','footer'];
            var mSels = ['.site-main','main.site-main','#main','main#main','main'];
            function measure(sels){
                for(var i=0;i<sels.length;i++){var el=document.querySelector(sels[i]);if(el)return el.getBoundingClientRect().height;}
                return 0;
            }
            function find(sels){
                for(var i=0;i<sels.length;i++){var el=document.querySelector(sels[i]);if(el)return el;}
                return null;
            }
            function applyHeight(){
                var hH=measure(hSels)||83, fH=measure(fSels)||601;
                var main=find(mSels);
                if(main) main.style.minHeight=Math.max(300,window.innerHeight-hH-fH)+'px';
            }
            // Align the heading with the WC cart block.
            // The cart block uses WordPress alignfull (negative margins to break out of the
            // content container). We read the block's computed margins and apply the same
            // offset + extra padding to the heading so the text starts at the same x as the block.
            function alignHeading(){
                var block   = document.querySelector('.wp-block-woocommerce-cart');
                var heading = document.querySelector('.infoplus-cart-heading');
                if(!block || !heading) return;
                var cs  = window.getComputedStyle(block);
                var ml  = parseFloat(cs.marginLeft)  || 0;
                var mr  = parseFloat(cs.marginRight) || 0;
                // Only adjust when the block actually uses negative margins (alignfull).
                if(ml < -1 || mr < -1){
                    heading.style.marginLeft  = ml + 'px';
                    heading.style.marginRight = mr + 'px';
                    heading.style.boxSizing   = 'border-box';
                    heading.style.width       = 'calc(100% + ' + (Math.abs(ml) + Math.abs(mr)) + 'px)';
                }
            }
            document.addEventListener('DOMContentLoaded', function(){ applyHeight(); alignHeading(); });
            window.addEventListener('resize', function(){ applyHeight(); alignHeading(); });
        })();
        </script>
        <?php
    }

    /**
     * Inject brand-consistent styles into the WooCommerce Blocks checkout page.
     * Fires on wp_head, outputs only on the checkout page.
     * Overrides the theme's transparent/outlined button style with solid pink.
     */
    public function enqueueCheckoutStyles(): void
    {
        if (!function_exists('is_checkout') || !is_checkout()) {
            return;
        }
        ?>
        <style id="infoplus-checkout-styles">
        /* ── Viewport fill ───────────────────────────────────────────────── */
        .site-main, main.site-main, #main, main#main, main {
            min-height: calc(100vh - 684px);
        }

        /* ── Primary submit button ("Jetzt kaufen") ─────────────────────── */
        /* Override both the theme [type=submit] rule AND WP element buttons */
        .wp-block-woocommerce-checkout .wp-element-button,
        .wp-block-woocommerce-checkout .wp-block-button__link,
        .wp-block-woocommerce-checkout .wc-block-components-checkout-step__submit,
        .wp-block-woocommerce-checkout .wc-block-components-button:not(.is-link),
        .wp-block-woocommerce-checkout [type="submit"],
        .wp-block-woocommerce-checkout button[type="submit"] {
            background-color: #e91e8c !important;
            border: none !important;
            border-radius: 8px !important;
            color: #fff !important;
            font-weight: 600 !important;
            padding: 14px 28px !important;
            transition: background-color 0.2s !important;
            text-align: center !important;
        }
        .wp-block-woocommerce-checkout .wp-element-button:hover,
        .wp-block-woocommerce-checkout .wc-block-components-checkout-step__submit:hover,
        .wp-block-woocommerce-checkout .wc-block-components-button:not(.is-link):hover,
        .wp-block-woocommerce-checkout [type="submit"]:hover {
            background-color: #d91a7e !important;
            color: #fff !important;
            border: none !important;
        }

        /* ── "Zurück zum Warenkorb" + all other links ───────────────────── */
        .wp-block-woocommerce-checkout a,
        .wp-block-woocommerce-checkout .wc-block-components-checkout-return-to-cart-button {
            color: #e91e8c !important;
        }
        .wp-block-woocommerce-checkout a:hover {
            color: #d91a7e !important;
        }

        /* ── "Bearbeiten" link inside address block ─────────────────────── */
        .wp-block-woocommerce-checkout .wc-block-components-address-card__edit {
            color: #e91e8c !important;
            font-weight: 500;
        }

        /* ── Radio/checkbox accent (selected payment method border) ────── */
        .wp-block-woocommerce-checkout .wc-block-components-radio-control__input:checked {
            accent-color: #e91e8c;
            border-color: #e91e8c !important;
        }
        .wp-block-woocommerce-checkout
            .wc-block-components-radio-control-accordion-option--checked {
            border-color: #e91e8c !important;
            box-shadow: 0 0 0 1px #e91e8c !important;
        }

        /* ── "Gutscheine hinzufügen" toggle ─────────────────────────────── */
        .wp-block-woocommerce-checkout .wc-block-components-panel__button,
        .wp-block-woocommerce-checkout .wc-block-components-totals-coupon__button {
            color: #e91e8c !important;
        }

        /* ── Totals: grand total value ──────────────────────────────────── */
        .wp-block-woocommerce-checkout .wc-block-components-totals-footer-item
            .wc-block-components-totals-item__value {
            color: #e91e8c !important;
            font-weight: 700 !important;
        }

        /* ── Input focus ring ────────────────────────────────────────────── */
        .wp-block-woocommerce-checkout input:focus,
        .wp-block-woocommerce-checkout select:focus,
        .wp-block-woocommerce-checkout textarea:focus {
            outline: none !important;
            border-color: #e91e8c !important;
            box-shadow: 0 0 0 2px rgba(233, 30, 140, 0.15) !important;
        }
        </style>
        <script>
        (function(){
            var hSels = ['header#masthead','header.site-header','#masthead','.site-header','header'];
            var fSels = ['footer#colophon','footer.site-footer','#colophon','.site-footer','footer'];
            var mSels = ['.site-main','main.site-main','#main','main#main','main'];
            function measure(sels){
                for(var i=0;i<sels.length;i++){var el=document.querySelector(sels[i]);if(el)return el.getBoundingClientRect().height;}
                return 0;
            }
            function find(sels){
                for(var i=0;i<sels.length;i++){var el=document.querySelector(sels[i]);if(el)return el;}
                return null;
            }
            function applyHeight(){
                var hH=measure(hSels)||83, fH=measure(fSels)||601;
                var main=find(mSels);
                if(main) main.style.minHeight=Math.max(300,window.innerHeight-hH-fH)+'px';
            }
            document.addEventListener('DOMContentLoaded',applyHeight);
            window.addEventListener('resize',applyHeight);
        })();
        </script>
        <?php
    }

    /**
     * render_block filter — intercepts the Gutenberg-based WooCommerce cart block.
     *
     * Strategy:
     *  - Always inject our custom empty-cart HTML before the WC block, wrapped in
     *    a container whose initial visibility is set server-side (hidden when cart
     *    has items, visible when empty).
     *  - Always hide `.wp-block-woocommerce-empty-cart-block` via CSS so the WC
     *    default empty state never shows — React still mounts into it (no error).
     *  - A JS snippet subscribes to the WC Blocks `wc/store/cart` data store so
     *    that when the user empties the cart on the page (AJAX, no reload) our
     *    overlay appears and the WC default stays hidden.
     */
    public function filterWooCartBlock(string $block_content, array $block): string
    {
        if (($block['blockName'] ?? '') !== 'woocommerce/cart') {
            return $block_content;
        }


        if (!function_exists('WC') || !WC()->cart) {
            return $block_content;
        }

        // Server-side initial state — JS takes over after hydration.
        $initially_empty = WC()->cart->is_empty();

        ob_start();
        $this->renderEmptyCartContent();
        $our_html = ob_get_clean();

        $initial_display = $initially_empty ? 'block' : 'none';

        $wrapped = '<div id="infoplus-empty-cart-overlay" style="display:' . $initial_display . '" aria-hidden="' . ($initially_empty ? 'false' : 'true') . '">'
                 . $our_html
                 . '</div>';

        // Always hide the WC default empty-cart block — we replace it with ours.
        // !important beats React inline styles in all browsers per CSS spec.
        $style = '<style>'
               . '#infoplus-empty-cart-overlay{}'   // anchor for specificity
               . '.wp-block-woocommerce-empty-cart-block{display:none!important}'
               . '</style>';

        // JS: detect when the cart becomes empty so our custom overlay appears.
        //
        // Two strategies run in parallel for maximum reliability:
        //
        // 1. wp.data store subscription — watches wc/store/cart for items_count===0.
        //    Guard: only act when items_count is a settled number (not undefined during
        //    hydration). Also tries d.items.length as a fallback property name.
        //
        // 2. MutationObserver on .wp-block-woocommerce-filled-cart-block — when WC
        //    Blocks hides this element (React sets display:none or removes it), we know
        //    the cart is empty. This is the reliable fallback when the data store API
        //    doesn't work or has changed shape.
        $initially_empty_js = $initially_empty ? 'true' : 'false';
        $script = '<script>'
            . '(function(){'
            .   'var overlay=document.getElementById("infoplus-empty-cart-overlay");'
            .   'if(!overlay)return;'
            .   'var lastEmpty=' . $initially_empty_js . ';'
            .   'function applyState(isEmpty){'
            .     'if(isEmpty===lastEmpty)return;'
            .     'lastEmpty=isEmpty;'
            .     'overlay.style.display=isEmpty?"block":"none";'
            .     'overlay.setAttribute("aria-hidden",isEmpty?"false":"true");'
            // When cart becomes empty, also hide the filled-cart block in case WC
            // Blocks leaves it partially visible during transition.
            .     'var filled=document.querySelector(".wp-block-woocommerce-filled-cart-block");'
            .     'if(filled){filled.style.display=isEmpty?"none":""}'
            .   '}'
            // Strategy 1: wp.data store subscription
            .   'function subscribe(){'
            .     'if(!window.wp||!wp.data)return false;'
            .     'var store=wp.data.select("wc/store/cart");'
            .     'if(!store)return false;'
            .     'wp.data.subscribe(function(){'
            .       'try{'
            .         'var d=wp.data.select("wc/store/cart").getCartData();'
            .         'if(d&&typeof d.items_count==="number"){applyState(d.items_count===0)}'
            .         'else if(d&&typeof d.itemsCount==="number"){applyState(d.itemsCount===0)}'
            .         'else if(d&&Array.isArray(d.items)){applyState(d.items.length===0)}'
            .       '}catch(e){}'
            .     '});'
            .     'return true;'
            .   '}'
            // Strategy 2: MutationObserver on the filled-cart block
            .   'function observeFilledBlock(){'
            .     'var filled=document.querySelector(".wp-block-woocommerce-filled-cart-block");'
            .     'if(!filled)return;'
            .     'function check(){'
            .       'var h=filled.style.display==="none"||filled.hidden||!filled.offsetParent;'
            .       'applyState(h);'
            .     '}'
            .     'new MutationObserver(check).observe(filled,{attributes:true,attributeFilter:["style","class","hidden"]});'
            // Also observe the parent for React child replacements
            .     'if(filled.parentElement){'
            .       'new MutationObserver(function(){'
            .         'var f=document.querySelector(".wp-block-woocommerce-filled-cart-block");'
            .         'if(!f){applyState(true);return}'
            .         'applyState(f.style.display==="none"||f.hidden||!f.offsetParent);'
            .       '}).observe(filled.parentElement,{childList:true,subtree:true,attributes:true});'
            .     '}'
            .   '}'
            // Boot: try data store immediately, retry on load; start observer on load
            .   'if(!subscribe()){'
            .     'window.addEventListener("load",function(){subscribe()||setTimeout(subscribe,800)});'
            .   '}'
            .   'if(document.readyState==="complete"){observeFilledBlock()}'
            .   'else{window.addEventListener("load",observeFilledBlock)}'
            . '})();'
            . '</script>';

        $heading = '<div class="infoplus-page-heading infoplus-cart-heading">'
                 . '<h1 class="infoplus-page-heading__title">Warenkorb</h1>'
                 . '</div>';

        return $heading . $wrapped . $style . $script . $block_content;
    }

    /**
     * render_block filter — rewrites the "Anmelden" login link inside the WooCommerce
     * checkout block so it points to our custom login page (/log-in/) instead of
     * the default wp-login.php URL.
     */
    public function filterWooCheckoutBlock(string $block_content, array $block): string
    {
        if (($block['blockName'] ?? '') !== 'woocommerce/checkout') {
            return $block_content;
        }

        $login_url = esc_url(home_url('/log-in/'));

        // Replace the href on the checkout login-prompt anchor.
        $block_content = preg_replace(
            '/(<a[^>]+class="[^"]*wc-block-checkout__login-prompt[^"]*"[^>]+href=")[^"]*(")/i',
            '$1' . $login_url . '$2',
            $block_content
        );

        // Also cover the reversed attribute order (href before class).
        $block_content = preg_replace(
            '/(<a[^>]+href=")[^"]*("[^>]+class="[^"]*wc-block-checkout__login-prompt[^"]*")/i',
            '$1' . $login_url . '$2',
            $block_content
        );

        return $block_content;
    }

    /**
     * Shortcode: [infoplus_empty_cart]
     * Returns empty string when cart has items; renders custom empty-cart layout otherwise.
     */
    public function emptyCartShortcode(array $atts = []): string
    {
        if (!function_exists('WC') || !WC()->cart || !WC()->cart->is_empty()) {
            return '';
        }

        ob_start();
        $this->renderEmptyCartContent();
        return ob_get_clean();
    }

    /**
     * Hooked on woocommerce_cart_is_empty (priority 1).
     * Renders a polished empty-cart UI and suppresses the default WC output.
     */
    public function renderEmptyCartContent(): void
    {
        $shop_url    = home_url('/namensschilder/');
        $account_url = home_url('/account/');
        $login_url   = home_url('/log-in/');   // custom login page (matches UserDashboard module)
        $logged_in   = is_user_logged_in();
        ?>
        <style>
            /* Hide the default WooCommerce empty-cart elements */
            .cart-empty,
            .return-to-shop,
            .woocommerce-info.woocommerce-cart-form__cart-info {
                display: none !important;
            }

            .infoplus-empty-cart-wrap {
                max-width: 700px;
                margin: 0 auto 48px;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }

            /* Hero banner */
            .infoplus-empty-cart-hero {
                text-align: center;
                padding: 48px 24px 40px;
                background: var(--e-global-color-primary, #e91e8c);
                border-radius: 16px;
                margin-bottom: 28px;
                color: #fff;
            }

            .infoplus-empty-cart-icon {
                width: 80px;
                height: 80px;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
            }

            .infoplus-empty-cart-icon svg {
                width: 40px;
                height: 40px;
                stroke: #fff;
                stroke-width: 2;
                fill: none;
            }

            .infoplus-empty-cart-title {
                font-size: 26px;
                font-weight: 700;
                margin: 0 0 10px;
                color: #fff;
            }

            .infoplus-empty-cart-subtitle {
                font-size: 15px;
                opacity: 0.88;
                margin: 0;
            }

            /* Hint card */
            .infoplus-empty-cart-card {
                background: #fff;
                border-radius: 12px;
                padding: 24px 28px;
                box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
                margin-bottom: 24px;
            }

            .infoplus-empty-cart-card-title {
                font-size: 13px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #888;
                margin: 0 0 14px;
                padding-bottom: 12px;
                border-bottom: 2px solid #f0f0f0;
            }

            .infoplus-empty-cart-tips {
                list-style: none;
                margin: 0;
                padding: 0;
            }

            .infoplus-empty-cart-tips li {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                padding: 10px 0;
                border-bottom: 1px solid #f5f5f5;
                font-size: 14px;
                color: #444;
                line-height: 1.5;
            }

            .infoplus-empty-cart-tips li:last-child {
                border-bottom: none;
                padding-bottom: 0;
            }

            .infoplus-empty-cart-tip-icon {
                flex-shrink: 0;
                width: 32px;
                height: 32px;
                background: rgba(233, 30, 140, 0.08);
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .infoplus-empty-cart-tip-icon svg {
                width: 16px;
                height: 16px;
                stroke: #e91e8c;
                fill: none;
                stroke-width: 2;
            }

            /* Action buttons */
            .infoplus-empty-cart-actions {
                display: flex;
                gap: 14px;
                justify-content: center;
                flex-wrap: wrap;
            }

            .infoplus-empty-cart-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 14px 28px;
                border-radius: 8px;
                font-weight: 600;
                font-size: 15px;
                text-decoration: none;
                transition: all 0.2s ease;
                cursor: pointer;
                border: none;
            }

            .infoplus-empty-cart-btn svg {
                width: 18px;
                height: 18px;
                stroke: currentColor;
                fill: none;
                stroke-width: 2;
            }

            .infoplus-empty-cart-btn-primary {
                background: var(--e-global-color-primary, #e91e8c);
                color: #fff;
            }

            .infoplus-empty-cart-btn-primary:hover {
                opacity: 0.88;
                transform: translateY(-1px);
                color: #fff;
            }

            .infoplus-empty-cart-btn-secondary {
                background: transparent;
                color: #e91e8c;
                border: 1.5px solid #e91e8c;
            }

            .infoplus-empty-cart-btn-secondary:hover {
                background: rgba(233, 30, 140, 0.06);
                color: #e91e8c;
            }

            @media (max-width: 600px) {
                .infoplus-empty-cart-actions {
                    flex-direction: column;
                }
                .infoplus-empty-cart-btn {
                    justify-content: center;
                }
            }
        </style>

        <div class="infoplus-empty-cart-wrap">

            <!-- Hero -->
            <div class="infoplus-empty-cart-hero">
                <div class="infoplus-empty-cart-icon">
                    <svg viewBox="0 0 24 24">
                        <circle cx="9" cy="21" r="1"/>
                        <circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                </div>
                <h2 class="infoplus-empty-cart-title">Ihr Warenkorb ist leer</h2>
                <p class="infoplus-empty-cart-subtitle">Entdecken Sie unsere Produkte und fügen Sie Artikel hinzu.</p>
            </div>

            <!-- Tips card -->
            <div class="infoplus-empty-cart-card">
                <p class="infoplus-empty-cart-card-title">So geht's weiter</p>
                <ul class="infoplus-empty-cart-tips">
                    <li>
                        <span class="infoplus-empty-cart-tip-icon">
                            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </span>
                        <span>Entdecken Sie unsere <a href="<?php echo esc_url($shop_url); ?>" style="color:#e91e8c;font-weight:600;">Namensschilder</a> und fügen Sie Artikel hinzu.</span>
                    </li>
                    <?php if ($logged_in): ?>
                    <li>
                        <span class="infoplus-empty-cart-tip-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </span>
                        <span>Schauen Sie in Ihr <a href="<?php echo esc_url($account_url); ?>" style="color:#e91e8c;font-weight:600;">Dashboard</a> für Ihre Bestellhistorie und gespeicherten Konfigurationen.</span>
                    </li>
                    <?php else: ?>
                    <li>
                        <span class="infoplus-empty-cart-tip-icon">
                            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </span>
                        <span>Mit einem <a href="<?php echo esc_url($login_url); ?>" style="color:#e91e8c;font-weight:600;">kostenlosen Konto</a> können Sie Bestellungen verwalten, Konfigurationen speichern und schneller bestellen.</span>
                    </li>
                    <?php endif; ?>
                    <li>
                        <span class="infoplus-empty-cart-tip-icon">
                            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        </span>
                        <span>Haben Sie Fragen? Kontaktieren Sie uns jederzeit — wir helfen gerne.</span>
                    </li>
                </ul>
            </div>

            <!-- Action buttons -->
            <div class="infoplus-empty-cart-actions">
                <a href="<?php echo esc_url($shop_url); ?>" class="infoplus-empty-cart-btn infoplus-empty-cart-btn-primary">
                    <svg viewBox="0 0 24 24">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    Namensschilder entdecken
                </a>
                <?php if ($logged_in): ?>
                <a href="<?php echo esc_url($account_url); ?>" class="infoplus-empty-cart-btn infoplus-empty-cart-btn-secondary">
                    <svg viewBox="0 0 24 24">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    Zum Dashboard
                </a>
                <?php else: ?>
                <a href="<?php echo esc_url($login_url); ?>" class="infoplus-empty-cart-btn infoplus-empty-cart-btn-secondary">
                    <svg viewBox="0 0 24 24">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                        <polyline points="10 17 15 12 10 7"/>
                        <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    Anmelden / Registrieren
                </a>
                <?php endif; ?>
            </div>

        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Branded Completed-Order Email
    // -------------------------------------------------------------------------

    /**
     * Fix logo alignment and spacing via output buffering so the overrides are
     * applied as inline styles — necessary for Gmail and other clients that
     * strip <head> CSS entirely.
     *
     * Captures the WooCommerce email header HTML and rewrites the inline
     * style/align attributes on #template_header_image directly.
     */
    public function fixEmailHeaderInlineStyles($email_heading, $email): void
    {
        if (!$email || !in_array($email->id, self::BRANDED_EMAIL_IDS, true)) {
            return;
        }

        ob_start();
        // The header template has already started output; we capture what
        // follows (the rest of the header) and patch it on ob_end_clean via
        // a shutdown-style filter on the full email content instead.
        // Use woocommerce_mail_content to rewrite the final HTML.
        add_filter('woocommerce_mail_content', [$this, 'rewriteEmailHeaderLogoInline']);
    }

    /**
     * Rewrite inline styles on the logo <td> and its <p> to force centering.
     * Runs once per email via woocommerce_mail_content.
     */
    public function rewriteEmailHeaderLogoInline(string $content): string
    {
        // Remove this filter immediately so it only runs once per email.
        remove_filter('woocommerce_mail_content', [$this, 'rewriteEmailHeaderLogoInline']);

        // Center the <td id="template_header_image"> — replace its padding style, add text-align.
        $content = preg_replace(
            '/(<td\s+id=["\']template_header_image["\'][^>]*style=["\'])([^"\']*?)(["\'])/i',
            '$1$2 text-align: center;$3',
            $content
        );

        // Center the <p> inside it and remove left alignment.
        $content = preg_replace(
            '/(<td\s+id=["\']template_header_image["\'].*?<p\s[^>]*style=["\'])([^"\']*?)(["\'])/is',
            '$1text-align: center; margin: 0 auto;$3',
            $content
        );

        // Remove align="left" attribute on the <p>.
        $content = preg_replace(
            '/(<td\s+id=["\']template_header_image["\'].*?<p\s[^>]*)\balign=["\']left["\']/is',
            '$1align="center"',
            $content
        );

        // Add margin-bottom and remove margin-right on the <img> inside that <td>.
        $content = preg_replace_callback(
            '/(<td\s+id=["\']template_header_image["\'].*?<img\s[^>]*style=["\'])([^"\']*?)(["\'])/is',
            function ($m) {
                $style = preg_replace('/margin-right:\s*\d+px;?\s*/i', '', $m[2]);
                $style = rtrim($style, '; ') . '; margin-bottom: 10px; margin-right: 0;';
                return $m[1] . $style . $m[3];
            },
            $content
        );

        return $content;
    }

    /**
     * Inject brand-colour CSS into the "Abgeschlossene Bestellung" customer email.
     *
     * WooCommerce collects this CSS into a <style> block placed in the email <head>.
     * We target only customer_completed_order to avoid changing every other WC email.
     * The overrides here handle:
     *  - Email header banner background → brand pink
     *  - Primary action button → brand pink
     *  - Section h2 headings → brand pink
     *  - Grand-total row → brand pink + bold
     *
     * @param string        $css   Existing WooCommerce email CSS.
     * @param \WC_Email|null $email The email being sent (null in some WC versions).
     */
    /**
     * The WC email IDs we brand — both the "order received / processing" confirmation
     * and the "order completed" notification.
     */
    private const BRANDED_EMAIL_IDS = [
        'customer_processing_order', // fires right after payment: "Wir haben Ihre Bestellung erhalten"
        'customer_completed_order',  // fires when order is marked completed: "Bestellung abgeschlossen"
    ];

    /**
     * Inject brand-colour CSS into the customer-facing order transactional emails.
     *
     * WooCommerce collects this CSS into a <style> block placed in the email <head>.
     * Overrides: pink header banner, pink h2 headings, pink CTA button, pink totals.
     *
     * @param string        $css   Existing WooCommerce email CSS.
     * @param \WC_Email|null $email The email being sent.
     */
    public function brandOrderEmailStyles(string $css, $email): string
    {
        if (!$email || !in_array($email->id, self::BRANDED_EMAIL_IDS, true)) {
            return $css;
        }

        return $css . '

            /* ── Infoplus brand overrides — order transactional emails ── */

            /* Header banner */
            #template_header                      { background-color: #e91e8c !important; }
            #template_header h1,
            #template_header p                    { color: #ffffff !important; }

            /* Logo alignment + spacing */
            #template_header_image                { text-align: center !important; }
            #template_header_image p              { text-align: center !important; margin: 0 auto !important; }
            #template_header_image p img          { margin-bottom: 10px !important; margin-right: 0 !important; }

            /* Section headings */
            h2                                    { color: #e91e8c !important; }

            /* Primary CTA button */
            a.button {
                background-color: #e91e8c !important;
                border-color:     #e91e8c !important;
                color:            #ffffff !important;
            }

            /* Order table links */
            .order_item td.td a                   { color: #e91e8c; }

            /* Grand total row (last tfoot row) */
            tfoot tr:last-child td {
                color:       #e91e8c !important;
                font-weight: 700     !important;
            }
        ';
    }

    /**
     * Render a branded banner above the WooCommerce order table for customer-facing
     * order transactional emails:
     *
     *  • customer_processing_order — "Bestellung erhalten, wir arbeiten daran"
     *    Fires automatically when a customer pays and the order enters "processing".
     *
     *  • customer_completed_order  — "Bestellung abgeschlossen"
     *    Fires when the order is marked "completed" (dispatched / fulfilled).
     *
     * Uses table-based inline-styled layout for broad email-client compatibility
     * (Gmail, Apple Mail, Outlook). Plain-text variants and admin copies are skipped.
     *
     * @param \WC_Abstract_Order $order          The order object.
     * @param bool               $sent_to_admin  True for the admin notification copy.
     * @param bool               $plain_text     True for plain-text email variants.
     * @param \WC_Email|null     $email          The WC_Email object.
     */
    public function renderOrderEmailBanner($order, bool $sent_to_admin, bool $plain_text, $email): void
    {
        if ($plain_text || $sent_to_admin) {
            return;
        }

        if (!$email || !in_array($email->id, self::BRANDED_EMAIL_IDS, true)) {
            return;
        }

        $first_name  = $order->get_billing_first_name();
        $order_num   = $order->get_order_number();
        $account_url = home_url('/account/');
        $shop_url    = home_url('/namensschilder/');

        // Content varies by email type
        $is_processing = ($email->id === 'customer_processing_order');

        if ($is_processing) {
            $icon_char = '📦';
            $headline  = 'Bestellung #' . esc_html($order_num) . ' erhalten!';
            $body      = 'Hallo ' . esc_html($first_name) . ', vielen Dank für Ihre Bestellung! '
                       . 'Wir haben sie erhalten und bearbeiten sie so schnell wie möglich. '
                       . 'Wir melden uns, sobald sie auf dem Weg ist.';
        } else {
            $icon_char = '✓';
            $headline  = 'Bestellung #' . esc_html($order_num) . ' abgeschlossen';
            $body      = 'Hallo ' . esc_html($first_name) . ', Ihre Bestellung wurde erfolgreich '
                       . 'abgeschlossen. Vielen Dank für Ihren Einkauf — wir freuen uns, Sie '
                       . 'bald wieder begrüßen zu dürfen!';
        }
        ?>
        <!--[if mso]><table width="100%"><tr><td><![endif]-->
        <table border="0" cellpadding="0" cellspacing="0" width="100%"
               style="margin-bottom:24px;">
            <tr>
                <td style="background-color:#fdf2f8;border-left:4px solid #e91e8c;
                           border-radius:6px;padding:20px 24px;">
                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                            <td width="44" valign="middle" style="padding-right:14px;">
                                <div style="width:44px;height:44px;border-radius:50%;
                                            background:#e91e8c;font-size:22px;
                                            display:flex;align-items:center;justify-content:center;">
                                    <span style="color:#fff;display:flex;align-items:center;justify-content:center;height:100%;"><?php echo $icon_char; ?></span>
                                </div>
                            </td>
                            <td valign="middle">
                                <p style="margin:0 0 5px;font-size:17px;font-weight:700;
                                          color:#e91e8c;line-height:1.3;">
                                    <?php echo $headline; ?>
                                </p>
                                <p style="margin:0;font-size:14px;color:#555;line-height:1.65;">
                                    <?php echo $body; ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <!--[if mso]></td></tr></table><![endif]-->

        <!-- Quick-action buttons -->
        <table border="0" cellpadding="0" cellspacing="0" width="100%"
               style="margin-bottom:28px;">
            <tr>
                <td align="center" style="padding:0 0 4px;">
                    <a href="<?php echo esc_url($account_url); ?>"
                       style="display:inline-block;background-color:#e91e8c;color:#fff;
                              text-decoration:none;font-weight:600;font-size:14px;
                              padding:10px 22px;border-radius:6px;margin-right:10px;">
                        Zum Dashboard
                    </a>
                    <a href="<?php echo esc_url($shop_url); ?>"
                       style="display:inline-block;background-color:#fff;color:#e91e8c;
                              text-decoration:none;font-weight:600;font-size:14px;
                              padding:10px 22px;border-radius:6px;
                              border:2px solid #e91e8c;">
                        Weiter einkaufen
                    </a>
                </td>
            </tr>
        </table>
        <?php
    }
}
