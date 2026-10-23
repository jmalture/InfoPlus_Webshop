<?php
/**
 * Category pills that filter the product grid in place (Namensschilder page,
 * shop page and the product category archives).
 *
 * Progressive enhancement:
 * - Server: every view that takes part renders the whole catalog once, in
 *   catalog order. Cards outside the current category carry `hidden`, every
 *   card carries its category slugs (data-ip-cats). The texts of the section
 *   head for "Alle" and for each pill (title, intro, breadcrumb, document
 *   title, URL) are printed once as JSON (#ip-catalog-data).
 * - The pills stay real links to the category archives, so the filter works
 *   without JS and search engines see one URL per category.
 * - assets/js/archive.js intercepts a plain click on a pill, shows the
 *   matching cards, swaps the head texts, and writes the state with
 *   history.pushState to the pill's own URL (the real category archive, or
 *   the Namensschilder page for "Alle"). Back/forward restore the state.
 *
 * URL decision: pushState to the real category URL, not ?kategorie=<slug>.
 * The category archive is already the canonical, indexable page for that
 * filter, and it renders the same layout with that pill active (this file
 * makes it render the full catalog with the other cards hidden). So a shared
 * or reloaded link opens exactly what the visitor saw, the link a crawler
 * follows is the URL the address bar shows, and no second URL per category
 * (a query variant of /namensschilder/) needs a canonical or a noindex.
 *
 * The in-place filter only runs when the whole catalog fits on one listing
 * page (IP_SHOP_PER_PAGE). With more products the pills fall back to normal
 * links, because cards of page 2 would be missing from the grid.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Context of the in-place filter for the current view, or null when the view
 * does not take part (search, page 2+, more products than fit on one page,
 * fewer than two pills, a category that has no pill).
 *
 * @return array{active:string,query:WP_Query,terms:WP_Term[]}|null
 */
function ip_catalog_context() {
	static $ctx = false;
	if ( false !== $ctx ) {
		return $ctx;
	}
	$ctx = null;
	if ( ! function_exists( 'WC' ) || ! function_exists( 'ip_shop_category_terms' ) || is_search() || is_paged() ) {
		return $ctx;
	}
	$active = '';
	if ( function_exists( 'is_product_category' ) && is_product_category() ) {
		$term = get_queried_object();
		if ( ! $term instanceof WP_Term ) {
			return $ctx;
		}
		$active = $term->slug;
	} elseif ( ! ip_shop_is_plates_page() && ! is_shop() ) {
		return $ctx;
	}

	$terms = ip_shop_category_terms();
	if ( count( $terms ) < 2 ) {
		return $ctx;
	}
	if ( '' !== $active && ! in_array( $active, wp_list_pluck( $terms, 'slug' ), true ) ) {
		return $ctx;
	}

	$args = array(
		'post_type'              => 'product',
		'post_status'            => 'publish',
		'posts_per_page'         => IP_SHOP_PER_PAGE,
		'paged'                  => 1,
		'orderby'                => array( 'menu_order' => 'ASC', 'title' => 'ASC' ), // Woo's default catalog order.
		'update_post_term_cache' => true,
	);
	if ( WC()->query ) {
		$args['tax_query'] = WC()->query->get_tax_query(); // phpcs:ignore WordPress.DB.SlowDBQuery -- Woo catalog visibility, as on the shop.
	}
	$query = new WP_Query( $args );
	if ( ! $query->have_posts() || $query->max_num_pages > 1 ) {
		return $ctx;
	}

	$ctx = array(
		'active' => $active,
		'query'  => $query,
		'terms'  => $terms,
	);
	return $ctx;
}

/** Category slugs of a product, with their parent categories (a parent pill also shows its children's products). */
function ip_catalog_card_cats( $product_id ) {
	$slugs = array();
	$terms = get_the_terms( $product_id, 'product_cat' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return $slugs;
	}
	foreach ( $terms as $term ) {
		$slugs[] = $term->slug;
		foreach ( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) as $parent_id ) {
			$parent = get_term( $parent_id, 'product_cat' );
			if ( $parent instanceof WP_Term ) {
				$slugs[] = $parent->slug;
			}
		}
	}
	return array_values( array_unique( $slugs ) );
}

/** True when a card of this product is hidden in the current filter state. */
function ip_catalog_card_hidden( $product_id ) {
	$ctx = ip_catalog_context();
	if ( ! $ctx || '' === $ctx['active'] ) {
		return false;
	}
	return ! in_array( $ctx['active'], ip_catalog_card_cats( $product_id ), true );
}

/**
 * Attributes for a card's <li>: data-ip-cats always (harmless elsewhere),
 * `hidden` when the card is outside the current category. Safe HTML.
 */
function ip_catalog_card_attrs( $product_id ) {
	$out = ' data-ip-cats="' . esc_attr( implode( ' ', ip_catalog_card_cats( $product_id ) ) ) . '"';
	if ( ip_catalog_card_hidden( $product_id ) ) {
		$out .= ' hidden';
	}
	return $out;
}

/**
 * Document title for a listing state, built like wp_get_document_title()
 * builds it for a term archive or a page: name, separator, site name.
 * Plain text (entities decoded) for document.title in JS. An SEO plugin that
 * rewrites titles is not mirrored here; the next real page load shows its
 * title again.
 */
function ip_catalog_doc_title( $name ) {
	$sep   = apply_filters( 'document_title_separator', '-' );
	$parts = apply_filters( 'document_title_parts', array(
		'title' => $name,
		'site'  => get_bloginfo( 'name', 'display' ),
	) );
	$title = implode( " $sep ", array_filter( $parts ) );
	$title = capital_P_dangit( convert_chars( wptexturize( $title ) ) );
	return html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES, 'UTF-8' );
}

/**
 * Data for archive.js: one entry per state ("_all" plus one per pill slug)
 * with everything the section head shows, plus the count labels.
 *
 * @return array
 */
function ip_catalog_data() {
	$ctx = ip_catalog_context();
	if ( ! $ctx ) {
		return array();
	}
	$plates_name = __( 'Namensschilder', 'infoplus' );
	$plates_page = get_page_by_path( apply_filters( 'ip_shop_plates_page_slug', 'namensschilder' ), OBJECT, array( 'page' ) );
	if ( $plates_page ) {
		$plates_name = get_the_title( $plates_page );
	}
	$intro  = (string) ip_setting( 'shop_intro' );
	$states = array(
		'_all' => array(
			'url'    => ip_shop_plates_url(),
			'title'  => (string) ip_setting( 'shop_heading' ),
			'accent' => (string) ip_setting( 'shop_heading_accent' ),
			'intro'  => '' === trim( $intro ) ? '' : '<p>' . esc_html( $intro ) . '</p>',
			'crumb'  => '',
			'doc'    => ip_catalog_doc_title( $plates_name ),
		),
	);
	foreach ( $ctx['terms'] as $term ) {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$states[ $term->slug ] = array(
			'url'    => $link,
			'title'  => $term->name,
			'accent' => '',
			'intro'  => trim( (string) wc_format_content( wp_kses_post( $term->description ) ) ),
			'crumb'  => $term->name,
			'doc'    => ip_catalog_doc_title( $term->name ),
		);
	}
	return array(
		'active' => $ctx['active'],
		'states' => $states,
		'home'   => array( __( 'Home', 'infoplus' ), home_url( '/' ) ),
		'plates' => array( __( 'Namensschilder', 'infoplus' ), ip_shop_plates_url() ),
		/* translators: %d: number of products. Same wording as ip_shop_count_label(). */
		'one'    => __( '%d Modell', 'infoplus' ),
		/* translators: %d: number of products. Same wording as ip_shop_count_label(). */
		'other'  => __( '%d Modelle', 'infoplus' ),
	);
}

/*
 * The Namensschilder page is a normal page: ip_template_bundle() gives it no
 * bundle and inc/shop.php adds only archive.css. The filter needs archive.js
 * there as well (category archives get it from the archive bundle).
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( function_exists( 'ip_shop_is_plates_page' ) && ip_shop_is_plates_page() && function_exists( 'ip_enqueue' ) ) {
		ip_enqueue( 'ip-archive', 'assets/js/archive.js' );
	}
}, 99 );
