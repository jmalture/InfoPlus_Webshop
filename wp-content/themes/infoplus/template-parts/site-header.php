<?php
/**
 * Site header. Content and links come from the old Elementor header 17
 * (logo, Namensschilder with its product panel, Über infoplus, Kontakt,
 * product search, account, cart); layout, layering and motion follow
 * DESIGN.md (SDX cues): compact white bar with a hairline, calm 19 px nav,
 * a floating product panel, round 40 px icon targets.
 *
 * Below 1101 px: logo, cart and burger; the burger opens the drawer
 * (template-parts/mobile-menu.php).
 *
 * The bar keeps a fixed height (--header-h, header.css) so the page never
 * shifts; the stuck state only moves the background layer and the bar
 * (transforms), see .is-stuck in header.css.
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
	 * Icons the shared set in inc/icons.php lacks, drawn in the same style
	 * (24 px grid, 1.75 stroke, currentColor). Decorative only.
	 */
	function ip_header_svg( $name ) {
		$paths = array(
			'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return '<svg class="ip-icon ip-icon--' . esc_attr( $name ) . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
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
	 * that limits search to products). Pill input with the icon inside.
	 *
	 * @param string $id Unique id for the input.
	 */
	function ip_header_search_form( $id ) {
		$id = sanitize_html_class( $id );
		?>
		<form class="ip-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="ip-sr" for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Suche', 'infoplus' ); ?></label>
			<input class="ip-search__input" type="search" id="<?php echo esc_attr( $id ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Namensschild suchen', 'infoplus' ); ?>" autocomplete="off">
			<input type="hidden" name="post_type" value="product">
			<button class="ip-search__submit" type="submit" aria-label="<?php esc_attr_e( 'Suche', 'infoplus' ); ?>"><?php echo ip_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></button>
		</form>
		<?php
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

if ( ! function_exists( 'ip_header_in_catalog' ) ) {
	/**
	 * True on every view that belongs to the Namensschilder section: the
	 * Namensschilder page, the shop page, product category and tag archives
	 * and single products. The header item "Namensschilder" is then current
	 * (aria-current="page"), also on the category URLs that the in-place
	 * filter pushes (inc/catalog.php, assets/js/archive.js), because those
	 * URLs render server side as category archives. Filter
	 * ip_header_in_catalog to change it.
	 */
	function ip_header_in_catalog() {
		$in = ( function_exists( 'ip_shop_is_plates_page' ) && ip_shop_is_plates_page() )
			|| ( function_exists( 'is_shop' ) && is_shop() )
			|| ( function_exists( 'is_product_category' ) && is_product_category() )
			|| ( function_exists( 'is_product_tag' ) && is_product_tag() )
			|| ( function_exists( 'is_product' ) && is_product() );
		return (bool) apply_filters( 'ip_header_in_catalog', $in );
	}
}

if ( ! function_exists( 'ip_header_menu_tree' ) ) {
	/**
	 * Top-level items (with children) of the menu in the 'mobile' location
	 * (Mobile Menu 247), shared by the mega panel and the drawer. Each item:
	 * title, url, current, plates (true for the Namensschilder item), children.
	 * Children also carry image (featured image ID) and product.
	 *
	 * Fallback without an assigned menu: Namensschilder (published products by
	 * menu_order, then title), Über infoplus, Kontakt.
	 *
	 * @return array<int,array>
	 */
	function ip_header_menu_tree() {
		static $tree = null;
		if ( null !== $tree ) {
			return $tree;
		}
		$tree    = array();
		$current = (int) get_queried_object_id();

		$is_current = static function ( $item ) use ( $current ) {
			if ( 'post_type' === $item->type || 'taxonomy' === $item->type ) {
				return $current && (int) $item->object_id === $current;
			}
			return ip_header_is_current( $item->url );
		};

		$locations = get_nav_menu_locations();
		if ( ! empty( $locations['mobile'] ) ) {
			$children = array();
			foreach ( (array) wp_get_nav_menu_items( (int) $locations['mobile'], array( 'update_post_term_cache' => false ) ) as $item ) {
				if ( empty( $item->_invalid ) ) {
					$children[ (int) $item->menu_item_parent ][] = $item;
				}
			}
			$thumb_ids = array();
			foreach ( $children[0] ?? array() as $top ) {
				$entry = array(
					'title'    => $top->title,
					'url'      => $top->url,
					'current'  => $is_current( $top ),
					'plates'   => false,
					'children' => array(),
				);
				foreach ( $children[ (int) $top->ID ] ?? array() as $child ) {
					$is_product          = 'product' === $child->object;
					$image               = $is_product ? (int) get_post_thumbnail_id( (int) $child->object_id ) : 0;
					$thumb_ids[]         = $image;
					$entry['children'][] = array(
						'title'   => $child->title,
						'url'     => $child->url,
						'image'   => $image,
						'product' => $is_product,
						'current' => $is_current( $child ),
					);
				}
				$tree[] = $entry;
			}
			$thumb_ids = array_filter( $thumb_ids );
			if ( $thumb_ids ) {
				_prime_post_caches( $thumb_ids, false, true ); // One query for all card images.
			}
			// The Namensschilder item: titled so, else the first item with children.
			$plates = null;
			foreach ( $tree as $i => $entry ) {
				if ( ! $entry['children'] ) {
					continue;
				}
				if ( 'namensschilder' === sanitize_title( $entry['title'] ) ) {
					$plates = $i;
					break;
				}
				$plates = null === $plates ? $i : $plates;
			}
			if ( null !== $plates ) {
				$tree[ $plates ]['plates']  = true;
				$tree[ $plates ]['current'] = $tree[ $plates ]['current'] || ip_header_in_catalog();
			}
			return $tree;
		}

		$products = array();
		if ( post_type_exists( 'product' ) ) {
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
				$products[] = array(
					// Product titles read "Atlantis Namensschild"; the menu shows "Atlantis".
					'title'   => preg_replace( '/\s+Namensschild(er)?$/iu', '', get_the_title( $id ) ),
					'url'     => get_permalink( $id ),
					'image'   => (int) get_post_thumbnail_id( $id ),
					'product' => true,
					'current' => (int) $id === $current,
				);
			}
		}
		$links = array(
			array( __( 'Namensschilder', 'infoplus' ), home_url( '/namensschilder/' ) ),
			array( __( 'Über infoplus', 'infoplus' ), home_url( '/unternehmen/' ) ),
			array( __( 'Kontakt', 'infoplus' ), home_url( '/kontakt/' ) ),
		);
		foreach ( $links as $i => $link ) {
			$tree[] = array(
				'title'    => $link[0],
				'url'      => $link[1],
				'current'  => ip_header_is_current( $link[1] ) || ( 0 === $i && ip_header_in_catalog() ),
				'plates'   => 0 === $i,
				'children' => 0 === $i ? $products : array(),
			);
		}
		return $tree;
	}
}

if ( ! function_exists( 'ip_header_nameplates' ) ) {
	/**
	 * The Namensschilder entry of ip_header_menu_tree() (title, url, current,
	 * items), with a /namensschilder/ default when the menu has none.
	 *
	 * @return array{title:string,url:string,current:bool,items:array}
	 */
	function ip_header_nameplates() {
		foreach ( ip_header_menu_tree() as $entry ) {
			if ( $entry['plates'] ) {
				return array(
					'title'   => $entry['title'],
					'url'     => $entry['url'],
					'current' => $entry['current'] || ip_header_is_current( $entry['url'] ),
					'items'   => $entry['children'],
				);
			}
		}
		$url = home_url( '/namensschilder/' );
		return array( 'title' => __( 'Namensschilder', 'infoplus' ), 'url' => $url, 'current' => ip_header_is_current( $url ) || ip_header_in_catalog(), 'items' => array() );
	}
}

if ( ! function_exists( 'ip_header_card_image' ) ) {
	/**
	 * Card image for a menu child: its featured image, the WooCommerce
	 * placeholder for products without one, else ''. Returns safe HTML.
	 * 'medium' keeps the wide nameplate shapes ('thumbnail' is a square crop).
	 */
	function ip_header_card_image( $item, $sizes ) {
		$attr = array( 'class' => 'ip-card-img', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => $sizes );
		if ( ! empty( $item['image'] ) ) {
			return wp_get_attachment_image( $item['image'], 'medium', false, $attr );
		}
		if ( ! empty( $item['product'] ) && function_exists( 'wc_placeholder_img' ) ) {
			unset( $attr['sizes'] );
			return wc_placeholder_img( 'medium', $attr );
		}
		return '';
	}
}

if ( ! function_exists( 'ip_header_count_label' ) ) {
	/** "21 Modelle" / "1 Modell". */
	function ip_header_count_label( $count ) {
		return sprintf( /* translators: %d: number of products */ _n( '%d Modell', '%d Modelle', $count, 'infoplus' ), $count );
	}
}

$ip_plates  = ip_header_nameplates();
$ip_count   = count( $ip_plates['items'] );
$ip_account = ip_header_account();
$ip_links   = array(
	array( __( 'Über infoplus', 'infoplus' ), home_url( '/unternehmen/' ) ),
	array( __( 'Kontakt', 'infoplus' ), home_url( '/kontakt/' ) ),
);
$ip_mega_id = 'ip-mega-panel';
?>
<header class="ip-header" data-ip-header>
	<div class="ip-header__bar">
		<a class="ip-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"<?php echo is_front_page() ? ' aria-current="page"' : ''; ?>>
			<?php echo ip_header_logo(); // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image() output or escaped text. ?>
		</a>

		<nav class="ip-nav" aria-label="<?php esc_attr_e( 'Hauptmenü', 'infoplus' ); ?>">
			<ul class="ip-nav__list">
				<li class="ip-nav__item<?php echo $ip_count ? ' ip-nav__item--mega' : ''; ?>"<?php echo $ip_count ? ' data-ip-mega' : ''; ?>>
					<a class="ip-nav__link" href="<?php echo esc_url( $ip_plates['url'] ); ?>" data-ip-nav-plates<?php echo $ip_plates['current'] ? ' aria-current="page"' : ''; ?>>
						<?php echo esc_html( $ip_plates['title'] ); ?>
						<?php if ( $ip_count ) : ?>
							<span class="ip-pill ip-nav__count" aria-hidden="true"><?php echo esc_html( $ip_count ); ?></span>
						<?php endif; ?>
					</a>
					<?php if ( $ip_count ) : ?>
						<button type="button" class="ip-nav__toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $ip_mega_id ); ?>">
							<span class="ip-sr"><?php echo esc_html( sprintf( /* translators: 1: menu item, 2: "21 Modelle" */ __( '%1$s anzeigen, %2$s', 'infoplus' ), $ip_plates['title'], ip_header_count_label( $ip_count ) ) ); ?></span>
							<?php echo ip_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
						</button>
						<?php
						// Inside the item for tab order; positioned against .ip-header__bar (header.css).
						get_template_part( 'template-parts/mega-menu', null, array(
							'id'    => $ip_mega_id,
							'title' => $ip_plates['title'],
							'url'   => $ip_plates['url'],
							'items' => $ip_plates['items'],
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

		<div class="ip-header__tools">
			<?php ip_header_search_form( 'ip-search-header' ); ?>

			<?php if ( function_exists( 'ip_account_menu_enabled' ) && ip_account_menu_enabled() ) : ?>
				<?php // Account quick menu (inc/account-menu.php): disclosure button, panel opens on click and, with a mouse, on hover (site.js). ?>
				<div class="ip-account" data-ip-account>
					<button type="button" class="ip-header__icon ip-header__account" aria-expanded="false" aria-controls="ip-account-panel" aria-label="<?php echo esc_attr( is_user_logged_in() ? __( 'Mein Konto, Menü', 'infoplus' ) : __( 'Anmelden, Menü', 'infoplus' ) ); ?>">
						<?php echo ip_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
					</button>
					<div class="ip-account__panel" id="ip-account-panel">
						<div class="ip-account__box ip-panel">
							<?php get_template_part( 'template-parts/account-menu', null, array( 'id' => 'ip-acct-panel', 'context' => 'panel' ) ); ?>
						</div>
					</div>
				</div>
			<?php else : ?>
				<a class="ip-header__icon ip-header__account" href="<?php echo esc_url( $ip_account['url'] ); ?>" aria-label="<?php echo esc_attr( $ip_account['label'] ); ?>">
					<?php echo ip_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
				</a>
			<?php endif; ?>
		</div>

		<?php if ( function_exists( 'ip_minicart_enabled' ) && ip_minicart_enabled() ) : ?>
			<?php
			// Cart preview (inc/woocommerce.php ip_minicart_html()): the icon stays a link to the Warenkorb;
			// on desktop the panel opens on mouse hover and on keyboard focus (site.js, header.css).
			?>
			<div class="ip-account ip-cartbox" data-ip-cartbox data-ip-fragments="<?php echo esc_url( add_query_arg( 'wc-ajax', 'get_refreshed_fragments', home_url( '/' ) ) ); ?>">
				<a class="ip-header__icon ip-cart" href="<?php echo esc_url( ip_header_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'Warenkorb', 'infoplus' ); ?>">
					<?php echo ip_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
					<?php echo ip_cart_badge(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in ip_cart_badge(). ?>
				</a>
				<div class="ip-account__panel ip-cartbox__panel" role="region" aria-label="<?php esc_attr_e( 'Warenkorb-Vorschau', 'infoplus' ); ?>">
					<div class="ip-account__box ip-panel">
						<?php echo ip_minicart_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in ip_minicart_html(). ?>
					</div>
				</div>
			</div>
		<?php else : ?>
			<a class="ip-header__icon ip-cart" href="<?php echo esc_url( ip_header_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'Warenkorb', 'infoplus' ); ?>">
				<?php echo ip_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
				<?php echo ip_cart_badge(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in ip_cart_badge(). ?>
			</a>
		<?php endif; ?>

		<button type="button" class="ip-header__icon ip-burger" data-ip-open="ip-mobile-menu" aria-haspopup="dialog" aria-controls="ip-mobile-menu" aria-label="<?php esc_attr_e( 'Menü', 'infoplus' ); ?>">
			<?php echo ip_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
		</button>

	</div>
</header>
<?php
get_template_part( 'template-parts/mobile-menu', null, array(
	'tree'    => ip_header_menu_tree(),
	'account' => $ip_account,
) );
