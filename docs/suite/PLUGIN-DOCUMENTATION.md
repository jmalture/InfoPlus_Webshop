# Infoplus Suite — Plugin Documentation

> **Version:** 0.9.40  
> **Author:** JM (Alture / Infoplus)  
> **License:** GPL-2.0-or-later  
> **Last Updated:** 2026-02-19

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
5. [Module Dependency Map](#5-module-dependency-map)
6. [REST API Endpoints](#6-rest-api-endpoints)
7. [WooCommerce Meta Keys](#7-woocommerce-meta-keys)
8. [Conventions & Patterns](#8-conventions--patterns)

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

**Tech stack:** WordPress, WooCommerce, Elementor Pro, ACF, PHP 7.4+, jQuery, REST API.

**Brand accent color:** `#e91e8c` (pink), used across all custom UI.

---

## 2. Architecture

### 2.1 Directory Structure

```
infoplus-suite/
├── infoplus-suite.php          # Plugin entry point
├── composer.json                # PSR-4 autoloader config
├── assets/                      # Plugin-level assets (icons, logos)
│   ├── ikona-icon.ico
│   ├── ikona-icon.png
│   └── logo-ikona.jpg
└── src/
    ├── Plugin.php               # Main plugin orchestrator
    ├── Admin/
    │   └── Dashboard.php        # WP Admin menu page + tab renderer
    ├── Contracts/
    │   └── ModuleInterface.php  # Interface all modules implement
    └── Modules/
        ├── EditorLinkSettings/  # Settings tab (admin UI)
        ├── IkonaIFE/            # Product sync dashboard (admin UI)
        ├── WooProductVariationFields/  # WooCommerce product meta fields
        ├── WooSingleProductAddons/     # Frontend product page scripts + file uploads
        ├── ProductDetailSuite/         # Elementor widgets for product pages
        ├── UserDashboard/              # User profile + auth management
        ├── UserOrders/                 # Order display + Elementor widgets
        └── ElementorUserAvatar/        # User avatar dynamic tag
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

All modules are instantiated in `Plugin.php → registerModules()`. Each module's `register()` method is called unconditionally — all modules run in the background.

### 2.3 Admin Dashboard Tabs

Only modules listed in `Plugin::$visibleModules` appear as tabs in the WP Admin dashboard page:

| Tab | Module Slug | Description |
|-----|-------------|-------------|
| Settings | `editor-link-settings` | Editor URL configuration |
| IFE Sync | `ikona-ife` | Product synchronization dashboard |

The admin page is registered under **IKONA IFE** in the WP sidebar (position 56) with a custom icon.

---

## 3. Plugin Bootstrap

### `infoplus-suite.php` (Entry Point)

1. Registers a PSR-4 autoloader: `Infoplus\Suite\` → `src/`
2. On `plugins_loaded`, instantiates `Plugin` and calls `boot()`
3. On error, logs to `error_log` and shows an admin notice

### `src/Plugin.php` (Orchestrator)

`boot()` runs in order:

1. **`defineConstants()`** — Sets `INFOPLUS_SUITE_FILE`, `INFOPLUS_SUITE_PATH`, `INFOPLUS_SUITE_URL`
2. **`registerModules()`** — Instantiates all 8 modules, calls `register()` on each
3. **`registerAdmin()`** — Passes visible modules to `Dashboard`, registers the admin page
4. **`registerHooks()`** — Loads text domain for i18n

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
4. SyncService fetches from internal REST: /infoplus/v1/products (Ikona plugin endpoint)
5. Products rendered in JS table with search/filter/sort
6. User clicks Sync → JS calls GET /infoplus/v1/products/synchronization?productId=X (EXTERNAL Ikona API)
7. On success → JS calls POST /infoplus-suite/v1/products/{id}/sync-result (saves to WP)
8. SyncService saves timestamps and IDs to post_meta
```

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

These hit the **Ikona IFE plugin** endpoints (separate plugin), not Infoplus Suite endpoints.

#### Sync Status Values

| Value | Constant | Badge |
|-------|----------|-------|
| 0 | `STATUS_SYNCHRONIZED` | ✔ Synchronized (green) |
| 1 | `STATUS_MODIFIED` | ⚠ Modified (orange) |
| 2 | `STATUS_NEVER_SYNCED` | ⏸ Never Synced (gray) |

#### ⚠ Current State (as of v1.0.4)

**Sync buttons are temporarily disabled** to prevent breaking products on the IFE end. The click handlers in `dashboard.js` are commented out. The Sync All button is visually disabled (grayed out). Individual sync buttons have a no-op click handler. All sync functions (`handleSyncAll`, `syncProduct`, etc.) remain intact and untouched.

---

### 4.3 WooProductVariationFields

> **Slug:** `product-variation-fields`  
> **Purpose:** Adds custom IFE/IBE meta fields to WooCommerce product edit pages.  
> **Has admin tab:** No (runs in background)

#### What it does

Adds an **IBE/IFE** tab to the WooCommerce product data panel (visible only for variable products). Stores IFE-related metadata on products that the sync system and editor link generation depend on.

#### Files

| File | Role |
|------|------|
| `Module.php` | Registers WooCommerce product data tab, renders fields, saves meta, exposes fields to REST API. |
| `Assets/admin-fields.js` | Media uploader support for admin fields. |

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

All visible fields are **read-only** with lock icons — they're managed by the IFE sync system.

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
| `Module.php` | Script enqueueing, REST endpoints for file uploads, cart/order integration hooks, product context JSON output, editor settings injection. |
| `Assets/variation-filter.js` | Filters available variations based on selected attributes. |
| `Assets/elementor-bulk-calculator.js` | Calculates and displays bulk pricing tiers. |
| `Assets/custom-model-dropdown.js` | Custom styled model dropdown behavior. |
| `Assets/product-gallery-sizer.js` | Handles product gallery image sizing/layout. |
| `Assets/variation-editor-link.js` | Updates the "Open Editor" button URL when a variation is selected. |
| `Assets/sonderkontur-upload-toggle.js` | Toggle UI for the Sonderkontur file upload area. |
| `Assets/sonderkontur-upload.css` | Styling for the upload UI. |
| `Assets/sonderkontur-admin.css` | Admin order page styling for uploaded files display. |
| `Assets/assets/arrow.svg` | Dropdown arrow icon. |

#### Frontend Global Variables

Injected via `wp_head` on product pages:

| Variable | Content |
|----------|---------|
| `window.currentProduct` | Full product context (id, sku, name, image, categories, variations) |
| `window.allWooVariations` | Flat array of variation attribute combos |
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

#### Cross-module usage

- Reads **EditorLinkSettings** via static methods for editor URL config
- Reads **WooProductVariationFields** meta (`_ife_uuid`) for editor URL generation

---

### 4.5 ProductDetailSuite

> **Slug:** `product-detail-suite`  
> **Purpose:** Custom Elementor widgets for product detail pages with shared state management.  
> **Has admin tab:** No (runs in background)  
> **Version:** 1.0.35

#### What it does

Provides three custom Elementor widgets that work together through a shared JavaScript state system (`product-state.js`) to create an integrated product configuration experience. Also adds custom fields to WooCommerce attribute terms and per-product attribute image overrides.

#### Files

| File | Role |
|------|------|
| `Module.php` | Widget registration, REST endpoints, admin/frontend asset enqueueing, product data building, product state output. |
| **Widgets/** | |
| `Widgets/ModelSelector.php` | Elementor widget: visual model selector (card grid with images, names, prices). |
| `Widgets/VariationDropdowns.php` | Elementor widget: attribute dropdown selectors that update based on model selection. |
| `Widgets/ProductImage.php` | Elementor widget: dynamic product image that changes with variation selection. |
| **Fields/** | |
| `Fields/AttributeTermFields.php` | Adds image, tooltip, and modal content fields to WooCommerce attribute term edit pages. |
| `Fields/ProductAttributeImages.php` | Adds per-product attribute image overrides in the product admin (product-level images take priority over global term images). |
| `Fields/VariationFields.php` | *(if present)* Custom fields on individual variations. |
| **Assets/** | |
| `Assets/product-state.js` | **Central state manager** — event bus for widget communication (`model:changed`, `variation:found`, etc.). |
| `Assets/model-selector.js` | Model selector widget frontend logic. |
| `Assets/variation-dropdowns.js` | Variation dropdowns frontend logic. |
| `Assets/product-image.js` | Product image widget frontend logic. |
| `Assets/product-detail-suite.css` | Shared styles for all widgets. |
| `Assets/admin-variation-fields.js` | Admin JS for variation custom fields (media uploader). |
| `Assets/admin-variation-fields.css` | Admin styles for variation fields. |
| `Assets/admin-product-attribute-images.js` | Admin JS for per-product attribute image management. |
| `Assets/admin-product-attribute-images.css` | Admin styles for attribute image UI. |

#### Elementor Widget Category

Registers `infoplus-product` category ("Infoplus Product") in Elementor.

#### Shared State System

All three widgets communicate through `product-state.js`:

```
ModelSelector → fires "model:changed" → VariationDropdowns listens, updates options
VariationDropdowns → fires "variation:found" → ProductImage listens, updates image
All widgets → read from window.infoplusProductData (injected on page load)
```

#### REST Endpoints (namespace: `infoplus/v1`)

| Method | Route | Permission | Purpose |
|--------|-------|------------|---------|
| GET | `/product/{id}/detail` | Public | Full product data with custom fields, variations, models, attributes |
| GET | `/attribute-terms/{attribute}` | Public | Attribute terms with images, tooltips, modal content |

#### Frontend Global Variable

```js
window.infoplusProductData = {
  id, name, sku, type, image, gallery, price, price_html,
  variations: [{ id, sku, price, attributes, model_image, ... }],
  attributes: { pa_model: { name, terms: [{ slug, name, image_url, ... }] }, ... },
  models: [{ slug, name, image_url, min_price, variation_count, ... }],
  ife_uuid, editor_url
}
```

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

Provides REST API endpoints for user profile management, handles login/logout redirects to a custom `/account` page (instead of WooCommerce's `/my-account`), includes a login guard shortcode, and provides a dropdown menu script for the frontend navigation.

#### Files

| File | Role |
|------|------|
| `Module.php` | REST endpoints, login redirect filters, shortcode, frontend scripts. |
| `Assets/user-dashboard.js` | Frontend JS for profile management API calls. |
| `Assets/menu-dropdown.js` | Navigation dropdown menu behavior (login/register/account/logout links). |
| `Assets/menu-dropdown.css` | Dropdown menu styling. |
| *(Also contains copies of ProductDetailSuite assets — likely legacy/duplicate)* | |

#### REST Endpoints (namespace: `infoplus/v1`)

| Method | Route | Permission | Purpose |
|--------|-------|------------|---------|
| GET | `/user/profile` | Logged in | Get current user profile data |
| POST | `/user/profile` | Logged in | Update name, email, company |
| POST | `/user/password` | Logged in | Change password (requires current) |
| POST | `/user/avatar` | Logged in | Upload avatar image (JPG/PNG/GIF, 5MB max) |

#### User Meta Keys

| Meta Key | Description |
|----------|-------------|
| `infoplus_user_avatar` | Attachment ID of custom avatar |
| `infoplus_company` | Company name |

#### Login Flow

- `login_redirect` and `woocommerce_login_redirect` filters redirect all logins to `/account`
- `template_redirect` catches logged-in users visiting `/log-in` and redirects to `/account`
- Elementor editor/preview mode is excluded from redirects

#### Shortcode

`[infoplus_login_guard]` — Shows a styled "Login Required" message with redirect button for non-authenticated users. Returns empty for logged-in users.

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

The most feature-rich frontend module. Provides complete order management for customers: order list widget, order detail widget, custom URL routing (`/order/123/`), Elementor Loop Grid integration, 8 dynamic tags for order data, and a fully custom WooCommerce Thank You page.

#### Files

| File | Role |
|------|------|
| `Module.php` | REST endpoints, rewrite rules, template routing, Thank You page rendering, widget/tag registration. |
| `ElementorLoopIntegration.php` | Integrates with Elementor Loop Grid to use orders as a custom data source. |
| **Widgets/** | |
| `Widgets/UserOrdersGrid.php` | Elementor widget: displays order list in a card grid with search/filter. |
| `Widgets/OrderDetails.php` | Elementor widget: displays single order details (reads from URL query var). |
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
| `Assets/user-orders.js` | Frontend JS for order interactions. |
| `Assets/user-orders.css` | Order display styling. |
| `test-orders.php` | Debug shortcode template. |
| `test-orders-advanced.php` | Advanced debug template. |

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
2. `infoplus_user_avatar` (Infoplus custom — set by UserDashboard module)
3. `wp_user_avatar` (WP User Avatar plugin)
4. `basic_user_avatar` (Simple Local Avatars)
5. `simple_local_avatar` (Simple Local Avatars)
6. Falls back to Gravatar

Each value is checked as: attachment ID → URL string → array with `url` key → array with `full` key.

#### Cross-module usage

- Works with **UserDashboard** which saves avatars to `infoplus_user_avatar` meta key

---

## 5. Module Dependency Map

```
EditorLinkSettings ◄──── WooSingleProductAddons (reads editor URL config)
       ▲                         ▲
       │                         │
       └──── ProductDetailSuite ─┘ (reads editor URL + product UUID)
                    ▲
                    │
WooProductVariationFields (provides _ife_uuid, _ife_print_api_id meta)
       ▲
       │
IkonaIFE (SyncService writes to these meta keys during sync)

UserDashboard ────► ElementorUserAvatar (shares infoplus_user_avatar meta key)

UserOrders (standalone — reads WooCommerce order data directly)
```

**No circular dependencies.** EditorLinkSettings is the most depended-upon module.

---

## 6. REST API Endpoints

### Namespace: `infoplus-suite/v1` (Internal plugin endpoints)

| Method | Route | Module | Auth |
|--------|-------|--------|------|
| GET | `/products` | IkonaIFE | manage_woocommerce |
| POST | `/products/{id}/sync-result` | IkonaIFE | manage_woocommerce |
| GET | `/products/{id}/sync-status` | IkonaIFE | manage_woocommerce |
| POST | `/products/clear-cache` | IkonaIFE | manage_woocommerce |

### Namespace: `infoplus/v1` (Shared endpoints)

| Method | Route | Module | Auth |
|--------|-------|--------|------|
| GET | `/user/profile` | UserDashboard | logged_in |
| POST | `/user/profile` | UserDashboard | logged_in |
| POST | `/user/password` | UserDashboard | logged_in |
| POST | `/user/avatar` | UserDashboard | logged_in |
| GET | `/orders` | UserOrders | logged_in |
| GET | `/orders/{id}` | UserOrders | logged_in |
| POST | `/sonderkontur/upload` | WooSingleProductAddons | public |
| GET | `/sonderkontur/files` | WooSingleProductAddons | public |
| DELETE | `/sonderkontur/files/{index}` | WooSingleProductAddons | public |
| POST | `/sonderkontur/clear` | WooSingleProductAddons | public |
| GET | `/product/{id}/detail` | ProductDetailSuite | public |
| GET | `/attribute-terms/{attribute}` | ProductDetailSuite | public |

### External (Ikona IFE plugin — NOT part of Infoplus Suite)

| Method | Route | Called By |
|--------|-------|----------|
| GET | `/infoplus/v1/products` | IkonaIFE SyncService (server-side) |
| GET | `/infoplus/v1/products/synchronization` | IkonaIFE dashboard.js (client-side) |

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
| `infoplus_company` | UserDashboard | Company name |

### Order Item Meta

| Key | Set By | Description |
|-----|--------|-------------|
| `_sonderkontur_files` | WooSingleProductAddons | Array of uploaded file data |

---

## 8. Conventions & Patterns

### Version & Cache Busting

- Plugin version: `Plugin::VERSION` in `src/Plugin.php`
- Module-level versions: e.g., `IkonaIFE\Module::VERSION`, `ProductDetailSuite\Module::VERSION`
- All asset URLs use version parameter for cache busting
- **After any JS/CSS change:** bump the relevant VERSION constant and hard refresh (`Ctrl+Shift+R`)

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

## 9. Anfrage Popup — Data Injection Pattern

### Overview

The "Anfrage" (inquiry) call-to-action is an **Elementor popup** triggered by the Anfrage button in the `ProductOrderPanel` widget. Because the popup is not in the DOM until opened, standard jQuery selectors fail — the solution is a script that listens for the Elementor popup event.

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

- `window.infoplusProductData` — injected by `ProductDetailSuite/Module.php` on `woocommerce_after_single_product`
- `window.infoplusProductState` — shared event bus from `product-state.js`; exposes `.selectedModel` (slug) and `.selectedAttributes` (object of `pa_*` key to term slug)

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

### ⚠ Adding a new order type — required checklist

When adding a new order type value, **four separate whitelists** must all be updated or the new value will silently fall back to `'editable'` on reload (the save works, but the read discards the unknown value). Missing any one of these was the cause of the `requestable_with_variants` persistence bug.

| # | File | Location | What to update |
|---|---|---|---|
| 1 | `Fields/ProductAttributeImages.php` | `renderTermFields()` — local `$allowed_order_types` variable | Add new value to the array used to validate the saved DB value before pre-selecting the dropdown |
| 2 | `Fields/ProductAttributeImages.php` | `saveMetaBox()` — `$allowed` variable in the `META_KEY_ORDER_TYPE` branch | Add new value to the array used to sanitize the submitted POST value before saving |
| 3 | `Fields/ProductAttributeImages.php` | `getTermOrderType()` — `$allowed` variable | Add new value to the array used when reading the type for frontend data output |
| 4 | `Fields/ProductAttributeImages.php` | `renderTermFields()` — the `<select>` HTML | Add a new `<option value="...">` element visible to the admin |

Additionally update:

- `Assets/product-order-panel.js` → `applyOrderType()` — add button visibility logic for the new type
- `Assets/product-order-panel.js` → `applyVariationState()` — **required, see below**
- `PLUGIN-DOCUMENTATION.md` → update the order types summary table above

### ⚠ applyVariationState() must handle every order type explicitly

`applyVariationState()` in `product-order-panel.js` is called by multiple downstream event handlers (`variation:reset`, `variation:notfound`, `attribute:changed`). These events can fire synchronously after model selection — for example, WooCommerce fires `reset_data` inside `syncToWooCommerce()`, which chains into `variation:reset`, which calls `applyVariationState()`.

**If a new order type has no explicit branch in `applyVariationState()`, those downstream events can silently leave the buttons in a broken state** — even though `applyOrderType()` ran correctly and showed the right buttons a moment earlier.

The rule: **every order type must have an explicit case in both `applyOrderType()` AND `applyVariationState()`**.

| Order type | `applyOrderType()` | `applyVariationState()` |
|---|---|---|
| `editable` | Show Editor + Anfrage | Enable/disable Editor based on variation validity |
| `requestable` | Show Anfrage + pulse | Re-enforce Anfrage visible + pulsing (no variation needed) |
| `requestable_with_variants` | Show Anfrage + pulse | Re-enforce Anfrage visible + pulsing (no variation needed) |
| `buyable` | Show Cart | Enable/disable Cart based on variation validity |

For types where the button is always enabled (requestable family), `applyVariationState()` must **re-enforce** the visible + pulsing state rather than do nothing — this is the defensive pattern that prevents any subsequent event from leaving the button hidden.
