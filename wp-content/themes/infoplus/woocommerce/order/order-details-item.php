<?php
/**
 * One order line: picture (the Suite's model picture when the line has one),
 * name, SKU, model and option meta (technical editor keys hidden, the design
 * name labelled "Design"), quantity and line total. Theme override; the item
 * hooks and filters of WooCommerce's template stay (Germanized, Suite).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) {
	return;
}

$ip_sku = $product ? $product->get_sku() : '';
?>
<tr class="<?php echo esc_attr( apply_filters( 'woocommerce_order_item_class', 'woocommerce-table__line-item order_item ip-order-item', $item, $order ) ); ?>">

	<td class="woocommerce-table__product-name product-name">
		<div class="ip-order-item__row">
			<span class="ip-order-item__thumb"><?php echo wp_kses_post( ip_account_item_thumbnail( $item, $product ) ); ?></span>
			<div class="ip-order-item__body">
				<p class="ip-order-item__name">
					<?php
					$is_visible        = $product && $product->is_visible();
					$product_permalink = apply_filters( 'woocommerce_order_item_permalink', $is_visible ? $product->get_permalink( $item ) : '', $item, $order );

					echo wp_kses_post( apply_filters( 'woocommerce_order_item_name', $product_permalink ? sprintf( '<a href="%s">%s</a>', $product_permalink, $item->get_name() ) : $item->get_name(), $item, $is_visible ) );
					?>
				</p>
				<?php if ( $ip_sku ) : ?>
					<p class="ip-order-item__sku"><?php esc_html_e( 'Art.-Nr.', 'infoplus' ); ?> <?php echo esc_html( $ip_sku ); ?></p>
				<?php endif; ?>
				<?php
				$qty          = $item->get_quantity();
				$refunded_qty = $order->get_qty_refunded_for_item( $item_id );

				if ( $refunded_qty ) {
					$qty_display = '<del>' . esc_html( $qty ) . '</del> <ins>' . esc_html( $qty - ( $refunded_qty * -1 ) ) . '</ins>';
				} else {
					$qty_display = esc_html( $qty );
				}

				echo apply_filters( 'woocommerce_order_item_quantity_html', ' <strong class="product-quantity">' . sprintf( '%s&nbsp;%s', $qty_display, esc_html__( 'Stück', 'infoplus' ) ) . '</strong>', $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $order, false );

				add_filter( 'woocommerce_order_item_get_formatted_meta_data', 'ip_account_filter_item_meta', 99 );
				wc_display_item_meta( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				remove_filter( 'woocommerce_order_item_get_formatted_meta_data', 'ip_account_filter_item_meta', 99 );

				do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $order, false );
				?>
			</div>
		</div>
	</td>

	<td class="woocommerce-table__product-total product-total">
		<?php echo $order->get_formatted_line_subtotal( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</td>

</tr>

<?php if ( $show_purchase_note && $purchase_note ) : ?>

<tr class="woocommerce-table__product-purchase-note product-purchase-note">

	<td colspan="2"><?php echo wpautop( do_shortcode( wp_kses_post( $purchase_note ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>

</tr>

<?php endif; ?>
