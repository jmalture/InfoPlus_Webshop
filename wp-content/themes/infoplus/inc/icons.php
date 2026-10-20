<?php
/**
 * Inline SVG icons (no icon font, no Font Awesome). Outline style, 24 px grid,
 * stroke = currentColor. Add icons here when a template needs one.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Print-ready SVG markup for an icon.
 *
 * @param string $name  Icon key.
 * @param string $label Accessible name; empty = decorative (aria-hidden).
 */
function ip_icon( $name, $label = '' ) {
	$paths = array(
		'menu'    => '<path d="M3 6h18M3 12h18M3 18h18"/>',
		'close'   => '<path d="M6 6l12 12M18 6L6 18"/>',
		'search'  => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
		'cart'    => '<path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6.2"/><circle cx="10" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/>',
		'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
		'phone'   => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
		'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'chevron' => '<path d="M9 6l6 6-6 6"/>',
		'chat'    => '<path d="M4 5h16v11H8l-4 4z"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	$a11y = $label
		? 'role="img" aria-label="' . esc_attr( $label ) . '"'
		: 'aria-hidden="true" focusable="false"';
	return '<svg class="ip-icon ip-icon--' . esc_attr( $name ) . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" ' . $a11y . '>' . $paths[ $name ] . '</svg>';
}
