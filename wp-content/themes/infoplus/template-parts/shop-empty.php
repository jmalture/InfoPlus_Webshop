<?php
/**
 * Empty state of a product listing: nothing found for a search, or a
 * category without products. Message, a suggestion, a new search and the
 * way back to all models.
 *
 * $args:
 * - mode (string) 'search' or 'archive'.
 */

defined( 'ABSPATH' ) || exit;

$ip_mode = ( $args['mode'] ?? 'archive' ) === 'search' ? 'search' : 'archive';
?>
<div class="ip-shop-empty ip-card">
	<span class="ip-tile"><?php echo ip_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
	<div class="ip-shop-empty__body">
		<?php if ( 'search' === $ip_mode ) : ?>
			<h2 class="ip-shop-empty__title"><?php echo esc_html( ip_setting( 'search_empty_text' ) ); ?></h2>
			<p class="ip-sub"><?php esc_html_e( 'Prüfen Sie die Schreibweise oder suchen Sie nach einem Modellnamen. Oder sehen Sie sich alle Namensschilder an.', 'infoplus' ); ?></p>
			<?php
			if ( function_exists( 'ip_header_search_form' ) ) {
				ip_header_search_form( 'ip-search-empty' );
			} else {
				get_search_form();
			}
			?>
		<?php else : ?>
			<h2 class="ip-shop-empty__title"><?php esc_html_e( 'Hier gibt es gerade keine Modelle.', 'infoplus' ); ?></h2>
			<p class="ip-sub"><?php esc_html_e( 'Alle verfügbaren Namensschilder finden Sie in der Übersicht.', 'infoplus' ); ?></p>
		<?php endif; ?>
		<p><a class="ip-btn" href="<?php echo esc_url( ip_shop_plates_url() ); ?>"><?php esc_html_e( 'Alle Namensschilder ansehen', 'infoplus' ); ?><?php echo ip_shop_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></a></p>
	</div>
</div>
