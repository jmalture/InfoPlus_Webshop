<?php
/**
 * Site footer. Content from the old Elementor footer (template 21), layout
 * per DESIGN.md (SDX cues): one #EFEFF6 band with a brand column (logo +
 * trust line), link columns under small kickers, a contact column with icon
 * tiles, then a row of payment / shipping / security logos as white chips;
 * below it the slim magenta bar (the one magenta block, DESIGN.md 10).
 *
 * Every text comes from the options page via ip_setting() (inc/options.php)
 * with the old site's text as fallback, and each block renders only when
 * filled. Menus fall back to the old menu items until the real menus are
 * assigned to their locations at go-live.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Old menu items (wp/menus.json: Footer_Informationen 20, Footer_Rechtliches 21).
 * Page items: [ page ID, label ]; custom items: [ path, label ].
 */
$ip_fallback_menus = array(
	'footer-info'  => array(
		array( 2656, 'Versand/Express und Zahlung' ),
		array( '/kontakt/', 'Kontakt' ),
		array( 2662, 'Unternehmen' ),
		array( 2647, 'Verhaltenskodex' ),
		array( 2667, 'FAQ' ),
	),
	'footer-legal' => array(
		array( 2642, 'Datenschutz' ),
		array( 2637, 'AGB' ),
		array( 203, 'Impressum' ),
	),
);

/** Assigned menu, or the old links as a plain list with the same markup and classes. */
$ip_footer_menu = function ( $location ) use ( $ip_fallback_menus ) {
	if ( has_nav_menu( $location ) ) {
		ip_menu( $location, array( 'menu_class' => 'ip-footer__menu' ) );
		return;
	}
	$items = '';
	foreach ( $ip_fallback_menus[ $location ] as $item ) {
		if ( is_int( $item[0] ) ) {
			if ( 'publish' !== get_post_status( $item[0] ) ) {
				continue;
			}
			$url     = get_permalink( $item[0] );
			$current = is_page( $item[0] );
		} else {
			$url     = home_url( $item[0] );
			$current = false;
		}
		$items .= sprintf(
			'<li class="menu-item%1$s"><a href="%2$s"%3$s>%4$s</a></li>',
			$current ? ' current-menu-item' : '',
			esc_url( $url ),
			$current ? ' aria-current="page"' : '',
			esc_html( $item[1] )
		);
	}
	if ( $items ) {
		echo '<ul class="ip-footer__menu">' . $items . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above.
	}
};

/** Logo chips (payment, shipping, security): each image centred in a white rounded chip. */
$ip_footer_chips = function ( $ids ) {
	$html = '';
	foreach ( $ids as $id ) {
		$img = wp_get_attachment_image( $id, 'full', false, array( 'loading' => 'lazy', 'decoding' => 'async' ) );
		if ( $img ) {
			$html .= '<li class="ip-footer__chip">' . $img . '</li>';
		}
	}
	return $html;
};

/*
 * Trust-line icon. Drawn like the inc/icons.php set (24 grid, 1.75 stroke,
 * currentColor); used from there once a "briefcase" icon is added to it.
 */
$ip_trust_icon = ip_icon( 'briefcase' );
if ( ! $ip_trust_icon ) {
	$ip_trust_icon = '<svg class="ip-icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18"/></svg>';
}

// Brand and columns.
$ip_logo_id     = absint( ip_setting( 'footer_logo' ) );
$ip_trust       = ip_setting( 'footer_legal_note' );
$ip_info_title  = ip_setting( 'footer_info_title' );
$ip_legal_title = ip_setting( 'footer_legal_title' );
$ip_ct_title    = ip_setting( 'footer_contact_title' );
$ip_company     = ip_setting( 'company_name' );
$ip_address     = ip_setting( 'company_address' );
$ip_phone       = ip_setting( 'phone' );
$ip_fax         = ip_setting( 'fax' );
$ip_email       = ip_setting( 'email' );

// Logo chips.
$ip_badges = array();
foreach ( array( 'payment', 'shipping', 'security' ) as $ip_group ) {
	$ip_badges[ $ip_group ] = array(
		'title' => ip_setting( "footer_{$ip_group}_title" ),
		'chips' => $ip_footer_chips( ip_setting_ids( "footer_{$ip_group}_logos" ) ),
	);
}
$ip_btn_label = ip_setting( 'footer_security_button_label' );
$ip_btn_url   = $ip_btn_label ? ip_setting_page_url( 'footer_security_button_page' ) : '';
$ip_btn_show  = $ip_btn_label && $ip_btn_url;

// Bar.
$ip_copyright = trim( str_replace(
	array( '{jahr}', '{firma}' ),
	array( wp_date( 'Y' ), ip_one_line( $ip_company ) ),
	(string) ip_setting( 'footer_copyright' )
) );
$ip_price_note  = ip_setting( 'footer_price_note' );
$ip_credit_text = ip_setting( 'footer_credit_text' );
$ip_credit_name = ip_setting( 'footer_credit_name' );
$ip_credit_url  = ip_setting( 'footer_credit_url' );
$ip_credit_logo = absint( ip_setting( 'footer_credit_logo' ) );

$ip_logo_img = '';
if ( $ip_logo_id ) {
	$ip_logo_alt = trim( (string) get_post_meta( $ip_logo_id, '_wp_attachment_image_alt', true ) );
	$ip_logo_img = ip_img(
		$ip_logo_id,
		'(min-width: 768px) 170px, 150px',
		array( 'alt' => $ip_logo_alt ? $ip_logo_alt : get_bloginfo( 'name' ) ),
		'full'
	);
}
$ip_has_chips = $ip_badges['payment']['chips'] || $ip_badges['shipping']['chips'] || $ip_badges['security']['chips'] || $ip_btn_show;
?>
<footer class="ip-footer">
	<div class="ip-footer__main">
		<div class="ip-footer__inner">
			<div class="ip-footer__grid">

				<?php if ( $ip_logo_img || $ip_trust ) : ?>
					<div class="ip-footer__brand">
						<?php if ( $ip_logo_img ) : ?>
							<a class="ip-footer__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
								<?php echo $ip_logo_img; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image() output. ?>
							</a>
						<?php endif; ?>
						<?php if ( $ip_trust ) : ?>
							<p class="ip-footer__trust">
								<span class="ip-footer__trust-icon"><?php echo $ip_trust_icon; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
								<span><?php echo ip_lines( $ip_trust ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_lines() escapes each line. ?></span>
							</p>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<nav class="ip-footer__col ip-footer__col--info" aria-label="<?php echo esc_attr( $ip_info_title ? $ip_info_title : __( 'Informationen', 'infoplus' ) ); ?>">
					<?php if ( $ip_info_title ) : ?>
						<h2 class="ip-kicker ip-footer__kicker"><?php echo esc_html( $ip_info_title ); ?></h2>
					<?php endif; ?>
					<?php $ip_footer_menu( 'footer-info' ); ?>
				</nav>

				<nav class="ip-footer__col ip-footer__col--legal" aria-label="<?php echo esc_attr( $ip_legal_title ? $ip_legal_title : __( 'Rechtliches', 'infoplus' ) ); ?>">
					<?php if ( $ip_legal_title ) : ?>
						<h2 class="ip-kicker ip-footer__kicker"><?php echo esc_html( $ip_legal_title ); ?></h2>
					<?php endif; ?>
					<?php $ip_footer_menu( 'footer-legal' ); ?>
				</nav>

				<?php if ( $ip_ct_title || $ip_company || $ip_address || $ip_phone || $ip_fax || $ip_email ) : ?>
					<div class="ip-footer__col ip-footer__col--contact">
						<?php if ( $ip_ct_title ) : ?>
							<h2 class="ip-kicker ip-footer__kicker"><?php echo esc_html( $ip_ct_title ); ?></h2>
						<?php endif; ?>
						<address class="ip-footer__contact">
							<?php if ( $ip_company || $ip_address ) : ?>
								<p class="ip-footer__address">
									<?php if ( $ip_company ) : ?>
										<strong><?php echo ip_lines( $ip_company ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped per line. ?></strong><br>
									<?php endif; ?>
									<?php echo ip_lines( $ip_address ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped per line. ?>
								</p>
							<?php endif; ?>

							<?php if ( $ip_phone ) : ?>
								<a class="ip-footer__reach" href="<?php echo esc_url( ip_tel( $ip_phone ) ); ?>">
									<span class="ip-tile"><?php echo ip_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
									<span class="ip-footer__reach-text">
										<span class="ip-footer__reach-label"><?php esc_html_e( 'Telefon', 'infoplus' ); ?></span>
										<span class="ip-footer__reach-value"><?php echo esc_html( $ip_phone ); ?></span>
									</span>
								</a>
							<?php endif; ?>

							<?php if ( $ip_email ) : ?>
								<a class="ip-footer__reach" href="<?php echo esc_url( 'mailto:' . antispambot( $ip_email ) ); ?>">
									<span class="ip-tile"><?php echo ip_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
									<span class="ip-footer__reach-text">
										<span class="ip-footer__reach-label"><?php esc_html_e( 'E-Mail', 'infoplus' ); ?></span>
										<span class="ip-footer__reach-value"><?php echo esc_html( antispambot( $ip_email ) ); ?></span>
									</span>
								</a>
							<?php endif; ?>

							<?php if ( $ip_fax ) : ?>
								<p class="ip-footer__fax"><?php esc_html_e( 'Telefax', 'infoplus' ); ?> <?php echo esc_html( $ip_fax ); ?></p>
							<?php endif; ?>
						</address>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $ip_has_chips ) : ?>
				<div class="ip-footer__badges">
					<?php foreach ( $ip_badges as $ip_group => $ip_badge ) : ?>
						<?php
						$ip_has_btn = ( 'security' === $ip_group && $ip_btn_show );
						if ( ! $ip_badge['chips'] && ! $ip_has_btn ) {
							continue;
						}
						?>
						<div class="ip-footer__group ip-footer__group--<?php echo esc_attr( $ip_group ); ?>">
							<?php if ( $ip_badge['title'] ) : ?>
								<h2 class="ip-kicker ip-footer__kicker"><?php echo esc_html( $ip_badge['title'] ); ?></h2>
							<?php endif; ?>
							<div class="ip-footer__chip-row">
								<?php if ( $ip_badge['chips'] ) : ?>
									<ul class="ip-footer__chips" role="list">
										<?php echo $ip_badge['chips']; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image() output. ?>
									</ul>
								<?php endif; ?>
								<?php if ( $ip_has_btn ) : ?>
									<a class="ip-btn ip-btn--ghost ip-footer__btn" href="<?php echo esc_url( $ip_btn_url ); ?>"><?php echo esc_html( $ip_btn_label ); ?></a>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $ip_copyright || $ip_price_note || $ip_credit_text || $ip_credit_name ) : ?>
		<div class="ip-footer__bar">
			<div class="ip-footer__inner ip-footer__bar-inner">
				<?php if ( $ip_copyright || $ip_price_note ) : ?>
					<p class="ip-footer__legal">
						<?php if ( $ip_copyright ) : ?>
							<span><?php echo esc_html( $ip_copyright ); ?></span>
						<?php endif; ?>
						<?php if ( $ip_price_note ) : ?>
							<span><?php echo esc_html( $ip_price_note ); ?></span>
						<?php endif; ?>
					</p>
				<?php endif; ?>

				<?php if ( $ip_credit_text || $ip_credit_name ) : ?>
					<p class="ip-footer__credit">
						<?php if ( $ip_credit_text ) : ?>
							<span><?php echo esc_html( $ip_credit_text ); ?></span>
						<?php endif; ?>
						<?php if ( $ip_credit_name && $ip_credit_url ) : ?>
							<a href="<?php echo esc_url( $ip_credit_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $ip_credit_name ); ?><span class="ip-sr"> <?php esc_html_e( '(öffnet in neuem Tab)', 'infoplus' ); ?></span></a>
						<?php elseif ( $ip_credit_name ) : ?>
							<span><?php echo esc_html( $ip_credit_name ); ?></span>
						<?php endif; ?>
						<?php
						if ( $ip_credit_logo ) {
							// Shown at 20 x 20 px (CSS).
							echo wp_get_attachment_image( $ip_credit_logo, 'full', false, array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- core output.
						}
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</footer>
