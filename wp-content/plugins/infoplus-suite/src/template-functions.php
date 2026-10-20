<?php
/**
 * Public template helpers for classic PHP themes.
 *
 * Each helper renders one Suite component through Infoplus\Suite\View::render().
 * Args not passed fall back to the component defaults (the Elementor widget
 * control defaults). The $product argument accepts a WC_Product, a product ID,
 * or null for the global $product.
 *
 * Loaded by Infoplus\Suite\Plugin::boot(). Every function is guarded with
 * function_exists() so a theme can replace it.
 *
 * @package Infoplus\Suite
 */

use Infoplus\Suite\View;
use Infoplus\Suite\Modules\ProductDetailSuite\Components;

if (!defined('ABSPATH')) exit;

if (!function_exists('infoplus_suite_model_selector')) {
    /**
     * Render the model selector (model cards with image, name, "from" price, info button).
     *
     * @param WC_Product|int|null $product Product, product ID, or null for the global $product.
     * @param array               $args    See templates/model-selector.php. Defaults:
     *                                     Components::modelSelectorDefaults().
     */
    function infoplus_suite_model_selector($product = null, array $args = []): void
    {
        View::render('model-selector', Components::modelSelector($product, $args));
    }
}

if (!function_exists('infoplus_suite_variation_dropdowns')) {
    /**
     * Render the variation attribute dropdowns (Format, Befestigung, ...).
     *
     * @param WC_Product|int|null $product Product, product ID, or null for the global $product.
     * @param array               $args    See templates/variation-dropdowns.php. Defaults:
     *                                     Components::variationDropdownsDefaults().
     */
    function infoplus_suite_variation_dropdowns($product = null, array $args = []): void
    {
        View::render('variation-dropdowns', Components::variationDropdowns($product, $args));
    }
}

if (!function_exists('infoplus_suite_product_image')) {
    /**
     * Render the product image with zoom, gallery thumbnails and lightbox.
     *
     * @param WC_Product|int|null $product Product, product ID, or null for the global $product.
     * @param array               $args    See templates/product-image.php. Defaults:
     *                                     Components::productImageDefaults().
     */
    function infoplus_suite_product_image($product = null, array $args = []): void
    {
        View::render('product-image', Components::productImage($product, $args));
    }
}

if (!function_exists('infoplus_suite_product_info_box')) {
    /**
     * Render the product info box (SKU, minimum order quantity, delivery time).
     *
     * @param WC_Product|int|null $product Product, product ID, or null for the global $product.
     * @param array               $args    See templates/product-info-box.php. Defaults:
     *                                     Components::productInfoBoxDefaults().
     */
    function infoplus_suite_product_info_box($product = null, array $args = []): void
    {
        View::render('product-info-box', Components::productInfoBox($product, $args));
    }
}

if (!function_exists('infoplus_suite_bulk_price_table')) {
    /**
     * Render the bulk price table (Staffelpreise). Tiers are loaded in the browser
     * by bulk-price-table.js for the selected variation; $product is accepted for
     * a uniform signature and passed to the template.
     *
     * @param WC_Product|int|null $product Product, product ID, or null for the global $product.
     * @param array               $args    See templates/bulk-price-table.php. Defaults:
     *                                     Components::bulkPriceTableDefaults().
     */
    function infoplus_suite_bulk_price_table($product = null, array $args = []): void
    {
        View::render('bulk-price-table', Components::bulkPriceTable($product, $args));
    }
}
