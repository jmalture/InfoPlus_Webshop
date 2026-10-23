<?php
/**
 * Sticky action bar for phones and tablets (below 1101 px): the same data as
 * the summary card (quantity, unit price of the current tier, total) plus the
 * primary action. It mirrors the real buttons and does not replace them:
 * assets/js/product.js fills it from the same snapshot as the summary card,
 * copies the label (aria-label with the full text) and the disabled state, and
 * forwards the click to the button of the current order type: #editor
 * (editable), the buy form's submit button (buyable, so the WooCommerce form
 * posts as usual) or #anfrage (requestable). data-ip-kind on the bar button
 * says which one. It shows only while the summary card's buttons are out of
 * view. Hidden without JS (the attribute is removed by product.js).
 *
 * $args:
 * - product (WC_Product)
 * - suite   (bool) Suite helpers available.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $args['suite'] ) ) {
	return;
}
?>
<div class="ip-actionbar" data-ip-actionbar hidden>
	<div class="ip-actionbar__sum" data-ip-actionbar-sum>
		<span class="ip-actionbar__meta" data-ip-actionbar-meta></span>
		<span class="ip-actionbar__value">
			<span class="ip-sr"><?php echo esc_html( rtrim( ip_product_text( 'total_label' ), '*' ) ); ?>: </span>
			<span data-ip-actionbar-total><?php echo esc_html( ip_product_text( 'price_zero' ) ); ?></span>
		</span>
	</div>
	<button type="button" class="ip-btn ip-actionbar__btn" data-ip-actionbar-btn>
		<span data-ip-actionbar-label><?php echo esc_html( ip_product_text( 'editor_label' ) ); ?></span>
	</button>
</div>
