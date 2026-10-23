<?php
/**
 * Account quick menu content (inc/account-menu.php). Printed twice per page
 * at most: in the desktop panel of the header and in the mobile drawer;
 * $args['id'] keeps the IDs apart. The data is built once per request.
 *
 * $args:
 * - id      (string) prefix for IDs
 * - context (string) panel|drawer
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'ip_account_menu_data' ) ) {
	return;
}
$ip_d   = ip_account_menu_data();
$ip_id  = sanitize_html_class( $args['id'] ?? 'ip-acct' );
$ip_ctx = ( $args['context'] ?? 'panel' ) === 'drawer' ? 'drawer' : 'panel';
?>
<div class="ip-acct ip-acct--<?php echo esc_attr( $ip_ctx ); ?>">
	<?php if ( ! $ip_d['logged_in'] ) : ?>
		<p class="ip-acct__intro"><?php esc_html_e( 'Bestellungen, Rechnungen und Adressen an einem Ort. Für Händler und Geschäftskunden.', 'infoplus' ); ?></p>
		<a class="ip-btn ip-acct__login" href="<?php echo esc_url( $ip_d['login'] ); ?>"><?php echo ip_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php esc_html_e( 'Anmelden', 'infoplus' ); ?></a>
		<ul class="ip-acct__links" role="list">
			<?php if ( $ip_d['register'] ) : ?>
				<li><a class="ip-acct__link" href="<?php echo esc_url( $ip_d['register'] ); ?>"><?php echo ip_icon( 'pen' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><span><?php esc_html_e( 'Konto erstellen', 'infoplus' ); ?></span></a></li>
			<?php endif; ?>
			<li><a class="ip-acct__link" href="<?php echo esc_url( $ip_d['lost'] ); ?>"><?php echo ip_icon( 'help' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><span><?php esc_html_e( 'Passwort vergessen', 'infoplus' ); ?></span></a></li>
		</ul>
	<?php else : ?>
		<div class="ip-acct__head">
			<?php // Avatar and name lead to the account overview (Übersicht). ?>
			<a class="ip-acct__me" href="<?php echo esc_url( $ip_d['home'] ); ?>">
				<?php echo function_exists( 'ip_account_avatar' ) ? ip_account_avatar( $ip_d['uid'], 44 ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?>
				<span class="ip-acct__who">
					<span class="ip-acct__name"><?php echo esc_html( $ip_d['name'] ); ?></span>
					<?php if ( $ip_d['company'] ) : ?>
						<span class="ip-acct__company"><?php echo esc_html( $ip_d['company'] ); ?></span>
					<?php endif; ?>
				</span>
			</a>
			<?php if ( $ip_d['status'] ) : ?>
				<p class="ip-acct__status"><span class="ip-sr"><?php esc_html_e( 'Kontostatus:', 'infoplus' ); ?></span><span class="ip-pill"><?php echo esc_html( $ip_d['status'] ); ?></span></p>
			<?php endif; ?>
		</div>

		<section class="ip-acct__orders" aria-labelledby="<?php echo esc_attr( $ip_id ); ?>-orders">
			<h2 class="ip-acct__label" id="<?php echo esc_attr( $ip_id ); ?>-orders"><?php esc_html_e( 'Letzte Bestellungen', 'infoplus' ); ?></h2>
			<?php if ( $ip_d['orders'] ) : ?>
				<ul class="ip-acct__list" role="list">
					<?php foreach ( $ip_d['orders'] as $ip_o ) : ?>
						<li class="ip-acct__order">
							<a class="ip-acct__order-link" href="<?php echo esc_url( $ip_o['url'] ); ?>">
								<span class="ip-acct__order-main">
									<span class="ip-acct__order-no">
										<?php
										/* translators: %s: order number */
										echo esc_html( sprintf( __( 'Bestellung #%s', 'infoplus' ), $ip_o['number'] ) );
										?>
									</span>
									<?php if ( $ip_o['date'] ) : ?>
										<time class="ip-acct__order-date" datetime="<?php echo esc_attr( $ip_o['iso'] ); ?>"><?php echo esc_html( $ip_o['date'] ); ?></time>
									<?php endif; ?>
								</span>
								<span class="ip-acct__order-side">
									<span class="ip-acct__order-total"><?php echo wp_kses_post( $ip_o['total'] ); ?></span>
									<?php echo function_exists( 'ip_account_status_pill' ) ? ip_account_status_pill( $ip_o['order'] ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>
								</span>
							</a>
							<?php if ( $ip_o['invoice'] && ! empty( $ip_o['invoice']['url'] ) ) : ?>
								<?php
								/* translators: %s: order number */
								$ip_label = sprintf( __( 'Rechnung zu Bestellung #%s herunterladen', 'infoplus' ), $ip_o['number'] );
								?>
								<a class="ip-acct__invoice" href="<?php echo esc_url( $ip_o['invoice']['url'] ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( $ip_label ); ?>" title="<?php echo esc_attr( $ip_label ); ?>"><?php echo ip_icon( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<a class="ip-acct__all" href="<?php echo esc_url( $ip_d['all'] ); ?>"><?php esc_html_e( 'Alle Bestellungen', 'infoplus' ); ?><?php echo ip_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></a>
			<?php else : ?>
				<p class="ip-acct__empty"><?php esc_html_e( 'Noch keine Bestellungen.', 'infoplus' ); ?></p>
			<?php endif; ?>
		</section>

		<ul class="ip-acct__links" role="list" aria-label="<?php esc_attr_e( 'Kontobereiche', 'infoplus' ); ?>">
			<?php foreach ( $ip_d['links'] as $ip_l ) : ?>
				<li><a class="ip-acct__link" href="<?php echo esc_url( $ip_l['url'] ); ?>"><?php echo ip_icon( $ip_l['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><span><?php echo esc_html( $ip_l['label'] ); ?></span></a></li>
			<?php endforeach; ?>
			<li class="ip-acct__sep"><a class="ip-acct__link ip-acct__link--logout" href="<?php echo esc_url( $ip_d['logout'] ); ?>"><?php echo ip_icon( 'logout' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><span><?php esc_html_e( 'Abmelden', 'infoplus' ); ?></span></a></li>
		</ul>
	<?php endif; ?>
</div>
