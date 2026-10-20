<?php
/**
 * Below the configurator: the product description (Elementor widget
 * "Product Content" 1fee0fa) and the "Individuelle Anfrage" section
 * (containers fcd96be / d809b0f / aa678ab of template 2190), each as a card.
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
	<section class="ip-card ip-request" id="special_section" aria-labelledby="ip-request-title">
		<div class="ip-request__head">
			<h2 class="ip-request__title" id="ip-request-title"><?php echo esc_html( ip_product_text( 'request_title' ) ); ?></h2>
			<p class="ip-sub"><?php echo esc_html( ip_product_text( 'request_text' ) ); ?></p>
		</div>
		<?php if ( $ip_items ) : ?>
			<ul class="ip-request__list">
				<?php foreach ( $ip_items as $ip_item ) : ?>
					<li class="ip-request__item">
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
	</section>
</div>
