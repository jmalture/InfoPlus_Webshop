<?php
/**
 * Sticky action bar for phones and tablets (below 1101 px): Gesamtpreis and
 * quantity, plus the primary action. It mirrors the real buttons and does not
 * replace them: assets/js/product.js copies the label, the visible / disabled
 * state and the total from the configurator and forwards the click to the
 * button that is currently shown (#editor, or #anfrage for models that are
 * only requestable). It shows only while the real buttons are out of view.
 * Hidden without JS (the attribute is removed by product.js).
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
		<span class="ip-actionbar__label"><?php echo esc_html( ip_product_text( 'total_label' ) ); ?></span>
		<span class="ip-actionbar__value">
			<span data-ip-actionbar-total><?php echo esc_html( ip_product_text( 'price_zero' ) ); ?></span>
			<span class="ip-actionbar__qty" data-ip-actionbar-qty></span>
		</span>
	</div>
	<button type="button" class="ip-btn ip-actionbar__btn" data-ip-actionbar-btn>
		<span data-ip-actionbar-label><?php echo esc_html( ip_product_text( 'editor_label' ) ); ?></span>
	</button>
</div>
