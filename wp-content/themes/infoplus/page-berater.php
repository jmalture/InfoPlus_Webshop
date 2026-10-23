<?php
/**
 * Namensschild-Berater (/berater/), picked by slug. See inc/finder.php.
 *
 * Left: one question per screen (a fieldset of radio cards, so keyboard and
 * screen readers get a native radio group); a pick moves on by itself.
 * Right: the live preview. The badge takes the look of the current favourite,
 * shows the insert, the logo, the contour, the fastening and the quantity
 * as they are answered, and the counter shows how many models still fit.
 * Below: the three picks, filled in by assets/js/finder.js.
 *
 * The answers live in the URL (?use=…&change=…), so a reload keeps them and a
 * link shares the result. ?weg=self|logo|print (from the front page cards)
 * answers the second question in advance.
 *
 * Needs JavaScript; without it the page links to the product overview.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$ip_data      = ip_finder_data();
$ip_questions = ip_finder_questions();
$ip_count     = count( $ip_questions );
$ip_plates    = function_exists( 'ip_shop_plates_url' ) ? ip_shop_plates_url() : home_url( '/namensschilder/' );
$ip_opt_look  = array( 'often' => 'self', 'sometimes' => 'logo', 'rarely' => 'print' );
?>
<div class="ip-finder" data-ip-finder>
	<div class="ip-shell">
		<?php ip_breadcrumb( array( array( __( 'Home', 'infoplus' ), home_url( '/' ) ), array( get_the_title(), '' ) ) ); ?>

		<header class="ip-finder__head">
			<p class="ip-kicker"><?php esc_html_e( 'Namensschild-Berater', 'infoplus' ); ?></p>
			<h1 class="ip-finder__title"><?php esc_html_e( 'Welches Namensschild', 'infoplus' ); ?> <em><?php esc_html_e( 'passt zu Ihnen?', 'infoplus' ); ?></em></h1>
			<p class="ip-finder__lead"><?php esc_html_e( 'Sechs kurze Fragen, rund eine Minute. Danach zeigen wir Ihnen drei passende Modelle und erklären, warum.', 'infoplus' ); ?></p>
		</header>

		<?php if ( ! $ip_data ) : ?>
			<p class="ip-finder__fallback ip-card"><?php esc_html_e( 'Der Berater ist gerade nicht verfügbar.', 'infoplus' ); ?> <a href="<?php echo esc_url( $ip_plates ); ?>"><?php esc_html_e( 'Alle Namensschilder ansehen', 'infoplus' ); ?></a></p>
		<?php else : ?>
			<noscript><p class="ip-finder__fallback ip-card"><?php esc_html_e( 'Der Berater braucht JavaScript.', 'infoplus' ); ?> <a href="<?php echo esc_url( $ip_plates ); ?>"><?php esc_html_e( 'Alle Namensschilder ansehen', 'infoplus' ); ?></a></p></noscript>

			<div class="ip-finder__grid ip-js-only">
				<section class="ip-finder__panel ip-panel" aria-label="<?php esc_attr_e( 'Fragen', 'infoplus' ); ?>">
					<ol class="ip-finder__progress" role="list" data-ip-finder-progress>
						<?php foreach ( $ip_questions as $ip_i => $ip_q ) : ?>
							<li class="ip-finder__dot" data-ip-finder-dot="<?php echo esc_attr( $ip_q['key'] ); ?>"><span class="ip-sr"><?php echo esc_html( sprintf( /* translators: 1: step, 2: total */ __( 'Frage %1$d von %2$d', 'infoplus' ), $ip_i + 1, $ip_count ) ); ?></span></li>
						<?php endforeach; ?>
					</ol>

					<form class="ip-finder__form" data-ip-finder-form novalidate>
						<?php foreach ( $ip_questions as $ip_i => $ip_q ) : ?>
							<fieldset class="ip-fq ip-fq--<?php echo esc_attr( $ip_q['visual'] ); ?>" data-ip-fq="<?php echo esc_attr( $ip_q['key'] ); ?>"<?php echo 0 === $ip_i ? '' : ' hidden'; ?>>
								<p class="ip-fq__step"><?php echo esc_html( sprintf( /* translators: 1: step, 2: total */ __( 'Frage %1$d von %2$d', 'infoplus' ), $ip_i + 1, $ip_count ) ); ?></p>
								<legend class="ip-fq__title" tabindex="-1"><?php echo esc_html( $ip_q['title'] ); ?></legend>
								<?php if ( ! empty( $ip_q['hint'] ) ) : ?>
									<p class="ip-fq__hint"><?php echo esc_html( $ip_q['hint'] ); ?></p>
								<?php endif; ?>
								<div class="ip-fq__options">
									<?php foreach ( $ip_q['options'] as $ip_o ) : ?>
										<label class="ip-fopt">
											<input class="ip-fopt__input" type="radio" name="<?php echo esc_attr( $ip_q['key'] ); ?>" value="<?php echo esc_attr( $ip_o['value'] ); ?>">
											<span class="ip-fopt__visual" aria-hidden="true">
												<?php
												if ( 'option' === $ip_q['visual'] ) {
													echo ip_finder_plate( 'atlantis', $ip_opt_look[ $ip_o['value'] ] ?? 'print' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup.
												} elseif ( 'material' === $ip_q['visual'] ) {
													echo ip_finder_plate( $ip_o['icon'], 'print' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup.
												} elseif ( 'count' === $ip_q['visual'] ) {
													echo '<span class="ip-fcount" style="--n:' . (int) $ip_o['icon'] . '">' . str_repeat( '<i></i>', (int) $ip_o['icon'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- static markup.
												} else {
													echo '<span class="ip-tile">' . ip_finder_icon( $ip_o['icon'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
												}
												?>
											</span>
											<span class="ip-fopt__text">
												<span class="ip-fopt__label"><?php echo esc_html( $ip_o['label'] ); ?></span>
												<?php if ( ! empty( $ip_o['text'] ) ) : ?>
													<span class="ip-fopt__sub"><?php echo esc_html( $ip_o['text'] ); ?></span>
												<?php endif; ?>
											</span>
											<span class="ip-fopt__check" aria-hidden="true"><?php echo ip_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</fieldset>
						<?php endforeach; ?>

						<div class="ip-finder__done" data-ip-finder-done hidden>
							<p class="ip-fq__step"><?php esc_html_e( 'Fertig', 'infoplus' ); ?></p>
							<h2 class="ip-fq__title" tabindex="-1" data-ip-finder-done-title><?php esc_html_e( 'Ihre Antworten', 'infoplus' ); ?></h2>
							<ul class="ip-finder__answers" role="list" data-ip-finder-answers></ul>
						</div>
					</form>

					<div class="ip-finder__nav">
						<button type="button" class="ip-btn ip-btn--ghost ip-btn--sm" data-ip-finder-back hidden><?php echo ip_icon( 'arrow-left' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php esc_html_e( 'Zurück', 'infoplus' ); ?></button>
						<button type="button" class="ip-finder__restart" data-ip-finder-restart hidden><?php echo ip_icon( 'refresh' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php esc_html_e( 'Neu starten', 'infoplus' ); ?></button>
						<button type="button" class="ip-btn ip-btn--sm" data-ip-finder-next hidden><?php esc_html_e( 'Weiter', 'infoplus' ); ?><?php echo ip_shop_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></button>
						<button type="button" class="ip-btn ip-btn--sm" data-ip-finder-show hidden><?php esc_html_e( 'Empfehlung ansehen', 'infoplus' ); ?><?php echo ip_shop_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></button>
					</div>
				</section>

				<aside class="ip-finder__stage" aria-label="<?php esc_attr_e( 'Live-Vorschau', 'infoplus' ); ?>" data-ip-finder-stage>
					<div class="ip-fstage">
						<div class="ip-fstage__top">
							<p class="ip-fstage__tag"><span class="ip-fstage__dot"></span><?php esc_html_e( 'Live-Vorschau', 'infoplus' ); ?></p>
							<p class="ip-fstage__scene" data-ip-finder-scene hidden></p>
						</div>

						<div class="ip-fstage__bed">
							<div class="ip-fstage__stack" data-ip-finder-stack>
								<div class="ip-fbadge ip-fbadge--atlantis" data-ip-finder-badge data-option="" data-logo="" data-fastener="">
									<span class="ip-fbadge__contour" aria-hidden="true"></span>
									<span class="ip-fbadge__logo" aria-hidden="true"><svg viewBox="0 0 48 48" focusable="false"><path d="M24 3c11 7 11 35 0 42C13 38 13 10 24 3z"/><path class="ip-fbadge__logo-b" d="M3 24c7-11 35-11 42 0-7 11-35 11-42 0z"/></svg></span>
									<span class="ip-fbadge__lines">
										<span class="ip-fbadge__name" data-ip-finder-name><?php esc_html_e( 'Andreas Denker', 'infoplus' ); ?></span>
										<span class="ip-fbadge__role" data-ip-finder-role><?php esc_html_e( 'Vertrieb', 'infoplus' ); ?></span>
									</span>
									<span class="ip-fbadge__insert-tag" aria-hidden="true"><?php esc_html_e( 'Einleger', 'infoplus' ); ?></span>
								</div>
							</div>
							<p class="ip-fstage__qty" data-ip-finder-qty hidden></p>
						</div>

						<div class="ip-fstage__chips" data-ip-finder-chips aria-live="polite"></div>

						<div class="ip-fstage__foot">
							<label class="ip-fstage__field">
								<span class="ip-fstage__label"><?php esc_html_e( 'Ihr Name auf der Vorschau', 'infoplus' ); ?></span>
								<input type="text" class="ip-fstage__input" maxlength="28" placeholder="<?php esc_attr_e( 'Andreas Denker', 'infoplus' ); ?>" autocomplete="name" data-ip-finder-name-input>
							</label>
							<div class="ip-fstage__count">
								<span class="ip-fstage__num" data-ip-finder-count><?php echo (int) count( $ip_data['candidates'] ); ?></span>
								<span class="ip-fstage__count-label" data-ip-finder-count-label><?php esc_html_e( 'passende Varianten', 'infoplus' ); ?></span>
							</div>
						</div>
						<p class="ip-fstage__fav" data-ip-finder-fav aria-live="polite"></p>
					</div>
				</aside>
			</div>

			<section class="ip-finder__results" aria-labelledby="ip-finder-results-title" data-ip-finder-results hidden>
				<header class="ip-finder__results-head">
					<p class="ip-kicker"><?php esc_html_e( 'Ihre Empfehlung', 'infoplus' ); ?></p>
					<h2 class="ip-head__title" id="ip-finder-results-title" tabindex="-1"><?php esc_html_e( 'Drei Modelle,', 'infoplus' ); ?> <em><?php esc_html_e( 'die zu Ihnen passen', 'infoplus' ); ?></em></h2>
					<p class="ip-sub" data-ip-finder-results-sub></p>
				</header>
				<ol class="ip-finder__picks" role="list" data-ip-finder-picks></ol>
				<div class="ip-finder__more" data-ip-finder-more hidden>
					<button type="button" class="ip-btn ip-btn--ghost" aria-expanded="false" data-ip-finder-more-toggle></button>
					<ul class="ip-finder__list" role="list" data-ip-finder-list hidden></ul>
				</div>
				<div class="ip-finder__cta ip-card">
					<span class="ip-tile"><?php echo ip_icon( 'chat' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
					<div>
						<p class="ip-finder__cta-title"><?php esc_html_e( 'Lieber persönlich beraten lassen?', 'infoplus' ); ?></p>
						<p class="ip-finder__cta-text"><?php esc_html_e( 'Wir schicken Ihnen kostenlose Muster oder melden uns mit einem Angebot. Ihre Antworten geben wir gleich mit.', 'infoplus' ); ?></p>
					</div>
					<div class="ip-finder__cta-actions">
						<button type="button" class="ip-btn" data-ip-finder-ask="samples"><?php esc_html_e( 'Kostenlose Muster anfordern', 'infoplus' ); ?></button>
						<button type="button" class="ip-btn ip-btn--ghost" data-ip-finder-ask="advice"><?php esc_html_e( 'Beratung anfragen', 'infoplus' ); ?></button>
						<button type="button" class="ip-btn ip-btn--ghost" data-ip-finder-share><?php esc_html_e( 'Link zum Ergebnis kopieren', 'infoplus' ); ?></button>
					</div>
				</div>
			</section>

			<?php
			$ip_payload = array(
				'candidates' => $ip_data['candidates'],
				'options'    => $ip_data['options'],
				'meta'       => $ip_data['meta'],
				'questions'  => array_map( function ( $q ) {
					return array(
						'key'     => $q['key'],
						'title'   => $q['title'],
						'options' => array_map( function ( $o ) {
							return array( 'value' => $o['value'], 'label' => $o['label'] );
						}, $q['options'] ),
					);
				}, $ip_questions ),
			);
			?>
			<script type="application/json" id="ip-finder-data"><?php echo wp_json_encode( $ip_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
		<?php endif; ?>
	</div>
</div>
<?php
get_footer();
