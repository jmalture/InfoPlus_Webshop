# Customer account and order confirmation

How the classic theme renders the customer account (`/account/`), the login page (`/log-in/`), old order links (`/order/...`) and the order confirmation (order received). Code: `infoplus/inc/account.php`. Built and tested locally on 2026-10-20 (WooCommerce 10.6.1). Nothing was changed on staging.

## Routing

| URL | What renders | Notes |
|---|---|---|
| `/account/` and its endpoints `/account/orders/`, `/account/view-order/{id}/`, `/account/edit-address/`, `/account/edit-address/billing/`, `/account/edit-account/`, `/account/lost-password/` | `wc-account.php` + WooCommerce My Account shortcode with the theme's `woocommerce/myaccount/*` templates | WooCommerce endpoints, so every plugin hook works (Germanized, PDF Invoices). |
| `/log-in/` (page 12381) | `wc-account.php` with `woocommerce/myaccount/form-login.php` | Login and, when WooCommerce registration is on, "Neues Konto". |
| `/order/{n}/`, `/order/?order_id={n}`, `/order/` | 302 to `/account/view-order/{n}/` (or `/account/orders/`) | The Suite URLs keep working. |
| `/kasse/order-received/{id}/?key=...` | `wc-page.php` frame + `woocommerce/checkout/thankyou.php` | Suite thank-you block switched off there (see below). |

Which page is the account page:

1. The page in WooCommerce > Settings > Advanced > "My account page" (`woocommerce_myaccount_page_id`) when that page is published.
2. Otherwise the published page with slug `account` (filter `woocommerce_get_myaccount_page_id` in `inc/account.php`).

Staging today: the setting points to page 29 `my-account`, which is a draft, so rule 2 applies and `/account/` (page 12466) is the account page. This works without any database change. Tested locally in both states.

Guests on the account are sent to `/log-in/?redirect_to=<where they were>`; after login they come back there. `/account/lost-password/` and the password reset link stay open for guests. Logged-in visitors on `/log-in/` go to `redirect_to` or `/account/`.

### Recommended one-time settings change (staging, later production)

Not required for the theme to work, but it makes the setting say what the site does, and the fallback filter then does nothing.

- Change: WooCommerce > Settings > Advanced > Page setup > "My account page": choose **Account** (ID 12466). Save. Then Settings > Permalinks > Save (flushes rewrite rules once).
- Equivalent WP-CLI: `wp option update woocommerce_myaccount_page_id 12466 && wp rewrite flush`
- Rollback: set it back to 29 (`wp option update woocommerce_myaccount_page_id 29 && wp rewrite flush`). With the theme active, `/account/` keeps working either way. With the old Elementor setup the value 29 is what it was before.
- Leave page 29 as draft. Do not delete 12466, 12381 or 13123: the theme finds them by slug (`account`, `log-in`, `order`).

## What the theme switches off in the Infoplus Suite (theme side, Suite unchanged)

Removed with `ip_unhook_suite()` (looks the callbacks up in `$wp_filter`, same approach as the cart styles in `inc/shop.php`). Under any other theme the Suite behaves as before.

| Suite callback | Why | Where |
|---|---|---|
| `UserDashboard::redirectGuestUsers` (template_redirect) | Sent every guest path starting with `/account` to `/log-in/` without a way back; also blocked lost password and the reset link. Replaced by `ip_account_redirects()`. | every request |
| `UserOrders::renderThankYouContent` (woocommerce_thankyou 1, woocommerce_before_thankyou), `filterThankYouText`, `enqueueCheckoutStyles` (wp_head), style `infoplus-woo-pages`, shortcode `[infoplus_order_received]` (returns empty) | The theme renders the confirmation itself. | order received only |
| Scripts `infoplus-user-dashboard`, `infoplus-user-orders`, style `infoplus-user-orders` (dequeued) | Not used by the theme's account views. | account and login pages |

The Suite's login redirect (always `/account/`, priority 999) stays; the theme adds a later filter (1000) that honours the validated `redirect` field of its own login form.

Switches: filter `ip_account_enabled` (false = theme account handling off), `ip_account_own_thankyou` (false = WooCommerce template + Suite block on order received), `ip_account_register_role`, `ip_account_orders_per_page` (20), `ip_account_status_filters`, `ip_account_unique_item_meta`, `ip_account_can_reorder`, `ip_account_avatar_upload`.

## Feature inventory

Old sources: Elementor page 12466 `account` (tabs Übersicht, Bestellungen, Projekte, Einstellungen), 12381 `log-in` (Essential Addons login/register), 13123 `order` (Suite OrderDetails widget), 13983 order card, 13223 status guide, 12702 / 12750 / 13937 project pages, 29 `my-account` (draft, Elementor My Account widget), Suite UserDashboard and UserOrders modules, snippets 12463, 13427, 13432, 13799.

| Feature | Old source | New implementation | Status |
|---|---|---|---|
| Header: avatar, "{Name} Dashboard", "Händlerkonto", Abmelden | 12466 header (image with Suite avatar tag, heading, button) | Profile card in the aside: avatar (Suite meta `infoplus_user_avatar`, else initials, no Gravatar), name, company, account status pill; "Abmelden" in the menu | rebuilt |
| Tabs Übersicht / Bestellungen / Projekte / Einstellungen | 12466 nested tabs | WooCommerce menu: Übersicht, Bestellungen, Adressen, Kontodaten, Abmelden (Downloads only when there are any) | rebuilt (Projekte: see below) |
| Greeting "Willkommen zurück, {Name}" | 12466 heading with user-info tag | H1 of the dashboard | rebuilt |
| Stat cards: Bestellungen gesamt, In Bearbeitung, Abgeschlossen, Gesamtausgaben | Suite UserStatCard widget | Four tiles, real counts, links to the filtered order list; Gesamtausgaben = `wc_get_customer_total_spent()` as before | rebuilt |
| Static row "Projekte gesamt 12 / Bestellungen 28 / Geteilte Projekte 5" | 12466, fixed numbers | not shown (placeholder data) | not rebuilt, placeholder |
| "Letzte Aktivitäten" | 12466, three fixed entries | "Letzte Bestellungen": the three latest real orders with status, total, invoice | rebuilt with real data |
| "Neuigkeiten" (news loop) | 12466 loop grid, template 13381 | Latest three `news` posts as a link list (only when the post type exists and has posts) | rebuilt |
| Orders list with search, status filter (Alle / In Wartestellung / Abgeschlossen / In Bearbeitung), reset, "Mehr laden", "Bestellung ansehen" | Suite UserOrdersGrid, snippet 13799 | Status filter as links with counts (server side), quick search over the listed orders (JS), table that becomes cards on phones, pagination 20 per page, column "Referenz" (PO number), status pills | rebuilt |
| Invoice download | Suite widgets (button commented out, "hidden for now") | PDF Invoices & Packing Slips' own My Account action (`invoice`, setting "always" on staging) in the list, the dashboard, the order view and the confirmation; absent when the plugin is absent | rebuilt (plugin not installed locally: degradation tested, action markup tested with WooCommerce's action API) |
| Order detail: number, date, payment, status, customer, addresses, items with model meta, SKU, totals, back link | Suite OrderDetails on `/order/{n}/` | `/account/view-order/{id}/`: summary (date, status, payment, PO, total incl. VAT note), actions, order updates, items (picture, name, SKU, model and option meta, quantity, total), WooCommerce totals, billing and shipping address | rebuilt |
| Print order | OrderDetails print button, snippets 13427 / 13432 | "Drucken" button + print styles in `order.css` | rebuilt |
| "E-Mail-Kunde" mailto button | OrderDetails | not shown: it mails the customer themselves, which makes no sense in the customer's own view | dropped on purpose |
| Reorder | none | "Erneut bestellen" (WooCommerce order again) only for completed orders whose items have no editor design or uploaded files; crafted links for other orders are refused | new |
| Addresses (billing / shipping) | Suite profile form (`[infoplus_profile_settings]`, billing only) | WooCommerce edit-address forms; company field kept although WooCommerce hides it (see findings) | rebuilt |
| Name, e-mail, password | Elementor forms 9c3adcd, 20ce51c + Suite REST `user/profile`, `user/password` | WooCommerce account details form (nonce, current password check) | rebuilt |
| Umsatzsteuer-ID (`infoplus_vat_id`) | Suite profile form | Field in the account details form, same meta | rebuilt |
| Profile picture upload | HTML widget 8cbcd03 + Suite REST `user/avatar` | Upload in Kontodaten through the same Suite route (fetch, nonce), live status text | rebuilt (needs the Suite) |
| Account status "Händler" + link to status guide | 12466 fixed text, page 13223 | Pill with the user's role name (`sales` = "Händler"); no link | rebuilt; guide not linked (finding) |
| Projekte tab (editor projects, team sharing, project detail, shared project) | 12466 tab 3, pages 12702, 12750, 13937 | none | not rebuilt: all cards, dates and buttons are fixed mockup content; no page calls `infoplus/v1/customerdashboard*` (finding) |
| Login, "Angemeldet bleiben", "Passwort vergessen?" | Essential Addons widget on 12381 | WooCommerce login form in the theme design; lost password and reset via WooCommerce endpoints | rebuilt |
| Registration (role `sales`) | Essential Addons register form (username, e-mail, password, custom welcome mail with the password) | WooCommerce registration (enabled on staging; e-mail only, WooCommerce sends a set-password link); role `sales` kept for this form | rebuilt, mail differs (finding) |
| Login guard / logged-out behaviour | Suite redirects, `[infoplus_login_guard]` | `ip_account_redirects()` with redirect back | rebuilt |
| Logout without confirmation | Suite `skipLogoutConfirmation` | WooCommerce logout endpoint with nonce (no confirmation); Suite hook untouched | kept |
| Order confirmation | Suite thank-you block | Theme `checkout/thankyou.php` | rebuilt |

## Header quick menu

The account icon in the header (1101 px and up) is a disclosure button with a panel: click or tap on any device, mouse hover with the mega menu's intent delay (open after 120 ms, close 250 ms after leaving icon and panel), Escape closes and returns focus, a click outside or focus leaving closes it (`assets/js/site.js`). Below 1101 px the same menu is a "Mein Konto" / "Anmelden" submenu in the mobile drawer (tap only). Code: `inc/account-menu.php` (data, built once per request), `template-parts/account-menu.php` (markup), styles in `header.css`.

- Logged in: avatar, name, company, status pill; the last three orders (number, date, status, total, link to the order view; download icon when PDF Invoices offers the `invoice` account action); "Alle Bestellungen"; Übersicht, Bestellungen, Adressen, Kontodaten; Abmelden (`wc_logout_url()`). One `wc_get_orders()` query, no REST call.
- Guests: Anmelden (login page with `redirect_to` the current page), Konto erstellen (only when WooCommerce registration is on, links to the registration card), Passwort vergessen.
- Page cache: user-specific markup only for logged-in users (not served from the guest cache); the guest panel is the same for every guest of a URL.
- The Suite's Elementor-era dropdown (`infoplus-menu-dropdown`, it looks for the widget `#log-in`) is dequeued by the theme.
- Tested locally (2026-10-20) with a stand-in for the PDF Invoices action (the plugin is not installed locally).

## Order confirmation (order received)

Success card (message, order number, date, status, payment method, Bestellreferenz when given, e-mail, total with VAT note, "Zum Kundenkonto" / "Weiter einkaufen", for guests "Weiter einkaufen" / "Anmelden oder Konto anlegen"), then a payment card with whatever the gateway prints on `woocommerce_thankyou_{gateway}` (bank transfer: instructions and bank details from the BACS settings), then `woocommerce_thankyou` (items, totals, addresses). Shipping appears only when the order has a shipping line (WooCommerce's own totals). Fixed against the four problems seen on staging: font, "Versand: Kostenlos", missing bank details, missing PO number, oversized phone.

## Findings (not fixed here)

1. **Bank details on staging look like placeholders**: IBAN `DE0001111111112345678`, BIC `SPKXXX`, account number `12345678` (WooCommerce > Payments > Überweisung). They now show on every confirmation. Replace before launch.
2. **Cash on delivery title is English** on staging ("Cash on delivery", description "Pay with cash upon delivery."). Shows on the confirmation and in the account.
3. **Company field hidden** (`woocommerce_checkout_company_field = hidden`). The checkout asks for no company in a B2B shop. The account address form shows it anyway (theme filter); checkout untouched. Tested locally: every Checkout block order then saves an empty company back to the customer (`billing_company`), so a company entered in the account is lost at the next order. Set the field to optional (or required) in the WooCommerce settings / Checkout block.
4. **Projekte area**: no data source is wired. InfoplusAPI `customerdashboard` counts projects through the IFE API but returns a fixed `customer_shared_projects: 2`, and nothing calls it. Needs an IFE "projects of a customer" endpoint and a decision on sharing before it can be built.
5. **Account status guide** (page 13223) is an English mockup (tiers Shopper / Reseller 15 % / Partner 25 %). Not linked.
6. **Registration mail**: the old Essential Addons mail sent the password in clear text; WooCommerce sends a set-password link instead (better), but the custom German welcome text is gone. Adjust the WooCommerce "New account" e-mail if wanted. Update 2026-10-20: the theme now overrides `woocommerce/emails/customer-new-account.php` with a German B2B welcome (formal Sie, set-password button, account features); see README.md, "E-mails".
7. **Suite avatar route** (`POST infoplus/v1/user/avatar`) lets every logged-in user put files into the media library and trusts the browser's MIME type. Works, but worth tightening (size limits, `wp_check_filetype_and_ext`, no media library entry).
8. **Suite user-dashboard.js** loads on every page for logged-in users and calls `GET infoplus/v1/user/profile` each time (theme dequeues it only on account pages).
9. **Checkout "Anmelden" link** is rewritten by the Suite to `/log-in/` without `redirect_to`, so a customer who logs in from the checkout lands in the account, not back at the checkout.
10. **Reorder for designed items**: orders with editor designs (`project_id`) or Sonderkontur uploads cannot be reordered safely by WooCommerce; a "reopen in editor" link would need the IFE project URL scheme (unverified).
11. Local tests ran without the German WooCommerce language pack, so WooCommerce strings (form labels, totals labels, status names, dates) were English locally; staging has de_DE.

## Tests (local, acc-wp on port 8920, WooCommerce 10.6.1, Suite feature/view-renderer)

Staging-like setup: My Account option on a draft page, pages `account` / `log-in` / `order`, role `sales`, BACS and COD, 19 % VAT, prices net / shown gross, company field hidden, a "Händler" customer with four orders (BACS on-hold with PO, COD processing with shipping, completed, completed with editor meta). Checked: guest redirects with return, login and return to the order, logged-in `/log-in/`, dashboard, orders search and filter, view-order for each order, reorder only where safe and refused for crafted links, VAT ID save, avatar upload, address save with company, logout, lost password and reset mail, registration (role `sales`), `/order/{n}/` redirects, real checkouts with BACS (PO) and COD, confirmation without shipping, Suite inactive, `ip_account_own_thankyou` false, setting switched to the account page and back. No horizontal overflow at 390 px. `php -l` and `node --check` clean.
