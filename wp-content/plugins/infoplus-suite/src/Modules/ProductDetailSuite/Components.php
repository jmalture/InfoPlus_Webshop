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

    /**
     * Merge args over defaults. Unknown keys are kept for theme overrides.
     */
    private static function merge(array $defaults, array $args): array
    {
        return array_merge($defaults, $args);
    }
}
