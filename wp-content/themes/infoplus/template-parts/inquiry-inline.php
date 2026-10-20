<?php
/**
 * Inquiry form inline in the page (Kontakt page, variant "contact"). Same
 * Suite form, steps, validation and success state as the drawer; driven by
 * assets/js/inquiry.js in its inline mode ([data-ip-inquiry-inline]).
 * Printed by ip_inquiry_render() (inc/inquiry.php), once per page.
 *
 * id="anfrage" is the target of the no-JS links from product pages
 * (/kontakt/?produkt=...#anfrage) and of in-page links to #anfrage.
 *
 * $args:
 * - variant (string) contact|product
 * - product (WC_Product|null) product named in ?produkt=, when it exists
 * - title, text (string) optional head texts
 */

defined( 'ABSPATH' ) || exit;

$ip_variant = ( $args['variant'] ?? 'contact' ) === 'product' ? 'product' : 'contact';
$ip_product = ( $args['product'] ?? null ) instanceof WC_Product ? $args['product'] : null;
$ip_title   = (string) ( $args['title'] ?? '' );
$ip_text    = (string) ( $args['text'] ?? '' );
$ip_name    = function_exists( 'ip_inquiry_requested_name' ) ? ip_inquiry_requested_name() : '';

// Context for the script: a found product by ID, else the plain name from the URL.
$ip_context = array();
if ( $ip_product ) {
	$ip_img     = (int) $ip_product->get_image_id();
	$ip_context = array(
		'product_id'   => $ip_product->get_id(),
		'product_name' => $ip_product->get_name(),
		'image'        => $ip_img ? (string) wp_get_attachment_image_url( $ip_img, 'woocommerce_thumbnail' ) : '',
	);
} elseif ( '' !== $ip_name ) {
	$ip_context = array( 'product_name' => $ip_name );
}
?>
<section class="ip-inq-inline" id="anfrage" aria-labelledby="ip-inquiry-title" data-ip-inquiry-inline data-variant="<?php echo esc_attr( $ip_variant ); ?>" data-ip-inq-context="<?php echo esc_attr( wp_json_encode( (object) $ip_context ) ); ?>" data-ip-inq-again="<?php esc_attr_e( 'Weitere Nachricht schreiben', 'infoplus' ); ?>">
	<header class="ip-inq-inline__head">
		<p class="ip-kicker"><?php esc_html_e( 'Kontaktformular', 'infoplus' ); ?></p>
		<h2 class="ip-inq-inline__title" id="ip-inquiry-title" tabindex="-1"><?php echo esc_html( $ip_title ? $ip_title : __( 'Nachricht an Infoplus', 'infoplus' ) ); ?></h2>
		<p class="ip-inq-inline__sub"><?php echo esc_html( $ip_text ? $ip_text : __( 'Schreiben Sie uns, wir melden uns bei Ihnen. Für Angebote zu einem Namensschild nennen Sie gern Modell und Menge.', 'infoplus' ) ); ?></p>
	</header>
	<?php
	infoplus_suite_inquiry_form( array(
		'variant'   => $ip_variant,
		'product'   => $ip_product,
		'id_prefix' => 'ip-inq',
	) );
	?>
	<noscript>
		<p class="ip-inq-inline__noscript"><?php esc_html_e( 'Das Formular braucht JavaScript. Bitte schreiben Sie uns eine E-Mail oder rufen Sie uns an.', 'infoplus' ); ?></p>
	</noscript>
</section>
