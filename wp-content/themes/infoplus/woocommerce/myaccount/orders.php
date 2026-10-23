<?php
/**
 * Orders list: status filter (server side, links), a quick search over the
 * listed orders (assets/js/account.js, hidden without JS), then a table that
 * becomes one card per order below 768 px. Columns, actions (invoice from
 * the PDF plugin, "Erneut bestellen" where safe) and their hooks come from
 * WooCommerce, so plugins keep adding to them. Theme override.
 *
 * Replaces the UserOrdersGrid widget of the old "Bestellungen" tab (search,
 * status filter, "Bestellung ansehen").
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.5.0
 */

defined( 'ABSPATH' ) || exit;

$ip_status   = ip_account_current_status_filter();
$ip_base     = wc_get_account_endpoint_url( 'orders' );
$ip_filters  = array( '' => __( 'Alle', 'infoplus' ) ) + ip_account_status_filters();
$ip_columns  = wc_get_account_orders_columns();
$ip_page_url = function ( $page ) use ( $ip_status ) {
	$url = wc_get_endpoint_url( 'orders', $page > 1 ? (string) $page : '' );
	return $ip_status ? add_query_arg( 'status', $ip_status, $url ) : $url;
};

do_action( 'woocommerce_before_account_orders', $has_orders ); ?>

<?php if ( $has_orders || $ip_status ) : ?>
	<div class="ip-acc-toolbar">
		<nav class="ip-acc-filter" aria-label="<?php esc_attr_e( 'Nach Status filtern', 'infoplus' ); ?>">
			<ul role="list">
				<?php foreach ( $ip_filters as $ip_key => $ip_label ) : ?>
					<?php $ip_count = ip_account_order_count( $ip_key ); ?>
					<li>
						<a class="ip-acc-filter__link" href="<?php echo esc_url( $ip_key ? add_query_arg( 'status', $ip_key, $ip_base ) : $ip_base ); ?>"<?php echo $ip_key === $ip_status ? ' aria-current="page"' : ''; ?>>
							<?php echo esc_html( $ip_label ); ?>
							<span class="ip-acc-filter__count"><?php echo esc_html( $ip_count ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php if ( $has_orders ) : ?>
			<div class="ip-acc-search ip-js-only">
				<label class="ip-sr" for="ip-orders-search"><?php esc_html_e( 'Bestellungen durchsuchen', 'infoplus' ); ?></label>
				<?php echo ip_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
				<input type="search" id="ip-orders-search" class="ip-acc-search__input" placeholder="<?php esc_attr_e( 'Nummer, Referenz, Datum …', 'infoplus' ); ?>" autocomplete="off" aria-describedby="ip-orders-result" data-ip-orders-search>
			</div>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if ( $has_orders ) : ?>

	<p class="ip-sr" id="ip-orders-result" role="status" aria-live="polite" data-ip-orders-result></p>

	<div class="ip-acc-card ip-acc-card--flush">
		<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table ip-orders">
			<thead>
				<tr>
					<?php foreach ( $ip_columns as $column_id => $column_name ) : ?>
						<th scope="col" class="woocommerce-orders-table__header woocommerce-orders-table__header-<?php echo esc_attr( $column_id ); ?>"><span class="nobr"><?php echo esc_html( $column_name ); ?></span></th>
					<?php endforeach; ?>
				</tr>
			</thead>

			<tbody>
				<?php
				foreach ( $customer_orders->orders as $customer_order ) {
					$order      = wc_get_order( $customer_order ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					$item_count = $order->get_item_count() - $order->get_item_count_refunded();
					?>
					<tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr( $order->get_status() ); ?> order" data-ip-order-row>
						<?php
						foreach ( $ip_columns as $column_id => $column_name ) :
							$is_order_number = 'order-number' === $column_id;
							?>
							<?php if ( $is_order_number ) : ?>
								<th class="woocommerce-orders-table__cell woocommerce-orders-table__cell-<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>" scope="row">
							<?php else : ?>
								<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>">
							<?php endif; ?>

								<?php if ( has_action( 'woocommerce_my_account_my_orders_column_' . $column_id ) ) : ?>
									<?php do_action( 'woocommerce_my_account_my_orders_column_' . $column_id, $order ); ?>

								<?php elseif ( $is_order_number ) : ?>
									<a class="ip-orders__number" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
										<?php
										/* translators: %s: order number */
										echo esc_html( sprintf( __( '#%s', 'infoplus' ), $order->get_order_number() ) );
										?>
									</a>

								<?php elseif ( 'order-date' === $column_id ) : ?>
									<time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></time>

								<?php elseif ( 'order-status' === $column_id ) : ?>
									<?php echo ip_account_status_pill( $order ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>

								<?php elseif ( 'order-total' === $column_id ) : ?>
									<span class="ip-orders__total"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
									<span class="ip-orders__count">
										<?php
										/* translators: %d: number of items */
										echo esc_html( sprintf( _n( '%d Artikel', '%d Artikel', $item_count, 'infoplus' ), $item_count ) );
										?>
									</span>

								<?php elseif ( 'order-actions' === $column_id ) : ?>
									<span class="ip-orders__actions">
										<?php
										$actions = wc_get_account_orders_actions( $order );
										if ( isset( $actions['view'] ) ) {
											// "Ansehen" first; the rest (pay, invoice, reorder, cancel, plugins) in their order.
											$actions = array( 'view' => $actions['view'] ) + $actions;
										}
										foreach ( $actions as $key => $action ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
											echo ip_account_action_link( $key, $action, $order, 'pay' === $key ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper.
										}
										?>
									</span>
								<?php endif; ?>

							<?php if ( $is_order_number ) : ?>
								</th>
							<?php else : ?>
								</td>
							<?php endif; ?>
						<?php endforeach; ?>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<p class="ip-acc-noresult" hidden data-ip-orders-none><?php esc_html_e( 'Keine Bestellung passt zu Ihrer Suche.', 'infoplus' ); ?></p>
	</div>

	<?php do_action( 'woocommerce_before_account_orders_pagination' ); ?>

	<?php if ( 1 < $customer_orders->max_num_pages ) : ?>
		<nav class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination ip-acc-pager" aria-label="<?php esc_attr_e( 'Seiten', 'infoplus' ); ?>">
			<?php if ( 1 !== $current_page ) : ?>
				<a class="ip-btn ip-btn--ghost ip-btn--sm woocommerce-button--previous" href="<?php echo esc_url( $ip_page_url( $current_page - 1 ) ); ?>"><?php echo ip_icon( 'arrow-left' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><span><?php esc_html_e( 'Neuere', 'infoplus' ); ?></span></a>
			<?php endif; ?>
			<span class="ip-acc-pager__info">
				<?php
				/* translators: 1: current page, 2: number of pages */
				echo esc_html( sprintf( __( 'Seite %1$d von %2$d', 'infoplus' ), $current_page, $customer_orders->max_num_pages ) );
				?>
			</span>
			<?php if ( intval( $customer_orders->max_num_pages ) !== $current_page ) : ?>
				<a class="ip-btn ip-btn--ghost ip-btn--sm woocommerce-button--next" href="<?php echo esc_url( $ip_page_url( $current_page + 1 ) ); ?>"><span><?php esc_html_e( 'Ältere', 'infoplus' ); ?></span><?php echo ip_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>

<?php else : ?>

	<div class="ip-acc-card ip-acc-empty">
		<span class="ip-tile"><?php echo ip_icon( 'package' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
		<p>
			<?php
			echo $ip_status
				? esc_html__( 'Keine Bestellungen mit diesem Status.', 'infoplus' )
				: esc_html__( 'Sie haben noch keine Bestellungen.', 'infoplus' );
			?>
		</p>
		<?php if ( $ip_status ) : ?>
			<a class="ip-btn ip-btn--ghost" href="<?php echo esc_url( $ip_base ); ?>"><?php esc_html_e( 'Alle Bestellungen zeigen', 'infoplus' ); ?></a>
		<?php else : ?>
			<a class="ip-btn" href="<?php echo esc_url( ip_account_shop_url() ); ?>"><?php esc_html_e( 'Zu den Namensschildern', 'infoplus' ); ?></a>
		<?php endif; ?>
	</div>

<?php endif; ?>

<?php do_action( 'woocommerce_after_account_orders', $has_orders ); ?>
