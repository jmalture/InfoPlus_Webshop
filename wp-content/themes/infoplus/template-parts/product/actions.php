<?php
/**
 * The action buttons. Which one is the action depends on the order type of
 * the selected model (Infoplus Suite, Attribute Overrides tab, meta
 * _infoplus_attr_order_type_model_{slug} on the product):
 *
 * - editable: "Produkt gestalten" (#editor, data-ip-action="editor") plus
 *   "Individuelle Anfrage". The Suite's variation-editor-link.js keeps the
 *   editor link disabled until a variation is found, then points it to the
 *   external editor ({baseUrl}?product={_ife_uuid}&theme={themeId}&cartEntryPath=…&backPath=…
 *   from window.infoplusEditorSettings), opening in a new tab.
 * - buyable: "In den Warenkorb" (data-ip-action="cart"), the submit button of
 *   a real WooCommerce add-to-cart form. It posts to the product permalink, so
 *   WC_Form_Handler::add_to_cart_action adds the line: add-to-cart (parent ID),
 *   variation_id, attribute_* and quantity, plus the marker infoplus_buy_form
 *   that the Suite's cart item data filter looks for (model image). Validation
 *   errors and the success message come back as Woo notices at the top of the
 *   page; with "Redirect to the cart page after successful addition" on, Woo
 *   redirects to the cart. assets/js/product.js fills the hidden fields from
 *   the Suite's product state and enables the button once a variation is found.
 *   The Suite (0.10.27+) sees form[data-ip-buy-form] and no longer turns
 *   #editor into its admin-ajax cart button (the Elementor page keeps that).
 * - requestable / requestable_with_variants: "Individuelle Anfrage" only
 *   (#anfrage, data-ip-action="inquiry"), shown as the primary button. For now
 *   it links to the Kontakt page with the product name (ip_product_inquiry_url()).
 *
 * product.js writes the active type to data-ip-order-type on [data-ip-actions]
 * and the matching sentence into [data-ip-order-hint] (polite live region).
 *
 * $args:
 * - product (WC_Product)
 */

defined( 'ABSPATH' ) || exit;

$ip_product = $args['product'] ?? null;
if ( ! $ip_product instanceof WC_Product ) {
	return;
}
$ip_id       = $ip_product->get_id();
// Always for variable products: with the form on the page the Suite never falls back to its
// admin-ajax cart button. WooCommerce itself refuses products that are not purchasable.
$ip_can_buy  = $ip_product->is_type( 'variable' );
$ip_attrs    = $ip_can_buy ? array_keys( $ip_product->get_variation_attributes() ) : array();
$ip_posted   = ip_product_buy_form_posted( $ip_product );
$ip_hints    = array(
	'editable'    => ip_product_text( 'hint_editable' ),
	'buyable'     => ip_product_text( 'hint_buyable' ),
	'requestable' => ip_product_text( 'hint_requestable' ),
);
?>
<div class="ip-actions" data-ip-actions data-ip-order-type="editable">
	<a id="editor" class="ip-btn ip-actions__btn ip-actions__btn--editor infoplus-btn-disabled" data-ip-action="editor" href="#" aria-disabled="true">
		<span data-ip-label><?php echo esc_html( ip_product_text( 'editor_label' ) ); ?></span>
		<span class="ip-sr"><?php echo esc_html( ip_product_text( 'editor_sr' ) ); ?></span>
		<?php echo ip_product_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
	</a>

	<?php if ( $ip_can_buy ) : ?>
		<form class="cart ip-actions__form" data-ip-buy-form hidden data-ip-busy-label="<?php echo esc_attr( ip_product_text( 'cart_busy' ) ); ?>" data-ip-choose-text="<?php echo esc_attr( ip_product_text( 'choose_options' ) ); ?>" method="post" enctype="multipart/form-data" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $ip_product->get_permalink() ) ); ?>"<?php echo $ip_posted ? ' data-ip-posted="' . esc_attr( wp_json_encode( $ip_posted ) ) . '"' : ''; ?>>
			<?php
			/*
			 * Plugins that add fields to the add-to-cart form print them here
			 * (on staging: File Uploads Addon for WooCommerce, only for its
			 * configured category). The *_add_to_cart_form hooks stay off, like
			 * woocommerce_single_product_summary (express checkout buttons, see
			 * inc/product.php). Filter ip_product_buy_form_button_hooks to skip them.
			 */
			if ( apply_filters( 'ip_product_buy_form_button_hooks', true, $ip_product ) ) {
				do_action( 'woocommerce_before_add_to_cart_button' );
			}
			?>
			<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $ip_id ); ?>">
			<input type="hidden" name="product_id" value="<?php echo esc_attr( $ip_id ); ?>">
			<input type="hidden" name="variation_id" class="variation_id" value="0" data-ip-buy="variation">
			<input type="hidden" name="quantity" class="qty" value="1" data-ip-buy="qty">
			<input type="hidden" name="infoplus_buy_form" value="1">
			<?php foreach ( $ip_attrs as $ip_attr ) : ?>
				<input type="hidden" name="<?php echo esc_attr( 'attribute_' . sanitize_title( $ip_attr ) ); ?>" value="" data-ip-buy-attr="<?php echo esc_attr( sanitize_title( $ip_attr ) ); ?>">
			<?php endforeach; ?>
			<button type="submit" class="ip-btn ip-actions__btn ip-actions__btn--cart single_add_to_cart_button infoplus-btn-disabled" data-ip-action="cart" aria-disabled="true">
				<?php echo ip_product_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
				<span data-ip-label><?php echo esc_html( ip_product_text( 'cart_label' ) ); ?></span>
			</button>
			<?php
			if ( apply_filters( 'ip_product_buy_form_button_hooks', true, $ip_product ) ) {
				do_action( 'woocommerce_after_add_to_cart_button' );
			}
			?>
		</form>
	<?php endif; ?>

	<a id="anfrage" class="ip-btn ip-btn--ghost ip-actions__btn ip-actions__btn--inquiry" data-ip-action="inquiry" href="<?php echo esc_url( ip_product_inquiry_url( $ip_product ) ); ?>">
		<?php echo ip_product_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
		<span data-ip-label><?php echo esc_html( ip_product_text( 'inquiry_label' ) ); ?></span>
	</a>

	<p class="ip-actions__hint" data-ip-order-hint aria-live="polite" data-ip-hints="<?php echo esc_attr( wp_json_encode( $ip_hints ) ); ?>"><?php echo esc_html( $ip_hints['editable'] ); ?></p>
	<?php
	/**
	 * TODO (inquiry modal task): the theme's inquiry modal hooks in here, opens on
	 * [data-ip-action="inquiry"] instead of following the link, and dispatches
	 * document.dispatchEvent(new CustomEvent('infoplus:inquiry-open')) so the
	 * Suite marks the product field (data-ip-field="product") readonly.
	 *
	 * @param WC_Product $product
	 */
	do_action( 'infoplus_theme_inquiry_button', $ip_product );
	?>
</div>
