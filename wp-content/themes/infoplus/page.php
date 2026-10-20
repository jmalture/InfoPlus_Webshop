<?php
/**
 * Plain pages (privacy policy, terms): title and the text the client writes
 * in the classic editor. Field-driven pages use templates/*.php instead.
 */

defined( 'ABSPATH' ) || exit;

get_header();
while ( have_posts() ) :
	the_post();
	?>
	<div class="ip-shell ip-page">
		<?php ip_breadcrumb( array( array( __( 'Home', 'infoplus' ), home_url( '/' ) ), array( get_the_title(), get_permalink() ) ) ); ?>
		<h1><?php the_title(); ?></h1>
		<div class="ip-prose"><?php the_content(); ?></div>
	</div>
	<?php
endwhile;
get_footer();
