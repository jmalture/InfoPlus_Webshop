<?php
/**
 * Contact band at the end of a content page (front page today). All data
 * from the site settings (tab "Kontakt"), the same values the floating
 * Kontakt panel shows, so there is one place to edit.
 *
 * Left: kicker, heading, text and the button to the contact page. Right, on
 * a tinted panel:
 * - a live status line ("Jetzt erreichbar, bis 17:00 Uhr" or "Geschlossen,
 *   morgen ab 8:00 Uhr"), worked out in the browser from the opening hours
 *   in German time (assets/js/home.js), so a cached page is never wrong.
 *   Public holidays are not known; the line only appears with JS and when
 *   the hours can be read (ip_hours_week());
 * - phone and e-mail as two large tiles to tap;
 * - the week as seven day chips (open days tinted, today marked by home.js)
 *   with the hours lines below. When the hours cannot be read as days, the
 *   lines are shown as they are.
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
$ip_cb_ways  = ip_contact_rows( array( 'phone', 'email' ) );
$ip_cb_hours = ip_hours_rows( ip_setting( 'kontakt_hours' ) );
$ip_cb_week  = $ip_cb_hours ? ip_hours_week( $ip_cb_hours ) : null;
$ip_cb_label = (string) ip_setting( 'kontakt_form_label' );
$ip_cb_url   = $ip_cb_label ? ip_setting_page_url( 'kontakt_form_page', '/kontakt/' ) : '';
// No button to the page we are on.
if ( $ip_cb_url && is_page() && untrailingslashit( $ip_cb_url ) === untrailingslashit( (string) get_permalink() ) ) {
	$ip_cb_url = '';
}

if ( ! $ip_cb_ways && ! $ip_cb_hours && ! $ip_cb_url ) {
	return;
}
$ip_cb_level = in_array( $ip_cb['level'], array( 'h2', 'h3' ), true ) ? $ip_cb['level'] : 'h2';
$ip_cb_names = array( 1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So' );
$ip_cb_long  = array( 1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag' );
$ip_cb_act   = array(
	'phone' => __( 'Anrufen', 'infoplus' ),
	'email' => __( 'E-Mail schreiben', 'infoplus' ),
);
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

			<?php if ( $ip_cb_ways || $ip_cb_hours ) : ?>
				<div class="ip-contact-band__panel">
					<?php if ( $ip_cb_week ) : ?>
						<p class="ip-cb-status" data-ip-hours="<?php echo esc_attr( wp_json_encode( $ip_cb_week ) ); ?>" role="status" hidden>
							<span class="ip-cb-status__dot" aria-hidden="true"></span>
							<span class="ip-cb-status__text" data-ip-hours-text></span>
						</p>
					<?php endif; ?>

					<?php if ( $ip_cb_ways ) : ?>
						<div class="ip-cb-ways">
							<?php foreach ( $ip_cb_ways as $ip_row ) : ?>
								<a class="ip-cb-way" href="<?php echo esc_url( $ip_row['url'], array( 'tel', 'mailto' ) ); ?>">
									<span class="ip-tile"><?php echo ip_icon( $ip_row['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
									<span class="ip-cb-way__label"><?php echo esc_html( $ip_row['label'] ); ?></span>
									<span class="ip-cb-way__value"><?php echo esc_html( $ip_row['value'] ); ?></span>
									<span class="ip-cb-way__go"><?php echo esc_html( $ip_cb_act[ $ip_row['key'] ] ?? '' ); ?> <?php echo ip_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( $ip_cb_hours ) : ?>
						<div class="ip-cb-week">
							<p class="ip-cb-week__head"><?php echo ip_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php esc_html_e( 'Öffnungszeiten', 'infoplus' ); ?></p>
							<?php if ( $ip_cb_week ) : ?>
								<ol class="ip-cb-days" aria-hidden="true">
									<?php foreach ( $ip_cb_names as $ip_d => $ip_name ) : ?>
										<li class="ip-cb-day<?php echo isset( $ip_cb_week[ $ip_d ] ) ? ' is-open' : ''; ?>" data-ip-day="<?php echo (int) $ip_d; ?>" title="<?php echo esc_attr( $ip_cb_long[ $ip_d ] . ( isset( $ip_cb_week[ $ip_d ] ) ? ': ' . $ip_cb_week[ $ip_d ]['from'] . '–' . $ip_cb_week[ $ip_d ]['to'] : ': ' . __( 'geschlossen', 'infoplus' ) ) ); ?>"><?php echo esc_html( $ip_name ); ?></li>
									<?php endforeach; ?>
								</ol>
							<?php endif; ?>
							<p class="ip-cb-week__lines">
								<?php foreach ( $ip_cb_hours as $ip_line ) : ?>
									<span><?php if ( '' !== $ip_line['label'] ) : ?><b><?php echo esc_html( $ip_line['label'] ); ?></b> <?php endif; ?><?php echo esc_html( $ip_line['value'] ); ?></span>
								<?php endforeach; ?>
							</p>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
