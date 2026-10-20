<?php
/**
 * Site footer, ported 1:1 from Elementor template 21 "infoplus_footer".
 *
 * Structure (Elementor container in brackets):
 * - grey main area [5139926]: logo [0ac2d65] + area [ac371b7] holding
 *   - three columns [68f7414]: Informationen menu, Rechtliches menu + B2B note,
 *     Kontakt (company, address, phone/fax/e-mail);
 *   - badge row [f733e91]: Zahlungsarten logos, Versand logo,
 *     Sicherheit logos + "Datenschutz" button.
 * - magenta bar [9fbcba0]: copyright, agency credit, price note.
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

/** Logo row (payment, shipping, security): attachment IDs as a list of images. */
$ip_footer_logos = function ( $ids ) {
	$html = '';
	foreach ( $ids as $id ) {
		$img = wp_get_attachment_image( $id, 'full', false, array( 'loading' => 'lazy', 'decoding' => 'async' ) );
		if ( $img ) {
			$html .= '<li>' . $img . '</li>';
		}
	}
	return $html;
};

// Main area values.
$ip_logo_id     = absint( ip_setting( 'footer_logo' ) );
$ip_info_title  = ip_setting( 'footer_info_title' );
$ip_legal_title = ip_setting( 'footer_legal_title' );
$ip_legal_note  = ip_setting( 'footer_legal_note' );
$ip_ct_title    = ip_setting( 'footer_contact_title' );
$ip_company     = ip_setting( 'company_name' );
$ip_address     = ip_setting( 'company_address' );
$ip_phone       = ip_setting( 'phone' );
$ip_fax         = ip_setting( 'fax' );
$ip_email       = ip_setting( 'email' );

// Badge row values.
$ip_badges = array();
foreach ( array( 'payment', 'shipping', 'security' ) as $ip_group ) {
	$ip_badges[ $ip_group ] = array(
		'title' => ip_setting( "footer_{$ip_group}_title" ),
		'logos' => $ip_footer_logos( ip_setting_ids( "footer_{$ip_group}_logos" ) ),
	);
}
$ip_btn_label = ip_setting( 'footer_security_button_label' );
$ip_btn_page  = absint( ip_setting( 'footer_security_button_page' ) );
$ip_btn_url   = ( $ip_btn_page && 'publish' === get_post_status( $ip_btn_page ) ) ? get_permalink( $ip_btn_page ) : '';
$ip_btn_show  = $ip_btn_label && $ip_btn_url;

// Bar values.
$ip_copyright = str_replace(
	array( '{jahr}', '{firma}' ),
	array( wp_date( 'Y' ), ip_one_line( $ip_company ) ),
	(string) ip_setting( 'footer_copyright' )
);
$ip_credit_text = ip_setting( 'footer_credit_text' );
$ip_credit_name = ip_setting( 'footer_credit_name' );
$ip_credit_url  = ip_setting( 'footer_credit_url' );
$ip_credit_logo = absint( ip_setting( 'footer_credit_logo' ) );
$ip_price_note  = ip_setting( 'footer_price_note' );
?>
<footer class="ip-footer">
	<div class="ip-footer__main">
		<div class="ip-footer__inner ip-footer__top">

			<?php if ( $ip_logo_id ) : ?>
				<?php
				$ip_logo_alt = trim( (string) get_post_meta( $ip_logo_id, '_wp_attachment_image_alt', true ) );
				$ip_logo_img = ip_img(
					$ip_logo_id,
					'(min-width: 1101px) 190px, 150px',
					array( 'alt' => $ip_logo_alt ? $ip_logo_alt : get_bloginfo( 'name' ) ),
					'full'
				);
				?>
				<?php if ( $ip_logo_img ) : ?>
					<a class="ip-footer__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
						<?php echo $ip_logo_img; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image() output. ?>
					</a>
				<?php endif; ?>
			<?php endif; ?>

			<div class="ip-footer__area">
				<div class="ip-footer__cols">
					<div class="ip-footer__col">
						<?php if ( $ip_info_title ) : ?>
							<h2 class="ip-footer__title"><?php echo esc_html( $ip_info_title ); ?></h2>
						<?php endif; ?>
						<nav class="ip-footer__nav" aria-label="<?php esc_attr_e( 'Informationen', 'infoplus' ); ?>">
							<?php $ip_footer_menu( 'footer-info' ); ?>
						</nav>
					</div>

					<div class="ip-footer__col">
						<?php if ( $ip_legal_title ) : ?>
							<h2 class="ip-footer__title"><?php echo esc_html( $ip_legal_title ); ?></h2>
						<?php endif; ?>
						<nav class="ip-footer__nav" aria-label="<?php esc_attr_e( 'Rechtliches', 'infoplus' ); ?>">
							<?php $ip_footer_menu( 'footer-legal' ); ?>
						</nav>
						<?php if ( $ip_legal_note ) : ?>
							<p class="ip-footer__text"><?php echo ip_lines( $ip_legal_note ); // phpcs:ignore WordPress.Security.EscapeOutput -- ip_lines() escapes each line. ?></p>
						<?php endif; ?>
					</div>

					<?php if ( $ip_ct_title || $ip_company || $ip_address || $ip_phone || $ip_fax || $ip_email ) : ?>
						<div class="ip-footer__col ip-footer__col--contact">
							<?php if ( $ip_ct_title ) : ?>
								<h2 class="ip-footer__title"><?php echo esc_html( $ip_ct_title ); ?></h2>
							<?php endif; ?>
							<address class="ip-footer__contact">
								<?php if ( $ip_company ) : ?>
									<p class="ip-footer__company"><?php echo ip_lines( $ip_company ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped per line. ?></p>
								<?php endif; ?>
								<?php if ( $ip_address ) : ?>
									<p><?php echo ip_lines( $ip_address ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped per line. ?></p>
								<?php endif; ?>
								<?php if ( $ip_phone || $ip_fax || $ip_email ) : ?>
									<p>
										<?php
										$ip_rows = array();
										if ( $ip_phone ) {
											$ip_rows[] = esc_html__( 'Telefon:', 'infoplus' ) . ' <a href="' . esc_url( ip_tel( $ip_phone ) ) . '">' . esc_html( $ip_phone ) . '</a>';
										}
										if ( $ip_fax ) {
											$ip_rows[] = esc_html__( 'Telefax:', 'infoplus' ) . ' ' . esc_html( $ip_fax );
										}
										if ( $ip_email ) {
											$ip_rows[] = esc_html__( 'E-Mail:', 'infoplus' ) . ' <a href="' . esc_url( 'mailto:' . antispambot( $ip_email ) ) . '">' . esc_html( antispambot( $ip_email ) ) . '</a>';
										}
										echo implode( '<br>', $ip_rows ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
										?>
									</p>
								<?php endif; ?>
							</address>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( $ip_badges['payment']['logos'] || $ip_badges['shipping']['logos'] || $ip_badges['security']['logos'] || $ip_btn_show ) : ?>
					<div class="ip-footer__badges">
						<?php foreach ( $ip_badges as $ip_group => $ip_badge ) : ?>
							<?php
							$ip_has_btn = ( 'security' === $ip_group && $ip_btn_show );
							if ( ! $ip_badge['logos'] && ! $ip_has_btn ) {
								continue;
							}
							?>
							<div class="ip-footer__group ip-footer__group--<?php echo esc_attr( $ip_group ); ?>">
								<?php if ( $ip_badge['title'] ) : ?>
									<h2 class="ip-footer__title"><?php echo esc_html( $ip_badge['title'] ); ?></h2>
								<?php endif; ?>
								<ul class="ip-footer__logos" role="list">
									<?php echo $ip_badge['logos']; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image() output. ?>
									<?php if ( $ip_has_btn ) : ?>
										<li><a class="ip-footer__btn" href="<?php echo esc_url( $ip_btn_url ); ?>"><?php echo esc_html( $ip_btn_label ); ?></a></li>
									<?php endif; ?>
								</ul>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php if ( trim( $ip_copyright ) || $ip_credit_text || $ip_credit_name || $ip_price_note ) : ?>
		<div class="ip-footer__bar">
			<div class="ip-footer__inner ip-footer__bar-inner">
				<?php if ( trim( $ip_copyright ) ) : ?>
					<p class="ip-footer__copyright"><?php echo esc_html( $ip_copyright ); ?></p>
				<?php endif; ?>

				<?php if ( $ip_credit_text || $ip_credit_name ) : ?>
					<p class="ip-footer__credit">
						<?php if ( $ip_credit_text ) : ?>
							<span><?php echo esc_html( $ip_credit_text ); ?></span>
						<?php endif; ?>
						<?php if ( $ip_credit_name && $ip_credit_url ) : ?>
							<a href="<?php echo esc_url( $ip_credit_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $ip_credit_name ); ?></a>
						<?php elseif ( $ip_credit_name ) : ?>
							<span><?php echo esc_html( $ip_credit_name ); ?></span>
						<?php endif; ?>
						<?php
						if ( $ip_credit_logo ) {
							// Shown at 26 x 26 px (CSS), as in the old text widget.
							echo wp_get_attachment_image( $ip_credit_logo, 'full', false, array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- core output.
						}
						?>
					</p>
				<?php endif; ?>

				<?php if ( $ip_price_note ) : ?>
					<p class="ip-footer__note"><?php echo esc_html( $ip_price_note ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</footer>
