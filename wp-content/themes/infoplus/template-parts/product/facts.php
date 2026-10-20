<?php
/**
 * Facts under the main button, as small icon rows (DESIGN.md: "Lead time
 * (Fertigungszeit), shipping and payment facts sit directly under the main
 * button"). Every row comes from real data and is left out without it:
 *
 * - Fertigungszeit: ip_product_lead_time() (product meta "fertigungszeit",
 *   a Germanized delivery time term, or the Suite info box value).
 * - Versand: the "Versand/Express und Zahlung" page (2656), which names UPS as
 *   the carrier. No shipping price here: WooCommerce has no shipping zones on
 *   staging (checked 2026-10-20), so the page is the source.
 * - Zahlung: the gateways WooCommerce really offers (ip_product_payment_methods()).
 * - Beratung: the phone number from the theme settings (ip_setting('phone')).
 *
 * $args:
 * - product (WC_Product)
 */

defined( 'ABSPATH' ) || exit;

$ip_product = $args['product'] ?? null;
if ( ! $ip_product instanceof WC_Product ) {
	return;
}
$ip_lead     = ip_product_lead_time( $ip_product );
$ip_shipping = ip_product_shipping_url();
$ip_payments = ip_product_payment_methods();
$ip_phone    = function_exists( 'ip_setting' ) ? trim( (string) ip_setting( 'phone' ) ) : '';

if ( ! $ip_lead && ! $ip_shipping && ! $ip_payments && '' === $ip_phone ) {
	return;
}
?>
<ul class="ip-facts" aria-label="<?php echo esc_attr( ip_product_text( 'facts_label' ) ); ?>">
	<?php if ( $ip_lead ) : ?>
		<li class="ip-facts__row ip-facts__row--lead">
			<?php echo ip_product_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
			<span><?php echo esc_html( $ip_lead['label'] ); ?> <strong><?php echo esc_html( $ip_lead['value'] ); ?></strong></span>
		</li>
	<?php endif; ?>
	<?php if ( $ip_shipping ) : ?>
		<li class="ip-facts__row ip-facts__row--shipping">
			<?php echo ip_product_icon( 'truck' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
			<span><?php echo esc_html( ip_product_text( 'fact_shipping' ) ); ?>: <a href="<?php echo esc_url( $ip_shipping ); ?>"><?php echo esc_html( ip_product_text( 'fact_shipping_link' ) ); ?></a></span>
		</li>
	<?php endif; ?>
	<?php if ( $ip_payments ) : ?>
		<li class="ip-facts__row ip-facts__row--payment">
			<?php echo ip_product_icon( 'wallet' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
			<span><?php echo esc_html( ip_product_text( 'fact_payment' ) ); ?> <strong><?php echo esc_html( ip_product_join_or( $ip_payments ) ); ?></strong></span>
		</li>
	<?php endif; ?>
	<?php if ( '' !== $ip_phone ) : ?>
		<li class="ip-facts__row ip-facts__row--help">
			<?php echo ip_product_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG (shared set). ?>
			<span><?php echo esc_html( ip_product_text( 'fact_help' ) ); ?>: <a href="<?php echo esc_attr( function_exists( 'ip_tel' ) ? ip_tel( $ip_phone ) : 'tel:' . preg_replace( '/[^\d+]/', '', $ip_phone ) ); ?>"><?php echo esc_html( $ip_phone ); ?></a></span>
		</li>
	<?php endif; ?>
</ul>
