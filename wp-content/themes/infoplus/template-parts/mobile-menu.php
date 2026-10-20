<?php
/**
 * Mobile menu drawer (below 1101 px). Replaces Elementor popup 141
 * "Mobile Menu": Very Light Grey panel sliding in from the right, 90vw on
 * phones and 80vw on tablets, full height; close icon top right, then product
 * search, the 'mobile' menu (uppercase 600, Namensschilder expands in place),
 * a Dark Grey divider and a row with the account and cart icons.
 *
 * Native <dialog> opened with showModal() by the burger (site.js): focus trap,
 * Escape and inert page come from the browser.
 *
 * $args:
 * - plates  (array) ip_header_nameplates(), used when no 'mobile' menu is assigned.
 * - links   (array) [ label, url ] pairs after Namensschilder, same fallback.
 * - account (array) ip_header_account().
 */

defined( 'ABSPATH' ) || exit;

$ip_args    = wp_parse_args( $args ?? array(), array(
	'plates'  => array( 'url' => home_url( '/namensschilder/' ), 'title' => __( 'Namensschilder', 'infoplus' ), 'items' => array() ),
	'links'   => array(),
	'account' => array( 'url' => home_url( '/log-in/' ), 'label' => __( 'Anmelden', 'infoplus' ) ),
) );
$ip_plates  = $ip_args['plates'];
$ip_toggle  = static function ( $label ) {
	return '<button type="button" class="ip-drawer__sub-toggle" aria-expanded="false">'
		. '<span class="ip-sr">' . esc_html( sprintf( /* translators: %s: menu item */ __( 'Untermenü %s', 'infoplus' ), $label ) ) . '</span>'
		. ip_icon( 'chevron' )
		. '</button>';
};

/*
 * Disclosure button after every top-level item that has children. Added only
 * around this one menu call, so other menus are untouched.
 */
$ip_add_toggle = static function ( $output, $item, $depth, $menu_args ) use ( $ip_toggle ) {
	if ( 0 === (int) $depth && isset( $menu_args->theme_location ) && 'mobile' === $menu_args->theme_location
		&& in_array( 'menu-item-has-children', (array) $item->classes, true ) ) {
		$output .= $ip_toggle( $item->title );
	}
	return $output;
};
?>
<dialog class="ip-drawer" id="ip-mobile-menu" aria-label="<?php esc_attr_e( 'Menü', 'infoplus' ); ?>">
	<div class="ip-drawer__top">
		<button type="button" class="ip-drawer__close" data-ip-close aria-label="<?php esc_attr_e( 'Schließen', 'infoplus' ); ?>" autofocus>
			<?php echo ip_header_svg( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
		</button>
	</div>

	<div class="ip-drawer__body">
		<?php ip_header_search_form( 'ip-search-drawer' ); ?>

		<nav class="ip-drawer__nav" aria-label="<?php esc_attr_e( 'Mobilmenü', 'infoplus' ); ?>">
			<?php if ( has_nav_menu( 'mobile' ) ) : ?>
				<?php
				add_filter( 'walker_nav_menu_start_el', $ip_add_toggle, 10, 4 );
				ip_menu( 'mobile', array(
					'menu_class' => 'ip-drawer__menu',
					'depth'      => 2,
				) );
				remove_filter( 'walker_nav_menu_start_el', $ip_add_toggle, 10 );
				?>
			<?php else : ?>
				<ul class="ip-drawer__menu">
					<?php $ip_is_current = ip_header_is_current( $ip_plates['url'] ); ?>
					<li class="menu-item<?php echo $ip_plates['items'] ? ' menu-item-has-children' : ''; ?><?php echo $ip_is_current ? ' current-menu-item' : ''; ?>">
						<a href="<?php echo esc_url( $ip_plates['url'] ); ?>"<?php echo $ip_is_current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $ip_plates['title'] ); ?></a>
						<?php if ( $ip_plates['items'] ) : ?>
							<?php echo $ip_toggle( $ip_plates['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the closure. ?>
							<ul class="sub-menu">
								<?php foreach ( $ip_plates['items'] as $ip_item ) : ?>
									<li class="menu-item<?php echo $ip_item['current'] ? ' current-menu-item' : ''; ?>">
										<a href="<?php echo esc_url( $ip_item['url'] ); ?>"<?php echo $ip_item['current'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $ip_item['title'] ); ?></a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</li>
					<?php foreach ( $ip_args['links'] as $ip_link ) : ?>
						<?php $ip_is_current = ip_header_is_current( $ip_link[1] ); ?>
						<li class="menu-item<?php echo $ip_is_current ? ' current-menu-item' : ''; ?>"><a href="<?php echo esc_url( $ip_link[1] ); ?>"<?php echo $ip_is_current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $ip_link[0] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</nav>

		<hr class="ip-drawer__divider">

		<div class="ip-drawer__actions">
			<a class="ip-drawer__icon" href="<?php echo esc_url( $ip_args['account']['url'] ); ?>" aria-label="<?php echo esc_attr( $ip_args['account']['label'] ); ?>">
				<?php echo ip_header_svg( 'person' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
			</a>
			<a class="ip-drawer__icon ip-cart" href="<?php echo esc_url( ip_header_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'Warenkorb', 'infoplus' ); ?>">
				<?php echo ip_header_svg( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
				<?php echo ip_cart_badge(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in ip_cart_badge(). ?>
			</a>
		</div>
	</div>
</dialog>
