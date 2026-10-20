<?php
/**
 * Navigation helpers shared by header, mobile menu and footer.
 */

defined( 'ABSPATH' ) || exit;

/** Render a menu location, or nothing when no menu is assigned. */
function ip_menu( $location, $args = array() ) {
	if ( ! has_nav_menu( $location ) ) {
		return;
	}
	wp_nav_menu( array_merge( array(
		'theme_location' => $location,
		'container'      => false,
		'fallback_cb'    => false,
		'depth'          => 1,
	), $args ) );
}

/** Account URL: WooCommerce My Account when set and published, else the current /account/ page. */
function ip_account_url() {
	if ( function_exists( 'wc_get_page_id' ) ) {
		$id = wc_get_page_id( 'myaccount' );
		if ( $id > 0 && 'publish' === get_post_status( $id ) ) {
			return get_permalink( $id );
		}
	}
	return home_url( is_user_logged_in() ? '/account/' : '/log-in/' );
}
