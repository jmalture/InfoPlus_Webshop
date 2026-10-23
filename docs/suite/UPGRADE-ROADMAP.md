# Infoplus Suite: Upgrade Roadmap

> **Created:** 2026-02-19  
> **Status re-checked:** 2026-09-17 against version 0.9.111 (commit `f01aca6`)  
> **Reference:** Read `PLUGIN-DOCUMENTATION.md` first for full architecture context, and `PIPELINE.md` for how the Suite relates to InfoplusAPI.

Each item below is written as a self-contained implementation brief. When we start work on any item, read the relevant section: it contains the exact files, lines, and approach needed. Each item now carries a **Status (2026-09-17)** line with the evidence from the current code. Nothing from the original briefs was removed; line numbers quoted in the briefs may have shifted.

## Status summary (2026-09-17)

| Item | Status | One-line evidence |
|------|--------|-------------------|
| R-01 Secure Sonderkontur endpoints | open | Four routes still `'permission_callback' => '__return_true'` |
| R-02 Delete duplicate UserDashboard assets | open | Nine duplicate files still present, none enqueued, some drifted |
| R-03 Unify REST namespace | open | All non-IkonaIFE routes still under `infoplus/v1`; one more added in 0.9.111 |
| R-04 WC session for uploads | open | `getStorageKey()` and transients unchanged |
| R-05 Try/catch module registration | open | `Plugin::registerModules()` unchanged, now nine modules |
| R-06 Remove SyncService self HTTP request | open, question answered | Still `wp_remote_get(rest_url('infoplus/v1/products'))`; source is InfoplusAPI on the same install |
| R-07 Extract inline CSS | partially done | Cart/checkout styles moved to `woo-pages.css`; thank-you page and login guard still inline; new inline CSS added in Custom404 |
| R-08 Unify version management | partly done (0.10.33) | Header and `Plugin::VERSION` are one number (`INFOPLUS_SUITE_VERSION`, checked by `tools/build-zips.sh`). The ProductDetailSuite and IkonaIFE constants are still separate; ProductDetailSuite VERSION also keys a transient |
| R-09 Translate hardcoded German | open | 14 PHP files with German characters, 82 lines outside `__()`; more added since |
| R-10 class_exists guards | done as far as it goes | Only two call sites exist, both guarded |
| R-11 Module toggle | open | No `disabled_modules` option or filter in the code |
| R-12 REST endpoint tests | open | No `tests/` directory |
| R-13 Merge InfoplusAPI into the Suite | new, open | Decision taken; reference copy committed |
| R-14 Invalidate the bulk prices transient | new, open | Added in 0.9.111 with no invalidation |
| R-15 Remove hardcoded attribute keys in product context JSON | new, open | Added in 0.9.111 |

---

## Priority Legend

| Label | Meaning |
|-------|---------|
| 🔴 CRITICAL | Security risk or data loss potential, fix ASAP |
| 🟡 MEDIUM | Architecture improvement, do before next major feature |
| 🟢 LOW | Code quality / maintainability, do when touching related code |

---

## 🔴 R-01: Secure Sonderkontur Upload Endpoints

> **Status (2026-09-17): open.** `src/Modules/WooSingleProductAddons/Module.php` lines 184 to 205 still register all four routes with `'permission_callback' => '__return_true'`. No file count or total size cap in `handleFileUpload()`. Step 4 is already true: `sonderkontur-upload-toggle.js` sets `X-WP-Nonce` on every XHR (lines 81, 153, 182, 254), so the PHP side can start verifying it without a JS change. Related finding from the same pass: the admin-ajax action `infoplus_add_to_cart` (`ajaxAddToCart()`, priv and nopriv) has no nonce or referer check either. Fix it in the same change.

**Problem:** All four Sonderkontur REST endpoints use `'permission_callback' => '__return_true'`, anyone on the internet can upload files, list them, or delete them. No rate limiting, no file count cap.

**Risk:** Bot abuse, disk filling, session hijacking of uploaded files via spoofed IP+UA hash.

**Files to modify:**
- `src/Modules/WooSingleProductAddons/Module.php`

**Implementation:**

1. In `registerRestRoutes()`, replace `'permission_callback' => '__return_true'` on all 4 Sonderkontur routes with a proper callback:

```php
'permission_callback' => function(\WP_REST_Request $request) {
    // Require valid WP REST nonce (sent via X-WP-Nonce header)
    // This verifies the request came from our frontend, not a random bot
    return wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest') !== false;
}
```

2. Add file count limit in `handleFileUpload()`, before processing, check:
```php
$existing = $this->getUploadedFiles();
if (count($existing) >= 10) {
    return new \WP_REST_Response([
        'success' => false,
        'message' => __('Maximum 10 files per session', 'infoplus-suite'),
    ], 429);
}
```

3. Add total size limit (e.g., 100MB per session) in the same method.

4. The frontend JS in `sonderkontur-upload-toggle.js` already sends `X-WP-Nonce` via the config object `window.infoplusSonderkonturUpload.nonce`, verify it actually includes the header in fetch/XHR calls. If not, add it.

**Test:** Try calling `POST /wp-json/infoplus/v1/sonderkontur/upload` with no nonce header via curl, should get 401. Try with valid nonce from browser, should work.

---

## 🔴 R-02: Delete Duplicate Assets from UserDashboard

> **Status (2026-09-17): open.** All nine files listed below still exist in `src/Modules/UserDashboard/Assets/`. `UserDashboard\Module::enqueueFrontend()` still loads only `menu-dropdown.*` and `user-dashboard.js`; `registerWidgets()` adds `user-stat-card.css` (new, keep). The copies have started to drift: in 0.9.111 `model-selector.js`, `product-image.js` and `variation-dropdowns.js` received the same small fixes as the originals, but `product-state.js` did not receive the deferred-loading code. Deleting them is still safe (no PHP references).

**Problem:** `src/Modules/UserDashboard/Assets/` contains 10 files that are exact copies of files from `ProductDetailSuite/Assets/`. They are never enqueued by UserDashboard's `enqueueFrontend()` method (which only loads `menu-dropdown.*` and `user-dashboard.js`). These duplicates will cause bugs when someone edits one copy but not the other.

**Duplicate files to DELETE from `src/Modules/UserDashboard/Assets/`:**
- `admin-product-attribute-images.css` ← copy of ProductDetailSuite
- `admin-product-attribute-images.js` ← copy of ProductDetailSuite
- `admin-variation-fields.css` ← copy of ProductDetailSuite
- `admin-variation-fields.js` ← copy of ProductDetailSuite
- `model-selector.js` ← copy of ProductDetailSuite
- `product-detail-suite.css` ← copy of ProductDetailSuite
- `product-image.js` ← copy of ProductDetailSuite
- `product-state.js` ← copy of ProductDetailSuite
- `variation-dropdowns.js` ← copy of ProductDetailSuite

**Files to KEEP in UserDashboard/Assets/:**
- `menu-dropdown.css` ✅ (unique, enqueued)
- `menu-dropdown.js` ✅ (unique, enqueued)
- `user-dashboard.js` ✅ (unique, enqueued)

**Implementation:** Delete the 9 duplicate files listed above. No code changes needed, nothing references them. Do a global search for `UserDashboard/Assets/product-state` (or any of the duplicate filenames) to confirm zero references.

**Test:** Full page load of product pages and user dashboard, nothing should break since these files were never loaded from this location.

---

## 🔴 R-03: Unify REST Namespace

> **Status (2026-09-17): open.** Every route in `UserDashboard`, `UserOrders`, `WooSingleProductAddons` and `ProductDetailSuite` is still registered under `infoplus/v1`. 0.9.111 added one more there: `GET /product/{id}/variations`. Two things the brief did not know: (a) `product-state.js` `loadDeferredVariations()` falls back to the literal string `/wp-json/infoplus/v1/` when `infoplusProductConfig` is missing, so the JS needs a change too; (b) the "external Ikona plugin" is InfoplusAPI, which will be merged into the Suite (R-13). After the merge `infoplus/v1` still has to exist for the routes the editor calls (`cart/updateCart`) and the sync dashboard calls (`products/synchronization`). Do R-03 together with R-13 so the namespaces are decided once.

**Problem:** The plugin uses two namespaces, `infoplus-suite/v1` (IkonaIFE admin endpoints) and `infoplus/v1` (everything else). The `infoplus/v1` namespace is shared with the external Ikona IFE plugin, creating collision risk.

**Current layout:**

| Namespace | Module | Routes |
|-----------|--------|--------|
| `infoplus-suite/v1` | IkonaIFE | `/products`, `/products/{id}/sync-result`, `/products/{id}/sync-status`, `/products/clear-cache` |
| `infoplus/v1` | UserDashboard | `/user/profile`, `/user/password`, `/user/avatar` |
| `infoplus/v1` | UserOrders | `/orders`, `/orders/{id}` |
| `infoplus/v1` | WooSingleProductAddons | `/sonderkontur/*` |
| `infoplus/v1` | ProductDetailSuite | `/product/{id}/detail`, `/attribute-terms/{attribute}` |
| `infoplus/v1` | **External Ikona plugin** | `/products`, `/products/synchronization` |

**Target:** Move ALL Infoplus Suite routes to `infoplus-suite/v1`. Leave `infoplus/v1` exclusively for the external Ikona plugin.

**Files to modify:**

1. **`src/Modules/UserDashboard/Module.php`**
   - `registerRestRoutes()`: Change all `'infoplus/v1'` → `'infoplus-suite/v1'`
   - `enqueueFrontend()`: Change `rest_url('infoplus/v1/')` → `rest_url('infoplus-suite/v1/')` in `wp_localize_script`

2. **`src/Modules/UserOrders/Module.php`**
   - `registerRestRoutes()`: Change `'infoplus/v1'` → `'infoplus-suite/v1'`
   - `enqueueFrontend()`: Change `rest_url('infoplus/v1/')` → `rest_url('infoplus-suite/v1/')` in `wp_localize_script`

3. **`src/Modules/WooSingleProductAddons/Module.php`**
   - `registerRestRoutes()`: Change `'infoplus/v1'` → `'infoplus-suite/v1'`
   - `injectUploadSettings()`: Change `rest_url('infoplus/v1/')` → `rest_url('infoplus-suite/v1/')`

4. **`src/Modules/ProductDetailSuite/Module.php`**
   - `registerRestRoutes()`: Change `'infoplus/v1'` → `'infoplus-suite/v1'`
   - `enqueueFrontendAssets()`: Change `rest_url('infoplus/v1/')` → `rest_url('infoplus-suite/v1/')` in `wp_localize_script`

5. **Frontend JS files** that construct REST URLs from the localized config, these should already use the `restUrl` variable from `wp_localize_script`, so changing the PHP side should be sufficient. But verify:
   - `UserDashboard/Assets/user-dashboard.js`, uses `infoplusUserDashboard.restUrl`
   - `UserOrders/Assets/user-orders.js`, uses `infoplusUserOrders.restUrl`
   - `WooSingleProductAddons/Assets/sonderkontur-upload-toggle.js`, uses `infoplusSonderkonturUpload.restUrl`

**Important:** The IkonaIFE dashboard.js calls to `config.syncApiUrl` (`/infoplus/v1/products/synchronization`) must NOT change, that's the external Ikona plugin endpoint.

**Test:** Hard refresh all pages. Test user dashboard profile save, order list loading, file upload, product detail page data loading.

---

## 🟡 R-04: Replace Transient File Storage with WC Session

> **Status (2026-09-17): open.** `getStorageKey()` is still referenced five times in `WooSingleProductAddons/Module.php`, storage is still `get_transient` / `set_transient` with `DAY_IN_SECONDS` (lines 121 to 141), `cleanupOldTransients()` is still hooked to `wp_scheduled_delete`, and `injectUploadSettings()` still sends `storageKey` to the browser. No change in the 0.9.93 to 0.9.111 diff.

**Problem:** Sonderkontur uploaded files are stored in WordPress transients keyed by session cookie hash. Transients can be evicted at any time by object cache (Redis/Memcached), causing silent file loss between upload and add-to-cart.

**Files to modify:**
- `src/Modules/WooSingleProductAddons/Module.php`

**Implementation:**

Replace the three storage methods with WooCommerce session storage:

```php
private function getUploadedFiles(): array
{
    if (!WC()->session) return [];
    $files = WC()->session->get('sonderkontur_files');
    return is_array($files) ? $files : [];
}

private function saveUploadedFiles(array $files): bool
{
    if (!WC()->session) return false;
    WC()->session->set('sonderkontur_files', $files);
    return true;
}

private function clearUploadedFiles(): bool
{
    if (!WC()->session) return false;
    WC()->session->set('sonderkontur_files', null);
    return true;
}
```

Also:
- Remove `getStorageKey()` method entirely (no longer needed)
- Remove `cleanupOldTransients()` method and its `wp_scheduled_delete` hook
- Remove `storageKey` from `injectUploadSettings()` output (was debug-only)

**Caveat:** WC session requires WooCommerce to be initialized. The REST endpoints fire early, verify `WC()->session` is available in REST context. If not, you may need to call `WC()->initialize_session()` or fall back to transients for REST-only requests.

**Test:** Upload files → verify they persist → add to cart → verify files attach. Repeat after clearing object cache.

---

## 🟡 R-05: Wrap Module Registration in Try/Catch

> **Status (2026-09-17): open.** `src/Plugin.php` `registerModules()` still builds a plain array of `new` instances and calls `register()` in a loop with no try/catch. The list now has nine entries (Custom404 was added), so the code sample below needs `Modules\Custom404\Module::class` appended. The entry point `infoplus-suite.php` does catch `\Throwable` around `boot()`, so a failing module still produces an admin notice, but it takes the whole plugin down with it.

**Problem:** If any module constructor throws an exception, the entire plugin fails to load. One broken module kills all 8.

**File to modify:**
- `src/Plugin.php` → `registerModules()`

**Implementation:**

```php
private function registerModules(): void
{
    $moduleClasses = [
        Modules\WooSingleProductAddons\Module::class,
        Modules\WooProductVariationFields\Module::class,
        Modules\EditorLinkSettings\Module::class,
        Modules\IkonaIFE\Module::class,
        Modules\ElementorUserAvatar\Module::class,
        Modules\UserDashboard\Module::class,
        Modules\UserOrders\Module::class,
        Modules\ProductDetailSuite\Module::class,
    ];

    $this->visibleModules = [
        'editor-link-settings',
        'ikona-ife',
    ];

    foreach ($moduleClasses as $class) {
        try {
            $module = new $class();
            if ($module instanceof ModuleInterface) {
                $module->register();
                $this->modules[] = $module;
            }
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Infoplus Suite: Failed to load module {$class}: " . $e->getMessage());
            }
            // Store failed modules for admin notice
            $this->failedModules[] = $class;
        }
    }
}
```

Add a property `private array $failedModules = [];` and optionally show an admin notice if any module failed (only in WP_DEBUG mode).

Remove the `use` import statements for individual module classes at the top of `Plugin.php`, they're no longer needed since we use `::class` strings.

**Test:** Temporarily break one module (add `throw new \Exception('test')` in its constructor), other modules should still load. Check `error_log` for the message.

---

## 🟡 R-06: SyncService: Eliminate Self-HTTP-Request

> **Status (2026-09-17): open, investigation answered.** `SyncService::fetchProducts()` line 57 still does `wp_remote_get(rest_url('infoplus/v1/products'))`. The route is registered by the InfoplusAPI plugin (`reference/infoplus_api/entities/Products.php`, class `Products`, method `getProducts()`), which runs on the same WordPress install. So Option A applies, but there is no `ikona_get_products` function or `\Ikona\ProductsController` class; the class is the global `Products` and its constructor registers routes as a side effect, so it cannot be instantiated safely from the Suite. The clean fix is R-13: once `Products::getProducts()` is a Suite class, call it in PHP. Option B (bearer token) is not needed for a same-site call.

**Problem:** `SyncService::fetchProducts()` calls `wp_remote_get(rest_url('infoplus/v1/products'))`, an HTTP request to itself. This is slow, can fail behind reverse proxies/firewalls, and doesn't pass proper authentication.

**File to modify:**
- `src/Modules/IkonaIFE/SyncService.php` → `fetchProducts()`

**Implementation options:**

**Option A (if Ikona IFE plugin is on same WP install):**
Call the Ikona plugin's PHP function directly instead of going through REST:

```php
public function fetchProducts(): array
{
    $cached = get_transient(self::TRANSIENT_KEY);
    if (is_array($cached)) return $cached;

    // Call Ikona plugin directly if available
    if (function_exists('ikona_get_products') || class_exists('\Ikona\ProductsController')) {
        // Direct PHP call, no HTTP overhead
        $products = /* call the appropriate Ikona function */;
    } else {
        // Fallback to REST
        $products = $this->fetchProductsViaRest();
    }

    if (!empty($products)) {
        set_transient(self::TRANSIENT_KEY, $products, self::CACHE_TTL);
    }

    return $products;
}
```

**Option B (if Ikona plugin is truly external / different server):**
Use `wp_remote_get()` but add proper Bearer token from EditorLinkSettings:

```php
$token = \Infoplus\Suite\Modules\EditorLinkSettings\Module::getBearerToken();
if ($token) {
    $args['headers']['Authorization'] = 'Bearer ' . $token;
}
```

**Investigation needed:** Check what the Ikona IFE plugin actually is, is it on the same WP install or a separate service? This determines which option to use. Look at the `infoplus/v1/products` route registration to find the source plugin.

---

## 🟡 R-07: Extract Inline CSS to Stylesheet Files

> **Status (2026-09-17): partially done.** Done: cart, checkout and thank-you page styling for WooCommerce Blocks now lives in `src/Modules/UserOrders/Assets/woo-pages.css` (631 lines, enqueued on `is_cart()`, `is_checkout()`, `is_wc_endpoint_url('order-received')`). Not done: `renderThankYouContent()` still prints its `<style>` block, `renderLoginGuard()` still prints its `<style>` block, and `enqueueCartStyles()` / `enqueueCheckoutStyles()` still print inline styles on `wp_head` next to the external file. New inline CSS added since the brief: `Custom404/Module.php` (two `<style>` blocks plus an inline script), `renderEmptyCartContent()` and `filterWooCartBlock()` in `UserOrders`. Count of `<style` in PHP today: UserOrders 7, Custom404 2, UserDashboard 2, Dashboard 2, EditorLinkSettings 1, OrderDetails 1, UserOrdersGrid 1.

**Problem:** Two modules output 200+ lines of inline CSS directly in PHP methods, which can't be browser-cached and makes PHP files hard to maintain.

**Locations:**

1. **UserOrders, Thank You page**
   - File: `src/Modules/UserOrders/Module.php` → `renderThankYouContent()`
   - ~200 lines of `<style>` output
   - **Extract to:** `src/Modules/UserOrders/Assets/thank-you.css`
   - **Enqueue conditionally:** In `enqueueStyles()`, detect WooCommerce thank-you endpoint:
     ```php
     if (is_wc_endpoint_url('order-received')) {
         wp_enqueue_style('infoplus-thank-you', $this->assetUrl('thank-you.css'), [], $ver);
     }
     ```

2. **UserDashboard, Login Guard shortcode**
   - File: `src/Modules/UserDashboard/Module.php` → `renderLoginGuard()`
   - ~80 lines of `<style>` output including `@keyframes`
   - **Extract to:** `src/Modules/UserDashboard/Assets/login-guard.css`
   - **Enqueue conditionally:** In `enqueueFrontend()`, always enqueue (it's small), or enqueue only when shortcode is detected using `has_shortcode()`.

**Note:** The EditorLinkSettings inline CSS in `renderSettings()` is admin-only and moderate in size, acceptable to leave inline for now.

---

## 🟡 R-08: Unify Version Management

> **Status (2026-10-23): partly done in 0.10.33.** The plugin header and `Plugin::VERSION` now share one number: `infoplus-suite.php` defines `INFOPLUS_SUITE_VERSION` next to the header, `Plugin::VERSION = INFOPLUS_SUITE_VERSION`, and `tools/build-zips.sh` stops when header and constant differ. Still open: the two module constants below.

> **Status (2026-09-17): open.** Current values: plugin header `0.9.111`, `Plugin::VERSION = '0.10.25'`, `IkonaIFE\Module::VERSION = '1.0.4'`, `ProductDetailSuite\Module::VERSION = '1.1.0'`. `IkonaIFE/Module.php` line 154 and `ProductDetailSuite/Module.php` lines 134 and 256 still use `self::VERSION` for enqueues; `ProductAttributeImages.php` line 980 uses `Plugin::VERSION . '.7'`. New since the brief: `ProductDetailSuite\Module::VERSION` is part of the transient key `infoplus_pd_{id}_{VERSION}`, so switching that module to `Plugin::VERSION` must also change the key (or bumping `Plugin::VERSION` will no longer refresh the product data cache the way a module bump does today). The header version and `Plugin::VERSION` have diverged (0.9.x vs 0.10.x) with no documented rule.

**Problem:** Three different version constants exist: `Plugin::VERSION = '0.9.40'`, `IkonaIFE\Module::VERSION = '1.0.4'`, `ProductDetailSuite\Module::VERSION = '1.0.35'`. Unclear which to bump when.

**Decision:** Use a **single source of truth**, `Plugin::VERSION`, for all asset cache busting.

**Files to modify:**

1. **`src/Modules/IkonaIFE/Module.php`:**
   - Keep `Module::VERSION` for internal reference/changelog only
   - Change `enqueueAdminAssets()` to use `Plugin::VERSION`:
     ```php
     $version = \Infoplus\Suite\Plugin::VERSION;
     ```

2. **`src/Modules/ProductDetailSuite/Module.php`:**
   - Same approach: keep module VERSION for reference, use `Plugin::VERSION` for asset enqueueing

3. **`src/Plugin.php`:**
   - On every release, bump `Plugin::VERSION`
   - This automatically busts cache for ALL modules

**Rule going forward:** Bump `Plugin::VERSION` for any change that needs to reach users. Module-level VERSION is optional metadata only.

---

## 🟢 R-09: Translate All Hardcoded German Strings

> **Status (2026-09-17): open.** Grep for `äöüßÄÖÜ` finds 14 PHP files under `src/`, with 82 matching lines that are not inside `__()`, `_e()`, `esc_html__()`, `esc_attr__()`, `esc_html_e()` or `esc_attr_e()`. The 0.9.93 to 0.9.111 diff added more: `Custom404/Module.php` (all page text), `UserStatCard.php` (data source labels are wrapped in `__()` but are German source strings), `product-info-box.js` ("Kein SKU"), `bulk-price-table.js` ("Für diese Variante sind noch keine Staffelpreise hinterlegt"). `Plugin::registerHooks()` loads the text domain from `languages/`, which does not exist in the repo. The JS strings need `wp_localize_script` or `wp_set_script_translations`, which the brief does not cover.

**Problem:** Many user-facing strings are hardcoded in German without `__()` wrapper, making translation impossible.

**Files with hardcoded German:**

1. **`src/Modules/UserDashboard/Module.php`:**
   - `'Benutzer nicht gefunden'` → `__('User not found', 'infoplus-suite')`
   - `'Ungültige E-Mail-Adresse'` → `__('Invalid email address', 'infoplus-suite')`
   - `'Diese E-Mail-Adresse wird bereits verwendet'` → `__('This email is already in use', 'infoplus-suite')`
   - `'Profil erfolgreich aktualisiert'` → `__('Profile updated successfully', 'infoplus-suite')`
   - `'Das aktuelle Passwort ist falsch'` → `__('Current password is incorrect', 'infoplus-suite')`
   - `'Die neuen Passwörter stimmen nicht überein'` → `__('New passwords do not match', 'infoplus-suite')`
   - `'Das Passwort muss mindestens 8 Zeichen lang sein'` → `__('Password must be at least 8 characters', 'infoplus-suite')`
   - `'Passwort erfolgreich geändert'` → `__('Password changed successfully', 'infoplus-suite')`
   - `'Keine Datei hochgeladen'` → `__('No file uploaded', 'infoplus-suite')`
   - `'Ungültiger Dateityp...'` → translate
   - `'Avatar erfolgreich hochgeladen'` → translate
   - Login guard HTML: `'Dashboard-Zugriff erforderlich'`, `'Sie müssen angemeldet sein...'`, `'Anmelden um fortzufahren'`
   - Security pages: all German strings in `getSecurityMessageHtml()`

2. **`src/Modules/UserOrders/Module.php`:**
   - Thank You page: `'Vielen Dank für Ihre Bestellung!'`, `'Bestelldetails'`, `'Lieferadresse'`, `'Bestellte Artikel'`, `'Zwischensumme'`, `'Versand'`, `'Kostenlos'`, `'MwSt.'`, `'Gesamtsumme'`, `'Zum Dashboard'`, `'Weiter einkaufen'`, `'Menge:'`, etc.
   - Status labels array (`$status_labels`)
   - Payment labels array (`$payment_labels`)

**Approach:** Do a search for German characters (`ä`, `ö`, `ü`, `ß`, `Ä`, `Ö`, `Ü`) across all PHP files to catch every instance. Wrap in `__()` with `infoplus-suite` text domain. Create a `languages/` directory. Optionally generate a `.pot` file.

---

## 🟢 R-10: Add class_exists Guards for Cross-Module Calls

> **Status (2026-09-17): done as far as it goes.** Grep for `EditorLinkSettings` outside its own module finds exactly two call sites, both guarded: `ProductDetailSuite/Module.php` line 886 (`if (!class_exists(...)) return ''`) and `WooSingleProductAddons/Module.php` line 683 (outer `if (class_exists(...))`). No other module calls another module's static methods. The remaining wish in the brief (handle empty returns inside the guarded block) is cosmetic; `generateEditorUrl()` already returns `''` when base URL or theme ID is empty. Nothing to do until R-05 lands and a module can actually be missing.

**Problem:** Some modules call other modules' static methods directly without checking if the class is loaded. If a module fails to load (especially after R-05), these calls would fatal error.

**Currently guarded (good):**
- `ProductDetailSuite/Module.php` → `buildEditorUrl()` checks `class_exists('\Infoplus\Suite\Modules\EditorLinkSettings\Module')`

**Currently unguarded (needs fix):**
- `WooSingleProductAddons/Module.php` → `injectEditorSettings()`:
  ```php
  if (class_exists('\Infoplus\Suite\Modules\EditorLinkSettings\Module')) {
      // existing code
  }
  ```
  This guard exists but only partially, the outer `if` is there, but the static calls inside should also handle empty returns gracefully.

**Implementation:** Add `class_exists()` check everywhere a module's static method is called from another module. Grep for `EditorLinkSettings\Module::` across all files and ensure each call site has a guard.

---

## 🟢 R-11: Add Module Toggle in Admin

> **Status (2026-09-17): open.** No `infoplus_disabled_modules` option and no `infoplus_suite_disabled_modules` filter anywhere in `src/`. `Plugin::registerModules()` registers all nine modules unconditionally.

**Problem:** No way to disable a module without editing code. If a module causes issues in production, the only option is FTP/SSH access.

**Implementation (lightweight approach):**

1. Add a new wp_option: `infoplus_disabled_modules` (array of slugs)
2. In `Plugin::registerModules()`, skip modules whose slug is in the disabled list
3. Add a simple UI in the admin dashboard (maybe a new "Modules" tab, or checkboxes on the Settings tab)
4. Use `update_option()` to save disabled modules

**Files to modify:**
- `src/Plugin.php`, check disabled list before registering
- `src/Admin/Dashboard.php`, add module toggle UI (optional, could be a simple filter-based approach first)

**Minimal version (no UI):** Just support a filter so it can be toggled in `wp-config.php` or a mu-plugin:
```php
$disabled = apply_filters('infoplus_suite_disabled_modules', []);
```

---

## 🟢 R-12: Basic REST Endpoint Tests

> **Status (2026-09-17): open.** No `tests/` directory and no `phpunit.xml.dist` in the repo. `composer.json` has no `require-dev`. Two candidate endpoints were added since the brief: `GET /infoplus/v1/product/{id}/variations` and the `rest_pre_dispatch` interception of `/products/prices` (which now has a transient cache that tests would need to clear).

**Problem:** No automated tests. REST endpoints handling file uploads, orders, and user data are untested.

**Implementation:**

1. Add `tests/` directory at plugin root
2. Add `phpunit.xml.dist` configuration
3. Start with integration tests for the highest-risk endpoints:
   - Sonderkontur upload (file validation, size limits, permission checks)
   - User profile update (email uniqueness, password validation)
   - Order access (ownership verification)
   - Sync result saving

**Framework:** Use WP's built-in test framework (`WP_UnitTestCase`) or Pest PHP with the WordPress plugin.

**Minimum viable test file structure:**
```
tests/
├── bootstrap.php
├── Integration/
│   ├── SonderkonturUploadTest.php
│   ├── UserProfileTest.php
│   └── OrderAccessTest.php
└── Unit/
    └── SyncServiceTest.php
```

---

## 🔴 R-13: Merge InfoplusAPI into the Suite

> **Status (2026-09-17): new, open.** Decision already taken. The live InfoplusAPI plugin (v1.00) is committed under `reference/infoplus_api/` with its two hardcoded credentials replaced by the wp-config constants `INFOPLUS_API_KEY` and `INFOPLUS_IFE_TOKEN`. Production still runs the plugin with literal values.

**Problem:** The Suite's IFE Sync dashboard depends on REST routes that live in a separate plugin by another author, with no authentication, custom database tables without `wp_` prefix, hardcoded URLs and e-mail addresses, and PHP code that references undefined variables. Two plugins own the `infoplus/v1` namespace. See `PIPELINE.md` Sections 5 to 8 for the full picture.

**Files to create (proposal):**
- `src/Modules/IfeBridge/Module.php` (or extend `IkonaIFE`): registers the editor-facing routes under `infoplus/v1` and the WooCommerce cart/order filters from `InforplusAPI.php`
- `src/Modules/IfeBridge/IfeConnector.php` from `reference/infoplus_api/ife/ifeConnector.php`
- `src/Modules/IfeBridge/Products.php`, `Cart.php`, `Project.php`, `CustomerDashboard.php` from the `entities/` and `modules/` folders

**Implementation, in the order the problems bite:**

1. Keep the exact paths the editor calls: `GET /infoplus/v1/cart/updateCart`. Keep `GET /infoplus/v1/products/synchronization` for the dashboard (or move the dashboard to call a `infoplus-suite/v1` route and drop the public one). Decide whether `products/variants`, `cart/testing`, `testing` and the four `customerdashboard*` routes survive; nothing in the Suite calls them today.
2. Attach `validate_api_key()` as `permission_callback` to the editor-facing routes and `manage_woocommerce` to the synchronization route. Then rotate both constants on production as `reference/README.md` says. Remove the `INFOPLUS_API_KEY` fallback to an empty string (today an empty constant would accept `Bearer ` as valid).
3. Register `products/prices` once in the Suite and delete `interceptBulkPricesRoute()` and its `rest_pre_dispatch` filter (keep `getBulkPricesBySku()`).
4. Replace `SyncService::fetchProducts()`'s HTTP self-call with a PHP call (closes R-06).
5. Decide one sync record: either the `infoplus_products` / `infoplus_synchronizations` tables (add an activation routine with `dbDelta`, prefix them) or the Suite's `_infoplus_*` post meta. Today both exist and only agree when a full sync runs.
6. Move hardcoded values into settings: cart redirect URL (currently a Hostinger staging host in `Cart.php`), sync report recipient `vr@alture.io`, `editor.infoplus.de` URLs (EditorLinkSettings already stores base URLs), the `wp_wdp_rules` table name, the `min_quantity` meta name (Suite writes `_infoplus_min_quantity`).
7. Scope the CORS header: `Access-Control-Allow-Origin: *` is currently sent on every REST response of the site.
8. Fix or delete `try_order_action_again()` and the retry branch of `new_order_action()` (undefined `$tries`, `$testing_order`; Action Scheduler assumed), and the undefined `$woocomerce_product` in `getProducts()`.
9. Decide precedence between InfoplusAPI's cart thumbnail filters and the Suite's `_infoplus_model_image_url` filters.
10. Unify text domain (`infoplus-api`, `your-textdomain` → `infoplus-suite`).
11. Deactivate InfoplusAPI on production, deploy, then delete the plugin folder including the dead nested `infoplus_api/Infoplus Suite/` copy.

**Test:** the whole editor round trip on a staging copy: product page → editor deep link → editor adds to cart via `cart/updateCart` → cart shows editor name, price and thumbnail → checkout → `processOrder` receives the order (needs the editor team to confirm receipt) → IFE Sync tab lists products with correct states.

---

## 🟡 R-14: Invalidate the Bulk Prices Transient

> **Status (2026-09-17): new, open.** Created by the 0.9.111 change to `interceptBulkPricesRoute()`.

**Problem:** `ProductDetailSuite/Module.php` `interceptBulkPricesRoute()` caches each SKU's tiers in `infoplus_prices_{md5(sku)}` for `DAY_IN_SECONDS`. Nothing deletes these transients. An edit to a WDP rule is invisible on the product page for up to 24 hours.

**Files to modify:**
- `src/Modules/ProductDetailSuite/Module.php`

**Implementation:** Either (a) hook WDP's own save action if the plugin provides one (unverified; check `wp_wdp_rules` write paths in the WDP plugin), or (b) add a "clear price cache" button to the IFE Sync tab that deletes all `_transient_infoplus_prices_%` rows with one `$wpdb->query()`, or (c) shorten the TTL to a few minutes. (b) is the smallest safe change. The existing `POST /infoplus-suite/v1/products/clear-cache` can be extended to do this.

**Test:** change a tier value in WDP, reload the product page, confirm the new value appears after the cache is cleared.

---

## 🟢 R-15: Remove Hardcoded Attribute Keys in Product Context JSON

> **Status (2026-09-17): new, open.** Created by the 0.9.111 rewrite of `outputProductContextJson()`.

**Problem:** `WooSingleProductAddons/Module.php` `outputProductContextJson()` queries `postmeta` for exactly four keys: `attribute_pa_model`, `attribute_pa_format`, `attribute_pa_befestigung`, `attribute_pa_oberflache`, and maps them to `model`, `format`, `befestigung`, `oberflaeche` in `window.allWooVariations`. A product with any other variation attribute gets empty strings. The old code (before 0.9.111) read whatever attributes the variation had.

**Files to modify:**
- `src/Modules/WooSingleProductAddons/Module.php`
- Consumers of `window.allWooVariations`: `Assets/variation-filter.js`, `Assets/custom-model-dropdown.js`, `Assets/variation-editor-link.js` (verify which of the four keys they actually read)

**Implementation:** Query `meta_key LIKE 'attribute_%'` (the same query `ProductDetailSuite::buildVariationsDataLite()` already runs) and emit the attributes map as-is, keeping the four legacy keys as aliases until the consumers are updated. Consider whether `window.allWooVariations` is still needed at all now that `window.infoplusProductData.variations` carries the same data.

**Test:** product with the four standard attributes behaves as before; a product with an extra attribute shows it in `window.allWooVariations`.

---

## Implementation Order

Suggested sequence for executing this roadmap:

| Phase | Items | Effort | Notes |
|-------|-------|--------|-------|
| **Phase 1: Security** | R-01, R-02 | ~30 min | Do immediately. No breaking changes. |
| **Phase 2: Namespace** | R-03 | ~45 min | Coordinate with frontend. Test all REST calls. |
| **Phase 3: Resilience** | R-04, R-05, R-06 | ~2 hours | Backend changes. Test thoroughly. |
| **Phase 4: Cleanup** | R-07, R-08 | ~1 hour | Cosmetic. Low risk. |
| **Phase 5: Polish** | R-09, R-10, R-11 | ~2 hours | Quality of life. Can be done incrementally. |
| **Phase 6: Testing** | R-12 | ~3 hours | Set up framework + write initial tests. |

Revised sequence after the 2026-09-17 review (the original table is kept above):

| Phase | Items | Notes |
|-------|-------|-------|
| **Phase 1: Security** | R-01 (plus the `infoplus_add_to_cart` nonce), R-02 | Unchanged. Still the first thing to do. |
| **Phase 2: Merge** | R-13, with R-03 and R-06 inside it | Namespaces and the self HTTP call are decided by the merge; doing R-03 first would be redone. |
| **Phase 3: Resilience** | R-04, R-05 | Unchanged. |
| **Phase 4: Cleanup** | R-07 (remaining inline CSS), R-08, R-14, R-15 | R-08 must keep the product data transient key in mind. |
| **Phase 5: Polish** | R-09, R-11 | R-10 is done. |
| **Phase 6: Testing** | R-12 | Include the new variations route and the merged editor routes. |

**After each phase:** Bump `Plugin::VERSION`, hard refresh (`Ctrl+Shift+R`), test affected pages.
