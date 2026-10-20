<?php
/**
 * Plain pages: legal and info texts (Impressum, AGB, Datenschutz,
 * Verhaltenskodex, Versand & Zahlung, Bestell-Hinweise ...) in a readable
 * prose layout. Pages with a design of their own have their own file
 * (front-page.php, page-{slug}.php).
 *
 * The text comes from ip_page_content() (inc/content.php): pages still built
 * with Elementor are rendered from their stored Elementor data without
 * Elementor, every other page through the_content(). The H1 is the page's
 * opening Elementor heading when it has one (ip_page_title()).
 */

defined( 'ABSPATH' ) || exit;

get_header();
while ( have_posts() ) :
	the_post();
	$ip_title = ip_page_title();
	?>
	<div class="ip-content ip-page">
		<div class="ip-shell">
			<?php ip_breadcrumb( array( array( __( 'Home', 'infoplus' ), home_url( '/' ) ), array( get_the_title(), '' ) ) ); ?>
			<article class="ip-article">
				<header class="ip-article__head">
					<h1 class="ip-article__title"><?php echo esc_html( $ip_title ); ?></h1>
				</header>
				<div class="ip-prose ip-article__body">
					<?php ip_page_content(); ?>
				</div>
			</article>
		</div>
	</div>
	<?php
endwhile;
get_footer();
