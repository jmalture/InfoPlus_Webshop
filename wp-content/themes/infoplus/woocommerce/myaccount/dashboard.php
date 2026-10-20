<?php
/**
 * My Account dashboard ("Übersicht"): order figures, the latest orders with
 * status and invoice, the default addresses and the latest news. Replaces
 * the "Übersicht" tab of the old Elementor page (UserStatCard, "Letzte
 * Aktivitäten", "Neuigkeiten"); the projects figures there were placeholders
 * and are not shown (see ACCOUNT.md). Theme override.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ip_uid      = get_current_user_id();
$ip_orders   = wc_get_account_endpoint_url( 'orders' );
$ip_recent   = wc_get_orders( array(
	'customer' => $ip_uid,
	'limit'    => 3,
	'orderby'  => 'date',
	'order'    => 'DESC',
) );
$ip_spent    = function_exists( 'wc_get_customer_total_spent' ) ? (float) wc_get_customer_total_spent( $ip_uid ) : 0.0;
$ip_tiles    = array(
	array( 'package', __( 'Bestellungen gesamt', 'infoplus' ), (string) ip_account_order_count(), $ip_orders ),
	array( 'clock', __( 'In Bearbeitung', 'infoplus' ), (string) ip_account_order_count( 'processing' ), add_query_arg( 'status', 'processing', $ip_orders ) ),
	array( 'check', __( 'Abgeschlossen', 'infoplus' ), (string) ip_account_order_count( 'completed' ), add_query_arg( 'status', 'completed', $ip_orders ) ),
	array( 'euro', __( 'Gesamtausgaben', 'infoplus' ), html_entity_decode( wp_strip_all_tags( wc_price( $ip_spent ) ), ENT_QUOTES, 'UTF-8' ), '' ),
);
$ip_billing  = wc_get_account_formatted_address( 'billing' );
$ip_news     = post_type_exists( 'news' ) ? get_posts( array(
	'post_type'        => 'news',
	'numberposts'      => 3,
	'no_found_rows'    => true,
	'suppress_filters' => false,
) ) : array();
?>

<section class="ip-acc-stats" aria-label="<?php esc_attr_e( 'Ihre Bestellungen in Zahlen', 'infoplus' ); ?>">
	<ul class="ip-acc-stats__list" role="list">
		<?php foreach ( $ip_tiles as $ip_tile ) : ?>
			<li class="ip-acc-stat">
				<?php if ( $ip_tile[3] ) : ?>
					<a class="ip-acc-stat__box" href="<?php echo esc_url( $ip_tile[3] ); ?>">
				<?php else : ?>
					<div class="ip-acc-stat__box">
				<?php endif; ?>
					<span class="ip-tile"><?php echo ip_icon( $ip_tile[0] ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
					<span class="ip-acc-stat__text">
						<span class="ip-acc-stat__label"><?php echo esc_html( $ip_tile[1] ); ?></span>
						<span class="ip-acc-stat__value"><?php echo esc_html( $ip_tile[2] ); ?></span>
					</span>
				<?php echo $ip_tile[3] ? '</a>' : '</div>'; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</section>

<div class="ip-acc-dash">
	<section class="ip-acc-card ip-acc-dash__orders" aria-labelledby="ip-acc-recent-title">
		<header class="ip-acc-card__head">
			<h2 class="ip-acc-card__title" id="ip-acc-recent-title"><?php esc_html_e( 'Letzte Bestellungen', 'infoplus' ); ?></h2>
			<?php if ( $ip_recent ) : ?>
				<a class="ip-acc-card__more" href="<?php echo esc_url( $ip_orders ); ?>"><?php esc_html_e( 'Alle Bestellungen', 'infoplus' ); ?><?php echo ip_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></a>
			<?php endif; ?>
		</header>
		<?php if ( $ip_recent ) : ?>
			<ul class="ip-acc-recent" role="list">
				<?php foreach ( $ip_recent as $ip_order ) : ?>
					<?php
					$ip_actions = ip_account_order_actions( $ip_order );
					$ip_count   = $ip_order->get_item_count() - $ip_order->get_item_count_refunded();
					?>
					<li class="ip-acc-recent__item">
						<a class="ip-acc-recent__main" href="<?php echo esc_url( $ip_order->get_view_order_url() ); ?>">
							<span class="ip-acc-recent__no">
								<?php
								/* translators: %s: order number */
								echo esc_html( sprintf( __( 'Bestellung #%s', 'infoplus' ), $ip_order->get_order_number() ) );
								?>
							</span>
							<span class="ip-acc-recent__meta">
								<?php echo esc_html( wc_format_datetime( $ip_order->get_date_created() ) ); ?>
								·
								<?php
								/* translators: %d: number of items */
								echo esc_html( sprintf( _n( '%d Artikel', '%d Artikel', $ip_count, 'infoplus' ), $ip_count ) );
								?>
							</span>
						</a>
						<?php echo ip_account_status_pill( $ip_order ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>
						<span class="ip-acc-recent__total"><?php echo wp_kses_post( $ip_order->get_formatted_order_total() ); ?></span>
						<?php if ( isset( $ip_actions['invoice'] ) ) : ?>
							<span class="ip-acc-recent__action"><?php echo ip_account_action_link( 'invoice', $ip_actions['invoice'], $ip_order ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<div class="ip-acc-empty">
				<p><?php esc_html_e( 'Sie haben noch keine Bestellungen.', 'infoplus' ); ?></p>
				<a class="ip-btn" href="<?php echo esc_url( ip_account_shop_url() ); ?>"><?php esc_html_e( 'Zu den Namensschildern', 'infoplus' ); ?></a>
			</div>
		<?php endif; ?>
	</section>

	<section class="ip-acc-card ip-acc-dash__address" aria-labelledby="ip-acc-address-title">
		<header class="ip-acc-card__head">
			<h2 class="ip-acc-card__title" id="ip-acc-address-title"><?php esc_html_e( 'Rechnungsadresse', 'infoplus' ); ?></h2>
			<a class="ip-acc-card__more" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', 'billing', wc_get_page_permalink( 'myaccount' ) ) ); ?>"><?php echo $ip_billing ? esc_html__( 'Ändern', 'infoplus' ) : esc_html__( 'Hinzufügen', 'infoplus' ); ?><?php echo ip_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></a>
		</header>
		<?php if ( $ip_billing ) : ?>
			<address class="ip-address"><?php echo wp_kses_post( $ip_billing ); ?></address>
		<?php else : ?>
			<p class="ip-muted"><?php esc_html_e( 'Noch keine Rechnungsadresse hinterlegt. Mit Adresse ist die Kasse schneller ausgefüllt.', 'infoplus' ); ?></p>
		<?php endif; ?>
		<p class="ip-acc-dash__links">
			<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>"><?php esc_html_e( 'Kontodaten und Passwort', 'infoplus' ); ?></a>
		</p>
	</section>

	<?php if ( $ip_news ) : ?>
		<section class="ip-acc-card ip-acc-dash__news" aria-labelledby="ip-acc-news-title">
			<header class="ip-acc-card__head">
				<h2 class="ip-acc-card__title" id="ip-acc-news-title"><?php esc_html_e( 'Neuigkeiten', 'infoplus' ); ?></h2>
			</header>
			<ul class="ip-acc-news" role="list">
				<?php foreach ( $ip_news as $ip_post ) : ?>
					<li>
						<a class="ip-acc-news__link" href="<?php echo esc_url( get_permalink( $ip_post ) ); ?>">
							<time datetime="<?php echo esc_attr( get_the_date( 'c', $ip_post ) ); ?>"><?php echo esc_html( get_the_date( 'j. F Y', $ip_post ) ); ?></time>
							<span><?php echo esc_html( get_the_title( $ip_post ) ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
</div>

<?php
/**
 * My Account dashboard.
 *
 * @since 2.6.0
 */
do_action( 'woocommerce_account_dashboard' );

/**
 * Deprecated woocommerce_before_my_account action.
 *
 * @deprecated 2.6.0
 */
do_action( 'woocommerce_before_my_account' );

/**
 * Deprecated woocommerce_after_my_account action.
 *
 * @deprecated 2.6.0
 */
do_action( 'woocommerce_after_my_account' );
