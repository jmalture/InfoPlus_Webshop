<?php
/**
 * The Template for displaying product archives, including the main shop page which is a post type archive
 *
 * Theme override for the shop, product categories/tags and the product
 * search of the header (?s=…&post_type=product, which Woo treats as the shop
 * archive). Layout: white band with the section head and category pills
 * (template-parts/shop-head.php), then the card grid (content-product.php)
 * and Woo's pagination. Every Woo hook of the original still fires; the
 * default callbacks the theme replaces (wrappers, breadcrumb, archive
 * header, result count, ordering, sidebar, "no products" notice) are
 * removed in inc/shop.php. The Namensschilder page renders the same layout
 * through page-namensschilder.php.
 *
 * Category pills filter in place (inc/catalog.php): on a product category
 * that has a pill, the grid holds the whole catalog in catalog order and the
 * cards of other categories are hidden, so assets/js/archive.js can switch
 * pills without a page load. The main query still decides 404, title and
 * the Woo context of the page.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.6.0
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

$ip_mode = is_search() ? 'search' : 'archive';
?>
<div class="ip-shop ip-shop--<?php echo esc_attr( $ip_mode ); ?>">
	<div class="ip-shell">
		<?php
		/**
		 * Hook: woocommerce_before_main_content.
		 *
		 * @hooked woocommerce_output_content_wrapper - 10 (removed by the theme: header.php opens <main>)
		 * @hooked woocommerce_breadcrumb - 20 (removed by the theme: ip_breadcrumb() in the head)
		 * @hooked WC_Structured_Data::generate_website_data() - 30
		 */
		do_action( 'woocommerce_before_main_content' );

		get_template_part( 'template-parts/shop-head', null, array(
			'mode'  => $ip_mode,
			'total' => (int) wc_get_loop_prop( 'total', $GLOBALS['wp_query']->found_posts ),
			'pills' => 'archive' === $ip_mode,
		) );

		/**
		 * Hook: woocommerce_shop_loop_header.
		 *
		 * @since 8.6.0
		 *
		 * @hooked woocommerce_product_taxonomy_archive_header - 10 (removed by the theme: shop-head.php)
		 */
		do_action( 'woocommerce_shop_loop_header' );

		/**
		 * Hook: woocommerce_archive_description.
		 *
		 * @hooked woocommerce_taxonomy_archive_description - 10 (removed by the theme: term description is the subline)
		 * @hooked woocommerce_product_archive_description - 10 (shop page content, if the client writes any)
		 */
		do_action( 'woocommerce_archive_description' );

		if ( woocommerce_product_loop() ) {

			/**
			 * Hook: woocommerce_before_shop_loop.
			 *
			 * @hooked woocommerce_output_all_notices - 10
			 * @hooked woocommerce_result_count - 20 (removed by the theme)
			 * @hooked woocommerce_catalog_ordering - 30 (removed by the theme)
			 */
			do_action( 'woocommerce_before_shop_loop' );

			// The whole catalog when the pills filter in place, else the main query.
			$ip_ctx   = function_exists( 'ip_catalog_context' ) ? ip_catalog_context() : null;
			$ip_query = $ip_ctx ? $ip_ctx['query'] : $GLOBALS['wp_query'];

			ip_prime_posts( wp_list_pluck( $ip_query->posts, 'ID' ) ); // Card images in one query.
			wc_set_loop_prop( 'ip_card_level', 'h2' );
			wc_set_loop_prop( 'ip_lcp', 1 === max( 1, (int) wc_get_loop_prop( 'current_page', 1 ) ) );

			woocommerce_product_loop_start();

			if ( wc_get_loop_prop( 'total' ) ) {
				while ( $ip_query->have_posts() ) {
					$ip_query->the_post(); // Sets the global $product (wc_setup_product_data).

					/**
					 * Hook: woocommerce_shop_loop.
					 */
					do_action( 'woocommerce_shop_loop' );

					wc_get_template_part( 'content', 'product' );
				}
				if ( $ip_ctx ) {
					wp_reset_postdata();
				}
			}

			woocommerce_product_loop_end();

			/**
			 * Hook: woocommerce_after_shop_loop.
			 *
			 * @hooked woocommerce_pagination - 10
			 */
			do_action( 'woocommerce_after_shop_loop' );
		} else {
			/**
			 * Hook: woocommerce_no_products_found.
			 *
			 * @hooked wc_no_products_found - 10 (removed by the theme: shop-empty.php)
			 */
			do_action( 'woocommerce_no_products_found' );

			get_template_part( 'template-parts/shop-empty', null, array( 'mode' => $ip_mode ) );
		}

		/**
		 * Hook: woocommerce_after_main_content.
		 *
		 * @hooked woocommerce_output_content_wrapper_end - 10 (removed by the theme)
		 */
		do_action( 'woocommerce_after_main_content' );
		?>
	</div>
</div>
<?php
/**
 * Hook: woocommerce_sidebar.
 *
 * @hooked woocommerce_get_sidebar - 10 (removed by the theme: no sidebar)
 */
do_action( 'woocommerce_sidebar' );

get_footer( 'shop' );
