<?php
/**
 * Namensschild-Berater ("Finder"): a guided picker for new customers.
 *
 * Six questions, one per screen, with a live preview of the badge that
 * changes with every answer; at the end three picks (Beste Wahl, Preis-Tipp,
 * Premium) with the reasons, the Staffelpreis for the chosen quantity and a
 * link into the product page with the model and quantity set.
 *
 * Division of labour:
 * - Infoplus Suite, module Finder: the candidate list (one per product family
 *   and model) with prices, Staffelpreise, fastenings, order type and the
 *   placeholder traits (material, look, use cases). infoplus_suite_finder_data().
 * - This file: the page (slug "berater", page-berater.php), the questions,
 *   the bundle (assets/css/finder.css, assets/js/finder.js), the inquiry drawer
 *   on that page ("Kostenlose Muster", "Beratung") and the "Drei Wege" section
 *   of the front page.
 * - assets/js/finder.js scores the candidates in the browser, so the live
 *   counter and the preview need no requests.
 *
 * Status: internal showcase. Material, look and use cases of the families are
 * placeholders (Suite Modules/Finder/Traits.php).
 */

defined( 'ABSPATH' ) || exit;

/** Slug of the finder page. */
const IP_FINDER_SLUG = 'berater';

/** True on the finder page. */
function ip_finder_is_page() {
	return is_page( IP_FINDER_SLUG );
}

/** URL of the finder page ('' when the page does not exist or the Suite module is missing). */
function ip_finder_url( $args = array() ) {
	if ( ! function_exists( 'infoplus_suite_finder_data' ) ) {
		return '';
	}
	$page = get_page_by_path( IP_FINDER_SLUG );
	if ( ! $page || 'publish' !== $page->post_status ) {
		return '';
	}
	return $args ? add_query_arg( $args, get_permalink( $page ) ) : get_permalink( $page );
}

/** The finder data from the Suite, or null. */
function ip_finder_data() {
	if ( ! function_exists( 'infoplus_suite_finder_data' ) ) {
		return null;
	}
	$data = infoplus_suite_finder_data();
	return is_array( $data ) && ! empty( $data['candidates'] ) ? $data : null;
}

/** The inquiry drawer on the finder page, product variant (it has the "Kostenlose Muster" switch). */
add_filter( 'ip_inquiry_variant', function ( $variant ) {
	return ip_finder_is_page() && function_exists( 'infoplus_suite_inquiry_form' ) ? 'product' : $variant;
} );

/**
 * The questions. Keys and option values are what finder.js scores against;
 * labels and hints can change freely.
 *
 * - key, title, hint
 * - visual: icon | option | material | count (how the cards are drawn)
 * - options: value, label, text, icon
 */
function ip_finder_questions() {
	$q = array(
		array(
			'key'     => 'use',
			'title'   => __( 'Wo werden die Namensschilder getragen?', 'infoplus' ),
			'hint'    => __( 'Damit wissen wir, wie robust und repräsentativ das Schild sein soll.', 'infoplus' ),
			'visual'  => 'icon',
			'options' => array(
				array( 'value' => 'staff', 'label' => __( 'Im Büro und im Team', 'infoplus' ), 'text' => __( 'Täglich im Kundenkontakt', 'infoplus' ), 'icon' => 'briefcase' ),
				array( 'value' => 'event', 'label' => __( 'Messe und Event', 'infoplus' ), 'text' => __( 'Für Tage, nicht für Jahre', 'infoplus' ), 'icon' => 'ticket' ),
				array( 'value' => 'hospitality', 'label' => __( 'Empfang, Hotel, Gastro', 'infoplus' ), 'text' => __( 'Repräsentativ und gepflegt', 'infoplus' ), 'icon' => 'bell' ),
				array( 'value' => 'care', 'label' => __( 'Praxis, Pflege, Labor', 'infoplus' ), 'text' => __( 'Oft gereinigt, sicher getragen', 'infoplus' ), 'icon' => 'cross' ),
				array( 'value' => 'industry', 'label' => __( 'Werkstatt und Lager', 'infoplus' ), 'text' => __( 'Robust für harten Einsatz', 'infoplus' ), 'icon' => 'tool' ),
			),
		),
		array(
			'key'     => 'change',
			'title'   => __( 'Wie oft ändern sich die Namen in Ihrem Team?', 'infoplus' ),
			'hint'    => __( 'Davon hängt ab, wer die Namen druckt: Sie selbst oder wir.', 'infoplus' ),
			'visual'  => 'option',
			'options' => array(
				array( 'value' => 'often', 'label' => __( 'Oft', 'infoplus' ), 'text' => __( 'Neue Kollegen, Aushilfen, Gäste', 'infoplus' ), 'icon' => 'self' ),
				array( 'value' => 'sometimes', 'label' => __( 'Ab und zu', 'infoplus' ), 'text' => __( 'Fester Stamm, gelegentlich neue Namen', 'infoplus' ), 'icon' => 'logo' ),
				array( 'value' => 'rarely', 'label' => __( 'Kaum', 'infoplus' ), 'text' => __( 'Das Schild gehört fest zur Person', 'infoplus' ), 'icon' => 'print' ),
			),
		),
		array(
			'key'     => 'logo',
			'title'   => __( 'Soll Ihr Logo auf das Schild?', 'infoplus' ),
			'hint'    => __( 'Auf Wunsch fertigen wir das Schild sogar in der Form Ihres Logos (Sonderkontur).', 'infoplus' ),
			'visual'  => 'icon',
			'options' => array(
				array( 'value' => 'none', 'label' => __( 'Nein, nur der Name', 'infoplus' ), 'text' => __( 'Schlicht und klar', 'infoplus' ), 'icon' => 'type' ),
				array( 'value' => 'logo', 'label' => __( 'Ja, mit Logo', 'infoplus' ), 'text' => __( 'Farbig gedruckt oder graviert', 'infoplus' ), 'icon' => 'logo-mark' ),
				array( 'value' => 'shape', 'label' => __( 'Ja, in Form des Logos', 'infoplus' ), 'text' => __( 'Sonderkontur, ein echter Hingucker', 'infoplus' ), 'icon' => 'shape' ),
			),
		),
		array(
			'key'     => 'material',
			'title'   => __( 'Welche Optik gefällt Ihnen?', 'infoplus' ),
			'hint'    => __( 'Die Vorschau zeigt das Material sofort.', 'infoplus' ),
			'visual'  => 'material',
			'options' => array(
				array( 'value' => 'metal', 'label' => __( 'Metall', 'infoplus' ), 'text' => __( 'Edel und langlebig', 'infoplus' ), 'icon' => 'alu' ),
				array( 'value' => 'acrylic', 'label' => __( 'Acrylglas', 'infoplus' ), 'text' => __( 'Modern und glänzend', 'infoplus' ), 'icon' => 'clear' ),
				array( 'value' => 'wood', 'label' => __( 'Holz', 'infoplus' ), 'text' => __( 'Natürlich und nachhaltig', 'infoplus' ), 'icon' => 'wood' ),
				array( 'value' => 'plastic', 'label' => __( 'Kunststoff', 'infoplus' ), 'text' => __( 'Leicht und günstig', 'infoplus' ), 'icon' => 'classic' ),
				array( 'value' => 'writable', 'label' => __( 'Tafel-Optik', 'infoplus' ), 'text' => __( 'Für Gastro und Events', 'infoplus' ), 'icon' => 'chalk' ),
				array( 'value' => 'any', 'label' => __( 'Egal', 'infoplus' ), 'text' => __( 'Zeigen Sie mir das Beste', 'infoplus' ), 'icon' => 'atlantis' ),
			),
		),
		array(
			'key'     => 'fastener',
			'title'   => __( 'Wie soll das Schild halten?', 'infoplus' ),
			'hint'    => __( 'Magnete schonen die Kleidung, Nadeln sind die klassische und günstige Wahl.', 'infoplus' ),
			'visual'  => 'icon',
			'options' => array(
				array( 'value' => 'magnet', 'label' => __( 'Magnet', 'infoplus' ), 'text' => __( 'Keine Löcher in der Kleidung', 'infoplus' ), 'icon' => 'magnet' ),
				array( 'value' => 'pin', 'label' => __( 'Nadel', 'infoplus' ), 'text' => __( 'Klassisch und günstig', 'infoplus' ), 'icon' => 'needle' ),
				array( 'value' => 'clip', 'label' => __( 'Clip', 'infoplus' ), 'text' => __( 'Zum Anklemmen an Tasche oder Kragen', 'infoplus' ), 'icon' => 'clip' ),
				array( 'value' => 'any', 'label' => __( 'Egal', 'infoplus' ), 'text' => __( 'Entscheide ich später', 'infoplus' ), 'icon' => 'help' ),
			),
		),
		array(
			'key'     => 'qty',
			'title'   => __( 'Für wie viele Personen?', 'infoplus' ),
			'hint'    => __( 'Mit Staffelpreisen: je mehr Schilder, desto günstiger das einzelne.', 'infoplus' ),
			'visual'  => 'count',
			'options' => array(
				array( 'value' => '5', 'label' => __( '1 bis 9', 'infoplus' ), 'text' => __( 'Kleines Team', 'infoplus' ), 'icon' => '1' ),
				array( 'value' => '25', 'label' => __( '10 bis 49', 'infoplus' ), 'text' => __( 'Abteilung oder Filiale', 'infoplus' ), 'icon' => '2' ),
				array( 'value' => '100', 'label' => __( '50 bis 249', 'infoplus' ), 'text' => __( 'Ganzes Unternehmen', 'infoplus' ), 'icon' => '3' ),
				array( 'value' => '300', 'label' => __( '250 und mehr', 'infoplus' ), 'text' => __( 'Großauftrag, Serienfertigung', 'infoplus' ), 'icon' => '4' ),
			),
		),
	);
	/**
	 * Filter the finder questions. Option values are scored by assets/js/finder.js.
	 *
	 * @param array $q
	 */
	return (array) apply_filters( 'ip_finder_questions', $q );
}

/**
 * Icons of the finder cards (24 px grid, 1.75 stroke like inc/icons.php).
 * Falls back to ip_icon() for the shared set.
 */
function ip_finder_icon( $name ) {
	$paths = array(
		'ticket'    => '<path d="M4 7a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v3a2 2 0 0 0 0 4v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a2 2 0 0 0 0-4z"/><path d="M14 6v12" stroke-dasharray="2 2"/>',
		'bell'      => '<path d="M4 17h16M6 17a6 6 0 0 1 12 0M12 8V6M10 6h4"/><path d="M3 20h18"/>',
		'cross'     => '<path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z"/>',
		'tool'      => '<path d="M14.5 5.5a4 4 0 0 0 4.9 5L21 12l-9 9-3-3 9-9-1.5-1.5a4 4 0 0 0-5-4.9l2.6 2.6-2 2-2.6-2.6"/>',
		'type'      => '<path d="M5 6V4h14v2M12 4v16M9 20h6"/>',
		'logo-mark' => '<path d="M12 3c5 3.5 5 14.5 0 18-5-3.5-5-14.5 0-18z"/><path d="M3 12c3.5-5 14.5-5 18 0-3.5 5-14.5 5-18 0z"/>',
		'shape'     => '<path d="M7 4h7l6 6v7a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3z"/><path d="M14 4v6h6"/>',
		'magnet'    => '<path d="M6 4v8a6 6 0 0 0 12 0V4h-4v8a2 2 0 0 1-4 0V4z"/><path d="M6 8h4M14 8h4"/>',
		'needle'    => '<path d="M4 20L17 7"/><circle cx="18.5" cy="5.5" r="2"/><path d="M4 20l2.5-.5"/>',
		'clip'      => '<path d="M8 4v11a4 4 0 0 0 8 0V6a2.5 2.5 0 0 0-5 0v9a1 1 0 0 0 2 0V7"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return ip_icon( $name );
	}
	return '<svg class="ip-icon ip-icon--' . esc_attr( $name ) . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * A small badge drawn in CSS (finder.css .ip-fplate), used on the option cards,
 * in the "Drei Wege" section and as the result card fallback.
 *
 * @param string $look   Material look (alu, wood, clear, ...).
 * @param string $option self | logo | print | '' (no insert line).
 * @param string $extra  Extra classes.
 */
function ip_finder_plate( $look = 'plain', $option = 'print', $extra = '' ) {
	$classes = 'ip-fplate ip-fplate--' . sanitize_html_class( $look ) . ' ip-fplate--opt-' . sanitize_html_class( $option ) . ( $extra ? ' ' . $extra : '' );
	return '<span class="' . esc_attr( $classes ) . '" aria-hidden="true">'
		. '<span class="ip-fplate__logo"></span>'
		. '<span class="ip-fplate__lines"><span class="ip-fplate__name"></span><span class="ip-fplate__role"></span></span>'
		. '</span>';
}

/** "4,40 €" in the shop format. */
function ip_finder_money( $amount ) {
	$amount = (float) $amount;
	if ( function_exists( 'wc_price' ) ) {
		return wp_strip_all_tags( html_entity_decode( wc_price( $amount ), ENT_QUOTES, 'UTF-8' ) );
	}
	return number_format_i18n( $amount, 2 ) . ' €';
}

/**
 * "Drei Wege zu Ihrem Namensschild": the three options with live "ab" prices
 * and a button into the finder. Front page, after the hero. Head texts and
 * the switch come from the field group "Startseite" (tab "Drei Wege").
 *
 * @param int $pid Front page ID.
 */
function ip_finder_ways_section( $pid ) {
	if ( ! ip_flag( 'ways_show', $pid, true ) ) {
		return;
	}
	$data = ip_finder_data();
	$url  = ip_finder_url();
	if ( ! $data || ! $url || empty( $data['options'] ) ) {
		return;
	}
	$tax   = (string) ( $data['meta']['tax'] ?? '' );
	$looks = array( 'self' => 'window', 'logo' => 'atlantis', 'print' => 'alu' );
	$head  = ip_section_fields( 'ways', $pid );
	?>
	<section class="ip-section ip-ways" aria-labelledby="ip-ways-title">
		<div class="ip-shell">
			<?php ip_section_head( array_merge( $head, array( 'id' => 'ip-ways-title' ) ) ); ?>
			<ol class="ip-ways__list" role="list">
				<?php
				$i = 0;
				foreach ( $data['options'] as $key => $opt ) :
					++$i;
					if ( empty( $opt['models'] ) ) {
						continue;
					}
					?>
					<li class="ip-ways__item">
						<article class="ip-way ip-card">
							<div class="ip-way__visual">
								<span class="ip-way__num" aria-hidden="true"><?php echo (int) $i; ?></span>
								<?php echo ip_finder_plate( $looks[ $key ] ?? 'plain', $key, 'ip-fplate--lg' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup. ?>
							</div>
							<div class="ip-way__body">
								<h3 class="ip-way__title"><?php echo esc_html( $opt['label'] ); ?></h3>
								<p class="ip-way__short"><?php echo esc_html( $opt['short'] ); ?></p>
								<ul class="ip-way__points" role="list">
									<?php foreach ( (array) $opt['points'] as $point ) : ?>
										<li><?php echo ip_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php echo esc_html( $point ); ?></li>
									<?php endforeach; ?>
								</ul>
								<p class="ip-way__fit"><?php echo esc_html( $opt['fit'] ); ?></p>
								<div class="ip-way__foot">
									<?php if ( ! empty( $opt['from'] ) ) : ?>
										<p class="ip-way__price">
											<span class="ip-way__from"><?php esc_html_e( 'ab', 'infoplus' ); ?></span>
											<b><?php echo esc_html( ip_finder_money( $opt['from'] ) ); ?></b>
											<span class="ip-way__unit"><?php echo esc_html( sprintf( /* translators: %s: tax note */ __( 'pro Stück, %s', 'infoplus' ), $tax ) ); ?></span>
										</p>
									<?php endif; ?>
									<p class="ip-way__count">
										<?php
										/* translators: 1: number of models, 2: number of product families */
										echo esc_html( sprintf( __( '%1$d Varianten in %2$d Serien', 'infoplus' ), (int) $opt['models'], (int) $opt['families'] ) );
										?>
									</p>
								</div>
								<a class="ip-btn ip-btn--ghost ip-way__cta" href="<?php echo esc_url( ip_finder_url( array( 'weg' => $key ) ) ); ?>"><?php esc_html_e( 'Passende Modelle finden', 'infoplus' ); ?><?php echo ip_shop_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></a>
							</div>
						</article>
					</li>
				<?php endforeach; ?>
			</ol>
			<div class="ip-ways__help ip-card">
				<span class="ip-tile"><?php echo ip_icon( 'help' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
				<div class="ip-ways__help-text">
					<p class="ip-ways__help-title"><?php echo esc_html( (string) ip_field( 'ways_help_title', $pid, __( 'Unsicher, welches Schild passt?', 'infoplus' ) ) ); ?></p>
					<p class="ip-ways__help-sub"><?php echo esc_html( (string) ip_field( 'ways_help_text', $pid, __( 'Sechs kurze Fragen, eine Minute. Danach empfehlen wir Ihnen drei Modelle und sagen, warum.', 'infoplus' ) ) ); ?></p>
				</div>
				<a class="ip-btn ip-btn--lg" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( (string) ip_field( 'ways_help_label', $pid, __( 'Berater starten', 'infoplus' ) ) ); ?><?php echo ip_shop_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></a>
			</div>
			<p class="ip-ways__note"><?php esc_html_e( 'Kostenlose Muster gibt es für jedes Modell: einfach im Berater oder in der Anfrage anfordern.', 'infoplus' ); ?></p>
		</div>
	</section>
	<?php
}
