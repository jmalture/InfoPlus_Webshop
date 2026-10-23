<?php
/**
 * Customer account: login page, WooCommerce "My account" at /account/, old
 * order URLs, the order confirmation (order received) and the helpers their
 * templates share.
 *
 * Routing (see ACCOUNT.md in the repository root):
 * - The account is WooCommerce My Account. Its endpoints live under the
 *   account page: /account/orders/, /account/view-order/{id}/,
 *   /account/edit-address/, /account/edit-account/, /account/lost-password/.
 * - The account page is the page set under WooCommerce > Settings > Advanced
 *   when that page is published. On staging that setting still points to the
 *   draft page 29 "my-account"; then the published page "account" (12466) is
 *   used instead (filter woocommerce_get_myaccount_page_id below). Once the
 *   setting points to 12466 the filter does nothing.
 * - /log-in/ (page 12381) shows the WooCommerce login and registration forms.
 * - /order/{n}/ and /order/?order_id={n} (Infoplus Suite URLs) redirect to
 *   /account/view-order/{n}/.
 * - Both pages render through wc-account.php; Elementor content on them is
 *   not used.
 *
 * Templates: wc-account.php, woocommerce/myaccount/*, woocommerce/order/*,
 * woocommerce/checkout/thankyou.php. Styles: assets/css/account.css (bundle
 * "account"), assets/css/order.css (view-order and order received).
 *
 * Everything that needs WooCommerce is guarded, so the theme still renders
 * while WooCommerce is inactive.
 */

defined( 'ABSPATH' ) || exit;

/* ── Pages ──────────────────────────────────────────────────────────────── */

/** ID of a published page by path, 0 if none. Cached per request. */
function ip_account_published_page( $path ) {
	static $cache = array();
	if ( ! array_key_exists( $path, $cache ) ) {
		$page           = $path ? get_page_by_path( $path, OBJECT, 'page' ) : null;
		$cache[ $path ] = ( $page && 'publish' === $page->post_status ) ? (int) $page->ID : 0;
	}
	return $cache[ $path ];
}

/** Slug of the page that holds the account when the WooCommerce setting is unusable. */
function ip_account_fallback_slug() {
	return (string) apply_filters( 'ip_account_page_slug', 'account' );
}

/** ID of the login page (/log-in/), 0 when it does not exist or is not published. */
function ip_account_login_page_id() {
	return ip_account_published_page( (string) apply_filters( 'ip_account_login_page_slug', 'log-in' ) );
}

/*
 * The WooCommerce "My account" setting (woocommerce_myaccount_page_id) wins
 * when it points to a published page. When it points to a draft or a missing
 * page (staging today: draft 29), the published page "account" becomes the
 * account page, so endpoints, is_account_page(), lost password links and the
 * header link all use /account/. No database write.
 */
add_filter( 'woocommerce_get_myaccount_page_id', function ( $id ) {
	$id = (int) $id;
	if ( $id > 0 && 'publish' === get_post_status( $id ) ) {
		return $id;
	}
	$fallback = ip_account_published_page( ip_account_fallback_slug() );
	return $fallback ? $fallback : $id;
} );

/** True on the login page. */
function ip_account_is_login_page() {
	$id = ip_account_login_page_id();
	return $id && is_page( $id );
}

/** True on any view rendered by wc-account.php (login page, account page and its endpoints). */
function ip_account_is_view() {
	if ( ip_account_is_login_page() ) {
		return true;
	}
	return function_exists( 'is_account_page' ) && is_account_page();
}

/** Account page URL ('' without WooCommerce or account page). */
function ip_account_page_url() {
	if ( ! function_exists( 'wc_get_page_id' ) || wc_get_page_id( 'myaccount' ) < 1 ) {
		return '';
	}
	return wc_get_page_permalink( 'myaccount' );
}

/**
 * Where to send a visitor who has to log in. The login page with a
 * redirect_to back to $return, or the account page (WooCommerce shows its
 * login form there) when no login page exists.
 */
function ip_account_login_url( $return = '' ) {
	$login = ip_account_login_page_id();
	$url   = $login ? get_permalink( $login ) : ip_account_page_url();
	if ( ! $url ) {
		return wp_login_url( $return );
	}
	return $return ? add_query_arg( 'redirect_to', rawurlencode( $return ), $url ) : $url;
}

/**
 * A redirect target from user input, or '' when it is not a URL on this
 * site or points back to the login page.
 */
function ip_account_safe_redirect( $url ) {
	$url = wp_validate_redirect( esc_url_raw( (string) $url ), '' );
	if ( '' === $url ) {
		return '';
	}
	$login = ip_account_login_page_id();
	if ( $login ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( untrailingslashit( $path ) === untrailingslashit( (string) wp_parse_url( get_permalink( $login ), PHP_URL_PATH ) ) ) {
			return '';
		}
	}
	return $url;
}

/** Target after login from the login form: ?redirect_to when safe, else the account. */
function ip_account_login_target() {
	$asked = isset( $_GET['redirect_to'] ) ? ip_account_safe_redirect( wp_unslash( $_GET['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- read-only redirect target, validated.
	return $asked ? $asked : ip_account_page_url();
}

/** False switches the theme's account handling off (pages then render as before). */
function ip_account_enabled() {
	return function_exists( 'WC' ) && (bool) apply_filters( 'ip_account_enabled', true );
}

/* ── Infoplus Suite hooks the theme replaces ────────────────────────────── */

/**
 * Remove callbacks of the Infoplus Suite plugin from a hook by method name.
 * The Suite registers them on object instances, so remove_action() needs the
 * instance; this finds it in $wp_filter (same approach as the cart styles in
 * inc/shop.php). A no-op when the Suite is inactive.
 *
 * @param string   $hook    Hook name.
 * @param string[] $methods Method names.
 */
function ip_unhook_suite( $hook, $methods ) {
	global $wp_filter;
	if ( empty( $wp_filter[ $hook ] ) ) {
		return;
	}
	foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$fn = $callback['function'];
			if ( is_array( $fn ) && is_object( $fn[0] ) && in_array( $fn[1], (array) $methods, true )
				&& str_starts_with( get_class( $fn[0] ), 'Infoplus\\Suite\\' ) ) {
				remove_action( $hook, $fn, $priority );
			}
		}
	}
}

/*
 * Infoplus Suite UserDashboard::redirectGuestUsers sends every guest request
 * whose path starts with /account to /log-in/, without a way back. That also
 * blocks /account/lost-password/ and the password reset link. The theme
 * replaces it with ip_account_redirects() below, which keeps lost password
 * open and adds redirect_to. Runs on "wp", before template_redirect.
 */
add_action( 'wp', function () {
	if ( ip_account_enabled() ) {
		ip_unhook_suite( 'template_redirect', array( 'redirectGuestUsers' ) );
	}
} );

/* ── Redirects ──────────────────────────────────────────────────────────── */

add_action( 'template_redirect', 'ip_account_redirects', 5 );

/**
 * - Old Suite order URLs to the WooCommerce order view.
 * - Guests on the account (except lost password) to the login page, with a
 *   way back.
 * - Logged-in users on the login page to where they wanted to go.
 * Priority 5: before the Suite's own login page redirect (10).
 */
function ip_account_redirects() {
	if ( ! ip_account_enabled() || is_admin() ) {
		return;
	}
	$account = ip_account_page_url();
	if ( ! $account ) {
		return;
	}

	// /order/{n}/ (Suite rewrite, query var infoplus_order_id) and /order/?order_id={n}.
	$old_id = absint( get_query_var( 'infoplus_order_id' ) );
	$order_page = (string) apply_filters( 'ip_account_order_page_slug', 'order' );
	if ( $old_id || ( $order_page && is_page( $order_page ) ) ) {
		if ( ! $old_id && isset( $_GET['order_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- read-only.
			$old_id = absint( $_GET['order_id'] ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		$target = $old_id ? wc_get_endpoint_url( 'view-order', (string) $old_id, $account ) : wc_get_endpoint_url( 'orders', '', $account );
		wp_safe_redirect( $target, 302, 'Infoplus theme' );
		exit;
	}

	if ( ip_account_is_login_page() && is_user_logged_in() ) {
		wp_safe_redirect( ip_account_login_target(), 302, 'Infoplus theme' );
		exit;
	}

	if ( ! is_user_logged_in() && is_account_page() && ip_account_login_page_id() ) {
		// WooCommerce serves these to guests itself.
		foreach ( array( 'lost-password', 'customer-logout' ) as $open ) {
			if ( is_wc_endpoint_url( $open ) ) {
				return;
			}
		}
		$here = home_url( add_query_arg( array() ) );
		wp_safe_redirect( ip_account_login_url( $here ), 302, 'Infoplus theme' );
		exit;
	}
}

/*
 * After login. The Suite forces /account/ for every login (filters at 999).
 * The theme's login form posts the target in "redirect" (validated here), so
 * a visitor sent to log in from /account/view-order/123/ comes back there.
 * Without a posted target the Suite's choice stands.
 */
add_filter( 'woocommerce_login_redirect', 'ip_account_posted_redirect', 1000 );
add_filter( 'woocommerce_registration_redirect', 'ip_account_posted_redirect', 1000 );

/** Posted redirect target when it is safe, else the given one. */
function ip_account_posted_redirect( $redirect ) {
	$posted = isset( $_POST['redirect'] ) ? ip_account_safe_redirect( wp_unslash( $_POST['redirect'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- WooCommerce checked the form nonce; value validated.
	return $posted ? $posted : $redirect;
}

/*
 * Registration on the login page. The old Essential Addons form created
 * users with the role "sales" (shown as "Händler"); WooCommerce would create
 * "customer". Keep "sales" when that role exists, only for the registration
 * form (not for accounts created at checkout). Filter ip_account_register_role
 * ('' keeps WooCommerce's role).
 */
add_filter( 'woocommerce_new_customer_data', function ( $data ) {
	if ( empty( $_POST['woocommerce-register-nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- presence check only; WooCommerce verifies it.
		return $data;
	}
	$role = (string) apply_filters( 'ip_account_register_role', 'sales' );
	if ( $role && get_role( $role ) ) {
		$data['role'] = $role;
	}
	return $data;
} );

/* ── Template, body class, assets ───────────────────────────────────────── */

/*
 * Login page and account page render through wc-account.php. Priority 20:
 * after Elementor's page template filter (11), like wc-page.php in
 * inc/shop.php. The Suite's order page filter (99) never runs for these
 * views because ip_account_redirects() sends /order/ away first.
 */
add_filter( 'template_include', function ( $template ) {
	if ( ip_account_enabled() && is_page() && ip_account_is_view() ) {
		$ours = locate_template( array( 'wc-account.php' ) );
		if ( $ours ) {
			return $ours;
		}
	}
	return $template;
}, 20 );

add_filter( 'body_class', function ( $classes ) {
	if ( ip_account_enabled() && ip_account_is_view() ) {
		$classes[] = 'ip-view-account';
		if ( ip_account_is_login_page() ) {
			$classes[] = 'woocommerce-account';
			$classes[] = 'woocommerce-page';
		}
	}
	return $classes;
} );

/** True on views that show an order in detail (shared order.css). */
function ip_account_is_order_view() {
	if ( ! function_exists( 'is_wc_endpoint_url' ) ) {
		return false;
	}
	return is_wc_endpoint_url( 'view-order' ) || is_wc_endpoint_url( 'order-received' );
}

/*
 * WooCommerce's classic shop CSS floats and restyles everything the theme
 * styles itself on these views (account.css, order.css).
 */
add_filter( 'woocommerce_enqueue_styles', function ( $styles ) {
	if ( ip_account_enabled() && ( ip_account_is_view() || ( ip_account_owns_thankyou() && function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) ) {
		unset( $styles['woocommerce-layout'], $styles['woocommerce-smallscreen'], $styles['woocommerce-general'] );
	}
	return $styles;
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! ip_account_enabled() || ! function_exists( 'ip_enqueue' ) ) {
		return;
	}
	if ( ip_account_is_order_view() ) {
		ip_enqueue( 'ip-order', 'assets/css/order.css', array( 'ip-main' ) );
	}
	if ( ! ip_account_is_view() ) {
		return;
	}
	// Data for the avatar upload in assets/js/account.js (Suite REST route, logged-in only).
	if ( is_user_logged_in() && wp_script_is( 'ip-account', 'enqueued' ) ) {
		wp_add_inline_script( 'ip-account', 'window.ipAccount=' . wp_json_encode( array(
			'avatarUrl' => ip_account_avatar_upload_enabled() ? esc_url_raw( rest_url( 'infoplus/v1/user/avatar' ) ) : '',
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'i18n'      => array(
				'type'      => __( 'Bitte wählen Sie ein JPG-, PNG- oder GIF-Bild.', 'infoplus' ),
				'size'      => __( 'Das Bild darf höchstens 5 MB groß sein.', 'infoplus' ),
				'uploading' => __( 'Bild wird hochgeladen …', 'infoplus' ),
				'done'      => __( 'Profilbild gespeichert.', 'infoplus' ),
				'failed'    => __( 'Das Bild konnte nicht gespeichert werden.', 'infoplus' ),
				/* translators: %d: number of orders shown */
				'count'     => __( '%d Bestellungen', 'infoplus' ),
			),
		) ) . ';', 'before' );
	}
	/*
	 * The Infoplus Suite loads its dashboard and order scripts on every page
	 * for logged-in users (user-dashboard.js fetches /user/profile each time).
	 * The theme's account views do not use them.
	 */
	foreach ( array( 'infoplus-user-dashboard', 'infoplus-user-orders' ) as $handle ) {
		wp_dequeue_script( $handle );
	}
	wp_dequeue_style( 'infoplus-user-orders' );
}, 100 );

/* ── Menu, titles ───────────────────────────────────────────────────────── */

/** German labels in the B2B wording of the old account tabs; Downloads only when there are any. */
add_filter( 'woocommerce_account_menu_items', function ( $items ) {
	$labels = array(
		'dashboard'       => __( 'Übersicht', 'infoplus' ),
		'orders'          => __( 'Bestellungen', 'infoplus' ),
		'downloads'       => __( 'Downloads', 'infoplus' ),
		'edit-address'    => __( 'Adressen', 'infoplus' ),
		'payment-methods' => __( 'Zahlungsmethoden', 'infoplus' ),
		'edit-account'    => __( 'Kontodaten', 'infoplus' ),
		'customer-logout' => __( 'Abmelden', 'infoplus' ),
	);
	foreach ( $labels as $key => $label ) {
		if ( isset( $items[ $key ] ) ) {
			$items[ $key ] = $label;
		}
	}
	if ( isset( $items['downloads'] ) && is_user_logged_in() && function_exists( 'wc_get_customer_available_downloads' ) && ! wc_get_customer_available_downloads( get_current_user_id() ) ) {
		unset( $items['downloads'] );
	}
	return $items;
}, 20 );

/** Icon per menu item (inc/icons.php keys). */
function ip_account_menu_icon( $endpoint ) {
	$map = array(
		'dashboard'       => 'grid',
		'orders'          => 'package',
		'downloads'       => 'download',
		'edit-address'    => 'pin',
		'payment-methods' => 'card',
		'edit-account'    => 'user',
		'customer-logout' => 'logout',
	);
	return isset( $map[ $endpoint ] ) ? $map[ $endpoint ] : 'chevron';
}

/**
 * Heading texts of the current account view.
 *
 * @return array{kicker:string,title:string,sub:string}
 */
function ip_account_head() {
	$head = array(
		'kicker' => __( 'Kundenkonto', 'infoplus' ),
		'title'  => __( 'Mein Konto', 'infoplus' ),
		'sub'    => '',
	);
	if ( ip_account_is_login_page() || ! is_user_logged_in() ) {
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'lost-password' ) ) {
			$reset         = ! empty( $_GET['show-reset-form'] ); // phpcs:ignore WordPress.Security.NonceVerification -- display only.
			$head['title'] = $reset ? __( 'Neues Passwort festlegen', 'infoplus' ) : __( 'Passwort vergessen', 'infoplus' );
			$head['sub']   = $reset ? '' : __( 'Wir senden Ihnen einen Link, mit dem Sie ein neues Passwort festlegen.', 'infoplus' );
			return $head;
		}
		$head['title'] = __( 'Anmelden', 'infoplus' );
		$head['sub']   = __( 'Bestellungen, Rechnungen und Adressen an einem Ort. Für Händler und Geschäftskunden.', 'infoplus' );
		return $head;
	}

	$endpoint = WC()->query ? WC()->query->get_current_endpoint() : '';
	switch ( $endpoint ) {
		case 'orders':
			$head['title'] = __( 'Bestellungen', 'infoplus' );
			$head['sub']   = __( 'Status, Rechnungen und Details aller Bestellungen.', 'infoplus' );
			break;
		case 'view-order':
			$order = wc_get_order( absint( get_query_var( 'view-order' ) ) );
			/* translators: %s: order number */
			$head['title'] = $order && current_user_can( 'view_order', $order->get_id() ) ? sprintf( __( 'Bestellung #%s', 'infoplus' ), $order->get_order_number() ) : __( 'Bestellung', 'infoplus' );
			break;
		case 'edit-address':
			$type          = sanitize_key( (string) get_query_var( 'edit-address' ) );
			$head['title'] = 'billing' === $type ? __( 'Rechnungsadresse', 'infoplus' ) : ( 'shipping' === $type ? __( 'Lieferadresse', 'infoplus' ) : __( 'Adressen', 'infoplus' ) );
			$head['sub']   = $type ? '' : __( 'Diese Adressen schlagen wir an der Kasse vor.', 'infoplus' );
			break;
		case 'edit-account':
			$head['title'] = __( 'Kontodaten', 'infoplus' );
			$head['sub']   = __( 'Name, E-Mail-Adresse, Umsatzsteuer-ID und Passwort.', 'infoplus' );
			break;
		case 'downloads':
			$head['title'] = __( 'Downloads', 'infoplus' );
			break;
		case 'payment-methods':
		case 'add-payment-method':
			$head['title'] = __( 'Zahlungsmethoden', 'infoplus' );
			break;
		case '':
			$user = wp_get_current_user();
			$name = $user->first_name ? $user->first_name : $user->display_name;
			/* translators: %s: first name */
			$head['title'] = sprintf( __( 'Willkommen zurück, %s', 'infoplus' ), $name );
			$head['sub']   = __( 'Ihre Bestellungen, Rechnungen und Daten auf einen Blick.', 'infoplus' );
			break;
		default:
			$title = WC()->query->get_endpoint_title( $endpoint );
			if ( $title ) {
				$head['title'] = $title;
			}
	}
	return $head;
}

/* ── Profile: avatar, status, VAT ID ────────────────────────────────────── */

/** True when the Infoplus Suite avatar upload route is there (UserDashboard module). */
function ip_account_avatar_upload_enabled() {
	return class_exists( '\Infoplus\Suite\Modules\UserDashboard\Module' ) && (bool) apply_filters( 'ip_account_avatar_upload', true );
}

/**
 * Avatar markup: the picture uploaded through the Suite (user meta
 * infoplus_user_avatar), else the initials. No Gravatar: no third-party
 * request.
 */
function ip_account_avatar( $user_id, $size = 56 ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return '';
	}
	$att = (int) get_user_meta( $user_id, 'infoplus_user_avatar', true );
	if ( $att && wp_attachment_is_image( $att ) ) {
		$img = wp_get_attachment_image( $att, 'thumbnail', false, array(
			'class'    => 'ip-avatar__img',
			'alt'      => '',
			'loading'  => 'lazy',
			'decoding' => 'async',
			'width'    => $size,
			'height'   => $size,
		) );
		if ( $img ) {
			return '<span class="ip-avatar" style="--size:' . (int) $size . 'px" data-ip-avatar>' . $img . '</span>';
		}
	}
	$name     = trim( $user->first_name . ' ' . $user->last_name );
	$name     = $name ? $name : $user->display_name;
	$initials = '';
	foreach ( array_slice( preg_split( '/\s+/u', trim( $name ) ), 0, 2 ) as $part ) {
		$initials .= function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $part, 0, 1 ) ) : strtoupper( substr( $part, 0, 1 ) );
	}
	return '<span class="ip-avatar ip-avatar--initials" style="--size:' . (int) $size . 'px" data-ip-avatar aria-hidden="true">' . esc_html( $initials ) . '</span>';
}

/**
 * Account status: the display name of the user's role (staging: "sales" =
 * "Händler"). Replaces the fixed "Händler" text of the old page.
 */
function ip_account_status_label( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user || ! $user->roles ) {
		return '';
	}
	$names = wp_roles()->role_names;
	$role  = reset( $user->roles );
	return isset( $names[ $role ] ) ? translate_user_role( $names[ $role ] ) : '';
}

/*
 * Umsatzsteuer-ID in the account details. The old settings tab saved it as
 * user meta infoplus_vat_id through the Suite REST route; the field here
 * reads and writes the same meta, inside WooCommerce's own form (nonce
 * checked by WooCommerce before woocommerce_save_account_details runs).
 */
add_action( 'woocommerce_edit_account_form_fields', function () {
	$value = (string) get_user_meta( get_current_user_id(), 'infoplus_vat_id', true );
	?>
	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="ip_vat_id"><?php esc_html_e( 'Umsatzsteuer-ID', 'infoplus' ); ?> <span class="optional"><?php esc_html_e( '(optional)', 'infoplus' ); ?></span></label>
		<input type="text" class="woocommerce-Input input-text" name="ip_vat_id" id="ip_vat_id" value="<?php echo esc_attr( $value ); ?>" maxlength="20" autocomplete="off" spellcheck="false" placeholder="DE123456789">
	</p>
	<?php
} );

add_action( 'woocommerce_save_account_details', function ( $user_id ) {
	if ( ! isset( $_POST['ip_vat_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- WooCommerce verified save-account-details-nonce.
		return;
	}
	$vat = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_POST['ip_vat_id'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	update_user_meta( $user_id, 'infoplus_vat_id', substr( $vat, 0, 20 ) );
} );

/* Avatar block above the account details form (upload via the Suite route, assets/js/account.js). */
add_action( 'woocommerce_before_edit_account_form', function () {
	$uid = get_current_user_id();
	?>
	<section class="ip-acc-avatar" aria-labelledby="ip-acc-avatar-title">
		<?php echo ip_account_avatar( $uid, 72 ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?>
		<div class="ip-acc-avatar__body">
			<h2 class="ip-acc-avatar__title" id="ip-acc-avatar-title"><?php esc_html_e( 'Profilbild', 'infoplus' ); ?></h2>
			<?php if ( ip_account_avatar_upload_enabled() ) : ?>
				<p class="ip-acc-avatar__hint" id="ip-acc-avatar-hint"><?php esc_html_e( 'JPG, PNG oder GIF, höchstens 5 MB.', 'infoplus' ); ?></p>
				<label class="ip-btn ip-btn--ghost ip-btn--sm ip-js-only ip-acc-avatar__pick">
					<?php echo ip_icon( 'upload' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
					<span><?php esc_html_e( 'Neues Bild hochladen', 'infoplus' ); ?></span>
					<input class="ip-sr" type="file" accept="image/jpeg,image/png,image/gif" aria-describedby="ip-acc-avatar-hint" data-ip-avatar-input>
				</label>
				<p class="ip-acc-avatar__status" role="status" aria-live="polite" data-ip-avatar-status></p>
			<?php else : ?>
				<p class="ip-acc-avatar__hint"><?php esc_html_e( 'Ihre Initialen erscheinen als Profilbild.', 'infoplus' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
	<?php
} );

/*
 * Company name in the address forms of the account. Staging hides the
 * company field (WooCommerce > Settings > General "Firma": hidden), which
 * also removes it from /account/edit-address/. The old account settings tab
 * let customers edit "Firmenname" (billing_company), so it stays here as an
 * optional field. Checkout is not touched.
 */
add_filter( 'woocommerce_default_address_fields', function ( $fields ) {
	if ( ! ip_account_enabled() || isset( $fields['company'] ) || ! did_action( 'parse_query' ) || ! is_wc_endpoint_url( 'edit-address' ) ) {
		return $fields;
	}
	$fields['company'] = array(
		'label'        => __( 'Firmenname', 'infoplus' ),
		'class'        => array( 'form-row-wide' ),
		'autocomplete' => 'organization',
		'priority'     => 30,
		'required'     => false,
	);
	return $fields;
} );

/* ── Orders: list query, columns, status ────────────────────────────────── */

/** Status filters of the orders list: query value => label. */
function ip_account_status_filters() {
	return (array) apply_filters( 'ip_account_status_filters', array(
		'on-hold'    => __( 'In Wartestellung', 'infoplus' ),
		'processing' => __( 'In Bearbeitung', 'infoplus' ),
		'completed'  => __( 'Abgeschlossen', 'infoplus' ),
	) );
}

/** Active status filter from ?status=, '' for all. */
function ip_account_current_status_filter() {
	$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- read-only filter.
	return array_key_exists( $status, ip_account_status_filters() ) ? $status : '';
}

add_filter( 'woocommerce_my_account_my_orders_query', function ( $args ) {
	$args['limit'] = (int) apply_filters( 'ip_account_orders_per_page', 20 );
	$status        = ip_account_current_status_filter();
	if ( $status ) {
		$args['status'] = array( 'wc-' . $status );
	}
	return $args;
} );

/**
 * Number of the current customer's orders, optionally of one status.
 * Cached per request (dashboard tiles and filter pills share it).
 */
function ip_account_order_count( $status = '' ) {
	static $cache = array();
	$uid = get_current_user_id();
	if ( ! $uid || ! function_exists( 'wc_get_orders' ) ) {
		return 0;
	}
	if ( ! isset( $cache[ $status ] ) ) {
		$args = array(
			'customer' => $uid,
			'limit'    => 1,
			'paginate' => true,
			'return'   => 'ids',
		);
		if ( $status ) {
			$args['status'] = array( 'wc-' . $status );
		}
		$result           = wc_get_orders( $args );
		$cache[ $status ] = is_object( $result ) ? (int) $result->total : 0;
	}
	return $cache[ $status ];
}

/** Columns of the orders list: German labels plus the order reference (PO number). */
add_filter( 'woocommerce_account_orders_columns', function ( $columns ) {
	$labels = array(
		'order-number'  => __( 'Bestellung', 'infoplus' ),
		'order-date'    => __( 'Datum', 'infoplus' ),
		'order-status'  => __( 'Status', 'infoplus' ),
		'order-total'   => __( 'Summe', 'infoplus' ),
		'order-actions' => __( 'Aktionen', 'infoplus' ),
	);
	$out = array();
	foreach ( $columns as $key => $label ) {
		$out[ $key ] = isset( $labels[ $key ] ) ? $labels[ $key ] : $label;
		if ( 'order-date' === $key ) {
			$out['ip-reference'] = __( 'Referenz', 'infoplus' );
		}
	}
	return $out;
} );

add_action( 'woocommerce_my_account_my_orders_column_ip-reference', function ( $order ) {
	$po = function_exists( 'ip_shop_po_number' ) ? ip_shop_po_number( $order ) : '';
	echo $po ? esc_html( $po ) : '<span class="ip-muted" aria-hidden="true">–</span><span class="ip-sr">' . esc_html__( 'keine', 'infoplus' ) . '</span>';
} );

/** Status pill markup. Colour comes from a dot; the text says the status. */
function ip_account_status_pill( $order ) {
	$status = $order->get_status();
	return sprintf(
		'<span class="ip-status ip-status--%1$s">%2$s</span>',
		esc_attr( sanitize_html_class( $status ) ),
		esc_html( wc_get_order_status_name( $status ) )
	);
}

/** Icon for an order action key. */
function ip_account_action_icon( $key ) {
	$map = array(
		'view'       => 'chevron',
		'invoice'    => 'download',
		'ip-reorder' => 'refresh',
		'pay'        => 'card',
		'cancel'     => 'close',
	);
	return isset( $map[ $key ] ) ? $map[ $key ] : '';
}

/**
 * One order action as a link button. $primary marks the main action of a
 * row or summary.
 */
function ip_account_action_link( $key, $action, $order, $primary = false ) {
	$label = empty( $action['aria-label'] )
		/* translators: 1: action name, 2: order number */
		? sprintf( __( '%1$s, Bestellung %2$s', 'infoplus' ), $action['name'], $order->get_order_number() )
		: $action['aria-label'];
	$icon  = ip_account_action_icon( $key );
	$attrs = 'invoice' === $key || str_starts_with( (string) $key, 'invoice' ) ? ' target="_blank" rel="noopener"' : '';
	return sprintf(
		'<a href="%1$s" class="ip-btn ip-btn--sm %2$s woocommerce-button button %3$s" aria-label="%4$s"%5$s>%6$s<span>%7$s</span></a>',
		esc_url( $action['url'] ),
		$primary ? '' : 'ip-btn--ghost',
		esc_attr( sanitize_html_class( $key ) ),
		esc_attr( $label ),
		$attrs,
		$icon ? ip_icon( $icon ) : '',
		esc_html( $action['name'] )
	);
}

/** Order actions without "view" (for views that already show the order). */
function ip_account_order_actions( $order ) {
	if ( ! function_exists( 'wc_get_account_orders_actions' ) ) {
		return array();
	}
	$actions = wc_get_account_orders_actions( $order );
	unset( $actions['view'] );
	return $actions;
}

/* ── Reorder ("Erneut bestellen") ───────────────────────────────────────── */

/**
 * Order item meta that makes a line unique: an editor design (project_id,
 * display, ife_price, ife_thumbnail_url from InfoplusAPI) or uploaded files
 * (_sonderkontur_files from the Suite). WooCommerce's "order again" copies
 * product, variation and quantity only, so such lines would come back without
 * the design or at a different price.
 */
function ip_account_unique_item_meta() {
	return (array) apply_filters( 'ip_account_unique_item_meta', array( 'project_id', 'display', 'ife_price', 'ife_thumbnail_url', '_sonderkontur_files' ) );
}

/** True when WooCommerce's "order again" would recreate this order faithfully. */
function ip_account_can_reorder( $order ) {
	if ( ! $order instanceof WC_Order || ! is_user_logged_in() || (int) $order->get_customer_id() !== get_current_user_id() ) {
		return false;
	}
	if ( ! $order->has_status( apply_filters( 'woocommerce_valid_order_statuses_for_order_again', array( 'completed' ) ) ) ) {
		return false;
	}
	$items = $order->get_items();
	if ( ! $items ) {
		return false;
	}
	foreach ( $items as $item ) {
		$product = $item->get_product();
		if ( ! $product || ! $product->is_purchasable() ) {
			return false;
		}
		foreach ( ip_account_unique_item_meta() as $key ) {
			$value = $item->get_meta( $key );
			if ( ! empty( $value ) ) {
				return false;
			}
		}
	}
	return (bool) apply_filters( 'ip_account_can_reorder', true, $order );
}

/* "Erneut bestellen" as an order action (orders list and order view), only when safe. */
add_filter( 'woocommerce_my_account_my_orders_actions', function ( $actions, $order ) {
	if ( ip_account_enabled() && ip_account_can_reorder( $order ) ) {
		$actions['ip-reorder'] = array(
			'url'  => wp_nonce_url( add_query_arg( 'order_again', $order->get_id(), wc_get_cart_url() ), 'woocommerce-order_again' ),
			'name' => __( 'Erneut bestellen', 'infoplus' ),
		);
	}
	return $actions;
}, 20, 2 );

/* The action above replaces WooCommerce's own button under the order table. */
add_action( 'wp', function () {
	if ( ip_account_enabled() ) {
		remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );
	}
} );

/*
 * A crafted ?order_again= link for an order with designed or uploaded items
 * is refused before WooCommerce reads it (WC_Cart_Session::get_cart_from_session,
 * wp_loaded priority 10).
 */
add_action( 'wp_loaded', function () {
	if ( ! ip_account_enabled() || empty( $_GET['order_again'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- WooCommerce verifies the nonce; this only blocks.
		return;
	}
	$order = wc_get_order( absint( $_GET['order_again'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( $order && ! ip_account_can_reorder( $order ) ) {
		unset( $_GET['order_again'] );
		wc_add_notice( __( 'Diese Bestellung enthält gestaltete oder individuell angepasste Artikel. Bitte bestellen Sie diese über den Editor oder fragen Sie uns an.', 'infoplus' ), 'notice' );
		// The cart block does not print classic notices; the order view does.
		if ( is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id() ) {
			wp_safe_redirect( $order->get_view_order_url() );
			exit;
		}
	}
}, 5 );

/* ── Order items and fields in the order views ──────────────────────────── */

/**
 * Item meta for customers: hides the technical editor keys and labels the
 * design name. Added only while the theme's order templates print item meta
 * (order/order-details-item.php), so e-mails and the admin are unchanged.
 */
function ip_account_filter_item_meta( $meta ) {
	$hidden = array( 'project_id', 'ife_price', 'ife_thumbnail_url', 'infoplus_unique' );
	foreach ( $meta as $id => $entry ) {
		if ( in_array( $entry->key, $hidden, true ) ) {
			unset( $meta[ $id ] );
		} elseif ( 'display' === $entry->key ) {
			$meta[ $id ]->display_key = __( 'Design', 'infoplus' );
		}
	}
	return $meta;
}

/**
 * Thumbnail of an order line: the Suite's model picture (through
 * woocommerce_order_item_thumbnail), else the product image.
 */
function ip_account_item_thumbnail( $item, $product ) {
	$image = $product ? $product->get_image( 'woocommerce_gallery_thumbnail', array( 'alt' => '', 'loading' => 'lazy' ) ) : '';
	$html  = (string) apply_filters( 'woocommerce_order_item_thumbnail', $image, $item );
	return $html ? $html : '<span class="ip-order-item__noimg" aria-hidden="true"></span>';
}

/*
 * WooCommerce lists the PO number under "Zusätzliche Informationen" in the
 * customer details. The theme's order views show it in the summary at the
 * top, so it is left out there (only there: e-mails keep it).
 */
add_filter( 'woocommerce_filter_fields_for_order_confirmation', function ( $show, $field, $fields = array(), $context = array() ) {
	if ( ip_account_enabled() && isset( $context['caller'] ) && 'CheckoutFieldsFrontend::render_order_other_fields' === $context['caller']
		&& isset( $field['id'] ) && defined( 'IP_SHOP_PO_FIELD' ) && IP_SHOP_PO_FIELD === $field['id'] ) {
		return false;
	}
	return $show;
}, 10, 4 );

/**
 * Facts of an order for the summary of the order view and the confirmation.
 *
 * @return array<string,array{label:string,html:string}>
 */
function ip_account_order_facts( $order, $context = 'view' ) {
	$facts = array();
	if ( 'received' === $context ) {
		$facts['number'] = array( 'label' => __( 'Bestellnummer', 'infoplus' ), 'html' => esc_html( $order->get_order_number() ) );
	}
	$date = $order->get_date_created();
	if ( $date ) {
		$facts['date'] = array( 'label' => __( 'Datum', 'infoplus' ), 'html' => '<time datetime="' . esc_attr( $date->date( 'c' ) ) . '">' . esc_html( wc_format_datetime( $date ) ) . '</time>' );
	}
	$facts['status'] = array( 'label' => __( 'Status', 'infoplus' ), 'html' => ip_account_status_pill( $order ) );
	if ( $order->get_payment_method_title() ) {
		$facts['payment'] = array( 'label' => __( 'Zahlungsart', 'infoplus' ), 'html' => wp_kses_post( $order->get_payment_method_title() ) );
	}
	$po = function_exists( 'ip_shop_po_number' ) ? ip_shop_po_number( $order ) : '';
	if ( $po ) {
		$facts['po'] = array( 'label' => __( 'Ihre Bestellreferenz', 'infoplus' ), 'html' => esc_html( $po ) );
	}
	if ( 'received' === $context && is_user_logged_in() && $order->get_user_id() === get_current_user_id() && $order->get_billing_email() ) {
		$facts['email'] = array( 'label' => __( 'Bestätigung an', 'infoplus' ), 'html' => esc_html( $order->get_billing_email() ) );
	}
	// With the shop's tax display, so "inkl. … MwSt." shows under the total when prices are shown gross.
	$facts['total'] = array( 'label' => __( 'Gesamtsumme', 'infoplus' ), 'html' => wp_kses_post( $order->get_formatted_order_total( get_option( 'woocommerce_tax_display_cart' ) ) ) );
	return (array) apply_filters( 'ip_account_order_facts', $facts, $order, $context );
}

/** Print a facts list (ip_account_order_facts()). */
function ip_account_print_facts( $facts ) {
	echo '<dl class="ip-facts">';
	foreach ( $facts as $key => $fact ) {
		printf(
			'<div class="ip-facts__item ip-facts__item--%1$s"><dt>%2$s</dt><dd>%3$s</dd></div>',
			esc_attr( $key ),
			esc_html( $fact['label'] ),
			$fact['html'] // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in ip_account_order_facts().
		);
	}
	echo '</dl>';
}

/* ── Order confirmation (order received) ────────────────────────────────── */

/**
 * True when the theme renders the order confirmation itself
 * (woocommerce/checkout/thankyou.php). Return false from the filter
 * ip_account_own_thankyou to give the page back to the Infoplus Suite.
 */
function ip_account_owns_thankyou() {
	return ip_account_enabled() && (bool) apply_filters( 'ip_account_own_thankyou', true );
}

/*
 * The Infoplus Suite (UserOrders module) takes the order-received page over:
 * it prints its own thank-you block on woocommerce_thankyou (priority 1) and
 * woocommerce_before_thankyou, replaces the received text, renders the same
 * block through the [infoplus_order_received] shortcode (in the checkout page
 * content), hides WooCommerce's details with CSS and adds checkout styles in
 * wp_head. With the theme's thankyou.php all of that is switched off here,
 * only on the order-received page. The Suite itself is unchanged, so it works
 * as before under any other theme.
 */
add_action( 'wp', function () {
	if ( ! ip_account_owns_thankyou() || ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
		return;
	}
	ip_unhook_suite( 'woocommerce_thankyou', array( 'renderThankYouContent' ) );
	ip_unhook_suite( 'woocommerce_before_thankyou', array( 'renderThankYouContent' ) );
	ip_unhook_suite( 'woocommerce_thankyou_order_received_text', array( 'filterThankYouText' ) );
	ip_unhook_suite( 'wp_head', array( 'enqueueCheckoutStyles' ) );
	add_filter( 'pre_do_shortcode_tag', function ( $output, $tag ) {
		return 'infoplus_order_received' === $tag ? '' : $output;
	}, 10, 2 );
	add_action( 'wp_enqueue_scripts', function () {
		wp_dequeue_style( 'infoplus-woo-pages' );
	}, 100 );
} );

/** The order confirmation's "continue shopping" target: the Namensschilder overview. */
function ip_account_shop_url() {
	return function_exists( 'ip_shop_plates_url' ) ? ip_shop_plates_url() : home_url( '/' );
}
