<?php
/**
 * Site header, ported from Elementor header template 17 ("infoplus_header + native").
 *
 * Desktop (1101 px and up): logo, Namensschilder (link + mega menu panel),
 * Über infoplus, Kontakt, "|" separator, product search, account icon, cart icon.
 * Below 1101 px (Elementor tablet + mobile): logo + burger, which opens the
 * mobile menu dialog (replaces Elementor popup 141).
 *
 * Header 17 is sticky, white at all times and has no logo swap. The light/dark
 * logo swap in the customizer CSS belongs to header 369 ("infoplus_header_home"),
 * which has no display conditions and never renders, so it is not ported.
 * site.js still toggles .is-stuck (the equivalent of .elementor-sticky--effects).
 */

defined( 'ABSPATH' ) || exit;

/*
 * Header helpers. Defined here (guarded) because inc/ belongs to the shared
 * theme layer; move them to inc/navigation.php when convenient.
 */

if ( ! function_exists( 'ip_header_logo' ) ) {
	/**
	 * Logo <img> HTML. Order: the Elementor kit site logo (attachment 85,
	 * IP-Logo-magenta.png), then the WP custom logo, then the site name.
	 */
	function ip_header_logo() {
		$name = get_bloginfo( 'name' );
		$attr = array(
			'class'    => 'ip-logo__img',
			'alt'      => $name,
			'loading'  => 'eager',
			'decoding' => 'async',
			'sizes'    => '(min-width: 1101px) 120px, 100px',
		);
		$ids  = array( (int) apply_filters( 'ip_logo_id', 85 ), (int) get_theme_mod( 'custom_logo' ) );
		foreach ( array_filter( $ids ) as $id ) {
			$html = wp_get_attachment_image( $id, 'medium', false, $attr );
			if ( $html ) {
				return $html;
			}
		}
		return '<span class="ip-logo__text">' . esc_html( $name ) . '</span>';
	}
}

if ( ! function_exists( 'ip_header_svg' ) ) {
	/**
	 * Header-only icons drawn like the old site (not in inc/icons.php).
	 * burger/close: uploads/2025/06/mobile-burger-menu.svg and mobile-close.svg.
	 * user: Font Awesome Free 5 "user-circle" solid (CC BY 4.0, fontawesome.com),
	 * the icon header 17 used. person: uploads/2025/06/user-filled.svg (popup 141),
	 * coordinates rounded. cart: filled cart standing in for Elementor's
	 * "cart-solid" eicon. Always decorative; the link or button carries the name.
	 */
	function ip_header_svg( $name ) {
		$svgs = array(
			'burger' => '<svg viewBox="0 0 32 32" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7H30M2 16H30M2 25H30"/></svg>',
			'close'  => '<svg viewBox="0 0 32 32" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7L30 25M2 25L30 7"/></svg>',
			'user'   => '<svg viewBox="0 0 496 512" width="32" height="32" fill="currentColor"><path d="M248 8C111 8 0 119 0 256s111 248 248 248 248-111 248-248S385 8 248 8zm0 96c48.6 0 88 39.4 88 88s-39.4 88-88 88-88-39.4-88-88 39.4-88 88-88zm0 344c-58.7 0-111.3-26.6-146.5-68.2 18.8-35.4 55.6-59.8 98.5-59.8 2.4 0 4.8.4 7.1 1.1 13 4.2 26.6 6.9 40.9 6.9 14.3 0 28-2.7 40.9-6.9 2.3-.7 4.7-1.1 7.1-1.1 42.9 0 79.7 24.4 98.5 59.8C359.3 421.4 306.7 448 248 448z"/></svg>',
			'person' => '<svg viewBox="0 0 30 30" width="30" height="30" fill="currentColor"><path fill-rule="evenodd" clip-rule="evenodd" d="M23.36 23.87A12.15 12.15 0 0 0 27.19 15C27.19 8.27 21.73 2.81 15 2.81S2.81 8.27 2.81 15a12.15 12.15 0 0 0 3.83 8.87A12.14 12.14 0 0 0 15 27.19a12.14 12.14 0 0 0 8.36-3.32ZM7.68 22.27A9.36 9.36 0 0 1 15 18.75a9.36 9.36 0 0 1 7.32 3.52A10.29 10.29 0 0 1 15 25.31a10.29 10.29 0 0 1-7.32-3.04ZM19.69 11.25a4.69 4.69 0 1 1-9.38 0 4.69 4.69 0 0 1 9.38 0Z"/></svg>',
			'cart'   => '<svg viewBox="0 0 24 24" width="30" height="30" fill="currentColor"><path d="M1.5 2.5h2.9l.7 2.5h15.6a1 1 0 0 1 .97 1.24l-1.9 7.5a1.5 1.5 0 0 1-1.45 1.13H8.35l.45 1.6H19v2H8.05a1 1 0 0 1-.96-.73L3.65 4.5H1.5z"/><circle cx="9.5" cy="20.5" r="1.75"/><circle cx="17.5" cy="20.5" r="1.75"/></svg>',
		);
		if ( ! isset( $svgs[ $name ] ) ) {
			return '';
		}
		return str_replace( '<svg ', '<svg class="ip-hicon ip-hicon--' . esc_attr( $name ) . '" aria-hidden="true" focusable="false" ', $svgs[ $name ] );
	}
}

if ( ! function_exists( 'ip_header_account' ) ) {
	/**
	 * Account link target and label. Mirrors snippet 12463: logged-in users go
	 * to the account, guests to /log-in/.
	 *
	 * @return array{url:string,label:string}
	 */
	function ip_header_account() {
		if ( is_user_logged_in() ) {
			return array( 'url' => ip_account_url(), 'label' => __( 'Mein Konto', 'infoplus' ) );
		}
		return array( 'url' => home_url( '/log-in/' ), 'label' => __( 'Anmelden', 'infoplus' ) );
	}
}

if ( ! function_exists( 'ip_header_cart_url' ) ) {
	/** Cart page URL, or home when WooCommerce is inactive. */
	function ip_header_cart_url() {
		return function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );
	}
}

if ( ! function_exists( 'ip_header_search_form' ) ) {
	/**
	 * Product search form (GET, s + post_type=product, like the child theme
	 * that limits search to products).
	 *
	 * @param string $id Unique id prefix for this instance.
	 */
	function ip_header_search_form( $id ) {
		$id = sanitize_html_class( $id );
		?>
		<form class="ip-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="ip-sr" for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Suche', 'infoplus' ); ?></label>
			<input class="ip-search__input" type="search" id="<?php echo esc_attr( $id ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Suchbegriff', 'infoplus' ); ?>" autocomplete="off">
			<input type="hidden" name="post_type" value="product">
			<button class="ip-search__submit" type="submit" aria-label="<?php esc_attr_e( 'Suche', 'infoplus' ); ?>"><?php echo ip_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></button>
		</form>
		<?php
	}
}

if ( ! function_exists( 'ip_header_nameplates' ) ) {
	/**
	 * Data for the Namensschilder mega menu and the drawer fallback.
	 *
	 * Source of truth: the children of the "Namensschilder" item in the menu
	 * assigned to the 'mobile' location (Mobile Menu 247). Fallback when no
	 * menu is assigned: all published products by menu_order, then title.
	 *
	 * @return array{url:string,title:string,items:array<int,array{title:string,url:string,image:int,product:bool,current:bool}>}
	 */
	function ip_header_nameplates() {
		static $data = null;
		if ( null !== $data ) {
			return $data;
		}
		$data    = array(
			'url'   => home_url( '/namensschilder/' ),
			'title' => __( 'Namensschilder', 'infoplus' ),
			'items' => array(),
		);
		$current = (int) get_queried_object_id();

		$locations = get_nav_menu_locations();
		if ( ! empty( $locations['mobile'] ) ) {
			$menu_items = wp_get_nav_menu_items( (int) $locations['mobile'], array( 'update_post_term_cache' => false ) );
			$children   = array();
			foreach ( (array) $menu_items as $item ) {
				if ( empty( $item->_invalid ) ) {
					$children[ (int) $item->menu_item_parent ][] = $item;
				}
			}
			// The top-level item titled "Namensschilder"; else the first one with children.
			$parent = null;
			foreach ( $children[0] ?? array() as $top ) {
				if ( empty( $children[ (int) $top->ID ] ) ) {
					continue;
				}
				if ( 'namensschilder' === sanitize_title( $top->title ) ) {
					$parent = $top;
					break;
				}
				$parent = $parent ? $parent : $top;
			}
			if ( $parent ) {
				$data['url']   = $parent->url;
				$data['title'] = $parent->title;
				foreach ( $children[ (int) $parent->ID ] as $child ) {
					$is_product      = 'product' === $child->object;
					$data['items'][] = array(
						'title'   => $child->title,
						'url'     => $child->url,
						'image'   => $is_product ? (int) get_post_thumbnail_id( (int) $child->object_id ) : 0,
						'product' => $is_product,
						'current' => 'post_type' === $child->type && (int) $child->object_id === $current,
					);
				}
			}
		}

		if ( ! $data['items'] && post_type_exists( 'product' ) ) {
			$ids = get_posts( array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => 24,
				'orderby'                => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			) );
			if ( function_exists( 'ip_prime_posts' ) ) {
				ip_prime_posts( $ids );
			}
			foreach ( $ids as $id ) {
				// Product titles read "Atlantis Namensschild"; the menu shows "Atlantis".
				$title           = preg_replace( '/\s+Namensschild(er)?$/iu', '', get_the_title( $id ) );
				$data['items'][] = array(
					'title'   => $title,
					'url'     => get_permalink( $id ),
					'image'   => (int) get_post_thumbnail_id( $id ),
					'product' => true,
					'current' => (int) $id === $current,
				);
			}
		}
		return $data;
	}
}

if ( ! function_exists( 'ip_header_is_current' ) ) {
	/** True when the request path equals the path of $url (both with a trailing slash). */
	function ip_header_is_current( $url ) {
		$want = trailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		// add_query_arg() with no args returns the current REQUEST_URI.
		$have = trailingslashit( (string) wp_parse_url( add_query_arg( array() ), PHP_URL_PATH ) );
		return $want === $have;
	}
}

$ip_plates  = ip_header_nameplates();
$ip_account = ip_header_account();
$ip_links   = array(
	array( __( 'Über infoplus', 'infoplus' ), home_url( '/unternehmen/' ) ),
	array( __( 'Kontakt', 'infoplus' ), home_url( '/kontakt/' ) ),
);
$ip_mega_id = 'ip-mega-panel';
$ip_plates_current = ip_header_is_current( $ip_plates['url'] );
?>
<header class="ip-header" data-ip-header>
	<div class="ip-header__bar">
		<a class="ip-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"<?php echo is_front_page() ? ' aria-current="page"' : ''; ?>>
			<?php echo ip_header_logo(); // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image() output or escaped text. ?>
		</a>

		<div class="ip-header__tools">
			<nav class="ip-nav" aria-label="<?php esc_attr_e( 'Hauptmenü', 'infoplus' ); ?>">
				<ul class="ip-nav__list">
					<li class="ip-nav__item<?php echo $ip_plates['items'] ? ' ip-nav__item--mega' : ''; ?>"<?php echo $ip_plates['items'] ? ' data-ip-mega' : ''; ?>>
						<a class="ip-nav__link" href="<?php echo esc_url( $ip_plates['url'] ); ?>"<?php echo $ip_plates_current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $ip_plates['title'] ); ?></a>
						<?php if ( $ip_plates['items'] ) : ?>
							<button type="button" class="ip-nav__toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $ip_mega_id ); ?>">
								<span class="ip-sr"><?php echo esc_html( sprintf( /* translators: %s: menu item, e.g. Namensschilder */ __( 'Untermenü %s', 'infoplus' ), $ip_plates['title'] ) ); ?></span>
								<?php echo ip_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
							</button>
							<?php
							get_template_part( 'template-parts/mega-menu', null, array(
								'id'    => $ip_mega_id,
								'items' => $ip_plates['items'],
								'label' => $ip_plates['title'],
							) );
							?>
						<?php endif; ?>
					</li>
					<?php foreach ( $ip_links as $ip_link ) : ?>
						<li class="ip-nav__item">
							<a class="ip-nav__link" href="<?php echo esc_url( $ip_link[1] ); ?>"<?php echo ip_header_is_current( $ip_link[1] ) ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $ip_link[0] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<span class="ip-header__sep" aria-hidden="true">|</span>

			<?php ip_header_search_form( 'ip-search-header' ); ?>

			<a class="ip-header__icon ip-header__account" href="<?php echo esc_url( $ip_account['url'] ); ?>" aria-label="<?php echo esc_attr( $ip_account['label'] ); ?>">
				<?php echo ip_header_svg( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
			</a>

			<a class="ip-header__icon ip-cart" href="<?php echo esc_url( ip_header_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'Warenkorb', 'infoplus' ); ?>">
				<?php echo ip_header_svg( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
				<?php echo ip_cart_badge(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in ip_cart_badge(). ?>
			</a>
		</div>

		<button type="button" class="ip-header__icon ip-burger" data-ip-open="ip-mobile-menu" aria-haspopup="dialog" aria-controls="ip-mobile-menu" aria-label="<?php esc_attr_e( 'Menü', 'infoplus' ); ?>">
			<?php echo ip_header_svg( 'burger' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
		</button>
	</div>
</header>
<?php
get_template_part( 'template-parts/mobile-menu', null, array(
	'plates'  => $ip_plates,
	'links'   => $ip_links,
	'account' => $ip_account,
) );
