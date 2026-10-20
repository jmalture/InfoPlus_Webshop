<?php
/**
 * Order view in the account (/account/view-order/{id}/): summary card with
 * status, date, payment method, order reference and total, the order actions
 * (invoice PDF from the PDF plugin, "Erneut bestellen" where safe, pay) and a
 * print button; then the order updates and, through woocommerce_view_order,
 * the items, totals and addresses (woocommerce/order/order-details*.php).
 * Theme override. The H1 above says "Bestellung #…" (inc/account.php).
 *
 * Replaces the Suite's OrderDetails widget on the old /order/ page.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.6.0
 */

defined( 'ABSPATH' ) || exit;

$notes      = $order->get_customer_order_notes();
$ip_actions = ip_account_order_actions( $order );
?>
<div class="ip-order ip-order--view">
	<section class="ip-acc-card ip-order__summary" aria-label="<?php esc_attr_e( 'Bestellübersicht', 'infoplus' ); ?>">
		<p class="ip-sr">
			<?php
			echo wp_kses_post(
				/**
				 * Filter to modify order details status text.
				 *
				 * @since 10.1.0
				 */
				apply_filters(
					'woocommerce_order_details_status',
					sprintf(
						/* translators: 1: order number 2: order date 3: order status */
						esc_html__( 'Bestellung #%1$s vom %2$s, Status: %3$s.', 'infoplus' ),
						'<mark class="order-number">' . esc_html( $order->get_order_number() ) . '</mark>',
						'<mark class="order-date">' . esc_html( wc_format_datetime( $order->get_date_created() ) ) . '</mark>',
						'<mark class="order-status">' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</mark>'
					),
					$order
				)
			);
			?>
		</p>
		<?php ip_account_print_facts( ip_account_order_facts( $order, 'view' ) ); ?>

		<div class="ip-order__actions">
			<?php
			foreach ( $ip_actions as $ip_key => $ip_action ) {
				echo ip_account_action_link( $ip_key, $ip_action, $order, in_array( $ip_key, array( 'pay', 'invoice' ), true ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper.
			}
			?>
			<button type="button" class="ip-btn ip-btn--ghost ip-btn--sm ip-js-only" data-ip-print>
				<?php echo ip_icon( 'printer' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
				<span><?php esc_html_e( 'Drucken', 'infoplus' ); ?></span>
			</button>
			<a class="ip-order__back" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>"><?php echo ip_icon( 'arrow-left' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php esc_html_e( 'Alle Bestellungen', 'infoplus' ); ?></a>
		</div>
	</section>

	<?php if ( $notes ) : ?>
		<section class="ip-acc-card ip-order__notes" aria-labelledby="ip-order-notes-title">
			<h2 class="ip-acc-card__title" id="ip-order-notes-title"><?php esc_html_e( 'Neuigkeiten zu Ihrer Bestellung', 'infoplus' ); ?></h2>
			<ol class="woocommerce-OrderUpdates commentlist notes ip-notes">
				<?php foreach ( $notes as $note ) : ?>
					<li class="woocommerce-OrderUpdate comment note">
						<p class="woocommerce-OrderUpdate-meta meta"><time datetime="<?php echo esc_attr( gmdate( 'c', strtotime( $note->comment_date_gmt ) ) ); ?>"><?php echo esc_html( date_i18n( __( 'j. F Y, H:i', 'infoplus' ), strtotime( $note->comment_date ) ) ); ?></time></p>
						<div class="woocommerce-OrderUpdate-description description">
							<?php echo wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) ); ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
	<?php endif; ?>

	<?php do_action( 'woocommerce_view_order', $order_id ); ?>
</div>
