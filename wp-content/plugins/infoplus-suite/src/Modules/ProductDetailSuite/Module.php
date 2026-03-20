<?php

/**
 * Product Detail Suite Module
 * 
 * Provides custom Elementor widgets for WooCommerce product detail pages.
 * Widgets communicate via a shared state system to create an integrated experience.
 * 
 * @package Infoplus\Suite\Modules\ProductDetailSuite
 * @since 1.0.52
 */

namespace Infoplus\Suite\Modules\ProductDetailSuite;

use Infoplus\Suite\Contracts\ModuleInterface;
use Infoplus\Suite\Plugin;

if (!defined('ABSPATH')) exit;

final class Module implements ModuleInterface
{
    /** @var string Module version */
    public const VERSION = '1.0.58';

    public function slug(): string
    {
        return 'product-detail-suite';
    }
    public function label(): string
    {
        return __('Product Detail Suite', 'infoplus-suite');
    }

    public function register(): void
    {
        // Register custom fields for variations and attributes
        $this->registerFieldHandlers();

        // Register Elementor widgets
        add_action('elementor/widgets/register', [$this, 'registerWidgets']);

        // Register widget category
        add_action('elementor/elements/categories_registered', [$this, 'registerWidgetCategory']);

        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);

        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Intercept /products/prices before the legacy AlgolPlus plugin handles it.
        // rest_pre_dispatch short-circuits dispatch entirely — no route conflict.
        add_filter('rest_pre_dispatch', [$this, 'interceptBulkPricesRoute'], 10, 3);

        // Output product state to frontend
        add_action('woocommerce_after_single_product', [$this, 'outputProductState'], 5);
    }

    /**
     * Register field handlers for variations and attributes
     */
    private function registerFieldHandlers(): void
    {
        require_once __DIR__ . '/Fields/AttributeTermFields.php';
        require_once __DIR__ . '/Fields/ProductAttributeImages.php';

        $attributeTermFields = new Fields\AttributeTermFields();
        $attributeTermFields->register();

        $productAttributeImages = new Fields\ProductAttributeImages();
        $productAttributeImages->register();
    }

    /**
     * Register Elementor widget category
     */
    public function registerWidgetCategory($elements_manager): void
    {
        $elements_manager->add_category(
            'infoplus-product',
            [
                'title' => __('Infoplus Product', 'infoplus-suite'),
                'icon' => 'fa fa-shopping-bag',
            ]
        );
    }

    /**
     * Register Elementor widgets
     */
    public function registerWidgets($widgets_manager): void
    {
        // Only register if Elementor is loaded
        if (!did_action('elementor/loaded')) {
            return;
        }

        require_once __DIR__ . '/Widgets/ModelSelector.php';
        require_once __DIR__ . '/Widgets/VariationDropdowns.php';
        require_once __DIR__ . '/Widgets/ProductImage.php';
        require_once __DIR__ . '/Widgets/ProductOrderPanel.php';
        require_once __DIR__ . '/Widgets/BulkPriceTable.php';

        $widgets_manager->register(new Widgets\ModelSelector());
        $widgets_manager->register(new Widgets\VariationDropdowns());
        $widgets_manager->register(new Widgets\ProductImage());
        $widgets_manager->register(new Widgets\ProductOrderPanel());
        $widgets_manager->register(new Widgets\BulkPriceTable());
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueueFrontendAssets(): void
    {
        if (!is_product()) {
            return;
        }

        $ver = self::VERSION;
        $assetUrl = $this->assetUrl();

        // Main state management and communication JS
        wp_enqueue_script(
            'infoplus-product-state',
            $assetUrl . 'product-state.js',
            ['jquery'],
            $ver,
            true
        );

        // Model selector widget JS
        wp_enqueue_script(
            'infoplus-model-selector',
            $assetUrl . 'model-selector.js',
            ['jquery', 'infoplus-product-state'],
            $ver,
            true
        );

        // Variation dropdowns widget JS
        wp_enqueue_script(
            'infoplus-variation-dropdowns',
            $assetUrl . 'variation-dropdowns.js',
            ['jquery', 'infoplus-product-state'],
            $ver,
            true
        );

        // Product image widget JS
        wp_enqueue_script(
            'infoplus-product-image',
            $assetUrl . 'product-image.js',
            ['jquery', 'infoplus-product-state'],
            $ver,
            true
        );

        // Product order panel widget JS
        wp_enqueue_script(
            'infoplus-product-order-panel',
            $assetUrl . 'product-order-panel.js',
            ['jquery', 'infoplus-product-state'],
            $ver,
            true
        );

        // Main stylesheet
        wp_enqueue_style(
            'infoplus-product-detail-suite',
            $assetUrl . 'product-detail-suite.css',
            [],
            $ver
        );

        // Product order panel widget CSS
        wp_enqueue_style(
            'infoplus-product-order-panel',
            $assetUrl . 'product-order-panel.css',
            ['infoplus-product-detail-suite'],
            $ver
        );

        // Bulk price table widget JS
        wp_enqueue_script(
            'infoplus-bulk-price-table',
            $assetUrl . 'bulk-price-table.js',
            ['jquery', 'infoplus-product-state'],
            $ver,
            true
        );

        // Bulk price table widget CSS
        wp_enqueue_style(
            'infoplus-bulk-price-table',
            $assetUrl . 'bulk-price-table.css',
            ['infoplus-product-detail-suite'],
            $ver
        );

        // Localize script with REST URL and nonce
        wp_localize_script('infoplus-product-state', 'infoplusProductConfig', [
            'restUrl' => rest_url('infoplus/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n' => [
                'selectOption' => __('Choose an option', 'infoplus-suite'),
                'outOfStock' => __('Out of stock', 'infoplus-suite'),
                'moreInfo' => __('More information', 'infoplus-suite'),
                'close' => __('Close', 'infoplus-suite'),
                'from' => __('from', 'infoplus-suite'),
            ],
        ]);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(string $hook): void
    {
        // Only on product edit pages
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'product') {
            return;
        }

        wp_enqueue_media();

        $ver = self::VERSION;
        $assetUrl = $this->assetUrl();

        wp_enqueue_script(
            'infoplus-variation-fields-admin',
            $assetUrl . 'admin-variation-fields.js',
            ['jquery', 'wp-util'],
            $ver,
            true
        );

        wp_enqueue_style(
            'infoplus-variation-fields-admin',
            $assetUrl . 'admin-variation-fields.css',
            [],
            $ver
        );

        wp_localize_script('infoplus-variation-fields-admin', 'infoplusVariationFieldsConfig', [
            'i18n' => [
                'selectImage' => __('Select Image', 'infoplus-suite'),
                'removeImage' => __('Remove Image', 'infoplus-suite'),
                'useImage' => __('Use this image', 'infoplus-suite'),
                'chooseImage' => __('Choose Model Image', 'infoplus-suite'),
            ],
        ]);
    }

    /**
     * Register REST API endpoints
     */
    public function registerRestRoutes(): void
    {
        // Get product data with all custom fields
        register_rest_route('infoplus/v1', '/product/(?P<id>\d+)/detail', [
            'methods' => 'GET',
            'callback' => [$this, 'getProductDetail'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Get attribute term data (images, descriptions)
        register_rest_route('infoplus/v1', '/attribute-terms/(?P<attribute>[\w-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getAttributeTerms'],
            'permission_callback' => '__return_true',
        ]);
    }

    // -------------------------------------------------------------------------
    // Bulk prices REST endpoint
    // -------------------------------------------------------------------------

    /**
     * Intercept GET /wp-json/infoplus/v1/products/prices?sku=XXX before the
     * legacy AlgolPlus Products.php plugin handles it.
     *
     * The old handler only matched rules that stored SKUs in their filter value.
     * New pricing rules created in the WDP admin store WooCommerce variation IDs
     * (or parent product IDs) instead. This implementation handles all three cases.
     *
     * Returning a non-null value from rest_pre_dispatch short-circuits dispatch.
     */
    public function interceptBulkPricesRoute(mixed $result, \WP_REST_Server $server, \WP_REST_Request $request): mixed
    {
        // Only intercept if nothing has already claimed this request
        if ($result !== null) {
            return $result;
        }

        if ($request->get_route() !== '/infoplus/v1/products/prices') {
            return $result;
        }

        if ($request->get_method() !== 'GET') {
            return $result;
        }

        $sku = sanitize_text_field((string) ($request->get_param('sku') ?? ''));
        if ($sku === '') {
            return new \WP_REST_Response([], 400);
        }

        return new \WP_REST_Response($this->getBulkPricesBySku($sku), 200);
    }

    /**
     * Query the WDP rules table for bulk price tiers matching the given SKU.
     *
     * Matches against:
     *   1. The SKU string itself  (old-style rules filtered by SKU attribute)
     *   2. The variation post ID  (new-style rules filtered by "Products")
     *   3. The parent product ID  (rules applied at parent-product level)
     *
     * Returns an array of { from, to, value } objects ordered as stored in WDP.
     * Open-ended "to" values (≥ 99 000) are normalised to an empty string so the
     * frontend can render "500+ Stück" labels correctly.
     */
    private function getBulkPricesBySku(string $sku): array
    {
        global $wpdb;

        // Resolve WooCommerce IDs from the SKU
        $variation_id = (int) wc_get_product_id_by_sku($sku);
        $parent_id    = 0;

        if ($variation_id > 0) {
            $variation = wc_get_product($variation_id);
            if ($variation && $variation->get_parent_id()) {
                $parent_id = (int) $variation->get_parent_id();
            }
        }

        $table = $wpdb->prefix . 'wdp_rules';

        // Guard: table might not exist when the WDP plugin is inactive
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rules = $wpdb->get_results(
            "SELECT filters, bulk_adjustments FROM {$table} WHERE enabled = 1 ORDER BY priority ASC, id ASC"
        );

        foreach ($rules as $rule) {
            $filters = maybe_unserialize($rule->filters);
            if (!is_array($filters)) {
                continue;
            }

            foreach ($filters as $filter) {
                $values = $filter['value'] ?? [];
                if (!is_array($values)) {
                    continue;
                }

                // Cast values to strings for reliable in_array comparison
                $str_values = array_map('strval', $values);

                $matched = in_array($sku, $str_values, true)
                    || ($variation_id > 0 && in_array((string) $variation_id, $str_values, true))
                    || ($parent_id    > 0 && in_array((string) $parent_id,    $str_values, true));

                if ($matched) {
                    return $this->parseWdpRanges($rule->bulk_adjustments);
                }
            }
        }

        return [];
    }

    /**
     * Deserialise a WDP bulk_adjustments blob into the { from, to, value } shape
     * expected by the BulkPriceTable frontend widget.
     */
    private function parseWdpRanges(string $raw): array
    {
        $bulk   = maybe_unserialize($raw);
        $ranges = $bulk['ranges'] ?? [];
        $result = [];

        foreach ($ranges as $range) {
            $from  = (string) ($range['from']  ?? '');
            $to    = (string) ($range['to']    ?? '');
            $value = (string) ($range['value'] ?? '');

            // Normalise open-ended upper bounds (WDP uses 99999 as "unlimited")
            if ($to !== '' && (int) $to >= 99000) {
                $to = '';
            }

            $result[] = [
                'from'  => $from,
                'to'    => $to,
                'value' => $value,
            ];
        }

        return $result;
    }

    /**
     * Get detailed product data for frontend widgets
     */
    public function getProductDetail(\WP_REST_Request $request): \WP_REST_Response
    {
        $product_id = (int) $request->get_param('id');
        $product = wc_get_product($product_id);

        if (!$product) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Product not found', 'infoplus-suite'),
            ], 404);
        }

        $data = $this->buildProductData($product);

        return new \WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * Get attribute terms with custom fields
     */
    public function getAttributeTerms(\WP_REST_Request $request): \WP_REST_Response
    {
        $attribute = sanitize_text_field($request->get_param('attribute'));
        $taxonomy = 'pa_' . $attribute;

        if (!taxonomy_exists($taxonomy)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Attribute not found', 'infoplus-suite'),
            ], 404);
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);

        $data = [];
        foreach ($terms as $term) {
            $data[] = [
                'id' => $term->term_id,
                'slug' => $term->slug,
                'name' => $term->name,
                'description' => $term->description,
                'image_id' => get_term_meta($term->term_id, '_infoplus_term_image_id', true),
                'image_url' => $this->getTermImageUrl($term->term_id),
                'tooltip' => get_term_meta($term->term_id, '_infoplus_term_tooltip', true),
                'modal_content' => get_term_meta($term->term_id, '_infoplus_term_modal_content', true),
            ];
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * Build comprehensive product data for frontend
     */
    private function buildProductData($product): array
    {
        $product_id = $product->get_id();

        // Get product image
        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_url($image_id) : '';

        // Base data
        $data = [
            'id' => $product_id,
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'type' => $product->get_type(),
            'image' => $image_url,
            'gallery' => $this->getGalleryImages($product),
            'price' => $product->get_price(),
            'price_html' => $product->get_price_html(),
        ];

        // If variable product, get variations with custom fields
        if ($product->is_type('variable')) {
            $data['variations'] = $this->buildVariationsData($product);
            $data['attributes'] = $this->buildAttributesData($product);
            $data['models'] = $this->buildModelsData($product);
        }

        // Add IFE/IBE fields
        $data['ife_uuid'] = get_post_meta($product_id, '_ife_uuid', true);
        $data['editor_url'] = $this->buildEditorUrl($product_id);

        // Add order panel meta
        $min_qty = (int) get_post_meta($product_id, '_infoplus_min_quantity', true);
        $data['min_quantity'] = $min_qty > 0 ? $min_qty : 1;
        $delivery = (int) get_post_meta($product_id, '_infoplus_delivery_days', true);
        $data['delivery_days'] = $delivery > 0 ? $delivery : 0;

        // Default pre-selected variation — legacy single default
        $default_var_id = (int) get_post_meta($product_id, '_infoplus_default_variation_id', true);
        $data['default_variation_id'] = $default_var_id > 0 ? $default_var_id : null;

        // Per-model default variations map (model_slug => variation_id)
        $model_defaults_raw = get_post_meta($product_id, '_infoplus_model_default_variations', true);
        $model_defaults     = $model_defaults_raw ? json_decode($model_defaults_raw, true) : null;
        $data['model_default_variations'] = is_array($model_defaults) && !empty($model_defaults) ? $model_defaults : null;

        return $data;
    }

    /**
     * Build variations data with custom fields
     */
    private function buildVariationsData($product): array
    {
        $variations = [];

        foreach ($product->get_available_variations() as $variation_data) {
            $variation_id = $variation_data['variation_id'];
            $variation = wc_get_product($variation_id);

            if (!$variation) continue;

            // Get custom image for this variation's model
            $model_image_id = get_post_meta($variation_id, '_infoplus_model_image_id', true);
            $model_image_url = $model_image_id ? wp_get_attachment_url($model_image_id) : '';

            $variations[] = [
                'id' => $variation_id,
                'sku' => $variation->get_sku(),
                'price' => $variation->get_price(),
                'price_html' => $variation->get_price_html(),
                'regular_price' => $variation->get_regular_price(),
                'sale_price' => $variation->get_sale_price(),
                'is_in_stock' => $variation->is_in_stock(),
                'is_purchasable' => $variation->is_purchasable(),
                'attributes' => $variation_data['attributes'],
                'image' => [
                    'id' => $variation->get_image_id(),
                    'url' => $variation_data['image']['url'] ?? '',
                    'src' => $variation_data['image']['src'] ?? '',
                ],
                'model_image' => [
                    'id' => $model_image_id,
                    'url' => $model_image_url,
                ],
                'description' => get_post_meta($variation_id, '_infoplus_model_description', true),
                'tooltip' => get_post_meta($variation_id, '_infoplus_model_tooltip', true),
            ];
        }

        return $variations;
    }

    /**
     * Build attributes data with term info
     */
    private function buildAttributesData($product): array
    {
        $attributes = [];
        $product_id = $product->get_id();

        foreach ($product->get_attributes() as $attribute_name => $attribute) {
            if (!$attribute->get_visible()) continue;

            $taxonomy = $attribute->get_name();
            $terms = [];

            if ($attribute->is_taxonomy()) {
                $term_objects = get_terms([
                    'taxonomy' => $taxonomy,
                    'include' => $attribute->get_options(),
                    'hide_empty' => false,
                ]);

                foreach ($term_objects as $term) {
                    $terms[] = [
                        'id' => $term->term_id,
                        'slug' => $term->slug,
                        'name' => $term->name,
                        'image_url' => $this->getTermImageUrl($term->term_id, $product_id, $taxonomy, $term->slug),
                        'tooltip' => get_term_meta($term->term_id, '_infoplus_term_tooltip', true),
                    ];
                }
            }

            $attributes[$taxonomy] = [
                'name' => wc_attribute_label($taxonomy),
                'slug' => str_replace('pa_', '', $taxonomy),
                'terms' => $terms,
                'variation' => $attribute->get_variation(),
            ];
        }

        return $attributes;
    }

    /**
     * Build models data grouped by model attribute
     */
    private function buildModelsData($product): array
    {
        $models = [];
        $product_id = $product->get_id();
        $variations = $product->get_available_variations();
        $model_attribute = 'attribute_pa_model';

        // Group variations by model
        $grouped = [];
        foreach ($variations as $variation) {
            $model_slug = $variation['attributes'][$model_attribute] ?? '';
            if (!$model_slug) continue;

            if (!isset($grouped[$model_slug])) {
                $grouped[$model_slug] = [
                    'slug' => $model_slug,
                    'variations' => [],
                    'min_price' => PHP_FLOAT_MAX,
                ];
            }

            $price = (float) $variation['display_price'];
            if ($price < $grouped[$model_slug]['min_price']) {
                $grouped[$model_slug]['min_price'] = $price;
            }

            $grouped[$model_slug]['variations'][] = $variation['variation_id'];
        }

        // Build final models array with term data
        foreach ($grouped as $model_slug => $model_data) {
            $term = get_term_by('slug', $model_slug, 'pa_model');

            if (!$term) continue;

            // Get gallery using the override system (product-level gallery first, then single image, then global)
            $gallery_images = Fields\ProductAttributeImages::getTermGalleryUrls($product_id, 'pa_model', $model_slug);
            $model_image_url = !empty($gallery_images) ? $gallery_images[0]['url'] : '';

            $order_type = Fields\ProductAttributeImages::getTermOrderType($product_id, 'pa_model', $model_slug);

            $models[] = [
                'slug' => $model_slug,
                'name' => $term->name,
                'description' => $term->description,
                'image_url' => $model_image_url,      // First gallery image (backward compat for ModelSelector cards)
                'gallery' => $gallery_images,          // Full gallery array for ProductImage widget
                'order_type' => $order_type,           // 'editable' | 'requestable' | 'buyable'
                'min_price' => $model_data['min_price'] === PHP_FLOAT_MAX ? 0 : $model_data['min_price'],
                'min_price_html' => wc_price($model_data['min_price'] === PHP_FLOAT_MAX ? 0 : $model_data['min_price']),
                'variation_count' => count($model_data['variations']),
                'tooltip' => get_term_meta($term->term_id, '_infoplus_term_tooltip', true),
                'modal_content' => get_term_meta($term->term_id, '_infoplus_term_modal_content', true),
            ];
        }

        return $models;
    }

    /**
     * Get gallery images
     */
    private function getGalleryImages($product): array
    {
        $gallery_ids = $product->get_gallery_image_ids();
        $images = [];

        foreach ($gallery_ids as $image_id) {
            $images[] = [
                'id' => $image_id,
                'url' => wp_get_attachment_url($image_id),
                'thumbnail' => wp_get_attachment_image_url($image_id, 'thumbnail'),
                'full' => wp_get_attachment_image_url($image_id, 'full'),
            ];
        }

        return $images;
    }

    /**
     * Get term image URL (with product-level override support)
     */
    private function getTermImageUrl(int $term_id, int $product_id = 0, string $taxonomy = '', string $term_slug = ''): string
    {
        // If we have product context, use the override system
        if ($product_id > 0 && $taxonomy && $term_slug) {
            return Fields\ProductAttributeImages::getTermImageUrl($product_id, $taxonomy, $term_slug);
        }

        // Fallback to global term image
        $image_id = get_term_meta($term_id, '_infoplus_term_image_id', true);
        return $image_id ? wp_get_attachment_url($image_id) : '';
    }

    /**
     * Build editor URL for product
     */
    private function buildEditorUrl(int $product_id): string
    {
        if (!class_exists('\Infoplus\Suite\Modules\EditorLinkSettings\Module')) {
            return '';
        }

        $uuid = get_post_meta($product_id, '_ife_uuid', true);
        if (!$uuid) {
            return '';
        }

        $product_url = get_permalink($product_id);
        return \Infoplus\Suite\Modules\EditorLinkSettings\Module::generateEditorUrl($uuid, $product_url);
    }

    /**
     * Output product state to frontend (for initial page load)
     */
    public function outputProductState(): void
    {
        global $product;

        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }

        $data = $this->buildProductData($product);

        echo '<script id="infoplus-product-data">';
        echo 'window.infoplusProductData = ' . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';';
        echo '</script>';
    }

    /**
     * Get asset URL
     */
    private function assetUrl(): string
    {
        return INFOPLUS_SUITE_URL . 'src/Modules/ProductDetailSuite/Assets/';
    }
}
