<?php
/**
 * WooCommerce integration. Day 1 scope: theme support and the header cart
 * count. Template overrides live in /woocommerce and arrive with the shop
 * steps of the rebuild map (rows 6 to 14).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', function () {
	add_theme_support( 'woocommerce' );
} );

/** Cart item count, 0 when WooCommerce or the cart is unavailable. */
function ip_cart_count() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0;
	}
	return (int) WC()->cart->get_cart_contents_count();
}

/** Header cart badge markup; also returned as a cart fragment so it updates after add-to-cart. */
function ip_cart_badge() {
	$count = ip_cart_count();
	return sprintf(
		'<span class="ip-cart__count%s" data-ip-cart-count>%s</span>',
		$count ? '' : ' is-empty',
		esc_html( $count )
	);
}

add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
	$fragments['[data-ip-cart-count]'] = ip_cart_badge();
	return $fragments;
} );
