<?php
/**
 * The template for displaying product content in the single-product.php template
 *
 * Theme override: the product configurator (Infoplus Suite components) in
 * the theme layout. Breadcrumb, then two columns from 1101 px (gallery left,
 * sticky; configurator right), stacked on phones with a sticky action bar,
 * then the description and the "Individuelle Anfrage" section as cards.
 * Parts: template-parts/product/*.php. Logic and hook removals: inc/product.php.
 *
 * Hooks kept for plugins: woocommerce_before_single_product (notices),
 * woocommerce_before_single_product_summary, woocommerce_after_single_product_summary
 * and woocommerce_after_single_product (prints window.infoplusProductData and
 * window.currentProduct, which the Suite scripts need). Not fired by default:
 * woocommerce_single_product_summary (see inc/product.php).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

/**
 * Hook: woocommerce_before_single_product.
 *
 * @hooked woocommerce_output_all_notices - 10
 */
echo '<div class="ip-shell ip-product-notices">';
do_action( 'woocommerce_before_single_product' );
echo '</div>';

if ( post_password_required() ) {
	echo '<div class="ip-shell ip-page">' . get_the_password_form() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}

if ( ! $product instanceof WC_Product ) {
	return;
}

$ip_has_suite = ip_product_has_suite();
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'ip-product', $product ); ?>>
	<div class="ip-shell">
		<?php ip_breadcrumb( ip_product_breadcrumb_items( $product ) ); ?>

		<div class="ip-product__grid">
			<div class="ip-product__media">
				<?php
				/**
				 * Hook: woocommerce_before_single_product_summary.
				 *
				 * @hooked woocommerce_show_product_sale_flash - 10 (removed by the theme)
				 * @hooked woocommerce_show_product_images - 20 (removed by the theme: Suite product image)
				 */
				do_action( 'woocommerce_before_single_product_summary' );

				get_template_part( 'template-parts/product/gallery', null, array( 'product' => $product, 'suite' => $ip_has_suite ) );
				?>
			</div>

			<div class="ip-product__summary summary entry-summary">
				<?php get_template_part( 'template-parts/product/summary', null, array( 'product' => $product, 'suite' => $ip_has_suite ) ); ?>
			</div>
		</div>

		<?php
		/**
		 * Hook: woocommerce_after_single_product_summary.
		 *
		 * @hooked woocommerce_output_product_data_tabs - 10 (removed by the theme: description card below)
		 * @hooked woocommerce_upsell_display - 15 (removed by the theme)
		 * @hooked woocommerce_output_related_products - 20 (removed by the theme)
		 */
		do_action( 'woocommerce_after_single_product_summary' );

		get_template_part( 'template-parts/product/details', null, array( 'product' => $product ) );
		?>
	</div>

	<?php get_template_part( 'template-parts/product/action-bar', null, array( 'product' => $product, 'suite' => $ip_has_suite ) ); ?>
</div>

<?php
/**
 * Hook: woocommerce_after_single_product.
 *
 * @hooked ProductDetailSuite\Module::outputProductState - 5 (window.infoplusProductData)
 * @hooked WooSingleProductAddons\Module::outputProductContextJson - 5 (window.currentProduct)
 */
do_action( 'woocommerce_after_single_product' );
