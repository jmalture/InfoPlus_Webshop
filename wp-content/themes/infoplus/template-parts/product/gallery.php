<?php
/**
 * Product image with zoom, gallery thumbnails and lightbox: the Suite's
 * product-image component (product-image.js swaps the images when a model is
 * picked). Without the Suite: the product image as a plain responsive image.
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
?>
<div class="ip-product__gallery">
	<?php
	if ( ! empty( $args['suite'] ) ) {
		// Same settings as widget d85ba62 in template 2190 (control defaults).
		infoplus_suite_product_image( $ip_product, array(
			'enable_zoom'      => true,
			'zoom_scale'       => 2,
			'enable_lightbox'  => true,
			'show_gallery'     => true,
			'gallery_position' => 'bottom',
		) );
	} else {
		echo '<div class="ip-product__fallback-image">';
		echo ip_img( $ip_product->get_image_id(), '(min-width: 1101px) 50vw, 100vw', array( 'loading' => 'eager', 'fetchpriority' => 'high', 'alt' => $ip_product->get_name() ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image() output.
		echo '</div>';
	}
	?>
</div>
