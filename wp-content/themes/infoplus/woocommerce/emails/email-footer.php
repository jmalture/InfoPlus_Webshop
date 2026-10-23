<?php
/**
 * Email Footer (Infoplus theme).
 *
 * Closes the white card of email-header.php. Below the card, on the grey
 * page: the footer text from WooCommerce > Settings > E-Mails (filter
 * woocommerce_email_footer_text, placeholders such as {site_title} and
 * {store_address} replaced by WooCommerce), then the contact line and the
 * B2B note from the site settings (ip_email_footer_contact_html()).
 *
 * Plugins that print legal texts into the mail (Germanized) hook into
 * woocommerce_email_footer before this template runs, so their text stays
 * inside the card.
 *
 * Based on WooCommerce templates/emails/email-footer.php 10.4.0.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

defined( 'ABSPATH' ) || exit;

$email = $email ?? null;

?>
																		</div>
																	</td>
																</tr>
															</table>
															<!-- End Content -->
														</td>
													</tr>
												</table>
												<!-- End Body -->
											</td>
										</tr>
									</table>
								</td>
							</tr>
							<tr>
								<td valign="top">
									<!-- Footer -->
									<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_footer" role="presentation">
										<tr>
											<td valign="top" id="credit">
												<?php
												$email_footer_text = get_option( 'woocommerce_email_footer_text' );
												/**
												 * This filter is documented in templates/emails/email-styles.php
												 *
												 * @since 9.6.0
												 */
												if ( apply_filters( 'woocommerce_is_email_preview', false ) ) {
													$text_transient    = get_transient( 'woocommerce_email_footer_text' );
													$email_footer_text = false !== $text_transient ? $text_transient : $email_footer_text;
												}
												echo wp_kses_post(
													wpautop(
														wptexturize(
															/**
															 * Provides control over the email footer text used for most order emails.
															 *
															 * @since 4.0.0
															 *
															 * @param string $email_footer_text
															 */
															apply_filters( 'woocommerce_email_footer_text', $email_footer_text, $email )
														)
													)
												);
												echo function_exists( 'ip_email_footer_contact_html' ) ? ip_email_footer_contact_html() : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_* in inc/emails.php.
												?>
											</td>
										</tr>
									</table>
									<!-- End Footer -->
								</td>
							</tr>
						</table>
					</div>
					<!--[if mso]></td></tr></table><![endif]-->
				</td>
			</tr>
		</table>
	</body>
</html>
