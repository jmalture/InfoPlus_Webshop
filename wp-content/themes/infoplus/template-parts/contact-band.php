<?php
/**
 * Contact band at the end of a content page (front page today): heading and
 * text, phone, e-mail and opening hours as tile rows, and a button to the
 * contact page. All data from the site settings (tab "Kontakt"), the same
 * values the floating Kontakt panel shows, so there is one place to edit.
 *
 * $args:
 * - title (string) Heading; '' = setting "kontakt_heading".
 * - text  (string) Text; '' = setting "kontakt_intro".
 * - level (string) Heading tag, default h2.
 */

defined( 'ABSPATH' ) || exit;

$ip_cb = wp_parse_args( $args ?? array(), array( 'title' => '', 'text' => '', 'level' => 'h2' ) );

$ip_cb_title = '' !== $ip_cb['title'] ? $ip_cb['title'] : (string) ip_setting( 'kontakt_heading' );
$ip_cb_text  = '' !== $ip_cb['text'] ? $ip_cb['text'] : (string) ip_setting( 'kontakt_intro' );
$ip_cb_rows  = ip_contact_rows( array( 'phone', 'email', 'hours' ) );
$ip_cb_label = (string) ip_setting( 'kontakt_form_label' );
$ip_cb_url   = $ip_cb_label ? ip_setting_page_url( 'kontakt_form_page', '/kontakt/' ) : '';
// No button to the page we are on.
if ( $ip_cb_url && is_page() && untrailingslashit( $ip_cb_url ) === untrailingslashit( (string) get_permalink() ) ) {
	$ip_cb_url = '';
}

if ( ! $ip_cb_rows && ! $ip_cb_url ) {
	return;
}
$ip_cb_level = in_array( $ip_cb['level'], array( 'h2', 'h3' ), true ) ? $ip_cb['level'] : 'h2';
?>
<section class="ip-section ip-contact-band" aria-labelledby="ip-contact-band-title">
	<div class="ip-shell">
		<div class="ip-contact-band__card ip-card">
			<div class="ip-contact-band__intro">
				<p class="ip-kicker"><?php echo esc_html( ip_setting( 'kontakt_tab_label' ) ? ip_setting( 'kontakt_tab_label' ) : __( 'Kontakt', 'infoplus' ) ); ?></p>
				<<?php echo tag_escape( $ip_cb_level ); ?> class="ip-contact-band__title" id="ip-contact-band-title"><?php echo esc_html( $ip_cb_title ? $ip_cb_title : __( 'Kontakt', 'infoplus' ) ); ?></<?php echo tag_escape( $ip_cb_level ); ?>>
				<?php if ( $ip_cb_text ) : ?>
					<div class="ip-sub"><?php echo ip_paras( $ip_cb_text ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_paras() escapes. ?></div>
				<?php endif; ?>
				<?php if ( $ip_cb_url ) : ?>
					<a class="ip-btn" href="<?php echo esc_url( $ip_cb_url ); ?>"><?php echo esc_html( $ip_cb_label ); ?></a>
				<?php endif; ?>
			</div>
			<?php if ( $ip_cb_rows ) : ?>
				<div class="ip-reach-list">
					<?php array_map( 'ip_contact_row', $ip_cb_rows ); ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
