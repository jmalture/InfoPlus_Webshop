<?php
/**
 * Account quick menu of the header (template-parts/account-menu.php).
 *
 * Desktop (1101 px and up): the account icon is a disclosure button; the
 * panel opens on click or tap (all pointers) and on mouse hover with the
 * same intent delay as the mega menu (assets/js/site.js). Below 1101 px the
 * header has no account icon: the same menu sits in the mobile drawer as
 * a "Mein Konto" / "Anmelden" item with a submenu toggle, no hover.
 *
 * Logged in: avatar, name, company, status pill (the account helpers of
 * inc/account.php), the last three orders (number, date, status, total, link
 * to the order view, invoice link when PDF Invoices & Packing Slips offers
 * one through the WooCommerce account actions), "Alle Bestellungen", the
 * account areas and "Abmelden". Guests: Anmelden (back to this page),
 * Konto erstellen (only when WooCommerce registration is on), Passwort
 * vergessen.
 *
 * Cost: one wc_get_orders() query per request, only for logged-in users,
 * cached in a static for the desktop panel and the drawer. No REST call.
 *
 * Page cache: user-specific markup is printed only for logged-in users,
 * who are not served from the guest page cache. The guest version contains
 * nothing user-specific except the redirect_to of the current URL, which is
 * the same for every guest of that URL.
 */

defined( 'ABSPATH' ) || exit;

/** True when the quick menu is used (filter ip_account_menu_enabled). */
function ip_account_menu_enabled() {
	return (bool) apply_filters( 'ip_account_menu_enabled', function_exists( 'WC' ) && function_exists( 'wc_get_account_endpoint_url' ) );
}

/**
 * Everything the menu shows, built once per request.
 *
 * @return array<string,mixed>
 */
function ip_account_menu_data() {
	static $data = null;
	if ( null !== $data ) {
		return $data;
	}
	if ( ! is_user_logged_in() ) {
		$here     = home_url( add_query_arg( array() ) );
		$login    = function_exists( 'ip_account_login_url' ) ? ip_account_login_url( $here ) : wp_login_url( $here );
		$register = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
		$data     = array(
			'logged_in' => false,
			'login'     => $login,
			// The registration card of woocommerce/myaccount/form-login.php.
			'register'  => $register ? ( function_exists( 'ip_account_login_url' ) ? ip_account_login_url() : wc_get_page_permalink( 'myaccount' ) ) . '#ip-register-title' : '',
			'lost'      => wc_lostpassword_url(),
		);
		return $data;
	}

	$uid  = get_current_user_id();
	$user = wp_get_current_user();
	$name = trim( $user->first_name . ' ' . $user->last_name );

	$orders = wc_get_orders( array(
		'customer' => $uid,
		'limit'    => (int) apply_filters( 'ip_account_menu_orders', 3 ),
		'orderby'  => 'date',
		'order'    => 'DESC',
		'type'     => 'shop_order',
	) );

	$rows = array();
	foreach ( $orders as $order ) {
		if ( ! $order instanceof WC_Order ) {
			continue;
		}
		$actions = function_exists( 'ip_account_order_actions' ) ? ip_account_order_actions( $order ) : array();
		$date    = $order->get_date_created();
		$rows[]  = array(
			'order'   => $order,
			'number'  => $order->get_order_number(),
			'url'     => $order->get_view_order_url(),
			'date'    => $date ? wc_format_datetime( $date ) : '',
			'iso'     => $date ? $date->format( 'c' ) : '',
			'total'   => $order->get_formatted_order_total(),
			'invoice' => isset( $actions['invoice'] ) ? $actions['invoice'] : null,
		);
	}

	$links = array();
	foreach ( array(
		'dashboard'    => __( 'Übersicht', 'infoplus' ),
		'orders'       => __( 'Bestellungen', 'infoplus' ),
		'edit-address' => __( 'Adressen', 'infoplus' ),
		'edit-account' => __( 'Kontodaten', 'infoplus' ),
	) as $endpoint => $label ) {
		$links[] = array(
			'url'   => wc_get_account_endpoint_url( $endpoint ),
			'label' => $label,
			'icon'  => function_exists( 'ip_account_menu_icon' ) ? ip_account_menu_icon( $endpoint ) : 'chevron',
		);
	}

	$data = array(
		'logged_in' => true,
		'uid'       => $uid,
		'name'      => $name ? $name : $user->display_name,
		'company'   => (string) get_user_meta( $uid, 'billing_company', true ),
		'status'    => function_exists( 'ip_account_status_label' ) ? ip_account_status_label( $uid ) : '',
		'orders'    => $rows,
		'all'       => wc_get_account_endpoint_url( 'orders' ),
		'links'     => $links,
		'logout'    => wc_logout_url(),
	);
	return $data;
}

/*
 * The Suite's Elementor-era dropdown (UserDashboard, menu-dropdown.js/.css)
 * looks for the Elementor widget #log-in, which this theme does not have; the
 * quick menu replaces it. Not loaded under this theme.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ip_account_menu_enabled() ) {
		wp_dequeue_script( 'infoplus-menu-dropdown' );
		wp_dequeue_style( 'infoplus-menu-dropdown' );
	}
}, 100 );
