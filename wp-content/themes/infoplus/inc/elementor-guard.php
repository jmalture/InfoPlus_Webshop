<?php
/**
 * Keeps Elementor and Elementor Pro from taking over this theme's views
 * while the plugins are still active (preview phase and the first weeks
 * after the switch). Delete this file once Elementor is deactivated for good.
 *
 * Why each part is needed (checked against Elementor Pro 3.35 on staging):
 * - Theme Builder (modules/theme-builder/classes/locations-manager.php) swaps
 *   template_include for any location that has a template with conditions
 *   (single product 2190, search 15663, news 13266) when the theme has not
 *   registered that location OR the location is marked "overwrite". We register
 *   all core locations and answer "no override", so our templates stay.
 * - The WooCommerce module forces "override" for product views through the
 *   same filter at priority 10; ours runs later.
 * - Pages saved with Elementor's page template ("elementor_header_footer",
 *   "elementor_canvas") get the plugin's template file. The last-resort
 *   template_include filter sends those views back to our hierarchy.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'elementor/theme/register_locations', function ( $manager ) {
	if ( method_exists( $manager, 'register_all_core_location' ) ) {
		$manager->register_all_core_location();
	}
} );

add_filter( 'elementor/theme/need_override_location', '__return_false', 100 );

/**
 * Template file our theme would use for the current view, ignoring any
 * Elementor page template saved on the post.
 */
function ip_theme_template_for_view() {
	if ( function_exists( 'is_woocommerce' ) && ( is_product() || is_shop() || is_product_taxonomy() ) ) {
		$wc = is_product() ? 'single-product.php' : 'archive-product.php';
		$found = locate_template( array( 'woocommerce/' . $wc, $wc ) );
		return $found ? $found : WC()->plugin_path() . '/templates/' . $wc;
	}
	if ( is_front_page() && ( $found = locate_template( array( 'front-page.php' ) ) ) ) {
		return $found;
	}
	if ( is_page() ) {
		$page   = get_queried_object();
		$custom = get_page_template_slug( $page );
		$names  = array();
		if ( $custom && ! str_starts_with( $custom, 'elementor_' ) ) {
			$names[] = $custom;
		}
		$names[] = 'page-' . $page->post_name . '.php';
		$names[] = 'page.php';
		$names[] = 'index.php';
		return locate_template( $names );
	}
	if ( is_singular() ) {
		$post = get_queried_object();
		return locate_template( array( 'single-' . $post->post_type . '.php', 'single.php', 'singular.php', 'index.php' ) );
	}
	if ( is_search() && ( $found = locate_template( array( 'search.php' ) ) ) ) {
		return $found;
	}
	return locate_template( array( 'index.php' ) );
}

add_filter( 'template_include', function ( $template ) {
	$path = wp_normalize_path( (string) $template );
	if ( ! str_contains( $path, '/plugins/elementor' ) ) {
		return $template;
	}
	$ours = ip_theme_template_for_view();
	return $ours ? $ours : $template;
}, PHP_INT_MAX );

/*
 * Elementor front-end assets are not needed by any of our templates. They are
 * dequeued, not deregistered, so a page still rendered through the_content()
 * by Elementor during the transition can ask for them again.
 */
add_action( 'wp_enqueue_scripts', function () {
	foreach ( array( 'elementor-frontend', 'elementor-pro', 'elementor-icons', 'elementor-post-5', 'e-animations', 'elementor-pro-frontend', 'elementor-sticky' ) as $handle ) {
		wp_dequeue_style( $handle );
		wp_dequeue_script( $handle );
	}
}, 100 );
