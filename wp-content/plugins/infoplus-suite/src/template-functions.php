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
