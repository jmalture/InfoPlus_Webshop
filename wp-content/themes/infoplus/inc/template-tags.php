<?php
/**
 * Small render helpers shared by several templates. Functions that print
 * escape here; functions that return HTML say so in their docblock.
 */

defined( 'ABSPATH' ) || exit;

/** ID of the published page using a page template (cached per request), 0 if none. */
function ip_page_by_template( $template ) {
	static $cache = array();
	if ( ! array_key_exists( $template, $cache ) ) {
		$ids = get_posts( array(
			'post_type'              => 'page',
			'post_status'            => 'publish',
			'meta_key'               => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'             => $template, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'                 => 'ids',
			'numberposts'            => 1,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		) );
		$id = $ids ? (int) $ids[0] : 0;
		// Multilingual: the page in the current language (WPML; Polylang supports the filter too).
		$cache[ $template ] = (int) apply_filters( 'wpml_object_id', $id, 'page', true );
	}
	return $cache[ $template ];
}

/** Permalink of that page, or home if it doesn't exist yet. */
function ip_page_url( $template ) {
	$id = ip_page_by_template( $template );
	return $id ? get_permalink( $id ) : home_url( '/' );
}

/** tel: link target from a displayed number. */
function ip_tel( $number ) {
	return 'tel:' . preg_replace( '/[^\d+]/', '', (string) $number );
}

/**
 * Breadcrumb from [ label, url ] pairs; the last item is the current page.
 */
function ip_breadcrumb( $items ) {
	if ( ! $items ) {
		return;
	}
	echo '<nav class="ip-crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'infoplus' ) . '"><ol>';
	$last = count( $items ) - 1;
	foreach ( $items as $i => $item ) {
		if ( $i === $last ) {
			printf( '<li><span aria-current="page">%s</span></li>', esc_html( $item[0] ) );
		} else {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $item[1] ), esc_html( $item[0] ) );
		}
	}
	echo '</ol></nav>';
}

/**
 * Section heading: optional eyebrow, title with a caller-chosen level, and
 * an optional right-hand slot ($slot must already be escaped HTML).
 */
function ip_heading( $eyebrow, $title = '', $slot = '', $tag = 'h2' ) {
	$tag = in_array( $tag, array( 'h1', 'h2', 'h3', 'h4' ), true ) ? $tag : 'h2';
	echo '<div class="ip-heading"><div>';
	if ( $eyebrow ) {
		echo '<span class="ip-eyebrow">' . esc_html( $eyebrow ) . '</span>';
	}
	if ( $title ) {
		printf( '<%1$s class="ip-heading__title">%2$s</%1$s>', tag_escape( $tag ), esc_html( $title ) );
	}
	echo '</div>';
	if ( $slot ) {
		echo '<div class="ip-heading__meta">' . $slot . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the caller.
	}
	echo '</div>';
}

/** Prime meta, terms and thumbnails for a list of IDs before rendering cards (avoids N+1). */
function ip_prime_posts( $ids ) {
	$ids = array_filter( array_map( 'intval', (array) $ids ) );
	if ( ! $ids ) {
		return;
	}
	_prime_post_caches( $ids, true, true );
	$thumbs = array_filter( array_map( 'get_post_thumbnail_id', $ids ) );
	if ( $thumbs ) {
		_prime_post_caches( $thumbs, false, true );
	}
}
