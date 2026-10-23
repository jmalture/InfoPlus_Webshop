<?php
/**
 * Search results for "/?s=…" without post_type=product. Search is limited to
 * products (pre_get_posts in inc/shop.php, as the old child theme did), so
 * this is the same card grid as the shop. The header form sends
 * post_type=product; WooCommerce then renders woocommerce/archive-product.php
 * in its search mode, with the same head and empty state.
 *
 * Old site: Elementor search template 15663 ("Suchergebnis:" + archive posts).
 */

defined( 'ABSPATH' ) || exit;

get_header();

global $wp_query;
$ip_paged = max( 1, (int) get_query_var( 'paged' ) );
$ip_woo   = function_exists( 'wc_setup_loop' );
?>
<div class="ip-shop ip-shop--search">
	<div class="ip-shell">
		<?php
		get_template_part( 'template-parts/shop-head', null, array(
			'mode'  => 'search',
			'total' => (int) $wp_query->found_posts,
			'pills' => false,
		) );

		if ( have_posts() ) {
			ip_prime_posts( wp_list_pluck( $wp_query->posts, 'ID' ) );

			if ( $ip_woo ) {
				wc_setup_loop( array(
					'name'          => 'ip-search',
					'is_search'     => true,
					'is_paginated'  => true,
					'total'         => (int) $wp_query->found_posts,
					'total_pages'   => (int) $wp_query->max_num_pages,
					'per_page'      => (int) $wp_query->get( 'posts_per_page' ),
					'current_page'  => $ip_paged,
					'ip_card_level' => 'h2',
					'ip_lcp'        => 1 === $ip_paged,
				) );

				/** This action is documented in woocommerce/templates/archive-product.php (notices). */
				do_action( 'woocommerce_before_shop_loop' );

				woocommerce_product_loop_start();
				while ( have_posts() ) {
					the_post();

					/** This action is documented in woocommerce/templates/archive-product.php */
					do_action( 'woocommerce_shop_loop' );

					wc_get_template_part( 'content', 'product' );
				}
				woocommerce_product_loop_end();

				/** This action is documented in woocommerce/templates/archive-product.php (pagination). */
				do_action( 'woocommerce_after_shop_loop' );
			} else {
				echo '<ul class="products ip-shop-grid" role="list">';
				$ip_i = 0;
				while ( have_posts() ) {
					the_post();
					echo '<li class="ip-shop-grid__item">';
					get_template_part( 'template-parts/product-card', null, array( 'id' => get_the_ID(), 'level' => 'h2', 'eager' => 0 === $ip_i++ && 1 === $ip_paged ) );
					echo '</li>';
				}
				echo '</ul>';
				the_posts_pagination();
			}
		} else {
			get_template_part( 'template-parts/shop-empty', null, array( 'mode' => 'search' ) );
		}
		?>
	</div>
</div>
<?php
get_footer();
