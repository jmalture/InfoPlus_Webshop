<?php
/**
 * The template for displaying product content within loops
 *
 * Theme override: each item renders template-parts/product-card.php. All five
 * loop item hooks still fire, so plugins hooked there keep working; their
 * output is collected and placed at the end of the card body. WooCommerce's
 * own callbacks on these hooks (link, thumbnail, title, rating, price,
 * add-to-cart) are removed in inc/shop.php because the card replaces them.
 *
 * Loop props read here (set by the listing templates with wc_set_loop_prop()):
 * - ip_card_level (string) heading tag for the product name, default h2.
 * - ip_card_sizes (string) sizes attribute, default = the 1/2/3/4 column grid.
 * - ip_lcp        (bool)   true when the first card's image is the page's LCP
 *                          (first page of a listing): loaded eagerly, high priority.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.4.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

// Check if the product is a valid WooCommerce product and ensure its visibility before proceeding.
if ( ! is_a( $product, WC_Product::class ) || ! $product->is_visible() ) {
	return;
}

// Running index across the request: the first card of the page is the LCP candidate.
$ip_index = (int) wc_get_loop_prop( 'ip_index', 0 );
wc_set_loop_prop( 'ip_index', $ip_index + 1 );

ob_start();
/**
 * Hook: woocommerce_before_shop_loop_item.
 *
 * @hooked woocommerce_template_loop_product_link_open - 10 (removed by the theme)
 */
do_action( 'woocommerce_before_shop_loop_item' );

/**
 * Hook: woocommerce_before_shop_loop_item_title.
 *
 * @hooked woocommerce_show_product_loop_sale_flash - 10 (removed by the theme)
 * @hooked woocommerce_template_loop_product_thumbnail - 10 (removed by the theme)
 */
do_action( 'woocommerce_before_shop_loop_item_title' );

/**
 * Hook: woocommerce_shop_loop_item_title.
 *
 * @hooked woocommerce_template_loop_product_title - 10 (removed by the theme)
 */
do_action( 'woocommerce_shop_loop_item_title' );

/**
 * Hook: woocommerce_after_shop_loop_item_title.
 *
 * @hooked woocommerce_template_loop_rating - 5 (removed by the theme)
 * @hooked woocommerce_template_loop_price - 10 (removed by the theme)
 */
do_action( 'woocommerce_after_shop_loop_item_title' );

/**
 * Hook: woocommerce_after_shop_loop_item.
 *
 * @hooked woocommerce_template_loop_product_link_close - 5 (removed by the theme)
 * @hooked woocommerce_template_loop_add_to_cart - 10 (removed by the theme)
 */
do_action( 'woocommerce_after_shop_loop_item' );
$ip_extra = trim( (string) ob_get_clean() );

$ip_args = array(
	'id'    => $product->get_id(),
	'level' => wc_get_loop_prop( 'ip_card_level', 'h2' ),
	'extra' => $ip_extra,
	'eager' => 0 === $ip_index && wc_get_loop_prop( 'ip_lcp', false ),
);
$ip_sizes = wc_get_loop_prop( 'ip_card_sizes', '' );
if ( $ip_sizes ) {
	$ip_args['sizes'] = $ip_sizes;
}
?>
<li <?php wc_product_class( 'ip-shop-grid__item', $product ); ?>>
	<?php get_template_part( 'template-parts/product-card', null, $ip_args ); ?>
</li>
