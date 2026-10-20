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

    // ── variation-dropdowns ─────────────────────────────────────────────────

    /**
     * Defaults of the variation dropdowns (VariationDropdowns widget content controls).
     */
    public static function variationDropdownsDefaults(): array
    {
        return [
            'attributes_to_show'   => ['pa_format', 'pa_befestigung'], // empty array = all
            'exclude_model'        => true,
            'show_info_button'     => true,
            'info_button_behavior' => 'tooltip', // 'tooltip' | 'modal'
            'placeholder_text'     => __('Wähle eine Option', 'infoplus-suite'),
        ];
    }

    /**
     * Final template args for templates/variation-dropdowns.php.
     *
     * Adds: product, has_product (bool, variable product present), dropdowns
     * (list of attribute, label, slug, has_images, terms; each term has slug,
     * name, image_url, tooltip, modal_content).
     *
     * @param \WC_Product|int|null $product
     */
    public static function variationDropdowns($product = null, array $args = []): array
    {
        $args = self::merge(self::variationDropdownsDefaults(), $args);

        $show = $args['attributes_to_show'];
        if (!is_array($show)) {
            $show = ($show === '' || $show === null) ? [] : (array) $show;
        }
        $args['attributes_to_show']   = $show;
        $args['exclude_model']        = self::flag($args['exclude_model']);
        $args['show_info_button']     = self::flag($args['show_info_button']);
        $args['info_button_behavior'] = (string) $args['info_button_behavior'];
        $args['placeholder_text']     = (string) $args['placeholder_text'];

        $product = self::resolveProduct($product);
        $args['product']     = $product;
        $args['has_product'] = $product !== null && $product->is_type('variable');
        $args['dropdowns']   = $args['has_product']
            ? self::dropdowns($product, $args['attributes_to_show'], $args['exclude_model'])
            : [];

        return $args;
    }

    private static function dropdowns(\WC_Product $product, array $attributes_to_show, bool $exclude_model): array
    {
        $product_id = $product->get_id();
        $dropdowns  = [];

        foreach ($product->get_variation_attributes() as $attribute_name => $options) {
            $attribute_name = (string) $attribute_name;

            // Skip model if excluded
            if ($exclude_model && $attribute_name === 'pa_model') {
                continue;
            }

            // Filter by selected attributes if specified
            if (!empty($attributes_to_show) && !in_array($attribute_name, $attributes_to_show)) {
                continue;
            }

            // Terms with custom fields (with product-level image overrides)
            $terms = self::termsData($attribute_name, (array) $options, $product_id);

            $has_images = false;
            foreach ($terms as $term) {
                if (!empty($term['image_url'])) {
                    $has_images = true;
                    break;
                }
            }

            $dropdowns[] = [
                'attribute'  => $attribute_name,
                'label'      => wc_attribute_label($attribute_name),
                'slug'       => str_replace('pa_', '', $attribute_name),
                'has_images' => $has_images,
                'terms'      => $terms,
            ];
        }

        return $dropdowns;
    }

    /**
     * Term data for one attribute: product-level overrides first, then global term meta.
     * Formerly VariationDropdowns::getTermsData().
     */
    private static function termsData(string $taxonomy, array $option_slugs, int $product_id = 0): array
    {
        $terms_data = [];

        foreach ($option_slugs as $slug) {
            $term = get_term_by('slug', $slug, $taxonomy);

            if (!$term) {
                // Fallback for custom attributes
                $terms_data[] = [
                    'slug' => $slug,
                    'name' => ucfirst($slug),
                    'image_url' => '',
                    'tooltip' => '',
                    'modal_content' => '',
                ];
                continue;
            }

            if ($product_id > 0) {
                $image_url     = Fields\ProductAttributeImages::getTermImageUrl($product_id, $taxonomy, $slug);
                $tooltip       = Fields\ProductAttributeImages::getTermTooltip($product_id, $taxonomy, $slug);
                $modal_content = Fields\ProductAttributeImages::getTermModalContent($product_id, $taxonomy, $slug);
            } else {
                // Fallback to global term data
                $image_id      = get_term_meta($term->term_id, '_infoplus_term_image_id', true);
                $image_url     = $image_id ? wp_get_attachment_url($image_id) : '';
                $tooltip       = get_term_meta($term->term_id, '_infoplus_term_tooltip', true) ?: '';
                $modal_content = get_term_meta($term->term_id, '_infoplus_term_modal_content', true) ?: $term->description;
            }

            $terms_data[] = [
                'slug' => $term->slug,
                'name' => $term->name,
                'image_url' => $image_url,
                'tooltip' => $tooltip,
                'modal_content' => $modal_content,
            ];
        }

        return $terms_data;
    }

    // ── product-image ───────────────────────────────────────────────────────

    /**
     * Defaults of the product image (ProductImage widget content controls).
     */
    public static function productImageDefaults(): array
    {
        return [
            'enable_zoom'      => true,
            'zoom_scale'       => 2,
            'enable_lightbox'  => true,
            'show_gallery'     => true,
            'gallery_position' => 'bottom', // 'bottom' | 'left' | 'right'
        ];
    }

    /**
     * Final template args for templates/product-image.php.
     *
     * Adds: product, product_name (alt text), main_image_url, main_image_full,
     * initial_thumbs (list of url, thumbnail_url).
     *
     * @param \WC_Product|int|null $product
     */
    public static function productImage($product = null, array $args = []): array
    {
        $args = self::merge(self::productImageDefaults(), $args);

        foreach (['enable_zoom', 'enable_lightbox', 'show_gallery'] as $key) {
            $args[$key] = self::flag($args[$key]);
        }
        $args['zoom_scale']       = is_scalar($args['zoom_scale']) ? $args['zoom_scale'] : 2;
        $args['gallery_position'] = (string) $args['gallery_position'];

        $product = self::resolveProduct($product);
        $args['product']      = $product;
        $args['product_name'] = $product ? $product->get_name() : 'Product';

        // Main image: product image, or placeholder
        $main_image_id   = $product ? $product->get_image_id() : 0;
        $main_image_url  = $main_image_id ? wp_get_attachment_url($main_image_id) : wc_placeholder_img_src('woocommerce_single');
        $main_image_full = $main_image_id ? wp_get_attachment_image_url($main_image_id, 'full') : $main_image_url;

        $gallery_ids = $product ? $product->get_gallery_image_ids() : [];

        // First model's full gallery for the initial render
        $first_gallery = $product ? self::firstModelGallery($product) : [];

        // If the model has a gallery, its first image is the main image
        if (!empty($first_gallery)) {
            $main_image_url  = $first_gallery[0]['url'];
            $main_image_full = $first_gallery[0]['url'];
        }

        // Initial thumbnails: model gallery[1..n], or the WooCommerce gallery for non-model products
        $initial_thumbs = [];
        if (!empty($first_gallery) && count($first_gallery) > 1) {
            foreach (array_slice($first_gallery, 1) as $img) {
                $initial_thumbs[] = [
                    'url'           => $img['url'],
                    'thumbnail_url' => $img['thumbnail_url'],
                ];
            }
        } elseif (empty($first_gallery)) {
            foreach ($gallery_ids as $gid) {
                $gurl = wp_get_attachment_url($gid);
                if ($gurl) {
                    $initial_thumbs[] = [
                        'url'           => $gurl,
                        'thumbnail_url' => wp_get_attachment_image_url($gid, 'thumbnail') ?: $gurl,
                    ];
                }
            }
        }

        $args['main_image_url']  = $main_image_url;
        $args['main_image_full'] = $main_image_full;
        $args['initial_thumbs']  = $initial_thumbs;

        return $args;
    }

    /**
     * Full gallery of the first model of this product that has gallery images:
     * list of id, url, thumbnail_url. Empty when there are no models or images.
     * Formerly ProductImage::getFirstModelGallery().
     */
    private static function firstModelGallery(\WC_Product $product): array
    {
        if (!$product->is_type('variable')) {
            return [];
        }

        $product_id = $product->get_id();

        // First model term assigned to this product, no need to load all variations
        $model_terms = wc_get_product_terms($product_id, 'pa_model', ['fields' => 'slugs']);
        if (empty($model_terms) || is_wp_error($model_terms)) {
            return [];
        }

        foreach ($model_terms as $model_slug) {
            $gallery = Fields\ProductAttributeImages::getTermGalleryUrls($product_id, 'pa_model', (string) $model_slug);
            if (!empty($gallery)) {
                return $gallery;
            }
        }

        return [];
    }

    /**
     * Merge args over defaults. Unknown keys are kept for theme overrides.
     */
    private static function merge(array $defaults, array $args): array
    {
        return array_merge($defaults, $args);
    }
}
