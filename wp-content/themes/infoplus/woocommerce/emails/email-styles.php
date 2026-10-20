<?php
/**
 * Email Styles (Infoplus theme).
 *
 * CSS that WooCommerce inlines into every HTML e-mail (WC_Email::style_inline,
 * Emogrifier). Rules that cannot be inlined (the media query) stay in a
 * <style> block for the clients that read it (Apple Mail, iOS, Gmail apps).
 *
 * Brand values come from ip_email_tokens() (inc/emails.php), not from the
 * colour options in WooCommerce > Settings > E-Mails: the theme is the
 * brand. The logo and the footer text still come from those settings.
 * Magenta only for buttons and links; text in #191B1C and #4A4E53.
 *
 * Works with WooCommerce's "email improvements" markup (classes
 * email-order-details, email-introduction, hr, order-totals) and with the
 * older markup (bordered .td tables), so the look does not depend on that
 * feature flag.
 *
 * Based on WooCommerce templates/emails/email-styles.php 10.5.0.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.5.0
 */

defined( 'ABSPATH' ) || exit;

$ip_t = function_exists( 'ip_email_tokens' ) ? ip_email_tokens() : array(
	'page'    => '#EFEFF6',
	'card'    => '#FFFFFF',
	'border'  => '#DEDEE9',
	'text'    => '#191B1C',
	'muted'   => '#4A4E53',
	'soft'    => '#F6F6FA',
	'primary' => '#E6007E',
	'link'    => '#9E0056',
	'font'    => "'Archivo Narrow', 'Arial Narrow', Arial, Helvetica, sans-serif",
);
$page    = esc_attr( $ip_t['page'] );
$card    = esc_attr( $ip_t['card'] );
$border  = esc_attr( $ip_t['border'] );
$text    = esc_attr( $ip_t['text'] );
$muted   = esc_attr( $ip_t['muted'] );
$soft    = esc_attr( $ip_t['soft'] );
$primary = esc_attr( $ip_t['primary'] );
$link    = esc_attr( $ip_t['link'] );
$font    = $ip_t['font']; // Fixed list with single quotes; not escaped so the quotes survive (as in WooCommerce).
$start   = is_rtl() ? 'right' : 'left';
$end     = is_rtl() ? 'left' : 'right';

// !important is a Gmail guard, as in WooCommerce's own styles.
?>
body {
	background-color: <?php echo $page; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	margin: 0;
	padding: 0;
	-webkit-text-size-adjust: 100%;
	-ms-text-size-adjust: 100%;
}

#outer_wrapper {
	background-color: <?php echo $page; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	width: 100%;
}

#outer_cell {
	padding: 32px 12px 40px;
}

#wrapper {
	margin: 0 auto;
	padding: 0;
	width: 100%;
	max-width: 600px;
	-webkit-text-size-adjust: none !important;
}

#inner_wrapper {
	width: 100%;
}

#template_header_image {
	padding: 0 4px 20px;
	text-align: <?php echo $start; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
}

#template_header_image p {
	margin: 0;
}

#template_header_image img {
	margin: 0;
}

#template_container {
	background-color: <?php echo $card; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	border: 1px solid <?php echo $border; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	border-radius: 16px !important;
	border-collapse: separate;
	box-shadow: none;
}

#template_header {
	background-color: <?php echo $card; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	border-radius: 16px 16px 0 0 !important;
	color: <?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-family: <?php echo $font; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
}

#header_wrapper {
	padding: 36px 40px 0;
	display: block;
}

#template_header h1,
#template_header h1 a {
	color: <?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	background-color: inherit;
}

#template_body,
#body_content {
	background-color: <?php echo $card; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	border-radius: 0 0 16px 16px;
}

#body_content_inner_cell {
	padding: 20px 40px 40px;
}

#body_content_inner {
	color: <?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-family: <?php echo $font; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-size: 16px;
	line-height: 150%;
	text-align: <?php echo $start; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
}

#body_content p {
	margin: 0 0 16px;
}

#body_content table td td,
#body_content table td th {
	padding: 10px 12px;
}

/* ── Type ───────────────────────────────────────────────────────────── */

h1 {
	color: <?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-family: <?php echo $font; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-size: 30px;
	font-weight: 700;
	line-height: 120%;
	margin: 0;
	text-align: <?php echo $start; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
}

h2 {
	color: <?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	display: block;
	font-family: <?php echo $font; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-size: 20px;
	font-weight: 700;
	line-height: 130%;
	margin: 32px 0 12px;
	text-align: <?php echo $start; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
}

h3 {
	color: <?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	display: block;
	font-family: <?php echo $font; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-size: 16px;
	font-weight: 700;
	line-height: 140%;
	margin: 16px 0 8px;
	text-align: <?php echo $start; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
}

a {
	color: <?php echo $link; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-weight: 600;
	text-decoration: underline;
}

.link {
	color: <?php echo $link; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
}

img {
	border: none;
	display: inline-block;
	font-size: 14px;
	font-weight: bold;
	height: auto;
	outline: none;
	text-decoration: none;
	text-transform: none;
	vertical-align: middle;
	margin-<?php echo $end; // phpcs:ignore WordPress.Security.EscapeOutput ?>: 12px;
	max-width: 100%;
}

.text,
.address-title,
.order-item-data,
.font-family {
	color: <?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-family: <?php echo $font; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
}

.text-align-left {
	text-align: <?php echo $start; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
}

.text-align-right {
	text-align: <?php echo $end; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
}

.screen-reader-text {
	display: none;
}

/* Dividers of the improved WooCommerce markup. */
.hr {
	border-bottom: 1px solid <?php echo $border; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	margin: 16px 0;
}

.hr-top {
	margin-top: 24px;
}

.hr-bottom {
	margin-bottom: 24px;
}

.email-introduction {
	padding-bottom: 8px;
}

.email-logo-text {
	color: <?php echo $primary; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-family: <?php echo $font; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-size: 22px;
	font-weight: 700;
}

/* ── Order summary ──────────────────────────────────────────────────── */

h2.email-order-detail-heading span {
	color: <?php echo $muted; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	display: block;
	font-size: 15px;
	font-weight: 400;
}

.td {
	color: <?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	border: 0;
	border-bottom: 1px solid <?php echo $border; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	vertical-align: middle;
}

table.td {
	border: 0;
	border-collapse: collapse;
}

#body_content table .email-order-details td,
#body_content table .email-order-details th {
	padding: 10px 8px;
}

#body_content table .email-order-details td:first-child,
#body_content table .email-order-details th:first-child {
	padding-<?php echo $start; // phpcs:ignore WordPress.Security.EscapeOutput ?>: 0;
}

#body_content table .email-order-details td:last-child,
#body_content table .email-order-details th:last-child {
	padding-<?php echo $end; // phpcs:ignore WordPress.Security.EscapeOutput ?>: 0;
}

#body_content table thead th.td,
#body_content .email-order-details thead th {
	color: <?php echo $muted; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-size: 13px;
	font-weight: 700;
	letter-spacing: 1px;
	text-transform: uppercase;
	border-bottom: 1px solid <?php echo $border; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
}

/* Column heads and prices do not break inside a word (no effect on the product cell). */
#body_content table.email-order-details > thead > tr > th,
#body_content table.email-order-details > tbody > tr > td.td:last-child {
	white-space: nowrap;
}

#body_content .email-order-details tbody tr:last-child td {
	border-bottom: 0;
	padding-bottom: 16px;
}

#body_content .email-order-details tfoot tr:first-child td,
#body_content .email-order-details tfoot tr:first-child th {
	padding-top: 16px;
}

#body_content .order-item-data td {
	border: 0 !important;
	padding: 0 !important;
	vertical-align: middle;
}

#body_content .order-item-data img {
	width: 48px;
	min-width: 48px;
	max-width: 48px;
	overflow: hidden;
	border-radius: 8px;
	background-color: <?php echo $page; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
}

#body_content .order-item-data h3 {
	margin: 0 0 4px;
	font-size: 16px;
	font-weight: 700;
}

#body_content .email-order-details .order-totals td,
#body_content .email-order-details .order-totals th {
	border: 0;
	font-weight: 400;
	padding-top: 4px;
	padding-bottom: 4px;
}

#body_content .email-order-details .order-totals .includes_tax,
#body_content .includes_tax {
	color: <?php echo $muted; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	display: block;
	font-size: 14px;
	font-weight: 400;
}

#body_content .email-order-details .order-totals-total th,
#body_content .email-order-details .order-totals-total td {
	font-weight: 700;
	padding-top: 12px;
	border-top: 1px solid <?php echo $border; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
}

#body_content .email-order-details .order-totals-total td {
	font-size: 20px;
}

#body_content .email-order-details .order-totals-last td,
#body_content .email-order-details .order-totals-last th {
	padding-bottom: 16px;
}

#body_content .email-order-details .order-customer-note td {
	border-bottom: 1px solid <?php echo $border; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	padding-top: 16px;
	padding-bottom: 16px;
}

.email-order-item-meta {
	color: <?php echo $muted; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-size: 14px;
	line-height: 140%;
}

#body_content td ul.wc-item-meta {
	color: <?php echo $muted; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-size: 14px;
	line-height: 140%;
	margin: 6px 0 0;
	padding: 0;
	list-style: none;
}

#body_content td ul.wc-item-meta li {
	margin: 2px 0 0;
	padding: 0;
}

#body_content td ul.wc-item-meta li p {
	margin: 0;
}

#body_content .wc-item-meta-label {
	clear: both;
	float: <?php echo $start; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-weight: 600;
	margin-<?php echo $end; // phpcs:ignore WordPress.Security.EscapeOutput ?>: .25em;
}

/* ── Addresses, extra fields, notes ─────────────────────────────────── */

#addresses td {
	vertical-align: top;
}

.address {
	color: <?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-style: normal;
	padding: 12px 16px;
	border: 1px solid <?php echo $border; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	border-radius: 12px;
	word-break: break-word;
}

#addresses td + td {
	padding-<?php echo $start; // phpcs:ignore WordPress.Security.EscapeOutput ?>: 12px !important;
}

.additional-fields {
	margin: 0 0 24px;
	padding: 12px 16px;
	color: <?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	border: 1px solid <?php echo $border; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	border-radius: 12px;
	list-style: none outside;
}

.additional-fields li {
	margin: 0 0 6px;
}

#body_content td.email-additional-content,
#body_content table td td.email-additional-content {
	color: <?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-family: <?php echo $font; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	padding: 24px 0 0;
}

.email-additional-content p,
.email-additional-content-aligned p {
	text-align: <?php echo $start; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
}

/* Bank details of the BACS gateway (on-hold e-mail). */
.wc-bacs-bank-details-heading {
	margin-top: 0;
}

.wc-bacs-bank-details-account-name {
	margin: 0 0 8px;
	font-size: 16px;
}

ul.wc-bacs-bank-details,
ul.bacs_details {
	margin: 0 0 24px;
	padding: 12px 16px;
	list-style: none;
	background-color: <?php echo $soft; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	border: 1px solid <?php echo $border; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	border-radius: 12px;
}

ul.wc-bacs-bank-details li,
ul.bacs_details li {
	margin: 0;
	padding: 4px 0;
	color: <?php echo $muted; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
}

ul.wc-bacs-bank-details li strong,
ul.bacs_details li strong {
	color: <?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-weight: 700;
}

/* Theme blocks (inc/emails.php and the theme's e-mail bodies). */
#body_content td.ip-mail-box,
.ip-mail-box {
	margin: 0 0 24px;
	padding: 14px 16px;
	background-color: <?php echo $soft; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	border: 1px solid <?php echo $border; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	border-radius: 12px;
}

.ip-mail-list {
	margin: 0 0 16px;
	padding-<?php echo $start; // phpcs:ignore WordPress.Security.EscapeOutput ?>: 20px;
}

.ip-mail-list li {
	margin: 0 0 4px;
}

.ip-mail-muted {
	color: <?php echo $muted; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-size: 14px;
}

/* ── Footer (on the grey page, below the card) ──────────────────────── */

#template_footer td {
	padding: 0;
}

#template_footer #credit {
	border: 0;
	color: <?php echo $muted; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-family: <?php echo $font; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	font-size: 13px;
	line-height: 150%;
	text-align: center;
	padding: 24px 16px 0;
}

#template_footer #credit p {
	margin: 0 0 10px;
}

#template_footer #credit,
#template_footer #credit a {
	color: <?php echo $muted; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
}

/*
 * Phones. Not every client reads media queries; the fluid tables work
 * without them, these only tighten the spacing.
 */
@media screen and (max-width: 600px) {
	#outer_cell {
		padding: 16px 8px 24px !important;
	}

	#template_header_image {
		padding: 0 8px 14px !important;
	}

	#header_wrapper {
		padding: 24px 20px 0 !important;
	}

	#header_wrapper h1 {
		font-size: 24px !important;
	}

	#body_content_inner_cell {
		padding: 16px 20px 28px !important;
	}

	#body_content_inner {
		font-size: 16px !important;
	}

	#body_content .email-order-details .order-totals-total td {
		font-size: 18px !important;
	}

	h2.email-order-detail-heading {
		font-size: 18px !important;
	}

	#body_content table.email-order-details > thead > tr > th {
		font-size: 12px !important;
		letter-spacing: 0 !important;
	}

	#body_content table .email-order-details td,
	#body_content table .email-order-details th {
		padding-left: 0 !important;
		padding-right: 0 !important;
	}

	#body_content table .email-order-details td + td,
	#body_content table .email-order-details th + th,
	#body_content table .email-order-details th + td {
		padding-left: 8px !important;
	}

	#body_content .order-item-data img {
		width: 40px !important;
		min-width: 40px !important;
		max-width: 40px !important;
		margin-right: 8px !important;
	}

	#addresses td {
		display: block !important;
		width: 100% !important;
		padding: 0 0 12px !important;
	}
}
<?php
