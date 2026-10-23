<?php
/**
 * The "Namensschilder" page (/namensschilder/, page 15902): all models in
 * the same layout as the shop archive (woocommerce/archive-product.php).
 *
 * The old page was an Elementor heading plus a loop grid of loop item 15906
 * over all products. WordPress picks this file by the page slug, so nothing
 * changes in the database and the URL the menus link to stays. The page's
 * own content (an Elementor copy of the heading) is not rendered; the head
 * texts come from the site settings (shop_kicker, shop_heading, shop_intro).
 *
 * Recommended at launch: set this page as the WooCommerce shop page
 * (WooCommerce > Settings > Products > Shop page). Then /namensschilder/ is
 * the real product archive (archive-product.php), this file is no longer
 * used, and /shop/ (page 26, empty) can be redirected and removed.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$ip_paged = max( 1, (int) get_query_var( 'paged' ) );
$ip_args  = array(
	'post_type'              => 'product',
	'post_status'            => 'publish',
	'posts_per_page'         => IP_SHOP_PER_PAGE,
	'paged'                  => $ip_paged,
	'orderby'                => array( 'menu_order' => 'ASC', 'title' => 'ASC' ), // Woo's default catalog order.
	'update_post_term_cache' => true,
);
if ( function_exists( 'WC' ) && WC()->query ) {
	$ip_args['tax_query'] = WC()->query->get_tax_query(); // phpcs:ignore WordPress.DB.SlowDBQuery -- Woo catalog visibility, as on the shop.
}
// With the in-place category filter (inc/catalog.php) its query is the same one; reuse it.
$ip_ctx   = function_exists( 'ip_catalog_context' ) ? ip_catalog_context() : null;
$ip_query = $ip_ctx ? $ip_ctx['query'] : new WP_Query( $ip_args );
ip_prime_posts( wp_list_pluck( $ip_query->posts, 'ID' ) );
$ip_woo = function_exists( 'wc_setup_loop' );
?>
<div class="ip-shop ip-shop--archive">
	<div class="ip-shell">
		<?php
		get_template_part( 'template-parts/shop-head', null, array(
			'mode'  => 'archive',
			'total' => (int) $ip_query->found_posts,
			'pills' => true,
		) );

		if ( $ip_query->have_posts() ) {
			if ( $ip_woo ) {
				wc_setup_loop( array(
					'name'         => 'ip-namensschilder',
					'is_paginated' => true,
					'total'        => (int) $ip_query->found_posts,
					'total_pages'  => (int) $ip_query->max_num_pages,
					'per_page'     => IP_SHOP_PER_PAGE,
					'current_page' => $ip_paged,
					'ip_card_level' => 'h2',
					'ip_lcp'       => 1 === $ip_paged,
				) );

				/** This action is documented in woocommerce/templates/archive-product.php (notices). */
				do_action( 'woocommerce_before_shop_loop' );

				woocommerce_product_loop_start();
				while ( $ip_query->have_posts() ) {
					$ip_query->the_post(); // Sets the global $product (wc_setup_product_data).

					/** This action is documented in woocommerce/templates/archive-product.php */
					do_action( 'woocommerce_shop_loop' );

					wc_get_template_part( 'content', 'product' );
				}
				woocommerce_product_loop_end();

				/** This action is documented in woocommerce/templates/archive-product.php (pagination). */
				do_action( 'woocommerce_after_shop_loop' );
			} else {
				// WooCommerce inactive (preview, local dev): same cards without Woo.
				echo '<ul class="products ip-shop-grid" role="list">';
				foreach ( $ip_query->posts as $ip_i => $ip_post ) {
					echo '<li class="ip-shop-grid__item"' . ( function_exists( 'ip_catalog_card_attrs' ) ? ip_catalog_card_attrs( $ip_post->ID ) : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in ip_catalog_card_attrs().
					get_template_part( 'template-parts/product-card', null, array( 'id' => $ip_post->ID, 'level' => 'h2', 'eager' => 0 === $ip_i && 1 === $ip_paged ) );
					echo '</li>';
				}
				echo '</ul>';
			}
			wp_reset_postdata();
		} else {
			get_template_part( 'template-parts/shop-empty', null, array( 'mode' => 'archive' ) );
		}
		?>
	</div>
</div>
<?php
get_footer();
