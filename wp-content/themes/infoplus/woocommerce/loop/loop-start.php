<?php
/**
 * Product Loop Start
 *
 * Theme override: the product grid of archive.css (1/2/3/4 columns at
 * 0/560/768/1101 px) instead of Woo's float columns. role="list" keeps list
 * semantics in Safari, which drops them for list-style:none. The "products"
 * class stays for plugins that look for it. loop/loop-end.php (</ul>) is
 * unchanged and not overridden.
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<ul class="products ip-shop-grid" role="list">
