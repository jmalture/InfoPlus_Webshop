<?php
/**
 * "Unternehmen" page (/unternehmen/, page 2662), picked by slug.
 *
 * Content ported from the old Elementor HTML widget
 * (legacy/html-widgets/2662-e43551a.html); texts and the photo are fields
 * of the group "Unternehmen" (acf-json/group_ip_unternehmen.json) with the
 * old text as defaults. Layout since theme 0.5.4, in the SDX manner of the
 * rest of the site (docs/theme/DESIGN.md):
 *
 * 1. Hero: the team photo as a wide picture with a soft dark fade at the
 *    bottom; kicker, H1 and subline sit on it bottom left, the
 *    "Kernaussagen" float in as white pills bottom right, a magenta tile
 *    with the award icon bobs in the corner. Below 768 px the text and the
 *    pills sit under the picture.
 * 2. Numbers that count up when they scroll into view (field "stats").
 * 3. "Unsere Geschichte": heading left, text right.
 * 4. Milestones as an interactive timeline: years with a short title on a
 *    line that draws itself; hover, focus or tap on a year shows its text
 *    in the card below (WAI-ARIA tabs). Without JS every text is shown.
 * 5. Service: lead text and the services as icon cards.
 * 6. Closing call to action.
 *
 * Motion (assets/js/content.js, content.css): sections rise in once
 * ([data-ip-rv]), numbers count, the timeline draws. All of it is off under
 * prefers-reduced-motion; the finished state is what the server renders.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$ip_pid = (int) get_queried_object_id();
$ip_f   = function ( $name, $default = '' ) use ( $ip_pid ) {
	return ip_field( $name, $ip_pid, $default );
};
$ip_rows_of = function ( $name, $key ) use ( $ip_f ) {
	return array_values( array_filter( ip_rows( $ip_f( $name, array() ) ), function ( $r ) use ( $key ) {
		return '' !== trim( (string) ( $r[ $key ] ?? '' ) );
	} ) );
};

$ip_title      = (string) $ip_f( 'title' );
$ip_title      = $ip_title ? $ip_title : get_the_title();
$ip_highlights = $ip_rows_of( 'highlights', 'text' );
$ip_team       = (int) $ip_f( 'team_image', 0 );
$ip_stats      = $ip_rows_of( 'stats', 'value' );
$ip_milestones = $ip_rows_of( 'milestones', 'text' );
$ip_services   = $ip_rows_of( 'service_items', 'title' );
$ip_cta_label  = (string) $ip_f( 'cta_label' );
$ip_cta_url    = ip_content_url( $ip_f( 'cta_url' ) );
$ip_phone      = (string) ip_setting( 'phone' );
$ip_tagline    = (string) $ip_f( 'tagline' );

/** Icon name from a select value, with a fallback for empty or unknown values. */
$ip_icon_of = function ( $name, $fallback ) {
	$name = (string) $name;
	return ( '' !== $name && '' !== ip_icon( $name ) ) ? $name : $fallback;
};
?>
<div class="ip-content ip-about">
	<div class="ip-shell">
		<?php ip_breadcrumb( array( array( __( 'Home', 'infoplus' ), home_url( '/' ) ), array( $ip_title, '' ) ) ); ?>

		<?php /* ── 1. Hero ─────────────────────────────────────────────────── */ ?>
		<section class="ip-ahero<?php echo $ip_team ? ' has-photo' : ''; ?>" aria-labelledby="ip-ahero-title">
			<?php if ( $ip_team ) : ?>
				<div class="ip-ahero__media">
					<?php
					$ip_alt = trim( (string) get_post_meta( $ip_team, '_wp_attachment_image_alt', true ) );
					echo ip_img( $ip_team, '(min-width: 1496px) 1400px, calc(100vw - 2 * var(--layout-margin))', array( 'alt' => $ip_alt ? $ip_alt : 'Infoplus Team', 'loading' => 'eager', 'fetchpriority' => 'high', 'class' => 'ip-ahero__img' ), 'ip-1600' ); // phpcs:ignore WordPress.Security.EscapeOutput -- core image HTML.
					?>
					<span class="ip-ahero__fold" aria-hidden="true"><?php echo ip_icon( 'award' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
				</div>
			<?php endif; ?>
			<div class="ip-ahero__text">
				<?php if ( $ip_f( 'kicker' ) ) : ?>
					<p class="ip-kicker ip-ahero__kicker"><?php echo esc_html( $ip_f( 'kicker' ) ); ?></p>
				<?php endif; ?>
				<h1 class="ip-ahero__title" id="ip-ahero-title"><?php echo esc_html( $ip_title ); ?></h1>
				<?php if ( $ip_f( 'subtitle' ) ) : ?>
					<p class="ip-ahero__sub"><?php echo esc_html( $ip_f( 'subtitle' ) ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $ip_highlights ) : ?>
				<div class="ip-ahero__pills">
					<ul class="ip-ahero__list" role="list">
						<?php foreach ( $ip_highlights as $ip_i => $ip_row ) : ?>
							<li class="ip-ahero__pill" style="--ip-i: <?php echo (int) $ip_i; ?>"><span class="ip-ahero__check"><?php echo ip_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span><?php echo esc_html( $ip_row['text'] ); ?></li>
						<?php endforeach; ?>
					</ul>
					<?php if ( $ip_tagline ) : ?>
						<p class="ip-ahero__tagline" style="--ip-i: <?php echo (int) count( $ip_highlights ); ?>"><?php echo esc_html( $ip_tagline ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</section>

		<?php /* ── 2. Numbers ──────────────────────────────────────────────── */ ?>
		<?php if ( $ip_stats ) : ?>
			<ul class="ip-astats" role="list" data-ip-rv>
				<?php foreach ( $ip_stats as $ip_i => $ip_row ) : ?>
					<li class="ip-astats__item" style="--dl: <?php echo esc_attr( $ip_i * 0.08 ); ?>s">
						<span class="ip-astats__value" data-ip-count><?php echo esc_html( $ip_row['value'] ); ?></span>
						<span class="ip-astats__label"><?php echo esc_html( $ip_row['label'] ?? '' ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php /* ── 3. Story ────────────────────────────────────────────────── */ ?>
		<?php if ( $ip_f( 'story_title' ) || $ip_f( 'story_text' ) ) : ?>
			<section class="ip-split ip-astory" aria-labelledby="ip-story-title" data-ip-rv>
				<div class="ip-astory__head">
					<p class="ip-kicker"><?php esc_html_e( 'Geschichte', 'infoplus' ); ?></p>
					<h2 class="ip-split__title" id="ip-story-title"><?php echo esc_html( $ip_f( 'story_title' ) ); ?></h2>
				</div>
				<div class="ip-prose ip-split__body ip-astory__body"><?php echo ip_rich( $ip_f( 'story_text' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_rich() runs wp_kses. ?></div>
			</section>
		<?php endif; ?>
	</div>

	<?php /* ── 4. Milestones ───────────────────────────────────────────────── */ ?>
	<?php if ( $ip_milestones ) : ?>
		<section class="ip-section ip-band--alt ip-miles" aria-labelledby="ip-milestones-title">
			<div class="ip-shell">
				<div class="ip-miles__head" data-ip-rv>
					<p class="ip-kicker"><?php esc_html_e( 'Zeitleiste', 'infoplus' ); ?></p>
					<h2 class="ip-miles__title" id="ip-milestones-title"><?php echo esc_html( $ip_f( 'milestones_title' ) ); ?></h2>
					<?php if ( $ip_f( 'milestones_text' ) ) : ?>
						<p class="ip-sub"><?php echo esc_html( $ip_f( 'milestones_text' ) ); ?></p>
					<?php endif; ?>
				</div>
				<div class="ip-miles__box" data-ip-miles style="--ip-n: <?php echo (int) count( $ip_milestones ); ?>">
					<div class="ip-miles__track" role="tablist" aria-label="<?php echo esc_attr( $ip_f( 'milestones_title' ) ); ?>">
						<?php foreach ( $ip_milestones as $ip_i => $ip_row ) : ?>
							<button type="button" class="ip-miles__tab<?php echo 0 === $ip_i ? ' is-on' : ''; ?>" role="tab" id="ip-mile-tab-<?php echo (int) $ip_i; ?>" aria-controls="ip-mile-<?php echo (int) $ip_i; ?>" aria-selected="<?php echo 0 === $ip_i ? 'true' : 'false'; ?>" tabindex="<?php echo 0 === $ip_i ? '0' : '-1'; ?>" style="--ip-i: <?php echo (int) $ip_i; ?>">
								<span class="ip-miles__dot" aria-hidden="true"></span>
								<span class="ip-miles__year"><?php echo esc_html( $ip_row['year'] ?? '' ); ?></span>
								<?php if ( ! empty( $ip_row['title'] ) ) : ?>
									<span class="ip-miles__short"><?php echo esc_html( $ip_row['title'] ); ?></span>
								<?php endif; ?>
							</button>
						<?php endforeach; ?>
					</div>
					<div class="ip-miles__panels">
						<?php foreach ( $ip_milestones as $ip_i => $ip_row ) : ?>
							<div class="ip-miles__panel" role="tabpanel" id="ip-mile-<?php echo (int) $ip_i; ?>" aria-labelledby="ip-mile-tab-<?php echo (int) $ip_i; ?>"<?php echo 0 === $ip_i ? '' : ' hidden'; ?>>
								<span class="ip-tile ip-miles__icon"><?php echo ip_icon( $ip_icon_of( $ip_row['icon'] ?? '', 'award' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
								<div class="ip-miles__copy">
									<p class="ip-miles__panel-year"><?php echo esc_html( $ip_row['year'] ?? '' ); ?></p>
									<?php if ( ! empty( $ip_row['title'] ) ) : ?>
										<h3 class="ip-miles__panel-title"><?php echo esc_html( $ip_row['title'] ); ?></h3>
									<?php endif; ?>
									<?php echo ip_paras( $ip_row['text'], 'ip-miles__text' ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_paras() escapes. ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<div class="ip-shell">
		<?php /* ── 5. Service ──────────────────────────────────────────────── */ ?>
		<?php if ( $ip_f( 'service_title' ) || $ip_f( 'service_text' ) || $ip_services ) : ?>
			<section class="ip-aservice" aria-labelledby="ip-about-service-title">
				<div class="ip-aservice__head" data-ip-rv>
					<p class="ip-kicker"><?php esc_html_e( 'Full-Service', 'infoplus' ); ?></p>
					<h2 class="ip-aservice__title" id="ip-about-service-title"><?php echo esc_html( $ip_f( 'service_title' ) ); ?></h2>
					<?php echo ip_paras( $ip_f( 'service_text' ), 'ip-sub' ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_paras() escapes. ?>
				</div>
				<?php if ( $ip_services ) : ?>
					<ul class="ip-aservice__grid" role="list">
						<?php foreach ( $ip_services as $ip_i => $ip_row ) : ?>
							<li class="ip-aservice__item ip-card" data-ip-rv style="--dl: <?php echo esc_attr( $ip_i * 0.07 ); ?>s">
								<span class="ip-tile"><?php echo ip_icon( $ip_icon_of( $ip_row['icon'] ?? '', 'check' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
								<h3 class="ip-aservice__item-title"><?php echo esc_html( $ip_row['title'] ); ?></h3>
								<?php echo ip_paras( $ip_row['text'] ?? '', 'ip-aservice__item-text' ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_paras() escapes. ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php elseif ( $ip_f( 'service_quote' ) ) : ?>
					<blockquote class="ip-quote"><?php echo ip_paras( $ip_f( 'service_quote' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_paras() escapes. ?></blockquote>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php /* ── 6. Call to action ───────────────────────────────────────── */ ?>
		<?php if ( $ip_f( 'cta_title' ) || ( $ip_cta_label && $ip_cta_url ) ) : ?>
			<section class="ip-cta ip-card ip-acta" aria-labelledby="ip-about-cta-title" data-ip-rv>
				<div class="ip-cta__text">
					<h2 class="ip-cta__title" id="ip-about-cta-title"><?php echo esc_html( $ip_f( 'cta_title' ) ); ?></h2>
					<?php if ( $ip_f( 'cta_text' ) ) : ?>
						<p class="ip-sub"><?php echo esc_html( $ip_f( 'cta_text' ) ); ?></p>
					<?php endif; ?>
				</div>
				<div class="ip-cta__actions">
					<?php if ( $ip_cta_label && $ip_cta_url ) : ?>
						<a class="ip-btn ip-btn--lg" href="<?php echo esc_url( $ip_cta_url ); ?>"><?php echo esc_html( $ip_cta_label ); ?></a>
					<?php endif; ?>
					<?php if ( $ip_phone ) : ?>
						<a class="ip-btn ip-btn--ghost ip-btn--lg" href="<?php echo esc_url( ip_tel( $ip_phone ), array( 'tel' ) ); ?>"><?php echo ip_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php echo esc_html( $ip_phone ); ?></a>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>
	</div>
</div>
<?php
get_footer();
