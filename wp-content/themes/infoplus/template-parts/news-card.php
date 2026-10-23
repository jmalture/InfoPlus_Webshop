<?php
/**
 * News card ("Weitere News" under an article). Port of Elementor loop item
 * 13365 (featured image, title, date) in the DESIGN.md card style.
 * One link per card: the title link is stretched over the card (news.css).
 *
 * $args:
 * - id    (int)    News post ID. Required.
 * - level (string) Heading tag for the title: h3 (default) or h4.
 */

defined( 'ABSPATH' ) || exit;

$ip_nc = wp_parse_args( $args ?? array(), array( 'id' => 0, 'level' => 'h3' ) );
$ip_id = (int) $ip_nc['id'];
if ( ! $ip_id || 'publish' !== get_post_status( $ip_id ) ) {
	return;
}
$ip_level = in_array( $ip_nc['level'], array( 'h3', 'h4' ), true ) ? $ip_nc['level'] : 'h3';
$ip_thumb = (int) get_post_thumbnail_id( $ip_id );
?>
<article class="ip-news-card ip-card ip-card--link">
	<div class="ip-news-card__media">
		<?php
		// Decorative: the title right below names the card.
		echo $ip_thumb ? ip_img( $ip_thumb, '(min-width: 1101px) 440px, (min-width: 768px) 33vw, 84vw', array( 'alt' => '' ), 'ip-800' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- core image HTML.
		?>
	</div>
	<div class="ip-news-card__body">
		<time class="ip-news-card__date" datetime="<?php echo esc_attr( get_the_date( 'c', $ip_id ) ); ?>"><?php echo esc_html( get_the_date( 'j. F Y', $ip_id ) ); ?></time>
		<<?php echo tag_escape( $ip_level ); ?> class="ip-news-card__title">
			<a class="ip-news-card__link" href="<?php echo esc_url( get_permalink( $ip_id ) ); ?>"><?php echo esc_html( get_the_title( $ip_id ) ); ?></a>
		</<?php echo tag_escape( $ip_level ); ?>>
	</div>
</article>
