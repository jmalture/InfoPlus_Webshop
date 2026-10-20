<?php
/**
 * Customer new account email (Infoplus theme): a German B2B welcome, formal Sie.
 *
 * WooCommerce's logic is kept as it is: the set-password link only when the
 * password was generated ($password_generated) and a link exists
 * ($set_password_url, a reset key that expires like a password reset link),
 * the account link from wc_get_page_permalink( 'myaccount' ), the
 * additional content from the e-mail settings. The password itself is never
 * printed. Account features listed are the ones the theme's account offers
 * (ACCOUNT.md): orders with invoices, addresses, reorder.
 *
 * Based on WooCommerce templates/emails/customer-new-account.php 10.4.0.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

defined( 'ABSPATH' ) || exit;

$ip_user_id     = ( isset( $email->object ) && $email->object instanceof WP_User ) ? (int) $email->object->ID : 0;
$ip_account_url = wc_get_page_permalink( 'myaccount' );
$ip_set_pw      = $password_generated && $set_password_url;
$ip_greeting    = function_exists( 'ip_email_greeting' ) ? ip_email_greeting( $ip_user_id ) : __( 'Guten Tag,', 'infoplus' );

/**
 * Fires to output the email header.
 *
 * @hooked WC_Emails::email_header()
 *
 * @since 3.7.0
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<div class="email-introduction">
	<p><?php echo esc_html( $ip_greeting ); ?></p>
	<?php /* translators: %s: Site title */ ?>
	<p><?php printf( esc_html__( 'vielen Dank für Ihre Registrierung bei %s. Ihr Kundenkonto ist eingerichtet.', 'infoplus' ), esc_html( $blogname ) ); ?></p>

	<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="ip-mail-box-table" style="margin:0 0 24px;border-collapse:separate">
		<tr>
			<td class="ip-mail-box">
				<p class="ip-mail-muted" style="margin:0 0 2px"><?php esc_html_e( 'Ihr Benutzername', 'infoplus' ); ?></p>
				<p style="margin:0"><strong><?php echo esc_html( $user_login ); ?></strong></p>
			</td>
		</tr>
	</table>

	<?php if ( $ip_set_pw ) : ?>
		<?php // The password was not chosen at sign-up: the customer sets it with this link (WooCommerce logic). ?>
		<p><?php esc_html_e( 'Bitte legen Sie zuerst Ihr Passwort fest. Der Link ist aus Sicherheitsgründen nur begrenzt gültig.', 'infoplus' ); ?></p>
		<?php echo function_exists( 'ip_email_button' ) ? ip_email_button( $set_password_url, __( 'Passwort festlegen', 'infoplus' ) ) : '<p><a href="' . esc_url( $set_password_url ) . '">' . esc_html__( 'Passwort festlegen', 'infoplus' ) . '</a></p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_*. ?>
	<?php endif; ?>

	<p><?php esc_html_e( 'In Ihrem Kundenkonto können Sie:', 'infoplus' ); ?></p>
	<ul class="ip-mail-list">
		<li><?php esc_html_e( 'Bestellungen, Status und Rechnungen einsehen', 'infoplus' ); ?></li>
		<li><?php esc_html_e( 'Rechnungs- und Lieferadressen pflegen', 'infoplus' ); ?></li>
		<li><?php esc_html_e( 'abgeschlossene Bestellungen erneut bestellen', 'infoplus' ); ?></li>
	</ul>

	<?php if ( $ip_set_pw ) : ?>
		<p><a href="<?php echo esc_url( $ip_account_url ); ?>"><?php esc_html_e( 'Zum Kundenkonto', 'infoplus' ); ?></a></p>
	<?php else : ?>
		<?php echo function_exists( 'ip_email_button' ) ? ip_email_button( $ip_account_url, __( 'Zum Kundenkonto', 'infoplus' ) ) : '<p><a href="' . esc_url( $ip_account_url ) . '">' . esc_html__( 'Zum Kundenkonto', 'infoplus' ) . '</a></p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_*. ?>
	<?php endif; ?>

	<p><?php esc_html_e( 'Haben Sie Fragen zu Ihrem Konto oder zu einer Bestellung? Sie erreichen uns telefonisch und per E-Mail, die Kontaktdaten stehen unten.', 'infoplus' ); ?></p>
	<p><?php esc_html_e( 'Freundliche Grüße', 'infoplus' ); ?><br><?php echo esc_html( $blogname ); ?></p>
</div>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo '<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation"><tr><td class="email-additional-content email-additional-content-aligned">';
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
	echo '</td></tr></table>';
}

/**
 * Fires to output the email footer.
 *
 * @hooked WC_Emails::email_footer()
 *
 * @since 3.7.0
 */
do_action( 'woocommerce_email_footer', $email );
