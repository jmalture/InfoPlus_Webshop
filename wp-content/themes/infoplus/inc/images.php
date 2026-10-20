<?php
/**
 * Image sizes and the responsive image helper.
 *
 * Only widths the design renders are generated; heights follow the original
 * (no hard crops) and CSS object-fit/aspect-ratio frames each context. Every
 * size then joins one srcset and the client can upload any aspect ratio.
 * Adjust the widths to the design's layout. After changing sizes on a site
 * with content: wp media regenerate.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', function () {
	add_image_size( 'ip-480', 480, 0 );
	add_image_size( 'ip-800', 800, 0 );
	add_image_size( 'ip-1200', 1200, 0 );
	add_image_size( 'ip-1600', 1600, 0 );
	add_image_size( 'ip-2400', 2400, 0 );
	add_image_size( 'ip-og', 1200, 630, true ); // Link previews only.
} );

/** Core sizes the design never uses. thumbnail and medium stay: the admin uses them. */
add_filter( 'intermediate_image_sizes_advanced', function ( $sizes ) {
	foreach ( array( 'medium_large', 'large', '1536x1536', '2048x2048' ) as $name ) {
		unset( $sizes[ $name ] );
	}
	return $sizes;
} );

/** Theme sizes are for templates only; keep them out of the editor's size dropdown. */
add_filter( 'image_size_names_choose', function ( $sizes ) {
	return array_intersect_key( $sizes, array_flip( array( 'thumbnail', 'medium', 'full' ) ) );
} );

add_filter( 'max_srcset_image_width', fn() => 2400 );

/**
 * Responsive <img> by attachment ID. Returns safe HTML ('' when no image).
 *
 * @param int    $id    Attachment ID.
 * @param string $sizes The rendered width in THIS layout, e.g.
 *                      '(min-width: 1101px) 25vw, (min-width: 561px) 50vw, 100vw'.
 * @param array  $attr  Extra attributes. For the one LCP image per template pass
 *                      array( 'loading' => 'eager', 'fetchpriority' => 'high' ).
 *                      Pass 'alt' => '' for decorative images.
 * @param string $size  Fallback src size.
 */
function ip_img( $id, $sizes, $attr = array(), $size = 'ip-1200' ) {
	$id = (int) $id;
	if ( ! $id ) {
		return '';
	}
	$attr = array_merge( array( 'sizes' => $sizes, 'loading' => 'lazy', 'decoding' => 'async' ), $attr );
	if ( ! isset( $attr['alt'] ) ) {
		$attr['alt'] = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
	}
	return wp_get_attachment_image( $id, $size, false, $attr );
}
