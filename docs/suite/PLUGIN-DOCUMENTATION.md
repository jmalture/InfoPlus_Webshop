# Infoplus Suite: Plugin Documentation

> **Version:** 0.9.111 (plugin header), internal cache-bust constant `Plugin::VERSION` = 0.10.26 on `feature/view-renderer` (0.10.25 before)
> **Author:** JM (Alture / Infoplus)
> **License:** GPL-2.0-or-later
> **Last Updated:** 2026-09-17 (matches production, git commit `f01aca6`)
> **Related docs:** `PIPELINE.md` (system overview), `UPGRADE-ROADMAP.md` (planned work), `CLAUDE.md` (project brief), `reference/README.md` (InfoplusAPI and legacy plugins)

---

## Table of Contents

1. [Overview](#1-overview)
2. [Architecture](#2-architecture)
3. [Plugin Bootstrap](#3-plugin-bootstrap)
4. [Module Reference](#4-module-reference)
   - 4.1 [EditorLinkSettings](#41-editorlinksettings)
   - 4.2 [IkonaIFE](#42-ikonaife)
   - 4.3 [WooProductVariationFields](#43-wooproductvariationfields)
   - 4.4 [WooSingleProductAddons](#44-woosingleproductaddons)
   - 4.5 [ProductDetailSuite](#45-productdetailsuite)
   - 4.6 [UserDashboard](#46-userdashboard)
   - 4.7 [UserOrders](#47-userorders)
   - 4.8 [ElementorUserAvatar](#48-elementoruseravatar)
   - 4.9 [Custom404](#49-custom404)
   - 4.10 [Inquiry](#410-inquiry)
5. [Module Dependency Map](#5-module-dependency-map)
6. [REST API Endpoints](#6-rest-api-endpoints)
7. [WooCommerce Meta Keys](#7-woocommerce-meta-keys)
8. [Conventions & Patterns](#8-conventions--patterns)
9. [Anfrage Popup: Data Injection Pattern](#9-anfrage-popup-data-injection-pattern)
10. [Admin Meta Box: "Infoplus Suite"](#10-admin-meta-box-infoplus-suite)
11. [Per-Model Default Variations](#11-per-model-default-variations)
12. [WDP Bulk Prices API Interception](#12-wdp-bulk-prices-api-interception)
    - [12a. Catalog Import](#12a-catalog-import-woocommerce--katalog-import)
13. [Model Switching: Single-Trigger WooCommerce Sync](#13-model-switching-single-trigger-woocommerce-sync)
14. [Two-Way Qty / Price Table Sync](#14-two-way-qty--price-table-sync)
15. [Cart Page Styling (WooCommerce Blocks)](#15-cart-page-styling-woocommerce-blocks)
16. [Known Issues & Incomplete Features](#16-known-issues--incomplete-features)
17. [Large Products: Deferred Variations and Caching](#17-large-products-deferred-variations-and-caching)
18. [Changes from 0.9.93 to 0.9.111](#18-changes-from-0993-to-09111)
19. [Theme Renderer](#19-theme-renderer)
20. [Order Types and the Buy Flow](#20-order-types-and-the-buy-flow)
21. [Inquiry Form ("Individuelle Anfrage")](#21-inquiry-form-individuelle-anfrage)

---

## 1. Overview

**Infoplus Suite** is a modular WordPress/WooCommerce plugin powering a B2B nameplate and badge customization platform. It integrates WooCommerce with the external **Ikona IFE/IBE** editor system (editor.infoplus.de), providing:

- Product synchronization between WooCommerce and the Ikona backend
- Custom product detail pages with model selectors, variation dropdowns, and dynamic images
- Editor URL generation that launches the external IFE editor for product customization
- Custom file upload system for "Sonderkontur" (special contour) designs
- User dashboard with profile management, order history, and avatar uploads
- Custom Elementor widgets and dynamic tags for frontend display
- B2B user tier support (Shoppers, Resellers, Partners)
- Branded cart, checkout, empty-cart, thank-you page and order emails (WooCommerce Blocks)
- Branded 404 page

**Tech stack:** WordPress, WooCommerce, Elementor Pro, ACF, PHP 7.4+, jQuery, REST API.

**Companion plugin:** the separate **InfoplusAPI** plugin (author Vaclav Rezabek, copy in `reference/infoplus_api/`) owns the REST routes under `infoplus/v1` that talk to editor.infoplus.de. The Suite's IkonaIFE module depends on it. A merge into the Suite is planned. See `PIPELINE.md`.

**Brand accent color:** `#e91e8c` (pink), used across all custom UI.

---

## 2. Architecture

### 2.1 Directory Structure

```
infoplus-suite/
├── infoplus-suite.php          # Plugin entry point (header Version 0.9.111)
├── composer.json                # PSR-4 autoloader config
├── PLUGIN-DOCUMENTATION.md      # This file
├── PIPELINE.md                  # End-to-end system overview (all four plugins)
├── UPGRADE-ROADMAP.md           # Planned refactors R-01 to R-12 with status
├── CLAUDE.md                    # Short project brief for AI-assisted sessions
├── FEATURE-*.md                 # Four older feature briefs (model gallery, order panel, order types, dropdown height)
├── assets/                      # Plugin-level assets (icons, logos)
│   ├── ikona-icon.ico
│   ├── ikona-icon.png
│   └── logo-ikona.jpg
├── reference/                   # NOT deployed. Lineage copies, see reference/README.md
│   ├── infoplus_api/            # Live InfoplusAPI plugin (secrets moved to wp-config constants)
│   └── legacy/                  # The two predecessor plugins (inactive on production)
└── src/
    ├── Plugin.php               # Main plugin orchestrator, Plugin::VERSION = 0.10.26
    ├── Admin/
    │   └── Dashboard.php        # WP Admin menu page + tab renderer
    ├── Contracts/
    │   └── ModuleInterface.php  # Interface all modules implement
    └── Modules/
        ├── EditorLinkSettings/  # Settings tab (admin UI)
        ├── IkonaIFE/            # Product sync dashboard (admin UI)
        ├── WooProductVariationFields/  # Backup save of IFE meta + REST exposure
        ├── WooSingleProductAddons/     # Frontend product page scripts + file uploads + AJAX cart
        ├── ProductDetailSuite/         # Elementor widgets for product pages (six widgets)
        ├── UserDashboard/              # User profile + auth management + UserStatCard widget
        ├── UserOrders/                 # Orders, thank-you page, cart/checkout styling, emails
        ├── ElementorUserAvatar/        # User avatar dynamic tag
        └── Custom404/                  # Branded 404 page
```

### 2.2 Module System

Every module implements `ModuleInterface`:

```php
interface ModuleInterface {
    public function slug(): string;    // Unique identifier (e.g. 'ikona-ife')
    public function label(): string;   // Human-readable name
    public function register(): void;  // Hook registration
}
```

All nine modules are instantiated in `Plugin.php → registerModules()` in this order: WooSingleProductAddons, WooProductVariationFields, EditorLinkSettings, IkonaIFE, ElementorUserAvatar, UserDashboard, UserOrders, ProductDetailSuite, Custom404. Each module's `register()` method is called unconditionally. Since Plugin 0.10.29 (branch `feature/view-renderer`) the module `Inquiry` is registered last, after CatalogImport (Section 21). There is no try/catch around instantiation and no way to disable a single module without editing code (roadmap items R-05 and R-11).

### 2.3 Admin Dashboard Tabs

Only modules listed in `Plugin::$visibleModules` appear as tabs in the WP Admin dashboard page:

| Tab | Module Slug | Description |
|-----|-------------|-------------|
| Settings | `editor-link-settings` | Editor URL configuration |
| IFE Sync | `ikona-ife` | Product synchronization dashboard |
| Anfragen | `inquiry` | Settings of the inquiry form (since 0.10.29, Section 21) |

The admin page is registered under **IKONA IFE** in the WP sidebar (position 56) with a custom icon.

---

## 3. Plugin Bootstrap

### `infoplus-suite.php` (Entry Point)

1. Registers a PSR-4 autoloader: `Infoplus\Suite\` → `src/`
2. On `plugins_loaded`, instantiates `Plugin` and calls `boot()`
3. On error, logs to `error_log` and shows an admin notice

### `src/Plugin.php` (Orchestrator)

`boot()` runs in order:

1. **`defineConstants()`**: sets `INFOPLUS_SUITE_FILE`, `INFOPLUS_SUITE_PATH`, `INFOPLUS_SUITE_URL`
2. **`registerModules()`**: instantiates all 9 modules, calls `register()` on each
3. **`registerAdmin()`**: passes visible modules to `Dashboard`, registers the admin page
4. **`registerHooks()`**: loads text domain `infoplus-suite` from `languages/` on `init` (the directory does not exist in the repo, so no translations load)

`Plugin::VERSION` (currently `0.10.26`, 0.10.25 before `feature/view-renderer`) is the cache-bust string most modules append to their asset URLs. It is independent of the plugin header version (`0.9.111`). See Section 8.

### `src/Admin/Dashboard.php`

- Registers the `admin.php?page=infoplus-suite` menu page
- Renders tab navigation from visible modules
- Active tab content is rendered via `do_action('infoplus/module/settings/' . $slug)`
- Custom CSS for header icon alignment and tab styling

---

## 4. Module Reference

---

### 4.1 EditorLinkSettings

> **Slug:** `editor-link-settings`  
> **Purpose:** Admin settings page for configuring IFE editor URLs, theme ID, and API authentication.  
> **Has admin tab:** Yes (Settings)

#### What it does

Stores and manages all configuration needed to generate editor URLs that launch the external IFE product editor. Other modules call its static methods to build URLs.

#### Files

| File | Role |
|------|------|
| `Module.php` | Settings registration, form rendering, URL generation logic |

#### Key Options (wp_options)

| Option Key | Description | Default |
|------------|-------------|---------|
| `infoplus_editor_base_desktop_url` | Desktop editor base URL | `https://editor.infoplus.de/e2c/editor` |
| `infoplus_editor_base_mobile_url` | Mobile editor base URL | `https://m.editor.infoplus.de/e2c/editor` |
| `infoplus_editor_theme_id` | Global theme UUID | *(empty)* |
| `infoplus_editor_cart_entry_path` | Cart entry path parameter | `/e2-cart` |
| `infoplus_editor_back_path` | Back path template | `{product_url}` |
| `infoplus_editor_bearer_token` | API bearer token (stored as password field) | *(empty)* |

#### Static API (used by other modules)

```php
EditorLinkSettings\Module::getBaseUrl(): string          // Auto-detects mobile/desktop
EditorLinkSettings\Module::getDesktopBaseUrl(): string
EditorLinkSettings\Module::getMobileBaseUrl(): string
EditorLinkSettings\Module::getThemeId(): string
EditorLinkSettings\Module::getCartEntryPath(): string
EditorLinkSettings\Module::getBackPath(): string
EditorLinkSettings\Module::getBearerToken(): string
EditorLinkSettings\Module::generateEditorUrl(string $uuid, string $productUrl, ?bool $forceMobile): string
EditorLinkSettings\Module::isMobile(): bool
```

#### UI Features

- All fields are **locked by default** (readonly) with a lock/unlock toggle button
- Unsaved changes indicator with `beforeunload` warning
- Live-updating example URL preview
- Save confirmation dialog

#### Cross-module usage

- **WooSingleProductAddons** reads settings via static methods to inject `window.infoplusEditorSettings` on product pages
- **ProductDetailSuite** calls `generateEditorUrl()` to build editor links in product data

---

### 4.2 IkonaIFE

> **Slug:** `ikona-ife`  
> **Purpose:** Admin dashboard for viewing and synchronizing WooCommerce products with the Ikona IFE/IBE backend.  
> **Has admin tab:** Yes (IFE Sync)  
> **Version:** 1.0.4

#### What it does

Provides a full-featured product sync dashboard in WP Admin. Displays all WooCommerce products with their sync status, allows individual or batch synchronization with the external Ikona API, and saves sync results back to WooCommerce product meta.

#### Files

| File | Role |
|------|------|
| `Module.php` | Module registration, dashboard rendering, asset enqueueing. Version constant for cache busting. |
| `SyncController.php` | REST API endpoints for products proxy, sync result saving, cache management. Also has legacy AJAX handlers. |
| `SyncService.php` | Business logic: fetches products from Ikona API, caches results (60s transient), saves sync metadata to post_meta. |
| `Views/dashboard.php` | HTML template for the sync dashboard (table, filters, progress bar, modal, toast container). Receives `$configJson` and `$productsJson` from Module. |
| `Assets/dashboard.js` | Full dashboard controller: table rendering, search/filter/sort, sync operations (single + batch), modals, toasts, progress tracking. |
| `Assets/dashboard.css` | Dashboard styling (table, badges, modals, toasts, progress bars, loading spinners). |

#### Data Flow

```
1. Page loads → dashboard.php renders skeleton
2. JS fetches GET /infoplus-suite/v1/products (proxied through SyncController)
3. SyncController calls SyncService::fetchProductsWithSyncStatus()
4. SyncService does wp_remote_get() against its own site: rest_url('infoplus/v1/products').
   That route is registered by the InfoplusAPI plugin (reference/infoplus_api/entities/Products.php),
   which reads the custom tables infoplus_products and infoplus_synchronizations.
   Result is cached in the transient infoplus_products_cache for 60 seconds.
5. Products rendered in JS table with search/filter/sort
6. User clicks Sync → JS calls GET /infoplus/v1/products/synchronization?productId=X
   (InfoplusAPI route; it calls editor.infoplus.de/smp/infoplus/updateProduct server-side)
7. On success → JS calls POST /infoplus-suite/v1/products/{id}/sync-result (saves to WP)
8. SyncService saves timestamps and IDs to post_meta
```

Config passed to `dashboard.js` (`Module::renderDashboard`): `proxyUrl`, `syncApiUrl`, `saveSyncUrl`, `nonce` (`wp_rest`), `loadAsync`, plus the i18n strings.

Two legacy admin-ajax actions still exist in `SyncController` (`wp_ajax_infoplus_sync_all`, `wp_ajax_infoplus_sync_product`, nonce action `infoplus_sync`). `dashboard.js` does not call them.

#### REST Endpoints (namespace: `infoplus-suite/v1`)

| Method | Route | Handler | Permission |
|--------|-------|---------|------------|
| GET | `/products` | `getProducts()` | `manage_woocommerce` |
| POST | `/products/{id}/sync-result` | `saveSyncResult()` | `manage_woocommerce` |
| GET | `/products/{id}/sync-status` | `getSyncStatus()` | `manage_woocommerce` |
| POST | `/products/clear-cache` | `clearCache()` | `manage_woocommerce` |

#### External API Calls (from JS, NOT proxied through PHP)

| Method | URL | Purpose |
|--------|-----|---------|
| GET | `/infoplus/v1/products/synchronization` | Sync all products |
| GET | `/infoplus/v1/products/synchronization?productId=X` | Sync single product |

These hit the **InfoplusAPI plugin** endpoints (separate plugin, copy in `reference/infoplus_api/`), not Infoplus Suite endpoints. The route has `permission_callback => '__return_true'` on the InfoplusAPI side, so it is public.

#### Sync Status Values

| Value | Constant | Badge |
|-------|----------|-------|
| 0 | `STATUS_SYNCHRONIZED` | ✔ Synchronized (green) |
| 1 | `STATUS_MODIFIED` | ⚠ Modified (orange) |
| 2 | `STATUS_NEVER_SYNCED` | ⏸ Never Synced (gray) |

#### ⚠ Current State (as of v1.0.4)

**Sync buttons are temporarily disabled** to prevent breaking products on the IFE end. The click handlers in `dashboard.js` are commented out. The Sync All button is visually disabled (grayed out). Individual sync buttons have a no-op click handler. All sync functions (`handleSyncAll`, `syncProduct`, etc.) remain intact and untouched.

Re-checked 2026-09-17 against `dashboard.js` lines 88 to 103: still disabled. No change in the 0.9.93 to 0.9.111 diff.

---

### 4.3 WooProductVariationFields

> **Slug:** `product-variation-fields`  
> **Purpose:** Backup save handler for the IFE/IBE meta fields and REST exposure of `_ife_*` meta.  
> **Has admin tab:** No (runs in background)

#### What it does

Historically this module added an **IBE/IFE** tab to the WooCommerce product data panel. That tab is gone (see Section 10). The module now only:

1. Saves the IBE/IFE POST fields on `woocommerce_process_product_meta` as a backup to the "Infoplus Suite" meta box save. Since 0.9.111 the three locked fields (`ife_uuid`, `ife_unique_text_name`, `ife_print_api_id`) are only written when the current user has `manage_options`. The hidden fields, `infoplus_min_quantity` and `infoplus_delivery_days` are saved for any user who can edit the product.
2. Deletes the legacy keys `_ife_product_bg_image` and `_ife_overlay_frame_image` on every save.
3. Exposes all `_ife_*` meta on the WooCommerce Products REST response.

#### Files

| File | Role |
|------|------|
| `Module.php` | Backup save of IFE meta, legacy key cleanup, REST exposure. |
| `Assets/admin-fields.js` | Media uploader support for admin fields. Not enqueued anywhere in the current code (grep for `admin-fields.js` finds no PHP reference). Dead file. |

#### Product Meta Fields

| Meta Key | Label | Editable | Description |
|----------|-------|----------|-------------|
| `_ife_uuid` | UUID | 🔒 Locked | Product UUID from IFE system (managed by sync) |
| `_ife_unique_text_name` | Einzigart. Name | 🔒 Locked | Unique display name from IFE |
| `_ife_print_api_id` | Print API ID | 🔒 Locked | Print API identifier |
| `_ife_name_2` | *(hidden)* | Hidden | Secondary name field |
| `_ife_ife_image` | *(hidden)* | Hidden | IFE image URL |
| `_ife_preview_images` | *(hidden)* | Hidden | Array of preview image URLs (JSON) |
| `_ife_template_zip` | *(hidden)* | Hidden | Template ZIP URL |

The three visible fields render read-only with a lock button in the "Infoplus Suite" meta box (Section 10). Since 0.9.111 an administrator (`manage_options`) can click the lock to unlock and edit them; for other roles the button is disabled. They are normally written by the IFE sync (InfoplusAPI writes `_ife_uuid` directly in `start_single_synchronization`).

#### REST API Integration

Hooks into `woocommerce_rest_prepare_product_object` to expose all `_ife_*` meta fields on the WooCommerce Products REST API response.

#### Cross-module usage

- **IkonaIFE (SyncService)** writes to `_ife_uuid` and related fields during sync
- **WooSingleProductAddons** reads `_ife_uuid` to generate editor URLs
- **ProductDetailSuite** reads `_ife_uuid` for editor link building

---

### 4.4 WooSingleProductAddons

> **Slug:** `single-product-addons`  
> **Purpose:** Frontend scripts and functionality for WooCommerce single product pages, including file uploads and editor link generation.  
> **Has admin tab:** No (runs in background)

#### What it does

Enqueues multiple frontend scripts on product pages that handle variation filtering, bulk pricing calculations, model dropdown behavior, gallery sizing, editor link updating, and the Sonderkontur (special contour) file upload system. Also integrates uploaded files with the WooCommerce cart and order system.

#### Files

| File | Role |
|------|------|
| `Module.php` | Script enqueueing, REST endpoints for file uploads, cart/order integration hooks, product context JSON output, editor settings injection, AJAX add-to-cart, buy form filters for the classic theme (0.10.27, Section 20), model image in cart and order line items. |
| `Assets/variation-filter.js` | Filters available variations based on selected attributes. |
| `Assets/elementor-bulk-calculator.js` | Legacy Elementor bulk calculator (`#qty_value`, `#bulk_table`). Since 0.9.111 it no longer renders its own price grid. It syncs its qty with `infoplusProductState` (`qty:changed`, `source: 'calculator'`), emits `minqty:changed`, and hides the qty row for requestable models. Since 0.10.28, after it clamps the quantity to the minimum of a newly loaded variation, it also calls `infoplusProductState.setQty(min, 'calculator')`, so `currentQty` and the active Staffelpreise tier match the quantity field (before, the table kept the old quantity and highlighted no tier until the visitor changed the quantity). |
| `Assets/custom-model-dropdown.js` | Custom styled model dropdown behavior. |
| `Assets/product-gallery-sizer.js` | Handles product gallery image sizing/layout. |
| `Assets/variation-editor-link.js` | Updates the "Open Editor" button URL when a variation is selected. Since 0.9.111 it handles `requestable_with_variants` like `requestable` and pulses the active button (`infoplus-anfrage-pulse--long`, 3.1 s). Since 0.10.27 it publishes the resolved order type (`infoplusProductState.orderType`, event `ordertype:changed`) and, on a page with `form[data-ip-buy-form]`, leaves the "buyable" action to that form instead of its admin-ajax cart button (Section 20). Since 0.10.28 it keeps `#anfrage` visible for "buyable" models on such a page. |
| `Assets/sonderkontur-upload-toggle.js` | Toggle UI for the Sonderkontur file upload area. |
| `Assets/sonderkontur-upload.css` | Styling for the upload UI. |
| `Assets/sonderkontur-admin.css` | Admin order page styling for uploaded files display. |
| `Assets/assets/arrow.svg` | Dropdown arrow icon. |

#### Frontend Global Variables

Injected via `wp_head` on product pages:

| Variable | Content |
|----------|---------|
| `window.currentProduct` | Full product context (id, sku, name, image, categories, variations, mindestanzahl). Since 0.10.26 `variations` is the same array object as `window.allWooVariations`. |
| `window.allWooVariations` | Flat array of variation attribute combos. Empty when the filter `infoplus_suite_product_context_variations` returns false. |
| `window.currentProductImage` | Main product image URL |
| `window.infoplusEditorSettings` | Editor config (baseUrl, themeId, productUuid, cartEntryPath, backPath) |
| `window.infoplusSonderkonturUpload` | Upload REST URL and nonce |
| `window.arrowSvgUrl` | Path to arrow SVG asset |

#### Sonderkontur File Upload System

A temporary file storage system using WP transients (24h TTL) keyed by WooCommerce session cookie or IP+UA hash.

**REST Endpoints (namespace: `infoplus/v1`):**

| Method | Route | Permission | Purpose |
|--------|-------|------------|---------|
| POST | `/sonderkontur/upload` | Public | Upload files (multi-file, 38MB max, PDF/images/docs/audio/video) |
| GET | `/sonderkontur/files` | Public | List uploaded files for current session |
| DELETE | `/sonderkontur/files/{index}` | Public | Delete a specific uploaded file |
| POST | `/sonderkontur/clear` | Public | Clear all uploaded files |

**Cart Integration Flow:**

1. Files uploaded to temp storage via REST API
2. On `woocommerce_add_cart_item_data` → files moved from temp to `wp-content/uploads/sonderkontur-uploads/`
3. On `woocommerce_get_item_data` → files displayed in cart
4. On `woocommerce_checkout_create_order_line_item` → files saved to order item meta (`_sonderkontur_files`)
5. On `woocommerce_after_order_itemmeta` → files displayed in admin order view with download links

#### AJAX add to cart (`infoplus_add_to_cart`)

`ajaxAddToCart()` is registered on `wp_ajax_infoplus_add_to_cart` and `wp_ajax_nopriv_infoplus_add_to_cart`. It reads `product_id`, `variation_id`, `quantity` and `model_image_url` from POST, resolves the variation attributes from the variation object and calls `WC()->cart->add_to_cart()`. The only caller in the repo is `variation-editor-link.js` (the legacy Elementor button flow for the `buyable` order type). There is no nonce or referer check in this handler (see Section 16).

Since 0.10.27 the classic theme does not use this handler. It posts a normal WooCommerce add-to-cart form instead; see Section 20. The handler and its caller are unchanged for the Elementor template.

#### Model image in cart and orders

`model_image_url` is stored as cart item data `_infoplus_model_image_url`, copied to the order line item meta of the same name on `woocommerce_checkout_create_order_line_item`, and used by `cartItemModelImage()` (`woocommerce_cart_item_thumbnail`) and `orderItemModelImage()` (`woocommerce_order_item_thumbnail`). The OrderDetails widget reads the same order item meta.

#### Product context JSON (0.9.111 change)

`outputProductContextJson()` builds `window.currentProduct` / `window.allWooVariations`. It no longer calls `get_available_variations()`. It reads the child IDs and one `postmeta` query for four hardcoded attribute keys: `attribute_pa_model`, `attribute_pa_format`, `attribute_pa_befestigung`, `attribute_pa_oberflache`. Products with other attribute names will show empty values here.

#### Product context JSON (0.10.26 change)

Before 0.10.26 the variation list was JSON encoded twice, once as `window.currentProduct.variations` and once as `window.allWooVariations`. On the staging Atlantis page (product 3338, 1380 variations, checked 2026-10-20) each copy was about 194 KB, and LiteSpeed's inline script handling base64 encodes it into a `data:` URI, so the two copies made up about 517 KB of the 875 KB HTML. The list is now printed once as `window.allWooVariations`, and the script sets `window.currentProduct.variations = window.allWooVariations`, so both names still point at the same data.

Readers of the list (search over `wp-content` on staging and this repo, 2026-10-20): `variation-filter.js` reads `window.allWooVariations` and returns early when there is no `.variations_form` on the page or the array is empty. Nothing reads `window.currentProduct.variations`. `elementor-bulk-calculator.js` reads only `currentProduct.id` and `currentProduct.mindestanzahl`. The inactive legacy plugin "Woo Single Product Addons" also read `allWooVariations`.

New filter `infoplus_suite_product_context_variations` (bool, default true, arguments `$include` and the `WC_Product`). Returning false skips the `postmeta` query and prints an empty list. The classic theme does this when it renders no `form.variations_form`, which removes the list from its product pages.

#### Cross-module usage

- Reads **EditorLinkSettings** via static methods for editor URL config (guarded by `class_exists`)
- Reads **WooProductVariationFields** meta (`_ife_uuid`) for editor URL generation

---

### 4.5 ProductDetailSuite

> **Slug:** `product-detail-suite`
> **Purpose:** Custom Elementor widgets for product detail pages with shared state management.
> **Has admin tab:** No (runs in background)
> **Version:** 1.1.1 (`Module::VERSION`, 1.1.0 before `feature/view-renderer`, used for this module's asset cache busting and for the product data transient key)

#### What it does

Provides six custom Elementor widgets that work together through a shared JavaScript state system (`product-state.js`) to create an integrated product configuration experience. Also adds the consolidated "Infoplus Suite" admin meta box (replaces the old IBE/IFE WooCommerce product tab) with three tabs: Attribute Overrides, IBE/IFE, and Default Variation.

Since 0.9.111 the module also has a performance path for products with many variations: product data is cached in a transient, variations above a threshold are loaded by REST after page load, and several builders use direct `$wpdb` queries instead of `get_available_variations()`. See Section 17.

#### Files

| File | Role |
|------|------|
| `Module.php` | Widget registration, REST endpoints, admin/frontend asset enqueueing, product data building (full and "lite"), product state output with transient cache, cache invalidation on product save, `woocommerce_ajax_variation_threshold` filter, WDP bulk prices API interception via `rest_pre_dispatch` with transient cache. |
| **Widgets/** | |
| `Widgets/ModelSelector.php` | Elementor widget: visual model selector (card grid with images, names, prices). Since 0.9.111 `renderModelCards()` computes min price per model with one SQL query over child variations instead of `get_available_variations()`. |
| `Widgets/VariationDropdowns.php` | Elementor widget: attribute dropdown selectors that update based on model selection. Since 0.9.111 each option carries `data-image`, and the selected image tag has `loading="eager"`, `data-no-lazy="1"` and class `litespeed-no-lazy` to stop lazy-load plugins from blanking it. |
| `Widgets/ProductImage.php` | Elementor widget: dynamic product image that changes with variation selection. Since 0.9.111 the initial gallery is resolved from the product's `pa_model` terms, not from variations. |
| `Widgets/ProductOrderPanel.php` | Elementor widget: quantity selector, inline bulk price grid, price display (Einzelpreis + Gesamtpreis), action buttons (Editor / Anfrage / Cart). |
| `Widgets/BulkPriceTable.php` | Elementor widget: standalone bulk price table with clickable tier cells. |
| `Widgets/ProductInfoBox.php` | **New in 0.9.111.** Elementor widget `infoplus-product-info-box`: rows for SKU of the selected variation, minimum order quantity and delivery time. Controls: heading, per-row show/hide, SKU label, unit text, default delivery days (15), prefix/suffix, icons and colors, typography. Server side it reads `_infoplus_min_quantity` and `_infoplus_delivery_days`. |
| **Fields/** | |
| `Fields/AttributeTermFields.php` | Adds image, tooltip, and modal content fields to WooCommerce attribute term edit pages. |
| `Fields/ProductAttributeImages.php` | **Main admin meta box "Infoplus Suite"**, three tabs: Attribute Overrides (per-product attribute images + order type per model term), IBE/IFE (UUID, Print API, Mindestanzahl, Lieferzeit), Default Variation (per-model cascading dropdowns). |
| **Assets/** | |
| `Assets/product-state.js` | **Central state manager**: event bus + shared qty, `setQty()`, `applyModelDefault()`, `loadDeferredVariations()`. Events: `model:changed`, `variation:found`, `variation:notfound`, `variation:reset`, `attribute:changed`, `qty:changed`, `minqty:changed`, `variations:loading`, `variations:loaded`. |
| `Assets/product-info-box.js` | **New in 0.9.111.** ProductInfoBox widget: writes the SKU on `variation:found` ("Kein SKU" when the variation has none), clears it on `variation:notfound` / `variation:reset`, updates the min qty on `minqty:changed`, re-applies after `variations:loaded`. |
| `Assets/model-selector.js` | Model selector widget frontend logic. |
| `Assets/variation-dropdowns.js` | Variation dropdowns frontend logic. |
| `Assets/product-image.js` | Product image widget frontend logic. |
| `Assets/product-order-panel.js` | ProductOrderPanel widget: qty buttons, inline bulk grid, price display, button state, AJAX cart. |
| `Assets/product-order-panel.css` | ProductOrderPanel widget styles. |
| `Assets/bulk-price-table.js` | BulkPriceTable widget: renders price tiers, handles cell clicks → `setQty()`, updates active cell on `qty:changed`. Since 0.9.111 it has three display states: grid (prices found), hidden (model is `requestable` or `requestable_with_variants`, or no variation selected), and a frosted "no prices" overlay with placeholder cells and the text "Für diese Variante sind noch keine Staffelpreise hinterlegt" (variation selected but no tiers or no SKU). |
| `Assets/bulk-price-table.css` | BulkPriceTable widget styles, including `.ibpt-no-prices-lock` overlay and `.ibpt-placeholder-cell`. |
| `Assets/product-order-panel.js` (0.9.111 additions) | Effective minimum qty: `state.productMinQty` is the product meta value, `state.minQty` is raised to the first bulk tier's `from` when higher (`applyBulkMinQty`), and reset when no tiers exist. Emits `minqty:changed`. Active buttons get `infoplus-btn--pulsing`. Error hint auto-hides after 5 s (was 2 s). |
| `Assets/product-detail-suite.css` | Shared CSS variables and base styles for all widgets. Since 0.9.111 also the `.ip-variations-loading` shimmer for deferred variations and the `.ip-info-box__*` styles. |
| `Assets/admin-product-attribute-images.js` | Admin JS for the "Infoplus Suite" meta box: tab switching, per-model default variation cascading dropdowns, attribute filtering. Since 0.9.111 also the lock/unlock toggle for the IBE/IFE fields (`.infoplus-ife-lock-btn`, strings from `infoplusI18n`). |
| `Assets/admin-product-attribute-images.css` | Admin styles for the meta box, including the lock button states. |
| `Assets/admin-variation-fields.js`, `.css` | Enqueued by `enqueueAdminAssets()` on the product edit screen. Companion of the deprecated `Fields/VariationFields.php`. |
| `Fields/VariationFields.php` | Deprecated and empty. The file says it can be deleted. Not instantiated. |

#### Elementor Widget Category

Registers `infoplus-product` category ("Infoplus Product") in Elementor.

#### Shared State System (`product-state.js`)

All widgets communicate through `window.infoplusProductState`:

```
ModelSelector    → fires "model:changed"    → VariationDropdowns, ProductOrderPanel listen
VariationDropdowns → fires "variation:found" → ProductImage, ProductOrderPanel, BulkPriceTable listen
ProductOrderPanel  → fires "qty:changed" (source='panel')  → BulkPriceTable updates cell highlight
BulkPriceTable     → calls setQty() → fires "qty:changed" (source='external') → ProductOrderPanel updates input+price
```

Key state properties on `window.infoplusProductState`:

| Property | Description |
|----------|-------------|
| `selectedModel` | Current model slug |
| `selectedAttributes` | `{ pa_*: value }` map |
| `selectedVariation` | Full variation object after `variation:found` |
| `currentQty` | Current quantity (shared across widgets) |
| `availableAttributes` | Computed available values per attribute |

Key methods:

| Method | Description |
|--------|-------------|
| `setModel(slug)` | Selects a model, resets other attrs, emits `model:changed`, applies per-model default, syncs to WC |
| `setAttribute(attr, value)` | Sets one attribute, rebuilds available attrs, emits `attribute:changed`, syncs to WC |
| `setQty(qty, source)` | Sets shared qty, emits `qty:changed`. `source` defaults to `'external'`; panel passes `'panel'` to prevent echo |
| `applyModelDefault(slug)` | Batch-writes per-model default variation attributes without intermediate WC syncs |
| `syncToWooCommerce()` | Sets all WC form `<select>` values silently, triggers change once on the last, prevents WC intermediate resets |
| `findMatchingVariation()` | Requires ALL variation-type attributes selected; emits `variation:found` or `variation:notfound` |
| `loadDeferredVariations()` (0.9.111) | When `productData._variationsDeferred` is true, fetches `GET {restUrl}product/{id}/variations`, stores the result in `productData.variations`, rebuilds available attributes, emits `variations:loaded`, resets `selectedModel` so `setModel()` re-runs with real data, then `applyDefaultVariation()`. Emits `variations:loading` before the request. On failure emits `variations:loaded` with `error: true`. |
| `buildAvailableAttributes()` (0.9.111) | Normalises WooCommerce's `attribute_pa_*` keys to bare `pa_*` keys so `availableAttributes` matches the dropdown widgets' `data-attribute` values. |

Additional events since 0.9.111:

| Event | Emitted by | Payload |
|-------|-----------|---------|
| `minqty:changed` | `product-order-panel.js` (`applyBulkMinQty`, `resetMinQty`), `elementor-bulk-calculator.js` | `{ minQty }` |
| `variations:loading` | `product-state.js` | `{}` |
| `variations:loaded` | `product-state.js` | `{ count, availableAttributes }` or `{ count: 0, error: true }` |
| `qty:changed` with `source: 'calculator'` | `elementor-bulk-calculator.js` | `{ qty, source }`; the calculator ignores events with its own source |

`variation-dropdowns.js` listens to `attribute:changed` and visually selects the option when the value was set programmatically (for example by `applyModelDefault`). It also adds `ip-variations-loading` to the widget between `variations:loading` and `variations:loaded`. `pulseActionButton()` pulses `#anfrage` for requestable types and `#editor` / `.infoplus-btn--editor` / `.infoplus-btn--cart` otherwise.

#### REST Endpoints (namespace: `infoplus/v1`)

| Method | Route | Permission | Purpose |
|--------|-------|------------|---------|
| GET | `/product/{id}/detail` | Public | Full product data with custom fields, variations, models, attributes |
| GET | `/product/{id}/variations` | Public | **New in 0.9.111.** Lightweight variation list (`id`, `sku`, `price`, `is_in_stock`, `is_purchasable: true`, `attributes`, `image`) built by `buildVariationsDataLite()` from three `$wpdb` queries. Used by `loadDeferredVariations()`. Returns 404 for non-variable products. |
| GET | `/products/prices` | Public | Bulk price tiers for a SKU. The route itself is registered by InfoplusAPI; the Suite **intercepts** it via `rest_pre_dispatch` (see Section 12). Result cached in transient `infoplus_prices_{md5(sku)}` for 24 h. |
| GET | `/attribute-terms/{attribute}` | Public | Attribute terms with images, tooltips, modal content |

`wp_localize_script` passes `window.infoplusProductConfig = { restUrl: rest_url('infoplus/v1/'), nonce, ajaxUrl }`. `loadDeferredVariations()` falls back to the hardcoded path `/wp-json/infoplus/v1/` when the config object is missing.

#### Frontend Global Variable

```js
window.infoplusProductData = {
  id, name, sku, type, image, gallery, price, price_html,
  variations: [{ id, sku, price, attributes: { 'attribute_pa_*': value }, is_in_stock, ... }],
  attributes: { pa_model: { name, variation: true, terms: [{ slug, name, image_url, ... }] }, ... },
  models: [{ slug, name, image_url, min_price, variation_count, order_type, ... }],
  ife_uuid, editor_url,
  min_quantity,              // from _infoplus_min_quantity
  delivery_days,             // from _infoplus_delivery_days
  default_variation_id,      // legacy single default (int|null)
  model_default_variations,  // per-model defaults map: { "slug": variation_id, ... } | null
  _variationsDeferred,       // 0.9.111: true when variations were NOT embedded (see Section 17)
}
```

When the product has more than 200 child variations (`Module::VARIATION_AJAX_THRESHOLD`), `outputProductState()` uses `buildProductDataLite()`: `variations` is `[]`, `models` comes from `buildModelsDataLite()` (one SQL query over child `_price` and `attribute_pa_model` meta plus `pa_model` terms), and `_variationsDeferred` is `true`. The same constant is returned from the `woocommerce_ajax_variation_threshold` filter so WooCommerce also stops embedding its own variation JSON for such products. The whole JSON is cached in the transient `infoplus_pd_{product_id}_{Module::VERSION}` for 12 hours and deleted on `woocommerce_after_product_object_save` (product and parent of a variation).

#### Term Meta Keys

| Meta Key | Description |
|----------|-------------|
| `_infoplus_term_image_id` | Attachment ID for term image |
| `_infoplus_term_tooltip` | Tooltip text |
| `_infoplus_term_modal_content` | Extended description / modal content |

#### Variation Meta Keys

| Meta Key | Description |
|----------|-------------|
| `_infoplus_model_image_id` | Custom model image attachment ID |
| `_infoplus_model_description` | Model description |
| `_infoplus_model_tooltip` | Model tooltip |

These three keys are read in `Module::buildVariationsData()` only. Nothing in the current code writes them (the writer was the deprecated `Fields/VariationFields.php`). Whether production still has values is unverified.

#### Per-product attribute override keys

`Fields/ProductAttributeImages.php` stores per-product overrides on the parent product with the pattern `_infoplus_attr_{type}_{attribute_slug}_{term_slug}`, where `attribute_slug` is the taxonomy name without the `pa_` prefix. Types: `image`, `gallery`, `tooltip`, `modal`, `hide_image`, `hide_tooltip`, `hide_modal`, `order_type`. Example: `_infoplus_attr_order_type_model_alu`. `getTermGalleryUrls()` resolves gallery, then single image, then the global term image. `getTermOrderType()` validates against the four allowed order types and falls back to `editable`.

#### Cross-module usage

- Reads **EditorLinkSettings** via `generateEditorUrl()` for editor links
- Reads **WooProductVariationFields** meta (`_ife_uuid`) for editor URL
- Shares product page context with **WooSingleProductAddons** (both inject data via `woocommerce_after_single_product`)

---

### 4.6 UserDashboard

> **Slug:** `user-dashboard`  
> **Purpose:** Frontend user account management (profile, password, avatar, login flow).  
> **Has admin tab:** No (runs in background)

#### What it does

Provides REST API endpoints for user profile management, handles login/logout redirects to a custom `/account` page (instead of WooCommerce's `/my-account`), includes a login guard shortcode and a full profile settings form shortcode, registers the UserStatCard Elementor widget, and provides a dropdown menu script for the frontend navigation.

#### Files

| File | Role |
|------|------|
| `Module.php` | REST endpoints, login/logout redirects, two shortcodes, Elementor widget registration, frontend scripts. |
| `Widgets/UserStatCard.php` | **New in 0.9.111.** Elementor widget `infoplus-user-stat-card` ("User Stat Cards", category `infoplus-product`). A repeater of cards, each with a data source, label, icon and colors, rendered as a CSS grid (`.ip-stat-grid`). |
| `Assets/user-stat-card.css` | **New in 0.9.111.** Grid and card styles for the widget. Registered as `infoplus-user-stat-card` in `registerWidgets()`. |
| `Assets/user-dashboard.js` | Frontend JS for profile management API calls. |
| `Assets/menu-dropdown.js` | Navigation dropdown menu behavior (login/register/account/logout links). |
| `Assets/menu-dropdown.css` | Dropdown menu styling. |
| `Assets/admin-*.{js,css}`, `model-selector.js`, `product-detail-suite.css`, `product-image.js`, `product-state.js`, `variation-dropdowns.js` | Copies of ProductDetailSuite assets. Never enqueued by this module. Some copies drifted in 0.9.111 (they received the same `elementorFrontend.hooks` guard and image fix as the originals) but `product-state.js` did not receive the deferred-loading code. Roadmap R-02 (still open). |

#### UserStatCard data sources

| `data_source` value | Value shown |
|---------------------|-------------|
| `orders_total` | Count of the current user's orders in statuses pending, processing, on-hold, completed, cancelled, refunded, failed |
| `orders_processing` | Count with status `wc-processing` |
| `orders_completed` | Count with status `wc-completed` |
| `total_spent` | `wc_get_customer_total_spent()` formatted as `1.234,56 EUR` |
| `projects_total`, `shared_projects` | Placeholder, always `0`. Labelled "(Platzhalter)" in the admin. The InfoplusAPI `customerdashboard` route has the project count logic that this could use after the merge. |
| `custom` | Static text from the `custom_value` control |

Logged-out viewers (for example the Elementor editor preview) get demo values.

#### REST Endpoints (namespace: `infoplus/v1`)

| Method | Route | Permission | Purpose |
|--------|-------|------------|---------|
| GET | `/user/profile` | Logged in | Returns legacy fields (`user_name`, `user_email`, `user_company`, `avatar_url`) plus `first_name`, `last_name`, `phone`, `company`, `vat_id`, `address_1`, `address_2`, `postcode`, `city`, `country` |
| POST | `/user/profile` | Logged in | Updates name and email via `wp_update_user`, the WooCommerce `billing_*` user meta, `infoplus_vat_id`, and `infoplus_company` (kept for backward compatibility). Accepts `user_company` as a legacy alias of `company`. |
| POST | `/user/password` | Logged in | Change password (requires current) |
| POST | `/user/avatar` | Logged in | Upload avatar image (JPG/PNG/GIF, 5MB max) |

#### User Meta Keys

| Meta Key | Description |
|----------|-------------|
| `infoplus_user_avatar` | Attachment ID of custom avatar |
| `infoplus_company` | Company name (legacy; `billing_company` is preferred when reading) |
| `infoplus_vat_id` | VAT ID |
| `billing_first_name`, `billing_last_name`, `billing_company`, `billing_phone`, `billing_address_1`, `billing_address_2`, `billing_postcode`, `billing_city`, `billing_country` | Standard WooCommerce billing meta, written by the profile endpoint so checkout auto-fills |

#### Login Flow

- `login_redirect` and `woocommerce_login_redirect` filters (priority 999) redirect all logins to `/account`
- `template_redirect` → `redirectLoggedInUsers()` sends logged-in users who open `/log-in` to `/account`
- `template_redirect` → `redirectGuestUsers()` sends guests who open `/account` to `/log-in/`
- `login_form_logout` → `skipLogoutConfirmation()` (0.9.111) logs the user out before WordPress shows its "Do you really want to log out?" page, then redirects to `redirect_to` or home. This runs before the nonce check on purpose, so stale logout links from cached pages still work.
- On the login page for guests, an inline script (0.9.111) watches for the Essential Addons register form success message (`.eael-register-form-wrapper .eael-form-msg.valid`) and reloads `/log-in/` after 2 seconds.
- Elementor editor/preview mode is excluded from redirects

Hardcoded page paths in this module: `/account`, `/log-in`, `/log-in/?eael-register=1`.

#### Shortcodes

| Shortcode | Purpose |
|-----------|---------|
| `[infoplus_login_guard]` | Styled "Login Required" message with a redirect button for guests. Returns empty for logged-in users. |
| `[infoplus_profile_settings]` | Full profile form (name, email, phone, company, VAT ID, billing address with a country `<select>` from `WC()->countries->get_countries()`, password change, avatar upload). Saves through the profile endpoints above. This replaces the plan that Section 16 of the 0.9.93 documentation listed as "not started". |

#### Frontend JS Variables

```js
window.infoplusMenuDropdown = { isLoggedIn, loginUrl, registerUrl, accountUrl, logoutUrl }
window.infoplusUserDashboard = { restUrl, nonce, currentUserId }
```

---

### 4.7 UserOrders

> **Slug:** `user-orders`  
> **Purpose:** Order history display, order detail pages, Elementor widgets and dynamic tags for orders, and custom Thank You page.  
> **Has admin tab:** No (runs in background)

#### What it does

The most feature-rich frontend module. Provides complete order management for customers: order list widget, order detail widget, custom URL routing (`/order/123/`), Elementor Loop Grid integration (query ID `infoplus_user_orders`, post type `shop_order`), 8 dynamic tags for order data, and a fully custom WooCommerce Thank You page. It also owns everything that styles the WooCommerce Blocks cart and checkout, the custom empty-cart overlay, and the branded customer emails.

#### Files

| File | Role |
|------|------|
| `Module.php` | REST endpoints, rewrite rules, template routing, Thank You page rendering, widget/tag registration, cart/checkout inline styles, empty-cart overlay, checkout login link rewrite, email branding. |
| `ElementorLoopIntegration.php` | Integrates with Elementor Loop Grid to use orders as a custom data source. |
| **Widgets/** | |
| `Widgets/UserOrdersGrid.php` | Elementor widget: displays order list in a card grid with search/filter. Since 0.9.111 the `wc_get_orders` call passes an explicit status list (pending, processing, on-hold, completed, cancelled, refunded, failed) so draft/checkout-draft orders are excluded. |
| `Widgets/OrderDetails.php` | Elementor widget: displays single order details (reads from URL query var). Item image: order item meta `_infoplus_model_image_url` first, then the product image at size `medium`, shown in a 120x60 box with `object-fit: contain` (2:1 ratio for 300x150 badge products). Since 0.9.111 it ships a full A4 print stylesheet (`@media print`): hides header, footer, back button, actions and images; compact three-column customer grid; suppresses link URLs. |
| **DynamicTags/** | |
| `DynamicTags/OrderTagBase.php` | Abstract base class for all order dynamic tags. |
| `DynamicTags/OrderNumber.php` | Dynamic tag: `{order_number}` |
| `DynamicTags/OrderDate.php` | Dynamic tag: `{order_date}` |
| `DynamicTags/OrderStatus.php` | Dynamic tag: `{order_status}` |
| `DynamicTags/OrderTotal.php` | Dynamic tag: `{order_total}` |
| `DynamicTags/OrderItemsList.php` | Dynamic tag: `{order_items_list}` |
| `DynamicTags/OrderItemsCount.php` | Dynamic tag: `{order_items_count}` |
| `DynamicTags/OrderPaymentMethod.php` | Dynamic tag: `{order_payment_method}` |
| `DynamicTags/OrderViewUrl.php` | Dynamic tag: `{order_view_url}` |
| **Assets/** | |
| `Assets/user-orders.js` | Frontend JS for order interactions. Enqueued for logged-in users with `window.infoplusUserOrders`. |
| `Assets/user-orders.css` | Order display styling. Enqueued for logged-in users only. |
| `Assets/woo-pages.css` | **New in 0.9.111** (631 lines). External stylesheet for WooCommerce Blocks cart, checkout and thank-you pages. Enqueued for everyone (guests included) when `is_cart()`, `is_checkout()` or `is_wc_endpoint_url('order-received')`. Sections: design tokens, base font, cart (items panel, 2:1 item images, qty badge, sidebar, totals, coupon, proceed button), checkout steps, floating-label form inputs, address card, radio accordion options (shipping, payment, saved addresses), payment section, place order button, notices, classic shortcode fallback. |
| `Assets/cart-qty-fix.js` | **New in 0.9.111.** Vanilla JS on cart and checkout. Reads `wp.data.select('wc/store/cart').getCartData().items` and appends a `.ip-cart-qty-badge` ("× qty") to each product name in cart rows (only when the native quantity selector is not visible) and in the checkout order summary. Re-runs on store changes and DOM mutations. The file comment calls it a temporary solution. |
| `test-orders.php` | Debug shortcode template. |
| `test-orders-advanced.php` | Advanced debug template. |

#### Cart, checkout and empty cart (WooCommerce Blocks)

| Hook | Method | What it does |
|------|--------|--------------|
| `wp_head` | `enqueueCartStyles()` | Inline `<style>` on `is_cart()` (Section 15) |
| `wp_head` | `enqueueCheckoutStyles()` | Inline `<style id="infoplus-checkout-styles">` on `is_checkout()`: viewport fill (`min-height: calc(100vh - 684px)`) and checkout tweaks |
| `render_block` | `filterWooCartBlock()` | For `woocommerce/cart`: wraps the block, adds a custom empty-cart overlay and a script that shows it when the cart becomes empty. Two strategies since 0.9.111: `wp.data` subscription on `wc/store/cart` (checks `items_count`, then `itemsCount`, then `items.length`) and a `MutationObserver` on `.wp-block-woocommerce-filled-cart-block`. |
| `render_block` | `filterWooCheckoutBlock()` | **New in 0.9.111.** For `woocommerce/checkout`: rewrites the `href` of `.wc-block-checkout__login-prompt` to `/log-in/` (two regexes for either attribute order). |
| `woocommerce_cart_is_empty` | `renderEmptyCartContent()` | Classic empty-cart hook: branded layout with links to `/namensschilder/`, `/account/`, `/log-in/` |
| `wp_enqueue_scripts` | `enqueueStyles()` | See asset table above |

#### Branded customer emails

`brandOrderEmailStyles()` (`woocommerce_email_styles`) appends brand CSS and `renderOrderEmailBanner()` (`woocommerce_email_before_order_table`) prints a banner for the emails listed in `BRANDED_EMAIL_IDS`: `customer_processing_order` and `customer_completed_order`. The 0.9.111 diff only changed the banner icon markup to flexbox centering.

#### REST Endpoints (namespace: `infoplus/v1`)

| Method | Route | Permission | Purpose |
|--------|-------|------------|---------|
| GET | `/orders` | Logged in | List current user's orders (supports `status`, `limit`, `search` params) |
| GET | `/orders/{id}` | Logged in | Single order details (ownership verified) |

#### URL Routing

Custom rewrite rule: `/order/{id}/` → loads the WordPress page with slug `order`, sets `infoplus_order_id` query var.

**Security checks:**
1. User must be logged in → shows login page
2. Order must exist → shows 404-style page
3. User must own the order → shows access denied page
4. WordPress page "order" must exist → shows config error

All error pages are styled in German with the brand pink color.

#### Thank You Page

Completely replaces WooCommerce's default Thank You page with a branded design featuring:
- Success header with checkmark icon
- Order details card (number, date, status, payment method, email)
- Shipping address card
- Ordered items list with images and prices
- Totals breakdown (subtotal, shipping, tax, grand total)
- Action buttons (Dashboard, Continue Shopping)

Hides default WooCommerce order details via CSS.

#### Elementor Dynamic Tag Group

Registers `infoplus-orders` group containing all 8 order dynamic tags.

#### Shortcodes

| Shortcode | Purpose |
|-----------|---------|
| `[infoplus_test_orders]` | Debug: shows user info and raw order data |
| `[infoplus_order_received]` | Manual placement of Thank You content |
| `[infoplus_empty_cart]` | Manual placement of the empty-cart layout. Returns an empty string when the cart has items. |

#### Thank You page (0.9.111 changes)

The title hide rule is now `body.woocommerce-order-received .entry-title` instead of a page-ID specific selector. Cards lost their borders and shadows, item images use size `medium`, and the primary color falls back to `#e91e8c` when the Elementor global is missing.

---

### 4.8 ElementorUserAvatar

> **Slug:** `elementor-user-avatar`  
> **Purpose:** Custom Elementor dynamic tag and WordPress avatar filter for user profile pictures.  
> **Has admin tab:** No (runs in background)

#### What it does

Registers a custom Elementor dynamic tag for user profile images and filters WordPress's `get_avatar_url` to use custom uploaded avatars stored in user meta.

#### Files

| File | Role |
|------|------|
| `Module.php` | Dynamic tag registration, `get_avatar_url` filter with multi-plugin fallback. |
| `DynamicTags/UserProfileImage.php` | Elementor dynamic tag implementation for user avatar. |

#### Avatar Resolution Order

The `get_avatar_url` filter checks these user meta keys in order:

1. `user_profile_picture` (User Profile Picture plugin)
2. `infoplus_user_avatar` (Infoplus custom, set by UserDashboard module)
3. `wp_user_avatar` (WP User Avatar plugin)
4. `basic_user_avatar` (Simple Local Avatars)
5. `simple_local_avatar` (Simple Local Avatars)
6. Falls back to Gravatar

Each value is checked as: attachment ID → URL string → array with `url` key → array with `full` key.

#### Cross-module usage

- Works with **UserDashboard** which saves avatars to `infoplus_user_avatar` meta key

---

### 4.9 Custom404

> **Slug:** `custom-404`  
> **Purpose:** Branded 404 page injected into the theme's layout.  
> **Has admin tab:** No (runs in background)  
> **Added:** commit `6b0d87f` (before 0.9.93, not documented until 0.9.111)

#### What it does

The theme renders its own 404 markup without `the_content()`, so a content filter cannot replace it. The module therefore:

1. On `wp`, if `is_404()` and not in Elementor preview/editor, hooks `wp_head` and `wp_footer`.
2. `injectHideStyles()` (`wp_head`) prints a `<style id="ip404-hide">` that hides `.page-header`, `.page-content`, `.entry-header`, `.widget_search` and `.search-form` on `body.error404` and removes main padding.
3. `injectContent()` (`wp_footer`) prints a hidden `#ip404-mount` block with the branded UI, its `<style id="ip404-styles">`, and an inline script that moves the block to the first match of `main#content.site-main`, `main.site-main`, `#main`, `#content`, `.site-main`, `main`, then shows it. The script also measures header and footer height (fallback 83 px and 601 px) and sets `min-height` on `.ip404-wrap` so the page fills the viewport.
4. `wp_title` and `pre_get_document_title` return the string `404`, an en dash, `Seite nicht gefunden` on 404 pages.

#### Content

Hero card with "404" and "Diese Seite existiert nicht", a tips card ("SO GEHT'S WEITER") and action buttons. Links depend on login state:

| Target | URL | Shown to |
|--------|-----|----------|
| Startseite | `home_url('/')` | all |
| Namensschilder | `home_url('/namensschilder/')` | all |
| Dashboard | `home_url('/account/')` | logged in |
| Anmelden | `home_url('/log-in/')` | guests |
| Registrieren | `home_url('/log-in/')?eael-register=1` | guests |

All strings are hardcoded German. Colors use `--e-global-color-primary` with `#e91e8c` fallback.

#### Files

| File | Role |
|------|------|
| `Module.php` | Everything (hooks, CSS, HTML, JS are all inline in this one file). No assets folder. |

---

### 4.10 Inquiry

`src/Modules/Inquiry/`, added in Plugin 0.10.29. The inquiry form "Individuelle Anfrage" for the classic theme: REST routes, the private post type `infoplus_inquiry` (WooCommerce → Anfragen), protected file storage, e-mails and a settings tab. Full description in Section 21.

---

## 5. Module Dependency Map

```
EditorLinkSettings ◄──── WooSingleProductAddons (reads editor URL config)
       ▲                         ▲
       │                         │
       └──── ProductDetailSuite ─┘ (reads editor URL + product UUID)
                    ▲
                    │
WooProductVariationFields (backup save of _ife_uuid, _ife_print_api_id meta)
       ▲
       │
IkonaIFE ──► InfoplusAPI plugin (external, reference/infoplus_api/)
   SyncService reads /infoplus/v1/products from it,
   dashboard.js calls /infoplus/v1/products/synchronization on it,
   InfoplusAPI writes _ife_uuid and calls editor.infoplus.de

UserDashboard ────► ElementorUserAvatar (shares infoplus_user_avatar meta key)
UserDashboard (UserStatCard) reads WooCommerce orders directly

UserOrders (reads WooCommerce order data directly; OrderDetails reads
            _infoplus_model_image_url written by WooSingleProductAddons)

ProductDetailSuite (rest_pre_dispatch) ──► overrides InfoplusAPI's
            /infoplus/v1/products/prices handler

Custom404 (standalone)
```

**No circular dependencies inside the Suite.** EditorLinkSettings is the most depended-upon module. Three Suite modules share hardcoded page paths with each other (`/account`, `/log-in`, `/namensschilder`): UserDashboard, UserOrders and Custom404.

---

## 6. REST API Endpoints

Regenerated 2026-09-17 from every `register_rest_route` call in `src/` and `reference/infoplus_api/`.

### Namespace: `infoplus-suite/v1` (Suite only, `IkonaIFE/SyncController.php`)

| Method | Route | Module | Auth |
|--------|-------|--------|------|
| GET | `/products` | IkonaIFE | manage_woocommerce |
| POST | `/products/{id}/sync-result` | IkonaIFE | manage_woocommerce |
| GET | `/products/{id}/sync-status` | IkonaIFE | manage_woocommerce |
| POST | `/products/clear-cache` | IkonaIFE | manage_woocommerce |

### Namespace: `infoplus/v1`, routes registered by the Suite

| Method | Route | Module | Auth |
|--------|-------|--------|------|
| GET | `/user/profile` | UserDashboard | logged_in |
| POST | `/user/profile` | UserDashboard | logged_in |
| POST | `/user/password` | UserDashboard | logged_in |
| POST | `/user/avatar` | UserDashboard | logged_in |
| GET | `/orders` | UserOrders | logged_in |
| GET | `/orders/{id}` | UserOrders | logged_in |
| POST | `/sonderkontur/upload` | WooSingleProductAddons | public (`__return_true`) |
| GET | `/sonderkontur/files` | WooSingleProductAddons | public |
| DELETE | `/sonderkontur/files/{index}` | WooSingleProductAddons | public |
| POST | `/sonderkontur/clear` | WooSingleProductAddons | public |
| GET | `/product/{id}/detail` | ProductDetailSuite | public |
| GET | `/product/{id}/variations` | ProductDetailSuite (new in 0.9.111) | public |
| GET | `/attribute-terms/{attribute}` | ProductDetailSuite | public |
| POST | `/inquiries/session` | Inquiry (0.10.29) | public (`__return_true`), rate limited; issues a signed form token |
| POST | `/inquiries/files` | Inquiry (0.10.29) | form token |
| DELETE | `/inquiries/files/{id}` | Inquiry (0.10.29) | form token |
| POST | `/inquiries` | Inquiry (0.10.29) | form token, plus `X-WP-Nonce` for logged-in visitors |

Not a registered route but effectively a Suite endpoint: `GET /infoplus/v1/products/prices?sku=X` is answered by `ProductDetailSuite\Module::interceptBulkPricesRoute()` on `rest_pre_dispatch` before the InfoplusAPI handler runs (Section 12).

### Namespace: `infoplus/v1`, routes registered by InfoplusAPI (separate plugin, `reference/infoplus_api/`)

| Method | Route | File | Auth | Notes |
|--------|-------|------|------|-------|
| GET | `/products` | `entities/Products.php` | none declared (public, WordPress logs a `_doing_it_wrong` notice) | Read by Suite `SyncService::fetchProducts()`. Builds the product list with sync state from `infoplus_products` and `infoplus_synchronizations` tables. |
| GET | `/products/prices?sku=` | `entities/Products.php` | `__return_true` | Reads `wp_wdp_rules`. Shadowed by the Suite interception. |
| GET | `/products/variants?productId=` | `entities/Products.php` | `__return_true` | Variants for a product |
| GET | `/products/synchronization[?productId=]` | `entities/Products.php` | `__return_true` | Runs the sync against `editor.infoplus.de/smp/infoplus/updateProduct`, writes `_ife_uuid`, emails a report to the hardcoded recipient list. Called by Suite `dashboard.js` (handlers currently disabled). |
| GET | `/cart/updateCart` | `entities/Cart.php` | `__return_true` | Editor cart entry: adds a product/variant to the WooCommerce cart with `display`, `project_id`, `ife_price`, `ife_thumbnail_url`, optional coupon, then redirects to a hardcoded cart URL. |
| GET | `/cart/testing?sku=` | `entities/Cart.php` | `__return_true` | Test endpoint |
| GET | `/testing` | `InforplusAPI.php` | none declared | Returns the current user object |
| GET | `/customerdashboard?id=` | `modules/CustomerDashboard.php` | none declared | Project count from `editor.infoplus.de/smp/infoplus/getProjects` plus order count for a customer ID passed in the query string |
| GET | `/customerdashboard/orders?customerId=` | `modules/CustomerDashboard.php` | none declared | Orders for a customer ID |
| GET | `/customerdashboard/orders/detailed?orderId=` | `modules/CustomerDashboard.php` | none declared | Order detail |
| GET | `/customerdashboard/events?customerId=` | `modules/CustomerDashboard.php` | none declared | Customer events from the editor |

`validate_api_key()` in `InforplusAPI.php` compares the `Authorization` header with `Bearer INFOPLUS_API_KEY`, but no route uses it as `permission_callback` in the reference copy. All InfoplusAPI routes are therefore public. InfoplusAPI also sends `Access-Control-Allow-Origin: *` on every REST response.

### admin-ajax actions

| Action | Module | Nonce |
|--------|--------|-------|
| `infoplus_add_to_cart` (priv and nopriv) | WooSingleProductAddons | none |
| `infoplus_sync_all`, `infoplus_sync_product` | IkonaIFE (legacy, unused by the dashboard) | `infoplus_sync` |

---

## 7. WooCommerce Meta Keys

### Product Meta (post_meta on products)

| Key | Set By | Description |
|-----|--------|-------------|
| `_ife_uuid` | WooProductVariationFields / IkonaIFE Sync | Product UUID in IFE system |
| `_ife_print_api_id` | WooProductVariationFields / IkonaIFE Sync | Print API identifier |
| `_ife_unique_text_name` | WooProductVariationFields | IFE display name |
| `_ife_name_2` | WooProductVariationFields | Secondary name |
| `_ife_ife_image` | WooProductVariationFields | IFE image URL |
| `_ife_preview_images` | WooProductVariationFields | Preview images (JSON array) |
| `_ife_template_zip` | WooProductVariationFields | Template ZIP URL |
| `_infoplus_last_synced` | IkonaIFE SyncService | Last sync timestamp |
| `_infoplus_sync_status` | IkonaIFE SyncService | Sync status (0/1/2) |
| `_infoplus_ife_product_id` | IkonaIFE SyncService | IFE product UUID (post-sync) |
| `_infoplus_ife_variant_id` | IkonaIFE SyncService | IFE variant UUID (on variations) |
| `_infoplus_model_image_id` | ProductDetailSuite | Custom model image (on variations) |
| `_infoplus_model_description` | ProductDetailSuite | Model description (on variations) |
| `_infoplus_model_tooltip` | ProductDetailSuite | Model tooltip (on variations) |
| `_infoplus_min_quantity` | ProductDetailSuite (IBE/IFE tab) | Minimum order quantity, exposed as `productData.min_quantity` |
| `_infoplus_delivery_days` | ProductDetailSuite (IBE/IFE tab) | Delivery days, exposed as `productData.delivery_days` |
| `_infoplus_default_variation_id` | ProductDetailSuite (Default Variation tab) | Legacy single default variation ID (int) |
| `_infoplus_model_default_variations` | ProductDetailSuite (Default Variation tab) | JSON map `{"model-slug": variation_id}` for per-model defaults |
| `_infoplus_attr_order_type_{attr}_{term}` | ProductDetailSuite (Attribute Overrides tab) | Order type per `pa_model` term: `editable`, `requestable`, `requestable_with_variants`, `buyable`. `attr` is the taxonomy without `pa_`. The 0.9.93 documentation wrote this key as `_infoplus_order_type_{term_slug}`, which was wrong. |
| `_infoplus_attr_image_{attr}_{term}` | ProductDetailSuite (Attribute Overrides tab) | Per-product override image (attachment ID) for a term |
| `_infoplus_attr_gallery_{attr}_{term}` | ProductDetailSuite (Attribute Overrides tab) | Per-product gallery for a term |
| `_infoplus_attr_tooltip_{attr}_{term}`, `_infoplus_attr_modal_{attr}_{term}` | ProductDetailSuite (Attribute Overrides tab) | Per-product tooltip / modal text |
| `_infoplus_attr_hide_image_{attr}_{term}`, `_infoplus_attr_hide_tooltip_{attr}_{term}`, `_infoplus_attr_hide_modal_{attr}_{term}` | ProductDetailSuite (Attribute Overrides tab) | Per-product hide flags |
| `_ife_product_bg_image`, `_ife_overlay_frame_image` | nobody (WooProductVariationFields deletes them on save) | Legacy keys, cleanup only |
| `min_quantity` | nobody in the Suite | Read by InfoplusAPI `getProducts()` (`$product->get_meta('min_quantity')`). The Suite uses `_infoplus_min_quantity` instead, so InfoplusAPI never sees the Suite's value. |

Regenerated 2026-09-17 by grepping the quoted key strings in `src/`.

### Term Meta (on product attribute terms)

| Key | Set By | Description |
|-----|--------|-------------|
| `_infoplus_term_image_id` | ProductDetailSuite | Term image attachment ID |
| `_infoplus_term_tooltip` | ProductDetailSuite | Term tooltip text |
| `_infoplus_term_modal_content` | ProductDetailSuite | Term extended description |

### User Meta

| Key | Set By | Description |
|-----|--------|-------------|
| `infoplus_user_avatar` | UserDashboard | Avatar attachment ID |
| `infoplus_company` | UserDashboard | Company name (legacy, still written for backward compatibility) |
| `infoplus_vat_id` | UserDashboard | VAT ID |
| `billing_first_name`, `billing_last_name`, `billing_company`, `billing_phone`, `billing_address_1`, `billing_address_2`, `billing_postcode`, `billing_city`, `billing_country` | UserDashboard | WooCommerce standard billing fields, written by `POST /user/profile` |

### Cart Item Data and Order Item Meta

| Key | Set By | Description |
|-----|--------|-------------|
| `_sonderkontur_files` | WooSingleProductAddons | Array of uploaded file data (order item meta) |
| `_infoplus_model_image_url` | WooSingleProductAddons (`ajaxAddToCart`, and since 0.10.27 `addBuyFormCartItemData` for the theme buy form) | Model image URL. Cart item data, copied to order item meta at checkout. Read by cart/order thumbnail filters and by the OrderDetails widget. The ajax path takes the URL from the request; the buy form path resolves it on the server. |
| `display`, `project_id`, `ife_price`, `ife_thumbnail_url`, `infoplus_unique` | InfoplusAPI (`woocommerce_add_cart_item_data`, `/cart/updateCart`) | Editor line items: display name, IFE project UUID, unit price from the editor, thumbnail. The first four are copied to order item meta at checkout. |

### Order Meta (InfoplusAPI)

| Key | Set By | Description |
|-----|--------|-------------|
| `order_attempts` | InfoplusAPI `new_order_action()` | Retry counter for posting the order to `editor.infoplus.de/processOrder` |

### Options (wp_options)

| Key | Set By | Description |
|-----|--------|-------------|
| `infoplus_editor_base_desktop_url`, `infoplus_editor_base_mobile_url`, `infoplus_editor_theme_id`, `infoplus_editor_cart_entry_path`, `infoplus_editor_back_path`, `infoplus_editor_bearer_token` | EditorLinkSettings (settings group `infoplus_editor_link_settings`) | See 4.1 |
| `infoplus_flush_rewrite_rules` | UserOrders | One-shot flag; when set, `registerRewriteRules()` flushes rewrite rules and deletes it |
| `infoplus_inquiry_settings` | Inquiry (tab Anfragen) | Array: recipient, max_upload_mb, max_files, attach_limit_mb, success_text, customer_mail, privacy_page. See Section 21 |
| `infoplus_inquiry_seq_{year}` | Inquiry `PostType::nextReference()` | Counter of the reference numbers ANF-{year}-{0000} |

### Transients

| Key | Set By | TTL | Invalidation |
|-----|--------|-----|--------------|
| `infoplus_products_cache` | IkonaIFE SyncService | 60 s | `POST /infoplus-suite/v1/products/clear-cache` |
| `infoplus_pd_{product_id}_{ProductDetailSuite VERSION}` | ProductDetailSuite `outputProductState()` | 12 h | `woocommerce_after_product_object_save` for the product or the parent of a saved variation; a VERSION bump changes the key |
| `infoplus_prices_{md5(sku)}` | ProductDetailSuite `interceptBulkPricesRoute()` | 24 h | none (WDP rule edits are not seen for up to 24 h) |
| Sonderkontur upload storage (key from `getStorageKey()`, session cookie or IP+UA hash) | WooSingleProductAddons | 24 h | `cleanupOldTransients()` on `wp_scheduled_delete` |
| `infoplus_inq_up_{token key}` | Inquiry `Files::storeTemp()` | 24 h | deleted when the inquiry is sent; files in `tmp/` older than a day removed by the daily cron `infoplus_inquiry_cleanup` |
| `infoplus_inq_done_{token key}` | Inquiry `Controller::submit()` | 6 h | none; answers a repeated submit with the same reference |
| `infoplus_inq_rl_{bucket}_{ip hash}` | Inquiry `Controller::limited()` | 15 min | expires |

### Custom database tables (InfoplusAPI, not created by the Suite)

| Table | Used by | Notes |
|-------|---------|-------|
| `infoplus_products` | InfoplusAPI `Products.php`, `Cart.php`, `ifeConnector.php` | Maps WooCommerce product/variation IDs (`woocommerce_ref_id`) to IFE UUIDs (`ife_id`), with `type`, `parent`, `modified`. No `wp_` prefix. |
| `infoplus_synchronizations` | InfoplusAPI `Products.php` | One row per sync run (`startTime`, `endTime`, type, product). No `wp_` prefix. |
| `{prefix}wdp_rules` | InfoplusAPI `Products.php`, Suite `getBulkPricesBySku()` | Advanced Dynamic Pricing rules table. The Suite uses `$wpdb->prefix . 'wdp_rules'` and guards with `SHOW TABLES LIKE`; InfoplusAPI hardcodes `wp_wdp_rules`. |

No code in the repo creates these tables (no `dbDelta`, no activation hook). How they were created on production is unverified.

---

## 8. Conventions & Patterns

### Version & Cache Busting

There are two version numbers plus two module constants. Verified 2026-09-17:

| Value | Where | Current | Used for |
|-------|-------|---------|----------|
| Plugin header `Version:` | `infoplus-suite.php` | `0.9.111` | What WordPress shows in the plugin list. Not used in code. |
| `Plugin::VERSION` | `src/Plugin.php` | `0.10.26` | Cache-bust string for assets of WooSingleProductAddons, UserDashboard, UserOrders, and the "Infoplus Suite" meta box admin assets (which append `.7`). |
| `ProductDetailSuite\Module::VERSION` | `src/Modules/ProductDetailSuite/Module.php` | `1.1.1` | Cache-bust for all ProductDetailSuite frontend and admin assets, and part of the product data transient key. |
| `IkonaIFE\Module::VERSION` | `src/Modules/IkonaIFE/Module.php` | `1.0.4` | Cache-bust for the sync dashboard assets. |

Rules:

- **After any JS/CSS change** in WooSingleProductAddons, UserDashboard or UserOrders: bump `Plugin::VERSION` and hard refresh (`Ctrl+Shift+R`).
- **After any change in ProductDetailSuite assets or in the shape of `infoplusProductData`**: bump `ProductDetailSuite\Module::VERSION`. This also invalidates every `infoplus_pd_*` transient because the version is part of the key.
- The plugin header version is bumped by hand at release time. The two numbers have drifted (0.9.111 vs 0.10.25) and there is no rule that ties them together. Roadmap R-08 proposes a single source of truth.

### Naming

- PHP namespaces: `Infoplus\Suite\Modules\{ModuleName}\`
- REST namespace: `infoplus-suite/v1` (internal), `infoplus/v1` (shared/public)
- CSS classes: `infoplus-*` prefix
- JS globals: `window.infoplus*` prefix
- Meta keys: `_ife_*` (IFE system), `_infoplus_*` (plugin), `infoplus_*` (user meta, no underscore = public)

### UI Design

- Brand color: `#e91e8c` (pink)
- Error color: `#a12828`
- Success color: `#137333`
- Borderless cards with soft shadows (`box-shadow: 0 2px 12px rgba(0,0,0,0.06)`)
- Rounded corners (`border-radius: 8-16px`)
- German localization for all user-facing text

### Error Handling

- REST endpoints return `WP_REST_Response` with `success` boolean
- Errors return `WP_Error` with appropriate HTTP status codes
- `WP_DEBUG` logging via `error_log()`

### Security

- REST permissions: `manage_woocommerce` for admin endpoints, `is_user_logged_in()` for user endpoints
- Nonce verification: `X-WP-Nonce` header for REST, `wp_verify_nonce` for forms
- Order ownership checks: customer_id comparison before returning data
- Elementor editor mode detection: bypasses redirects when editing

---

## 9. Anfrage Popup: Data Injection Pattern

### Overview

The "Anfrage" (inquiry) call-to-action is an **Elementor popup** triggered by the Anfrage button in the `ProductOrderPanel` widget. Because the popup is not in the DOM until opened, standard jQuery selectors fail, the solution is a script that listens for the Elementor popup event.

### Why a script attached to the popup form

Elementor popups are injected into the DOM lazily when first triggered. Attempting to bind events or populate fields on `$(document).ready` will find nothing. The solution is a **custom JS snippet attached directly to the popup widget** (via Elementor Custom Code or as a script block in the popup HTML widget), which listens for:

```js
$(document).on('elementor/popup/show', function(event, id, instance) { ... });
```

This fires every time any Elementor popup opens. A `setTimeout(..., 100)` gives Elementor time to finish rendering the popup DOM before querying its fields.

### Popup CSS class

The popup container is identified by the CSS class **`.sonderwuche_popup`** (applied to the Elementor popup Section/Container). All field selectors are scoped inside this container to avoid collisions with other popups.

### Form fields populated

| Field selector | Content | Populated when |
|---|---|---|
| `#form-field-product` | Product name + ` (SKU: sku)` | Always (from `infoplusProductData`) |
| `#form-field-befestigung` | select populated with `pa_befestigung` terms | Always (from `infoplusProductData.attributes`) |
| `#form-field-modell` | Selected model display name | When a model is selected (`infoplusProductState.selectedModel`) |
| `#form-field-variante` | Comma-separated attribute label/value pairs (e.g. `Groesse: 50x100 mm, Material: Aluminium`) | When attributes are selected; primarily for `requestable_with_variants` order type |

### Data sources

- `window.infoplusProductData`, injected by `ProductDetailSuite/Module.php` on `woocommerce_after_single_product`
- `window.infoplusProductState`, shared event bus from `product-state.js`; exposes `.selectedModel` (slug) and `.selectedAttributes` (object of `pa_*` key to term slug)

### Order type: requestable_with_variants

The 4th order type is the primary consumer of the variant fields. When a model has this type:

- Only the Anfrage button is shown (same as plain `requestable`)
- The variation dropdowns remain fully interactive so the user selects their desired configuration
- When the popup opens, `#form-field-modell` and `#form-field-variante` are pre-filled with the current selection
- The inquiry form arrives pre-populated with the specific variant the customer wants to request

### Order types summary

| Value | Admin label | Buttons shown | Variant data in popup |
|---|---|---|---|
| `editable` | Editable (Editor) | Editor + Anfrage | No |
| `requestable` | Requestable (Anfrage) | Anfrage only | No |
| `requestable_with_variants` | Anfrage mit Varianten | Anfrage only | Yes |
| `buyable` | Buyable (Warenkorb) | Cart only | No |

On the classic theme the "Cart" button of a `buyable` model is the submit button of a WooCommerce add-to-cart form, not the `#editor` button in cart mode. See Section 20.

### ⚠ Adding a new order type: required checklist

When adding a new order type value, **four separate whitelists** must all be updated or the new value will silently fall back to `'editable'` on reload (the save works, but the read discards the unknown value). Missing any one of these was the cause of the `requestable_with_variants` persistence bug.

| # | File | Location | What to update |
|---|---|---|---|
| 1 | `Fields/ProductAttributeImages.php` | `renderTermFields()`, local `$allowed_order_types` variable | Add new value to the array used to validate the saved DB value before pre-selecting the dropdown |
| 2 | `Fields/ProductAttributeImages.php` | `saveMetaBox()`, `$allowed` variable in the `META_KEY_ORDER_TYPE` branch | Add new value to the array used to sanitize the submitted POST value before saving |
| 3 | `Fields/ProductAttributeImages.php` | `getTermOrderType()`, `$allowed` variable | Add new value to the array used when reading the type for frontend data output |
| 4 | `Fields/ProductAttributeImages.php` | `renderTermFields()`, the `<select>` HTML | Add a new `<option value="...">` element visible to the admin |

Additionally update:

- `Assets/product-order-panel.js` → `applyOrderType()`, add button visibility logic for the new type
- `Assets/product-order-panel.js` → `applyVariationState()`, **required, see below**
- `PLUGIN-DOCUMENTATION.md` → update the order types summary table above

### ⚠ applyVariationState() must handle every order type explicitly

`applyVariationState()` in `product-order-panel.js` is called by multiple downstream event handlers (`variation:reset`, `variation:notfound`, `attribute:changed`). These events can fire synchronously after model selection, for example, WooCommerce fires `reset_data` inside `syncToWooCommerce()`, which chains into `variation:reset`, which calls `applyVariationState()`.

**If a new order type has no explicit branch in `applyVariationState()`, those downstream events can silently leave the buttons in a broken state**, even though `applyOrderType()` ran correctly and showed the right buttons a moment earlier.

The rule: **every order type must have an explicit case in both `applyOrderType()` AND `applyVariationState()`**.

| Order type | `applyOrderType()` | `applyVariationState()` |
|---|---|---|
| `editable` | Show Editor + Anfrage | Enable/disable Editor based on variation validity |
| `requestable` | Show Anfrage + pulse | Re-enforce Anfrage visible + pulsing (no variation needed) |
| `requestable_with_variants` | Show Anfrage + pulse | Re-enforce Anfrage visible + pulsing (no variation needed) |
| `buyable` | Show Cart | Enable/disable Cart based on variation validity |

For types where the button is always enabled (requestable family), `applyVariationState()` must **re-enforce** the visible + pulsing state rather than do nothing, this is the defensive pattern that prevents any subsequent event from leaving the button hidden.

---

## 10. Admin Meta Box: "Infoplus Suite"

### Overview

The product admin page has a single **"Infoplus Suite"** meta box (registered by `Fields/ProductAttributeImages.php`) that consolidates all product-level configuration previously spread across two places (a WooCommerce product data tab and a separate meta box).

### Three Tabs

| Tab | Purpose |
|-----|---------|
| **Attribute Overrides** | Per-product attribute images (override global term images); order type per `pa_model` term |
| **IBE/IFE** | UUID (read-only), Print API ID (read-only), Mindestanzahl (`_infoplus_min_quantity`), Lieferzeit (`_infoplus_delivery_days`) |
| **Default Variation** | Per-model cascading dropdowns, each `pa_model` term gets its own attribute selectors; see Section 11 |

### Tab Switching

Implemented with CSS class `.infoplus-suite-tab` on buttons and `.infoplus-suite-panel` on panels. JS in `admin-product-attribute-images.js` toggles `is-active` / `is-visible`. Data attribute `data-suite-panel` on each panel links to the tab button's `data-suite-tab`.

### Admin JS data (`infoplusAdminData`)

Passed via `wp_localize_script`:

```js
window.infoplusAdminData = {
  variations: [{ id, sku, attributes: { 'attribute_pa_*': value }, in_stock }],
  defaultVariationId: 123,                      // legacy single default (int|null)
  modelDefaultVariations: { slug: id, ... },    // per-model map (object|null)
}
```

### WooProductVariationFields integration

The IBE/IFE WooCommerce product data tab was removed from `WooProductVariationFields/Module.php`. Its `saveProductFields()` method remains as a backup save on `woocommerce_process_product_meta`. The IBE/IFE fields are now exclusively edited via the "Infoplus Suite" meta box IBE/IFE tab.

---

## 11. Per-Model Default Variations

### Problem

The old `_infoplus_default_variation_id` meta key stored a single default variation for the whole product. When a product has multiple `pa_model` values (e.g., "Alu", "Kunststoff", "Holz"), only one model's default could be configured and that default only applied at page load.

### Solution

A JSON map stored in `_infoplus_model_default_variations` (e.g. `{"alu": 1234, "kunststoff": 5678}`) allows a different default variation per model. Every time the user switches to a different model, `applyModelDefault()` automatically selects that model's configured default.

### Admin UI (Default Variation tab)

The tab renders one bordered card per `pa_model` term on the product. Each card contains:
- A status badge (`✓ Gesetzt`, `✗ Nicht gesetzt`, `⚠ Keine passende Variation gefunden`)
- One `<select>` per non-model variation-type attribute, labeled with the attribute name
- A hidden `<input name="_infoplus_model_default_variations[slug]">` that holds the resolved variation ID

**Cascading filtering**: The `filterModelDefaultSelects($section)` function in `admin-product-attribute-images.js` rebuilds each select's `<option>` list from a snapshot (`modelDefaultAllOptions`) captured at page load, keeping only values that appear in at least one variation for that model given the other current selections. This prevents "Keine passende Variation gefunden" states, every visible combination is guaranteed to exist.

**Initialization order**: `initModelDefaultAttrOptions()` (captures original options) runs before `initModelDefaultVariationDropdowns()` (pre-populates from saved data and triggers initial filtering).

### Frontend logic (`product-state.js`)

```
Page load / model switch
  → setModel(slug)
    → applyModelDefault(slug)           // batch-write all attrs in one pass
      → buildAvailableAttributes()      // once for the full selection
      → emit attribute:changed per attr // UI widgets update
      → findMatchingVariation()         // emits variation:found
    → syncToWooCommerce()               // single WC trigger (see Section 13)
```

`applyModelDefault()` writes directly to `this.selectedAttributes` (bypassing `setAttribute()`) to avoid per-attribute `syncToWooCommerce()` calls. This is the critical batch-write pattern, calling `setAttribute()` per attribute would trigger WC's `reset_data` event repeatedly between each write.

### Fallback

If `model_default_variations` is null or a given model has no entry, `applyModelDefault()` silently no-ops. The legacy `default_variation_id` path in `applyDefaultVariation()` is retained as a further fallback at initial page load only.

### Meta keys

| Key | Type | Description |
|-----|------|-------------|
| `_infoplus_default_variation_id` | int | Legacy: single product-level default |
| `_infoplus_model_default_variations` | JSON string | New: `{ "model-slug": variation_id }` map |

---

## 12. WDP Bulk Prices API Interception

### Problem

The `GET /infoplus/v1/products/prices?sku=X` endpoint is registered by the **InfoplusAPI** plugin (`reference/infoplus_api/entities/Products.php`, `get_bulk_price_rules_for_product()`), which reads the AlgolPlus WDP (Advanced Dynamic Pricing) rules table `wp_wdp_rules`. The 0.9.93 documentation attributed the route to the WDP plugin itself; the code shows it is InfoplusAPI. New WDP pricing rules store WooCommerce **product/variation IDs** in the filter's `value` array instead of SKU strings, so the InfoplusAPI handler returned `[]` for any SKU that used the newer rule format.

Adding a competing `rest_api_init` route causes a "Route already registered" conflict since both plugins share the `infoplus/v1` namespace with the same path.

Since 0.9.111 the intercepted result is cached in the transient `infoplus_prices_{md5(sku)}` for 24 hours. There is no invalidation hook, so a changed WDP rule can take up to a day to show on the product page unless the transient is deleted by hand.

After the InfoplusAPI merge the interception can become a normal route registration and the InfoplusAPI handler can be dropped.

### Solution: `rest_pre_dispatch` interception

`Module::interceptBulkPricesRoute()` is hooked to the `rest_pre_dispatch` filter at priority 10. WordPress calls this filter before dispatching to any registered route handler. Returning a non-null value short-circuits dispatch entirely, the WDP handler never runs.

```php
add_filter('rest_pre_dispatch', [$this, 'interceptBulkPricesRoute'], 10, 3);
```

The method only activates when `$request->get_route() === '/infoplus/v1/products/prices'` and method is `GET`. All other routes are passed through unchanged (`return $result`).

### `getBulkPricesBySku(string $sku): array`

Resolution strategy (falls through to next level if no match found):

1. `wc_get_product_id_by_sku($sku)` → `$variation_id` (int)
2. `wp_get_post_parent_id($variation_id)` → `$parent_id` (int)
3. Queries `wp_wdp_rules` WHERE `enabled = 1 AND deleted = 0` ORDER BY `priority` (rules deleted in the WDP admin stay in the table with `deleted = 1`)
4. For each rule: deserializes `filters` column, looks for a filter object whose `value` array contains **any of**: the SKU string, `(string)$variation_id`, or `(string)$parent_id`
5. For the first matching rule: deserializes `bulk_adjustments`, maps `ranges` array to `[{from, to, value}]`
6. Normalizes `to >= 99000` to `''` (open-ended / display as "500+")
7. `toDisplayPrices()` converts each `value` with `wc_get_price_to_display()` on the variation, so the response is what the shop displays (incl. MwSt. when prices are entered net and shown incl. tax). All three consumers (order panel, Bulk Price Table widget, Elementor bulk calculator) show the response as-is.

### Response format

```json
[
  { "from": "1",   "to": "24",  "value": "2.50" },
  { "from": "25",  "to": "99",  "value": "2.20" },
  { "from": "100", "to": "",    "value": "1.90" }
]
```

An empty `to` means the tier has no upper bound ("ab 100 Stück").

### `parseWdpRanges(string $raw): array`

Helper that `unserialize()`s the `bulk_adjustments` column and maps the nested `ranges` data to the flat `[from, to, value]` shape the frontend expects.

### Table availability guard

Before querying, `getBulkPricesBySku` does `SHOW TABLES LIKE '{$table}'`, returns `[]` silently if the WDP plugin is not installed.

---

## 12a. Catalog Import (WooCommerce → Katalog-Import)

Module `src/Modules/CatalogImport/`. Imports the client's SKU Excel (via `import-mapping/build_mapping.py` → `build_import.py` → `import.csv`; scripts and mapping tables live in `import-mapping/`, the Excel and the generated `import.csv` stay out of git; run `python import-mapping/build_mapping.py "<path to Excel>"`) into the variable products.

| File | Role |
|---|---|
| `Module.php` | Admin page, AJAX `infoplus_catalog_plan` / `_step` / `_reset`, plan storage in `uploads/infoplus-catalog-import/` (web access denied) |
| `CsvReader.php` | Parses and validates the `;` CSV (no WP calls, CLI-testable) |
| `Planner.php` | Dry run: compares CSV with the site, read-only |
| `Runner.php` | Applies the plan in phases, one small batch per AJAX request, resumable |
| `PriceRules.php` | Builds WDP `single_item` bulk rules (one per distinct tier set, SKU filter, `price__fixed`, priority 0, title prefix `[Katalog] `) |
| `TermIndex.php` | Finds attribute terms by normalised name (`×`→`x`, entities, case) before creating new ones |

**Rules of the import:** the CSV is the source of truth for the products it names (`parent_id`). Variations of those products that are not in the CSV are deleted, including variations without SKU. Other products are not touched. Existing bulk rules are replaced only if every SKU in them belongs to the catalog (or exists nowhere on the site); rules with conditions, role discounts or non-SKU filters (e.g. „Händlerrabatt“) stay.

**Phases:** `attributes` (creates missing `pa_*`, e.g. `pa_fenster`) → `terms` → `parents` (sets variation attributes in CSV order, keeps non-variation attributes) → `variations` (25 per request) → `deletes` (50) → `rules` (100) → `finalize`.

**Finalize:** sets `woocommerce_prices_include_tax = no` (CSV prices are net) with display incl. tax, repairs `_infoplus_model_default_variations` / `_infoplus_default_variation_id` that point at deleted variations, syncs variable prices, clears `infoplus_prices_*` transients, purges LiteSpeed.

**Options:** `infoplus_catalog_import_state` (progress, log, errors), `infoplus_catalog_import_summary` (dry-run numbers and result, readable via SQL for checks).

Minimum order per variation needs no extra meta: the order panel raises the minimum to the first tier's `from` (see `product-order-panel.js`).

---

## 13. Model Switching: Single-Trigger WooCommerce Sync

### Problem

WooCommerce's `variations_form` JS (`wc-add-to-cart-variation`) has an `onVariationChange` handler that fires on every `<select change>` event. When fewer attributes are selected than required (`count < count_all`), it calls `resetVariations()`, which fires the `reset_data` event, which our `product-state.js` translates into `variation:reset`.

The old `syncToWooCommerce()` called `.trigger('change')` on each select individually. This caused a chain of intermediate resets between each trigger, which clobbered the in-progress model switch and left subsequent model switches broken.

### Fix: batch-write then single trigger

`syncToWooCommerce()` now operates in two passes:

**Pass 1, silent writes:**
```js
Object.entries(this.selectedAttributes).forEach(([attr, value]) => {
    const $select = $form.find(`select[name="${attr.replace('pa_', 'attribute_pa_')}"]`);
    if ($select.length && value) {
        $select.val(value);    // set value without triggering change
        $lastSelect = $select;
    }
});
```

**Pass 2, single trigger on the last select:**
```js
if ($lastSelect) {
    $lastSelect.trigger('change');
}
```

WooCommerce now reads all selects in a single evaluation, sees `count === count_all`, and goes straight to finding the variation without any intermediate resets.

### Related: `setModel()` call order

Before this fix, `setModel()` called `syncToWooCommerce()` directly. Now the order is:

```
setModel(slug)
  1. Reset other selectedAttributes to null
  2. buildAvailableAttributes()
  3. emit model:changed
  4. findMatchingVariation()   ← likely notfound (other attrs are null)
  5. applyModelDefault(slug)   ← batch-writes all attrs, emits variation:found
  6. syncToWooCommerce()       ← single WC sync with complete state
```

`applyModelDefault()` does NOT call `syncToWooCommerce()` itself, the call in step 6 fires once after all defaults are applied.

---

## 14. Two-Way Qty / Price Table Sync

### Overview

Two widgets interact through the shared `infoplusProductState.currentQty` and `qty:changed` event:

- **ProductOrderPanel** owns the quantity input (`+`/`−` buttons, text input). It emits `qty:changed` with `source: 'panel'`.
- **BulkPriceTable** (standalone widget) and the panel's **inline bulk grid** (`.infoplus-bulk-row` rows inside the panel) both react to qty changes by highlighting the active tier. Clicking a tier cell/row updates the qty.

### Direction 1: Clicking a tier → updates qty input + price

**BulkPriceTable widget (`.ibpt-cell` click):**
```
click .ibpt-cell
  → onCellClick($cell)
  → infoplusProductState.setQty(from)   // source='external'
  → qty:changed { qty: from, source: 'external' }
  → ProductOrderPanel listener: source !== 'panel' → setQty(from)
      → $input.val(from), updatePrices($panel), highlightActiveTier($panel)
      → emits qty:changed { source: 'panel' }
  → BulkPriceTable listener: currentQty = from, updateAllActiveCells()
```

**Panel's inline bulk row (`.infoplus-bulk-row` click):**
```
click .infoplus-bulk-row[data-from=N]
  → infoplusProductState.setQty(N)    // same flow as above
```

Each `.infoplus-bulk-row` now carries a `data-from` attribute (set in `buildBulkGrid`) and has `cursor: pointer` styling. Rows respond to `Enter` key for keyboard accessibility.

**Critical guard removed:** The old `qty:changed` listener in `initQuantitySelector` had:
```js
if (data.source !== 'panel' && data.qty !== state.currentQty) { setQty(data.qty); }
```
The `data.qty !== state.currentQty` equality check blocked updates when the clicked tier's minimum happened to equal the current qty (e.g., clicking the lowest "ab 1 Stück" row when qty was already 1). This guard was removed, the `source !== 'panel'` check alone is sufficient to prevent echo loops.

### Direction 2: Changing qty input → updates active tier highlight

```
user types / clicks +/− in ProductOrderPanel
  → setQty(val)  (or direct emit from $input.on('input'))
  → qty:changed { qty: val, source: 'panel' }
  → BulkPriceTable listener (no source filter):
      currentQty = val; updateAllActiveCells()
  → panel's own highlightActiveTier($panel) already ran inside setQty()
```

BulkPriceTable's listener intentionally has no source filter, it updates on every `qty:changed` regardless of origin.

### Script load order dependency

Both widgets initialize on `$(document).ready()`. WordPress enqueues `product-order-panel.js` before `bulk-price-table.js` (registration order in `enqueueFrontendAssets`). The panel's `qty:changed` listener is therefore registered first. When `setQty` in the panel emits with `source: 'panel'`, the panel's own listener skips it (source check), and BulkPriceTable's listener (registered second) processes it correctly.

### Third participant since 0.9.111: the legacy bulk calculator

`WooSingleProductAddons/Assets/elementor-bulk-calculator.js` (the old `#qty_value` / `#bulk_table` Elementor layout) now takes part in the same bus. Its `apply(qty)` writes `infoplusProductState.currentQty` and emits `qty:changed` with `source: 'calculator'`; it listens to `qty:changed` from any other source and applies the qty without re-emitting. It also emits `minqty:changed` after loading tiers. It no longer renders its own price grid.

### Effective minimum quantity since 0.9.111

`product-order-panel.js` keeps two values: `productMinQty` (from `_infoplus_min_quantity`, via the widget's data attribute) and `minQty` (effective). When bulk tiers arrive and the first tier's `from` is higher than the product minimum, `applyBulkMinQty()` raises `minQty`, clamps the current qty up, disables the minus button and emits `qty:changed` (source `panel`) plus `minqty:changed`. When no tiers exist, `resetMinQty()` restores the product value. The ProductInfoBox widget shows the effective value.

### Status

The code state as of 0.9.111 is confirmed by reading `product-order-panel.js`: `.infoplus-bulk-row` carries `data-from` (line 112) and the `qty:changed` listener checks only `data.source !== 'panel'` (line 307, with a comment explaining why the equality guard is gone). Browser verification is still unverified from the repository. See Section 16.

---

## 15. Cart Page Styling (WooCommerce Blocks)

### Overview

The WooCommerce Blocks cart (`/cart/`) renders with default WordPress button styles (`#32373c` dark background). `UserOrders/Module.php` adds an `enqueueCartStyles()` method hooked to `wp_head` that fires only when `is_cart()` returns true and outputs an inline `<style>` block targeting `.wp-block-woocommerce-cart` and its children.

### What is styled

| Element | Change |
|---------|--------|
| `.wp-block-woocommerce-cart` buttons | Pink (`#e91e8c`), rounded corners, white text |
| Coupon link, panel toggle, anchors | Pink color |
| Product name links | Pink, no underline |
| Footer total value | Pink, bold |
| Remove item link | Pink |
| Section headings | Dark, bold, 0.5px letter spacing |

### Location

`src/Modules/UserOrders/Module.php`, `enqueueCartStyles()` method.

### Since 0.9.111: `woo-pages.css` and `cart-qty-fix.js`

Most cart and checkout styling now lives in the external stylesheet `UserOrders/Assets/woo-pages.css` (see 4.7), enqueued for guests and logged-in users on cart, checkout and the order-received endpoint. The inline `enqueueCartStyles()` and `enqueueCheckoutStyles()` blocks still exist alongside it. `cart-qty-fix.js` adds a "× qty" badge to product names where the native quantity selector is hidden.

### Translations note

WooCommerce Blocks loads its UI strings ("Estimated total", "Add coupons", "Proceed to Checkout") from JavaScript `.json` translation files installed with WooCommerce's German language pack. These are **not overridden by the plugin**. To fix untranslated strings:

1. WP Admin → Updates → Update WooCommerce (de_DE) translation
2. If strings still appear in English: install Loco Translate or "Say What?" plugin and map the strings manually (text domain: `woo-gutenberg-products-block`)

### Known issue (as reported in 0.9.93)

The WooCommerce Blocks **checkout** page (`/checkout/`) showed an infinite-loading skeleton even with items in the cart. See Section 16 for the current status.

---

## 16. Known Issues & Incomplete Features

Every entry was re-checked against the 0.9.111 code on 2026-09-17. Status values: **still open**, **fixed**, **unverifiable**.

### WooCommerce Blocks Checkout: Infinite Loading Skeleton

**Status: unverifiable from the code.** Nothing in the 0.9.93 to 0.9.111 diff addresses a checkout hydration problem, and no commit message mentions it. The same period added a lot of checkout work that only makes sense against a rendering checkout (`woo-pages.css` checkout sections, `filterWooCheckoutBlock()`, `cart-qty-fix.js` on the order summary), so the page evidently renders on production now. Whether the original cause was a plugin conflict, a session issue or something else is not recorded anywhere in the repo. Original notes kept below.

**Original status (0.9.93): NOT FIXED**

**Symptom:** `/checkout/` page with items in cart shows a skeleton/loading state indefinitely. The checkout block content never renders. No JavaScript errors in console, no errors in `debug.log`, no failed network requests.

**What was tried:** Removing a `padding-left: 80px` heading style from the checkout page (applied via `enqueueCartStyles`). The heading style was removed; the infinite loading persists.

**Suspected area:** WooCommerce Blocks hydration/initialization issue, possibly a conflict with another plugin or a missing nonce/session for the Store API. Needs dedicated debugging session with browser network tab open while checking for `wc/store/v1/cart` requests and their response status.

**Files to investigate:**
- `src/Modules/UserOrders/Module.php`, `enqueueCartStyles()` (confirm no unintended CSS that hides the checkout content)
- Browser console → Application → Cookies (check `woocommerce_session_*` exists)
- Network tab → filter by `/wc/store/v1/` (check for 4xx/5xx responses)

---

### Two-Way Qty / Price Table Sync

**Status: code confirmed in 0.9.111, browser verification unverifiable from the repo.** All three changes listed below are present in the current `product-order-panel.js` and `bulk-price-table.css`. The code was further extended after 0.9.93 (effective min qty, calculator participation, see Section 14), which means it has been worked on in the browser since, but no test record exists in the repo.

**Original status (0.9.93): IMPLEMENTED, NOT YET BROWSER-TESTED**

The fix was applied in v1.0.58 (see Section 14). The changes are:
1. `.infoplus-bulk-row` elements now have `data-from` attribute and a delegated click handler that calls `infoplusProductState.setQty(from)`
2. The `data.qty !== state.currentQty` guard removed from the panel's `qty:changed` listener
3. `cursor: pointer` + hover styles added to `.infoplus-bulk-row`

**To verify:**
1. Open a product page with a variation that has WDP pricing rules
2. Wait for the bulk price grid to load in the panel
3. Click a tier row, qty input should update and price should recalculate
4. Type a qty in the input, the active row in the grid should highlight the correct tier
5. If BulkPriceTable widget is also on the page: clicking its cells should also update the panel qty

---

### Extended Profile Settings Form

**Status: fixed (implemented).** `UserDashboard/Module.php` registers `[infoplus_profile_settings]` (`renderProfileSettings()`), `GET /user/profile` returns the extended fields, `POST /user/profile` writes the `billing_*` meta and `infoplus_vat_id`, and the country select uses `WC()->countries->get_countries()`. This matches the plan below point by point. Whether the Elementor page was switched to the shortcode widget is a site configuration question, unverifiable from the repo. Original plan kept for reference.

**Original status (0.9.93): PLANNED, NOT STARTED**

A plan exists to replace the Elementor Form widget on the "Einstellungen" account tab with a shortcode `[infoplus_profile_settings]` that renders a complete profile editing form including:
- First name, last name, email, phone
- Company name, VAT ID (`infoplus_vat_id`)
- Full billing address (street, postcode, city, country) stored as WooCommerce `billing_*` meta keys
- Password change section (reusing existing `#infoplus_password_form` ID)
- Avatar upload (reusing existing `#infoplus-avatar-btn` ID)

**Files to modify:** `src/Modules/UserDashboard/Module.php`, `src/Modules/UserDashboard/Assets/user-dashboard.js`

The full implementation plan is stored in the Claude plan file. Key decisions:
- Use `billing_*` meta keys (WC standard) so fields auto-populate WooCommerce checkout
- Country field uses `WC()->countries->get_countries()` for the `<select>` options
- JS populates all fields from the existing `GET /user/profile` endpoint (which needs to be extended to return the new fields)
- The user must swap the Elementor Form widget for an Elementor Shortcode widget containing `[infoplus_profile_settings]`

---

### New findings from the 0.9.111 documentation pass (2026-09-17)

Observed while reading the code. None of these has been fixed; they are listed so they are not lost.

| # | Where | Finding | Status |
|---|-------|---------|--------|
| 1 | `WooSingleProductAddons/Module.php` `ajaxAddToCart()` | No nonce or referer check on the `infoplus_add_to_cart` admin-ajax action (priv and nopriv). Any site can trigger adds to a visitor's cart. | still open |
| 2 | `ProductDetailSuite/Module.php` `interceptBulkPricesRoute()` | Transient `infoplus_prices_{md5(sku)}` (24 h) has no invalidation. WDP rule edits are invisible for up to a day. | still open |
| 3 | `WooSingleProductAddons/Module.php` `outputProductContextJson()` | Variation attribute keys are hardcoded (`pa_model`, `pa_format`, `pa_befestigung`, `pa_oberflache`). Products using other attributes get empty values in `window.allWooVariations`. | still open |
| 4 | `ProductDetailSuite/Assets/product-state.js` `loadDeferredVariations()` | Falls back to the literal `/wp-json/infoplus/v1/` when `infoplusProductConfig` is missing. Breaks on sites without pretty permalinks or with a custom REST prefix. | still open |
| 5 | `ProductDetailSuite/Module.php` `buildVariationsData()` | Reads `_infoplus_model_image_id`, `_infoplus_model_description`, `_infoplus_model_tooltip` which nothing writes any more (`Fields/VariationFields.php` is deprecated and empty). Dead reads or stale data. | still open |
| 6 | `UserDashboard/Assets/` | Nine duplicated ProductDetailSuite files, never enqueued. They partially drifted in 0.9.111. Roadmap R-02. | still open |
| 7 | `WooProductVariationFields/Assets/admin-fields.js` | Not enqueued by any PHP. Dead file. | still open |
| 8 | `reference/infoplus_api/InforplusAPI.php` `validate_api_key()` | Defined but not used by any route. `INFOPLUS_API_KEY` currently protects nothing; every InfoplusAPI route is public. Contradicts `reference/README.md`, which says the key "validates incoming requests to /infoplus/v1/*". | still open |
| 9 | `reference/infoplus_api/entities/Cart.php` `update_cart()` | Redirect base URL is hardcoded to `https://navajowhite-rhinoceros-679020.hostingersite.com/cart`, which is a Hostinger staging host, not the production domain. Whether production has a different value is unverified (the reference copy is stated to be the live copy). | still open |
| 10 | `reference/infoplus_api/entities/Products.php` `getProducts()` | Uses `$woocomerce_product->id` (undefined variable) when calling `get_variants_for_product_internal_by_id()`, and reads `min_quantity` meta that the Suite never writes. | still open |
| 11 | `reference/infoplus_api/InforplusAPI.php` `try_order_action_again()` | Uses undefined `$tries` and `$testing_order`; retry scheduling calls `as_schedule_single_action` without checking Action Scheduler exists. | still open |
| 12 | `reference/infoplus_api/entities/Products.php` | Email recipient for sync reports is hardcoded (`vr@alture.io`); order retry alerts in `InforplusAPI.php` go to the same address. | still open |
| 13 | InfoplusAPI custom tables | `infoplus_products` and `infoplus_synchronizations` have no `wp_` prefix and no creating code in either plugin. | unverifiable how they were created |
| 14 | `Plugin.php` `registerHooks()` | Loads the text domain from `languages/`, which does not exist. Harmless, but R-09 will need the directory. | still open |
| 15 | Section 12 of the 0.9.93 docs | Attributed `/products/prices` to the WDP plugin. It is InfoplusAPI. Corrected in this version. | fixed (docs) |
| 16 | Section 7 of the 0.9.93 docs | Order type meta key was documented as `_infoplus_order_type_{term}`. Real key is `_infoplus_attr_order_type_{attr}_{term}`. Corrected in this version. | fixed (docs) |
| 17 | `Custom404/Module.php` and `UserOrders/Module.php` | Both hardcode `calc(100vh - 684px)` from measured header (83 px) and footer (601 px) heights. A theme change breaks the layout silently. | still open |
| 18 | `ProductDetailSuite/Widgets/VariationDropdowns.php` `renderDropdowns()` (up to `feature/catalog-import`) | Read `$info_behavior`, a local variable of `render()`, so the option info buttons never got `data-tooltip` and PHP logged "Undefined variable" once per option. Since `feature/view-renderer` the template keeps the old output on purpose (`$option_info_tooltip_attr = false`) without the warning. `variation-dropdowns.js` falls back to the option's own `data-tooltip`. See Section 19. | kept as is, warning gone |
| 19 | `WooSingleProductAddons/Module.php` `ajaxAddToCart()` | Calls `WC()->cart->add_to_cart()` directly, so `woocommerce_add_to_cart_validation` never runs (Min and Max Quantity for WooCommerce, Germanized and other validators are skipped), errors only show as button text, and `model_image_url` is stored as sent by the browser (escaped on output, but not checked). Found 2026-10-20. The theme buy form (Section 20) avoids all three; the Elementor template still uses the handler. | still open (Elementor path) |
| 20 | Minimum order quantity | `elementor-bulk-calculator.js` clamps the quantity to the larger of `currentProduct.mindestanzahl` (post meta `mindestanzahl`) and the first Staffelpreise tier. Nothing checks this on the server, in either add-to-cart path. The info box shows `_infoplus_min_quantity`, a different key. Found 2026-10-20. | still open |

---

## 17. Large Products: Deferred Variations and Caching

Added in 0.9.111 (`ProductDetailSuite\Module::VERSION` 1.1.0) to stop product pages with 1000+ variations from timing out.

### Threshold

`Module::VARIATION_AJAX_THRESHOLD = 200`. Counted with `count($product->get_children())` in `outputProductState()`. The same value is returned from the `woocommerce_ajax_variation_threshold` filter so WooCommerce's own `variations_form` also switches to AJAX for these products.

### Page load

```
outputProductState()
  transient infoplus_pd_{id}_{VERSION} hit?  → echo cached JSON
  miss:
    children > 200 → buildProductDataLite()   variations: [], models via buildModelsDataLite(), _variationsDeferred: true
    else           → buildProductData()       full variations (get_available_variations() called once, reused by models)
    cache JSON for 12 h
```

`buildProductDataLite()` still includes `attributes` (from `buildAttributesData()`) so dropdowns render immediately. `buildModelsDataLite()` gets min price and count per model from one SQL query over child `_price` / `attribute_pa_model` meta and takes names, tooltips and gallery from the `pa_model` terms.

### After load

```
product-state.js init()
  productData._variationsDeferred?
    yes → emit variations:loading
          GET {restUrl}product/{id}/variations
          → productData.variations = response.variations
          → buildAvailableAttributes()
          → emit variations:loaded { count, availableAttributes }
          → if a model was already selected, reset selectedModel and re-run
          → applyDefaultVariation()
    no  → applyDefaultVariation()
```

`variation-dropdowns.js` adds `ip-variations-loading` (shimmer, pointer-events none) to the widget between the two events. `product-info-box.js` re-applies the SKU after `variations:loaded`.

### REST endpoint

`GET /infoplus/v1/product/{id}/variations` → `getProductVariations()` → `buildVariationsDataLite()`. Three `$wpdb` queries over the child IDs: core meta (`_sku`, `_price`, `_stock_status`, `_thumbnail_id`), all `attribute_%` meta, and published status. Returns `{ success, variations: [...] }`. Unpublished children are skipped. `is_purchasable` is always `true` in the lite shape.

### Other direct-query paths added in 0.9.111

| Where | Replaced | With |
|-------|----------|------|
| `ModelSelector::renderModelCards()` | loop over `get_available_variations()` | one `GROUP BY attribute_pa_model` query with `MIN(_price)` |
| `ProductImage` initial gallery | loop over variations for the first model | `wc_get_product_terms($id, 'pa_model')` |
| `WooSingleProductAddons::outputProductContextJson()` | `get_available_variations()` then `wc_get_product()` per child | one `postmeta` query for four attribute keys |

### Invalidation

`invalidateProductCache()` on `woocommerce_after_product_object_save` deletes `infoplus_pd_{id}_{VERSION}` for the saved product and for its parent when the saved object is a variation. Bumping `Module::VERSION` orphans old transients (they expire after 12 h).

---

## 18. Changes from 0.9.93 to 0.9.111

Summary of `git diff 40ce2e5 f01aca6` (38 files, 3234 insertions, 432 deletions). Work from April to September 2026, synced from production in one commit, so there is no per-feature history.

| Area | Change |
|------|--------|
| Repo | `.gitattributes` (LF normalisation), `.gitignore` |
| Versions | header 0.9.93 → 0.9.111, `Plugin::VERSION` → 0.10.25, ProductDetailSuite 1.0.58 → 1.1.0 |
| ProductDetailSuite | New ProductInfoBox widget and JS. Deferred variation loading, product data transient, lite builders, `/product/{id}/variations` route, prices transient (Section 17). Admin lock buttons for the IBE/IFE fields (admins only). `data-image` on dropdown options, lazy-load opt-out on the selected image. Bulk price table overlay states. Effective min qty in the order panel. `pulseActionButton()` in dropdowns. `attribute:changed` now selects the option visually. `elementorFrontend.hooks` guards in all widget scripts. |
| WooSingleProductAddons | Direct-query product context JSON. Bulk calculator joins the state bus and stops rendering its own grid. Editor link script handles `requestable_with_variants` and pulses buttons. |
| WooProductVariationFields | Locked IFE fields saved only for `manage_options`. |
| UserDashboard | UserStatCard widget and CSS. Logout without confirmation page. Registration success redirect script. Copies of ProductDetailSuite assets partly updated. |
| UserOrders | `woo-pages.css` (cart, checkout, thank-you) for all users, `cart-qty-fix.js`, checkout login link rewrite, empty-cart detection with MutationObserver fallback, thank-you page restyle, OrderDetails print stylesheet and 2:1 item images, UserOrdersGrid explicit status list, email banner icon markup. |
| Custom404 | No change in this diff (existed since `6b0d87f`, documented for the first time in 4.9). |

---

## 19. Theme Renderer

Added on branch `feature/view-renderer` (based on `feature/catalog-import`). Not deployed.

### Why

The product page components of ProductDetailSuite used to print their markup inside the Elementor widgets' `render()`. A classic PHP theme could not show them without Elementor. The split is now: the Suite owns the shop logic and the markup of its components, the theme owns layout and design. The Elementor widgets still exist and print the same markup, so the current Elementor product template keeps working until the theme switch.

### View::render

`Infoplus\Suite\View` (`src/View.php`) is the one entry point.

| Method | What it does |
|--------|--------------|
| `View::render(string $component, array $args = []): void` | Finds the template, sets `$args['context']` to `theme` when the caller did not pass one, runs the filter `infoplus_suite_view_args`, enqueues the component's assets and includes the template. |
| `View::capture(string $component, array $args = []): string` | Same as `render()`, returns the markup instead of printing it. |
| `View::locate(string $component): string` | Theme first: `locate_template('infoplus-suite/{component}.php')`, so a child theme beats its parent. Then the plugin file `templates/{component}.php`. Filter `infoplus_suite_locate_template`. Returns an empty string when nothing is found. |
| `View::registerAssets(string $component, array $scripts, array $styles): void` | Declares which registered handles a component needs. Called by `ProductDetailSuite\Module::register()` from `Components::ASSETS`. |
| `View::enqueue(string $component): void` | Enqueues those handles if they are registered. A theme may call it early, for example in `wp_enqueue_scripts`, to get the styles into the head. |

Rules for templates, the same idea as `wc_get_template()`:

- The template gets one variable, `$args`. There is no `extract()`. It is included inside a static closure, so `$this` is not available.
- The template must escape everything it prints. Prices come from `wc_price()`, which returns escaped markup. Icon markup goes through `Infoplus\Suite\Icons::kses()`.
- Component names must match `[a-z0-9][a-z0-9_-]*`. Anything else is rejected with `_doing_it_wrong()`.
- `$args['context']` is `elementor` when a widget renders and `theme` otherwise. The default templates do not branch on it. It is there so an override can, if ever needed.

A theme overrides a component by copying the plugin template to `{theme}/infoplus-suite/{component}.php`. The override also applies when the Elementor widget renders, because both paths go through `View::render()`.

### Components and templates

| Component | Template | Data builder | Widget | Root element |
|-----------|----------|--------------|--------|--------------|
| `model-selector` | `templates/model-selector.php` | `Components::modelSelector()` | ModelSelector | `.infoplus-model-selector.ip-c-model-selector` |
| `variation-dropdowns` | `templates/variation-dropdowns.php` | `Components::variationDropdowns()` | VariationDropdowns | `.infoplus-variation-dropdowns.ip-c-variation-dropdowns` |
| `product-image` | `templates/product-image.php` | `Components::productImage()` | ProductImage | `.infoplus-product-image.ip-c-product-image` |
| `product-info-box` | `templates/product-info-box.php` | `Components::productInfoBox()` | ProductInfoBox | `.ip-info-box.ip-c-product-info-box` |
| `bulk-price-table` | `templates/bulk-price-table.php` | `Components::bulkPriceTable()` | BulkPriceTable | `.infoplus-bulk-price-table.ip-c-bulk-price-table` |

`ProductDetailSuite\Components` (`src/Modules/ProductDetailSuite/Components.php`) holds the defaults (the widget content control defaults, in `*Defaults()` methods) and the shop logic that used to sit in the widgets: the model card query, term images and tooltips, the first model gallery, SKU and meta lookups, and the clamps of the bulk table. Each builder merges the passed args over the defaults, turns switch values into booleans (`'yes'`, `true`, `1` and `'1'` count as on) and adds the data keys the template needs. The full list of args is in the comment at the top of each template.

ProductOrderPanel is still registered but unused on the product template. It was not ported and renders as before.

Each widget's `render()` now does three things: keep the Elementor editor fallback (a sample product when there is no global `$product`, unchanged, including that three widgets assign it to the global as before), map `get_settings_for_display()` to the same arg keys, and call `View::render()` with `context` set to `elementor`. BulkPriceTable passes the editor state as `is_preview`, which makes the template print the static demo tiers. Style controls with Elementor `selectors` stay in the widgets and keep working through Elementor's generated CSS.

### Helper functions for themes

Defined in `src/template-functions.php`, loaded by `Plugin::boot()` right after the constants. Each one is wrapped in `function_exists()`, so a theme can replace it. `$product` accepts a `WC_Product`, a product ID, or `null` for the global `$product`. Missing args fall back to the defaults.

```php
infoplus_suite_model_selector($product = null, array $args = []): void
infoplus_suite_variation_dropdowns($product = null, array $args = []): void
infoplus_suite_product_image($product = null, array $args = []): void
infoplus_suite_product_info_box($product = null, array $args = []): void
infoplus_suite_bulk_price_table($product = null, array $args = []): void
```

Example in a theme's `woocommerce/single-product.php`:

```php
infoplus_suite_product_image();
infoplus_suite_model_selector(null, ['title' => '', 'show_price' => false]);
infoplus_suite_variation_dropdowns(null, ['attributes_to_show' => [], 'info_button_behavior' => 'modal']);
infoplus_suite_product_info_box();
infoplus_suite_bulk_price_table();
```

The bulk price table does not use `$product` on the server. Its tiers are loaded in the browser by `bulk-price-table.js` for the selected variation SKU. The parameter exists for a uniform signature and is passed to the template.

### Assets

`ProductDetailSuite\Module::enqueueFrontendAssets()` now registers every frontend handle on each frontend request (same handles, same files, same `Module::VERSION`), including the `infoplusProductConfig` localisation. On single product pages (`is_product()`) it still enqueues the same handles in the same order as before. `View::render()` enqueues a component's handles when it renders, which matters only outside product pages. Handles per component are in `Components::ASSETS` and match the widgets' `get_script_depends()` and `get_style_depends()`.

All component scripts depend on `product-state.js`, which reads `window.infoplusProductData`. That object is printed by `Module::outputProductState()` on `woocommerce_after_single_product`. A theme that renders the components outside the normal WooCommerce single product template must make sure that hook still fires, otherwise the components render but stay inert.

### Restyling from the theme

Every component root has the class `ip-c-{component}` and the attribute `data-ip-component="{component}"`, next to its existing classes. Key inner parts have `ip-c-{component}__{part}` classes, listed in the comment at the top of each template:

| Component | Parts |
|-----------|-------|
| model-selector | `__title`, `__options`, `__option`, `__image`, `__info`, `__label`, `__price`, `__modal` |
| variation-dropdowns | `__row`, `__label`, `__trigger`, `__options`, `__option`, `__image` |
| product-image | `__main`, `__image`, `__fullscreen`, `__gallery`, `__thumb`, `__lightbox` |
| product-info-box | `__heading`, `__rows`, `__row`, `__icon`, `__label`, `__value` |
| bulk-price-table | `__skeleton`, `__table`, and in the editor preview `__cell`, `__label`, `__qty`, `__price` |

Elements built later by JavaScript carry only the old classes, not the `ip-c-*` ones: the price cells of `bulk-price-table.js`, the thumbnails rebuilt by `product-image.js`, the info modal and tooltip created by `model-selector.js` and `variation-dropdowns.js`, and the lock overlays. Target those by their `infoplus-*` or `ibpt-*` classes.

Each template header also lists CSS custom properties (prefixes `--ip-ms-`, `--ip-vd-`, `--ip-pi-`, `--ip-ib-`, `--ip-bt-`) with the widget control defaults. They are a naming contract for the theme. The plugin CSS does not read them; the theme stylesheet maps them onto the `ip-c-*` classes. No design CSS was added to the plugin. Two properties the plugin CSS already reads are `--infoplus-dropdown-image-height` and `--infoplus-dropdown-image-width`.

Some rules in `product-detail-suite.css` only match inside Elementor: lines 134, 135, 179 to 182 (model selector info button) and 414, 415, 457 to 460 (dropdown option info button) use `.elementor-widget-infoplus-*` or `.elementor-element` as ancestor. Without Elementor those info buttons fall back to the lower specificity base rules, so the theme should style `.ip-c-model-selector__info` and the option info buttons itself.

### Icons

`Infoplus\Suite\Icons` (`src/Icons.php`) replaces Elementor's `Icons_Manager` in the theme path. `Icons::svg('box')` and `Icons::svg('clock')` return inline SVG of the Font Awesome Free 5 solid icons that the ProductInfoBox widget uses by default, so the pictograms are the same. No icon had to change. The filter `infoplus_suite_icons` adds more shapes. `Icons::kses()` runs `wp_kses()` with an allow list for `<i>` font icons and plain inline SVG.

In the Elementor path the ProductInfoBox widget still renders the chosen icon with `Icons_Manager::render_icon()` (only for visible rows, as before) and passes the markup as `icon_min_order_html` and `icon_delivery_html`. The template prints that markup through `Icons::kses()`. An uploaded custom SVG icon that uses elements outside the allow list (for example gradients or `<style>`) would lose those parts. The staging template uses the default icons (checked on 2026-10-20).

### How the output was verified

A PHP CLI harness outside the repo stubs the WordPress, WooCommerce and Elementor functions the widgets call. It uses the real `wp-includes/kses.php` from WordPress 6.9. For 34 scenarios (staging settings read from template 2190, control defaults, toggles off, editor mode without product, simple product, missing settings, hostile strings) it renders each widget once with the old `render()` from `feature/catalog-import` and once with the new widget. After removing the added `ip-c-*` classes and `data-ip-component` attributes, the output is byte for byte identical for all five components, including the three markup shapes `Icons_Manager` can produce (font `<i>`, inline `<svg>`, `<svg><use>`). The template files keep the old indentation for that reason. The old VariationDropdowns run logged "Undefined variable $info_behavior" warnings (Section 16, finding 18); the new run logs none.

### JavaScript that depends on Elementor DOM

Status: the rows for `variation-dropdowns.js:718` and `721` and `product-state.js:587` and `597`, and the bulk calculator paragraph below, were done on 2026-10-20 (see "Changes for classic themes" below). The attribute values differ from the proposals in this table: `inquiry` instead of `anfrage`, and the event is `infoplus:inquiry-open`. The original text follows.

Not changed in this branch. The default templates work without Elementor because every component script also boots on document ready from its `infoplus-*` root class. The dependencies below either do nothing outside Elementor or point at elements that only the Elementor template has. Proposed replacements use data attributes so the theme can mark its own elements.

| File and line | Dependency | Effect without Elementor | Proposed replacement |
|---------------|------------|--------------------------|----------------------|
| `model-selector.js:262` to `264` | `elementor/frontend/init` and `elementorFrontend.hooks` `frontend/element_ready/infoplus-model-selector.default` | Editor re-init only. No effect on the front end, the document ready boot at line 257 covers it. | Keep as optional. Boot from `[data-ip-component="model-selector"]`. |
| `variation-dropdowns.js:718` | `$('#anfrage .elementor-button, .infoplus-btn--anfrage')`: the Anfrage button is an Elementor Button widget with CSS ID `anfrage` | The pulse on requestable models finds no button unless the theme uses `.infoplus-btn--anfrage`. | `[data-ip-action="anfrage"]` on the theme's Anfrage button. |
| `variation-dropdowns.js:721` | `$('#editor .elementor-button, .infoplus-btn--editor, .infoplus-btn--cart')`: Elementor Button widget with CSS ID `editor` | The pulse on editable and buyable models finds no button unless the theme uses the fallback classes. | `[data-ip-action="editor"]` and `[data-ip-action="cart"]`. |
| `variation-dropdowns.js:778` to `780` | `elementorFrontend.hooks` `infoplus-variation-dropdowns.default` | Editor re-init only. | Keep as optional. Boot from `[data-ip-component="variation-dropdowns"]`. |
| `product-image.js:402` to `404` | `elementorFrontend.hooks` `infoplus-product-image.default` | Editor re-init only. The lightbox lookup at line 197 needs the lightbox to stay the next sibling of the root, which the template keeps. | Keep as optional. Boot from `[data-ip-component="product-image"]`. Long term, `data-ip-lightbox` on the root pointing at the lightbox. |
| `product-info-box.js:63` to `65` | `elementorFrontend.hooks` `infoplus-product-info-box.default` | Editor re-init only. | Keep as optional. Boot from `[data-ip-component="product-info-box"]`. |
| `bulk-price-table.js:78` to `80` | `elementorFrontend.isEditMode()` decides between the PHP demo grid and the live table | Returns false without Elementor, which is correct. | `data-ip-preview="yes"` printed by the template when `is_preview` is true. |
| `product-state.js:587` | `#form-field-product`: the product field of the Elementor Pro Anfrage form | No readonly product field unless the theme form uses that ID. | `[data-ip-field="product"]` on the theme form field. |
| `product-state.js:597` | `elementor/popup/show` event to re-run the readonly fix | Never fires. The MutationObserver fallback at line 601 still runs. | A Suite event such as `infoplus:anfrage-open` fired by the theme's modal. |
| `product-order-panel.js:36` | `elementorFrontend.isEditMode()` | Returns false, correct. Widget is unused. | `data-ip-preview`. |
| `product-order-panel.js:493` | `querySelector('#anfrage')` as the scroll target of the Anfrage button | No scroll unless the theme has `#anfrage`. Widget is unused. | `[data-ip-target="anfrage"]`. |
| `product-order-panel.js:686` to `697` | Boots through `elementorFrontend.hooks` when `elementorFrontend` exists, and directly only when it does not | On a non Elementor page with Elementor still active the panel may never boot (unverified). Widget is unused. | Always boot on document ready from `[data-ip-component]`, keep the hook for the editor. |

Related, outside `ProductDetailSuite/Assets/`: the qty row of the legacy bulk calculator, `WooSingleProductAddons/Assets/elementor-bulk-calculator.js`, finds its elements by Elementor widget CSS IDs (`#qty_value`, `#qty_minus`, `#qty_plus`, `#Stuckpreis_value h2`, `#Gesamtpreis_value h2`, `#Mindestanzahl_value`, `#qty-limit-message`) and hides the row with `$('#qty_value').closest('.e-con')` (lines 40 and 46) and `.closest('.elementor-widget-container, ...')` (line 151). A theme quantity row needs either the same IDs or a port to data attributes such as `data-ip-qty="value"`, `data-ip-qty="minus"`, `data-ip-qty="plus"` and `data-ip-qty-row` on the row container.

### Risks before deploying this branch on staging

- Frontend handles are now registered on every frontend page, and `infoplusProductConfig` (including a `wp_rest` nonce) is built on every request. It is printed only where the script is enqueued.
- Pages other than product pages that contain one of the five widgets now load the component scripts. This includes the Elementor editor preview of the product template, where `is_product()` is false and the scripts did not load before. The scripts' editor hooks were written for that case, but this has not been tested in the editor (unverified).
- A theme override in `{theme}/infoplus-suite/` also replaces the markup of the Elementor widgets.
- No JavaScript or CSS file changed, so `Module::VERSION` was not bumped and the product data transients stay valid. Superseded on 2026-10-20: the changes for classic themes below touch JavaScript, so `Module::VERSION` is 1.1.1 and `Plugin::VERSION` is 0.10.26. The 1.1.1 bump orphans the product data transients `infoplus_pd_{id}_1.1.0` (they expire after 12 h) and the first view of each product rebuilds it.

### Changes for classic themes (Plugin 0.10.26, ProductDetailSuite 1.1.1)

Made for the classic Infoplus theme (single product page). Every change keeps the old selectors, so the Elementor product template keeps working. A theme marks its elements with data attributes.

| File | Change | Elementor template |
|------|--------|--------------------|
| `ProductDetailSuite/Assets/variation-dropdowns.js` `pulseActionButton()` | Also pulses `[data-ip-action="inquiry"]` (requestable models) and `[data-ip-action="editor"]` (editable and buyable). | Unchanged: `#anfrage .elementor-button` and `#editor .elementor-button` still match. |
| `ProductDetailSuite/Assets/product-state.js` readonly product field | Also matches `[data-ip-field="product"]`. Re-runs on the DOM event `infoplus:inquiry-open` on `document`, which a theme modal dispatches with `document.dispatchEvent(new CustomEvent('infoplus:inquiry-open'))`. | Unchanged: `#form-field-product` and `elementor/popup/show` still work. |
| `WooSingleProductAddons/Assets/variation-editor-link.js` | The order type button manager finds the theme buttons: editor `#editor, [data-ip-action="editor"]` (link `#editor .elementor-button, a[data-ip-action="editor"]`), Anfrage `#anfrage, [data-ip-action="inquiry"]`, label `.elementor-button-text, [data-ip-label]`, quantity `#qty_value, [data-ip-qty="value"]`. The pulse also applies to the `[data-ip-action]` element itself. This file was not in the table above; without it the theme's editor link never got its URL. | Unchanged. |
| `WooSingleProductAddons/Assets/elementor-bulk-calculator.js` | Lookups through selector constants: `#qty_value, [data-ip-qty="value"]`, `#qty_minus, [data-ip-qty="minus"]`, `#qty_plus, [data-ip-qty="plus"]`, `#Stuckpreis_value h2, [data-ip-qty="unit-price"]`, `#Gesamtpreis_value h2, [data-ip-qty="total-price"]`, `#Mindestanzahl_value, [data-ip-qty="min"]`. The row that is hidden for requestable models is `[data-ip-qty-row]` when the page has one, else `$('#qty_value').closest('.e-con')` as before. The limit message is inserted after `[data-ip-qty-controls]` when present. | Unchanged: no `[data-ip-qty-row]` on that page, so the `.e-con` fallback runs. |
| `WooSingleProductAddons/Module.php` `outputProductContextJson()` | Variation list printed once, filter `infoplus_suite_product_context_variations` (see the 0.10.26 note in the WooSingleProductAddons section). | About 194 KB less HTML on Atlantis (about 260 KB after LiteSpeed's base64 encoding). |

The theme side (data attributes on the buttons and the quantity row) is in the Infoplus theme, `template-parts/product/actions.php` and `purchase.php`.

Verified on a local WordPress 6.9 with WooCommerce and this branch (no Elementor), with a variable test product of 33 variations: editable model (editor link gets `{baseUrl}?product=…&theme=…&cartEntryPath=…&backPath=…` after the variation is complete), buyable model ("In den Warenkorb"), requestable model (editor hidden, quantity row hidden, Anfrage pulses), tier click and plus button update Einzelpreis and Gesamtpreis, no console errors. The Elementor template was not tested after these changes (unverified); the old selectors are kept as they were.

---

## 20. Order Types and the Buy Flow

Added in Plugin 0.10.27 (branch `feature/view-renderer`, 2026-10-20) for the classic Infoplus theme. The Elementor template keeps its old behaviour.

### Where the order type comes from

The order type is set per product and per model, not per variation. In the product edit screen, meta box "Infoplus Suite", tab Attribute Overrides, every `pa_model` term has a "Bestelltyp" select. It is saved as post meta on the parent product: `_infoplus_attr_order_type_model_{term_slug}`. Allowed values: `editable` (default when nothing is saved), `requestable`, `requestable_with_variants`, `buyable`. `Fields\ProductAttributeImages::getTermOrderType()` reads it, and `ProductDetailSuite` puts it into `window.infoplusProductData.models[].order_type`.

`variation-editor-link.js` reads `order_type` of the selected model on `model:changed`. It has one fallback: an `editable` model counts as `buyable` when the selected option of `select[name^="attribute_pa_model"]` contains "sonderkontur". That select only exists when the page renders WooCommerce's variations form, which neither the Elementor template nor the classic theme does by default, so the fallback does not apply there. On the server, `WooSingleProductAddons\Module::resolveOrderType()` applies the same rule to the model slug.

Since 0.10.27 the script also writes the result to `infoplusProductState.orderType` and emits `ordertype:changed` (`{ orderType, model }`, also as the DOM event `infoplus:ordertype:changed` on `document`).

### The three order types on the product page

| Order type | What the visitor sees | What happens |
|------------|----------------------|--------------|
| `editable` | "Produkt gestalten" and "Individuelle Anfrage" | The editor link stays disabled until the selection matches a variation, then opens the external editor in a new tab (`{baseUrl}?product={_ife_uuid}&theme={themeId}&cartEntryPath=…&backPath=…`). The editor puts the item into the Warenkorb through InfoplusAPI. |
| `buyable` | "In den Warenkorb" only (Elementor template). Classic theme since 0.10.28: "In den Warenkorb" plus "Individuelle Anfrage" | Elementor template: `#editor` turns into an admin-ajax button (`infoplus_add_to_cart`). Classic theme: a WooCommerce add-to-cart form, see below. |
| `requestable`, `requestable_with_variants` | "Individuelle Anfrage" only | No price and no quantity row (`elementor-bulk-calculator.js` hides it). The Anfrage button opens the inquiry (Elementor popup 14241, or on the classic theme for now the Kontakt page with `?produkt=`). With `requestable_with_variants` the dropdowns stay usable so the inquiry can carry the chosen variant. |

The classic theme marks the active type on its button container (`data-ip-order-type`), gives each type its own label, icon and one line of explanation (a polite live region), and mirrors the active button in the sticky phone bar with a full `aria-label`.

### Buy flow on the classic theme

The theme renders, next to the editor link, a form in `template-parts/product/actions.php`:

```html
<form class="cart" data-ip-buy-form method="post" enctype="multipart/form-data" action="{product permalink}">
  (woocommerce_before_add_to_cart_button)
  <input type="hidden" name="add-to-cart" value="{parent product ID}">
  <input type="hidden" name="product_id" value="{parent product ID}">
  <input type="hidden" name="variation_id" value="{selected variation}">
  <input type="hidden" name="quantity" class="qty" value="{quantity}">
  <input type="hidden" name="infoplus_buy_form" value="1">
  <input type="hidden" name="attribute_pa_model" value="…"> (one per variation attribute)
  <button type="submit" data-ip-action="cart">In den Warenkorb</button>
  (woocommerce_after_add_to_cart_button)
</form>
```

1. The theme's `product.js` shows the form only for `buyable` models and fills the hidden fields from `infoplusProductState` (selected model, `selectedAttributes`, `selectedVariation`, the quantity input). The submit button is `aria-disabled` until a variation is found. `elementor-bulk-calculator.js` also writes the clamped quantity into `form.cart input.qty`.
2. When `form[data-ip-buy-form]` exists, `variation-editor-link.js` hides `#editor` for `buyable` models and does not bind the admin-ajax handler. Until 0.10.27 it also hid `#anfrage`; since 0.10.28 `#anfrage` stays visible, so the inquiry is always available on the classic theme (DESIGN.md of the theme: "The inquiry is always available as a secondary action"). "Kombination nicht verfügbar" is shown after the form instead of after the editor button.
3. The browser posts the form to the product page. WooCommerce's `WC_Form_Handler::add_to_cart_action()` handles it: `woocommerce_add_to_cart_validation` (Min and Max Quantity for WooCommerce, Germanized and other plugins), `WC()->cart->add_to_cart()` with its own attribute checks, `woocommerce_add_cart_item_data` (all plugins), then either a redirect to the Warenkorb (WooCommerce setting "Redirect to the cart page after successful addition", `woocommerce_cart_redirect_after_add`) or the product page again with the success notice and the new header count. Errors come back as WooCommerce notices at the top of the product page. After such a page load the theme restores the posted model, options and quantity.
4. Two Suite filters run only for this form. Both check `infoplus_buy_form` in the POST and return early for every other request, including the admin-ajax handler:
   - `validateBuyForm()` on `woocommerce_add_to_cart_validation` (priority 20): refuses a variation whose model is not `buyable` with an error notice. The page never offers that, so it only catches tampered or stale requests. Filter `infoplus_suite_buy_form_check_order_type` (bool) turns it off.
   - `addBuyFormCartItemData()` on `woocommerce_add_cart_item_data` (priority 20): adds `_infoplus_model_image_url`, the first gallery image of the model (`getTermGalleryUrls()`, the same URL as `models[].image_url`), unless the line already has one.

### What the old handler added, and where it comes from now

| Cart line data | `infoplus_add_to_cart` (Elementor) | Buy form (classic theme) |
|----------------|-----------------------------------|--------------------------|
| Product, variation, quantity | From POST | From POST, WooCommerce's handler |
| Variation attributes | From the variation object | From the posted `attribute_*` fields, checked by WooCommerce against the variation |
| `_infoplus_model_image_url` | From POST (`model_image_url`, sent by the browser) | `addBuyFormCartItemData()`, resolved on the server |
| `sonderkontur_files` | `attachFilesToCart()` on `woocommerce_add_cart_item_data` | Same filter, unchanged |
| Validation (`woocommerce_add_to_cart_validation`) | Not run | Run |
| Staffelpreise | Not handled by the Suite in either path. The tiers on the page come from `/infoplus/v1/products/prices` (Section 12). The cart price is set by the pricing plugin (Advanced Dynamic Pricing on staging) when the cart is calculated, the same way in both paths (not tested locally, the plugin is not installed there). |
| Minimum order quantity | Only clamped in the browser | Only clamped in the browser (Section 16, row 20) |

### Per product setup

Nothing new. A model is bought directly when its "Bestelltyp" is "Buyable (Warenkorb)" and its variations have a price. The buy form is rendered on every variable product page of the classic theme; it stays hidden until a `buyable` model is selected.

### Verified

Local WordPress with WooCommerce 10.6.1, this branch and the classic theme (no Elementor, no Advanced Dynamic Pricing, no Min and Max plugin), test product with four models (one `buyable`, one `requestable`, two `editable`), 2026-10-20:

- `buyable` model, 68x28, Kombi-Clip, quantity 12: one POST to the product page, no admin-ajax request. Cart line has the right `variation_id`, the three `attribute_pa_*` values, quantity 12, `_infoplus_model_image_url` of the model. The line price was the regular variation price (no pricing plugin installed locally). Success notice shown, header count 12, selection and quantity restored.
- With the cart redirect setting on: redirect to the Warenkorb, line added.
- Posted variation of a `requestable` model: error notice from `validateBuyForm()`, nothing added. No variation: WooCommerce's "Please choose product options" notice. Posted format that does not match the variation: WooCommerce's "Invalid value posted for Format". Click on the disabled button: no request, a hint asks for the missing options.
- `editable` and `requestable` models: unchanged (editor link with URL, Anfrage only with quantity row hidden).
- Same page with the form removed (as on the Elementor template): `#editor` turns into "In den Warenkorb" and posts `infoplus_add_to_cart` as before.

---

## 21. Inquiry Form ("Individuelle Anfrage")

Added in Plugin 0.10.29 (branch `feature/view-renderer`, 2026-10-20) for the classic Infoplus theme. Not deployed. It replaces the Elementor popup 14241 (Section 9) on the classic theme and is meant to serve the Kontakt page as well. The Elementor template is not changed.

### Split between Suite and theme

| Part | Where |
|------|-------|
| Form markup (both steps, success state) | Suite `templates/inquiry-form.php`, rendered by `infoplus_suite_inquiry_form(array $args)` through `View::render('inquiry-form')`. Theme override: `{theme}/infoplus-suite/inquiry-form.php` |
| E-mail body | Suite `templates/inquiry-email.php` (`View::capture('inquiry-email')`), override `{theme}/infoplus-suite/inquiry-email.php` |
| REST, validation, storage, files, mails, admin, settings | Suite `src/Modules/Inquiry/` |
| Drawer (`<dialog id="ip-inquiry">`), styles, browser logic | Infoplus theme: `inc/inquiry.php`, `template-parts/inquiry-drawer.php`, `assets/css/inquiry.css`, `assets/js/inquiry.js` |

Files of the module:

| File | Role |
|------|------|
| `Module.php` | Registration, `formArgs()` (template args), `prefill()` (logged-in customer), `clientConfig()` (JSON for the browser) |
| `Controller.php` | REST routes, validation and sanitising, honeypot, time trap, rate limit, product context lookup |
| `Token.php` | Signed form token |
| `Files.php` | Upload checks and protected storage |
| `PostType.php` | Post type `infoplus_inquiry`, statuses, reference numbers |
| `Admin.php` | List columns, status filter, detail and status meta boxes, file download, "Neu" count in the menu |
| `Mailer.php` | Team e-mail and customer confirmation |
| `Settings.php` | Option `infoplus_inquiry_settings`, tab "Anfragen" in IKONA IFE |

`infoplus_suite_inquiry_form()` takes `variant` (`product` or `contact`), `product` (`WC_Product`, ID, or null for the queried product) and `id_prefix`.

### Form

Step 1 "Ihr Projekt" (contact variant: "Ihr Anliegen"): product card (image, name, chips for model and options, button "Auswahl ändern"), Menge (required in the product variant, prefilled from the configurator), toggle "Regelmäßiger Bedarf", Wunschtermin (optional, not in the past), Dateien (drag and drop or picker, several files), Ihre Nachricht (optional; required in the contact variant), toggle "Kostenlose Muster anfordern".

Step 2 "Ihre Kontaktdaten": Firma (recommended), Vorname and Nachname (required), E-Mail (required), Telefon, PLZ and Land (optional, for a shipping quote), consent checkbox with a link to the privacy page (required), honeypot `ip_extra`.

Success state: reference number, the address of the confirmation e-mail and the text "So geht es weiter" from the settings.

Elements with `data-ip-inq-only="product"` or `"contact"` show only in that variant; the theme script disables the inputs of the other one. The full list of data attributes is in the template header.

### Product context

The browser sends IDs and slugs only: `context.product_id`, `context.model`, `context.attributes` (`{ pa_format: '68x28', ... }`), `context.variation_id`, and on the Kontakt page `context.product_name`. `Controller::resolveProduct()` looks everything up again:

- The product must exist and be published.
- Each attribute must be an attribute of the product, and its value a term (or option) of the product. Unknown keys and values are dropped.
- The model gives the order type (`_infoplus_attr_order_type_model_{slug}`) and the image (first image of `_infoplus_attr_gallery_model_{slug}`).
- The variation ID is kept only when it is a child of the product and its attributes match the chosen ones.
- A product name without ID is stored as text with `verified => false` (shown as "nicht geprüft" in the admin).

### REST routes and security

Namespace `infoplus/v1`:

| Method | Route | Permission | Notes |
|--------|-------|------------|-------|
| POST | `/inquiries/session` | `__return_true` (public on purpose) | Returns `{ token, minSeconds }`, header `Cache-Control: no-store, private`. A POST so no page or REST cache stores it. 60 per IP and 15 minutes. |
| POST | `/inquiries/files` | valid token | Multipart field `file`, one file per request (per-file progress in the browser). 40 per IP and 15 minutes. Returns `{ id, name, size }` (201). |
| DELETE | `/inquiries/files/{id}` | valid token | Removes an uploaded file of this token again. |
| POST | `/inquiries` | valid token | JSON body. Returns `{ reference, message, email }` (201), 422 with `data.fields` (field name to German message), 400 for honeypot or time trap, 429 for the rate limit, 403 for a missing or expired token. |

Nonce strategy:

- Guests: product pages are page-cached, so a nonce in the HTML would go stale. The drawer asks `/inquiries/session` for a token when it opens. The token is `{issued time}.{random}.{HMAC-SHA256}` keyed with `wp_salt('nonce')` (`Token.php`). It is sent as header `X-IP-Inquiry-Token` and is valid for 6 hours.
- Logged-in visitors: `clientConfig()` adds a `wp_rest` nonce only for them (their pages are not page-cached). The browser sends it as `X-WP-Nonce`; core checks it before the route runs and treats a request without it as a guest, so the inquiry would not be linked to the account. The token is required as well.

Spam and abuse:

- Honeypot `ip_extra` (hidden, `tabindex="-1"`, `autocomplete="off"`): filled means 400.
- Time trap on the server clock: a submit sooner than 4 seconds (`Token::MIN_SECONDS`) after the token was issued is refused.
- Rate limit per IP: 5 accepted submits per 15 minutes (counted after validation, so correcting a field never locks a person out). Only a salted hash of `REMOTE_ADDR` is kept, in a transient. Filters `infoplus_suite_inquiry_client_ip` (for a proxy) and `infoplus_suite_inquiry_rate_limit`.
- A repeated submit with the same token (double click, lost response) returns the first answer and creates nothing new.

Validation and sanitising: every field is sanitised (`sanitize_text_field`, `sanitize_textarea_field`, `sanitize_email`) and cut to a maximum length. Menge integer 1 to 1,000,000; Wunschtermin `Y-m-d`, not before today and not more than three years ahead (site timezone); e-mail `is_email()`; phone digits, spaces, `+()/.-`; PLZ 3 to 12 letters, digits, spaces or hyphens; Land must be an allowed WooCommerce country; consent must be true. `source_url` is kept only when it has the site's host.

Files:

- Extensions: pdf, ai, eps, svg, png, jpg, jpeg, webp. Each file is checked with `wp_check_filetype_and_ext()` against the real content (fileinfo) for a list of allowed MIME types per extension (`Files::TYPES`; AI as PDF or PostScript, EPS as PostScript, SVG as SVG/XML with an `<svg` root). During the check those types are added to `upload_mimes` and removed again, so the media library does not accept them. A PNG named `.svg` is stored as `.png`; `logo.php.png` with PHP content and `.exe` are refused.
- Size: total of all files of one form at most the setting (default 20 MB, cap 64 MB); one file is also limited by PHP (`wp_max_upload_size()`). At most 10 files (setting).
- Storage: `wp-content/uploads/infoplus-inquiries/`, created with an `.htaccess` (`Require all denied`, `Deny from all`, `Options -Indexes`, `php_flag engine off`) and `index.php`. Temporary files in `tmp/`, sent files in `{Y}/{m}/{random}/`. Stored names are random hex with the checked extension; the original name is kept only in the record. Never in the media library. nginx does not read `.htaccess` (unverified for production, LiteSpeed is expected). The PHP built-in server used for local tests ignores `.htaccess` as well, so the deny rule was not tested locally.
- Download: `admin-post.php?action=infoplus_inquiry_file&post={id}&file={index}`, only with `edit_post` on the inquiry, always as `application/octet-stream` with `Content-Disposition: attachment` and `nosniff` (an SVG is never rendered in the admin's browser). Logged-out requests get 400 from `admin-post.php`.
- Temporary files that were never sent are removed by the daily cron `infoplus_inquiry_cleanup` after 24 hours. Permanently deleting an inquiry deletes its files (`before_delete_post`).

### Post type `infoplus_inquiry`

Private, not public, no REST, no front-end URL. Menu: WooCommerce → Anfragen (under IKONA IFE when WooCommerce is inactive), with a count of "Neu" inquiries. All capabilities map to `manage_woocommerce`; `create_posts` is `do_not_allow`.

| Meta key | Content |
|----------|---------|
| `_ip_inquiry` | The whole record: variant, product (see above), quantity, recurring, wish_date, message, samples, files (name, file, size, ext, mime), contact (company, first_name, last_name, email, phone, postcode, country, country_name), consent (time, privacy_url), source_url, reference, created, user_id |
| `_ip_inquiry_ref` | Reference, e.g. ANF-2026-0042 (also the start of the title) |
| `_ip_inquiry_status` | `neu`, `in-bearbeitung`, `angebot-gesendet`, `erledigt` (Neu, In Bearbeitung, Angebot gesendet, Erledigt) |
| `_ip_inquiry_email` | Customer e-mail (searching an e-mail address in the list finds it) |
| `_ip_inquiry_product_id` | Product ID or 0 |
| `_ip_inquiry_mail` | Result of both e-mails: sent, time, to, attached |

The record is stored before any e-mail is sent, so a failing mail never loses a request; the mail result is shown in the status box. List columns: Referenz, Status, Firma / Name, Produkt, Menge, Dateien, Eingang; filter by status. The edit screen shows a read-only detail box (Projekt, Dateien, Kontakt, Technisch) and a status box (status select, "Status speichern", "Per E-Mail antworten", mail results). The post always stays private.

Reference numbers: `ANF-{year}-{0000}`, one counter per year in the option `infoplus_inquiry_seq_{year}`, raised with a single SQL UPDATE. Filter `infoplus_suite_inquiry_reference`.

### E-mails

Sent with WooCommerce's e-mail header and footer and `WC_Email::send()` (shop sender, WooCommerce e-mail styles), or a small branded wrapper without WooCommerce. Both use the template `inquiry-email`.

- Team: to the recipients from the settings (filter `infoplus_suite_inquiry_recipients`), `Reply-To` the customer, subject "Neue Anfrage {ref}: {product} ({company or name})". Files are attached under their original names while all files together stay within "Dateien anhängen bis" (default 10 MB); otherwise the mail lists download links to the admin (login needed). Button "Anfrage im Backend öffnen".
- Customer (setting, default on): subject "Ihre Anfrage {ref} bei {site name}", `Reply-To` the first team recipient, the reference, the "So geht es weiter" text and a summary of the request.

### Settings (IKONA IFE → tab "Anfragen")

Option `infoplus_inquiry_settings`:

| Key | Default | Notes |
|-----|---------|-------|
| `recipient` | empty: the site admin e-mail (`admin_email`) | Comma separated. **To be confirmed by the client before rollout.** On staging the default is the site admin e-mail. No address is written in code. |
| `success_text` | "Wir prüfen Ihre Anfrage und melden uns innerhalb von 24 Stunden mit einem Angebot." | Shown in the success state and in the customer e-mail. Owner decision of 2026-10-20 to promise 24 hours. **To be confirmed by the client before rollout.** |
| `max_upload_mb` | 20 | Total of all files, 1 to 64 |
| `max_files` | 10 | 1 to 20 |
| `attach_limit_mb` | 10 | Larger totals are linked instead of attached; 0 never attaches |
| `customer_mail` | on | Confirmation to the customer |
| `privacy_page` | 0: the WordPress privacy page (else a page with slug `datenschutz`) | Link next to the consent checkbox |

### Hooks

| Hook | Type | Use |
|------|------|-----|
| `infoplus_suite_inquiry_record` | filter | Change the record before it is stored |
| `infoplus_suite_inquiry_created` | action | After storing and mailing (`$post_id`, `$record`, `$mail`) |
| `infoplus_suite_inquiry_recipients` | filter | Team recipients |
| `infoplus_suite_inquiry_reference` | filter | Reference format |
| `infoplus_suite_inquiry_client_ip` | filter | Client IP for the rate limit |
| `infoplus_suite_inquiry_rate_limit` | filter | Limit per bucket (`session`, `upload`, `submit`) |
| `infoplus_suite_inquiry_client_config` | filter | Browser configuration and messages |

### Theme side (Infoplus theme)

`inc/inquiry.php` prints the drawer in `wp_footer` and loads `inquiry.css` and `inquiry.js` on product pages (variant product) and on the page with slug `kontakt` (variant contact); filter `ip_inquiry_variant`. It appends `#anfrage` to `ip_product_inquiry_url()`, so the no-JS link (Kontakt page with `?produkt=`) opens the drawer there with the product name.

`assets/js/inquiry.js` opens the drawer on a click on `[data-ip-action="inquiry"]`, `[data-ip-inquiry-link]`, `[data-ip-inquiry-open]` or a link ending in `#anfrage` (capture phase, so the link does not navigate), on the DOM event `infoplus:inquiry-open` (its `detail` is the context), through `window.InfoplusInquiry.open(context)`, and on the hash `#anfrage`. After opening it dispatches `infoplus:inquiry-open` with `detail.source = 'drawer'`, which makes `product-state.js` re-run its readonly product field check (Section 19). The product context comes from `window.infoplusProductData` and `window.infoplusProductState` (selected model, `selectedAttributes`, `selectedVariation`, quantity from `[data-ip-qty="value"]` or `currentQty`). Further events: `infoplus:inquiry-close`, `infoplus:inquiry-sent` (`{ reference }`) and the cancelable `infoplus:inquiry-edit-selection` ("Auswahl ändern").

### Verified

Local WordPress 6.9 with WooCommerce 10.6.1, this branch and the classic theme, 2026-10-20 (mail captured by a local mu-plugin, nothing sent):

- Product page (model selected), 1440 and 390 px: drawer opens with product name, model chip and quantity; step validation in German with summary and focus on the first error; past Wunschtermin refused; PNG and PDF uploaded with progress, `.exe` and a 21 MB PDF refused in the browser; submit after both steps creates the inquiry, success state with reference; Escape closes and focus returns to the button.
- Server: `.exe`, `evil.php.png` (PHP content) refused; SVG accepted; PNG renamed `.svg` stored as `.png`; missing or forged token 403; honeypot 400; submit within 4 seconds 400; sixth accepted submit within 15 minutes 429; repeated submit returns the same reference; unknown attribute keys and values dropped; a variation that does not match the chosen options dropped.
- Kontakt page with `?produkt=…#anfrage`: contact variant, message required, privacy link opens a new tab without closing the drawer, inquiry stored with the unverified product name.
- Logged-in customer: step 2 prefilled from the billing data, inquiry linked to the account.
- Admin: list, status filter, detail box, status saved, file download as attachment for the admin, refused when logged out. Team e-mail with three attachments and Reply-To, customer e-mail with the 24-hour text.
- Not tested: real mail delivery, the `.htaccess` deny rule (PHP built-in server), the Elementor template, WebKit.

