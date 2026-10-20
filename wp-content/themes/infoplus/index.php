<?php
/**
 * Required fallback. Every public view should have its own template; this
 * only renders if a new view appears before it gets one.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="ip-shell ip-page">
	<?php while ( have_posts() ) : the_post(); ?>
		<article><h1><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1></article>
	<?php endwhile; ?>
</div>
<?php
get_footer();
