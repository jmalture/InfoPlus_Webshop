<?php
/**
 * Inquiry drawer: a native <dialog> (focus trap, Escape, inert page) that
 * holds the Suite's inquiry form. Right side from 768 px, bottom sheet below.
 * Opened and driven by assets/js/inquiry.js; printed by inc/inquiry.php.
 *
 * $args:
 * - variant (string) product|contact
 */

defined( 'ABSPATH' ) || exit;

$ip_variant = ( $args['variant'] ?? 'product' ) === 'contact' ? 'contact' : 'product';
?>
<dialog class="ip-inq-drawer" id="ip-inquiry" aria-labelledby="ip-inquiry-title" aria-describedby="ip-inquiry-sub" data-ip-inquiry-drawer data-variant="<?php echo esc_attr( $ip_variant ); ?>">
	<header class="ip-inq-drawer__head">
		<div class="ip-inq-drawer__titles">
			<p class="ip-kicker ip-inq-drawer__kicker"><span data-ip-inq-only="product"><?php esc_html_e( 'Angebot anfordern', 'infoplus' ); ?></span><span data-ip-inq-only="contact"><?php esc_html_e( 'Kontakt', 'infoplus' ); ?></span></p>
			<h2 class="ip-inq-drawer__title" id="ip-inquiry-title" tabindex="-1">
				<span data-ip-inq-only="product"><?php esc_html_e( 'Individuelle Anfrage', 'infoplus' ); ?></span>
				<span data-ip-inq-only="contact"><?php esc_html_e( 'Nachricht an Infoplus', 'infoplus' ); ?></span>
			</h2>
			<p class="ip-inq-drawer__sub" id="ip-inquiry-sub"><span data-ip-inq-only="product"><?php esc_html_e( 'Beschreiben Sie Ihr Vorhaben, wir erstellen Ihnen ein Angebot.', 'infoplus' ); ?></span><span data-ip-inq-only="contact"><?php esc_html_e( 'Schreiben Sie uns, wir melden uns bei Ihnen.', 'infoplus' ); ?></span></p>
		</div>
		<button type="button" class="ip-inq-drawer__close" data-ip-inq-dismiss aria-label="<?php esc_attr_e( 'Anfrage schließen', 'infoplus' ); ?>">
			<?php echo ip_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
		</button>
	</header>
	<?php infoplus_suite_inquiry_form( array( 'variant' => $ip_variant, 'id_prefix' => 'ip-inq' ) ); ?>
</dialog>
