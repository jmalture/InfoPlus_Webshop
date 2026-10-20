<?php
/**
 * Section head of a product listing (DESIGN.md 2: kicker, heading with a
 * magenta second half, grey subline) with the model count as a pill, and the
 * row of category pills below it (a horizontal swipe row on phones).
 *
 * Used by woocommerce/archive-product.php, page-namensschilder.php and
 * search.php. Texts come from ip_shop_head_data() (inc/shop.php).
 *
 * Where the pills filter in place (ip_catalog_context(), inc/catalog.php) the
 * head carries data-ip-* hooks for assets/js/archive.js, always renders the
 * breadcrumb and the intro box (so a swap never adds or removes a row), makes
 * the count a polite live region, and prints the texts of every pill as JSON.
 *
 * $args:
 * - mode  (string) 'archive' or 'search'.
 * - total (int)    Number of products found.
 * - pills (bool)   Show the category pills (archive views only).
 */

defined( 'ABSPATH' ) || exit;

$ip_head = wp_parse_args( $args ?? array(), array(
	'mode'  => 'archive',
	'total' => 0,
	'pills' => true,
) );

$ip_data  = ip_shop_head_data( $ip_head['mode'], (int) $ip_head['total'] );
$ip_terms = $ip_head['pills'] ? ip_shop_category_terms() : array();
$ip_all   = ip_shop_plates_url();
$ip_ctx   = ( 'archive' === $ip_head['mode'] && $ip_head['pills'] && function_exists( 'ip_catalog_context' ) ) ? ip_catalog_context() : null;

if ( $ip_data['term'] ) {
	ip_breadcrumb( array(
		array( __( 'Home', 'infoplus' ), home_url( '/' ) ), // Same first crumb as page.php.
		array( __( 'Namensschilder', 'infoplus' ), $ip_all ),
		array( single_term_title( '', false ), '' ),
	) );
} elseif ( $ip_ctx ) {
	// Same row as on a category page, so switching pills never moves the heading.
	ip_breadcrumb( array(
		array( __( 'Home', 'infoplus' ), home_url( '/' ) ),
		array( __( 'Namensschilder', 'infoplus' ), '' ),
	) );
}
?>
<header class="ip-shop-head"<?php echo $ip_ctx ? ' data-ip-filter-head' : ''; ?>>
	<div class="ip-head">
		<?php if ( $ip_data['kicker'] ) : ?>
			<p class="ip-kicker"><?php echo esc_html( $ip_data['kicker'] ); ?></p>
		<?php endif; ?>
		<h1 class="ip-shop-head__title"<?php echo $ip_ctx ? ' data-ip-filter-title' : ''; ?>>
			<?php echo esc_html( $ip_data['title'] ); ?>
			<?php if ( $ip_data['accent'] ) : ?>
				<em><?php echo esc_html( $ip_data['accent'] ); ?></em>
			<?php endif; ?>
		</h1>
		<?php if ( $ip_ctx ) : ?>
			<?php
			// One box for both kinds of intro (plain setting text, term description HTML).
			$ip_intro = $ip_data['intro_html'] ? $ip_data['intro'] : ( '' === trim( $ip_data['intro'] ) ? '' : '<p>' . esc_html( $ip_data['intro'] ) . '</p>' );
			?>
			<div class="ip-sub ip-shop-head__intro" data-ip-filter-intro<?php echo '' === trim( wp_strip_all_tags( $ip_intro ) ) ? ' hidden' : ''; ?>><?php echo $ip_intro; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above or wp_kses_post() in ip_shop_head_data(). ?></div>
		<?php elseif ( $ip_data['intro'] ) : ?>
			<?php if ( $ip_data['intro_html'] ) : ?>
				<div class="ip-sub ip-shop-head__intro"><?php echo $ip_data['intro']; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_kses_post() in ip_shop_head_data(). ?></div>
			<?php else : ?>
				<p class="ip-sub ip-shop-head__intro"><?php echo esc_html( $ip_data['intro'] ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php if ( 'archive' === $ip_head['mode'] && $ip_data['count'] ) : ?>
		<p class="ip-shop-head__count"<?php echo $ip_ctx ? ' role="status" data-ip-filter-count' : ''; ?>><span class="ip-pill"><?php echo esc_html( ip_shop_count_label( $ip_data['count'] ) ); ?></span></p>
	<?php endif; ?>
</header>

<?php if ( count( $ip_terms ) > 1 ) : ?>
	<nav class="ip-chips" aria-label="<?php esc_attr_e( 'Modelle filtern', 'infoplus' ); ?>"<?php echo $ip_ctx ? ' data-ip-filter="' . esc_attr( $ip_ctx['active'] ) . '"' : ''; ?>>
		<ul class="ip-chips__list" role="list">
			<li><a class="ip-chip" href="<?php echo esc_url( $ip_all ); ?>" data-ip-term=""<?php echo $ip_data['term'] ? '' : ' aria-current="page"'; ?>><?php esc_html_e( 'Alle', 'infoplus' ); ?></a></li>
			<?php foreach ( $ip_terms as $ip_term ) : ?>
				<?php
				$ip_link = get_term_link( $ip_term );
				if ( is_wp_error( $ip_link ) ) {
					continue;
				}
				?>
				<li><a class="ip-chip" href="<?php echo esc_url( $ip_link ); ?>" data-ip-term="<?php echo esc_attr( $ip_term->slug ); ?>"<?php echo (int) $ip_term->term_id === $ip_data['term'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( ip_shop_short_name( $ip_term->name ) ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php if ( $ip_ctx ) : ?>
		<script type="application/json" id="ip-catalog-data"><?php echo wp_json_encode( ip_catalog_data(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
	<?php endif; ?>
<?php endif; ?>
