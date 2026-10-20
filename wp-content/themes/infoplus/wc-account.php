<?php
/**
 * Customer account and login (pages "account" 12466 and "log-in" 12381 on
 * staging, found by inc/account.php, not by template name).
 *
 * Layout like the cart and checkout (wc-page.php): an #EFEFF6 band, the
 * section head (kicker, H1, subline), then the content. Logged in, the
 * WooCommerce My Account shortcode renders woocommerce/myaccount/my-account.php
 * (profile card and menu beside the endpoint content). Login page and
 * guests get the login, lost password or reset form in a narrow column.
 *
 * Elementor content stored on these pages is not used.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$ip_head     = ip_account_head();
$ip_is_login = ip_account_is_login_page();
$ip_guest    = ! is_user_logged_in();

while ( have_posts() ) :
	the_post();
	?>
	<div class="ip-acc<?php echo $ip_guest ? ' ip-acc--guest' : ''; ?>">
		<div class="ip-shell">
			<header class="ip-acc__head">
				<div class="ip-head">
					<p class="ip-kicker"><?php echo esc_html( $ip_head['kicker'] ); ?></p>
					<h1 class="ip-acc__title"><?php echo esc_html( $ip_head['title'] ); ?></h1>
					<?php if ( $ip_head['sub'] ) : ?>
						<p class="ip-sub"><?php echo esc_html( $ip_head['sub'] ); ?></p>
					<?php endif; ?>
				</div>
			</header>

			<div class="ip-acc__body">
				<?php
				if ( $ip_is_login && function_exists( 'wc_get_template' ) ) {
					// Login page: WooCommerce processes the forms itself (WC_Form_Handler on wp_loaded).
					echo '<div class="woocommerce">';
					wc_print_notices();
					wc_get_template( 'myaccount/form-login.php' );
					echo '</div>';
				} else {
					echo do_shortcode( '[woocommerce_my_account]' );
				}
				?>
			</div>
		</div>
	</div>
	<?php
endwhile;

get_footer();
