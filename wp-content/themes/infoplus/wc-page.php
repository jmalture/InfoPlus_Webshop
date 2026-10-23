<?php
/**
 * Cart and checkout (pages 27 and 28, found through the WooCommerce page
 * settings by the template_include filter in inc/shop.php, not by slug).
 *
 * Both are WooCommerce blocks. Layout: an #EFEFF6 band, the section head
 * with a three-step indicator, then the block full width in the shell; the
 * block's two columns become white cards (assets/css/checkout.css). How the
 * block HTML is produced (the_content() vs. block markup for the Elementor-
 * built checkout page) is explained at ip_shop_wc_page_html().
 *
 * Order received / order pay are endpoints of the checkout page: same frame,
 * step 3. Order received renders the theme's woocommerce/checkout/thankyou.php
 * (class ip-wc--received, styles in assets/css/order.css); the Infoplus Suite
 * thank-you block is switched off there (inc/account.php).
 */

defined( 'ABSPATH' ) || exit;

get_header();

$ip_is_cart     = function_exists( 'is_cart' ) && is_cart();
$ip_endpoint    = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url();
$ip_is_received = $ip_endpoint && is_wc_endpoint_url( 'order-received' );
$ip_step        = $ip_is_cart ? 1 : ( $ip_is_received ? 3 : 2 );
$ip_steps       = array(
	1 => array( __( 'Warenkorb', 'infoplus' ), function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '' ),
	2 => array( __( 'Kasse', 'infoplus' ), '' ),
	3 => array( __( 'Bestätigung', 'infoplus' ), '' ),
);

while ( have_posts() ) :
	the_post();
	?>
	<div class="ip-wc ip-wc--<?php echo $ip_is_cart ? 'cart' : 'checkout'; ?><?php echo $ip_endpoint ? ' ip-wc--endpoint' : ''; ?><?php echo $ip_is_received && function_exists( 'ip_account_owns_thankyou' ) && ip_account_owns_thankyou() ? ' ip-wc--received' : ''; ?>">
		<div class="ip-shell">
			<header class="ip-wc__head">
				<div class="ip-head">
					<p class="ip-kicker"><?php esc_html_e( 'Ihre Bestellung', 'infoplus' ); ?></p>
					<h1 class="ip-wc__title"><?php echo esc_html( ip_shop_wc_page_title() ); ?></h1>
				</div>
				<ol class="ip-steps" aria-label="<?php esc_attr_e( 'Bestellschritte', 'infoplus' ); ?>">
					<?php foreach ( $ip_steps as $ip_n => $ip_s ) : ?>
						<li class="ip-steps__item<?php echo $ip_n < $ip_step ? ' is-done' : ''; ?>"<?php echo $ip_n === $ip_step ? ' aria-current="step"' : ''; ?>>
							<span class="ip-steps__num" aria-hidden="true"><?php echo (int) $ip_n; ?></span>
							<?php if ( $ip_s[1] && $ip_n < $ip_step && 3 !== $ip_step ) : ?>
								<a class="ip-steps__label" href="<?php echo esc_url( $ip_s[1] ); ?>"><?php echo esc_html( $ip_s[0] ); ?></a>
							<?php else : ?>
								<span class="ip-steps__label"><?php echo esc_html( $ip_s[0] ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
			</header>

			<div class="ip-wc__body">
				<?php echo ip_shop_wc_page_html( $ip_is_cart ? 'cart' : 'checkout' ); // phpcs:ignore WordPress.Security.EscapeOutput -- rendered blocks/shortcodes. ?>
			</div>
		</div>
	</div>
	<?php
endwhile;

get_footer();
