<?php
/**
 * Document head and site header. The header markup lives in
 * template-parts/site-header.php; footer.php closes <main>.
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="ip-skip" href="#main"><?php esc_html_e( 'Zum Inhalt springen', 'infoplus' ); ?></a>

<?php get_template_part( 'template-parts/site-header' ); ?>

<main id="main" tabindex="-1">
