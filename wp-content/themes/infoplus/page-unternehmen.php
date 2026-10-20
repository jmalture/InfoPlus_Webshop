<?php
/**
 * "Unternehmen" page (/unternehmen/, page 2662), picked by slug.
 *
 * The old page was one Elementor HTML widget holding a complete standalone
 * HTML document with its own CSS (_legacy/html-widgets/2662-e43551a.html).
 * Its content and structure are ported here as sections: head, highlights,
 * team photo, history, milestones (timeline), service and a closing call to
 * action. Texts and the photo are fields of the group "Unternehmen"
 * (acf-json/group_ip_unternehmen.json) with the old text as defaults.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$ip_pid = (int) get_queried_object_id();
$ip_f   = function ( $name, $default = '' ) use ( $ip_pid ) {
	return ip_field( $name, $ip_pid, $default );
};

$ip_title      = (string) $ip_f( 'title' );
$ip_highlights = array_values( array_filter( ip_rows( $ip_f( 'highlights', array() ) ), function ( $r ) {
	return '' !== trim( (string) ( $r['text'] ?? '' ) );
} ) );
$ip_team       = (int) $ip_f( 'team_image', 0 );
$ip_milestones = array_values( array_filter( ip_rows( $ip_f( 'milestones', array() ) ), function ( $r ) {
	return '' !== trim( (string) ( $r['text'] ?? '' ) );
} ) );
$ip_cta_label  = (string) $ip_f( 'cta_label' );
$ip_cta_url    = ip_content_url( $ip_f( 'cta_url' ) );
$ip_phone      = (string) ip_setting( 'phone' );
?>
<div class="ip-content ip-about">
	<div class="ip-shell">
		<?php ip_breadcrumb( array( array( __( 'Home', 'infoplus' ), home_url( '/' ) ), array( $ip_title ? $ip_title : get_the_title(), '' ) ) ); ?>

		<div class="ip-page-head ip-about__head">
			<?php
			ip_section_head( array(
				'kicker' => (string) $ip_f( 'kicker' ),
				'title'  => $ip_title ? $ip_title : get_the_title(),
				'text'   => (string) $ip_f( 'subtitle' ),
				'tag'    => 'h1',
			) );
			?>
			<?php if ( $ip_highlights ) : ?>
				<div class="ip-about__highlights">
					<ul class="ip-checks" role="list">
						<?php foreach ( $ip_highlights as $ip_row ) : ?>
							<li class="ip-checks__item"><span class="ip-tile"><?php echo ip_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span><span><?php echo esc_html( $ip_row['text'] ); ?></span></li>
						<?php endforeach; ?>
					</ul>
					<?php if ( $ip_f( 'tagline' ) ) : ?>
						<p class="ip-about__tagline"><?php echo esc_html( $ip_f( 'tagline' ) ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $ip_team ) : ?>
			<figure class="ip-about__team">
				<?php
				$ip_alt = trim( (string) get_post_meta( $ip_team, '_wp_attachment_image_alt', true ) );
				echo ip_img( $ip_team, '(min-width: 1496px) 1400px, calc(100vw - 2 * var(--layout-margin))', array( 'alt' => $ip_alt ? $ip_alt : 'Infoplus Team', 'loading' => 'eager', 'fetchpriority' => 'high' ), 'ip-1600' ); // phpcs:ignore WordPress.Security.EscapeOutput -- core image HTML.
				?>
			</figure>
		<?php endif; ?>

		<?php if ( $ip_f( 'story_title' ) || $ip_f( 'story_text' ) ) : ?>
			<section class="ip-split" aria-labelledby="ip-story-title">
				<h2 class="ip-split__title" id="ip-story-title"><?php echo esc_html( $ip_f( 'story_title' ) ); ?></h2>
				<div class="ip-prose ip-split__body"><?php echo ip_rich( $ip_f( 'story_text' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_rich() runs wp_kses. ?></div>
			</section>
		<?php endif; ?>

		<?php if ( $ip_milestones ) : ?>
			<section class="ip-split" aria-labelledby="ip-milestones-title">
				<h2 class="ip-split__title" id="ip-milestones-title"><?php echo esc_html( $ip_f( 'milestones_title' ) ); ?></h2>
				<ol class="ip-timeline ip-split__body" role="list">
					<?php foreach ( $ip_milestones as $ip_row ) : ?>
						<li class="ip-timeline__item">
							<span class="ip-timeline__year"><?php echo esc_html( $ip_row['year'] ?? '' ); ?></span>
							<div class="ip-timeline__text"><?php echo ip_paras( $ip_row['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_paras() escapes. ?></div>
						</li>
					<?php endforeach; ?>
				</ol>
			</section>
		<?php endif; ?>

		<?php if ( $ip_f( 'service_title' ) || $ip_f( 'service_text' ) || $ip_f( 'service_quote' ) ) : ?>
			<section class="ip-split" aria-labelledby="ip-about-service-title">
				<h2 class="ip-split__title" id="ip-about-service-title"><?php echo esc_html( $ip_f( 'service_title' ) ); ?></h2>
				<div class="ip-prose ip-split__body">
					<?php echo ip_paras( $ip_f( 'service_text' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_paras() escapes. ?>
					<?php if ( $ip_f( 'service_quote' ) ) : ?>
						<blockquote class="ip-quote"><?php echo ip_paras( $ip_f( 'service_quote' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_paras() escapes. ?></blockquote>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $ip_f( 'cta_title' ) || ( $ip_cta_label && $ip_cta_url ) ) : ?>
			<section class="ip-cta ip-card" aria-labelledby="ip-about-cta-title">
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
