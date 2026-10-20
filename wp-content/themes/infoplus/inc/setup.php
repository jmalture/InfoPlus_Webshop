<?php
/**
 * Theme setup: supports, menus, head cleanup, editor choice, custom-field local JSON.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', function () {
	load_theme_textdomain( 'infoplus', IP_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	remove_theme_support( 'core-block-patterns' );

	// Menu locations. The existing WP menus get assigned to these once the
	// theme goes live (Mobile Menu 247, Footer_Informationen 20, Footer_Rechtliches 21).
	register_nav_menus( array(
		'primary'      => __( 'Hauptmenü (Desktop)', 'infoplus' ),
		'mobile'       => __( 'Mobilmenü', 'infoplus' ),
		'footer-info'  => __( 'Footer: Informationen', 'infoplus' ),
		'footer-legal' => __( 'Footer: Rechtliches', 'infoplus' ),
	) );
} );

/** Head cleanup: nothing here is used by the front end. */
add_action( 'init', function () {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
} );

/*
 * Block CSS only where blocks render. Cart and checkout are WooCommerce blocks,
 * and the legal pages are written in the block editor, so the block library
 * stays on any singular view whose content has blocks.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_singular() && has_blocks( get_queried_object_id() ) ) {
		return;
	}
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'global-styles' );
	wp_dequeue_style( 'classic-theme-styles' );
}, 100 );

/*
 * Editor choice: pages built from theme templates (templates/*.php) are edited
 * through their fields only, so the block editor is switched off there. All
 * other pages (legal texts, cart, checkout) keep the block editor, because
 * their content is blocks.
 */
add_filter( 'use_block_editor_for_post', function ( $use, $post ) {
	if ( $post && 'page' === $post->post_type ) {
		$template = get_page_template_slug( $post );
		if ( $template && str_starts_with( $template, 'templates/' ) ) {
			return false;
		}
	}
	return $use;
}, 10, 2 );

/*
 * ACF / Secure Custom Fields local JSON: field groups are versioned in the
 * theme's /acf-json and deploy with the code.
 */
add_filter( 'acf/settings/save_json', function () {
	return IP_DIR . '/acf-json';
} );
add_filter( 'acf/settings/load_json', function ( $paths ) {
	$paths[] = IP_DIR . '/acf-json';
	return array_unique( $paths );
} );
