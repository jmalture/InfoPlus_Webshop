<?php
/**
 * News article (post type "news", registered by SCF on staging). Port of the
 * Elementor single template 13266 in the DESIGN.md style: date and category
 * as kicker, title, excerpt as lead, featured image, the article text
 * (block editor content), a way back to the customer dashboard, and
 * "Weitere News" as three cards (loop item 13365, template-parts/news-card.php).
 *
 * The old template linked "Back to dashboard" to /account/ because news are
 * announced in the customer dashboard; kept, in German.
 */

defined( 'ABSPATH' ) || exit;

get_header();
while ( have_posts() ) :
	the_post();
	$ip_id      = get_the_ID();
	$ip_terms   = get_the_terms( $ip_id, 'category' );
	$ip_term    = ( $ip_terms && ! is_wp_error( $ip_terms ) ) ? $ip_terms[0] : null;
	$ip_thumb   = (int) get_post_thumbnail_id( $ip_id );
	$ip_excerpt = has_excerpt() ? get_the_excerpt() : '';
	$ip_related = ip_news_related( $ip_id, 3 );
	$ip_back    = (string) ip_setting( 'news_back_label' );
	$ip_back_to = function_exists( 'ip_account_url' ) ? ip_account_url() : home_url( '/account/' );
	?>
	<div class="ip-news">
		<div class="ip-shell">
			<?php ip_breadcrumb( array( array( __( 'Home', 'infoplus' ), home_url( '/' ) ), array( __( 'News', 'infoplus' ), '' ), array( get_the_title(), '' ) ) ); ?>
			<article class="ip-article ip-news__article">
				<header class="ip-article__head ip-news__head">
					<p class="ip-kicker ip-news__meta">
						<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'j. F Y' ) ); ?></time>
						<?php if ( $ip_term && 'uncategorized' !== $ip_term->slug ) : ?>
							<span class="ip-pill"><?php echo esc_html( $ip_term->name ); ?></span>
						<?php endif; ?>
					</p>
					<h1 class="ip-article__title"><?php the_title(); ?></h1>
					<?php if ( $ip_excerpt ) : ?>
						<p class="ip-sub ip-news__lead"><?php echo esc_html( $ip_excerpt ); ?></p>
					<?php endif; ?>
				</header>
				<?php if ( $ip_thumb ) : ?>
					<figure class="ip-news__media">
						<?php echo ip_img( $ip_thumb, '(min-width: 1101px) 960px, calc(100vw - 2 * var(--layout-margin))', array( 'alt' => '', 'loading' => 'eager', 'fetchpriority' => 'high' ), 'ip-1600' ); // phpcs:ignore WordPress.Security.EscapeOutput -- core image HTML. ?>
					</figure>
				<?php endif; ?>
				<div class="ip-prose ip-article__body">
					<?php the_content(); ?>
				</div>
				<?php if ( $ip_back && is_user_logged_in() ) : ?>
					<p class="ip-news__back"><a class="ip-btn ip-btn--ghost" href="<?php echo esc_url( $ip_back_to ); ?>"><?php echo ip_icon( 'arrow-left' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php echo esc_html( $ip_back ); ?></a></p>
				<?php endif; ?>
			</article>
		</div>

		<?php if ( $ip_related ) : ?>
			<?php ip_prime_posts( $ip_related ); ?>
			<section class="ip-section ip-news__related" aria-labelledby="ip-news-related-title">
				<div class="ip-shell">
					<?php ip_section_head( array( 'title' => (string) ip_setting( 'news_related_title' ), 'id' => 'ip-news-related-title' ) ); ?>
					<ul class="ip-swipe ip-cols-3" role="list" data-ip-swipe>
						<?php foreach ( $ip_related as $ip_rid ) : ?>
							<li class="ip-swipe__item"><?php get_template_part( 'template-parts/news-card', null, array( 'id' => $ip_rid ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</section>
		<?php endif; ?>
	</div>
	<?php
endwhile;
get_footer();
