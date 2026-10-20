<?php
/**
 * E-mail design: WooCommerce e-mails (and the Infoplus Suite inquiry mails,
 * which use WooCommerce's header and footer) in the shop's look.
 *
 * Templates (woocommerce/emails/, @version matched to WooCommerce 10.6):
 * - email-header.php, email-footer.php: light grey page, white card, logo
 *   above the card, contact line and B2B note below it.
 * - email-styles.php: the CSS WooCommerce inlines (Emogrifier). Brand
 *   colours from ip_email_tokens(), not from the WooCommerce colour options.
 * - customer-new-account.php, customer-reset-password.php: German B2B text
 *   with formal Sie; WooCommerce's link logic unchanged.
 * Every other e-mail keeps WooCommerce's own body template; only its look
 * changes. Plain-text e-mails are untouched.
 *
 * Hooks into the e-mail body are unchanged, so plugins that print into the
 * mails keep working: Germanized (legal texts on woocommerce_email_footer
 * before the footer template, attachments on woocommerce_email_attachments),
 * the BACS bank details (woocommerce_email_before_order_table, 10), PDF
 * Invoices & Packing Slips (attachments).
 *
 * The theme declares add_theme_support( 'infoplus-emails' ); the Infoplus
 * Suite (UserOrders, Plugin 0.10.30) then skips its own order e-mail
 * branding (pink banner, extra CSS, logo rewrite), which this design replaces.
 *
 * Switch: filter ip_emails_enabled (false = WooCommerce's default e-mail
 * templates and the Suite branding again, as without this theme).
 */

defined( 'ABSPATH' ) || exit;

/** True when the theme's e-mail design is on. */
function ip_emails_enabled() {
	return (bool) apply_filters( 'ip_emails_enabled', true );
}

add_action( 'after_setup_theme', function () {
	if ( ip_emails_enabled() ) {
		add_theme_support( 'infoplus-emails' );
	}
} );

/*
 * The overrides in woocommerce/emails/ are found by wc_locate_template().
 * With the switch off, send WooCommerce back to its own templates.
 */
add_filter( 'woocommerce_locate_template', function ( $template, $template_name, $template_path ) {
	if ( ip_emails_enabled() || 0 !== strpos( (string) $template_name, 'emails/' ) || 0 !== strpos( (string) $template, IP_DIR . '/' ) ) {
		return $template;
	}
	$default = WC()->plugin_path() . '/templates/' . $template_name;
	return file_exists( $default ) ? $default : $template;
}, 10, 3 );

/**
 * Colours and font of the e-mails. Brand values (DESIGN.md); filter
 * ip_email_tokens to change them.
 *
 * @return array<string,string>
 */
function ip_email_tokens() {
	return (array) apply_filters( 'ip_email_tokens', array(
		'page'      => '#EFEFF6', // Very Light Grey: the page around the card.
		'card'      => '#FFFFFF',
		'border'    => '#DEDEE9', // Light Grey.
		'text'      => '#191B1C',
		'muted'     => '#4A4E53', // Dark Grey.
		'soft'      => '#F6F6FA', // Boxes inside the card (bank details, PO number).
		'primary'   => '#E6007E', // Buttons.
		'link'      => '#9E0056', // Links: #E6007E is too light for small text (DESIGN.md, Contrast).
		// Archivo Narrow where installed or loaded (Apple Mail), else Arial / Helvetica.
		'font'      => "'Archivo Narrow', 'Arial Narrow', Arial, Helvetica, sans-serif",
	) );
}

/**
 * Logo for the e-mail header, as safe HTML (linked to the shop):
 * 1. WooCommerce > Settings > E-Mails > "Header image" (with its width setting),
 * 2. the site logo of the theme header (attachment 85 or the custom logo), when it is a PNG, JPG or GIF
 *    (SVG and WebP do not show in many mail clients),
 * 3. the site name as text.
 */
function ip_email_logo_html() {
	$name = get_bloginfo( 'name', 'display' );
	$img  = (string) get_option( 'woocommerce_email_header_image' );
	/** This filter is documented in woocommerce/templates/emails/email-styles.php */
	if ( apply_filters( 'woocommerce_is_email_preview', false ) ) {
		$transient = get_transient( 'woocommerce_email_header_image' );
		$img       = false !== $transient ? (string) $transient : $img;
	}
	$width = (int) get_option( 'woocommerce_email_header_image_width', 120 );
	$width = $width > 0 ? min( $width, 280 ) : 120;

	if ( '' === $img ) {
		$ids = array( (int) apply_filters( 'ip_logo_id', 85 ), (int) get_theme_mod( 'custom_logo' ) );
		foreach ( array_filter( $ids ) as $id ) {
			$mime = (string) get_post_mime_type( $id );
			if ( in_array( $mime, array( 'image/png', 'image/jpeg', 'image/gif' ), true ) ) {
				$src = wp_get_attachment_image_src( $id, 'medium' );
				if ( $src ) {
					$img   = (string) $src[0];
					$width = 140;
					break;
				}
			}
		}
	}

	$t = ip_email_tokens();
	if ( '' !== $img ) {
		$inner = sprintf(
			'<img src="%1$s" alt="%2$s" width="%3$d" style="display:block;width:%3$dpx;max-width:100%%;height:auto;border:0;outline:none;text-decoration:none;font-family:%4$s;font-size:22px;font-weight:700;color:%5$s">',
			esc_url( $img ),
			esc_attr( $name ),
			$width,
			esc_attr( $t['font'] ),
			esc_attr( $t['primary'] )
		);
	} else {
		$inner = sprintf(
			'<span class="ip-mail-logo-text" style="font-family:%1$s;font-size:26px;line-height:1.2;font-weight:700;color:%2$s;text-decoration:none">%3$s</span>',
			esc_attr( $t['font'] ),
			esc_attr( $t['primary'] ),
			esc_html( $name )
		);
	}
	return '<a href="' . esc_url( home_url( '/' ) ) . '" style="text-decoration:none">' . $inner . '</a>';
}

/**
 * Contact line and B2B note for the e-mail footer, from the site settings
 * (the values of the site footer). Returns safe HTML, '' without settings.
 */
function ip_email_footer_contact_html() {
	if ( ! function_exists( 'ip_setting' ) ) {
		return '';
	}
	$t     = ip_email_tokens();
	$link  = 'color:' . $t['muted'] . ';text-decoration:underline';
	$parts = array();
	$phone = trim( (string) ip_setting( 'phone' ) );
	$email = trim( (string) ip_setting( 'email' ) );
	if ( '' !== $phone && function_exists( 'ip_tel' ) ) {
		$parts[] = esc_html__( 'Telefon', 'infoplus' ) . ' <a href="' . esc_url( ip_tel( $phone ), array( 'tel' ) ) . '" style="' . esc_attr( $link ) . '">' . esc_html( $phone ) . '</a>';
	}
	if ( '' !== $email && is_email( $email ) ) {
		$parts[] = esc_html__( 'E-Mail', 'infoplus' ) . ' <a href="' . esc_url( 'mailto:' . $email, array( 'mailto' ) ) . '" style="' . esc_attr( $link ) . '">' . esc_html( $email ) . '</a>';
	}
	$html = '';
	if ( $parts ) {
		$html .= '<p class="ip-mail-contact">' . esc_html__( 'Fragen?', 'infoplus' ) . ' ' . implode( ' &middot; ', $parts ) . '</p>';
	}
	$note = trim( (string) ip_setting( 'footer_legal_note' ) );
	if ( '' !== $note ) {
		$html .= '<p class="ip-mail-note">' . esc_html( $note ) . '</p>';
	}
	return $html;
}

/**
 * Primary button for the e-mail body (table, so Outlook keeps the padding).
 * Safe HTML.
 */
function ip_email_button( $url, $label ) {
	$t = ip_email_tokens();
	return sprintf(
		'<table role="presentation" border="0" cellpadding="0" cellspacing="0" class="ip-mail-btn-wrap" style="margin:8px 0 24px;border-collapse:separate"><tr><td align="center" bgcolor="%1$s" style="padding:0;border-radius:10px;background:%1$s"><a class="ip-mail-btn" href="%2$s" style="display:inline-block;padding:13px 24px;border-radius:10px;background:%1$s;color:#FFFFFF;font-family:%3$s;font-size:16px;line-height:1.2;font-weight:700;text-decoration:none">%4$s</a></td></tr></table>',
		esc_attr( $t['primary'] ),
		esc_url( $url ),
		esc_attr( $t['font'] ),
		esc_html( $label )
	);
}

/** "Guten Tag Erika Mustermann," for a user, "Guten Tag," without a name. */
function ip_email_greeting( $user_id ) {
	$name = '';
	if ( $user_id ) {
		$first = (string) get_user_meta( $user_id, 'billing_first_name', true );
		$last  = (string) get_user_meta( $user_id, 'billing_last_name', true );
		if ( '' === $first . $last ) {
			$first = (string) get_user_meta( $user_id, 'first_name', true );
			$last  = (string) get_user_meta( $user_id, 'last_name', true );
		}
		$name = trim( $first . ' ' . $last );
	}
	/* translators: %s: customer name */
	return '' !== $name ? sprintf( __( 'Guten Tag %s,', 'infoplus' ), $name ) : __( 'Guten Tag,', 'infoplus' );
}

/* ── Order e-mails: the order reference (PO number) ─────────────────────── */

/*
 * The order reference sits right above the order summary, after the BACS
 * bank details (same hook, priority 10). WooCommerce would otherwise list it
 * only at the very end under "Additional information"; that copy is
 * dropped from the e-mails (it stays on the confirmation page and in the
 * admin). The field and its meta "_wc_other/infoplus/po-number": inc/shop.php.
 */
add_action( 'woocommerce_email_before_order_table', function ( $order, $sent_to_admin = false, $plain_text = false ) {
	if ( ! ip_emails_enabled() || ! function_exists( 'ip_shop_po_number' ) ) {
		return;
	}
	$po = ip_shop_po_number( $order );
	if ( '' === $po ) {
		return;
	}
	$label = $sent_to_admin ? __( 'Bestellreferenz des Kunden (PO-Nummer)', 'infoplus' ) : __( 'Ihre Bestellreferenz / PO-Nummer', 'infoplus' );
	if ( $plain_text ) {
		echo esc_html( $label ) . ': ' . esc_html( $po ) . "\n\n";
		return;
	}
	$t = ip_email_tokens();
	printf(
		'<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%%" class="ip-mail-po" style="margin:0 0 24px;border-collapse:separate"><tr><td style="padding:12px 16px;border-radius:12px;background:%1$s;border:1px solid %2$s"><p style="margin:0 0 2px;font-size:12px;line-height:1.3;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:%3$s">%4$s</p><p style="margin:0;font-size:18px;line-height:1.3;font-weight:700;color:%5$s;word-break:break-word;overflow-wrap:anywhere">%6$s</p></td></tr></table>',
		esc_attr( $t['soft'] ),
		esc_attr( $t['border'] ),
		esc_attr( $t['muted'] ),
		esc_html( $label ),
		esc_attr( $t['text'] ),
		esc_html( $po )
	);
}, 20, 3 );

add_filter( 'woocommerce_filter_fields_for_order_confirmation', function ( $show, $field = array(), $fields = array(), $context = array() ) {
	if ( ! ip_emails_enabled() || ! defined( 'IP_SHOP_PO_FIELD' ) || ! is_array( $field ) || ! is_array( $context ) ) {
		return $show;
	}
	$caller = (string) ( $context['caller'] ?? '' );
	if ( 0 === strpos( $caller, 'WC_Email::' ) && IP_SHOP_PO_FIELD === ( $field['id'] ?? '' ) ) {
		return false;
	}
	return $show;
}, 10, 4 );
