<?php
/**
 * "FAQ" page (/faq/, page 2667), picked by slug.
 *
 * Questions and answers are a repeater of the field group "FAQ"
 * (acf-json/group_ip_faq.json), defaults = the 19 entries of the old
 * Elementor accordion. Native <details>/<summary>: works without JS, opens
 * with the browser's find-in-page, keyboard accessible by default.
 * Structured data: FAQPage JSON-LD built from the same rows.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$ip_pid = (int) get_queried_object_id();
$ip_f   = function ( $name, $default = '' ) use ( $ip_pid ) {
	return ip_field( $name, $ip_pid, $default );
};

$ip_title = (string) $ip_f( 'title' );
$ip_items = array_values( array_filter( ip_rows( $ip_f( 'items', array() ) ), function ( $r ) {
	return '' !== trim( (string) ( $r['question'] ?? '' ) ) && '' !== trim( wp_strip_all_tags( (string) ( $r['answer'] ?? '' ) ) );
} ) );
$ip_ct_title = (string) $ip_f( 'contact_title' );
$ip_ct_rows  = $ip_ct_title ? ip_contact_rows( array( 'phone', 'email', 'hours' ) ) : array();
?>
<div class="ip-content ip-faq">
	<div class="ip-shell">
		<?php ip_breadcrumb( array( array( __( 'Home', 'infoplus' ), home_url( '/' ) ), array( get_the_title(), '' ) ) ); ?>

		<div class="ip-page-head">
			<?php
			ip_section_head( array(
				'kicker' => (string) $ip_f( 'kicker' ),
				'title'  => $ip_title ? $ip_title : get_the_title(),
				'text'   => (string) $ip_f( 'intro' ),
				'tag'    => 'h1',
			) );
			?>
		</div>

		<div class="ip-faq__grid<?php echo $ip_ct_rows ? ' has-aside' : ''; ?>">
			<?php if ( $ip_items ) : ?>
				<div class="ip-accordion">
					<?php foreach ( $ip_items as $ip_i => $ip_row ) : ?>
						<details class="ip-accordion__item" id="<?php echo esc_attr( 'frage-' . ( $ip_i + 1 ) ); ?>">
							<summary class="ip-accordion__q">
								<span><?php echo esc_html( $ip_row['question'] ); ?></span>
								<span class="ip-accordion__icon" aria-hidden="true"><?php echo ip_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
							</summary>
							<div class="ip-accordion__a ip-prose"><?php echo ip_rich( $ip_row['answer'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_rich() runs wp_kses. ?></div>
						</details>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $ip_ct_rows ) : ?>
				<aside class="ip-faq__aside ip-card" aria-labelledby="ip-faq-contact-title">
					<h2 class="ip-faq__aside-title" id="ip-faq-contact-title"><?php echo esc_html( $ip_ct_title ); ?></h2>
					<?php echo ip_paras( $ip_f( 'contact_text' ), 'ip-sub' ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_paras() escapes. ?>
					<div class="ip-reach-list">
						<?php array_map( 'ip_contact_row', $ip_ct_rows ); ?>
					</div>
				</aside>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php
if ( $ip_items ) {
	$ip_ld = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => array(),
	);
	foreach ( $ip_items as $ip_row ) {
		$ip_ld['mainEntity'][] = array(
			'@type'          => 'Question',
			'name'           => wp_strip_all_tags( $ip_row['question'] ),
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( ip_rich( $ip_row['answer'] ) ) ) ),
			),
		);
	}
	// JSON_HEX_TAG keeps "</script>" in an answer from closing the tag.
	echo '<script type="application/ld+json">' . wp_json_encode( $ip_ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON encoded.
}
get_footer();
