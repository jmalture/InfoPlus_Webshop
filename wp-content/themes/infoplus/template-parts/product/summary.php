<?php
/**
 * Right column of the product page: kicker (category), title, short
 * description, then the configurator as folding steps (DESIGN.md, "E-shop
 * UX for B2B buyers and resellers", owner decision "Folding steps"):
 *
 *   1 Modell                  model selector
 *   2..n {attribute label}    one step per variation attribute, in the
 *                             product's attribute order ("Frontscheibe",
 *                             "Format", "Befestigung" ...). Each step holds
 *                             its own Suite variation-dropdowns component
 *                             (attributes_to_show = that one attribute).
 *   n+1 Menge                 Staffelpreise, quantity, prices (purchase.php)
 *
 * then the summary card with the main action (order-summary.php, sticky on
 * desktop), the facts under it (facts.php) and the Suite info box (SKU and
 * minimum quantity; its delivery row moved into the facts).
 *
 * Each step is a <section> named by its <h2>. The header shows the number, the
 * title and a status slot ([data-ip-step-status]) plus an "Ändern" button.
 * assets/js/product.js reads the Suite product state and
 * - fills the status slot (check + chosen value when done, conflict reason,
 *   the Suite's lock message for models that are only requestable) and sets
 *   data-ip-step-state="open|done|locked|error";
 * - keeps exactly one step expanded (class is-current), folds the others into
 *   one line and moves on to the next step after a choice;
 * - fills the step bar above the steps ([data-ip-stepnav]: number, short name,
 *   check when done, aria-current on the expanded step).
 * Without JS the steps are plain titled blocks, all open, and the step bar
 * stays hidden (product.css styles the folding only under html.js).
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
$ip_attr_args = array(
	'exclude_model'        => true,
	'show_info_button'     => true,
	'info_button_behavior' => 'modal',
	'placeholder_text'     => ip_product_text( 'placeholder' ),
);

// Steps in display order: key => title. Keys: model, the attribute taxonomy, qty.
$ip_step_list = array();
if ( $ip_steps ) {
	$ip_step_list['model'] = ip_product_text( 'step_model' );
	foreach ( $ip_options as $ip_tax => $ip_label ) {
		$ip_step_list[ $ip_tax ] = $ip_label;
	}
	$ip_step_list['qty'] = ip_product_text( 'step_qty' );
}

/**
 * Opening markup of one step: section, numbered heading with the status slot,
 * the "Ändern" button that reopens a folded step, then the body.
 *
 * @param string $key   model|qty|attribute taxonomy (pa_format ...)
 * @param int    $num   Step number.
 * @param string $title Visible title.
 */
$ip_step_open = static function ( $key, $num, $title ) {
	$id   = 'ip-step-' . sanitize_html_class( $key );
	$type = in_array( $key, array( 'model', 'qty' ), true ) ? $key : 'attr';
	?>
	<section class="ip-step ip-step--<?php echo esc_attr( $type ); ?>" data-ip-step="<?php echo esc_attr( $key ); ?>" data-ip-step-state="open" aria-labelledby="<?php echo esc_attr( $id ); ?>">
		<div class="ip-step__bar">
			<h2 class="ip-step__head" id="<?php echo esc_attr( $id ); ?>" tabindex="-1">
				<span class="ip-step__badge" aria-hidden="true">
					<span class="ip-step__num"><?php echo (int) $num; ?></span>
					<?php echo ip_product_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
				</span>
				<span class="ip-step__title"><span class="ip-sr"><?php echo esc_html( sprintf( ip_product_text( 'step_prefix' ), $num ) ); ?> </span><?php echo esc_html( $title ); ?></span>
				<span class="ip-step__status" data-ip-step-status><span class="ip-sr"><?php echo esc_html( ip_product_text( 'step_open' ) ); ?></span></span>
			</h2>
			<button type="button" class="ip-step__edit" data-ip-step-edit aria-controls="<?php echo esc_attr( $id . '-body' ); ?>" aria-expanded="true">
				<span data-ip-step-edit-label><?php echo esc_html( ip_product_text( 'step_edit' ) ); ?></span><span class="ip-sr"> <?php echo esc_html( $title ); ?></span>
			</button>
		</div>
		<div class="ip-step__body" id="<?php echo esc_attr( $id . '-body' ); ?>">
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
	'edit'        => ip_product_text( 'step_edit' ),
	'choose'      => ip_product_text( 'step_choose' ),
);
?>
<?php // data-ip-placeholder: the image the Suite uses for a model without picture (text-only card in product.js). ?>
<div class="ip-product__config" data-ip-config data-ip-texts="<?php echo esc_attr( wp_json_encode( $ip_js_texts ) ); ?>" data-ip-placeholder="<?php echo esc_attr( function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src( 'woocommerce_thumbnail' ) : '' ); ?>">
	<?php if ( count( $ip_step_list ) > 1 ) : ?>
		<nav class="ip-stepnav" data-ip-stepnav aria-label="<?php echo esc_attr( ip_product_text( 'steps_nav' ) ); ?>">
			<ol class="ip-stepnav__list">
				<?php
				$ip_n = 0;
				foreach ( $ip_step_list as $ip_key => $ip_title ) :
					++$ip_n;
					?>
					<li class="ip-stepnav__item" data-ip-stepnav-item="<?php echo esc_attr( $ip_key ); ?>">
						<button type="button" class="ip-stepnav__btn" data-ip-goto="<?php echo esc_attr( $ip_key ); ?>" aria-controls="<?php echo esc_attr( 'ip-step-' . sanitize_html_class( $ip_key ) ); ?>">
							<span class="ip-stepnav__badge" aria-hidden="true"><span class="ip-stepnav__num"><?php echo (int) $ip_n; ?></span><?php echo ip_product_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
							<span class="ip-stepnav__label"><?php echo esc_html( $ip_title ); ?></span>
							<span class="ip-sr" data-ip-stepnav-state></span>
						</button>
					</li>
				<?php endforeach; ?>
			</ol>
		</nav>
	<?php endif; ?>
	<?php
	if ( $ip_steps ) {
		// All steps in one list (product.css: hairlines between them, compact when folded).
		echo '<div class="ip-cfg">';
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

		// Requestable models: the Suite locks the attribute steps; product.js hides them and shows its message here.
		echo '<p class="ip-step-lock" data-ip-step-lock hidden></p>';

		// One step per attribute, each with its own Suite component limited to that attribute
		// (widget fc20ec9 split up). Every component instance listens to the shared product state.
		foreach ( $ip_options as $ip_tax => $ip_label ) {
			$ip_step_open( $ip_tax, ++$ip_num, $ip_label );
			infoplus_suite_variation_dropdowns( $ip_product, array_merge( $ip_attr_args, array( 'attributes_to_show' => array( $ip_tax ) ) ) );
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
		echo '</div>';
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
