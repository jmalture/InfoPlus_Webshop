<?php
/**
 * Inquiry drawer ("Individuelle Anfrage").
 *
 * The Infoplus Suite module "Inquiry" owns the form markup
 * (infoplus_suite_inquiry_form(), template infoplus-suite/inquiry-form.php),
 * the REST routes, storage and e-mails. The theme puts the form into a
 * drawer (template-parts/inquiry-drawer.php, a native <dialog>), styles it
 * (assets/css/inquiry.css) and drives it (assets/js/inquiry.js).
 *
 * Where: product pages (variant "product", opened from the inquiry buttons)
 * and the Kontakt page (variant "contact", opened by any
 * [data-ip-inquiry-open] element or the #anfrage hash).
 *
 * Without JavaScript the inquiry buttons keep their href: the Kontakt page
 * with ?produkt= (ip_product_inquiry_url()). This file adds #anfrage to that
 * link, so a visitor who lands there with JavaScript gets the drawer with
 * the product name.
 */

defined( 'ABSPATH' ) || exit;

/** Variant of the drawer for this request: 'product', 'contact' or '' (no drawer). */
function ip_inquiry_variant() {
	if ( ! function_exists( 'infoplus_suite_inquiry_form' ) ) {
		return '';
	}
	$variant = '';
	if ( function_exists( 'is_product' ) && is_product() ) {
		$variant = 'product';
	} elseif ( is_page( 'kontakt' ) ) {
		$variant = 'contact';
	}
	/**
	 * Filter where the inquiry drawer is printed ('' = nowhere).
	 *
	 * @param string $variant
	 */
	return (string) apply_filters( 'ip_inquiry_variant', $variant );
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! ip_inquiry_variant() ) {
		return;
	}
	ip_enqueue( 'ip-inquiry', 'assets/css/inquiry.css', array( 'ip-main' ) );
	ip_enqueue( 'ip-inquiry', 'assets/js/inquiry.js' );
}, 20 );

add_action( 'wp_footer', function () {
	$variant = ip_inquiry_variant();
	if ( ! $variant ) {
		return;
	}
	get_template_part( 'template-parts/inquiry-drawer', null, array( 'variant' => $variant ) );
}, 5 );

/** No-JS target of the inquiry buttons: the Kontakt page, opened on the drawer. */
add_filter( 'ip_product_inquiry_url', function ( $url ) {
	return function_exists( 'infoplus_suite_inquiry_form' ) && false === strpos( $url, '#' ) ? $url . '#anfrage' : $url;
} );
