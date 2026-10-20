<?php
/**
 * Hero visual of the front page: a live mini configurator instead of a photo.
 * A nameplate in the middle cycles through a short story (assets/js/home.js):
 * the model changes (material look), the name types itself, the logo
 * appears, format and fastener chips get selected (the plate takes the
 * format's proportions), the Staffelpreis steps down through its tiers and
 * "In den Warenkorb" ends with a tick. Then the next model and name.
 *
 * Built from HTML and CSS only, no images, so it never competes with the
 * heading for LCP. The box has a fixed aspect ratio (home.css), so nothing
 * shifts while it changes. The server renders one finished state (first
 * model, name, format, fastener, price): that is what visitors without JS
 * and with prefers-reduced-motion see, and where the loop starts.
 *
 * Accessibility: the stage is one role="img" with a short description (the
 * changing details are decorative). The pause button (WCAG 2.2.2) is shown
 * by home.js only while the demo moves. The loop also pauses on hover, on
 * keyboard focus inside, off screen and in a hidden tab.
 *
 * All values come from the "Startseite" field group (hero_demo_*), one entry
 * per line, and are labelled "Beispiel" on screen.
 *
 * $args:
 * - models    (string) "Atlantis\nAlucontur\nWoodline"
 * - names     (string) "Andreas Denker | Vertrieb\n..."
 * - formats   (string) "60 × 20 mm\n75 × 30 mm"
 * - fasteners (string) "Magnet\nNadel"
 * - prices    (string) "10 | 7,92 €\n50 | 6,44 €\n..."
 */

defined( 'ABSPATH' ) || exit;

$ip_demo = wp_parse_args( $args ?? array(), array(
	'models'    => '',
	'names'     => '',
	'formats'   => '',
	'fasteners' => '',
	'prices'    => '',
) );

/** Non-empty trimmed lines of a textarea. */
$ip_lines = function ( $text ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $text ) ), 'strlen' ) );
};
/** "a | b" as array( a, b ). */
$ip_pair = function ( $line ) {
	$parts = array_map( 'trim', explode( '|', $line, 2 ) );
	return array( $parts[0], $parts[1] ?? '' );
};

// Material look per model name (home.css .ip-demo__plate--{look}).
$ip_looks = array(
	'atlantis'  => 'atlantis',
	'alucontur' => 'alu',
	'aluprofil' => 'alu',
	'woodline'  => 'wood',
	'classic'   => 'classic',
	'chalk'     => 'chalk',
	'papyrus'   => 'papyrus',
	'translex'  => 'clear',
	'x-style'   => 'xstyle',
);
$ip_models = array();
foreach ( $ip_lines( $ip_demo['models'] ) as $ip_name ) {
	$ip_slug = sanitize_title( $ip_name );
	$ip_look = 'plain';
	foreach ( $ip_looks as $ip_key => $ip_value ) {
		if ( str_contains( $ip_slug, $ip_key ) ) {
			$ip_look = $ip_value;
			break;
		}
	}
	$ip_models[] = array( 'name' => $ip_name, 'look' => $ip_look );
}

$ip_names = array();
foreach ( $ip_lines( $ip_demo['names'] ) as $ip_line ) {
	list( $ip_n, $ip_r ) = $ip_pair( $ip_line );
	if ( '' !== $ip_n ) {
		$ip_names[] = array( 'name' => $ip_n, 'role' => $ip_r );
	}
}

// Width and height in mm from "60 × 20 mm"; the plate in the demo is scaled from them.
$ip_formats = array();
foreach ( $ip_lines( $ip_demo['formats'] ) as $ip_line ) {
	if ( preg_match( '/(\d+(?:[.,]\d+)?)\s*[x×*]\s*(\d+(?:[.,]\d+)?)/u', $ip_line, $ip_m ) ) {
		$ip_w = (float) str_replace( ',', '.', $ip_m[1] );
		$ip_h = (float) str_replace( ',', '.', $ip_m[2] );
		if ( $ip_w > 0 && $ip_h > 0 ) {
			$ip_formats[] = array( 'label' => $ip_line, 'w' => $ip_w, 'h' => $ip_h );
		}
	}
}

$ip_fasteners = $ip_lines( $ip_demo['fasteners'] );

$ip_prices = array();
foreach ( $ip_lines( $ip_demo['prices'] ) as $ip_line ) {
	list( $ip_q, $ip_p ) = $ip_pair( $ip_line );
	if ( '' !== $ip_q && '' !== $ip_p ) {
		$ip_prices[] = array( 'qty' => $ip_q, 'price' => $ip_p );
	}
}

if ( ! $ip_models || ! $ip_names ) {
	return; // Nothing to show: front-page.php then shows the hero photo.
}
if ( ! $ip_formats ) {
	$ip_formats = array( array( 'label' => '', 'w' => 3, 'h' => 1 ) );
}

// Plate size in the stage: widest format = 100 % of the plate area, the others to scale.
$ip_max_w = max( wp_list_pluck( $ip_formats, 'w' ) );
$ip_plate = function ( $format ) use ( $ip_max_w ) {
	$w = round( $format['w'] / $ip_max_w, 4 );
	return sprintf( '--ip-pw: %s; --ip-ratio: %s', $w, round( $format['w'] / $format['h'], 4 ) );
};

$ip_first = array(
	'model'    => $ip_models[0],
	'name'     => $ip_names[0],
	'format'   => $ip_formats[0],
	'fastener' => $ip_fasteners[0] ?? '',
	'price'    => $ip_prices[0] ?? null,
);
$ip_config = array(
	'models'    => $ip_models,
	'names'     => $ip_names,
	'formats'   => array_map( function ( $f ) use ( $ip_max_w ) {
		return array( 'label' => $f['label'], 'pw' => round( $f['w'] / $ip_max_w, 4 ), 'ratio' => round( $f['w'] / $f['h'], 4 ) );
	}, $ip_formats ),
	'fasteners' => $ip_fasteners,
	'prices'    => $ip_prices,
);

/* translators: 1: model, 2: name on the plate */
$ip_alt = sprintf( __( 'Animierte Beispiel-Konfiguration: ein Namensschild %1$s mit dem Namen %2$s, Logo, Format, Befestigung und Staffelpreis, dann „In den Warenkorb“.', 'infoplus' ), $ip_first['model']['name'], $ip_first['name']['name'] );

$ip_logo = '<svg class="ip-demo__logo-svg" viewBox="0 0 48 48" aria-hidden="true" focusable="false"><path d="M24 3c11 7 11 35 0 42C13 38 13 10 24 3z"/><path class="ip-demo__logo-b" d="M3 24c7-11 35-11 42 0-7 11-35 11-42 0z"/></svg>';
$ip_cart = '<svg class="ip-demo__cart-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6.2"/><circle cx="10" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/></svg>';
$ip_tick = '<svg class="ip-demo__tick-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';

$ip_chips = function ( $items, $active, $key ) {
	$out = '';
	foreach ( $items as $i => $label ) {
		$out .= sprintf( '<span class="ip-demo__chip%s" data-ip-demo-%s="%d">%s</span>', $i === $active ? ' is-on' : '', esc_attr( $key ), (int) $i, esc_html( $label ) );
	}
	return $out;
};
?>
<div class="ip-hero__media ip-demo" data-ip-demo="<?php echo esc_attr( wp_json_encode( $ip_config ) ); ?>">
	<div class="ip-demo__stage" role="img" aria-label="<?php echo esc_attr( $ip_alt ); ?>">
		<p class="ip-demo__tag"><span class="ip-demo__dot"></span><?php esc_html_e( 'Beispiel-Konfiguration', 'infoplus' ); ?></p>

		<div class="ip-demo__preview">
			<div class="ip-demo__plate ip-demo__plate--<?php echo esc_attr( $ip_first['model']['look'] ); ?>" style="<?php echo esc_attr( $ip_plate( $ip_first['format'] ) ); ?>" data-ip-demo-plate>
				<span class="ip-demo__logo is-on" data-ip-demo-logo><?php echo $ip_logo; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
				<span class="ip-demo__lines">
					<span class="ip-demo__name" data-ip-demo-name><?php echo esc_html( $ip_first['name']['name'] ); ?></span>
					<span class="ip-demo__role is-on" data-ip-demo-role><?php echo esc_html( $ip_first['name']['role'] ); ?></span>
				</span>
			</div>
		</div>

		<div class="ip-demo__panel">
			<div class="ip-demo__group ip-demo__group--model">
				<span class="ip-demo__label"><?php esc_html_e( 'Modell', 'infoplus' ); ?></span>
				<span class="ip-demo__chips"><?php echo $ip_chips( wp_list_pluck( $ip_models, 'name' ), 0, 'model' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $ip_chips. ?></span>
			</div>
			<?php if ( '' !== $ip_formats[0]['label'] ) : ?>
				<div class="ip-demo__group">
					<span class="ip-demo__label"><?php esc_html_e( 'Format', 'infoplus' ); ?></span>
					<span class="ip-demo__chips"><?php echo $ip_chips( wp_list_pluck( $ip_formats, 'label' ), 0, 'format' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $ip_chips. ?></span>
				</div>
			<?php endif; ?>
			<?php if ( $ip_fasteners ) : ?>
				<div class="ip-demo__group">
					<span class="ip-demo__label"><?php esc_html_e( 'Befestigung', 'infoplus' ); ?></span>
					<span class="ip-demo__chips"><?php echo $ip_chips( $ip_fasteners, 0, 'fastener' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $ip_chips. ?></span>
				</div>
			<?php endif; ?>
			<div class="ip-demo__foot">
				<?php if ( $ip_first['price'] ) : ?>
					<span class="ip-demo__price">
						<span class="ip-demo__price-label"><?php esc_html_e( 'Staffelpreis', 'infoplus' ); ?> <span class="ip-demo__beispiel"><?php esc_html_e( 'Beispiel', 'infoplus' ); ?></span></span>
						<span class="ip-demo__price-value">
							<?php
							printf(
								/* translators: 1: quantity, 2: price per piece */
								esc_html__( 'ab %1$s Stück: %2$s', 'infoplus' ),
								'<span data-ip-demo-qty>' . esc_html( $ip_first['price']['qty'] ) . '</span>',
								'<b data-ip-demo-price>' . esc_html( $ip_first['price']['price'] ) . '</b>'
							);
							?>
						</span>
					</span>
				<?php endif; ?>
				<span class="ip-demo__cart" data-ip-demo-cart>
					<?php echo $ip_cart . $ip_tick; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
					<?php // Both labels share one grid cell, so the button keeps its width when the label changes. ?>
					<span class="ip-demo__cart-labels">
						<span class="ip-demo__cart-label"><?php esc_html_e( 'In den Warenkorb', 'infoplus' ); ?></span>
						<span class="ip-demo__cart-label ip-demo__cart-label--added"><?php esc_html_e( 'Im Warenkorb', 'infoplus' ); ?></span>
					</span>
				</span>
			</div>
		</div>
	</div>
	<button type="button" class="ip-demo__pause" aria-pressed="false" data-ip-demo-pause hidden>
		<svg class="ip-demo__pause-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 6v12M15 6v12"/></svg>
		<svg class="ip-demo__play-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5.5v13l10.5-6.5z"/></svg>
		<span class="ip-sr"><?php esc_html_e( 'Animation anhalten', 'infoplus' ); ?></span>
	</button>
</div>
