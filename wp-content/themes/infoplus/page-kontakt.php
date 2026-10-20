<?php
/**
 * "Kontakt" page (/kontakt/, page 3040), picked by slug.
 *
 * Head (kicker, H1, intro, photo) from the field group "Kontakt"
 * (acf-json/group_ip_kontakt.json). Phone, e-mail, fax, address and opening
 * hours from the site settings, the same values as footer and Kontakt panel.
 *
 * Form: with the Infoplus Suite Inquiry module active, the contact variant
 * of the inquiry form sits inline in the form card (inc/inquiry.php hooks
 * template-parts/inquiry-inline.php onto the action
 * "infoplus_theme_contact_form"), with phone and e-mail below it as
 * secondary options. It covers the fields of the old Elementor form 9cb78e8
 * (Firma, Name, E-Mail, Rufnummer, Nachricht, honeypot); Name is split into
 * Vorname and Nachname, Nachricht is required. Recipient: the Suite setting
 * (IKONA IFE > Anfragen). Another module may still print its own form on
 * the action. With nothing hooked, visitors get e-mail and phone buttons
 * instead of a form, and administrators see a placeholder card saying so.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$ip_pid = (int) get_queried_object_id();
$ip_f   = function ( $name, $default = '' ) use ( $ip_pid ) {
	return ip_field( $name, $ip_pid, $default );
};

$ip_title    = (string) $ip_f( 'title' );
$ip_image    = (int) $ip_f( 'image', 0 );
$ip_rows     = ip_contact_rows( array( 'phone', 'email', 'fax', 'address', 'hours' ) );
$ip_inline   = function_exists( 'ip_inquiry_placement' ) && 'inline' === ip_inquiry_placement();
$ip_has_form = $ip_inline || has_action( 'infoplus_theme_contact_form' );
$ip_email    = (string) ip_setting( 'email' );
$ip_phone    = (string) ip_setting( 'phone' );
?>
<div class="ip-content ip-kontakt">
	<div class="ip-shell">
		<?php ip_breadcrumb( array( array( __( 'Home', 'infoplus' ), home_url( '/' ) ), array( get_the_title(), '' ) ) ); ?>

		<div class="ip-page-head ip-kontakt__head<?php echo $ip_image ? ' has-media' : ''; ?>">
			<?php
			ip_section_head( array(
				'kicker' => (string) $ip_f( 'kicker' ),
				'title'  => $ip_title ? $ip_title : get_the_title(),
				'text'   => (string) $ip_f( 'intro' ),
				'tag'    => 'h1',
			) );
			?>
			<?php if ( $ip_image ) : ?>
				<div class="ip-kontakt__media">
					<?php echo ip_img( $ip_image, '(min-width: 1101px) 560px, (min-width: 768px) 45vw, 100vw', array( 'alt' => '', 'loading' => 'eager', 'fetchpriority' => 'high' ), 'ip-1200' ); // phpcs:ignore WordPress.Security.EscapeOutput -- core image HTML. ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="ip-kontakt__grid">
			<?php if ( $ip_rows ) : ?>
				<section class="ip-kontakt__details" aria-labelledby="ip-kontakt-details-title">
					<h2 class="ip-sr" id="ip-kontakt-details-title"><?php esc_html_e( 'Kontaktdaten', 'infoplus' ); ?></h2>
					<div class="ip-reach-list ip-reach-list--cards">
						<?php array_map( 'ip_contact_row', $ip_rows ); ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $ip_inline ) : ?>
				<div class="ip-kontakt__form ip-kontakt__form--inline ip-card">
					<?php
					/** The inline inquiry form (contact variant), see inc/inquiry.php. */
					do_action( 'infoplus_theme_contact_form' );
					?>
					<?php if ( ( $ip_email && is_email( $ip_email ) ) || $ip_phone ) : ?>
						<div class="ip-kontakt__direct">
							<p class="ip-kontakt__direct-label"><?php esc_html_e( 'Lieber direkt?', 'infoplus' ); ?></p>
							<div class="ip-kontakt__actions">
								<?php if ( $ip_phone ) : ?>
									<a class="ip-btn ip-btn--ghost" href="<?php echo esc_url( ip_tel( $ip_phone ), array( 'tel' ) ); ?>"><?php echo ip_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php echo esc_html( $ip_phone ); ?></a>
								<?php endif; ?>
								<?php if ( $ip_email && is_email( $ip_email ) ) : ?>
									<a class="ip-btn ip-btn--ghost" href="<?php echo esc_url( 'mailto:' . antispambot( $ip_email ), array( 'mailto' ) ); ?>"><?php echo ip_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php esc_html_e( 'E-Mail schreiben', 'infoplus' ); ?></a>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			<?php else : ?>
			<section class="ip-kontakt__form ip-card" aria-labelledby="ip-kontakt-form-title">
				<?php if ( $ip_has_form ) : ?>
					<h2 class="ip-kontakt__form-title" id="ip-kontakt-form-title"><?php echo esc_html( $ip_f( 'form_title', __( 'Nachricht senden', 'infoplus' ) ) ); ?></h2>
					<?php
					/**
					 * The contact form (Infoplus Suite module). Print the complete form here.
					 */
					do_action( 'infoplus_theme_contact_form' );
					?>
				<?php elseif ( current_user_can( 'manage_options' ) ) : ?>
					<h2 class="ip-kontakt__form-title" id="ip-kontakt-form-title"><?php echo esc_html( $ip_f( 'form_title', __( 'Nachricht senden', 'infoplus' ) ) ); ?></h2>
					<div class="ip-placeholder" role="note">
						<span class="ip-tile"><?php echo ip_icon( 'help' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
						<div>
							<p><strong><?php esc_html_e( 'Platzhalter für das Kontaktformular (nur für Administratoren sichtbar).', 'infoplus' ); ?></strong></p>
							<p><?php esc_html_e( 'Hier erscheint das Formular, sobald das Formular-Modul der Infoplus Suite aktiv ist (Aktion „infoplus_theme_contact_form“). Besucher sehen bis dahin Buttons für E-Mail und Telefon.', 'infoplus' ); ?></p>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( ! $ip_has_form ) : ?>
					<?php if ( ! current_user_can( 'manage_options' ) ) : ?>
						<h2 class="ip-kontakt__form-title" id="ip-kontakt-form-title"><?php echo esc_html( $ip_f( 'fallback_title', __( 'Schreiben Sie uns', 'infoplus' ) ) ); ?></h2>
					<?php endif; ?>
					<?php echo ip_paras( $ip_f( 'fallback_text' ), 'ip-sub' ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_paras() escapes. ?>
					<div class="ip-kontakt__actions">
						<?php if ( $ip_email && is_email( $ip_email ) ) : ?>
							<a class="ip-btn ip-btn--lg" href="<?php echo esc_url( 'mailto:' . antispambot( $ip_email ), array( 'mailto' ) ); ?>"><?php echo ip_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php esc_html_e( 'E-Mail schreiben', 'infoplus' ); ?></a>
						<?php endif; ?>
						<?php if ( $ip_phone ) : ?>
							<a class="ip-btn ip-btn--ghost ip-btn--lg" href="<?php echo esc_url( ip_tel( $ip_phone ), array( 'tel' ) ); ?>"><?php echo ip_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php esc_html_e( 'Anrufen', 'infoplus' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</section>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php
get_footer();
