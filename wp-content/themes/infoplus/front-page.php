<?php
/**
 * Front page (page 8 "Home"). Port of the old Elementor page in the
 * DESIGN.md style: hero, featured models (product cards), occasions as tabs,
 * benefits, quality + customer logos, service promise, long sortiment
 * texts and a contact band.
 *
 * Every text, image, link and list is a field of the group "Startseite"
 * (acf-json/group_ip_home.json); defaults are the old page's content, so
 * the page renders the same before anyone edits it. Contact data comes from
 * the site settings. Sections render only when they have content and their
 * "anzeigen" switch is on.
 *
 * Hero visual: a live mini configurator (template-parts/hero-demo.php,
 * switch "Animierte Vorschau statt Bild"); the hero photo is the fallback when
 * the switch is off or the demo has no models / names.
 *
 * Dropped from the old page on purpose: the decorative background photos of
 * the "Vorteile" and "Serviceversprechen" bands (DESIGN.md 1 and 10: calm
 * white/grey bands) and the hand-typed prices on the two model teasers (the
 * product cards show no prices, owner decision for all listings).
 */

defined( 'ABSPATH' ) || exit;

get_header();

$ip_pid = (int) get_queried_object_id();
if ( ! $ip_pid ) {
	$ip_pid = (int) get_option( 'page_on_front' );
}
$ip_f = function ( $name, $default = '' ) use ( $ip_pid ) {
	return ip_field( $name, $ip_pid, $default );
};
$ip_on = function ( $section ) use ( $ip_pid ) {
	return ip_flag( $section . '_show', $ip_pid, true );
};
$ip_svg_next = function_exists( 'ip_shop_arrow' ) ? ip_shop_arrow() : '';
?>
<div class="ip-home">

	<?php
	/* ── Hero ───────────────────────────────────────────────────────────── */
	$ip_hero_img   = (int) $ip_f( 'hero_image', 0 );
	$ip_hero_title = (string) $ip_f( 'hero_title' );
	$ip_hero_acc   = (string) $ip_f( 'hero_title_accent' );
	$ip_cta1       = array( (string) $ip_f( 'hero_cta_label' ), ip_content_url( $ip_f( 'hero_cta_url' ) ) );
	$ip_cta2       = array( (string) $ip_f( 'hero_cta2_label' ), ip_content_url( $ip_f( 'hero_cta2_url' ) ) );
	?>
	<section class="ip-hero" aria-labelledby="ip-hero-title">
		<div class="ip-shell ip-hero__grid">
			<div class="ip-hero__text">
				<?php if ( $ip_f( 'hero_kicker' ) ) : ?>
					<p class="ip-kicker"><?php echo esc_html( $ip_f( 'hero_kicker' ) ); ?></p>
				<?php endif; ?>
				<h1 class="ip-hero__title" id="ip-hero-title">
					<?php
					echo esc_html( $ip_hero_title ? $ip_hero_title : get_bloginfo( 'name' ) );
					if ( $ip_hero_acc ) {
						echo ' <em>' . esc_html( $ip_hero_acc ) . '</em>';
					}
					?>
				</h1>
				<?php echo ip_paras( $ip_f( 'hero_text' ), 'ip-hero__lead' ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_paras() escapes. ?>
				<?php if ( ( $ip_cta1[0] && $ip_cta1[1] ) || ( $ip_cta2[0] && $ip_cta2[1] ) ) : ?>
					<div class="ip-hero__actions">
						<?php if ( $ip_cta1[0] && $ip_cta1[1] ) : ?>
							<a class="ip-btn ip-btn--lg" href="<?php echo esc_url( $ip_cta1[1] ); ?>"><?php echo esc_html( $ip_cta1[0] ); ?><?php echo $ip_svg_next; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></a>
						<?php endif; ?>
						<?php if ( $ip_cta2[0] && $ip_cta2[1] ) : ?>
							<a class="ip-btn ip-btn--ghost ip-btn--lg" href="<?php echo esc_url( $ip_cta2[1] ); ?>"><?php echo esc_html( $ip_cta2[0] ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
			<?php
			// Live configurator demo (template-parts/hero-demo.php); the photo stays as the fallback when it is switched off.
			$ip_demo_html = '';
			if ( ip_flag( 'hero_demo_show', $ip_pid, true ) ) {
				ob_start();
				get_template_part( 'template-parts/hero-demo', null, array(
					'models'    => (string) $ip_f( 'hero_demo_models' ),
					'names'     => (string) $ip_f( 'hero_demo_names' ),
					'formats'   => (string) $ip_f( 'hero_demo_formats' ),
					'fasteners' => (string) $ip_f( 'hero_demo_fasteners' ),
					'prices'    => (string) $ip_f( 'hero_demo_prices' ),
				) );
				$ip_demo_html = trim( (string) ob_get_clean() );
			}
			?>
			<?php if ( $ip_demo_html ) : ?>
				<?php echo $ip_demo_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the template part. ?>
			<?php elseif ( $ip_hero_img ) : ?>
				<div class="ip-hero__media">
					<?php
					// LCP image of the page: eager, high priority, never faded in.
					echo ip_img( $ip_hero_img, '(min-width: 1101px) 760px, (min-width: 768px) 55vw, 100vw', array( 'alt' => '', 'loading' => 'eager', 'fetchpriority' => 'high', 'class' => 'ip-hero__img' ), 'ip-1600' ); // phpcs:ignore WordPress.Security.EscapeOutput -- core image HTML.
					?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<?php
	/* ── Models (product cards) ─────────────────────────────────────────── */
	$ip_models = array();
	if ( $ip_on( 'models' ) ) {
		foreach ( (array) $ip_f( 'models_products', array() ) as $ip_item ) {
			$ip_mid = $ip_item instanceof WP_Post ? $ip_item->ID : (int) $ip_item;
			if ( $ip_mid && 'publish' === get_post_status( $ip_mid ) ) {
				$ip_models[] = $ip_mid;
			}
		}
	}
	if ( $ip_models ) :
		ip_prime_posts( $ip_models );
		$ip_link_label = (string) $ip_f( 'models_link_label' );
		$ip_cols       = min( count( $ip_models ), 4 );
		?>
		<section class="ip-section ip-models" aria-labelledby="ip-models-title">
			<div class="ip-shell">
				<div class="ip-section__top">
					<?php ip_section_head( array_merge( ip_section_fields( 'models', $ip_pid ), array( 'id' => 'ip-models-title' ) ) ); ?>
					<?php if ( $ip_link_label && function_exists( 'ip_shop_plates_url' ) ) : ?>
						<a class="ip-btn ip-btn--ghost ip-section__more" href="<?php echo esc_url( ip_shop_plates_url() ); ?>"><?php echo esc_html( $ip_link_label ); ?><?php echo $ip_svg_next; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></a>
					<?php endif; ?>
				</div>
				<ul class="ip-swipe ip-models__list ip-cols-<?php echo (int) $ip_cols; ?>" role="list" data-ip-swipe>
					<?php foreach ( $ip_models as $ip_mid ) : ?>
						<li class="ip-swipe__item">
							<?php
							get_template_part( 'template-parts/product-card', null, array(
								'id'    => $ip_mid,
								'level' => 'h3',
								'sizes' => '(min-width: 1101px) ' . (int) floor( 1400 / $ip_cols ) . 'px, (min-width: 768px) 45vw, 84vw',
							) );
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
	<?php endif; ?>

	<?php
	/* ── Occasions (tabs) ───────────────────────────────────────────────── */
	$ip_occ = $ip_on( 'occasions' ) ? array_values( array_filter( ip_rows( $ip_f( 'occasions', array() ) ), function ( $r ) {
		return '' !== trim( (string) ( $r['label'] ?? '' ) ) && ( '' !== trim( (string) ( $r['title'] ?? '' ) ) || '' !== trim( (string) ( $r['text'] ?? '' ) ) );
	} ) ) : array();
	if ( $ip_occ ) :
		?>
		<section class="ip-section ip-band--alt ip-occasions" aria-labelledby="ip-occasions-title">
			<div class="ip-shell">
				<?php ip_section_head( array_merge( ip_section_fields( 'occasions', $ip_pid, false ), array( 'id' => 'ip-occasions-title' ) ) ); ?>
				<div class="ip-tabs" data-ip-tabs>
					<div class="ip-tabs__list" role="tablist" aria-labelledby="ip-occasions-title">
						<?php foreach ( $ip_occ as $ip_i => $ip_row ) : ?>
							<button type="button" class="ip-tabs__tab" role="tab" id="ip-occ-tab-<?php echo (int) $ip_i; ?>" aria-controls="ip-occ-panel-<?php echo (int) $ip_i; ?>" aria-selected="<?php echo 0 === $ip_i ? 'true' : 'false'; ?>"<?php echo 0 === $ip_i ? '' : ' tabindex="-1"'; ?>><?php echo esc_html( $ip_row['label'] ); ?></button>
						<?php endforeach; ?>
					</div>
					<?php foreach ( $ip_occ as $ip_i => $ip_row ) : ?>
						<?php
						$ip_oimg  = (int) ( is_array( $ip_row['image'] ?? null ) ? ( $ip_row['image']['ID'] ?? 0 ) : ( $ip_row['image'] ?? 0 ) );
						$ip_olink = ip_content_url( $ip_row['link_url'] ?? '' );
						?>
						<div class="ip-tabs__panel" role="tabpanel" id="ip-occ-panel-<?php echo (int) $ip_i; ?>" aria-labelledby="ip-occ-tab-<?php echo (int) $ip_i; ?>" tabindex="0"<?php echo 0 === $ip_i ? '' : ' hidden'; ?>>
							<article class="ip-occasion ip-card">
								<?php if ( $ip_oimg ) : ?>
									<div class="ip-occasion__media">
										<?php echo ip_img( $ip_oimg, '(min-width: 1101px) 680px, (min-width: 768px) 50vw, 100vw', array( 'alt' => '' ), 'ip-1200' ); // phpcs:ignore WordPress.Security.EscapeOutput -- core image HTML. ?>
									</div>
								<?php endif; ?>
								<div class="ip-occasion__body">
									<?php if ( ! empty( $ip_row['title'] ) ) : ?>
										<h3 class="ip-occasion__title"><?php echo esc_html( $ip_row['title'] ); ?></h3>
									<?php endif; ?>
									<?php echo ip_paras( $ip_row['text'] ?? '', 'ip-occasion__text' ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_paras() escapes. ?>
									<?php if ( $ip_olink && ! empty( $ip_row['link_label'] ) ) : ?>
										<a class="ip-btn ip-btn--ghost" href="<?php echo esc_url( $ip_olink ); ?>"><?php echo esc_html( $ip_row['link_label'] ); ?></a>
									<?php endif; ?>
								</div>
							</article>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php
	/* ── Benefits ───────────────────────────────────────────────────────── */
	$ip_benefits = $ip_on( 'benefits' ) ? array_values( array_filter( ip_rows( $ip_f( 'benefits', array() ) ), function ( $r ) {
		return '' !== trim( (string) ( $r['title'] ?? '' ) );
	} ) ) : array();
	if ( $ip_benefits ) :
		?>
		<section class="ip-section ip-benefits" aria-labelledby="ip-benefits-title">
			<div class="ip-shell">
				<?php ip_section_head( array_merge( ip_section_fields( 'benefits', $ip_pid ), array( 'id' => 'ip-benefits-title' ) ) ); ?>
				<ul class="ip-feature-grid" role="list">
					<?php foreach ( $ip_benefits as $ip_row ) : ?>
						<li class="ip-feature ip-card">
							<span class="ip-tile"><?php echo ip_icon( $ip_row['icon'] ?? 'check' ) ? ip_icon( $ip_row['icon'] ?? 'check' ) : ip_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
							<span class="ip-feature__body">
								<span class="ip-feature__title"><?php echo esc_html( $ip_row['title'] ); ?></span>
								<?php if ( ! empty( $ip_row['text'] ) ) : ?>
									<span class="ip-feature__text"><?php echo esc_html( $ip_row['text'] ); ?></span>
								<?php endif; ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
	<?php endif; ?>

	<?php
	/* ── Quality: facts and customer logos ─────────────────────────────── */
	if ( $ip_on( 'quality' ) ) :
		$ip_q_head  = ip_section_fields( 'quality', $ip_pid );
		$ip_facts   = array_values( array_filter( ip_rows( $ip_f( 'quality_facts', array() ) ), function ( $r ) {
			return '' !== trim( (string) ( $r['value'] ?? '' ) );
		} ) );
		$ip_logos   = ip_ids( $ip_f( 'quality_logos', array() ) );
		$ip_logos   = array_values( array_filter( $ip_logos, 'wp_attachment_is_image' ) );
		if ( $ip_q_head['title'] || $ip_q_head['text'] || $ip_facts || $ip_logos ) :
			?>
			<section class="ip-section ip-band--alt ip-quality" aria-labelledby="ip-quality-title">
				<div class="ip-shell ip-quality__grid">
					<div class="ip-quality__text">
						<?php ip_section_head( array_merge( $ip_q_head, array( 'id' => 'ip-quality-title' ) ) ); ?>
						<?php if ( $ip_facts ) : ?>
							<dl class="ip-facts">
								<?php foreach ( $ip_facts as $ip_row ) : ?>
									<div class="ip-facts__item">
										<dt class="ip-facts__label"><?php echo esc_html( $ip_row['label'] ?? '' ); ?></dt>
										<dd class="ip-facts__value"><?php echo esc_html( $ip_row['value'] ); ?></dd>
									</div>
								<?php endforeach; ?>
							</dl>
						<?php endif; ?>
					</div>
					<?php if ( $ip_logos ) : ?>
						<ul class="ip-logos" role="list" aria-label="<?php esc_attr_e( 'Kunden (Auswahl)', 'infoplus' ); ?>">
							<?php foreach ( $ip_logos as $ip_logo ) : ?>
								<?php
								$ip_alt = trim( (string) get_post_meta( $ip_logo, '_wp_attachment_image_alt', true ) );
								if ( '' === $ip_alt ) {
									$ip_alt = ucfirst( get_the_title( $ip_logo ) ); // Old logos have no alt text; the file title is the brand ("allianz").
								}
								?>
								<li class="ip-logos__item"><?php echo ip_img( $ip_logo, '160px', array( 'alt' => $ip_alt, 'class' => 'ip-logos__img' ), 'ip-480' ); // phpcs:ignore WordPress.Security.EscapeOutput -- core image HTML. ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>
	<?php endif; ?>

	<?php
	/* ── Service promise ───────────────────────────────────────────────── */
	if ( $ip_on( 'service' ) ) :
		$ip_s_head  = ip_section_fields( 'service', $ip_pid );
		$ip_points  = array_values( array_filter( ip_rows( $ip_f( 'service_points', array() ) ), function ( $r ) {
			return '' !== trim( (string) ( $r['title'] ?? '' ) );
		} ) );
		$ip_cards   = array_values( array_filter( ip_rows( $ip_f( 'service_cards', array() ) ), function ( $r ) {
			return '' !== trim( (string) ( $r['title'] ?? '' ) );
		} ) );
		if ( $ip_s_head['title'] || $ip_points || $ip_cards ) :
			?>
			<section class="ip-section ip-service" aria-labelledby="ip-service-title">
				<div class="ip-shell">
					<div class="ip-service__top">
						<?php ip_section_head( array_merge( $ip_s_head, array( 'id' => 'ip-service-title' ) ) ); ?>
						<?php if ( $ip_points ) : ?>
							<ul class="ip-points" role="list">
								<?php foreach ( $ip_points as $ip_row ) : ?>
									<li class="ip-points__item">
										<span class="ip-tile"><?php echo ip_icon( $ip_row['icon'] ?? 'check' ) ? ip_icon( $ip_row['icon'] ?? 'check' ) : ip_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
										<span class="ip-points__title"><?php echo esc_html( $ip_row['title'] ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
					<?php if ( $ip_cards ) : ?>
						<ul class="ip-swipe ip-service__cards ip-cols-<?php echo (int) min( 4, count( $ip_cards ) ); ?>" role="list" data-ip-swipe>
							<?php foreach ( $ip_cards as $ip_row ) : ?>
								<?php $ip_cimg = (int) ( is_array( $ip_row['image'] ?? null ) ? ( $ip_row['image']['ID'] ?? 0 ) : ( $ip_row['image'] ?? 0 ) ); ?>
								<li class="ip-swipe__item">
									<article class="ip-photo-card ip-card">
										<?php if ( $ip_cimg ) : ?>
											<div class="ip-photo-card__media"><?php echo ip_img( $ip_cimg, '(min-width: 1101px) 440px, (min-width: 768px) 33vw, 84vw', array( 'alt' => '' ), 'ip-800' ); // phpcs:ignore WordPress.Security.EscapeOutput -- core image HTML. ?></div>
										<?php endif; ?>
										<div class="ip-photo-card__body">
											<h3 class="ip-photo-card__title"><?php echo esc_html( $ip_row['title'] ); ?></h3>
											<?php echo ip_paras( $ip_row['text'] ?? '', 'ip-photo-card__text' ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_paras() escapes. ?>
										</div>
									</article>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>
	<?php endif; ?>

	<?php
	/* ── Sortiment texts ───────────────────────────────────────────────── */
	$ip_seo = $ip_on( 'seo' ) ? array_values( array_filter( ip_rows( $ip_f( 'seo_blocks', array() ) ), function ( $r ) {
		return '' !== trim( wp_strip_all_tags( (string) ( $r['text'] ?? '' ) ) );
	} ) ) : array();
	if ( $ip_seo ) :
		?>
		<section class="ip-section ip-band--alt ip-seo" aria-label="<?php esc_attr_e( 'Über unser Sortiment', 'infoplus' ); ?>">
			<div class="ip-shell ip-seo__grid">
				<?php foreach ( $ip_seo as $ip_row ) : ?>
					<div class="ip-seo__block">
						<?php if ( ! empty( $ip_row['title'] ) ) : ?>
							<h2 class="ip-seo__title"><?php echo esc_html( $ip_row['title'] ); ?></h2>
						<?php endif; ?>
						<div class="ip-prose ip-seo__text"><?php echo ip_rich( $ip_row['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_rich() runs wp_kses. ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	/* ── Contact band ──────────────────────────────────────────────────── */
	if ( ip_flag( 'contact_show', $ip_pid, true ) ) {
		get_template_part( 'template-parts/contact-band', null, array(
			'title' => (string) $ip_f( 'contact_title' ),
			'text'  => (string) $ip_f( 'contact_text' ),
		) );
	}
	?>
</div>
<?php
get_footer();
