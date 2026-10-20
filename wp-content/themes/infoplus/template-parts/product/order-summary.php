<?php
/**
 * Summary card: what is chosen, what it costs at the current quantity, and the
 * main action (DESIGN.md: "A summary card stays in view"). From 1101 px it is
 * sticky in the right column (product.css); below that it sits after the
 * steps and the phone bar (action-bar.php) takes over once it scrolls away.
 *
 * Everything in [data-ip-sum] is written by assets/js/product.js from one
 * snapshot of the Suite product state (the same data feeds the step headers
 * and the phone bar): model name, the chosen options, quantity, the unit price
 * of the current tier and the total (both as the Suite's bulk calculator wrote
 * them into the "Menge" step). The price block shows only while the quantity
 * row is shown, so models that are only requestable show no price.
 *
 * The action buttons (actions.php) live in this card.
 *
 * $args:
 * - product (WC_Product)
 * - suite   (bool) Suite helpers available.
 */

defined( 'ABSPATH' ) || exit;

$ip_product = $args['product'] ?? null;
if ( ! $ip_product instanceof WC_Product ) {
	return;
}
$ip_tax      = ip_product_tax_label();
$ip_shipping = ip_product_shipping_url();
?>
<section class="ip-summary" data-ip-summary aria-labelledby="ip-summary-title">
	<h2 class="ip-summary__title" id="ip-summary-title"><?php echo esc_html( ip_product_text( 'summary_title' ) ); ?></h2>
	<div class="ip-summary__body">
		<p class="ip-summary__choice">
			<span class="ip-summary__model" data-ip-sum="model"><?php echo esc_html( ip_product_text( 'summary_empty' ) ); ?></span>
			<span class="ip-summary__variant" data-ip-sum="variant"></span>
		</p>
		<div class="ip-summary__price" data-ip-sum="price" hidden>
			<p class="ip-summary__total">
				<span class="ip-sr"><?php echo esc_html( rtrim( ip_product_text( 'total_label' ), '*' ) ); ?>: </span>
				<span data-ip-sum="total"></span>
			</p>
			<p class="ip-summary__unit">
				<span data-ip-sum="qty"></span>
				<span class="ip-summary__unit-price"><?php echo esc_html( ip_product_text( 'summary_unit' ) ); ?> <span data-ip-sum="unit"></span></span>
			</p>
			<p class="ip-summary__tax">
				<?php
				echo esc_html( $ip_tax ? 'Preise ' . $ip_tax . ', zzgl.' : 'Preise zzgl.' ) . ' ';
				if ( $ip_shipping ) {
					echo '<a href="' . esc_url( $ip_shipping ) . '">' . esc_html__( 'Versandkosten', 'infoplus' ) . '</a>';
				} else {
					esc_html_e( 'Versandkosten', 'infoplus' );
				}
				?>
			</p>
		</div>
	</div>

	<?php get_template_part( 'template-parts/product/actions', null, array( 'product' => $ip_product ) ); ?>

	<p class="ip-sr" aria-live="polite" aria-atomic="true" data-ip-sum-live></p>
</section>
