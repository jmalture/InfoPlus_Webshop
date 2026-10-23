<?php

/**
 * Product Detail Suite Module
 * 
 * Provides custom Elementor widgets for WooCommerce product detail pages.
 * Widgets communicate via a shared state system to create an integrated experience.
 * 
 * @package Infoplus\Suite\Modules\ProductDetailSuite
 * @since 1.0.100
 */

namespace Infoplus\Suite\Modules\ProductDetailSuite;

use Infoplus\Suite\Contracts\ModuleInterface;
use Infoplus\Suite\Plugin;
use Infoplus\Suite\View;

if (!defined('ABSPATH')) exit;

final class Module implements ModuleInterface
{
    /** @var string Module version */
    public const VERSION = '1.1.1';

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

        // Register frontend assets everywhere, enqueue them globally on product pages
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);

        // Tell the View renderer which handles each component needs (on-demand enqueue)
        foreach (Components::ASSETS as $component => $handles) {
            View::registerAssets($component, $handles['scripts'], $handles['styles']);
        }

        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Intercept /products/prices before the legacy AlgolPlus plugin handles it.
        // rest_pre_dispatch short-circuits dispatch entirely — no route conflict.
        add_filter('rest_pre_dispatch', [$this, 'interceptBulkPricesRoute'], 10, 3);

        // Output product state to frontend
        add_action('woocommerce_after_single_product', [$this, 'outputProductState'], 5);

        // Invalidate product data cache when product is saved
        add_action('woocommerce_after_product_object_save', [$this, 'invalidateProductCache']);

        // Force AJAX variation loading for products with many variations.
        // Prevents WooCommerce from embedding a huge JSON blob inline.
        add_filter('woocommerce_ajax_variation_threshold', function () {
            return self::VARIATION_AJAX_THRESHOLD;
        });
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
        require_once __DIR__ . '/Widgets/ProductInfoBox.php';

        $widgets_manager->register(new Widgets\ModelSelector());
        $widgets_manager->register(new Widgets\VariationDropdowns());
        $widgets_manager->register(new Widgets\ProductImage());
        $widgets_manager->register(new Widgets\ProductOrderPanel());
        $widgets_manager->register(new Widgets\BulkPriceTable());
        $widgets_manager->register(new Widgets\ProductInfoBox());
    }

    /**
     * Register frontend assets on every front-end request so components can
     * enqueue them on demand (View::render), then enqueue them globally on
     * single product pages exactly as before.
     */
    public function enqueueFrontendAssets(): void
    {
        $this->registerFrontendAssets();

        if (!is_product()) {
            return;
        }

        // Same handles, same order as before the View renderer existed.
        // The live Elementor product template relies on this global enqueue.
        wp_enqueue_script('infoplus-product-state');
        wp_enqueue_script('infoplus-model-selector');
        wp_enqueue_script('infoplus-variation-dropdowns');
        wp_enqueue_script('infoplus-product-image');
        wp_enqueue_script('infoplus-product-order-panel');
        wp_enqueue_style('infoplus-product-detail-suite');
        wp_enqueue_style('infoplus-product-order-panel');
        wp_enqueue_script('infoplus-bulk-price-table');
        wp_enqueue_script('infoplus-product-info-box');
        wp_enqueue_style('infoplus-bulk-price-table');
    }

    /**
     * Register (not enqueue) all frontend handles of this module.
     */
    private function registerFrontendAssets(): void
    {
        $ver = self::VERSION;
        $assetUrl = $this->assetUrl();

        // Main state management and communication JS
        wp_register_script('infoplus-product-state', $assetUrl . 'product-state.js', ['jquery'], $ver, true);

        // Widget JS
        wp_register_script('infoplus-model-selector', $assetUrl . 'model-selector.js', ['jquery', 'infoplus-product-state'], $ver, true);
        wp_register_script('infoplus-variation-dropdowns', $assetUrl . 'variation-dropdowns.js', ['jquery', 'infoplus-product-state'], $ver, true);
        wp_register_script('infoplus-product-image', $assetUrl . 'product-image.js', ['jquery', 'infoplus-product-state'], $ver, true);
        wp_register_script('infoplus-product-order-panel', $assetUrl . 'product-order-panel.js', ['jquery', 'infoplus-product-state'], $ver, true);
        wp_register_script('infoplus-bulk-price-table', $assetUrl . 'bulk-price-table.js', ['jquery', 'infoplus-product-state'], $ver, true);
        wp_register_script('infoplus-product-info-box', $assetUrl . 'product-info-box.js', ['jquery', 'infoplus-product-state'], $ver, true);

        // Styles
        wp_register_style('infoplus-product-detail-suite', $assetUrl . 'product-detail-suite.css', [], $ver);
        wp_register_style('infoplus-product-order-panel', $assetUrl . 'product-order-panel.css', ['infoplus-product-detail-suite'], $ver);
        wp_register_style('infoplus-bulk-price-table', $assetUrl . 'bulk-price-table.css', ['infoplus-product-detail-suite'], $ver);

        // Localize script with REST URL and nonce (printed only when the handle is enqueued)
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

        // Get ONLY variations for a product (lightweight, for deferred loading)
        register_rest_route('infoplus/v1', '/product/(?P<id>\d+)/variations', [
            'methods' => 'GET',
            'callback' => [$this, 'getProductVariations'],
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

        // Tiers are read and cached (24 h per SKU) by BulkPrices, which the
        // server-side minimum quantity check (WooSingleProductAddons\MinQuantity) shares.
        return new \WP_REST_Response(BulkPrices::forSku($sku), 200);
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
     * Get ONLY variations for a product — lightweight endpoint for deferred loading.
     * Uses get_children() + direct meta queries instead of get_available_variations()
     * to avoid loading full WC_Product objects for every variation.
     */
    public function getProductVariations(\WP_REST_Request $request): \WP_REST_Response
    {
        $product_id = (int) $request->get_param('id');
        $product = wc_get_product($product_id);

        if (!$product || !$product->is_type('variable')) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Product not found or not variable',
            ], 404);
        }

        $variations = $this->buildVariationsDataLite($product);

        return new \WP_REST_Response([
            'success' => true,
            'variations' => $variations,
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
            // Fetch available variations once and reuse for both builders
            $raw_variations = $product->get_available_variations();
            $data['variations'] = $this->buildVariationsData($product, $raw_variations);
            $data['attributes'] = $this->buildAttributesData($product);
            $data['models'] = $this->buildModelsData($product, $raw_variations);
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
    private function buildVariationsData($product, ?array $raw_variations = null): array
    {
        $variations = [];

        if ($raw_variations === null) {
            $raw_variations = $product->get_available_variations();
        }

        foreach ($raw_variations as $variation_data) {
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
     * Build variations data using direct DB queries — no WC_Product loading.
     * Returns only the fields needed by the JS (id, sku, attributes, is_in_stock, price, image).
     */
    private function buildVariationsDataLite($product): array
    {
        global $wpdb;

        $children = $product->get_children();
        if (empty($children)) return [];

        $ids_placeholder = implode(',', array_map('intval', $children));

        // Get all relevant meta for all children in one query
        $meta_rows = $wpdb->get_results(
            "SELECT post_id, meta_key, meta_value
             FROM {$wpdb->postmeta}
             WHERE post_id IN ({$ids_placeholder})
               AND meta_key IN ('_sku', '_price', '_stock_status', '_thumbnail_id')
             ORDER BY post_id"
        );

        // Get attribute meta (attribute_pa_*) for all children
        $attr_rows = $wpdb->get_results(
            "SELECT post_id, meta_key, meta_value
             FROM {$wpdb->postmeta}
             WHERE post_id IN ({$ids_placeholder})
               AND meta_key LIKE 'attribute_%'
             ORDER BY post_id"
        );

        // Group meta by post_id
        $meta_map = [];
        foreach ($meta_rows as $row) {
            $meta_map[$row->post_id][$row->meta_key] = $row->meta_value;
        }

        $attr_map = [];
        foreach ($attr_rows as $row) {
            $attr_map[$row->post_id][$row->meta_key] = $row->meta_value;
        }

        // Check which children are published
        $status_rows = $wpdb->get_results(
            "SELECT ID FROM {$wpdb->posts}
             WHERE ID IN ({$ids_placeholder})
               AND post_status = 'publish'"
        );
        $published = array_flip(array_map(function($r) { return $r->ID; }, $status_rows));

        $variations = [];
        foreach ($children as $child_id) {
            if (!isset($published[$child_id])) continue;

            $meta = $meta_map[$child_id] ?? [];
            $attrs = $attr_map[$child_id] ?? [];

            $thumbnail_id = $meta['_thumbnail_id'] ?? '';
            $image_url = $thumbnail_id ? wp_get_attachment_url((int) $thumbnail_id) : '';

            $variations[] = [
                'id'             => $child_id,
                'sku'            => $meta['_sku'] ?? '',
                'price'          => $meta['_price'] ?? '',
                'is_in_stock'    => ($meta['_stock_status'] ?? 'instock') === 'instock',
                'is_purchasable' => true,
                'attributes'     => $attrs,
                'image'          => [
                    'url' => $image_url,
                    'src' => $image_url,
                ],
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
    private function buildModelsData($product, ?array $variations = null): array
    {
        $models = [];
        $product_id = $product->get_id();
        if ($variations === null) {
            $variations = $product->get_available_variations();
        }
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
     * Max variations to embed inline. Products above this threshold
     * will have variations loaded via AJAX to prevent page timeouts.
     */
    private const VARIATION_AJAX_THRESHOLD = 200;

    /**
     * Output product state to frontend (for initial page load).
     * For products with many variations, skip embedding variation data
     * inline and let product-state.js load it via REST instead.
     * Uses transient caching to avoid rebuilding on every page view.
     */
    public function outputProductState(): void
    {
        global $product;

        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }

        $product_id = $product->get_id();
        $cache_key  = 'infoplus_pd_' . $product_id . '_' . self::VERSION;
        $json       = get_transient($cache_key);

        if ($json === false) {
            $variation_count = $product->is_type('variable')
                ? count($product->get_children())
                : 0;

            if ($variation_count > self::VARIATION_AJAX_THRESHOLD) {
                $data = $this->buildProductDataLite($product);
                $data['_variationsDeferred'] = true;
            } else {
                $data = $this->buildProductData($product);
            }

            $json = wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            set_transient($cache_key, $json, 12 * HOUR_IN_SECONDS);
        }

        echo '<script id="infoplus-product-data">';
        echo 'window.infoplusProductData = ' . $json . ';';
        echo '</script>';
    }

    /**
     * Invalidate cached product data when a product is saved.
     */
    public function invalidateProductCache($product): void
    {
        $product_id = $product->get_id();
        // Delete current version cache
        delete_transient('infoplus_pd_' . $product_id . '_' . self::VERSION);

        // Also invalidate parent product cache if this is a variation
        $parent_id = $product->get_parent_id();
        if ($parent_id > 0) {
            delete_transient('infoplus_pd_' . $parent_id . '_' . self::VERSION);
        }
    }

    /**
     * Build product data WITHOUT loading each variation object.
     * Attributes and models are still included so the UI can render
     * dropdowns/selectors immediately; variations arrive via REST.
     */
    private function buildProductDataLite($product): array
    {
        $product_id = $product->get_id();

        $image_id  = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_url($image_id) : '';

        $data = [
            'id'         => $product_id,
            'name'       => $product->get_name(),
            'sku'        => $product->get_sku(),
            'type'       => $product->get_type(),
            'image'      => $image_url,
            'gallery'    => $this->getGalleryImages($product),
            'price'      => $product->get_price(),
            'price_html' => $product->get_price_html(),
            'variations' => [], // loaded via REST
        ];

        if ($product->is_type('variable')) {
            $data['attributes'] = $this->buildAttributesData($product);
            // Use lightweight model builder that doesn't load all variations
            $data['models']     = $this->buildModelsDataLite($product);
        }

        $data['ife_uuid']    = get_post_meta($product_id, '_ife_uuid', true);
        $data['editor_url']  = $this->buildEditorUrl($product_id);

        $min_qty = (int) get_post_meta($product_id, '_infoplus_min_quantity', true);
        $data['min_quantity']  = $min_qty > 0 ? $min_qty : 1;
        $delivery = (int) get_post_meta($product_id, '_infoplus_delivery_days', true);
        $data['delivery_days'] = $delivery > 0 ? $delivery : 0;

        $default_var_id = (int) get_post_meta($product_id, '_infoplus_default_variation_id', true);
        $data['default_variation_id'] = $default_var_id > 0 ? $default_var_id : null;

        $model_defaults_raw = get_post_meta($product_id, '_infoplus_model_default_variations', true);
        $model_defaults     = $model_defaults_raw ? json_decode($model_defaults_raw, true) : null;
        $data['model_default_variations'] = is_array($model_defaults) && !empty($model_defaults) ? $model_defaults : null;

        return $data;
    }

    /**
     * Build models data WITHOUT calling get_available_variations().
     * Uses taxonomy terms and child variation post meta directly,
     * which is orders of magnitude faster for 1000+ variants.
     */
    private function buildModelsDataLite($product): array
    {
        $product_id = $product->get_id();
        $models = [];

        // Get model terms assigned to this product
        $model_terms = wc_get_product_terms($product_id, 'pa_model', ['fields' => 'all']);
        if (empty($model_terms) || is_wp_error($model_terms)) {
            return [];
        }

        // Get min prices per model from child variations using a direct query
        global $wpdb;
        $children = $product->get_children();
        if (empty($children)) return [];

        $ids_placeholder = implode(',', array_map('intval', $children));

        // Get model attribute and price for each child variation in one query
        $rows = $wpdb->get_results(
            "SELECT p.ID, pm_price.meta_value AS price, pm_model.meta_value AS model_slug
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
             LEFT JOIN {$wpdb->postmeta} pm_model ON p.ID = pm_model.post_id AND pm_model.meta_key = 'attribute_pa_model'
             WHERE p.ID IN ({$ids_placeholder})
               AND p.post_status = 'publish'"
        );

        $grouped = [];
        foreach ($rows as $row) {
            $slug = $row->model_slug ?: '';
            if (!$slug) continue;

            if (!isset($grouped[$slug])) {
                $grouped[$slug] = [
                    'min_price' => PHP_FLOAT_MAX,
                    'count'     => 0,
                ];
            }

            $price = (float) $row->price;
            if ($price > 0 && $price < $grouped[$slug]['min_price']) {
                $grouped[$slug]['min_price'] = $price;
            }
            $grouped[$slug]['count']++;
        }

        foreach ($model_terms as $term) {
            $model_slug = $term->slug;
            $model_group = $grouped[$model_slug] ?? null;
            if (!$model_group) continue;

            $gallery_images  = Fields\ProductAttributeImages::getTermGalleryUrls($product_id, 'pa_model', $model_slug);
            $model_image_url = !empty($gallery_images) ? $gallery_images[0]['url'] : '';
            $order_type      = Fields\ProductAttributeImages::getTermOrderType($product_id, 'pa_model', $model_slug);
            $min_price       = $model_group['min_price'] === PHP_FLOAT_MAX ? 0 : $model_group['min_price'];

            $models[] = [
                'slug'            => $model_slug,
                'name'            => $term->name,
                'description'     => $term->description,
                'image_url'       => $model_image_url,
                'gallery'         => $gallery_images,
                'order_type'      => $order_type,
                'min_price'       => $min_price,
                'min_price_html'  => wc_price($min_price),
                'variation_count' => $model_group['count'],
                'tooltip'         => get_term_meta($term->term_id, '_infoplus_term_tooltip', true),
                'modal_content'   => get_term_meta($term->term_id, '_infoplus_term_modal_content', true),
            ];
        }

        return $models;
    }

    /**
     * Get asset URL
     */
    private function assetUrl(): string
    {
        return INFOPLUS_SUITE_URL . 'src/Modules/ProductDetailSuite/Assets/';
    }
}
