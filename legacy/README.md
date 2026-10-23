# Infoplus staging: Elementor / database legacy export

Read-only export of everything stored in Elementor and related WordPress data on the Infoplus staging site (navajowhite-rhinoceros-679020.hostingersite.com), taken 2026-10-20 before Elementor is removed. Only SELECT queries and read tools were used; nothing on the site was changed.

## Counts

| Folder | Files | Bytes |
|---|---|---|
| `custom-css/` | 24 | 20814 |
| `custom-js/` | 2 | 24376 |
| `elementor/` | 47 | 2426330 |
| `forms/` | 1 | 29466 |
| `html-widgets/` | 5 | 32698 |
| `kit/` | 1 | 15318 |
| `snippets/` | 9 | 42126 |
| `wp/` | 3 | 72438 |
| `wp/child-theme/` | 2 | 12416 |
| **total (excluding this README)** | **94** | **2675982** |

- Elementor documents: 47 JSON files (46 posts with `_elementor_data` + draft header 15739 which has none).
  - Breakdown: 22 pages, 1 post (2825), 22 `elementor_library` (incl. kit 5 and empty draft 15739), 2 `plus-mega-menu` (2832, 3023).
- Documents with custom CSS: 24 (23 with element-level `custom_css`, plus popup 14241 whose CSS is only in page settings).
- HTML widgets: 5. Elementor Pro form widgets: 4. Custom Code snippets: 9.
- Elementor form submissions in `wp_e_submissions`: 4 rows (1 form, 1 page). Not exported (personal data).

## Format notes

- `elementor/<id>-<slug>.json`: keys `id, post_type, title, slug, status, template_type, conditions, page_settings, elementor_data`, plus `extra_meta` (popup display settings, forms snapshot, versions, `_wp_page_template`, The Plus `tp_widgets`) and `export_info` (DB byte length and sha256 of the raw `_elementor_data` string). `page_settings` and `conditions` are PHP-unserialized; `elementor_data` is the decoded JSON with original key order.
- Posts without a slug get `noslug-<title>` in the file name (13937, 15739). Template 2190 really has slug `__trashed` while status is `publish`.
- `custom-css/*.css`: blocks are `/* element <elementId> <widgetType or elType> */` followed by the CSS verbatim; page-level CSS is headed `/* page_settings document <id> */`.
- `snippets/*`: the code lives in meta `_elementor_code` (post_content is empty for all 9). Extension is the inner language when the code is only `<style>` (css) or only `<script>` (js) blocks, otherwise html. The header is an HTML comment `<!-- ... -->` when the code starts with a tag (all of them do), ending with `<!-- END HEADER -->`; everything after that line is verbatim.
- `kit/kit-5-settings.json`: `settings` is the full decoded kit `_elementor_page_settings`; `system_colors`, `custom_colors`, `system_typography`, `custom_typography` are copies for convenience.
- `wp/options.json`: `value` decoded, `raw` exact DB string. Licence keys, API keys/secrets, connect keys and large remote caches (`elementor_remote_info_library`, `elementor_log`, `elementor_controls_usage`, Pro remote info) were deliberately left out. Menu location: `menu-1` = menu 19 (Main Menu).
- `wp/menus.json`: 4 menus (19 Main Menu, 20 Footer_Informationen, 21 Footer_Rechtliches, 247 Mobile Menu), items sorted by `menu_order`, with parent, target, type/object, resolved object title, The Plus mega menu meta and all raw item meta.
- `forms/forms.json`: per form `fields_summary`, `email_settings` and the full widget `settings_verbatim`, plus the `__elementor_forms_snapshot` meta. Forms 3040 and 14241 have no stored `submit_actions`, which means Elementor default (`email`). Forms on 12466 have `submit_actions: []` (handled by custom code).

## Verification

All `_elementor_data` values were read in full via SQL (no truncation flag set). For every document the UTF-8 byte length of the string received equals `LENGTH(meta_value)`, and re-encoding the decoded JSON the way WordPress does (`wp_json_encode`: ASCII escapes, `\/` slashes, no spaces) reproduces the DB string byte for byte. All serialized meta/options were unserialized with PHP and re-serialize to the identical string.

| Post | LENGTH(meta_value) | Bytes received | Byte-identical round trip |
|---|---|---|---|
| 5 | 2 | 2 | yes |
| 8 | 125181 | 125181 | yes |
| 17 | 76275 | 76275 | yes |
| 21 | 24557 | 24557 | yes |
| 27 | 1291 | 1291 | yes |
| 28 | 7568 | 7568 | yes |
| 29 | 4427 | 4427 | yes |
| 141 | 7460 | 7460 | yes |
| 203 | 11419 | 11419 | yes |
| 369 | 9691 | 9691 | yes |
| 2190 | 43945 | 43945 | yes |
| 2192 | 9067 | 9067 | yes |
| 2213 | 5375 | 5375 | yes |
| 2219 | 12243 | 12243 | yes |
| 2222 | 13629 | 13629 | yes |
| 2469 | 15226 | 15226 | yes |
| 2481 | 35409 | 35409 | yes |
| 2637 | 33718 | 33718 | yes |
| 2642 | 29258 | 29258 | yes |
| 2647 | 11417 | 11417 | yes |
| 2656 | 5720 | 5720 | yes |
| 2662 | 13249 | 13249 | yes |
| 2667 | 17027 | 17027 | yes |
| 2825 | 35409 | 35409 | yes |
| 2832 | 167388 | 167388 | yes |
| 2836 | 1660 | 1660 | yes |
| 3023 | 127880 | 127880 | yes |
| 3040 | 18946 | 18946 | yes |
| 3097 | 22976 | 22976 | yes |
| 9999 | 725 | 725 | yes |
| 12381 | 16179 | 16179 | yes |
| 12466 | 183792 | 183792 | yes |
| 12702 | 94988 | 94988 | yes |
| 12750 | 39983 | 39983 | yes |
| 13123 | 1834 | 1834 | yes |
| 13223 | 62120 | 62120 | yes |
| 13266 | 17275 | 17275 | yes |
| 13365 | 4167 | 4167 | yes |
| 13381 | 4250 | 4250 | yes |
| 13937 | 40007 | 40007 | yes |
| 13983 | 36787 | 36787 | yes |
| 14241 | 7204 | 7204 | yes |
| 14265 | 3698 | 3698 | yes |
| 15663 | 5911 | 5911 | yes |
| 15902 | 4171 | 4171 | yes |
| 15906 | 6509 | 6509 | yes |

Snippets (`_elementor_code`):

| Post | Bytes written | LENGTH(meta_value) | Match |
|---|---|---|---|
| 1792 | 3050 | 3050 | yes |
| 2183 | 11051 | 11051 | yes |
| 12433 | 667 | 667 | yes |
| 12463 | 563 | 563 | yes |
| 13427 | 290 | 290 | yes |
| 13432 | 1393 | 1393 | yes |
| 13799 | 8237 | 8237 | yes |
| 14273 | 11373 | 11373 | yes |
| 15757 | 232 | 232 | yes |

Other checks: customizer CSS post 381 = 2647 bytes (matches `LENGTH(post_content)`); child theme `functions.php` 11791 bytes / 288 lines and `style.css` 625 bytes / 16 lines match the sizes reported by the server; popup 14241 page setting `eael_custom_js` = 12189 bytes, postmeta `_eael_custom_js` = 12187 bytes (`LENGTH`), both written.

## Findings worth knowing

- Popup 14241: the two copies of the Essential Addons custom JS differ in one line (line 191). The page setting has `/\b\w/g`, the postmeta `_eael_custom_js` has `/bw/g` (backslashes stripped on save). The postmeta copy is what Essential Addons prints on the front end, so the live regex is broken. Use `custom-js/14241-special-wishes-eael-custom-js.js` as the intended source.
- Page 27 (Cart, empty title) has `_elementor_data` and was not in the original ID list; it is included.
- `elementor_library` 15739 (draft "Elementor Header #15739") has no `_elementor_data`; exported as metadata only (`elementor_data: null`).
- Elementor content contains business contact data that is part of the site (e.g. Impressum, form `email_to`). No user accounts, orders or submissions were exported.

## Missing / could not read

- Nothing that was requested failed to read.
- Not exported on purpose: form submissions (4 rows), `wp_users`/`wp_usermeta`, orders, post revisions, generated caches (`_elementor_css`, `_elementor_element_cache`, `_elementor_controls_usage`, `_elementor_page_assets`, screenshots), generated CSS files under `wp-content/uploads/elementor/`, and secret or licence options.
- Child theme `readme.txt` (2648 bytes, stock Elementor text) and `screenshot.png` (87033 bytes) were not copied.
- `_elementor_inline_svg` meta on 5 attachments (92, 94, 149, 165, 182) is an SVG render cache of media files and was not exported.

## File index

| Path | Source | What | Bytes |
|---|---|---|---|
| `elementor/5-default-kit.json` | post 5 (elementor_library) | Elementor document: Default Kit | 8768 |
| `elementor/8-home.json` | post 8 (page) | Elementor document: Home | 193423 |
| `custom-css/8-home.css` | post 8 | 8 custom_css block(s) | 1409 |
| `elementor/17-infoplus_header.json` | post 17 (elementor_library) | Elementor document: infoplus_header + native | 149367 |
| `elementor/21-infoplus_footer.json` | post 21 (elementor_library) | Elementor document: infoplus_footer | 42554 |
| `custom-css/21-infoplus_footer.css` | post 21 | 3 custom_css block(s) | 653 |
| `elementor/27-cart.json` | post 27 (page) | Elementor document: (no title) | 2221 |
| `elementor/28-kasse.json` | post 28 (page) | Elementor document: Checkout | 8778 |
| `custom-css/28-kasse.css` | post 28 | 1 custom_css block(s) | 266 |
| `elementor/29-my-account.json` | post 29 (page) | Elementor document: My account | 6732 |
| `elementor/141-mobile-menu.json` | post 141 (elementor_library) | Elementor document: Mobile Menu | 12788 |
| `custom-css/141-mobile-menu.css` | post 141 | 2 custom_css block(s) | 717 |
| `elementor/203-impressum.json` | post 203 (page) | Elementor document: Impressum | 17119 |
| `elementor/369-infoplus_header_home.json` | post 369 (elementor_library) | Elementor document: infoplus_header_home | 15152 |
| `custom-css/369-infoplus_header_home.css` | post 369 | 2 custom_css block(s) | 149 |
| `elementor/2190-__trashed.json` | post 2190 (elementor_library) | Elementor document: Elementor Single Product | 76774 |
| `custom-css/2190-__trashed.css` | post 2190 | 6 custom_css block(s) | 994 |
| `html-widgets/2190-ea7913e.html` | post 2190 element ea7913e | HTML widget content, 238 bytes | 238 |
| `elementor/2192-befestigungen.json` | post 2192 (elementor_library) | Elementor document: Befestigungen | 16223 |
| `elementor/2213-druck-gestaltung.json` | post 2213 (elementor_library) | Elementor document: Druck & Gestaltung | 9072 |
| `elementor/2219-bestell-hinweise.json` | post 2219 (elementor_library) | Elementor document: Bestell-Hinweise | 18803 |
| `elementor/2222-laufender-bedarf.json` | post 2222 (elementor_library) | Elementor document: Laufender Bedarf | 23575 |
| `elementor/2469-mega-menu.json` | post 2469 (elementor_library) | Elementor document: Mega menu | 24316 |
| `custom-css/2469-mega-menu.css` | post 2469 | 1 custom_css block(s) | 79 |
| `elementor/2481-megamenu-alucontur.json` | post 2481 (elementor_library) | Elementor document: MegaMenu AluContur | 56250 |
| `custom-css/2481-megamenu-alucontur.css` | post 2481 | 14 custom_css block(s) | 1735 |
| `elementor/2637-agb.json` | post 2637 (page) | Elementor document: AGB | 33169 |
| `elementor/2642-datenschutzerklaerung.json` | post 2642 (page) | Elementor document: Datenschutz | 29583 |
| `elementor/2647-verhaltenskodex.json` | post 2647 (page) | Elementor document: Verhaltenskodex | 12484 |
| `elementor/2656-versand-zahlung.json` | post 2656 (page) | Elementor document: Versand/Express und Zahlung | 7148 |
| `elementor/2662-unternehmen.json` | post 2662 (page) | Elementor document: Unternehmen | 13857 |
| `html-widgets/2662-e43551a.html` | post 2662 element e43551a | HTML widget content, 11389 bytes | 11389 |
| `elementor/2667-faq.json` | post 2667 (page) | Elementor document: FAQ | 23907 |
| `elementor/2825-alle-namensshilder.json` | post 2825 (post) | Elementor document: Alle Namensshilder  | 56357 |
| `custom-css/2825-alle-namensshilder.css` | post 2825 | 14 custom_css block(s) | 1735 |
| `elementor/2832-submenu-namebadge.json` | post 2832 (plus-mega-menu) | Elementor document: Submenu Namensschilder | 300799 |
| `custom-css/2832-submenu-namebadge.css` | post 2832 | 12 custom_css block(s) | 1432 |
| `elementor/2836-3d-product-view.json` | post 2836 (page) | Elementor document: 3D Product View | 3248 |
| `elementor/3023-submenu-badges.json` | post 3023 (plus-mega-menu) | Elementor document: Submenu Schilder | 230446 |
| `custom-css/3023-submenu-badges.css` | post 3023 | 8 custom_css block(s) | 936 |
| `elementor/3040-kontakt.json` | post 3040 (page) | Elementor document: Kontakt | 28851 |
| `custom-css/3040-kontakt.css` | post 3040 | 2 custom_css block(s) | 416 |
| `elementor/3097-elementor-single-product-3097.json` | post 3097 (elementor_library) | Elementor document: Elementor Single Product Exkclusive | 44206 |
| `custom-css/3097-elementor-single-product-3097.css` | post 3097 | 3 custom_css block(s) | 325 |
| `elementor/9999-xmas.json` | post 9999 (page) | Elementor document: xmas | 1599 |
| `elementor/12381-log-in.json` | post 12381 (page) | Elementor document: Log in | 20864 |
| `custom-css/12381-log-in.css` | post 12381 | 2 custom_css block(s) | 176 |
| `elementor/12466-account.json` | post 12466 (page) | Elementor document: Account | 360077 |
| `custom-css/12466-account.css` | post 12466 | 21 custom_css block(s) | 2536 |
| `html-widgets/12466-416ae71.html` | post 12466 element 416ae71 | HTML widget content, 7090 bytes | 7090 |
| `html-widgets/12466-8cbcd03.html` | post 12466 element 8cbcd03 | HTML widget content, 209 bytes | 209 |
| `elementor/12702-alture-namenschilder-team-projektdetail.json` | post 12702 (page) | Elementor document: Alture Namenschilder Team project detail | 162360 |
| `custom-css/12702-alture-namenschilder-team-projektdetail.css` | post 12702 | 10 custom_css block(s) | 879 |
| `html-widgets/12702-6542d28.html` | post 12702 element 6542d28 | HTML widget content, 13772 bytes | 13772 |
| `elementor/12750-alture-namenschilder-team-geteilt-projekt.json` | post 12750 (page) | Elementor document: Alture Namenschilder TeamShared shared project | 70850 |
| `custom-css/12750-alture-namenschilder-team-geteilt-projekt.css` | post 12750 | 2 custom_css block(s) | 175 |
| `elementor/13123-order.json` | post 13123 (page) | Elementor document: Order | 3009 |
| `elementor/13223-account-status-guide.json` | post 13223 (page) | Elementor document: Account status guide | 107773 |
| `elementor/13266-elementor-single-post-13266.json` | post 13266 (elementor_library) | Elementor document: Elementor Single News | 25899 |
| `custom-css/13266-elementor-single-post-13266.css` | post 13266 | 1 custom_css block(s) | 123 |
| `elementor/13365-elementor-loop-item-13365.json` | post 13365 (elementor_library) | Elementor document: Related News | 6289 |
| `custom-css/13365-elementor-loop-item-13365.css` | post 13365 | 2 custom_css block(s) | 366 |
| `elementor/13381-dashboard-news.json` | post 13381 (elementor_library) | Elementor document: Dashboard News | 6447 |
| `custom-css/13381-dashboard-news.css` | post 13381 | 2 custom_css block(s) | 354 |
| `elementor/13937-noslug-alture-namenschilder-teamshared-shared-project.json` | post 13937 (page) | Elementor document: Alture Namenschilder TeamShared shared project | 70893 |
| `custom-css/13937-noslug-alture-namenschilder-teamshared-shared-project.css` | post 13937 | 2 custom_css block(s) | 175 |
| `elementor/13983-order-card.json` | post 13983 (elementor_library) | Elementor document: Order Card | 61339 |
| `custom-css/13983-order-card.css` | post 13983 | 2 custom_css block(s) | 175 |
| `elementor/14241-special-wishes-form.json` | post 14241 (elementor_library) | Elementor document: Special Wishes Form | 30720 |
| `custom-css/14241-special-wishes-form.css` | post 14241 | 1 custom_css block(s) | 4854 |
| `custom-js/14241-special-wishes-eael-custom-js.js` | post 14241 page_settings.eael_custom_js | Essential Addons custom JS (page setting), 12189 bytes | 12189 |
| `custom-js/14241-special-wishes-form-eael-custom-js-postmeta.js` | post 14241 meta _eael_custom_js | Essential Addons custom JS (postmeta copy, differs from page setting) | 12187 |
| `elementor/14265-kontakt-menu.json` | post 14265 (elementor_library) | Elementor document: kontakt menu | 6373 |
| `elementor/15663-elementor-search-results-15663.json` | post 15663 (elementor_library) | Elementor document: Elementor Search Results #15663 | 9003 |
| `elementor/15739-noslug-elementor-header-15739.json` | post 15739 (elementor_library) | Elementor document: Elementor Header #15739 [NO _elementor_data in DB] | 496 |
| `elementor/15902-namensschilder.json` | post 15902 (page) | Elementor document: Namensschilder | 6463 |
| `elementor/15906-elementor-loop-item.json` | post 15906 (elementor_library) | Elementor document: Elementor Loop Item #15906 | 9906 |
| `custom-css/15906-elementor-loop-item.css` | post 15906 | 1 custom_css block(s) | 155 |
| `forms/forms.json` | posts 3040, 12466, 14241 | Elementor Pro form widget settings (4 forms) | 29466 |
| `snippets/1792-product-variations-js.js` | post 1792 (elementor_snippet, draft) | Custom Code: Product variations js, location elementor_body_end, priority 1 | 3643 |
| `snippets/2183-product-variants-css.css` | post 2183 (elementor_snippet, publish) | Custom Code: Product.css, location elementor_head, priority 1 | 11618 |
| `snippets/12433-project_main-css.css` | post 12433 (elementor_snippet, publish) | Custom Code: Project_main.css, location elementor_head, priority 1 | 1238 |
| `snippets/12463-dynamic-log-in.js` | post 12463 (elementor_snippet, draft) | Custom Code: dynamic log in, location elementor_body_end, priority 1 | 1134 |
| `snippets/13427-print-order-js.js` | post 13427 (elementor_snippet, publish) | Custom Code: print order js, location elementor_body_end, priority 1 | 887 |
| `snippets/13432-printorder-css.css` | post 13432 (elementor_snippet, publish) | Custom Code: printOrder.css, location elementor_body_end, priority 1 | 1991 |
| `snippets/13799-dashboard-filter.js` | post 13799 (elementor_snippet, publish) | Custom Code: dashboard filter, location elementor_body_end, priority 1 | 8852 |
| `snippets/14273-kontact-floating-button-js.js` | post 14273 (elementor_snippet, publish) | Custom Code: Kontact floating button.js, location elementor_body_end, priority 1 | 11960 |
| `snippets/15757-icon-style.css` | post 15757 (elementor_snippet, publish) | Custom Code: icon style, location elementor_body_start, priority 1 | 803 |
| `kit/kit-5-settings.json` | post 5 (kit) | Elementor kit: global colors, typography, all kit settings | 15318 |
| `wp/customizer-additional-css.css` | post 381 (custom_css) | Customizer Additional CSS for hello-elementor, verbatim post_content | 2647 |
| `wp/options.json` | wp_options | 126 options (requested + Elementor/EA/The Plus/theme settings) | 34640 |
| `wp/menus.json` | wp_terms nav_menu + nav_menu_item posts/meta | 4 nav menus, 39 items, locations | 35151 |
| `wp/child-theme/functions.php` | wp-content/themes/hello-theme-child-master/functions.php | Child theme functions.php (11791 bytes) | 11791 |
| `wp/child-theme/style.css` | wp-content/themes/hello-theme-child-master/style.css | Child theme style.css (625 bytes) | 625 |
