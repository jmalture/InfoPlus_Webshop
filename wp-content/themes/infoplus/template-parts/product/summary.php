<?php
/**
 * Right column of the product page: kicker (category), title, short
 * description, then the configurator as numbered steps (DESIGN.md, "E-shop
 * UX for B2B buyers and resellers"):
 *
 *   1 Modell                  model selector
 *   2 {attribute labels}      variation dropdowns, titled with the product's
 *                             real attribute labels ("Format und Befestigung")
 *   3 Menge                   Staffelpreise, quantity, prices (purchase.php)
 *
 * then the summary card with the main action (order-summary.php, sticky on
 * desktop), the facts under it (facts.php) and the Suite info box (SKU and
 * minimum quantity; its delivery row moved into the facts).
 *
 * Each step is a <section> named by its <h2>. The header shows the number, the
 * title and a status slot ([data-ip-step-status]); assets/js/product.js fills
 * the slot from the Suite product state (check + chosen value when done, the
 * Suite's lock message for models that are only requestable) and sets
 * data-ip-step-state="open|done|locked|error". Without JS the steps are plain
 * titled blocks. Step numbers follow the steps that render (a product without
 * dropdown attributes has "2 Menge").
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
$ip_steps    = $ip_suite && $ip_variable;
$ip_options  = $ip_steps ? ip_product_option_attributes( $ip_product ) : array();
$ip_num      = 0;

/**
 * Opening markup of one step: section, numbered heading with the status slot.
 *
 * @param string $key   model|options|qty
 * @param int    $num   Step number.
 * @param string $title Visible title.
 */
$ip_step_open = static function ( $key, $num, $title ) {
	$id = 'ip-step-' . $key;
	?>
	<section class="ip-step ip-step--<?php echo esc_attr( $key ); ?>" data-ip-step="<?php echo esc_attr( $key ); ?>" data-ip-step-state="open" aria-labelledby="<?php echo esc_attr( $id ); ?>">
		<h2 class="ip-step__head" id="<?php echo esc_attr( $id ); ?>">
			<span class="ip-step__badge" aria-hidden="true">
				<span class="ip-step__num"><?php echo (int) $num; ?></span>
				<?php echo ip_product_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
			</span>
			<span class="ip-step__title"><span class="ip-sr"><?php echo esc_html( sprintf( ip_product_text( 'step_prefix' ), $num ) ); ?> </span><?php echo esc_html( $title ); ?></span>
			<span class="ip-step__status" data-ip-step-status><span class="ip-sr"><?php echo esc_html( ip_product_text( 'step_open' ) ); ?></span></span>
		</h2>
		<div class="ip-step__body">
	<?php
};
$ip_step_close = static function () {
	echo "\t\t</div>\n\t</section>\n";
};
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

<?php
// Texts product.js writes into the step headers, the summary card and the phone bar.
$ip_js_texts = array(
	'done'        => ip_product_text( 'step_done' ),
	'open'        => ip_product_text( 'step_open' ),
	'unavailable' => ip_product_text( 'unavailable' ),
	'empty'       => ip_product_text( 'summary_empty' ),
	'unit'        => ip_product_text( 'summary_unit' ),
	'live'        => ip_product_text( 'summary_live' ),
	'total'       => rtrim( ip_product_text( 'total_label' ), '*' ),
	'pieces'      => ip_product_text( 'unit' ),
);
?>
<div class="ip-product__config" data-ip-config data-ip-texts="<?php echo esc_attr( wp_json_encode( $ip_js_texts ) ); ?>">
	<?php
	if ( $ip_steps ) {
		$ip_step_open( 'model', ++$ip_num, ip_product_text( 'step_model' ) );
		// Widget ca6b1d7: no "from" price, info button as modal. The step heading is the
		// title, so the component prints none (show_title off).
		infoplus_suite_model_selector( $ip_product, array(
			'title'                => '',
			'show_title'           => false,
			'show_price'           => false,
			'show_info_button'     => true,
			'info_button_behavior' => 'modal',
			'sync_product_image'   => true,
			'layout'               => 'horizontal',
		) );
		$ip_step_close();

		if ( $ip_options ) {
			$ip_step_open( 'options', ++$ip_num, ip_product_options_step_title( $ip_product ) );
			// Widget fc20ec9. Each dropdown keeps its own label (the attribute name).
			infoplus_suite_variation_dropdowns( $ip_product, array(
				'attributes_to_show'   => ip_product_dropdown_attributes(),
				'exclude_model'        => true,
				'show_info_button'     => true,
				'info_button_behavior' => 'modal',
				'placeholder_text'     => ip_product_text( 'placeholder' ),
			) );
			$ip_step_close();
		}
	}

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

	if ( $ip_steps ) {
		$ip_step_open( 'qty', ++$ip_num, ip_product_text( 'step_qty' ) );
		// Shown by product.css while the step waits for a complete selection (.is-waiting).
		echo '<p class="ip-step__wait">' . esc_html( ip_product_text( 'qty_wait' ) ) . '</p>';
	}
	get_template_part( 'template-parts/product/purchase', null, array( 'product' => $ip_product, 'suite' => $ip_suite ) );
	if ( $ip_steps ) {
		$ip_step_close();
	}

	get_template_part( 'template-parts/product/order-summary', null, array( 'product' => $ip_product, 'suite' => $ip_suite ) );
	get_template_part( 'template-parts/product/facts', null, array( 'product' => $ip_product ) );

	if ( $ip_suite ) {
		// Widget 7de0ba7. Outline icons from the theme set instead of the Font Awesome
		// defaults; colour comes from the icon tile (product.css), not from the args.
		// The delivery row is off: the lead time is a fact row under the main button now.
		echo '<div class="ip-product__info">';
		infoplus_suite_product_info_box( $ip_product, array(
			'heading'              => ip_product_text( 'info_heading' ),
			'sku_label'            => ip_product_text( 'sku_label' ),
			'min_order_unit'       => ip_product_text( 'unit' ),
			'show_delivery'        => false,
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
