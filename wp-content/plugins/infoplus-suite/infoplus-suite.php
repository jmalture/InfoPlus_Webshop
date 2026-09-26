<?php
/**
 * Plugin Name: Infoplus Suite
 * Description: Modular WooCommerce enhancements (Single Product Addons, Product Variation Fields, etc).
 * Version:     0.9.112
 * Author:      JM
 * License:     GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

// Require the Plugin class first
require_once __DIR__ . '/src/Plugin.php'; 

/** Try Composer first */
$composer = __DIR__ . '/vendor/autoload.php';
if (file_exists($composer)) {
    require $composer;
} else {
    // Tiny PSR-4 fallback: Infoplus\Suite\ => src/
    spl_autoload_register(function ($class) {
        $prefix = 'Infoplus\\Suite\\';
        $base   = __DIR__ . '/src/';
        $len    = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $rel = substr($class, $len);
        $file = $base . str_replace('\\', '/', $rel) . '.php';
        if (file_exists($file)) require $file;
    });
}

// Boot the plugin on plugins_loaded
add_action('plugins_loaded', function () {
    try {
        $plugin = new \Infoplus\Suite\Plugin(__FILE__);
        $plugin->boot();
    } catch (\Throwable $e) {
        // Log the error for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Infoplus Suite Error: ' . $e->getMessage());
            error_log($e->getTraceAsString());
        }
        
        // Show admin notice
        add_action('admin_notices', function () use ($e) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Infoplus Suite Error:</strong> ' . esc_html($e->getMessage());
            echo '</p></div>';
        });
    }
});