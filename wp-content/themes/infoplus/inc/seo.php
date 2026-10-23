<?php
/**
 * Search and sharing basics: meta description, Open Graph tags and the
 * Product JSON-LD of product pages.
 *
 * Why the theme does this: staging and (as far as the database copy shows)
 * production have no SEO plugin, so no page had a meta description or share
 * tags (checked 2026-10-23: no Yoast, Rank Math, AIOSEO or SEOPress meta in
 * wp_postmeta). WooCommerce's own Product JSON-LD is not printed either: it
 * hangs on woocommerce_single_product_summary, which this theme does not fire
 * (inc/product.php), and for a product with 1,380 variations it would walk
 * every variation price.
 *
 * Stays out of the way: everything here switches off when an SEO plugin is
 * active (ip_seo_plugin_active()) or through the filter ip_seo_enabled.
 * WordPress core keeps the title (title-tag), rel=canonical, robots and the
 * sitemap (wp-sitemap.xml).
 *
 * Description sources, first match wins (filter ip_seo_description):
 * - front page: Settings > General tagline, else the hero text (field hero_text);
 * - product: short description, else the description;
 * - other singular: the manual excerpt, else the content;
 * - product category or tag: the term description.
 * Text is stripped of tags and shortcodes and cut at a word to 160 characters.
 */

defined( 'ABSPATH' ) || exit;

/** True when a known SEO plugin handles descriptions and share tags. */
function ip_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' )
		|| defined( 'SEOPRESS_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' );
}

/** Theme SEO output on? */
function ip_seo_enabled() {
	return (bool) apply_filters( 'ip_seo_enabled', ! ip_seo_plugin_active() );
}

/** Plain text, cut at a word boundary to $max characters. */
function ip_seo_clean( $text, $max = 160 ) {
	$text = wp_strip_all_tags( strip_shortcodes( (string) $text ), true );
	$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $max ) {
		$cut  = mb_substr( $text, 0, $max - 1 );
		$last = mb_strrpos( $cut, ' ' );
		$text = rtrim( false !== $last && $last > $max * 0.6 ? mb_substr( $cut, 0, $last ) : $cut, " ,.;:-" ) . '…';
	}
	return $text;
}

/** Meta description of the current view, '' when there is no source text. */
function ip_seo_description() {
	$text = '';
	if ( is_front_page() ) {
		$text = (string) get_bloginfo( 'description' );
		if ( '' === $text && function_exists( 'ip_field' ) ) {
			$text = (string) ip_field( 'hero_text', (int) get_option( 'page_on_front' ) );
		}
	} elseif ( function_exists( 'is_product' ) && is_product() ) {
		$product = wc_get_product( get_queried_object_id() );
		if ( $product ) {
			$text = $product->get_short_description() ? $product->get_short_description() : $product->get_description();
		}
	} elseif ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			$text = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$text = term_description();
	}
	return (string) apply_filters( 'ip_seo_description', ip_seo_clean( $text ) );
}

/** Share image URL: the product or post image, else the custom logo. */
function ip_seo_image() {
	$id = 0;
	if ( is_singular() ) {
		$id = (int) get_post_thumbnail_id( get_queried_object_id() );
	}
	if ( ! $id && is_front_page() && function_exists( 'ip_field' ) ) {
		$id = (int) ip_field( 'hero_image', (int) get_option( 'page_on_front' ), 0 );
	}
	if ( ! $id ) {
		$id = (int) get_theme_mod( 'custom_logo' );
	}
	$url = $id ? wp_get_attachment_image_url( $id, 'large' ) : '';
	return (string) apply_filters( 'ip_seo_image', $url ? $url : '' );
}

/** Canonical URL of the view, matching what core prints for singular views. */
function ip_seo_url() {
	if ( is_singular() ) {
		$url = wp_get_canonical_url( get_queried_object_id() );
		return $url ? $url : (string) get_permalink( get_queried_object_id() );
	}
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	if ( is_category() || is_tag() || is_tax() ) {
		$link = get_term_link( get_queried_object() );
		return is_wp_error( $link ) ? '' : $link;
	}
	return '';
}

add_action( 'wp_head', function () {
	if ( ! ip_seo_enabled() || is_404() || is_search() ) {
		return;
	}
	$desc  = ip_seo_description();
	$url   = ip_seo_url();
	$image = ip_seo_image();
	$type  = ( function_exists( 'is_product' ) && is_product() ) ? 'product' : ( is_singular( 'post' ) ? 'article' : 'website' );

	if ( '' !== $desc ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	$og = array(
		'og:locale'    => 'de_DE',
		'og:type'      => $type,
		'og:site_name' => get_bloginfo( 'name' ),
		'og:title'     => wp_get_document_title(),
		'og:description' => $desc,
		'og:url'       => $url,
		'og:image'     => $image,
	);
	foreach ( $og as $property => $content ) {
		if ( '' !== (string) $content ) {
			echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '">' . "\n";
		}
	}
	echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
}, 2 );

/**
 * Product JSON-LD. Offers come from the Berater catalog of the Suite (one
 * entry per model with its lowest display price, the same "ab" prices the
 * Berater and the front page show), so no variation is loaded here.
 */
function ip_seo_product_schema( $product ) {
	$data = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Product',
		'name'        => html_entity_decode( $product->get_name(), ENT_QUOTES, 'UTF-8' ),
		'url'         => (string) get_permalink( $product->get_id() ),
		'description' => ip_seo_description(),
		'brand'       => array( '@type' => 'Brand', 'name' => get_bloginfo( 'name' ) ),
	);
	if ( $product->get_sku() ) {
		$data['sku'] = $product->get_sku();
	}
	$img = wp_get_attachment_image_url( (int) $product->get_image_id(), 'large' );
	if ( $img ) {
		$data['image'] = $img;
	}

	$prices = array();
	$offers = 0;
	if ( function_exists( 'infoplus_suite_finder_data' ) ) {
		$catalog = infoplus_suite_finder_data();
		foreach ( (array) ( $catalog['candidates'] ?? array() ) as $c ) {
			if ( (int) ( $c['pid'] ?? 0 ) === $product->get_id() && (float) ( $c['base'] ?? 0 ) > 0 ) {
				$prices[] = (float) $c['base'];
				$offers  += max( 1, (int) ( $c['variants'] ?? 1 ) );
			}
		}
	}
	if ( $prices ) {
		$data['offers'] = array(
			'@type'         => 'AggregateOffer',
			'priceCurrency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR',
			'lowPrice'      => wc_format_decimal( min( $prices ), 2 ),
			'highPrice'     => wc_format_decimal( max( $prices ), 2 ),
			'offerCount'    => $offers,
			'url'           => $data['url'],
		);
	}
	return (array) apply_filters( 'ip_seo_product_schema', $data, $product );
}

add_action( 'wp_footer', function () {
	if ( ! ip_seo_enabled() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product ) {
		return;
	}
	$data = ip_seo_product_schema( $product );
	if ( $data ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . '</script>' . "\n";
	}
}, 20 );
