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


## Cart preview in the header (since 0.5.0)

On desktop the cart icon opens a preview on mouse hover, in the same panel as the account quick menu (`.ip-account__panel`); keyboard focus on the icon shows it too. The icon stays a link to the Warenkorb. Markup: `ip_minicart_html()` in `inc/woocommerce.php` (first four lines with picture, model, quantity and line total, "+ N weitere Positionen", subtotal, "Zum Warenkorb" and "Zur Kasse"; an empty state with the shop and the Berater). It is also a cart fragment: `site.js` fetches `?wc-ajax=get_refreshed_fragments` the first time the panel opens, so a cached page never shows an old cart. Not shown on the cart and checkout pages (`ip_minicart_enabled()`, filter of the same name). Lines from the online editor use their `ife_thumbnail_url` and `display` text.

## Performance (since 0.5.1)

Measured on staging as a guest, server response time of one request without page cache (EMCP `get-page-html`). Before: theme 0.5.0 with Elementor deleted, 2026-10-23.

| Page | Before | After | Queries before | Queries after |
|------|--------|-------|----------------|---------------|
| `/namensschilder/` | 5489 ms | 624 ms | 9725 | 663 |
| `/product/atlantis-namensschild/` | 3374 ms | 757 ms | 5931 | 405 |

What changed:

- `woocommerce/content-product.php` and `woocommerce/content-single-product.php` print static product classes (`product type-product post-{id} product-type-{type}`) instead of `wc_product_class()`. For a variable product WooCommerce builds the class list from the prices of every variation, which was most of the time on both pages. No theme CSS or JS uses the other classes Woo would add (checked with grep, 2026-10-23).
- `inc/performance.php` dequeues plugin assets no theme view uses and trims Germanized on listing cards. Every rule has a key in the filter `ip_performance_trims` (`wpda`, `dashicons`, `wdp`, `ppcp_minicart`, `add_to_cart`, `wc_css`, `cart_grids`, `gzd_loop`); remove a key to restore that asset or hook. The file comment lists what each rule removes and why.

Second pass, same day (query count per page view, attributed with a temporary query profiler):

| Page | Queries before | After | Server time after |
|------|----------------|-------|-------------------|
| `/namensschilder/` | 662 | 242 | 682 ms |
| `/product/atlantis-namensschild/` | 407 | 346 | 765 ms |

- `inc/performance.php` `ip_performance_gzd_line()`: the Germanized tax and shipping line was still rendered once per request, and that one call loaded every variation of the first card's product (87 products, about 350 queries). The line is now kept in a transient for 12 hours.
- `inc/options.php` `ip_setting_prime()`: the settings fields were read as two options each, one query per option because nothing is saved yet (70 queries on every page). One `wp_prime_option_caches()` call now loads them all.

Where the rest goes (product page, 346): about 100 single option reads of plugins whose settings are not autoloaded (WooCommerce payment gateways, WP Activity Log, PayPal, Freemius in WP Data Access), which a persistent object cache removes; about 110 in the Suite product components, which load attribute terms and attachment images one by one (`Components.php` line 281, `ProductAttributeImages.php` lines 1074 and 1115); 18 for the footer images (`template-parts/site-footer.php` line 71); the rest is WordPress and WooCommerce bootstrap.

Still open (Suite `PLUGIN-DOCUMENTATION.md` Section 16, findings 28 to 30): autoloaded options of deleted plugins, the cart and checkout block prefetch on every page once the cart has items, no object cache and no page cache on staging.

## Search basics (since 0.5.1)

`inc/seo.php`: meta description, Open Graph and Twitter card tags in `wp_head`, and a Product JSON-LD in the footer of product pages. The description comes from the tagline (else the front page hero text), the product short description, the page excerpt or content, or the term description, cut to 160 characters (filter `ip_seo_description`). The JSON-LD offer range uses the lowest price per model from the Suite Berater catalog (`infoplus_suite_finder_data()`), so no variation is loaded. All of it switches off when Yoast, Rank Math, AIOSEO, SEOPress or The SEO Framework is active, or through the filter `ip_seo_enabled`. Checked on staging 2026-10-23 (Atlantis: offers 5.24 to 13.74 EUR over 1,380 variations). Launch steps: `docs/LAUNCH.md` section 9.

## Product image thumbnails (since 0.5.2)

The thumbnails under the product image (Suite component product-image) are one row on every width that swipes sideways, instead of wrapping from 768 px. When the row is wider than its column, `assets/js/product.js` wraps it in `.ip-thumbs` and adds an arrow on each side. An arrow clicks the previous or next thumbnail, so the Suite swaps the big image exactly as on a click, and the row scrolls along so the current thumbnail stays in view. The row snaps to thumbnail starts, so the scroll target is always a thumbnail start or the end of the row. The arrows follow the Suite rebuilding the row on a model change (MutationObserver) and are left out of the tab order, because the thumbnails are buttons themselves. Pattern taken from the HomeStyle theme. Styles: `assets/css/product.css` "Thumbnails".

## Animated pictures on Kontakt and in "Serviceversprechen" (since 0.5.3)

Both work like the front page hero demo: a switch in the page's field group picks animation or photo, the server renders a finished picture (no JS, prefers-reduced-motion), and everything is HTML and CSS only.

- Kontakt (`template-parts/contact-demo.php`, styles in `content.css`, script `assets/js/content.js`): an example conversation next to the intro. The question appears, three typing dots, the answer, then "Ihr Angebot, per E-Mail gesendet" with a tick; then the next example. Fields "Animiertes Beispiel statt Bild" (`demo_show`, default on) and "Beispiel-Gespräche" (`demo_chats`, one "Frage | Antwort" per line). Labelled "Beispiel". Pause button, pauses on hover, focus, off screen and in a hidden tab, like the hero demo. Square box below 1101 px, 4/3 beside the intro.
- Front page cards (`template-parts/service-anim.php`, styles in `home.css`, script at the end of `home.js`): Qualität (a badge outline is drawn with a pencil, the plate fills in, a "Made in Germany" seal is stamped on), Preise (Staffelpreise as growing bars from the hero field "Vorschau: Staffelpreise", labelled "Beispiel", then an "Onlineshop-Rabatt" chip), Service (Beratung, Layout, Fertigung, Versand light up along a line, then "badge24 Express"). Switch "Animationen statt Fotos" (`service_anim_show`, default on) and per card "Animation" (`anim`; "Automatisch" uses the position: 1 Qualität, 2 Preise, 3 Service). Each picture plays once when it scrolls into view and again when the pointer enters the card; under 3 seconds, so no pause button is needed. Texts in the pictures can be changed with the filters `ip_service_anim_name`, `ip_service_anim_seal`, `ip_service_anim_steps` and `ip_service_anim_express`.

