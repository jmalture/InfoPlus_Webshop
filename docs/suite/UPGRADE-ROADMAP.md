# Infoplus Suite — Upgrade Roadmap

> **Created:** 2026-02-19  
> **Status:** Planned  
> **Reference:** Read `PLUGIN-DOCUMENTATION.md` first for full architecture context.

Each item below is written as a self-contained implementation brief. When we start work on any item, read the relevant section — it contains the exact files, lines, and approach needed.

---

## Priority Legend

| Label | Meaning |
|-------|---------|
| 🔴 CRITICAL | Security risk or data loss potential — fix ASAP |
| 🟡 MEDIUM | Architecture improvement — do before next major feature |
| 🟢 LOW | Code quality / maintainability — do when touching related code |

---

## 🔴 R-01: Secure Sonderkontur Upload Endpoints

**Problem:** All four Sonderkontur REST endpoints use `'permission_callback' => '__return_true'` — anyone on the internet can upload files, list them, or delete them. No rate limiting, no file count cap.

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

2. Add file count limit in `handleFileUpload()` — before processing, check:
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

4. The frontend JS in `sonderkontur-upload-toggle.js` already sends `X-WP-Nonce` via the config object `window.infoplusSonderkonturUpload.nonce` — verify it actually includes the header in fetch/XHR calls. If not, add it.

**Test:** Try calling `POST /wp-json/infoplus/v1/sonderkontur/upload` with no nonce header via curl — should get 401. Try with valid nonce from browser — should work.

---

## 🔴 R-02: Delete Duplicate Assets from UserDashboard

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

**Implementation:** Delete the 9 duplicate files listed above. No code changes needed — nothing references them. Do a global search for `UserDashboard/Assets/product-state` (or any of the duplicate filenames) to confirm zero references.

**Test:** Full page load of product pages and user dashboard — nothing should break since these files were never loaded from this location.

---

## 🔴 R-03: Unify REST Namespace

**Problem:** The plugin uses two namespaces — `infoplus-suite/v1` (IkonaIFE admin endpoints) and `infoplus/v1` (everything else). The `infoplus/v1` namespace is shared with the external Ikona IFE plugin, creating collision risk.

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

5. **Frontend JS files** that construct REST URLs from the localized config — these should already use the `restUrl` variable from `wp_localize_script`, so changing the PHP side should be sufficient. But verify:
   - `UserDashboard/Assets/user-dashboard.js` — uses `infoplusUserDashboard.restUrl`
   - `UserOrders/Assets/user-orders.js` — uses `infoplusUserOrders.restUrl`
   - `WooSingleProductAddons/Assets/sonderkontur-upload-toggle.js` — uses `infoplusSonderkonturUpload.restUrl`

**Important:** The IkonaIFE dashboard.js calls to `config.syncApiUrl` (`/infoplus/v1/products/synchronization`) must NOT change — that's the external Ikona plugin endpoint.

**Test:** Hard refresh all pages. Test user dashboard profile save, order list loading, file upload, product detail page data loading.

---

## 🟡 R-04: Replace Transient File Storage with WC Session

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

**Caveat:** WC session requires WooCommerce to be initialized. The REST endpoints fire early — verify `WC()->session` is available in REST context. If not, you may need to call `WC()->initialize_session()` or fall back to transients for REST-only requests.

**Test:** Upload files → verify they persist → add to cart → verify files attach. Repeat after clearing object cache.

---

## 🟡 R-05: Wrap Module Registration in Try/Catch

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

Remove the `use` import statements for individual module classes at the top of `Plugin.php` — they're no longer needed since we use `::class` strings.

**Test:** Temporarily break one module (add `throw new \Exception('test')` in its constructor) — other modules should still load. Check `error_log` for the message.

---

## 🟡 R-06: SyncService — Eliminate Self-HTTP-Request

**Problem:** `SyncService::fetchProducts()` calls `wp_remote_get(rest_url('infoplus/v1/products'))` — an HTTP request to itself. This is slow, can fail behind reverse proxies/firewalls, and doesn't pass proper authentication.

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
        // Direct PHP call — no HTTP overhead
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

**Investigation needed:** Check what the Ikona IFE plugin actually is — is it on the same WP install or a separate service? This determines which option to use. Look at the `infoplus/v1/products` route registration to find the source plugin.

---

## 🟡 R-07: Extract Inline CSS to Stylesheet Files

**Problem:** Two modules output 200+ lines of inline CSS directly in PHP methods, which can't be browser-cached and makes PHP files hard to maintain.

**Locations:**

1. **UserOrders — Thank You page**
   - File: `src/Modules/UserOrders/Module.php` → `renderThankYouContent()`
   - ~200 lines of `<style>` output
   - **Extract to:** `src/Modules/UserOrders/Assets/thank-you.css`
   - **Enqueue conditionally:** In `enqueueStyles()`, detect WooCommerce thank-you endpoint:
     ```php
     if (is_wc_endpoint_url('order-received')) {
         wp_enqueue_style('infoplus-thank-you', $this->assetUrl('thank-you.css'), [], $ver);
     }
     ```

2. **UserDashboard — Login Guard shortcode**
   - File: `src/Modules/UserDashboard/Module.php` → `renderLoginGuard()`
   - ~80 lines of `<style>` output including `@keyframes`
   - **Extract to:** `src/Modules/UserDashboard/Assets/login-guard.css`
   - **Enqueue conditionally:** In `enqueueFrontend()`, always enqueue (it's small), or enqueue only when shortcode is detected using `has_shortcode()`.

**Note:** The EditorLinkSettings inline CSS in `renderSettings()` is admin-only and moderate in size — acceptable to leave inline for now.

---

## 🟡 R-08: Unify Version Management

**Problem:** Three different version constants exist: `Plugin::VERSION = '0.9.40'`, `IkonaIFE\Module::VERSION = '1.0.4'`, `ProductDetailSuite\Module::VERSION = '1.0.35'`. Unclear which to bump when.

**Decision:** Use a **single source of truth** — `Plugin::VERSION` — for all asset cache busting.

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
  This guard exists but only partially — the outer `if` is there, but the static calls inside should also handle empty returns gracefully.

**Implementation:** Add `class_exists()` check everywhere a module's static method is called from another module. Grep for `EditorLinkSettings\Module::` across all files and ensure each call site has a guard.

---

## 🟢 R-11: Add Module Toggle in Admin

**Problem:** No way to disable a module without editing code. If a module causes issues in production, the only option is FTP/SSH access.

**Implementation (lightweight approach):**

1. Add a new wp_option: `infoplus_disabled_modules` (array of slugs)
2. In `Plugin::registerModules()`, skip modules whose slug is in the disabled list
3. Add a simple UI in the admin dashboard (maybe a new "Modules" tab, or checkboxes on the Settings tab)
4. Use `update_option()` to save disabled modules

**Files to modify:**
- `src/Plugin.php` — check disabled list before registering
- `src/Admin/Dashboard.php` — add module toggle UI (optional, could be a simple filter-based approach first)

**Minimal version (no UI):** Just support a filter so it can be toggled in `wp-config.php` or a mu-plugin:
```php
$disabled = apply_filters('infoplus_suite_disabled_modules', []);
```

---

## 🟢 R-12: Basic REST Endpoint Tests

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

**After each phase:** Bump `Plugin::VERSION`, hard refresh (`Ctrl+Shift+R`), test affected pages.
