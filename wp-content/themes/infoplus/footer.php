<?php
/**
 * Closes the main content area opened in header.php, then renders the site
 * footer and the floating Kontakt panel (both server-side, no JS-injected
 * markup) before wp_footer().
 */

defined( 'ABSPATH' ) || exit;
?>
</main>
<?php
get_template_part( 'template-parts/site-footer' );
get_template_part( 'template-parts/contact-panel' );
wp_footer();
?>
</body>
</html>
