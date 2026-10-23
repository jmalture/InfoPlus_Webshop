<?php
/**
 * Kontakt page visual: a short, animated example conversation instead of the
 * photo (switch "demo_show" in the field group "Kontakt"; page-kontakt.php
 * shows the photo when it is off or nothing is filled in).
 *
 * One example at a time (assets/js/content.js): the customer's question
 * appears, the team types, the answer appears, then "Angebot per E-Mail
 * gesendet" with a tick. A pause, the conversation clears, the next example.
 *
 * Built from HTML and CSS only, fixed aspect box (content.css), so nothing
 * shifts. The server renders the first example finished: that is what
 * visitors without JS and with prefers-reduced-motion see. Labelled
 * "Beispiel" on screen. Accessibility as the front page demo: one role="img"
 * with a description, a pause button while it moves (WCAG 2.2.2), pauses on
 * hover, focus, off screen and in a hidden tab.
 *
 * $args:
 * - chats (string) one example per line: "Frage | Antwort"
 */

defined( 'ABSPATH' ) || exit;

$ip_chats = array();
foreach ( preg_split( '/\R/', (string) ( $args['chats'] ?? '' ) ) as $ip_line ) {
	$ip_parts = array_map( 'trim', explode( '|', $ip_line, 2 ) );
	if ( '' !== $ip_parts[0] && '' !== ( $ip_parts[1] ?? '' ) ) {
		$ip_chats[] = array( 'q' => $ip_parts[0], 'a' => $ip_parts[1] );
	}
}
if ( ! $ip_chats ) {
	return; // page-kontakt.php then shows the photo.
}
$ip_first = $ip_chats[0];

/* translators: 1: example question, 2: example answer */
$ip_alt = sprintf( __( 'Animiertes Beispiel einer Anfrage: „%1$s“ Antwort des Teams: „%2$s“ Danach wird ein Angebot per E-Mail gesendet.', 'infoplus' ), $ip_first['q'], $ip_first['a'] );

$ip_svg = function ( $paths, $class ) {
	return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths . '</svg>';
};
?>
<div class="ip-kontakt__media ip-cdemo" data-ip-cdemo="<?php echo esc_attr( wp_json_encode( $ip_chats ) ); ?>">
	<div class="ip-cdemo__stage" role="img" aria-label="<?php echo esc_attr( $ip_alt ); ?>">
		<div class="ip-cdemo__bar">
			<span class="ip-cdemo__avatar"><?php echo $ip_svg( '<path d="M4 13v-1a8 8 0 0 1 16 0v1"/><path d="M4 13a2 2 0 0 1 2-2h1v6H6a2 2 0 0 1-2-2zM20 13a2 2 0 0 0-2-2h-1v6h1a2 2 0 0 0 2-2z"/><path d="M18 17c0 2-2 3-5 3"/>', 'ip-cdemo__avatar-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
			<span class="ip-cdemo__who">
				<span class="ip-cdemo__name"><?php esc_html_e( 'Ihr Ansprechpartner', 'infoplus' ); ?></span>
				<span class="ip-cdemo__status"><span class="ip-cdemo__dot"></span><?php esc_html_e( 'Telefon, E-Mail, Formular', 'infoplus' ); ?></span>
			</span>
			<span class="ip-cdemo__tag"><?php esc_html_e( 'Beispiel', 'infoplus' ); ?></span>
		</div>

		<div class="ip-cdemo__thread" data-ip-cdemo-thread>
			<p class="ip-cdemo__msg ip-cdemo__msg--in is-on" data-ip-cdemo-q><?php echo esc_html( $ip_first['q'] ); ?></p>
			<?php // The typing dots sit on top of the answer's own place, so nothing moves when they show. ?>
			<div class="ip-cdemo__reply">
				<p class="ip-cdemo__typing" data-ip-cdemo-typing aria-hidden="true"><span></span><span></span><span></span></p>
				<p class="ip-cdemo__msg ip-cdemo__msg--out is-on" data-ip-cdemo-a><?php echo esc_html( $ip_first['a'] ); ?></p>
			</div>
			<p class="ip-cdemo__offer is-on" data-ip-cdemo-offer>
				<span class="ip-cdemo__file"><?php echo $ip_svg( '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>', 'ip-cdemo__file-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
				<span class="ip-cdemo__offer-text">
					<b><?php esc_html_e( 'Ihr Angebot', 'infoplus' ); ?></b>
					<span><?php esc_html_e( 'per E-Mail gesendet', 'infoplus' ); ?></span>
				</span>
				<span class="ip-cdemo__tick"><?php echo $ip_svg( '<path d="M5 12.5l4.5 4.5L19 7.5"/>', 'ip-cdemo__tick-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
			</p>
		</div>
	</div>
	<button type="button" class="ip-demo__pause ip-cdemo__pause" aria-pressed="false" data-ip-cdemo-pause hidden>
		<svg class="ip-demo__pause-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 6v12M15 6v12"/></svg>
		<svg class="ip-demo__play-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5.5v13l10.5-6.5z"/></svg>
		<span class="ip-sr"><?php esc_html_e( 'Animation anhalten', 'infoplus' ); ?></span>
	</button>
</div>
