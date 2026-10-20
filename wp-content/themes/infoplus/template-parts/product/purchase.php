<?php
/**
 * Step "Menge": Staffelpreise, quantity and prices. The Suite fills and drives
 * all of it: bulk-price-table.js loads the tiers for the selected variation
 * SKU, elementor-bulk-calculator.js (WooSingleProductAddons) wires the stepper,
 * clamps the quantity to the minimum, writes Einzelpreis / Gesamtpreis and
 * hides the row ([data-ip-qty-row]) for models that are only requestable.
 * The IDs qty_value / qty_minus / qty_plus are the ones the old Elementor
 * widgets had; the data-ip-qty attributes are what the Suite looks for since 0.10.26.
 *
 * The tier caption says net or gross exactly as WooCommerce is configured
 * (ip_product_tax_label()). The Einzelpreis / Gesamtpreis pair stays here for
 * the Suite to write into. In the step layout the summary card shows the same
 * values and the tax note (assets/js/product.js reads them from here), so the
 * pair and the note are visually hidden there (product.css, .ip-step--qty).
 *
 * Folding steps (summary.php): the Staffelpreise are not part of the Menge
 * step but an always visible strip above the summary card. summary.php calls
 * this file twice, with part "qty" inside the step and part "tiers" above the
 * card. bulk-price-table.js finds its component anywhere on the page
 * (.infoplus-bulk-price-table), so the place does not matter to the Suite.
 *
 * $args:
 * - product (WC_Product)
 * - suite   (bool) Suite helpers available.
 * - part    (string) "all" (default), "qty" (quantity row and note) or "tiers" (Staffelpreise only).
 */

defined( 'ABSPATH' ) || exit;

$ip_product = $args['product'] ?? null;
if ( ! $ip_product instanceof WC_Product ) {
	return;
}
$ip_tax  = ip_product_tax_label();
$ip_part = in_array( $args['part'] ?? 'all', array( 'all', 'qty', 'tiers' ), true ) ? ( $args['part'] ?? 'all' ) : 'all';

if ( 'tiers' === $ip_part ) {
	if ( ! empty( $args['suite'] ) ) {
		echo '<div class="ip-buy__tiers ip-tierstrip" data-ip-tiers>';
		echo '<p class="ip-buy__caption">' . esc_html( ip_product_text( 'tiers_caption' ) ) . ( $ip_tax ? ' <span class="ip-buy__tax">(' . esc_html( $ip_tax ) . ')</span>' : '' ) . '</p>';
		infoplus_suite_bulk_price_table( $ip_product, array(
			'columns'      => 2,
			'ab_label'     => ip_product_text( 'ab_label' ),
			'stueck_label' => ip_product_text( 'unit' ),
		) );
		echo '</div>';
	}
	return;
}
?>
<div class="ip-buy">
	<?php
	if ( 'all' === $ip_part && ! empty( $args['suite'] ) ) {
		echo '<div class="ip-buy__tiers" data-ip-tiers>';
		echo '<p class="ip-buy__caption">' . esc_html( ip_product_text( 'tiers_caption' ) ) . ( $ip_tax ? ' <span class="ip-buy__tax">(' . esc_html( $ip_tax ) . ')</span>' : '' ) . '</p>';
		// Widget 75affe8. Two columns, as the widget default.
		infoplus_suite_bulk_price_table( $ip_product, array(
			'columns'      => 2,
			'ab_label'     => ip_product_text( 'ab_label' ),
			'stueck_label' => ip_product_text( 'unit' ),
		) );
		echo '</div>';
	}
	?>

	<div class="ip-buy__row" data-ip-qty-row>
		<div class="ip-qty">
			<label class="ip-qty__label" for="qty_value"><?php echo esc_html( ip_product_text( 'qty_label' ) ); ?></label>
			<div class="ip-qty__stepper" data-ip-qty-controls>
				<button type="button" class="ip-qty__btn" id="qty_minus" data-ip-qty="minus" aria-controls="qty_value">
					<?php echo ip_product_icon( 'minus' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
					<span class="ip-sr"><?php esc_html_e( 'Weniger', 'infoplus' ); ?></span>
				</button>
				<input class="ip-qty__input" id="qty_value" data-ip-qty="value" type="text" value="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
				<button type="button" class="ip-qty__btn" id="qty_plus" data-ip-qty="plus" aria-controls="qty_value">
					<?php echo ip_product_icon( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
					<span class="ip-sr"><?php esc_html_e( 'Mehr', 'infoplus' ); ?></span>
				</button>
			</div>
		</div>

		<?php // No live region here: the summary card announces the changed total once (product.js). ?>
		<dl class="ip-buy__prices">
			<div class="ip-buy__price">
				<dt><?php echo esc_html( ip_product_text( 'unit_label' ) ); ?></dt>
				<dd data-ip-qty="unit-price"><?php echo esc_html( ip_product_text( 'price_zero' ) ); ?></dd>
			</div>
			<div class="ip-buy__price ip-buy__price--total">
				<dt><?php echo esc_html( ip_product_text( 'total_label' ) ); ?></dt>
				<dd data-ip-qty="total-price"><?php echo esc_html( ip_product_text( 'price_zero' ) ); ?></dd>
			</div>
		</dl>
	</div>

	<p class="ip-buy__note"><?php echo esc_html( ip_product_price_note() ); ?></p>
</div>
