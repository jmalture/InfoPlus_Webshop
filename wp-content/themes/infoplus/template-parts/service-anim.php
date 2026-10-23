<?php
/**
 * Animated picture for a card of the front page section "Serviceversprechen",
 * shown instead of the card photo (switch "service_anim_show", per card the
 * select "anim"; front-page.php decides).
 *
 * Three pictures, drawn with HTML, CSS and inline SVG only (home.css):
 * - quality: the outline of a badge draws itself like a sketch, the plate
 *   fills in, the name appears, a "Made in Germany" seal is stamped on.
 * - prices:  Staffelpreise as bars that grow one after the other and get
 *   lower with the quantity; the last one is magenta, then a
 *   "Onlineshop-Rabatt" chip. Values: the front page field
 *   hero_demo_prices ("Menge | Preis" per line), labelled "Beispiel".
 * - service: four steps (Beratung, Layout, Fertigung, Versand) light up
 *   along a line, then a "badge24 Express" chip.
 *
 * Plays once when the card scrolls into view and again when the pointer
 * enters the card (assets/js/home.js). The server renders the finished
 * picture: that is what visitors without JS and with prefers-reduced-motion
 * see. Decorative (aria-hidden): the card title and text say the same.
 *
 * $args:
 * - kind   (string) quality | prices | service
 * - prices (string) "10 | 7,92 €\n50 | 6,44 €" (only for prices)
 */

defined( 'ABSPATH' ) || exit;

$ip_kind = (string) ( $args['kind'] ?? '' );
if ( ! in_array( $ip_kind, array( 'quality', 'prices', 'service' ), true ) ) {
	return;
}

$ip_tiers = array();
if ( 'prices' === $ip_kind ) {
	foreach ( preg_split( '/\R/', (string) ( $args['prices'] ?? '' ) ) as $ip_line ) {
		$ip_parts = array_map( 'trim', explode( '|', $ip_line, 2 ) );
		$ip_num   = isset( $ip_parts[1] ) ? (float) str_replace( ',', '.', preg_replace( '/[^\d,.]/', '', $ip_parts[1] ) ) : 0;
		if ( '' !== $ip_parts[0] && $ip_num > 0 ) {
			$ip_tiers[] = array( 'qty' => $ip_parts[0], 'price' => $ip_parts[1], 'num' => $ip_num );
		}
	}
	$ip_tiers = array_slice( $ip_tiers, 0, 5 );
	if ( count( $ip_tiers ) < 2 ) {
		return; // front-page.php then shows the photo.
	}
}
?>
<div class="ip-sanim ip-sanim--<?php echo esc_attr( $ip_kind ); ?>" aria-hidden="true" data-ip-sanim>
	<?php if ( 'quality' === $ip_kind ) : ?>
		<div class="ip-sanim__plate">
			<svg class="ip-sanim__sketch" viewBox="0 0 300 100" preserveAspectRatio="none" focusable="false">
				<rect x="2" y="2" width="296" height="96" rx="14" pathLength="1"/>
			</svg>
			<span class="ip-sanim__face">
				<span class="ip-sanim__logo"></span>
				<span class="ip-sanim__lines">
					<span class="ip-sanim__name"><?php echo esc_html( apply_filters( 'ip_service_anim_name', 'Andreas Denker' ) ); ?></span>
					<span class="ip-sanim__role"></span>
				</span>
			</span>
			<span class="ip-sanim__seal">
				<span><?php echo esc_html( apply_filters( 'ip_service_anim_seal', 'Made in Germany' ) ); ?></span>
			</span>
		</div>
		<svg class="ip-sanim__pencil" viewBox="0 0 24 24" focusable="false"><path d="M4 20l1-5L15.5 4.5a2.1 2.1 0 0 1 3 3L8 18z"/><path d="M13.5 6.5l3 3"/></svg>

	<?php elseif ( 'prices' === $ip_kind ) : ?>
		<?php $ip_max = max( wp_list_pluck( $ip_tiers, 'num' ) ); ?>
		<span class="ip-sanim__tag"><?php esc_html_e( 'Staffelpreise', 'infoplus' ); ?> <span class="ip-sanim__beispiel"><?php esc_html_e( 'Beispiel', 'infoplus' ); ?></span></span>
		<ol class="ip-sanim__bars" style="--ip-n: <?php echo (int) count( $ip_tiers ); ?>">
			<?php foreach ( $ip_tiers as $ip_i => $ip_tier ) : ?>
				<li class="ip-sanim__bar" style="--ip-h: <?php echo esc_attr( round( $ip_tier['num'] / $ip_max, 3 ) ); ?>; --ip-i: <?php echo (int) $ip_i; ?>">
					<span class="ip-sanim__price"><?php echo esc_html( $ip_tier['price'] ); ?></span>
					<span class="ip-sanim__col"></span>
					<span class="ip-sanim__qty"><?php echo esc_html( $ip_tier['qty'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>
		<span class="ip-sanim__chip"><?php echo ip_icon( 'tag' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php esc_html_e( 'Onlineshop-Rabatt', 'infoplus' ); ?></span>

	<?php else : ?>
		<?php
		$ip_steps = (array) apply_filters( 'ip_service_anim_steps', array(
			array( 'chat', __( 'Beratung', 'infoplus' ) ),
			array( 'pen', __( 'Layout', 'infoplus' ) ),
			array( 'layers', __( 'Fertigung', 'infoplus' ) ),
			array( 'truck', __( 'Versand', 'infoplus' ) ),
		) );
		?>
		<ol class="ip-sanim__steps" style="--ip-n: <?php echo (int) count( $ip_steps ); ?>">
			<?php foreach ( $ip_steps as $ip_i => $ip_step ) : ?>
				<li class="ip-sanim__step" style="--ip-i: <?php echo (int) $ip_i; ?>">
					<span class="ip-sanim__dot"><?php echo ip_icon( $ip_step[0] ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
					<span class="ip-sanim__label"><?php echo esc_html( $ip_step[1] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>
		<span class="ip-sanim__chip ip-sanim__chip--express"><?php echo ip_icon( 'zap' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php echo esc_html( apply_filters( 'ip_service_anim_express', 'badge24 Express' ) ); ?></span>
	<?php endif; ?>
</div>
