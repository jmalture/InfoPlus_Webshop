<?php
/**
 * Infoplus theme bootstrap. Constants and the require list only; each concern
 * lives in its own file under /inc.
 *
 * Division of labour (see the "Infoplus rebuild map" doc):
 * - WordPress + WooCommerce: data and the shop flow.
 * - Infoplus Suite plugin: shop logic and the markup of its components.
 * - This theme: layout, page templates, styling, asset loading.
 */

defined( 'ABSPATH' ) || exit;

define( 'IP_VERSION', '0.5.2' ); // Keep in sync with style.css "Version".
define( 'IP_DIR', get_template_directory() );
define( 'IP_URI', get_template_directory_uri() );

require IP_DIR . '/inc/setup.php';
require IP_DIR . '/inc/fields.php';
require IP_DIR . '/inc/options.php';
require IP_DIR . '/inc/images.php';
require IP_DIR . '/inc/template-tags.php';
require IP_DIR . '/inc/icons.php';
require IP_DIR . '/inc/navigation.php';
require IP_DIR . '/inc/woocommerce.php';
require IP_DIR . '/inc/shop.php';
require IP_DIR . '/inc/account.php';
require IP_DIR . '/inc/account-menu.php';
require IP_DIR . '/inc/product.php';
require IP_DIR . '/inc/content.php';
require IP_DIR . '/inc/catalog.php';
require IP_DIR . '/inc/inquiry.php';
require IP_DIR . '/inc/finder.php';
require IP_DIR . '/inc/emails.php';
require IP_DIR . '/inc/seo.php';
require IP_DIR . '/inc/performance.php';
require IP_DIR . '/inc/assets.php';
