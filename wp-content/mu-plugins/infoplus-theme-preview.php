<?php
/**
 * Plugin Name: Infoplus theme preview (admin only)
 * Description: Lets administrators see the new "infoplus" theme on the live site while every other visitor keeps the active theme. Toggle with ?infoplus_preview=on / off or the admin bar. Remove this file after the theme switch.
 * Version:     1.0.0
 * Author:      Alture
 *
 * Install: copy to wp-content/mu-plugins/. Nothing is changed in the database;
 * deleting the file ends the preview for everyone.
 */

defined('ABSPATH') || exit;

const INFOPLUS_PREVIEW_THEME  = 'infoplus';
const INFOPLUS_PREVIEW_COOKIE = 'infoplus_theme_preview';

/**
 * True when the current request should render the preview theme.
 * Requires the cookie AND an administrator, so a copied cookie does nothing for a guest.
 */
function infoplus_preview_active(): bool
{
    static $active = null;
    if ($active !== null) {
        return $active;
    }
    $active = false;

    if (empty($_COOKIE[INFOPLUS_PREVIEW_COOKIE]) || !function_exists('wp_get_current_user')) {
        return $active;
    }
    if (!current_user_can('manage_options')) {
        return $active;
    }
    // Only if the theme is actually installed, otherwise WordPress would fall back oddly.
    $active = is_dir(get_theme_root() . '/' . INFOPLUS_PREVIEW_THEME);
    return $active;
}

/** Swap template and stylesheet for this request only. */
foreach (['pre_option_template', 'pre_option_stylesheet'] as $infoplus_hook) {
    add_filter($infoplus_hook, static function ($value) {
        return infoplus_preview_active() ? INFOPLUS_PREVIEW_THEME : $value;
    });
}

/** ?infoplus_preview=on|off sets or clears the cookie (administrators only). */
add_action('init', static function () {
    if (!isset($_GET['infoplus_preview']) || !current_user_can('manage_options')) {
        return;
    }
    $on = $_GET['infoplus_preview'] === 'on';
    setcookie(INFOPLUS_PREVIEW_COOKIE, $on ? '1' : '', [
        'expires'  => $on ? time() + DAY_IN_SECONDS * 30 : time() - HOUR_IN_SECONDS,
        'path'     => COOKIEPATH ?: '/',
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    wp_safe_redirect(remove_query_arg('infoplus_preview'));
    exit;
});

/** Never cache a preview response (LiteSpeed and others honour DONOTCACHEPAGE). */
add_action('template_redirect', static function () {
    if (infoplus_preview_active()) {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        do_action('litespeed_control_set_nocache', 'infoplus theme preview');
        nocache_headers();
    }
}, 0);

/** Admin bar switch so the team can flip between old and new on any page. */
add_action('admin_bar_menu', static function (WP_Admin_Bar $bar) {
    if (!current_user_can('manage_options') || is_admin()) {
        return;
    }
    $on = infoplus_preview_active();
    $bar->add_node([
        'id'    => 'infoplus-preview',
        'title' => $on ? 'Neues Theme: AN' : 'Neues Theme: AUS',
        'href'  => add_query_arg('infoplus_preview', $on ? 'off' : 'on'),
        'meta'  => ['title' => 'Infoplus PHP-Theme Vorschau umschalten'],
    ]);
}, 100);
