<?php
/**
 * My Account page (logged in): profile card and menu in the aside, the
 * endpoint content beside it (stacked below 1101 px). Theme override.
 *
 * Same hooks as WooCommerce's template: woocommerce_account_navigation prints
 * the menu (myaccount/navigation.php), woocommerce_account_content the
 * endpoint (notices first).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.5.0
 */

defined( 'ABSPATH' ) || exit;

$ip_uid     = get_current_user_id();
$ip_user    = wp_get_current_user();
$ip_company = (string) get_user_meta( $ip_uid, 'billing_company', true );
$ip_status  = ip_account_status_label( $ip_uid );
$ip_name    = trim( $ip_user->first_name . ' ' . $ip_user->last_name );
$ip_name    = $ip_name ? $ip_name : $ip_user->display_name;
?>
<div class="ip-acc-layout">
	<aside class="ip-acc-aside" aria-label="<?php esc_attr_e( 'Kundenkonto', 'infoplus' ); ?>">
		<div class="ip-acc-profile">
			<?php echo ip_account_avatar( $ip_uid, 56 ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?>
			<div class="ip-acc-profile__text">
				<p class="ip-acc-profile__name"><?php echo esc_html( $ip_name ); ?></p>
				<?php if ( $ip_company ) : ?>
					<p class="ip-acc-profile__company"><?php echo esc_html( $ip_company ); ?></p>
				<?php endif; ?>
				<?php if ( $ip_status ) : ?>
					<p class="ip-acc-profile__status">
						<span class="ip-sr"><?php esc_html_e( 'Kontostatus:', 'infoplus' ); ?></span>
						<span class="ip-pill"><?php echo esc_html( $ip_status ); ?></span>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		/**
		 * My Account navigation.
		 *
		 * @since 2.6.0
		 */
		do_action( 'woocommerce_account_navigation' );
		?>
	</aside>

	<div class="woocommerce-MyAccount-content ip-acc-content">
		<?php
		/**
		 * My Account content.
		 *
		 * @since 2.6.0
		 */
		do_action( 'woocommerce_account_content' );
		?>
	</div>
</div>
