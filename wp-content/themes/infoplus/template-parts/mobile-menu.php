<?php
/**
 * Mobile menu drawer (below 1101 px), replacing Elementor popup 141 with the
 * DESIGN.md look: white panel from the right with a rounded left edge, a
 * small "Menü" kicker and a round close button, product search, the 'mobile'
 * menu in calm 20 px type, Namensschilder expanding into a 2-column grid of
 * small product cards, and account + cart as two buttons pinned to the bottom.
 *
 * Native <dialog> opened with showModal() by the burger (site.js): focus trap,
 * Escape and inert page come from the browser.
 *
 * $args:
 * - tree    (array) ip_header_menu_tree().
 * - account (array) ip_header_account().
 */

defined( 'ABSPATH' ) || exit;

$ip_args = wp_parse_args( $args ?? array(), array(
	'tree'    => array(),
	'account' => array( 'url' => home_url( '/log-in/' ), 'label' => __( 'Anmelden', 'infoplus' ) ),
) );
?>
<dialog class="ip-drawer" id="ip-mobile-menu" aria-labelledby="ip-drawer-title">
	<div class="ip-drawer__head">
		<span class="ip-kicker" id="ip-drawer-title"><?php esc_html_e( 'Menü', 'infoplus' ); ?></span>
		<button type="button" class="ip-header__icon ip-drawer__close" data-ip-close aria-label="<?php esc_attr_e( 'Schließen', 'infoplus' ); ?>">
			<?php echo ip_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
		</button>
	</div>

	<div class="ip-drawer__scroll">
		<?php ip_header_search_form( 'ip-search-drawer' ); ?>

		<?php if ( $ip_args['tree'] ) : ?>
			<nav class="ip-drawer__nav" aria-label="<?php esc_attr_e( 'Mobilmenü', 'infoplus' ); ?>">
				<ul class="ip-drawer__menu">
					<?php foreach ( $ip_args['tree'] as $ip_n => $ip_entry ) : ?>
						<?php
						$ip_kids   = $ip_entry['children'];
						$ip_sub_id = 'ip-drawer-sub-' . (int) $ip_n;
						$ip_active = $ip_entry['current'] || in_array( true, wp_list_pluck( $ip_kids, 'current' ), true );
						// Product children render as cards; any other children as a plain list.
						$ip_cards  = $ip_entry['plates'] || in_array( true, wp_list_pluck( $ip_kids, 'product' ), true );
						?>
						<li class="ip-drawer__item<?php echo $ip_active ? ' is-active' : ''; ?><?php echo ( $ip_kids && $ip_active ) ? ' is-open' : ''; ?>">
							<div class="ip-drawer__row">
								<a class="ip-drawer__link" href="<?php echo esc_url( $ip_entry['url'] ); ?>"<?php echo ! empty( $ip_entry['plates'] ) ? ' data-ip-nav-plates' : ''; ?><?php echo $ip_entry['current'] ? ' aria-current="page"' : ''; ?>>
									<?php echo esc_html( $ip_entry['title'] ); ?>
									<?php if ( $ip_cards && $ip_kids ) : ?>
										<span class="ip-pill" aria-hidden="true"><?php echo esc_html( count( $ip_kids ) ); ?></span>
									<?php endif; ?>
								</a>
								<?php if ( $ip_kids ) : ?>
									<button type="button" class="ip-drawer__sub-toggle" aria-expanded="<?php echo ( $ip_kids && $ip_active ) ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $ip_sub_id ); ?>">
										<span class="ip-sr"><?php echo esc_html( sprintf( /* translators: %s: menu item */ __( 'Untermenü %s', 'infoplus' ), $ip_entry['title'] ) ); ?></span>
										<?php echo ip_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
									</button>
								<?php endif; ?>
							</div>

							<?php if ( $ip_kids ) : ?>
								<ul class="<?php echo $ip_cards ? 'ip-drawer__cards' : 'ip-drawer__sub'; ?>" id="<?php echo esc_attr( $ip_sub_id ); ?>" role="list">
									<?php foreach ( array_values( $ip_kids ) as $ip_i => $ip_kid ) : ?>
										<li style="--i: <?php echo (int) $ip_i; ?>">
											<?php if ( $ip_cards ) : ?>
												<a class="ip-pcard ip-pcard--small" href="<?php echo esc_url( $ip_kid['url'] ); ?>"<?php echo $ip_kid['current'] ? ' aria-current="page"' : ''; ?>>
													<span class="ip-pcard__thumb"><?php echo ip_header_card_image( $ip_kid, '(min-width: 768px) 200px, 45vw' ); // phpcs:ignore WordPress.Security.EscapeOutput -- core/WooCommerce image HTML. ?></span>
													<span class="ip-pcard__name"><?php echo esc_html( $ip_kid['title'] ); ?></span>
												</a>
											<?php else : ?>
												<a class="ip-drawer__sublink" href="<?php echo esc_url( $ip_kid['url'] ); ?>"<?php echo $ip_kid['current'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $ip_kid['title'] ); ?></a>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>

		<?php if ( function_exists( 'ip_account_menu_enabled' ) && ip_account_menu_enabled() ) : ?>
			<?php // The account quick menu (inc/account-menu.php) as a drawer submenu: tap only, no hover. ?>
			<div class="ip-drawer__item ip-drawer__item--account">
				<div class="ip-drawer__row">
					<a class="ip-drawer__link" href="<?php echo esc_url( $ip_args['account']['url'] ); ?>"><?php echo esc_html( $ip_args['account']['label'] ); ?></a>
					<button type="button" class="ip-drawer__sub-toggle" aria-expanded="false" aria-controls="ip-drawer-account">
						<span class="ip-sr"><?php echo esc_html( sprintf( /* translators: %s: menu item */ __( 'Untermenü %s', 'infoplus' ), $ip_args['account']['label'] ) ); ?></span>
						<?php echo ip_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
					</button>
				</div>
				<div class="ip-drawer__sub ip-drawer__account" id="ip-drawer-account">
					<?php get_template_part( 'template-parts/account-menu', null, array( 'id' => 'ip-acct-drawer', 'context' => 'drawer' ) ); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<div class="ip-drawer__foot">
		<a class="ip-btn ip-btn--ghost" href="<?php echo esc_url( $ip_args['account']['url'] ); ?>">
			<?php echo ip_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
			<?php echo esc_html( $ip_args['account']['label'] ); ?>
		</a>
		<a class="ip-btn ip-cart" href="<?php echo esc_url( ip_header_cart_url() ); ?>">
			<?php echo ip_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
			<?php esc_html_e( 'Warenkorb', 'infoplus' ); ?>
			<?php echo ip_cart_badge(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in ip_cart_badge(). ?>
		</a>
	</div>
</dialog>
