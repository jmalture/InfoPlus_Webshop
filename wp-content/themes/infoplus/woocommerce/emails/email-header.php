<?php
/**
 * Email Header (Infoplus theme).
 *
 * Light grey page, logo above a white card, the heading at the top of the
 * card. Layout in tables with fixed widths for Outlook (a 600 px ghost table
 * in an mso conditional), fluid up to 600 px elsewhere. The CSS from
 * email-styles.php is inlined by WooCommerce (Emogrifier); the <style> below
 * keeps what cannot be inlined: the web font for clients that load one
 * (Apple Mail), the Arial fallback for Outlook and the phone layout.
 *
 * Element IDs (#wrapper, #template_header_image, #template_container,
 * #template_header, #header_wrapper, #body_content, #body_content_inner) are
 * the ones WooCommerce uses, so styles and plugins that target them keep
 * working. Logo: ip_email_logo_html() (inc/emails.php).
 *
 * Based on WooCommerce templates/emails/email-header.php 10.4.0.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

defined( 'ABSPATH' ) || exit;

$store_name = $store_name ?? get_bloginfo( 'name', 'display' );
$ip_t       = function_exists( 'ip_email_tokens' ) ? ip_email_tokens() : array( 'page' => '#EFEFF6', 'font' => 'Arial, Helvetica, sans-serif' );
$ip_font    = defined( 'IP_URI' ) ? IP_URI . '/assets/fonts/archivo-narrow-var.woff2' : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
		<meta content="width=device-width, initial-scale=1.0" name="viewport">
		<meta name="x-apple-disable-message-reformatting">
		<meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
		<title><?php echo esc_html( $store_name ); ?></title>
		<!--[if mso]>
		<style>
			body, table, td, th, p, a, h1, h2, h3, li, span, div { font-family: Arial, Helvetica, sans-serif !important; }
		</style>
		<![endif]-->
		<?php if ( $ip_font ) : ?>
		<!--[if !mso]><!-->
		<style>
			@font-face { font-family: "Archivo Narrow"; src: url(<?php echo esc_url( $ip_font ); ?>) format("woff2"); font-weight: 400 700; font-style: normal; }
		</style>
		<!--<![endif]-->
		<?php endif; ?>
	</head>
	<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0" style="margin:0;padding:0;background-color:<?php echo esc_attr( $ip_t['page'] ); ?>">
		<table width="100%" id="outer_wrapper" role="presentation" border="0" cellpadding="0" cellspacing="0" bgcolor="<?php echo esc_attr( $ip_t['page'] ); ?>">
			<tr>
				<td align="center" valign="top" id="outer_cell">
					<!--[if mso]><table role="presentation" width="600" align="center" border="0" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
					<div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
						<table border="0" cellpadding="0" cellspacing="0" width="100%" id="inner_wrapper" role="presentation">
							<tr>
								<td valign="top" id="template_header_image">
									<?php echo function_exists( 'ip_email_logo_html' ) ? ip_email_logo_html() : esc_html( $store_name ); // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_* in inc/emails.php. ?>
								</td>
							</tr>
							<tr>
								<td valign="top">
									<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_container" role="presentation">
										<tr>
											<td valign="top">
												<!-- Header -->
												<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header" role="presentation">
													<tr>
														<td id="header_wrapper">
															<h1><?php echo esc_html( $email_heading ); ?></h1>
														</td>
													</tr>
												</table>
												<!-- End Header -->
											</td>
										</tr>
										<tr>
											<td valign="top">
												<!-- Body -->
												<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_body" role="presentation">
													<tr>
														<td valign="top" id="body_content">
															<!-- Content -->
															<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
																<tr>
																	<td valign="top" id="body_content_inner_cell">
																		<div id="body_content_inner">
