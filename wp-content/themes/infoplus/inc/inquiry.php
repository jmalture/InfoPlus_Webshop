<?php
/**
 * Inquiry form ("Individuelle Anfrage") and the Kontakt page form.
 *
 * The Infoplus Suite module "Inquiry" owns the form markup
 * (infoplus_suite_inquiry_form(), template infoplus-suite/inquiry-form.php),
 * the REST routes, storage and e-mails. The theme places the form, styles it
 * (assets/css/inquiry.css) and drives it (assets/js/inquiry.js).
 *
 * Two placements, never both on one page:
 * - Product pages: variant "product" in a drawer (template-parts/inquiry-drawer.php,
 *   a native <dialog> printed in wp_footer), opened from the inquiry buttons.
 * - Kontakt page: variant "contact" inline in the page, in the form card of
 *   page-kontakt.php (template-parts/inquiry-inline.php, printed on the
 *   action "infoplus_theme_contact_form"). No drawer there. Inquiry triggers
 *   on that page ([data-ip-inquiry-open], links to #anfrage) scroll to the
 *   form instead of opening a drawer.
 *
 * Without JavaScript the inquiry buttons keep their href: the Kontakt page
 * with ?produkt= (ip_product_inquiry_url()). This file adds #anfrage to that
 * link; the inline form section has id="anfrage", so the browser lands on it.
 * The product named in ?produkt= is looked up (exact title of a published
 * product) and shown as context in the form.
 */

defined( 'ABSPATH' ) || exit;

/** Variant of the inquiry form for this request: 'product', 'contact' or '' (no form). */
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
	 * Filter where the inquiry form is printed ('' = nowhere).
	 *
	 * @param string $variant
	 */
	return (string) apply_filters( 'ip_inquiry_variant', $variant );
}

/**
 * Placement of the form: 'inline' (Kontakt page), 'drawer' (everywhere else
 * the variant is set) or ''. Filter ip_inquiry_placement to change it.
 */
function ip_inquiry_placement() {
	$variant = ip_inquiry_variant();
	if ( ! $variant ) {
		return '';
	}
	$placement = ( 'contact' === $variant && is_page( 'kontakt' ) ) ? 'inline' : 'drawer';
	/**
	 * @param string $placement 'inline' or 'drawer'.
	 * @param string $variant
	 */
	$placement = (string) apply_filters( 'ip_inquiry_placement', $placement, $variant );
	return in_array( $placement, array( 'inline', 'drawer' ), true ) ? $placement : '';
}

/**
 * Prints the form once per request. A second call (a template that also
 * fires the hook, a filter that adds a drawer) prints nothing, so the page
 * never has two forms with the same IDs.
 *
 * @param string $placement 'inline' or 'drawer'.
 */
function ip_inquiry_render( $placement ) {
	static $done = false;
	if ( $done || ! function_exists( 'infoplus_suite_inquiry_form' ) ) {
		return;
	}
	$done    = true;
	$variant = ip_inquiry_variant();
	if ( 'inline' === $placement ) {
		get_template_part( 'template-parts/inquiry-inline', null, array(
			'variant' => $variant,
			'product' => 'contact' === $variant ? ip_inquiry_requested_product() : null,
			// Heading from the Kontakt field group ("Formular-Titel"), else the default in the template part.
			'title'   => function_exists( 'ip_field' ) ? (string) ip_field( 'form_title', (int) get_queried_object_id(), '' ) : '',
		) );
	} else {
		get_template_part( 'template-parts/inquiry-drawer', null, array( 'variant' => $variant ) );
	}
}

/**
 * The product named in ?produkt= (no-JS link from a product page), or null.
 * Exact title of a published product; anything else stays plain text for
 * the script (shown as the name only, stored unverified by the Suite).
 *
 * @return WC_Product|null
 */
function ip_inquiry_requested_product() {
	$name = ip_inquiry_requested_name();
	if ( '' === $name || ! function_exists( 'wc_get_product' ) ) {
		return null;
	}
	$ids = get_posts( array(
		'post_type'              => 'product',
		'post_status'            => 'publish',
		'title'                  => $name,
		'posts_per_page'         => 1,
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
	) );
	$product = $ids ? wc_get_product( (int) $ids[0] ) : null;
	return $product instanceof WC_Product ? $product : null;
}

/** The ?produkt= value, sanitised and cut to 200 characters ('' when absent). */
function ip_inquiry_requested_name() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display context.
	$raw = isset( $_GET['produkt'] ) && is_string( $_GET['produkt'] ) ? wp_unslash( $_GET['produkt'] ) : '';
	$name = trim( sanitize_text_field( $raw ) );
	return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 200 ) : substr( $name, 0, 200 );
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! ip_inquiry_placement() ) {
		return;
	}
	ip_enqueue( 'ip-inquiry', 'assets/css/inquiry.css', array( 'ip-main' ) );
	ip_enqueue( 'ip-inquiry', 'assets/js/inquiry.js' );
}, 20 );

/* Drawer: product pages. */
add_action( 'wp_footer', function () {
	if ( 'drawer' === ip_inquiry_placement() ) {
		ip_inquiry_render( 'drawer' );
	}
}, 5 );

/* Inline: the form slot of page-kontakt.php. */
add_action( 'infoplus_theme_contact_form', function () {
	if ( 'inline' === ip_inquiry_placement() ) {
		ip_inquiry_render( 'inline' );
	}
} );

/** No-JS target of the inquiry buttons: the Kontakt page, at the form. */
add_filter( 'ip_product_inquiry_url', function ( $url ) {
	return function_exists( 'infoplus_suite_inquiry_form' ) && false === strpos( $url, '#' ) ? $url . '#anfrage' : $url;
} );
