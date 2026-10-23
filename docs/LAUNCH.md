# Launch plan: classic theme and Suite 0.10.x on production

Written 2026-10-23 after the staging test run. Production state is mostly unverified from here: the repo has the production Suite (branch `production`, 0.9.111) but no production database or plugin list. Lines marked "unverified" must be checked on production before the rollout.

## 1. Where things stand

- Staging runs Suite 0.10.35 and theme 0.5.1 without Elementor. Elementor and Elementor Pro are deleted there.
- Tested on staging 2026-10-23: Berater, deep link to the product, buy form, Warenkorb, checkout with Vorkasse, thank-you page, invoice PDF, account, Anfrage drawer, Kontakt form, order types, phone layouts. All passed. Findings: `docs/suite/PLUGIN-DOCUMENTATION.md` Section 16, numbers 21 to 31.
- Speed on staging (guest, no page cache): listing 5.5 s to 0.6 s, product page 3.4 s to 0.8 s. Details: `docs/theme/README.md` "Performance".
- The theme renders with built-in defaults when a field was never saved (`inc/fields.php`, `inc/options.php`), so it does not need the staging database to show something sensible.

## 2. Decisions for the owner

1. **Rollout method.** Recommended: A.
   - A: install theme and Suite on the production site and copy the few staging settings across (section 5). Production keeps its orders, customers and the InfoplusAPI tables.
   - B: copy the staging database to production. Not recommended: staging holds test orders and test Anfragen, its settings point at the staging host, and production orders placed since the copy would be lost.
2. **Pages to retire.** Published on staging (and probably on production, unverified), not part of the new site:
   - `xmas` (9999): only a Slider Revolution shortcode. Slider Revolution is deleted on staging, so the page shows the raw shortcode.
   - `3d-product-view` (2836): three `[3d_viewer]` shortcodes, a test page.
   - `alture-namenschilder-team-projektdetail` (12702), `alture-namenschilder-team-geteilt-projekt` (12750), `account-status-guide` (13223, English): concept pages from the Elementor time.
   Suggestion: set them to draft. A 301 is only worth it if they have traffic (unverified).
3. **Brand in titles.** WordPress uses the site title as typed, `infoplus` in lower case, and every browser tab and search result ends with it. Settings > General.
4. **Payment methods at launch.** WooPayments has no account connected on staging; the PayPal and Klarna plugins are active. Decide which ones go live.

## 3. Needs data from Infoplus (blocks launch)

- Bank details for Überweisung (staging has placeholders, finding 22).
- Inquiry recipient address and the shop's sender address (staging sends from `hostinger@alture.io`).
- Front page texts, Berater copy and the sample offer (the Berater traits are placeholders, Section 22).
- Tagline for Settings > General (used as the front page meta description; today the hero text about Atlantis is used).
- Product short descriptions, if the current ones should not be the search snippets.

## 4. Pre-launch checklist on production

Security:
- [ ] Rotate the WooCommerce REST key that was committed in the old repository (CLAUDE.md "State of the repo").
- [ ] Move `INFOPLUS_API_KEY` and `INFOPLUS_IFE_TOKEN` into wp-config.php and rotate both (CLAUDE.md "Secrets").
- [ ] Know that every InfoplusAPI route is still public (finding 8, roadmap R-13). Not a launch blocker for this rollout, but the next security task.
- [ ] `test-orders.php` and `test-orders-advanced.php` disappear with the Suite 0.10.35 upload (finding 26). Check the URLs return 404 afterwards.
- [ ] `WP_DEBUG` off (finding 27 is staging; production unverified).
- [ ] Settings > General "Anyone can register": on staging it is on with role subscriber, which opens `wp-login.php?action=register` to bots. WooCommerce has its own registration on the log-in page. Suggest off (production unverified).
- [ ] Payment plugins in the right mode: on staging PayPal reports environment PRODUCTION and WooPayments test mode off. Make sure staging cannot take real payments, and production is live only for the chosen methods.

Settings:
- [ ] Timezone Europe/Berlin (staging is UTC+0 with no city set; order dates, e-mails and invoices use it).
- [ ] Cash on delivery title in German (finding 21).
- [ ] Bank details (finding 22).
- [ ] Site language Deutsch (Sie) (`de_DE_formal`, set on staging; production unverified).
- [ ] My Account page setting: see `docs/theme/ACCOUNT.md` (works without a change; the optional switch and its rollback are there).

Plugins:
- [ ] Secure Custom Fields active instead of free ACF (the options page in `inc/options.php` needs it; without it the defaults show).
- [ ] Elementor and its add-ons: deactivate at rollout, delete one or two weeks later.
- [ ] Plugins deleted on staging on 2026-10-23: delete the same ones on production after the rollout (list unverified here; take it from the staging Plugins screen).
- [ ] LiteSpeed Cache active (section 7).

## 5. Rollout steps (method A)

Plan a quiet hour. Steps 3 to 6 take a few minutes; the shop is in maintenance mode meanwhile, because Suite 0.10.35 no longer has the Elementor widgets the old product template uses.

1. Backup: Hostinger backup of files and database, plus an All-in-One WP Migration export. Note the active theme name.
2. Compare: build both ZIPs with `tools/build-zips.sh` from the commit that is on staging and check nothing on staging was changed outside the repo.
3. Maintenance mode on (Hostinger Tools or LiteSpeed).
4. Upload `infoplus-suite.zip`, "Replace current with uploaded". The folder stays `Infoplus Suite`.
5. Upload `infoplus-theme.zip` and activate the theme "Infoplus".
6. Deactivate Elementor, Elementor Pro and their add-ons.
7. Content and settings that live in the staging database:
   - Front page fields (124 values on page 8 on staging). Re-enter them in the page editor, or copy the `wp_postmeta` rows of page 8 with an SQL export if page 8 is the front page on production too (unverified).
   - Create the page `berater` (empty content; the template is picked by slug).
   - Menus: assign the four theme locations in Appearance > Menus (Hauptmenü, Mobilmenü, Footer: Informationen, Footer: Rechtliches). The old menus 19, 20, 21 and 247 can be reused (`legacy/wp/menus.json`).
   - Kontakt, FAQ and Unternehmen fields (one saved value each on staging).
   - Theme options page (nothing saved on staging; defaults are the old site texts).
8. Permalinks: open Settings > Permalinks and save once (flushes rewrite rules).
9. Maintenance mode off, LiteSpeed "Purge all".
10. Smoke test (section 6). If a core flow fails and cannot be fixed in minutes, roll back (section 8).

## 6. Smoke test after rollout

As a guest and logged in, on desktop and at 390 px:
- Home, Namensschilder listing and one category, a product with many variations (Atlantis), Berater with one full run.
- Product: change model, fastening and quantity, Staffelpreise follow; "In den Warenkorb".
- Warenkorb, header cart preview, Kasse until the order button. One real order with Vorkasse, then cancel it.
- Thank-you page, order e-mail, invoice PDF, account: orders, view order, addresses.
- Anfrage drawer on a product, Kontakt form. Check the mails arrive at the Infoplus address.
- Editor flow: "Im Editor gestalten" on an editable model opens editor.infoplus.de, and an order from the editor lands in the Warenkorb (InfoplusAPI `cart/updateCart`). Not testable on staging.
- Old links: `/order/{n}/` goes to the account order view, `/account/` and `/log-in/` work.
- Browser console without errors.

## 7. Cache plan

- LiteSpeed Cache is installed on staging but inactive, and `.htaccess` has the LiteSpeed blocks, so the server is LiteSpeed. Production probably uses it already (a comment in `inc/product.php` mentions LiteSpeed rewriting inline scripts; unverified).
- Page cache on for guests. WooCommerce marks Warenkorb, Kasse and account pages as not cacheable. Check after activation that `/log-in/` and `/order/` are not served from the cache (unverified).
- The header cart preview and the account menu are cache safe: the cart preview fetches its content through the WooCommerce fragments request, and the guest menu is the same for every guest (`docs/theme/ACCOUNT.md`).
- Object cache (Memcached or Redis, whichever Hostinger offers for the plan): turn on after the rollout is stable and compare the server times again.
- CSS and JS combine or minify: leave off at launch. Turn on one option at a time later and retest the product page and the Kasse.
- After every theme or Suite upload: Purge all.

## 8. Rollback

1. Maintenance mode on.
2. Activate Elementor and Elementor Pro again (deactivated, not deleted, in step 6).
3. Activate the previous theme (noted in step 1).
4. Upload the production Suite: `git archive --format=zip --prefix="Infoplus Suite/" -o dist/infoplus-suite-production.zip production:wp-content/plugins/infoplus-suite`. `tools/build-zips.sh` refuses this branch on purpose (0.9.111 has no `INFOPLUS_SUITE_VERSION`).
5. Purge all, maintenance off.
If the database was changed in a way that breaks the old site, restore the backup from step 1. Orders placed after the backup would then have to be re-entered, so prefer steps 1 to 5.

## 9. Search and URLs

- URLs stay the same with method A: same pages, same product and category slugs. `/order/...` is redirected by the theme. New: `/berater/`.
- Theme output since 0.5.1 (`inc/seo.php`): meta description, Open Graph and Twitter tags, Product JSON-LD with the "ab" prices. WordPress core: title, canonical, robots meta, `wp-sitemap.xml`. No SEO plugin is installed; if one is added later, the theme output switches itself off.
- Staging is indexable today (Settings > Reading "Discourage search engines" is off). Switch it on for staging. Never copy that setting to production: with method B it would come along with the database.
- After launch: submit `wp-sitemap.xml` in Google Search Console and check the product pages in the Rich Results Test.
