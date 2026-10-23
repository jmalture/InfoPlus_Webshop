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

/*
 * Until the menus are assigned under Appearance > Menus, use the existing
 * WordPress menus by name. This needs no database write, so the theme works
 * in the admin preview before it is activated. An assignment saved in the
 * admin always wins.
 */
add_filter( 'theme_mod_nav_menu_locations', function ( $locations ) {
	$locations = is_array( $locations ) ? $locations : array();
	$by_name   = array(
		'mobile'       => 'Mobile Menu',
		'footer-info'  => 'Footer_Informationen',
		'footer-legal' => 'Footer_Rechtliches',
	);
	foreach ( $by_name as $location => $name ) {
		if ( empty( $locations[ $location ] ) ) {
			$menu = wp_get_nav_menu_object( $name );
			if ( $menu ) {
				$locations[ $location ] = (int) $menu->term_id;
			}
		}
	}
	return $locations;
} );
