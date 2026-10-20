<?php
/**
 * My Account navigation: icon + label rows in the aside from 1101 px, one
 * horizontal swipe row of pills below. "Abmelden" sits apart at the end.
 * Theme override; same menu source and classes as WooCommerce's template, so
 * plugins that add items keep working.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_account_navigation' );
?>

<nav class="woocommerce-MyAccount-navigation ip-acc-nav" aria-label="<?php esc_attr_e( 'Kontobereiche', 'infoplus' ); ?>">
	<ul class="ip-acc-nav__list">
		<?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) : ?>
			<li class="<?php echo wc_get_account_menu_item_classes( $endpoint ); ?> ip-acc-nav__item<?php echo 'customer-logout' === $endpoint ? ' ip-acc-nav__item--logout' : ''; ?>">
				<a class="ip-acc-nav__link" href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>" <?php echo wc_is_current_account_menu_item( $endpoint ) ? 'aria-current="page"' : ''; ?>>
					<?php echo ip_icon( ip_account_menu_icon( $endpoint ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
					<span><?php echo esc_html( $label ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>
