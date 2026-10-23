<?php
/**
 * WooCommerce integration. Day 1 scope: theme support and the header cart
 * count. Template overrides live in /woocommerce and arrive with the shop
 * steps of the rebuild map (rows 6 to 14).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', function () {
	add_theme_support( 'woocommerce' );
} );

/** Cart item count, 0 when WooCommerce or the cart is unavailable. */
function ip_cart_count() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0;
	}
	return (int) WC()->cart->get_cart_contents_count();
}

/** Header cart badge markup; also returned as a cart fragment so it updates after add-to-cart. */
function ip_cart_badge() {
	$count = ip_cart_count();
	return sprintf(
		'<span class="ip-cart__count%s" data-ip-cart-count>%s</span>',
		$count ? '' : ' is-empty',
		esc_html( $count )
	);
}

add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
	$fragments['[data-ip-cart-count]'] = ip_cart_badge();
	$fragments['[data-ip-minicart]']   = ip_minicart_html();
	return $fragments;
} );

/** True when the header shows the cart preview (desktop hover panel). Not on the cart and checkout pages. */
function ip_minicart_enabled() {
	$on = function_exists( 'WC' ) && function_exists( 'wc_get_cart_url' )
		&& ! ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) );
	return (bool) apply_filters( 'ip_minicart_enabled', $on );
}

/**
 * Content of the header cart preview (template-parts/site-header.php). Same
 * look as the account quick menu: the first lines of the Warenkorb with
 * picture, model, quantity and line total, the subtotal and the two ways on
 * (Warenkorb, Kasse); a short empty state with the way into the shop and
 * the Namensschild-Berater.
 *
 * Also a cart fragment: site.js asks WooCommerce for fresh fragments
 * (?wc-ajax=get_refreshed_fragments) the first time the panel opens, so a
 * page from the page cache never shows somebody else's or an old cart.
 */
function ip_minicart_html() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return '<div class="ip-minicart" data-ip-minicart></div>';
	}
	$cart  = WC()->cart;
	$items = $cart->get_cart();
	$max   = (int) apply_filters( 'ip_minicart_lines', 4 );
	$tax   = 'incl' === get_option( 'woocommerce_tax_display_cart' ) ? __( 'inkl. MwSt., zzgl. Versand', 'infoplus' ) : __( 'zzgl. MwSt. und Versand', 'infoplus' );
	ob_start();
	?>
	<div class="ip-minicart" data-ip-minicart>
		<?php if ( ! $items ) : ?>
			<div class="ip-minicart__empty">
				<span class="ip-tile"><?php echo ip_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
				<p class="ip-minicart__empty-title"><?php esc_html_e( 'Ihr Warenkorb ist leer', 'infoplus' ); ?></p>
				<p class="ip-minicart__empty-text"><?php esc_html_e( 'Finden Sie das passende Namensschild für Ihr Team.', 'infoplus' ); ?></p>
			</div>
			<div class="ip-minicart__actions">
				<a class="ip-btn" href="<?php echo esc_url( function_exists( 'ip_shop_plates_url' ) ? ip_shop_plates_url() : home_url( '/namensschilder/' ) ); ?>"><?php esc_html_e( 'Namensschilder ansehen', 'infoplus' ); ?></a>
				<?php $ip_finder = function_exists( 'ip_finder_url' ) ? ip_finder_url() : ''; ?>
				<?php if ( $ip_finder ) : ?>
					<a class="ip-btn ip-btn--ghost" href="<?php echo esc_url( $ip_finder ); ?>"><?php esc_html_e( 'Berater starten', 'infoplus' ); ?></a>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<div class="ip-minicart__head">
				<p class="ip-minicart__title"><?php esc_html_e( 'Warenkorb', 'infoplus' ); ?></p>
				<span class="ip-pill">
					<?php
					// Lines, not pieces: one line of 10 badges is "1 Position" (the icon badge shows the pieces).
					$lines = count( $items );
					/* translators: %d: number of cart lines */
					echo esc_html( sprintf( _n( '%d Position', '%d Positionen', $lines, 'infoplus' ), $lines ) );
					?>
				</span>
			</div>
			<ul class="ip-minicart__list" role="list">
				<?php
				$i = 0;
				foreach ( $items as $key => $item ) :
					if ( ++$i > $max ) {
						break;
					}
					$product = $item['data'] ?? null;
					if ( ! $product instanceof WC_Product ) {
						continue;
					}
					$qty   = (int) $item['quantity'];
					$title = $product->is_type( 'variation' ) ? get_the_title( $product->get_parent_id() ) : $product->get_name();
					$model = '';
					if ( ! empty( $item['variation']['attribute_pa_model'] ) ) {
						$term  = get_term_by( 'slug', $item['variation']['attribute_pa_model'], 'pa_model' );
						$model = $term ? $term->name : '';
					}
					if ( ! empty( $item['display'] ) ) {
						$model = (string) $item['display']; // Line from the online editor (InfoplusAPI cart/updateCart).
					}
					if ( ! empty( $item['ife_thumbnail_url'] ) ) {
						$thumb = '<img src="' . esc_url( $item['ife_thumbnail_url'] ) . '" alt="" loading="lazy">';
					} else {
						$thumb = apply_filters( 'woocommerce_cart_item_thumbnail', $product->get_image( 'woocommerce_gallery_thumbnail', array( 'alt' => '', 'loading' => 'lazy' ) ), $item, $key );
					}
					$link = $product->is_visible() ? $product->get_permalink( $item ) : '';
					?>
					<li class="ip-minicart__item">
						<span class="ip-minicart__thumb"><?php echo wp_kses_post( $thumb ); ?></span>
						<span class="ip-minicart__main">
							<?php if ( $link ) : ?>
								<a class="ip-minicart__name" href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( wp_strip_all_tags( $title ) ); ?></a>
							<?php else : ?>
								<span class="ip-minicart__name"><?php echo esc_html( wp_strip_all_tags( $title ) ); ?></span>
							<?php endif; ?>
							<?php if ( $model ) : ?>
								<span class="ip-minicart__model"><?php echo esc_html( wp_strip_all_tags( html_entity_decode( $model, ENT_QUOTES, 'UTF-8' ) ) ); ?></span>
							<?php endif; ?>
							<span class="ip-minicart__qty">
								<?php
								/* translators: %d: quantity */
								echo esc_html( sprintf( __( '%d Stück', 'infoplus' ), $qty ) );
								?>
							</span>
						</span>
						<span class="ip-minicart__price"><?php echo wp_kses_post( $cart->get_product_subtotal( $product, $qty ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( count( $items ) > $max ) : ?>
				<p class="ip-minicart__more">
					<?php
					$rest = count( $items ) - $max;
					/* translators: %d: number of further cart lines */
					echo esc_html( sprintf( _n( '+ %d weitere Position', '+ %d weitere Positionen', $rest, 'infoplus' ), $rest ) );
					?>
				</p>
			<?php endif; ?>
			<div class="ip-minicart__sum">
				<span><?php esc_html_e( 'Zwischensumme', 'infoplus' ); ?><small><?php echo esc_html( $tax ); ?></small></span>
				<b><?php echo wp_kses_post( $cart->get_cart_subtotal() ); ?></b>
			</div>
			<div class="ip-minicart__actions">
				<a class="ip-btn ip-btn--ghost" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php esc_html_e( 'Zum Warenkorb', 'infoplus' ); ?></a>
				<a class="ip-btn" href="<?php echo esc_url( wc_get_checkout_url() ); ?>"><?php esc_html_e( 'Zur Kasse', 'infoplus' ); ?><?php echo function_exists( 'ip_shop_arrow' ) ? ip_shop_arrow() : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></a>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}
