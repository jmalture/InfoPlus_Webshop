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
		'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18"/>',
		// Content pages (inc/content.php, the "Symbol" select fields).
		'tag'     => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.3"/><path d="M15 9l-6 6"/>',
		'truck'   => '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
		'layers'  => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
		'award'   => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14L7 21l5-3 5 3-1.5-7"/>',
		'pen'     => '<path d="M4 20l1-5L16 4l4 4L9 19z"/><path d="M14 6l4 4"/>',
		'zap'     => '<path d="M13 3L4 14h7l-1 7 9-11h-7z"/>',
		'check'   => '<path d="M5 12.5l4.5 4.5L19 7"/>',
		'printer' => '<path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M7 14h10v7H7z"/>',
		'pin'     => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
		'help'    => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6"/><path d="M12 17h.01"/>',
		'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
		// Account area (inc/account.php).
		'grid'     => '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
		'package'  => '<path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z"/><path d="M4 7.5l8 4.5 8-4.5M12 12v9"/>',
		'download' => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
		'upload'   => '<path d="M12 20V9M7 14l5-5 5 5M5 4h14"/>',
		'logout'   => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 8l-4 4 4 4M6 12h10"/>',
		'card'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
		'refresh'  => '<path d="M20 11a8 8 0 0 0-14.7-3.9M4 4v4h4M4 13a8 8 0 0 0 14.7 3.9M20 20v-4h-4"/>',
		'euro'     => '<path d="M17 6.5A7 7 0 1 0 17 17.5"/><path d="M4 10h9M4 14h9"/>',
		'file'     => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	$a11y = $label
		? 'role="img" aria-label="' . esc_attr( $label ) . '"'
		: 'aria-hidden="true" focusable="false"';
	return '<svg class="ip-icon ip-icon--' . esc_attr( $name ) . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" ' . $a11y . '>' . $paths[ $name ] . '</svg>';
}
