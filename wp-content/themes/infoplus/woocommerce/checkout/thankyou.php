<?php
/**
 * Order confirmation (checkout endpoint order-received), inside the checkout
 * frame of wc-page.php (step 3 "Bestätigung"). Replaces the Infoplus Suite's
 * thank-you block (switched off in inc/account.php, filter
 * ip_account_own_thankyou).
 *
 * - Success card: message, order number, date, status, payment method, the
 *   order reference (PO number) when given, e-mail, total, and the buttons
 *   "Zum Kundenkonto" / "Weiter einkaufen".
 * - Payment card: what the gateway prints on woocommerce_thankyou_{gateway}
 *   (bank transfer: instructions and the shop's bank details from
 *   WooCommerce > Settings > Payments > Überweisung). Left out when empty.
 * - woocommerce_thankyou: items, totals and addresses
 *   (woocommerce/order/order-details*.php) plus anything plugins add.
 *
 * Theme override.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.1.0
 *
 * @var WC_Order $order
 */

defined( 'ABSPATH' ) || exit;

// Theme handling switched off (filter ip_account_own_thankyou): WooCommerce's own template, the Suite adds its block.
if ( ! function_exists( 'ip_account_owns_thankyou' ) || ! ip_account_owns_thankyou() ) {
	include WC()->plugin_path() . '/templates/checkout/thankyou.php';
	return;
}
?>

<div class="woocommerce-order ip-order ip-order--received">

	<?php
	if ( $order ) :

		do_action( 'woocommerce_before_thankyou', $order->get_id() );

		$ip_owner   = is_user_logged_in() && $order->get_user_id() === get_current_user_id();
		$ip_account = function_exists( 'ip_account_page_url' ) ? ip_account_page_url() : '';
		?>

		<?php if ( $order->has_status( 'failed' ) ) : ?>

			<section class="ip-acc-card ip-order__summary ip-order__summary--failed">
				<div class="ip-order__hero">
					<span class="ip-tile ip-tile--warn"><?php echo ip_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
					<div>
						<h2 class="ip-order__hero-title"><?php esc_html_e( 'Die Zahlung ist fehlgeschlagen', 'infoplus' ); ?></h2>
						<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed ip-order__hero-text"><?php esc_html_e( 'Ihre Bank oder der Zahlungsanbieter hat die Zahlung abgelehnt. Bitte versuchen Sie es noch einmal.', 'infoplus' ); ?></p>
					</div>
				</div>
				<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions ip-order__buttons">
					<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="ip-btn pay"><?php esc_html_e( 'Erneut bezahlen', 'infoplus' ); ?></a>
					<?php if ( is_user_logged_in() && $ip_account ) : ?>
						<a href="<?php echo esc_url( $ip_account ); ?>" class="ip-btn ip-btn--ghost"><?php esc_html_e( 'Zum Kundenkonto', 'infoplus' ); ?></a>
					<?php endif; ?>
				</p>
			</section>

		<?php else : ?>

			<section class="ip-acc-card ip-order__summary woocommerce-thankyou-order-received" aria-labelledby="ip-ty-title">
				<div class="ip-order__hero">
					<span class="ip-tile ip-tile--ok"><?php echo ip_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
					<div>
						<h2 class="ip-order__hero-title" id="ip-ty-title"><?php esc_html_e( 'Vielen Dank für Ihre Bestellung', 'infoplus' ); ?></h2>
						<p class="ip-order__hero-text">
							<?php
							// Same filter as WooCommerce's order-received.php, so gateways and plugins can still change the line.
							echo wp_kses_post(
								apply_filters(
									'woocommerce_thankyou_order_received_text',
									esc_html__( 'Wir haben Ihre Bestellung erhalten und bearbeiten sie so schnell wie möglich. Die Bestätigung ist per E-Mail unterwegs.', 'infoplus' ),
									$order
								)
							);
							?>
						</p>
					</div>
				</div>

				<?php ip_account_print_facts( ip_account_order_facts( $order, 'received' ) ); ?>

				<div class="ip-order__actions">
					<?php if ( $ip_owner && $ip_account ) : ?>
						<a class="ip-btn" href="<?php echo esc_url( $ip_account ); ?>"><?php echo ip_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><span><?php esc_html_e( 'Zum Kundenkonto', 'infoplus' ); ?></span></a>
						<?php
						foreach ( ip_account_order_actions( $order ) as $ip_key => $ip_action ) {
							echo ip_account_action_link( $ip_key, $ip_action, $order ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper.
						}
						?>
						<a class="ip-btn ip-btn--ghost" href="<?php echo esc_url( ip_account_shop_url() ); ?>"><?php esc_html_e( 'Weiter einkaufen', 'infoplus' ); ?></a>
					<?php else : ?>
						<a class="ip-btn" href="<?php echo esc_url( ip_account_shop_url() ); ?>"><?php esc_html_e( 'Weiter einkaufen', 'infoplus' ); ?></a>
						<?php if ( ! is_user_logged_in() && function_exists( 'ip_account_login_url' ) ) : ?>
							<a class="ip-btn ip-btn--ghost" href="<?php echo esc_url( ip_account_login_url() ); ?>"><?php echo ip_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><span><?php echo 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ? esc_html__( 'Anmelden oder Konto anlegen', 'infoplus' ) : esc_html__( 'Anmelden', 'infoplus' ); ?></span></a>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</section>

		<?php endif; ?>

		<?php
		ob_start();
		do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
		$ip_payment = trim( (string) ob_get_clean() );
		?>
		<?php if ( '' !== $ip_payment ) : ?>
			<section class="ip-acc-card ip-order__payment" aria-labelledby="ip-ty-payment-title">
				<h2 class="ip-acc-card__title" id="ip-ty-payment-title">
					<?php
					/* translators: %s: payment method title, e.g. Überweisung */
					echo esc_html( sprintf( __( 'Zahlung: %s', 'infoplus' ), wp_strip_all_tags( $order->get_payment_method_title() ) ) );
					?>
				</h2>
				<div class="ip-order__payment-body">
					<?php echo $ip_payment; // phpcs:ignore WordPress.Security.EscapeOutput -- gateway output, escaped by the gateway. ?>
				</div>
			</section>
		<?php endif; ?>

		<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>

	<?php else : ?>

		<section class="ip-acc-card ip-order__summary">
			<p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received ip-order__hero-text">
				<?php echo wp_kses_post( apply_filters( 'woocommerce_thankyou_order_received_text', esc_html__( 'Vielen Dank. Ihre Bestellung ist eingegangen.', 'infoplus' ), false ) ); ?>
			</p>
		</section>

	<?php endif; ?>

</div>
