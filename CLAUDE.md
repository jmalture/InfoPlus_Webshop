# Infoplus Webshop: project brief

Monorepo for the Infoplus nameplate shop (Namensschilder): the Infoplus Suite plugin, the classic PHP theme "infoplus" that replaces Elementor, docs, the Elementor export and tools. WordPress, WooCommerce (HPOS), Germanized, PHP 7.4+, jQuery, WordPress REST API. German customer-facing UI (formal Sie). Owner: JM (Alture s.r.o.). Read this first, then `docs/suite/PLUGIN-DOCUMENTATION.md` for the plugin modules, `docs/suite/PIPELINE.md` for how the plugins fit together, and `docs/theme/README.md`, `docs/theme/DESIGN.md`, `docs/theme/ACCOUNT.md` for the theme.

## State of the repo

- Created 2026-10-23 by merging two histories: the Suite (formerly MrBeardman/Infoplus-Suite, 45 commits) and the theme (formerly standalone, 40 commits). Paths were rewritten into this layout, so all commit hashes changed. Old hash to new hash: `docs/history/commit-map-suite.txt` and `docs/history/commit-map-theme.txt`. Hashes quoted in older docs are the old ones; look them up there.
- `main`: current line, deployed on staging (navajowhite-rhinoceros-679020.hostingersite.com) as Suite 0.10.35 + theme 0.5.4. Elementor and Elementor Pro are deleted on staging (2026-10-23), as are 20 other unused plugins. The Suite no longer contains Elementor widgets or dynamic tags (0.10.35); production still needs them until rollout.
- `production`: the Suite as production runs it (old `main`, production-identical at old `f01aca6`, new `1b24e5c`, 2026-09-17, plus documentation commits). Production still uses Elementor and the old Suite line. Reference until rollout.
- `feature/catalog-import` (0.9.113): CatalogImport module and the `tools/import-mapping/` pipeline. Already contained in `main`.
- Tags `archive/*`: merged or retired branches (fix/order-details-ownership, fix/infoplusapi-customerdashboard-auth, chore/sync-staging, claude/admiring-archimedes, an older unrelated line from March 2026).
- Production history from April to September 2026 was synced in one commit (old `3179bb5`, new `6dee0ee`), so `git log` does not explain individual features from that period. `docs/suite/PLUGIN-DOCUMENTATION.md` Section 18 summarises them.
- A WooCommerce REST key and secret that were committed in `reference/infoplus_api/entities/Products.php` were redacted from the whole history during the move (`ck_REDACTED_ROTATE_THIS_KEY`). The real key still works on production and still sits in the old repository: rotate it.
- No CI or tests. Deploy = ZIP upload in wp-admin, ZIPs built with `tools/build-zips.sh`. Before uploading, compare staging with the repo: files may have been changed over FTP.

## Repo layout

```
wp-content/plugins/infoplus-suite/   the Suite plugin (deployed as folder "Infoplus Suite" on staging)
  infoplus-suite.php                 entry point, plugin header and INFOPLUS_SUITE_VERSION (0.10.35)
  src/Plugin.php                     orchestrator, Plugin::VERSION (= INFOPLUS_SUITE_VERSION on main), registers the modules
  src/Admin/Dashboard.php            WP Admin menu "IKONA IFE" (slug infoplus-suite), module tabs
  src/Contracts/ModuleInterface.php  slug(), label(), register()
  src/Modules/<Name>/Module.php      one module each, see below
  src/Modules/<Name>/Assets/         JS and CSS, enqueued by the module itself
  (src/Modules/<Name>/Widgets/       Elementor widgets: removed in 0.10.35, still on the production branch)
  src/View.php, templates/           component renderer; the theme overrides templates in infoplus-suite/{component}.php
wp-content/themes/infoplus/          the theme (inc/, template-parts/, woocommerce/, assets/, acf-json/)
wp-content/mu-plugins/               theme preview helper (only needed while another theme is active)
reference/                           NOT deployed. InfoplusAPI live copy and the two legacy plugins. See reference/README.md
legacy/                              NOT deployed. Elementor export of staging (2026-10-20). See legacy/README.md
docs/suite/                          PLUGIN-DOCUMENTATION.md (module reference, REST routes, meta keys, findings in Section 16), PIPELINE.md, UPGRADE-ROADMAP.md, historical FEATURE-*.md and DATA-IMPORT-PLAN.md
docs/theme/                          README.md, DESIGN.md (brand, SDX-inspired rules, B2B UX), ACCOUNT.md (account routing, inventory)
docs/history/                        old to new commit maps from the move
docs/LAUNCH.md                       production rollout: decisions, checklist, steps, smoke test, cache, rollback
tools/build-acf-json.py              builds the theme field groups (never edit acf-json by hand)
tools/build-zips.sh                  builds the two upload ZIPs
tools/import-mapping/                Excel -> catalog_mapping.csv -> import.csv, plus WORK-ORDER.md
```

Paths in the sections below (`src/...`, `templates/...`) are relative to `wp-content/plugins/infoplus-suite/`.

Modules, in registration order: `WooSingleProductAddons`, `WooProductVariationFields`, `EditorLinkSettings` (admin tab), `IkonaIFE` (admin tab), `ElementorUserAvatar`, `UserDashboard`, `UserOrders`, `ProductDetailSuite`, `Custom404`. On `feature/catalog-import` also `CatalogImport` (WooCommerce → Katalog-Import, last). On `main` also `Inquiry` and `Finder` (Namensschild-Berater data, `docs/suite/PLUGIN-DOCUMENTATION.md` Section 22).

## Two version numbers and cache busting

| Where | Value | Meaning |
|-------|-------|---------|
| `infoplus-suite.php` header and `INFOPLUS_SUITE_VERSION` | 0.10.35 (header 0.9.111 on `production`) | Since 0.10.33 one release number: the header is what WordPress shows, the constant (next to it, same file) is what the code uses. Bump both together. `tools/build-zips.sh` refuses to build when they differ. |
| `Plugin::VERSION` in `src/Plugin.php` | `= INFOPLUS_SUITE_VERSION` (0.10.25 on `production`) | Cache-bust string for assets of WooSingleProductAddons, UserDashboard, UserOrders and the product meta box. Up to 0.10.32 it was a separate literal and the header lagged behind at 0.9.113. |
| `ProductDetailSuite\Module::VERSION` | 1.1.1 (1.1.0 on `production`) | Cache-bust for ProductDetailSuite assets and part of the transient key `infoplus_pd_{product_id}_{VERSION}`. |
| `IkonaIFE\Module::VERSION` | 1.0.4 | Cache-bust for the sync dashboard assets. |
| Theme `style.css` Version and `IP_VERSION` in `functions.php` | 0.5.4 | Theme release number; theme assets are versioned with it plus the file time, so every upload busts caches. |

Rule: after any JS or CSS change, bump the constant that the module's enqueue uses, then hard refresh. A ProductDetailSuite bump also invalidates every cached product data blob. Release rule for the Suite: every ZIP that goes to a site gets a new number in `infoplus-suite.php` (header and constant), so the Plugins screen shows which build is installed. The two module constants are still separate (R-08 partly done).

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
- `infoplus/v1` is also used by the separate InfoplusAPI plugin (`reference/infoplus_api/`): `products`, `products/synchronization`, `cart/updateCart`, `customerdashboard*` and others. Full table in `docs/suite/PLUGIN-DOCUMENTATION.md` Section 6.
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
- When the code and the docs disagree, list it as a finding (`docs/suite/PLUGIN-DOCUMENTATION.md` Section 16) and do not fix the code in a documentation session.
- Commit messages: `docs: <what>` for documentation, `feat:` / `fix:` for code, one topic per commit. Do not push without being asked.

## Things that bite

- Product pages with more than 200 variations use the deferred path (`_variationsDeferred`), cached 12 h. If a change does not show, bump `ProductDetailSuite\Module::VERSION` or save the product.
- Bulk price tiers are cached 24 h per SKU (`infoplus_prices_*`) with no invalidation (R-14).
- Page paths `/account`, `/log-in`, `/namensschilder`, `/order` are hardcoded in UserDashboard, UserOrders and Custom404.
- Header (83 px) and footer (601 px) heights are hardcoded in Custom404 and the checkout styles.
- Sync buttons in the IFE Sync tab are deliberately disabled in `IkonaIFE/Assets/dashboard.js`.
- `src/Modules/UserDashboard/Assets/` held nine dead copies of ProductDetailSuite files (R-02). Deleted on `main` in 0.10.35; the `production` branch still has them. Edit the originals in `ProductDetailSuite/Assets/`.
- Theme speed: never call `wc_product_class()` for variable products in a loop or on the product page (it loads every variation price). Plugin assets the theme does not use are dropped in the theme's `inc/performance.php`. Measurements in `docs/theme/README.md` "Performance".
