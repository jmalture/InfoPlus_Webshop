<?php
/**
 * Order items and totals (order view in the account and order
 * confirmation): a white card with one row per item (picture, name, model and
 * option meta, quantity, line total) and the totals WooCommerce computes for
 * the order (subtotal, shipping only when the order has shipping, fees,
 * discounts, VAT, total). The payment method row is left out: both views
 * show it in their summary, like the order actions. Then the addresses
 * (order/order-details-customer.php). Theme override, hooks as in
 * WooCommerce's template.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.1.0
 *
 * @var bool $show_downloads Controls whether the downloads table should be rendered.
 */

 // phpcs:disable WooCommerce.Commenting.CommentHooks.MissingHookComment

defined( 'ABSPATH' ) || exit;

$order = wc_get_order( $order_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

if ( ! $order ) {
	return;
}

$order_items        = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
$show_purchase_note = $order->has_status( apply_filters( 'woocommerce_purchase_note_order_statuses', array( 'completed', 'processing' ) ) );
$downloads          = $order->get_downloadable_items();

// We make sure the order belongs to the user. This will also be true if the user is a guest, and the order belongs to a guest (userID === 0).
$show_customer_details = $order->get_user_id() === get_current_user_id();

if ( $show_downloads ) {
	wc_get_template(
		'order/order-downloads.php',
		array(
			'downloads'  => $downloads,
			'show_title' => true,
		)
	);
}

$ip_totals = $order->get_order_item_totals();
unset( $ip_totals['payment_method'] );
?>
<section class="woocommerce-order-details ip-acc-card ip-order-items" aria-labelledby="ip-order-items-title">
	<?php do_action( 'woocommerce_order_details_before_order_table', $order ); ?>

	<h2 class="woocommerce-order-details__title ip-acc-card__title" id="ip-order-items-title"><?php esc_html_e( 'Bestellte Artikel', 'infoplus' ); ?></h2>

	<table class="woocommerce-table woocommerce-table--order-details shop_table order_details ip-order-table">
		<thead class="ip-sr">
			<tr>
				<th class="woocommerce-table__product-name product-name" scope="col"><?php esc_html_e( 'Artikel', 'infoplus' ); ?></th>
				<th class="woocommerce-table__product-table product-total" scope="col"><?php esc_html_e( 'Summe', 'infoplus' ); ?></th>
			</tr>
		</thead>

		<tbody>
			<?php
			do_action( 'woocommerce_order_details_before_order_table_items', $order );

			foreach ( $order_items as $item_id => $item ) {
				$product = $item->get_product();

				wc_get_template(
					'order/order-details-item.php',
					array(
						'order'              => $order,
						'item_id'            => $item_id,
						'item'               => $item,
						'show_purchase_note' => $show_purchase_note,
						'purchase_note'      => $product ? $product->get_purchase_note() : '',
						'product'            => $product,
					)
				);
			}

			do_action( 'woocommerce_order_details_after_order_table_items', $order );
			?>
		</tbody>

		<tfoot class="ip-order-table__totals">
			<?php foreach ( $ip_totals as $key => $total ) : ?>
				<tr class="ip-order-table__total ip-order-table__total--<?php echo esc_attr( sanitize_html_class( $key ) ); ?>">
					<th scope="row"><?php echo esc_html( rtrim( $total['label'], ': ' ) ); ?></th>
					<td><?php echo wp_kses_post( $total['value'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( $order->get_customer_note() ) : ?>
				<tr class="ip-order-table__total ip-order-table__total--note">
					<th scope="row"><?php esc_html_e( 'Ihre Anmerkung', 'infoplus' ); ?></th>
					<td>
						<?php
						$customer_note = wc_wptexturize_order_note( $order->get_customer_note() );
						echo wp_kses( nl2br( $customer_note ), array( 'br' => array() ) );
						?>
					</td>
				</tr>
			<?php endif; ?>
		</tfoot>
	</table>

	<?php do_action( 'woocommerce_order_details_after_order_table', $order ); ?>
</section>

<?php
/**
 * Action hook fired after the order details.
 *
 * @since 4.4.0
 * @param WC_Order $order Order data.
 */
do_action( 'woocommerce_after_order_details', $order );

if ( $show_customer_details ) {
	wc_get_template( 'order/order-details-customer.php', array( 'order' => $order ) );
}
