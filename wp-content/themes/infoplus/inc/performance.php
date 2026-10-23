<?php
/**
 * Front-end weight: assets of plugins that this theme's pages do not use.
 *
 * Measured on staging 2026-10-23 (guest, front page): 15 stylesheets and 17
 * scripts, of which these were not used by any theme view:
 *
 * - WP Data Access: wpda_wpdp_public (CSS) and wpda_rest_api (JS), which also
 *   pulled in wp-api, backbone, underscore and wp-api-request on every page.
 *   No page or post uses a WP Data Access shortcode (checked in post_content).
 * - Dashicons for visitors without the admin bar.
 * - Advanced Dynamic Pricing: wdp_cart-summary outside the cart and checkout.
 * - WooCommerce PayPal Payments: wc-ppcp-mini-cart-buttons (the theme has no
 *   WooCommerce mini cart widget; its own cart preview is inc/woocommerce.php).
 * - WooCommerce add-to-cart script and blockUI: the theme has no AJAX
 *   add-to-cart buttons (listings link to the product, the product page posts
 *   a normal form).
 * - WooCommerce classic shop CSS on the front page, the content pages and the
 *   Berater (no WooCommerce markup there). inc/shop.php already drops it on
 *   listings, cart and checkout.
 * - The cart page rendered the "Neu im Shop" product grids of the empty-cart
 *   section for every visit, which printed about 200 KB of product JSON
 *   inline. The Suite shows its own empty-cart panel (UserOrders), so these
 *   blocks are skipped on the cart page.
 *
 * - Germanized on product listings (namensschilder page, categories, search):
 *   its loop hooks cost about 4.1 s for the 21 cards (profiled 2026-10-23,
 *   9,725 queries per page view). The deposit (Pfand) info alone took 3.3 s
 *   and the delivery time 0.2 s, both empty for name badges. The tax and
 *   shipping lines ("inkl. MwSt.", "zzgl. Versandkosten") are the same for
 *   every card, so they are rendered once and reused.
 *
 * Every rule is in the filter ip_performance_trims (array of rule keys);
 * remove a key to keep that plugin asset or hook.
 */

defined( 'ABSPATH' ) || exit;

/** Active trim rules. */
function ip_performance_trims() {
	return (array) apply_filters( 'ip_performance_trims', array( 'wpda', 'dashicons', 'wdp', 'ppcp_minicart', 'add_to_cart', 'wc_css', 'cart_grids', 'gzd_loop' ) );
}

/** True when the request shows WooCommerce markup that the classic shop CSS styles (notices, account, order views). */
function ip_performance_needs_wc_css() {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return false;
	}
	return is_woocommerce() || is_cart() || is_checkout() || is_account_page()
		|| ( function_exists( 'ip_account_is_login_page' ) && ip_account_is_login_page() );
}

add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) {
		return;
	}
	$on = ip_performance_trims();

	if ( in_array( 'wpda', $on, true ) ) {
		wp_dequeue_style( 'wpda_wpdp_public' );
		wp_dequeue_script( 'wpda_rest_api' );
	}
	if ( in_array( 'dashicons', $on, true ) && ! is_admin_bar_showing() ) {
		wp_dequeue_style( 'dashicons' );
	}
	if ( in_array( 'wdp', $on, true ) && ! ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) ) {
		wp_dequeue_style( 'wdp_cart-summary' );
	}
	if ( in_array( 'ppcp_minicart', $on, true ) ) {
		wp_dequeue_style( 'wc-ppcp-mini-cart-buttons' );
	}
	if ( in_array( 'add_to_cart', $on, true ) ) {
		wp_dequeue_script( 'wc-add-to-cart' );
		wp_dequeue_script( 'wc-jquery-blockui' );
	}
	if ( in_array( 'wc_css', $on, true ) && ! ip_performance_needs_wc_css() ) {
		wp_dequeue_style( 'woocommerce-layout' );
		wp_dequeue_style( 'woocommerce-smallscreen' );
		wp_dequeue_style( 'woocommerce-general' );
	}
}, 200 );

/* The empty-cart product grids of the cart page (see the file comment). */
add_filter( 'pre_render_block', function ( $pre, $block ) {
	if ( null !== $pre || ! in_array( 'cart_grids', ip_performance_trims(), true ) ) {
		return $pre;
	}
	$name = $block['blockName'] ?? '';
	if ( in_array( $name, array( 'woocommerce/product-new', 'woocommerce/product-collection' ), true )
		&& function_exists( 'is_cart' ) && is_cart() ) {
		return '';
	}
	return $pre;
}, 10, 2 );

/* Germanized on listing cards (see the file comment). Runs late on "wp", after Germanized registered its loop hooks. */
add_action( 'wp', function () {
	if ( is_admin() || ! in_array( 'gzd_loop', ip_performance_trims(), true ) || ! function_exists( 'woocommerce_gzd_template_loop_tax_info' ) ) {
		return;
	}
	foreach ( array(
		array( 'woocommerce_after_shop_loop_item_title', 'woocommerce_gzd_template_loop_deposit' ),
		array( 'woocommerce_after_shop_loop_item_title', 'woocommerce_gzd_template_loop_deposit_packaging_type' ),
		array( 'woocommerce_after_shop_loop_item', 'woocommerce_gzd_template_loop_delivery_time_info' ),
	) as $hook ) {
		$prio = has_action( $hook[0], $hook[1] );
		if ( false !== $prio ) {
			remove_action( $hook[0], $hook[1], $prio );
		}
	}
	foreach ( array( 'woocommerce_gzd_template_loop_tax_info', 'woocommerce_gzd_template_loop_shipping_costs_info' ) as $fn ) {
		$prio = has_action( 'woocommerce_after_shop_loop_item', $fn );
		if ( false === $prio ) {
			continue;
		}
		remove_action( 'woocommerce_after_shop_loop_item', $fn, $prio );
		add_action( 'woocommerce_after_shop_loop_item', function () use ( $fn ) {
			static $html = array();
			if ( ! isset( $html[ $fn ] ) ) {
				$html[ $fn ] = ip_performance_gzd_line( $fn );
			}
			echo $html[ $fn ]; // phpcs:ignore WordPress.Security.EscapeOutput -- Germanized template output.
		}, $prio );
	}
}, 99990 );

/**
 * Germanized's tax or shipping line of a listing card, kept for 12 hours.
 * Rendering it once still loaded every variation of the first card's
 * product (87 products, about 350 queries on /namensschilder/, measured
 * 2026-10-23). The key holds the Germanized version, the tax display
 * setting and the language, so a change there renders it again.
 */
function ip_performance_gzd_line( $fn ) {
	$key  = 'ip_gzd_' . md5( $fn . '|' . ( defined( 'WC_GERMANIZED_VERSION' ) ? WC_GERMANIZED_VERSION : '' ) . '|' . get_option( 'woocommerce_tax_display_shop' ) . '|' . get_locale() );
	$html = get_transient( $key );
	if ( false === $html ) {
		ob_start();
		call_user_func( $fn );
		$html = (string) ob_get_clean();
		set_transient( $key, $html, 12 * HOUR_IN_SECONDS );
	}
	return (string) $html;
}

