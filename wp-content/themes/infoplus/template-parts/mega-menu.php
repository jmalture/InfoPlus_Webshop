<?php
/**
 * Namensschilder mega menu panel (desktop only, 1101 px and up).
 * Port of the Elementor mega-menu dropdown in header 17: a 6-column grid of
 * product tiles, each an 80 px high contained image over a centred 16 px bold
 * title in Dark Grey; the image scales to 1.1 on hover.
 *
 * Opened by the toggle button in site-header.php (disclosure pattern, see
 * site.js). Hidden until opened, so it stays out of the tab order.
 *
 * $args:
 * - id    (string) Panel id, referenced by the button's aria-controls.
 * - items (array)  From ip_header_nameplates(): title, url, image, product, current.
 * - label (string) Accessible name of the panel.
 */

defined( 'ABSPATH' ) || exit;

$ip_args = wp_parse_args( $args ?? array(), array(
	'id'    => 'ip-mega-panel',
	'items' => array(),
	'label' => '',
) );

if ( ! $ip_args['items'] ) {
	return;
}

// One query for all tile images instead of one per tile.
$ip_thumb_ids = array_filter( wp_list_pluck( $ip_args['items'], 'image' ) );
if ( $ip_thumb_ids ) {
	_prime_post_caches( $ip_thumb_ids, false, true );
}
?>
<div class="ip-mega" id="<?php echo esc_attr( $ip_args['id'] ); ?>" hidden>
	<div class="ip-mega__box">
		<ul class="ip-mega__grid" role="list"<?php echo $ip_args['label'] ? ' aria-label="' . esc_attr( $ip_args['label'] ) . '"' : ''; ?>>
			<?php foreach ( $ip_args['items'] as $ip_item ) : ?>
				<?php
				$ip_img = '';
				if ( $ip_item['image'] ) {
					// 'medium' keeps the wide nameplate shapes; 'thumbnail' is a hard square crop.
					$ip_img = wp_get_attachment_image( $ip_item['image'], 'medium', false, array(
						'class'    => 'ip-mega__img',
						'alt'      => '',
						'loading'  => 'lazy',
						'decoding' => 'async',
						'sizes'    => '200px',
					) );
				} elseif ( $ip_item['product'] && function_exists( 'wc_placeholder_img' ) ) {
					// Same as the Elementor panel, which showed the WooCommerce placeholder for products without a picture.
					$ip_img = wc_placeholder_img( 'medium', array( 'class' => 'ip-mega__img', 'alt' => '', 'loading' => 'lazy' ) );
				}
				?>
				<li class="ip-mega__cell">
					<a class="ip-mega__item" href="<?php echo esc_url( $ip_item['url'] ); ?>"<?php echo $ip_item['current'] ? ' aria-current="page"' : ''; ?>>
						<?php if ( $ip_img ) : ?>
							<span class="ip-mega__media"><?php echo $ip_img; // phpcs:ignore WordPress.Security.EscapeOutput -- core/WooCommerce image HTML. ?></span>
						<?php endif; ?>
						<span class="ip-mega__title"><?php echo esc_html( $ip_item['title'] ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
