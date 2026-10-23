<?php
/**
 * Login and registration (login page /log-in/, and the account page for
 * guests when no login page exists). Two white cards side by side from
 * 768 px: "Anmelden" and, when registration is switched on in WooCommerce,
 * "Neues Konto". The login form posts its return target in "redirect"
 * (validated in inc/account.php), so a visitor sent here from an order link
 * comes back to it. Theme override; fields, nonces and hooks as in
 * WooCommerce's template.
 *
 * Replaces the Essential Addons login/register widget of page 12381.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$ip_register = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
$ip_target   = function_exists( 'ip_account_login_target' ) ? ip_account_login_target() : '';

do_action( 'woocommerce_before_customer_login_form' ); ?>

<div class="ip-login<?php echo $ip_register ? ' ip-login--2' : ''; ?>" id="customer_login">

	<section class="ip-acc-card ip-login__card" aria-labelledby="ip-login-title">

		<h2 class="ip-acc-card__title" id="ip-login-title"><?php esc_html_e( 'Anmelden', 'infoplus' ); ?></h2>
		<p class="ip-login__lead"><?php esc_html_e( 'Willkommen zurück! Melden Sie sich mit Ihrer E-Mail-Adresse oder Ihrem Benutzernamen an.', 'infoplus' ); ?></p>

		<form class="woocommerce-form woocommerce-form-login login" method="post" novalidate>

			<?php do_action( 'woocommerce_login_form_start' ); ?>

			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="username"><?php esc_html_e( 'E-Mail-Adresse oder Benutzername', 'infoplus' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text ip-sr"><?php esc_html_e( 'Pflichtfeld', 'infoplus' ); ?></span></label>
				<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) && is_string( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" /><?php // @codingStandardsIgnoreLine ?>
			</p>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="password"><?php esc_html_e( 'Passwort', 'infoplus' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text ip-sr"><?php esc_html_e( 'Pflichtfeld', 'infoplus' ); ?></span></label>
				<input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" required aria-required="true" />
			</p>

			<?php do_action( 'woocommerce_login_form' ); ?>

			<p class="form-row ip-login__row">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme ip-check">
					<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span><?php esc_html_e( 'Angemeldet bleiben', 'infoplus' ); ?></span>
				</label>
				<span class="woocommerce-LostPassword lost_password">
					<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Passwort vergessen?', 'infoplus' ); ?></a>
				</span>
			</p>

			<p class="form-row">
				<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
				<?php if ( $ip_target ) : ?>
					<input type="hidden" name="redirect" value="<?php echo esc_url( $ip_target ); ?>" />
				<?php endif; ?>
				<button type="submit" class="woocommerce-button button woocommerce-form-login__submit ip-btn ip-btn--lg ip-btn--block" name="login" value="<?php esc_attr_e( 'Anmelden', 'infoplus' ); ?>"><?php esc_html_e( 'Anmelden', 'infoplus' ); ?></button>
			</p>

			<?php do_action( 'woocommerce_login_form_end' ); ?>

		</form>
	</section>

	<?php if ( $ip_register ) : ?>

		<section class="ip-acc-card ip-login__card ip-login__card--register" aria-labelledby="ip-register-title">

			<h2 class="ip-acc-card__title" id="ip-register-title"><?php esc_html_e( 'Neues Konto', 'infoplus' ); ?></h2>
			<p class="ip-login__lead"><?php esc_html_e( 'Mit einem Konto sehen Sie alle Bestellungen mit Status und Rechnung, bestellen schneller nach und haben Ihre Adressen gespeichert.', 'infoplus' ); ?></p>

			<form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?> >

				<?php do_action( 'woocommerce_register_form_start' ); ?>

				<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>

					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="reg_username"><?php esc_html_e( 'Benutzername', 'infoplus' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text ip-sr"><?php esc_html_e( 'Pflichtfeld', 'infoplus' ); ?></span></label>
						<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" /><?php // @codingStandardsIgnoreLine ?>
					</p>

				<?php endif; ?>

				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
					<label for="reg_email"><?php esc_html_e( 'E-Mail-Adresse', 'infoplus' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text ip-sr"><?php esc_html_e( 'Pflichtfeld', 'infoplus' ); ?></span></label>
					<input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" required aria-required="true" /><?php // @codingStandardsIgnoreLine ?>
				</p>

				<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>

					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="reg_password"><?php esc_html_e( 'Passwort', 'infoplus' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text ip-sr"><?php esc_html_e( 'Pflichtfeld', 'infoplus' ); ?></span></label>
						<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" required aria-required="true" />
					</p>

				<?php else : ?>

					<p class="ip-muted"><?php esc_html_e( 'Sie erhalten per E-Mail einen Link, mit dem Sie Ihr Passwort festlegen.', 'infoplus' ); ?></p>

				<?php endif; ?>

				<?php do_action( 'woocommerce_register_form' ); ?>

				<p class="woocommerce-form-row form-row">
					<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
					<?php if ( $ip_target ) : ?>
						<input type="hidden" name="redirect" value="<?php echo esc_url( $ip_target ); ?>" />
					<?php endif; ?>
					<button type="submit" class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit ip-btn ip-btn--ghost ip-btn--lg ip-btn--block" name="register" value="<?php esc_attr_e( 'Konto anlegen', 'infoplus' ); ?>"><?php esc_html_e( 'Konto anlegen', 'infoplus' ); ?></button>
				</p>

				<?php do_action( 'woocommerce_register_form_end' ); ?>

			</form>
		</section>

	<?php endif; ?>

</div>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
