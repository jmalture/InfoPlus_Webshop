<?php
/**
 * Front-end assets: one shared stylesheet and script, one bundle per view
 * (loaded only there), deferred JS, font preload for the first screen.
 *
 * jQuery stays: WooCommerce (variation form, cart fragments) and the Infoplus
 * Suite components depend on it. Theme code itself is vanilla JS.
 */

defined( 'ABSPATH' ) || exit;

/** Theme version + file mtime, so a deploy busts browser and LiteSpeed caches. */
function ip_asset_version( $rel ) {
	$path = IP_DIR . '/' . ltrim( $rel, '/' );
	return file_exists( $path ) ? IP_VERSION . '.' . filemtime( $path ) : IP_VERSION;
}

/**
 * Bundle for the current view: assets/css/{name}.css + assets/js/{name}.js.
 * Names follow the rows of the rebuild map.
 */
function ip_template_bundle() {
	if ( is_front_page() ) {
		return 'home';
	}
	if ( function_exists( 'is_product' ) && is_product() ) {
		return 'product';
	}
	if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) {
		return 'archive';
	}
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
		return 'checkout';
	}
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		return 'account';
	}
	if ( is_search() ) {
		return 'archive';
	}
	if ( is_singular( 'news' ) ) {
		return 'news';
	}
	return '';
}

/** Enqueue a theme file if it exists, with the cache-busting version. */
function ip_enqueue( $handle, $rel, $deps = array() ) {
	if ( ! file_exists( IP_DIR . '/' . $rel ) ) {
		return;
	}
	if ( str_ends_with( $rel, '.css' ) ) {
		wp_enqueue_style( $handle, IP_URI . '/' . $rel, $deps, ip_asset_version( $rel ) );
	} else {
		wp_enqueue_script( $handle, IP_URI . '/' . $rel, $deps, ip_asset_version( $rel ), array( 'strategy' => 'defer', 'in_footer' => true ) );
	}
}

add_action( 'wp_enqueue_scripts', function () {
	ip_enqueue( 'ip-main', 'assets/css/main.css' );
	ip_enqueue( 'ip-header', 'assets/css/header.css', array( 'ip-main' ) );
	ip_enqueue( 'ip-footer', 'assets/css/footer.css', array( 'ip-main' ) );
	ip_enqueue( 'ip-site', 'assets/js/site.js' );
	ip_enqueue( 'ip-contact-panel', 'assets/js/contact-panel.js' );

	$bundle = ip_template_bundle();
	if ( $bundle ) {
		ip_enqueue( "ip-{$bundle}", "assets/css/{$bundle}.css", array( 'ip-main' ) );
		ip_enqueue( "ip-{$bundle}", "assets/js/{$bundle}.js" );
	}

	wp_deregister_script( 'wp-embed' );
} );

/** Mark JS support before first paint, so enhanced layouts never shift. */
add_action( 'wp_head', function () {
	wp_print_inline_script_tag( "document.documentElement.classList.add('js');" );
}, 1 );

/** Preload the one face every first screen renders. */
add_action( 'wp_head', function () {
	if ( ! file_exists( IP_DIR . '/assets/fonts/archivo-narrow-var.woff2' ) ) {
		return;
	}
	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
		esc_url( IP_URI . '/assets/fonts/archivo-narrow-var.woff2' )
	);
}, 2 );

/** Speculative loading (WordPress 6.8+): prerender likely next pages. */
add_filter( 'wp_speculation_rules_configuration', function ( $config ) {
	if ( is_array( $config ) ) {
		$config['mode']      = 'prerender';
		$config['eagerness'] = 'moderate';
	}
	return $config;
} );
