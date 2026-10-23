# Infoplus webshop theme

Classic PHP theme for the Infoplus webshop (WordPress + WooCommerce), replacing Elementor.
Plan and template-by-template map: "Infoplus rebuild map" (Claude doc).

Customer account, login and order confirmation: `ACCOUNT.md` (routing, the one-time settings change, feature inventory, findings).

## Layout (monorepo since 2026-10-23)

- `wp-content/themes/infoplus/`   the theme (deployed to `wp-content/themes/infoplus/`).
- `wp-content/mu-plugins/`        `infoplus-theme-preview.php`: admin-only preview of the new theme, only needed while another theme is active.
- `legacy/`                       read-only export of everything that lived in Elementor on staging (2026-10-20). Reference only, never deployed. See `legacy/README.md`.
- `tools/build-acf-json.py`       builds the field groups in `acf-json/`.
- Theme paths in this file (`inc/...`, `assets/...`, `template-parts/...`) are relative to `wp-content/themes/infoplus/`.

## Deploy

ZIP upload in wp-admin (Themes > Add > Upload, "Replace current with uploaded"). Build the ZIP with `tools/build-zips.sh`. Before uploading, compare staging with the repo (files may have been changed over FTP).

## Kontakt form

The Kontakt page (`page-kontakt.php`) shows the Infoplus Suite inquiry form in its contact variant inline in the form card, with phone and e-mail below it as the second way. Placement and the `?produkt=` lookup: `inc/inquiry.php`; markup around the Suite form: `template-parts/inquiry-inline.php`; behaviour: `assets/js/inquiry.js` (inline mode). Product pages keep the drawer. One form per page; the drawer is never printed on the Kontakt page. Details: Suite `PLUGIN-DOCUMENTATION.md` Section 21.

## E-mails

All WooCommerce e-mails use the shop design: `inc/emails.php` (tokens, logo, footer contact line, PO number box, `add_theme_support( 'infoplus-emails' )`) and `woocommerce/emails/` (`email-header.php`, `email-footer.php`, `email-styles.php`, German B2B texts in `customer-new-account.php` and `customer-reset-password.php`; versions matched to WooCommerce 10.6.1). The Suite inquiry e-mails use the same header and footer. Logo: WooCommerce > Settings > E-Mails "Header image", else the header logo when it is PNG, JPG or GIF, else the site name. Switch: filter `ip_emails_enabled`.

Notes:
- Order e-mail body texts (greeting, intro) stay WooCommerce strings. WooCommerce's `de_DE` translation uses "du"; for formal Sie the site language must be "Deutsch (Sie)" (`de_DE_formal`) or Germanized's formal setting (unverified on staging).
- WooCommerce's block e-mail editor (feature `block_email_editor`) replaces these PHP templates when switched on. Off locally; staging unverified.
- The web font loads only in clients that allow it (Apple Mail); everywhere else Arial / Helvetica, Outlook forced to Arial. Whether the server sends the woff2 with a CORS header is unverified.

