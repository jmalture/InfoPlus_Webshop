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
