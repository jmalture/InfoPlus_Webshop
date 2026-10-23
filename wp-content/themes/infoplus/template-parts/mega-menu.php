<?php
/**
 * Namensschilder mega menu (desktop, 1101 px and up). A floating .ip-panel
 * with a caret pointing at its nav item, a grid of product cards (picture on
 * a Very Light Grey tile, name) and a footer link to the overview with the
 * model count. Cards fade in with a short stagger (--i, header.css).
 *
 * Product prices are left out on purpose: every Namensschild is a variable
 * product (up to 1,380 variations), so get_price_html() would compute
 * variation prices on every page view.
 *
 * Opened by the toggle button in site-header.php (disclosure pattern,
 * site.js). Closed state is visibility:hidden, so it is out of the tab order
 * and the accessibility tree.
 *
 * $args:
 * - id    (string) Panel id, referenced by the button's aria-controls.
 * - title (string) Menu item title, e.g. "Namensschilder".
 * - url   (string) Overview page.
 * - items (array)  Children from ip_header_menu_tree(): title, url, image, product, current.
 */

defined( 'ABSPATH' ) || exit;

$ip_args = wp_parse_args( $args ?? array(), array(
	'id'    => 'ip-mega-panel',
	'title' => '',
	'url'   => '',
	'items' => array(),
) );

if ( ! $ip_args['items'] ) {
	return;
}
$ip_count = count( $ip_args['items'] );
?>
<div class="ip-mega" id="<?php echo esc_attr( $ip_args['id'] ); ?>">
	<div class="ip-mega__box ip-panel">
		<ul class="ip-mega__grid" role="list"<?php echo $ip_args['title'] ? ' aria-label="' . esc_attr( $ip_args['title'] ) . '"' : ''; ?>>
			<?php foreach ( array_values( $ip_args['items'] ) as $ip_i => $ip_item ) : ?>
				<?php $ip_img = ip_header_card_image( $ip_item, '140px' ); ?>
				<li class="ip-mega__cell" style="--i: <?php echo (int) $ip_i; ?>">
					<a class="ip-pcard" href="<?php echo esc_url( $ip_item['url'] ); ?>"<?php echo $ip_item['current'] ? ' aria-current="page"' : ''; ?>>
						<span class="ip-pcard__thumb"><?php echo $ip_img; // phpcs:ignore WordPress.Security.EscapeOutput -- core/WooCommerce image HTML. ?></span>
						<span class="ip-pcard__name"><?php echo esc_html( $ip_item['title'] ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $ip_args['url'] ) : ?>
			<div class="ip-mega__foot">
				<a class="ip-mega__all" href="<?php echo esc_url( $ip_args['url'] ); ?>">
					<?php echo esc_html( sprintf( /* translators: %s: menu item, e.g. Namensschilder */ __( 'Alle %s ansehen', 'infoplus' ), $ip_args['title'] ) ); ?>
					<?php echo ip_header_svg( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
				</a>
				<span class="ip-pill"><?php echo esc_html( ip_header_count_label( $ip_count ) ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</div>
