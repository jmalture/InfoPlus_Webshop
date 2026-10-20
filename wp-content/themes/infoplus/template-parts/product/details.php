<?php
/**
 * Below the configurator: the product description (Elementor widget
 * "Product Content" 1fee0fa) and the "Individuelle Anfrage" section
 * (containers fcd96be / d809b0f / aa678ab of template 2190), each as a card.
 *
 * The request section is one wide panel: heading, text, the three benefits
 * as cards side by side (stacked on phones), the button, and on the side a
 * drawing of a custom contour (Sonderkontur): a standard plate outline, then
 * the custom outline drawing itself around a logo that fades in. The drawing
 * is decorative (aria-hidden). It animates once when the section scrolls into
 * view (reveal script at the end of assets/js/product.js); without JS, or
 * with prefers-reduced-motion, it shows the finished drawing.
 *
 * The button keeps its markup (class ip-btn, data-ip-inquiry-link, href from
 * ip_product_inquiry_url()) and the section keeps id="special_section".
 *
 * Not ported: the second "Sonderwünsche" block of template 2190 (heading,
 * text, icon list, media carousel of the ACF fields
 * custom_request_preview_image_1 to _4, closing text). Every element of it is
 * hidden on all devices on the live page, so visitors never saw it.
 *
 * $args:
 * - product (WC_Product)
 */

defined( 'ABSPATH' ) || exit;

$ip_product = $args['product'] ?? null;
if ( ! $ip_product instanceof WC_Product ) {
	return;
}
$ip_has_description = '' !== trim( wp_strip_all_tags( (string) $ip_product->get_description() ) );
$ip_items           = (array) ip_product_text( 'request_items' );
?>
<div class="ip-product__details">
	<?php if ( $ip_has_description ) : ?>
		<section class="ip-card ip-product-desc" aria-labelledby="ip-product-desc-title">
			<h2 class="ip-product-desc__title" id="ip-product-desc-title"><?php esc_html_e( 'Beschreibung', 'infoplus' ); // WooCommerce's own tab title in German. ?></h2>
			<div class="ip-prose ip-product-desc__body">
				<?php the_content(); ?>
			</div>
		</section>
	<?php endif; ?>

	<?php // id special_section: the old page had a menu anchor with this name here. ?>
	<section class="ip-request" id="special_section" aria-labelledby="ip-request-title" data-ip-reveal>
		<div class="ip-request__head">
			<h2 class="ip-request__title" id="ip-request-title"><?php echo esc_html( ip_product_text( 'request_title' ) ); ?></h2>
			<p class="ip-sub"><?php echo esc_html( ip_product_text( 'request_text' ) ); ?></p>
		</div>
		<?php if ( $ip_items ) : ?>
			<ul class="ip-request__list" role="list">
				<?php foreach ( array_values( $ip_items ) as $ip_i => $ip_item ) : ?>
					<li class="ip-request__item" style="--i: <?php echo (int) $ip_i; ?>">
						<span class="ip-tile"><?php echo ip_product_icon( $ip_item['icon'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
						<span class="ip-request__text">
							<strong><?php echo esc_html( $ip_item['title'] ?? '' ); ?></strong>
							<span><?php echo esc_html( $ip_item['text'] ?? '' ); ?></span>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<p class="ip-request__cta">
			<?php // Old button d034dcb opened popup 14241, like #anfrage. Same target as the inquiry button until the modal exists. ?>
			<a class="ip-btn" href="<?php echo esc_url( ip_product_inquiry_url( $ip_product ) ); ?>" data-ip-inquiry-link>
				<?php echo esc_html( ip_product_text( 'request_cta' ) ); ?>
				<?php echo ip_product_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
			</a>
		</p>
		<div class="ip-request__visual" aria-hidden="true">
			<?php
			/*
			 * Sonderkontur drawing (viewBox cropped to the drawing). Plate body 60..340 x 110..210, a round
			 * logo boss (centre 120/100, r 42) grown out of its top edge. pathLength="1"
			 * lets the CSS draw the outline with stroke-dasharray 1 / dashoffset 1 -> 0.
			 */
			?>
			<svg class="ip-contour" viewBox="40 34 320 216" width="320" height="216" focusable="false">
				<rect class="ip-contour__std" x="60" y="110" width="280" height="100" rx="12"/>
				<text class="ip-contour__note ip-contour__note--std" x="340" y="236" text-anchor="end"><?php esc_html_e( 'Standard', 'infoplus' ); ?></text>
				<path class="ip-contour__fill" d="M160.8 110H328a12 12 0 0 1 12 12v76a12 12 0 0 1-12 12H72a12 12 0 0 1-12-12v-76a12 12 0 0 1 12-12h7.2A42 42 0 1 1 160.8 110z"/>
				<g class="ip-contour__text">
					<rect x="182" y="138" width="124" height="13" rx="6.5"/>
					<rect class="ip-contour__text-sub" x="182" y="162" width="78" height="9" rx="4.5"/>
				</g>
				<circle class="ip-contour__slot" cx="120" cy="100" r="25"/>
				<g class="ip-contour__logo">
					<path d="M120 77c11 7 11 39 0 46-11-7-11-39 0-46z"/>
					<path class="ip-contour__logo-b" d="M97 100c7-11 39-11 46 0-7 11-39 11-46 0z"/>
				</g>
				<path class="ip-contour__line" d="M160.8 110H328a12 12 0 0 1 12 12v76a12 12 0 0 1-12 12H72a12 12 0 0 1-12-12v-76a12 12 0 0 1 12-12h7.2A42 42 0 1 1 160.8 110z" pathLength="1"/>
				<g class="ip-contour__nodes">
					<rect x="76.2" y="107" width="6" height="6"/>
					<rect x="157.8" y="107" width="6" height="6"/>
					<rect x="117" y="55" width="6" height="6"/>
					<rect x="337" y="157" width="6" height="6"/>
				</g>
				<g class="ip-contour__tag">
					<path class="ip-contour__leader" d="M153 72l28-20h22"/>
					<text class="ip-contour__note ip-contour__note--tag" x="208" y="56"><?php esc_html_e( 'Ihre Wunschkontur', 'infoplus' ); ?></text>
				</g>
			</svg>
		</div>
	</section>
</div>
