<?php
/*
Plugin Name: Woo Single Product Addons
Description: Filter Woocommerce variation form based on available variations and various extension of single product page functionality
Version: 1.9.1
Author: Jan Matyas
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$ver = '1.9.1';

// Enqueue scripts for product page
add_action('wp_enqueue_scripts', function() use ($ver) {
    if (is_product()) {
        // Native Woo filter
        wp_enqueue_script(
            'woo-variation-filter',
            plugins_url('variation-filter.js', __FILE__),
            ['jquery'],
            $ver,
            true
        );
        // Elementor custom bulk calculator (if you have it)
        wp_enqueue_script(
            'elementor-bulk-calculator',
            plugins_url('elementor-bulk-calculator.js', __FILE__),
            ['jquery'],
            $ver,
            true
        );
        // Custom model dropdown with image support
        wp_enqueue_script(
            'custom-model-dropdown',
            plugins_url('custom-model-dropdown.js', __FILE__),
            ['jquery'],
            $ver,
            true
        );
        // Elementor custom product gallery sizer
        wp_enqueue_script(
            'product-gallery-sizer',
            plugins_url('product-gallery-sizer.js', __FILE__),
            ['jquery'],
            $ver,
            true
        );
        wp_enqueue_script(
            'variation-editor-link',
             plugins_url('variation-editor-link.js', __FILE__),
            ['jquery'],
            $ver,
            true
        );
    }
});

add_action('woocommerce_after_single_product', function () {
    global $product;
    if (!$product || !$product->is_type('variable')) return;

    $available_variations = $product->get_available_variations();
    $product_image_url    = wp_get_attachment_url($product->get_image_id());

    $out = [];
    foreach ($available_variations as $v) {
        $variation_id = isset($v['variation_id']) ? (int) $v['variation_id'] : 0;

        $out[] = [
            'model'        => $v['attributes']['attribute_pa_model'] ?? '',
            'format'       => $v['attributes']['attribute_pa_format'] ?? '',
            'befestigung'  => $v['attributes']['attribute_pa_befestigung'] ?? '',
            'oberflaeche'  => $v['attributes']['attribute_pa_oberflache'] ?? '',
            'variation_id' => $variation_id,
            'ibe_ife_url'  => $variation_id ? get_post_meta($variation_id, '_ibe_variation_url', true) : '',
        ];
    }
    ?>
    <script>
      window.allWooVariations     = <?php echo wp_json_encode($out); ?>;
      window.currentProductImage  = <?php echo wp_json_encode($product_image_url); ?>;
    </script>
    <?php
});

// Output arrow SVG URL for JS
add_action('wp_head', function() {
    if (is_product()) {
        echo '<script>window.arrowSvgUrl = "' . esc_js(plugins_url('assets/arrow.svg', __FILE__)) . '";</script>';
    }
});
