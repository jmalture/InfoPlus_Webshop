# Infoplus Suite: project brief

WordPress plugin for the Infoplus nameplate shop (Namensschilder). PHP 7.4+, WooCommerce, Elementor Pro, jQuery, WordPress REST API. German customer-facing UI. Owner: JM (Alture s.r.o.). Read this first, then `PLUGIN-DOCUMENTATION.md` for module detail and `PIPELINE.md` for how the plugins fit together.

## State of the repo

- `main` matches production byte for byte at commit `f01aca6` (2026-09-17). Later commits on `main` are documentation only.
- Branch `feature/catalog-import` (0.9.113) adds the CatalogImport module and the `import-mapping/` pipeline. Deployed on staging (navajowhite-rhinoceros-679020.hostingersite.com), not on production. Status in `import-mapping/WORK-ORDER.md`.
- Production history from April to September 2026 was synced in one commit (`3179bb5`), so `git log` does not explain individual features from that period. `PLUGIN-DOCUMENTATION.md` Section 18 summarises them.
- No deploy tooling, CI config, build step or tests in the repo. Composer install is not needed (PSR-4 fallback autoloader in `infoplus-suite.php`). How files reach production is not recorded here (unverified).

## Repo layout

```
infoplus-suite.php        entry point, plugin header (Version: 0.9.111 on main, 0.9.113 on feature/catalog-import)
composer.json             PSR-4 map Infoplus\Suite\ => src/, nothing else
src/Plugin.php            orchestrator, Plugin::VERSION (0.10.27 on feature/view-renderer, 0.10.25 before), registers the nine modules (ten on feature/catalog-import)
src/Admin/Dashboard.php   WP Admin menu "IKONA IFE" (slug infoplus-suite), renders the visible module tabs
src/Contracts/ModuleInterface.php   slug(), label(), register()
src/Modules/<Name>/Module.php       one module each, see below
src/Modules/<Name>/Assets/          JS and CSS, enqueued by the module itself
src/Modules/<Name>/Widgets/         Elementor widgets
assets/                   plugin icons
reference/                NOT deployed. InfoplusAPI live copy and the two legacy plugins. See reference/README.md
PLUGIN-DOCUMENTATION.md   module reference, REST routes, meta keys, known issues (kept current)
PIPELINE.md               end-to-end flows across all four plugins, merge reconciliation list
UPGRADE-ROADMAP.md        R-01 to R-15 with status lines
FEATURE-*.md              four older feature briefs, historical
DATA-IMPORT-PLAN.md       original proposal for the catalog import (2026-09-18), historical
import-mapping/           feature/catalog-import only: Excel -> catalog_mapping.csv -> import.csv, plus WORK-ORDER.md
```

Modules, in registration order: `WooSingleProductAddons`, `WooProductVariationFields`, `EditorLinkSettings` (admin tab), `IkonaIFE` (admin tab), `ElementorUserAvatar`, `UserDashboard`, `UserOrders`, `ProductDetailSuite`, `Custom404`. On `feature/catalog-import` also `CatalogImport` (WooCommerce → Katalog-Import, last).

## Two version numbers and cache busting

| Where | Value | Meaning |
|-------|-------|---------|
| `infoplus-suite.php` header | 0.9.111 | What WordPress shows. Bumped by hand at release. Not used in code. |
| `Plugin::VERSION` in `src/Plugin.php` | 0.10.27 (0.10.25 before feature/view-renderer) | Cache-bust string for assets of WooSingleProductAddons, UserDashboard, UserOrders and the product meta box. |
| `ProductDetailSuite\Module::VERSION` | 1.1.1 (1.1.0 before feature/view-renderer) | Cache-bust for ProductDetailSuite assets and part of the transient key `infoplus_pd_{product_id}_{VERSION}`. |
| `IkonaIFE\Module::VERSION` | 1.0.4 | Cache-bust for the sync dashboard assets. |

Rule: after any JS or CSS change, bump the constant that the module's enqueue uses, then hard refresh. A ProductDetailSuite bump also invalidates every cached product data blob. The two top-level numbers have drifted; roadmap R-08 wants one source of truth, not done yet.

## Module registration pattern

```php
final class Module implements ModuleInterface
{
    public function slug(): string  { return 'my-module'; }
    public function label(): string { return __('My Module', 'infoplus-suite'); }
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontend']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        // admin tab only if the slug is in Plugin::$visibleModules:
        add_action('infoplus/module/settings/' . $this->slug(), [$this, 'renderSettings']);
    }
}
```

Add the class to the array in `Plugin::registerModules()`. All modules are instantiated unconditionally, no try/catch, no toggle (R-05, R-11). Asset URLs are built as `INFOPLUS_SUITE_URL . 'src/Modules/<Name>/Assets/<file>'`.

## Where REST routes live

- `infoplus-suite/v1`: only `src/Modules/IkonaIFE/SyncController.php` (`manage_woocommerce`).
- `infoplus/v1`: `registerRestRoutes()` in `UserDashboard`, `UserOrders`, `WooSingleProductAddons`, `ProductDetailSuite`. Plus `ProductDetailSuite::interceptBulkPricesRoute()` on `rest_pre_dispatch`, which answers `GET /infoplus/v1/products/prices` before InfoplusAPI's handler runs.
- `infoplus/v1` is also used by the separate InfoplusAPI plugin (`reference/infoplus_api/`): `products`, `products/synchronization`, `cart/updateCart`, `customerdashboard*` and others. Full table in `PLUGIN-DOCUMENTATION.md` Section 6.
- Grep `register_rest_route` in `src/` and `reference/infoplus_api/` to regenerate the table. Nothing else registers routes.

## InfoplusAPI merge plan

InfoplusAPI (author Vaclav Rezabek, active on production) owns the bridge to editor.infoplus.de: product sync, cart entry from the editor, order hand-off. The Suite's IkonaIFE dashboard depends on it. Decision taken: merge it into the Suite as modules. The plan with the reconciliation list is roadmap item R-13 and `PIPELINE.md` Section 8. Key points: keep the exact `infoplus/v1` paths the editor calls, add authentication (every InfoplusAPI route is public today), decide one sync record, prefix or replace the custom tables `infoplus_products` and `infoplus_synchronizations`, move hardcoded URLs and e-mail addresses into settings. Do R-03 (namespace unification) as part of the merge, not before.

## Secrets

Never commit tokens. The reference copy of InfoplusAPI reads two wp-config constants:

```php
define('INFOPLUS_API_KEY',   '...');  // meant for validate_api_key(); attached to no route yet
define('INFOPLUS_IFE_TOKEN', '...');  // Bearer token for editor.infoplus.de/smp/infoplus/*
```

Production still has literal values inside the plugin; rotate both once production reads the constants. The Suite stores its own editor settings (base URLs, theme ID, a bearer token option that no code reads) in `wp_options` via the EditorLinkSettings tab.

## Writing rules for documentation in this repo

- Every claim must be backed by something read in the code. Write "unverified" where it is not. Do not invent hook names, meta keys, file paths or behaviour.
- Plain English, short sentences. No em dashes or en dashes anywhere; use commas, colons or periods.
- German domain terms stay German: Namensschilder, Sonderkontur, Anfrage, Staffelpreise, Warenkorb.
- Keep existing text where it is still correct. Add rather than rewrite. Mark superseded statements instead of deleting them when they explain history.
- When the code and the docs disagree, list it as a finding (`PLUGIN-DOCUMENTATION.md` Section 16) and do not fix the code in a documentation session.
- Commit messages: `docs: <what>` for documentation, `feat:` / `fix:` for code, one topic per commit. Do not push without being asked.

## Things that bite

- Product pages with more than 200 variations use the deferred path (`_variationsDeferred`), cached 12 h. If a change does not show, bump `ProductDetailSuite\Module::VERSION` or save the product.
- Bulk price tiers are cached 24 h per SKU (`infoplus_prices_*`) with no invalidation (R-14).
- Page paths `/account`, `/log-in`, `/namensschilder`, `/order` are hardcoded in UserDashboard, UserOrders and Custom404.
- Header (83 px) and footer (601 px) heights are hardcoded in Custom404 and the checkout styles.
- Sync buttons in the IFE Sync tab are deliberately disabled in `IkonaIFE/Assets/dashboard.js`.
- `src/Modules/UserDashboard/Assets/` holds nine dead copies of ProductDetailSuite files (R-02). Edit the originals in `ProductDetailSuite/Assets/`.
