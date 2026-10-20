<?php
/**
 * Section head of a product listing (DESIGN.md 2: kicker, heading with a
 * magenta second half, grey subline) with the model count as a pill, and the
 * row of category pills below it (a horizontal swipe row on phones).
 *
 * Used by woocommerce/archive-product.php, page-namensschilder.php and
 * search.php. Texts come from ip_shop_head_data() (inc/shop.php).
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

if ( $ip_data['term'] ) {
	ip_breadcrumb( array(
		array( __( 'Home', 'infoplus' ), home_url( '/' ) ), // Same first crumb as page.php.
		array( __( 'Namensschilder', 'infoplus' ), $ip_all ),
		array( single_term_title( '', false ), '' ),
	) );
}
?>
<header class="ip-shop-head">
	<div class="ip-head">
		<?php if ( $ip_data['kicker'] ) : ?>
			<p class="ip-kicker"><?php echo esc_html( $ip_data['kicker'] ); ?></p>
		<?php endif; ?>
		<h1 class="ip-shop-head__title">
			<?php echo esc_html( $ip_data['title'] ); ?>
			<?php if ( $ip_data['accent'] ) : ?>
				<em><?php echo esc_html( $ip_data['accent'] ); ?></em>
			<?php endif; ?>
		</h1>
		<?php if ( $ip_data['intro'] ) : ?>
			<?php if ( $ip_data['intro_html'] ) : ?>
				<div class="ip-sub ip-shop-head__intro"><?php echo $ip_data['intro']; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_kses_post() in ip_shop_head_data(). ?></div>
			<?php else : ?>
				<p class="ip-sub ip-shop-head__intro"><?php echo esc_html( $ip_data['intro'] ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php if ( 'archive' === $ip_head['mode'] && $ip_data['count'] ) : ?>
		<p class="ip-shop-head__count"><span class="ip-pill"><?php echo esc_html( ip_shop_count_label( $ip_data['count'] ) ); ?></span></p>
	<?php endif; ?>
</header>

<?php if ( count( $ip_terms ) > 1 ) : ?>
	<nav class="ip-chips" aria-label="<?php esc_attr_e( 'Modelle filtern', 'infoplus' ); ?>">
		<ul class="ip-chips__list" role="list">
			<li><a class="ip-chip" href="<?php echo esc_url( $ip_all ); ?>"<?php echo $ip_data['term'] ? '' : ' aria-current="page"'; ?>><?php esc_html_e( 'Alle', 'infoplus' ); ?></a></li>
			<?php foreach ( $ip_terms as $ip_term ) : ?>
				<?php
				$ip_link = get_term_link( $ip_term );
				if ( is_wp_error( $ip_link ) ) {
					continue;
				}
				?>
				<li><a class="ip-chip" href="<?php echo esc_url( $ip_link ); ?>"<?php echo (int) $ip_term->term_id === $ip_data['term'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( ip_shop_short_name( $ip_term->name ) ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</nav>
<?php endif; ?>
