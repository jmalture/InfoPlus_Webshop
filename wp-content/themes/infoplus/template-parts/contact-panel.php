<?php
/**
 * Floating "Kontakt" panel on the right edge, ported from Elementor custom
 * code snippet 14273 ("Kontact floating button.js"). The old snippet injected
 * this markup and its CSS with JS; here the markup is server-rendered, the
 * styles live in assets/css/footer.css and assets/js/contact-panel.js only
 * toggles it (click on the tab, outside click, Escape).
 *
 * Without JS the tab could not open, so CSS shows the panel only under
 * html.js; the same contact data is in the footer anyway.
 *
 * The icons are the snippet's own drawings (stroke icons, 24 px grid), kept
 * here because they differ from the theme's ip_icon() set.
 */

defined( 'ABSPATH' ) || exit;

if ( ! (int) ip_setting( 'kontakt_panel_show' ) ) {
	return;
}

$ip_cp_label   = ip_setting( 'kontakt_tab_label' );
$ip_cp_heading = ip_setting( 'kontakt_heading' );
$ip_cp_intro   = ip_setting( 'kontakt_intro' );
$ip_cp_hours   = ip_hours_rows( ip_setting( 'kontakt_hours' ) );
$ip_cp_phone   = ip_setting( 'phone' );
$ip_cp_email   = ip_setting( 'email' );

if ( ! $ip_cp_hours && ! $ip_cp_phone && ! $ip_cp_email ) {
	return; // Nothing to show.
}
if ( ! $ip_cp_label ) {
	$ip_cp_label = __( 'Kontakt', 'infoplus' ); // The tab always needs an accessible name.
}

$ip_cp_svg = array(
	'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
	'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
	'mail'  => '<rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="22 4 12 13 2 4"/>',
);
/** Snippet icon markup; stroke widths as in the snippet (phone 2.5, others 2). */
$ip_cp_icon = function ( $name ) use ( $ip_cp_svg ) {
	$stroke = 'phone' === $name ? '2.5' : '2';
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $stroke . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $ip_cp_svg[ $name ] . '</svg>';
};
?>
<div class="ip-contact" data-ip-contact>
	<button type="button" class="ip-contact__tab" aria-expanded="false" aria-controls="ip-contact-body" data-ip-contact-toggle>
		<?php echo $ip_cp_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
		<span class="ip-contact__tab-label"><?php echo esc_html( $ip_cp_label ); ?></span>
	</button>

	<div class="ip-contact__body" id="ip-contact-body" role="region" aria-label="<?php echo esc_attr( $ip_cp_heading ? $ip_cp_heading : $ip_cp_label ); ?>">
		<?php if ( $ip_cp_heading || $ip_cp_intro ) : ?>
			<div class="ip-contact__head">
				<?php if ( $ip_cp_heading ) : ?>
					<h2 class="ip-contact__title"><?php echo esc_html( $ip_cp_heading ); ?></h2>
				<?php endif; ?>
				<?php if ( $ip_cp_intro ) : ?>
					<p class="ip-contact__intro"><?php echo ip_lines( $ip_cp_intro ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped per line. ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="ip-contact__items">
			<?php if ( $ip_cp_hours ) : ?>
				<div class="ip-contact__item ip-contact__item--hours">
					<span class="ip-contact__icon"><?php echo $ip_cp_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
					<p class="ip-contact__text">
						<span class="ip-sr"><?php esc_html_e( 'Öffnungszeiten:', 'infoplus' ); ?></span>
						<?php
						$ip_cp_lines = array();
						foreach ( $ip_cp_hours as $ip_cp_row ) {
							$ip_cp_lines[] = ( '' !== $ip_cp_row['label'] ? '<strong>' . esc_html( $ip_cp_row['label'] ) . '</strong> ' : '' ) . esc_html( $ip_cp_row['value'] );
						}
						echo implode( '<br>', $ip_cp_lines ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( $ip_cp_phone ) : ?>
				<a class="ip-contact__item" href="<?php echo esc_url( ip_tel( $ip_cp_phone ) ); ?>">
					<span class="ip-contact__icon ip-contact__icon--pink"><?php echo $ip_cp_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
					<span class="ip-contact__text"><span class="ip-sr"><?php esc_html_e( 'Telefon:', 'infoplus' ); ?> </span><?php echo esc_html( $ip_cp_phone ); ?></span>
				</a>
			<?php endif; ?>

			<?php if ( $ip_cp_email ) : ?>
				<a class="ip-contact__item" href="<?php echo esc_url( 'mailto:' . antispambot( $ip_cp_email ) ); ?>">
					<span class="ip-contact__icon ip-contact__icon--pink"><?php echo $ip_cp_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
					<span class="ip-contact__text"><span class="ip-sr"><?php esc_html_e( 'E-Mail:', 'infoplus' ); ?> </span><?php echo esc_html( antispambot( $ip_cp_email ) ); ?></span>
				</a>
			<?php endif; ?>
		</div>

		<button type="button" class="ip-contact__close" data-ip-contact-close>
			<?php echo ip_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG from inc/icons.php. ?>
			<span class="ip-sr"><?php esc_html_e( 'Schließen', 'infoplus' ); ?></span>
		</button>
	</div>
</div>
