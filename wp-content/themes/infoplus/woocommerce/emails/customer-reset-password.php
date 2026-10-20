<?php
/**
 * Customer Reset Password email (Infoplus theme): German, formal Sie.
 *
 * The reset link is built exactly as in WooCommerce (key, id, login on the
 * lost-password endpoint of the account page).
 *
 * Based on WooCommerce templates/emails/customer-reset-password.php 10.4.0.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$ip_reset_url = add_query_arg(
	array(
		'key'   => $reset_key,
		'id'    => $user_id,
		'login' => rawurlencode( $user_login ),
	),
	wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) )
);
$ip_greeting  = function_exists( 'ip_email_greeting' ) ? ip_email_greeting( (int) $user_id ) : __( 'Guten Tag,', 'infoplus' );
?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<div class="email-introduction">
	<p><?php echo esc_html( $ip_greeting ); ?></p>
	<?php /* translators: %s: Store name */ ?>
	<p><?php printf( esc_html__( 'für Ihr Kundenkonto bei %s wurde ein neues Passwort angefordert.', 'infoplus' ), esc_html( $blogname ) ); ?></p>

	<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="ip-mail-box-table" style="margin:0 0 24px;border-collapse:separate">
		<tr>
			<td class="ip-mail-box">
				<p class="ip-mail-muted" style="margin:0 0 2px"><?php esc_html_e( 'Benutzername', 'infoplus' ); ?></p>
				<p style="margin:0"><strong><?php echo esc_html( $user_login ); ?></strong></p>
			</td>
		</tr>
	</table>

	<p><?php esc_html_e( 'Über den folgenden Button legen Sie ein neues Passwort fest:', 'infoplus' ); ?></p>
	<?php echo function_exists( 'ip_email_button' ) ? ip_email_button( $ip_reset_url, __( 'Neues Passwort festlegen', 'infoplus' ) ) : '<p><a class="link" href="' . esc_url( $ip_reset_url ) . '">' . esc_html__( 'Neues Passwort festlegen', 'infoplus' ) . '</a></p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_*. ?>
	<p class="ip-mail-muted"><?php esc_html_e( 'Wenn Sie kein neues Passwort angefordert haben, können Sie diese E-Mail ignorieren. Ihr bisheriges Passwort bleibt dann gültig.', 'infoplus' ); ?></p>
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

do_action( 'woocommerce_email_footer', $email );
