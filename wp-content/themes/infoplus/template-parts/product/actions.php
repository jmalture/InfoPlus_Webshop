<?php
/**
 * The two action buttons of the old page, with their old IDs (#editor,
 * #anfrage) and the data attributes the Suite looks for since 0.10.26 / 1.1.1.
 *
 * "Produkt gestalten" (data-ip-action="editor"): starts disabled. The Suite's
 * variation-editor-link.js shows or hides it per model order type and, once
 * a variation is found, sets the link to the external editor:
 * {baseUrl}?product={_ife_uuid}&theme={themeId}&cartEntryPath=…&backPath=…
 * from window.infoplusEditorSettings (WooSingleProductAddons, wp_head; base
 * URL, theme ID and paths from the EditorLinkSettings tab, product UUID from
 * the product meta _ife_uuid). For "buyable" models it turns the same button
 * into "In den Warenkorb" (admin-ajax infoplus_add_to_cart).
 *
 * "Individuelle Anfrage" (data-ip-action="inquiry"): the old page opened the
 * Elementor popup 14241. For now it links to the Kontakt page with the
 * product name (ip_product_inquiry_url()).
 *
 * $args:
 * - product (WC_Product)
 */

defined( 'ABSPATH' ) || exit;

$ip_product = $args['product'] ?? null;
if ( ! $ip_product instanceof WC_Product ) {
	return;
}
?>
<div class="ip-actions" data-ip-actions>
	<a id="editor" class="ip-btn ip-actions__btn ip-actions__btn--editor infoplus-btn-disabled" data-ip-action="editor" href="#" aria-disabled="true">
		<span data-ip-label><?php echo esc_html( ip_product_text( 'editor_label' ) ); ?></span>
		<?php echo ip_product_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
	</a>
	<a id="anfrage" class="ip-btn ip-btn--ghost ip-actions__btn ip-actions__btn--inquiry" data-ip-action="inquiry" href="<?php echo esc_url( ip_product_inquiry_url( $ip_product ) ); ?>">
		<span data-ip-label><?php echo esc_html( ip_product_text( 'inquiry_label' ) ); ?></span>
	</a>
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
