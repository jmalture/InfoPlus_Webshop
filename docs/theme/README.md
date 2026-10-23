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

## Namensschild-Berater (since 0.5.0)

Guided picker for new customers at `/berater/` (page 25879 on staging, template `page-berater.php` by slug). Internal showcase.

- `inc/finder.php`: questions (`ip_finder_questions()`, filter `ip_finder_questions`), card icons, the CSS badge helper `ip_finder_plate()`, `ip_finder_url()`, the front page section `ip_finder_ways_section()`, and the inquiry drawer on the finder page (`ip_inquiry_variant` returns `product`, which has the "Kostenlose Muster" switch).
- `assets/js/finder.js`: reads `#ip-finder-data` (Suite `infoplus_suite_finder_data()` plus the questions), filters and scores the candidates, drives the live preview and renders the results. Rule based: every rule that adds points adds its sentence to the "Warum" list. Hard filters: fastening, minimum quantity against the chosen quantity band, Serienfertigung only for 250+, material and Sonderkontur; when nothing is left, material and contour become soft and the result says so.
- Picks: "Beste Wahl" (highest score), "Preis-Tipp" (lowest unit price at the chosen quantity among candidates with at least 60 % of the best score, another family), "Premium" (highest premium trait in the same pool, a third family).
- The answers are kept in the URL (`?use=&change=&logo=&material=&fastener=&qty=`). `?weg=self|logo|print` answers the second question in advance (links from the front page cards).
- Result buttons open the product with `?modell={slug}&menge={n}&befestigung={magnet|pin|clip}`. `inc/product.php` `ip_product_link_selection()` turns that into the same selection a buy form POST gives, and `product.js` restores it (model, fastening, quantity; the steps are ticked and Menge is open). Models with a requestable order type open the inquiry drawer instead.
- "Kostenloses Muster" and "Beratung" open the inquiry drawer with the product, the quantity and the answers in the message; samples switch on for "Muster".
- `assets/css/plates.css`: the badge looks drawn in CSS (`.ip-fplate` small, `.ip-fbadge` preview), shared by the home and finder bundles. The values follow the hero demo looks in `home.css`.

Front page: section "Drei Wege" after the hero (field group tab "Drei Wege": switch, head, help bar texts) with the three options, their live "ab" prices and model counts, and a link under the hero buttons (`hero_finder_label`).

