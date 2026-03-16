<?php
/**
 * Sync Controller
 * 
 * Handles REST API endpoints and AJAX requests for product synchronization.
 * 
 * @package Infoplus\Suite\Modules\IkonaIFE
 * @since 1.0.0
 */

namespace Infoplus\Suite\Modules\IkonaIFE;

if (!defined('ABSPATH')) exit;

class SyncController
{
    /** @var string REST API namespace */
    private const REST_NAMESPACE = 'infoplus-suite/v1';
    
    /** @var SyncService */
    private SyncService $service;

    /**
     * Constructor
     * 
     * @param SyncService $service Sync service instance
     */
    public function __construct(SyncService $service)
    {
        $this->service = $service;
    }

    /**
     * Register REST API routes
     */
    public function registerRoutes(): void
    {
        // GET products (proxy)
        register_rest_route(self::REST_NAMESPACE, '/products', [
            'methods' => 'GET',
            'callback' => [$this, 'getProducts'],
            'permission_callback' => [$this, 'canManageWooCommerce'],
        ]);

        // POST save sync result
        register_rest_route(self::REST_NAMESPACE, '/products/(?P<id>\d+)/sync-result', [
            'methods' => 'POST',
            'callback' => [$this, 'saveSyncResult'],
            'permission_callback' => [$this, 'canManageWooCommerce'],
            'args' => [
                'id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // GET single product sync status
        register_rest_route(self::REST_NAMESPACE, '/products/(?P<id>\d+)/sync-status', [
            'methods' => 'GET',
            'callback' => [$this, 'getSyncStatus'],
            'permission_callback' => [$this, 'canManageWooCommerce'],
            'args' => [
                'id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // POST clear cache
        register_rest_route(self::REST_NAMESPACE, '/products/clear-cache', [
            'methods' => 'POST',
            'callback' => [$this, 'clearCache'],
            'permission_callback' => [$this, 'canManageWooCommerce'],
        ]);
    }

    /**
     * Permission callback: check if user can manage WooCommerce
     * 
     * @return bool
     */
    public function canManageWooCommerce(): bool
    {
        // Use manage_options as fallback if manage_woocommerce isn't available
        return current_user_can('manage_woocommerce') || current_user_can('manage_options');
    }

    /**
     * GET /products - Proxy products from upstream API
     * 
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
      public function getProducts(\WP_REST_Request $request): \WP_REST_Response
    {
        $products = $this->service->fetchProductsWithSyncStatus();
        
        return new \WP_REST_Response($products, 200);
    }

    /**
     * POST /products/{id}/sync-result - Save sync result to database
     * 
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function saveSyncResult(\WP_REST_Request $request): \WP_REST_Response
    {
        $productId = (int) $request->get_param('id');
        $body = $request->get_json_params();
        
        // Validate product exists
        $product = wc_get_product($productId);
        if (!$product) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Product not found', 'infoplus-suite'),
            ], 404);
        }
        
        // Extract data from request
        $ifeProductId = sanitize_text_field($body['ifeProductId'] ?? '');
        $variants = $body['variants'] ?? [];
        
        // Validate variants array
        if (!is_array($variants)) {
            $variants = [];
        }
        
        // Save sync result
        $result = $this->service->saveSyncResult($productId, $ifeProductId, $variants);
        
        if (!$result['success']) {
            return new \WP_REST_Response($result, 400);
        }
        
        return new \WP_REST_Response($result, 200);
    }

    /**
     * GET /products/{id}/sync-status - Get sync status for a product
     * 
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getSyncStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $productId = (int) $request->get_param('id');
        
        // Validate product exists
        $product = wc_get_product($productId);
        if (!$product) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Product not found', 'infoplus-suite'),
            ], 404);
        }
        
        $status = $this->service->getProductSyncStatus($productId);
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $status,
        ], 200);
    }

    /**
     * POST /products/clear-cache - Clear products cache
     * 
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function clearCache(\WP_REST_Request $request): \WP_REST_Response
    {
        $this->service->clearCache();
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Cache cleared successfully', 'infoplus-suite'),
        ], 200);
    }

    /**
     * AJAX handler: Sync all products
     */
    public function handleAjaxSyncAll(): void
    {
        check_ajax_referer('infoplus_sync', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions', 'infoplus-suite'),
            ]);
            return;
        }
        
        // TODO: Implement batch sync
        wp_send_json_error([
            'message' => __('Batch sync will be available in a future update.', 'infoplus-suite'),
        ]);
    }

    /**
     * AJAX handler: Sync single product
     */
    public function handleAjaxSyncProduct(): void
    {
        check_ajax_referer('infoplus_sync', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions', 'infoplus-suite'),
            ]);
            return;
        }
        
        $productId = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        
        if (!$productId) {
            wp_send_json_error([
                'message' => __('Invalid product ID', 'infoplus-suite'),
            ]);
            return;
        }
        
        // Return info about using REST API instead
        wp_send_json_success([
            'message' => __('Use the REST API endpoint for sync operations.', 'infoplus-suite'),
        ]);
    }
}
