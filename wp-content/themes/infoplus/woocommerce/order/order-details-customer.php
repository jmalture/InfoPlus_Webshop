<?php
/**
 * Addresses of an order (order view and order confirmation): billing and,
 * when the order has its own shipping address, shipping, as two small cards.
 * Phone and e-mail in the body text size (the Suite's thank-you block showed
 * the phone larger). Theme override, hooks as in WooCommerce's template
 * (additional checkout fields render through them).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.7.0
 */

defined( 'ABSPATH' ) || exit;

$show_shipping = ! wc_ship_to_billing_address_only() && $order->needs_shipping_address();
?>
<section class="woocommerce-customer-details ip-order-customer" aria-label="<?php esc_attr_e( 'Adressen', 'infoplus' ); ?>">

	<div class="ip-order-customer__grid<?php echo $show_shipping ? ' ip-order-customer__grid--2' : ''; ?>">
		<div class="ip-acc-card ip-order-address woocommerce-column woocommerce-column--billing-address">
			<h2 class="woocommerce-column__title ip-acc-card__title"><?php esc_html_e( 'Rechnungsadresse', 'infoplus' ); ?></h2>

			<address class="ip-address">
				<?php echo wp_kses_post( $order->get_formatted_billing_address( esc_html__( 'Keine Angabe', 'infoplus' ) ) ); ?>

				<?php if ( $order->get_billing_phone() ) : ?>
					<p class="woocommerce-customer-details--phone ip-address__line"><?php echo ip_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><span class="ip-sr"><?php esc_html_e( 'Telefon:', 'infoplus' ); ?></span> <?php echo esc_html( $order->get_billing_phone() ); ?></p>
				<?php endif; ?>

				<?php if ( $order->get_billing_email() ) : ?>
					<p class="woocommerce-customer-details--email ip-address__line"><?php echo ip_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><span class="ip-sr"><?php esc_html_e( 'E-Mail:', 'infoplus' ); ?></span> <?php echo esc_html( $order->get_billing_email() ); ?></p>
				<?php endif; ?>

				<?php
					/**
					 * Action hook fired after an address in the order customer details.
					 *
					 * @since 8.7.0
					 * @param string $address_type Type of address (billing or shipping).
					 * @param WC_Order $order Order object.
					 */
					do_action( 'woocommerce_order_details_after_customer_address', 'billing', $order );
				?>
			</address>
		</div>

		<?php if ( $show_shipping ) : ?>
			<div class="ip-acc-card ip-order-address woocommerce-column woocommerce-column--shipping-address">
				<h2 class="woocommerce-column__title ip-acc-card__title"><?php esc_html_e( 'Lieferadresse', 'infoplus' ); ?></h2>
				<address class="ip-address">
					<?php echo wp_kses_post( $order->get_formatted_shipping_address( esc_html__( 'Keine Angabe', 'infoplus' ) ) ); ?>

					<?php if ( $order->get_shipping_phone() ) : ?>
						<p class="woocommerce-customer-details--phone ip-address__line"><?php echo ip_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><span class="ip-sr"><?php esc_html_e( 'Telefon:', 'infoplus' ); ?></span> <?php echo esc_html( $order->get_shipping_phone() ); ?></p>
					<?php endif; ?>

					<?php
						/** This action is documented above. */
						do_action( 'woocommerce_order_details_after_customer_address', 'shipping', $order );
					?>
				</address>
			</div>
		<?php endif; ?>
	</div>

	<?php do_action( 'woocommerce_order_details_after_customer_details', $order ); ?>

</section>
