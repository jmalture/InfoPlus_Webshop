<?php
/**
 * Product card, the one used everywhere products are listed (shop, product
 * categories, Namensschilder page, search; later home and mega menu).
 *
 * Port of Elementor loop item 15906 (image, title, short description) in the
 * DESIGN.md card style: white card, picture on a Very Light Grey tile, name,
 * a two-line description and a "Konfigurieren" cue. No prices (owner
 * decision: every Namensschild is a variable product with up to 1,380
 * variations, so a price range says little and costs a lot to compute).
 *
 * One link per card: the title link is stretched over the whole card
 * (archive.css .ip-shop-card__link::after), so screen readers hear the name
 * once and the card is one tab stop. "Konfigurieren" is a visual cue only.
 *
 * $args:
 * - id     (int)    Product ID. Required.
 * - level  (string) Heading tag for the name: h2 (listing under an h1), h3 (default) or h4.
 * - sizes  (string) sizes attribute for the image in the caller's layout.
 * - eager  (bool)   true for the one card whose image is the LCP (first card of a listing).
 * - extra  (string) Already-escaped HTML printed at the end of the body (Woo loop hook output).
 * - class  (string) Extra classes on the card.
 */

defined( 'ABSPATH' ) || exit;

$ip_card = wp_parse_args( $args ?? array(), array(
	'id'    => 0,
	'level' => 'h3',
	'sizes' => '(min-width: 1101px) 340px, (min-width: 768px) 33vw, (min-width: 560px) 50vw, 100vw',
	'eager' => false,
	'extra' => '',
	'class' => '',
) );

$ip_id = (int) $ip_card['id'];
if ( ! $ip_id || 'publish' !== get_post_status( $ip_id ) ) {
	return;
}

$ip_level = in_array( $ip_card['level'], array( 'h2', 'h3', 'h4' ), true ) ? $ip_card['level'] : 'h3';
$ip_text  = function_exists( 'ip_shop_card_text' ) ? ip_shop_card_text( $ip_id ) : '';

// The name is the link text right below, so the picture is decorative (alt="").
$ip_attr = array( 'class' => 'ip-shop-card__img', 'alt' => '' );
if ( $ip_card['eager'] ) {
	$ip_attr['loading']       = 'eager';
	$ip_attr['fetchpriority'] = 'high';
}
$ip_thumb = (int) get_post_thumbnail_id( $ip_id );
$ip_img   = $ip_thumb ? ip_img( $ip_thumb, $ip_card['sizes'], $ip_attr, 'medium_large' ) : '';
if ( ! $ip_img && function_exists( 'wc_placeholder_img' ) ) {
	$ip_img = wc_placeholder_img( 'medium', $ip_attr );
}
?>
<article class="ip-shop-card ip-card ip-card--link<?php echo $ip_card['class'] ? ' ' . esc_attr( $ip_card['class'] ) : ''; ?>">
	<div class="ip-shop-card__media">
		<?php echo $ip_img; // phpcs:ignore WordPress.Security.EscapeOutput -- core/WooCommerce image HTML. ?>
	</div>
	<div class="ip-shop-card__body">
		<<?php echo tag_escape( $ip_level ); ?> class="ip-shop-card__title">
			<a class="ip-shop-card__link" href="<?php echo esc_url( get_permalink( $ip_id ) ); ?>"><?php echo esc_html( get_the_title( $ip_id ) ); ?></a>
		</<?php echo tag_escape( $ip_level ); ?>>
		<?php if ( $ip_text ) : ?>
			<p class="ip-shop-card__text"><?php echo esc_html( $ip_text ); ?></p>
		<?php endif; ?>
		<span class="ip-shop-card__cta" aria-hidden="true">
			<?php esc_html_e( 'Konfigurieren', 'infoplus' ); ?>
			<?php echo function_exists( 'ip_shop_arrow' ) ? ip_shop_arrow() : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
		</span>
		<?php if ( $ip_card['extra'] ) : ?>
			<div class="ip-shop-card__extra"><?php echo $ip_card['extra']; // phpcs:ignore WordPress.Security.EscapeOutput -- hook output, escaped by its callbacks. ?></div>
		<?php endif; ?>
	</div>
</article>
