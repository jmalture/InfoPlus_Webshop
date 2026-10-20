<?php
/**
 * Floating "Kontakt" panel. Content and behaviour from the old Elementor
 * snippet 14273 (tab on the right edge, opens on click, closes on outside
 * click); look per DESIGN.md: an .ip-panel with icon tiles, brand colours
 * only. Desktop: magenta tab on the right edge, the panel slides out next to
 * it. Phone: a round floating button bottom right, the panel is a bottom
 * sheet. Markup is server-rendered; assets/js/contact-panel.js toggles it.
 *
 * Without JS the tab could not open, so CSS shows the panel only under
 * html.js; the same contact data is in the footer anyway.
 */

defined( 'ABSPATH' ) || exit;

if ( ! (int) ip_setting( 'kontakt_panel_show' ) ) {
	return;
}

$ip_cp_label    = ip_setting( 'kontakt_tab_label' );
$ip_cp_heading  = ip_setting( 'kontakt_heading' );
$ip_cp_intro    = ip_setting( 'kontakt_intro' );
$ip_cp_hours    = ip_hours_rows( ip_setting( 'kontakt_hours' ) );
$ip_cp_phone    = ip_setting( 'phone' );
$ip_cp_email    = ip_setting( 'email' );
$ip_cp_form_lbl = ip_setting( 'kontakt_form_label' );
$ip_cp_form_url = $ip_cp_form_lbl ? ip_setting_page_url( 'kontakt_form_page', '/kontakt/' ) : '';
// With the Suite form on the Kontakt page, land on the form (inc/inquiry.php); on that page the link scrolls to it.
if ( $ip_cp_form_url && function_exists( 'infoplus_suite_inquiry_form' ) && false === strpos( $ip_cp_form_url, '#' ) ) {
	$ip_cp_form_url .= '#anfrage';
}

if ( ! $ip_cp_hours && ! $ip_cp_phone && ! $ip_cp_email && ! $ip_cp_form_url ) {
	return; // Nothing to show.
}
if ( ! $ip_cp_label ) {
	$ip_cp_label = __( 'Kontakt', 'infoplus' ); // The tab always needs an accessible name.
}
$ip_cp_name = $ip_cp_heading ? $ip_cp_heading : $ip_cp_label;
?>
<div class="ip-contact" data-ip-contact>
	<button type="button" class="ip-contact__tab" aria-expanded="false" aria-controls="ip-contact-panel" data-ip-contact-toggle>
		<span class="ip-contact__tab-icon ip-contact__tab-icon--phone"><?php echo ip_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
		<span class="ip-contact__tab-icon ip-contact__tab-icon--chat"><?php echo ip_icon( 'chat' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
		<span class="ip-contact__tab-label"><?php echo esc_html( $ip_cp_label ); ?></span>
	</button>

	<div class="ip-contact__scrim" data-ip-contact-close aria-hidden="true"></div>

	<div class="ip-contact__panel ip-panel" id="ip-contact-panel" role="region" aria-label="<?php echo esc_attr( $ip_cp_name ); ?>" tabindex="-1">
		<div class="ip-contact__head">
			<span class="ip-contact__grabber" aria-hidden="true"></span>
			<span class="ip-kicker ip-contact__kicker"><?php echo esc_html( $ip_cp_label ); ?></span>
			<?php if ( $ip_cp_heading ) : ?>
				<h2 class="ip-contact__title"><?php echo esc_html( $ip_cp_heading ); ?></h2>
			<?php endif; ?>
			<?php if ( $ip_cp_intro ) : ?>
				<p class="ip-contact__intro"><?php echo ip_lines( $ip_cp_intro ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped per line. ?></p>
			<?php endif; ?>
			<button type="button" class="ip-contact__close" data-ip-contact-close>
				<?php echo ip_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
				<span class="ip-sr"><?php esc_html_e( 'Schließen', 'infoplus' ); ?></span>
			</button>
		</div>

		<?php if ( $ip_cp_hours ) : ?>
			<div class="ip-contact__hours">
				<h3 class="ip-contact__hours-title"><?php echo ip_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php esc_html_e( 'Öffnungszeiten', 'infoplus' ); ?></h3>
				<dl class="ip-contact__hours-list">
					<?php foreach ( $ip_cp_hours as $ip_cp_row ) : ?>
						<div>
							<dt><?php echo esc_html( $ip_cp_row['label'] ); ?></dt>
							<dd><?php echo esc_html( $ip_cp_row['value'] ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</div>
		<?php endif; ?>

		<?php if ( $ip_cp_phone || $ip_cp_email ) : ?>
			<ul class="ip-contact__reach" role="list">
				<?php if ( $ip_cp_phone ) : ?>
					<li>
						<a class="ip-contact__row" href="<?php echo esc_url( ip_tel( $ip_cp_phone ) ); ?>">
							<span class="ip-tile"><?php echo ip_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
							<span class="ip-contact__row-text">
								<span class="ip-contact__row-label"><?php esc_html_e( 'Anrufen', 'infoplus' ); ?></span>
								<span class="ip-contact__row-value"><?php echo esc_html( $ip_cp_phone ); ?></span>
							</span>
							<?php echo ip_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
						</a>
					</li>
				<?php endif; ?>
				<?php if ( $ip_cp_email ) : ?>
					<li>
						<a class="ip-contact__row" href="<?php echo esc_url( 'mailto:' . antispambot( $ip_cp_email ) ); ?>">
							<span class="ip-tile"><?php echo ip_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
							<span class="ip-contact__row-text">
								<span class="ip-contact__row-label"><?php esc_html_e( 'E-Mail schreiben', 'infoplus' ); ?></span>
								<span class="ip-contact__row-value"><?php echo esc_html( antispambot( $ip_cp_email ) ); ?></span>
							</span>
							<?php echo ip_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
						</a>
					</li>
				<?php endif; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $ip_cp_form_url ) : ?>
			<a class="ip-btn ip-btn--ghost ip-contact__form" href="<?php echo esc_url( $ip_cp_form_url ); ?>"><?php echo esc_html( $ip_cp_form_lbl ); ?></a>
		<?php endif; ?>
	</div>
</div>
