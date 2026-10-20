<?php
/**
 * Product Detail Suite components: defaults and view data.
 *
 * Shop logic for the product page components lives here, markup lives in
 * templates/<component>.php. Each public builder merges the caller's args over
 * the component defaults (the Elementor control defaults), adds the data the
 * template needs and returns the final $args for View::render().
 *
 * Used by the Elementor widgets and by the infoplus_suite_*() theme helpers.
 *
 * @package Infoplus\Suite\Modules\ProductDetailSuite
 */

namespace Infoplus\Suite\Modules\ProductDetailSuite;

if (!defined('ABSPATH')) exit;

final class Components
{
    /**
     * Script and style handles per component. Same handles as the widgets'
     * get_script_depends() / get_style_depends().
     */
    public const ASSETS = [
        'model-selector' => [
            'scripts' => ['infoplus-product-state', 'infoplus-model-selector'],
            'styles'  => ['infoplus-product-detail-suite'],
        ],
        'variation-dropdowns' => [
            'scripts' => ['infoplus-product-state', 'infoplus-variation-dropdowns'],
            'styles'  => ['infoplus-product-detail-suite'],
        ],
        'product-image' => [
            'scripts' => ['infoplus-product-state', 'infoplus-product-image'],
            'styles'  => ['infoplus-product-detail-suite'],
        ],
        'product-info-box' => [
            'scripts' => ['infoplus-product-state', 'infoplus-product-info-box'],
            'styles'  => ['infoplus-product-detail-suite'],
        ],
        'bulk-price-table' => [
            'scripts' => ['infoplus-product-state', 'infoplus-bulk-price-table'],
            'styles'  => ['infoplus-product-detail-suite', 'infoplus-bulk-price-table'],
        ],
    ];

    /**
     * Resolve the product argument of a helper or widget.
     *
     * @param \WC_Product|int|null $product Product object, product ID, or null for the global $product.
     */
    public static function resolveProduct($product = null): ?\WC_Product
    {
        if ($product === null) {
            $product = $GLOBALS['product'] ?? null;
        }

        if (is_numeric($product) && (int) $product > 0 && function_exists('wc_get_product')) {
            $product = wc_get_product((int) $product);
        }

        return $product instanceof \WC_Product ? $product : null;
    }

    /**
     * Normalise a switch value: Elementor sends 'yes' / '', themes may send booleans.
     */
    public static function flag($value): bool
    {
        return $value === true || $value === 'yes' || $value === 1 || $value === '1';
    }

    // ── model-selector ──────────────────────────────────────────────────────

    /**
     * Defaults of the model selector (ModelSelector widget content controls).
     */
    public static function modelSelectorDefaults(): array
    {
        return [
            'title'                => __('Variant', 'infoplus-suite'),
            'show_title'           => true,
            'show_price'           => true,
            'show_info_button'     => true,
            'info_button_behavior' => 'modal',   // 'modal' | 'tooltip'
            'sync_product_image'   => true,
            'layout'               => 'vertical', // 'vertical' | 'horizontal'
        ];
    }

    /**
     * Final template args for templates/model-selector.php.
     *
     * Adds: product (WC_Product|null), has_product (bool, variable product
     * present), models (list of slug, name, image_url, tooltip, modal_content,
     * min_price).
     *
     * @param \WC_Product|int|null $product
     */
    public static function modelSelector($product = null, array $args = []): array
    {
        $args = self::merge(self::modelSelectorDefaults(), $args);

        foreach (['show_title', 'show_price', 'show_info_button', 'sync_product_image'] as $key) {
            $args[$key] = self::flag($args[$key]);
        }
        $args['title']                = (string) $args['title'];
        $args['info_button_behavior'] = (string) $args['info_button_behavior'];
        $args['layout']               = (string) $args['layout'];

        $product = self::resolveProduct($product);
        $args['product']     = $product;
        $args['has_product'] = $product !== null && $product->is_type('variable');
        $args['models']      = $args['has_product'] ? self::modelCards($product) : [];

        return $args;
    }

    /**
     * Model cards of a variable product: one per pa_model value of its
     * published variations, with the lowest variation price.
     * Direct DB query, fast for products with many variations.
     */
    private static function modelCards(\WC_Product $product): array
    {
        global $wpdb;

        $product_id = $product->get_id();
        $children   = $product->get_children();
        $rows       = [];

        if (!empty($children)) {
            $ids_placeholder = implode(',', array_map('intval', $children));

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IDs are cast to int above.
            $rows = $wpdb->get_results(
                "SELECT pm_model.meta_value AS model_slug, MIN(CAST(pm_price.meta_value AS DECIMAL(10,2))) AS min_price
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm_model ON p.ID = pm_model.post_id AND pm_model.meta_key = 'attribute_pa_model'
                 LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
                 WHERE p.ID IN ({$ids_placeholder})
                   AND p.post_status = 'publish'
                   AND pm_model.meta_value != ''
                 GROUP BY pm_model.meta_value"
            );
        }

        $min_prices = [];
        foreach ((array) $rows as $row) {
            $min_prices[$row->model_slug] = (float) $row->min_price;
        }

        $cards = [];
        foreach ($min_prices as $model_slug => $min_price) {
            $model_slug = (string) $model_slug;
            $term = get_term_by('slug', $model_slug, 'pa_model');
            if (!$term) {
                continue;
            }

            // Model image: first gallery image, fallback single image, then global, then placeholder
            $gallery_images  = Fields\ProductAttributeImages::getTermGalleryUrls($product_id, 'pa_model', $model_slug);
            $model_image_url = !empty($gallery_images) ? $gallery_images[0]['url'] : '';
            if (!$model_image_url) {
                $model_image_url = wc_placeholder_img_src('woocommerce_thumbnail');
            }

            $cards[] = [
                'slug'          => $model_slug,
                'name'          => $term->name,
                'image_url'     => $model_image_url,
                'tooltip'       => Fields\ProductAttributeImages::getTermTooltip($product_id, 'pa_model', $model_slug),
                'modal_content' => Fields\ProductAttributeImages::getTermModalContent($product_id, 'pa_model', $model_slug),
                'min_price'     => $min_price,
            ];
        }

        return $cards;
    }

    /**
     * Merge args over defaults. Unknown keys are kept for theme overrides.
     */
    private static function merge(array $defaults, array $args): array
    {
        return array_merge($defaults, $args);
    }
}
