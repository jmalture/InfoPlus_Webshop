<?php
/**
 * Right column of the product page: kicker (category), title, short
 * description, then the configurator in the order of the old page: model
 * selector, variation dropdowns, quantity and Staffelpreise, the two action
 * buttons, the info box.
 *
 * Component args mirror the widget settings of template 2190; the texts come
 * from ip_product_texts() (inc/product.php).
 *
 * $args:
 * - product (WC_Product)
 * - suite   (bool) Suite helpers available.
 */

defined( 'ABSPATH' ) || exit;

$ip_product = $args['product'] ?? null;
if ( ! $ip_product instanceof WC_Product ) {
	return;
}
$ip_suite    = ! empty( $args['suite'] );
$ip_term     = ip_product_category( $ip_product );
$ip_short    = $ip_product->get_short_description();
$ip_variable = $ip_product->is_type( 'variable' );
?>
<header class="ip-product__head">
	<?php if ( $ip_term ) : ?>
		<p class="ip-kicker ip-product__kicker"><?php echo esc_html( $ip_term->name ); ?></p>
	<?php endif; ?>
	<?php the_title( '<h1 class="ip-product__title product_title entry-title">', '</h1>' ); ?>
	<?php if ( '' !== trim( wp_strip_all_tags( (string) $ip_short ) ) ) : ?>
		<div class="ip-product__lead woocommerce-product-details__short-description">
			<?php echo wp_kses_post( wc_format_content( $ip_short ) ); ?>
		</div>
	<?php endif; ?>
</header>

<div class="ip-product__config">
	<?php if ( $ip_suite && $ip_variable ) : ?>
		<div class="ip-product__step ip-product__step--model">
			<?php
			// Widget ca6b1d7: no title, no "from" price, info button as modal.
			// The theme shows the attribute name ("Model" on staging) as the field label.
			infoplus_suite_model_selector( $ip_product, array(
				'title'                => function_exists( 'wc_attribute_label' ) ? wc_attribute_label( 'pa_model', $ip_product ) : '',
				'show_title'           => true,
				'show_price'           => false,
				'show_info_button'     => true,
				'info_button_behavior' => 'modal',
				'sync_product_image'   => true,
				'layout'               => 'horizontal',
			) );
			?>
		</div>

		<div class="ip-product__step ip-product__step--options">
			<?php
			// Widget fc20ec9.
			infoplus_suite_variation_dropdowns( $ip_product, array(
				'attributes_to_show'   => ip_product_dropdown_attributes(),
				'exclude_model'        => true,
				'show_info_button'     => true,
				'info_button_behavior' => 'modal',
				'placeholder_text'     => ip_product_text( 'placeholder' ),
			) );
			?>
		</div>
	<?php endif; ?>

	<?php
	if ( ip_product_renders_wc_form() ) {
		// Native form for plugins that need it (filter ip_product_variations_form). Hidden:
		// the Suite components are the visible controls and sync their values into it.
		echo '<div class="ip-product__wc-form" hidden>';
		woocommerce_template_single_add_to_cart();
		echo '</div>';
	}

	if ( ip_product_fires_summary_hook() ) {
		/**
		 * Hook: woocommerce_single_product_summary (off by default, see inc/product.php).
		 * Woo's own callbacks are removed there; plugin callbacks print here.
		 */
		echo '<div class="ip-product__hooked">';
		do_action( 'woocommerce_single_product_summary' );
		echo '</div>';
	}

	get_template_part( 'template-parts/product/purchase', null, array( 'product' => $ip_product, 'suite' => $ip_suite ) );
	get_template_part( 'template-parts/product/actions', null, array( 'product' => $ip_product ) );

	if ( $ip_suite ) {
		// Widget 7de0ba7. Outline icons from the theme set instead of the Font Awesome
		// defaults; colour comes from the icon tile (product.css), not from the args.
		echo '<div class="ip-product__info">';
		infoplus_suite_product_info_box( $ip_product, array(
			'heading'              => ip_product_text( 'info_heading' ),
			'sku_label'            => ip_product_text( 'sku_label' ),
			'min_order_unit'       => ip_product_text( 'unit' ),
			'delivery_prefix'      => ip_product_text( 'delivery_pre' ),
			'delivery_suffix'      => ip_product_text( 'delivery_post' ),
			'icon_min_order_html'  => ip_product_icon( 'box' ),
			'icon_min_order_color' => 'inherit',
			'icon_delivery_html'   => ip_product_icon( 'clock' ),
			'icon_delivery_color'  => 'inherit',
		) );
		echo '</div>';
	}
	?>
</div>
