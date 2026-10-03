# Catalog import: work order

Target: staging `navajowhite-rhinoceros-679020.hostingersite.com`
Source of truth: `SKU_Namensschilder mit Varianten und Staffelpreisen.xlsx`
Owner key: **[Honza]** = you, **[Claude]** = me, **[check]** = verification step (who runs it is in brackets).

## Phase 0: Preparation

- [x] 0.1 [Honza] Full backup of staging (files + database) in the Hostinger panel. Backup: 2026-09-26 12:50:10 (All-in-One WP Migration, `navajowhite-rhinoceros-679020-hostingersite-com-20260926-125010-fjwitih2bf97`)
- [x] 0.2 [Claude] Read the Excel and build `catalog_mapping.csv` + `REPORT.md`
- [x] 0.3 [Claude] Read the staging site over MCP (read-only): products, attributes, SKUs, pricing rules, VAT settings
- [x] 0.4 [Claude] Diff site vs Excel: 803 site SKUs not in Excel (`site_skus_not_in_excel.txt`), 305 kept, 2,019 new

## Phase 1: Mapping (Excel → WooCommerce attributes)

- [x] 1.1 [Claude] New `build_import.py` to fill every attribute per SKU (model, format, Trägerfarbe, Flächenfarbe, Fenster, Befestigung, Oberfläche, Holzart, Farbe) and assign each SKU to its parent product
- [x] 1.2 [check · Claude] Every SKU has all attributes of its line; no two SKUs in one product have the same attribute combination (otherwise the dropdowns can't tell them apart)
- [ ] 1.3 [Honza] Review `ATTRIBUTE-REVIEW.md`: term names, parent-product assignment, the new `Fenster` attribute, fill values ("ohne Fenster", "ohne Flächendruck", "Standard"), the 2 unpriced SKUs left out, and the open points listed in the chat
- [ ] 1.4 [Claude] Apply your corrections and regenerate the CSV

## Phase 2: Importer (in the Infoplus Suite plugin)

- [x] 2.1 [Claude] New module `CatalogImport`: admin page under WooCommerce → Katalog-Import
      - upload CSV → **dry run** (shows create / update / delete / price-rule counts, nothing written)
      - **apply** in small batches with a progress bar (no timeouts on shared hosting)
      - creates missing attribute terms, variations, prices (net), bulk-price rules
      - deletes variations of managed products that are not in the CSV (incl. those without SKU)
      - replaces the SKU-based bulk-price rules; **never touches Händlerrabatt** (rule 17)
      - switches VAT to "prices entered excl. tax" in the same run
      - fixes per-model default variations that pointed at deleted variations; clears price caches
- [x] 2.2 [check · Claude] PHP lint + local tests (21/21 pass, `test_catalog_import.php`) of CSV parsing and price-rule building
- [x] 2.3 [Claude] Commit `3fb6cb6` on branch `feature/catalog-import`, v0.9.112, zip in `dist/infoplus-suite-0.9.112.zip`
      (also fixes the tier-price lookup: skips deleted WDP rules, shows prices incl. MwSt.)
- [x] 2.4 [Honza] Install the zip on staging (Plugins → Add New → Upload → "Replace current with uploaded").
      Note: staging runs the April 2026 plugin code; the zip also brings it up to the current repo/production code.
- [x] 2.5 [check · Claude] Over MCP: plugin version 0.9.112 active, WooCommerce → Katalog-Import page exists

## Phase 3: Dry run

- [x] 3.1 [Honza] WooCommerce → Katalog-Import → upload **`import.csv`** → Probelauf starten
- [x] 3.2 [check · Claude] Compare dry-run numbers with the expected ones (below) over MCP — all match (plan 2026-09-26 13:09:55)
- [x] 3.3 [Honza] Go / no-go

Expected: 2,322 rows · 21 products · 305 updated · 2,017 created · 0 moved · 3,411 deleted (803 with SKU + 2,608 without SKU) · 1 new attribute (pa_fenster) · 57 price rules replaced · 628 created · rule 17 kept

## Phase 4: Apply

- [x] 4.1 [Honza] Confirm backup from 0.1 exists, then press Apply and leave the tab open until 100 %
- [x] 4.2 [check · Claude] (done: 0 errors, counts per product = CSV, 628 rules, rule 17 ok, VAT net, 20/20 SKUs' tier prices = Excel × 1.19) Over MCP: variation count per product = CSV count; 0 of the 803 SKUs left; every SKU has a price rule; spot-check 20 SKUs' tier prices against the Excel; rule 17 still enabled; `woocommerce_prices_include_tax = no`
- [x] 4.3 [check · Claude] Atlantis + AluContur pages: prices = Excel net × 1.19, tiers and min. qty correct, models/order types/defaults intact. Found 2 template issues → 4.5, 4.6
- [ ] 4.5 [Claude, needs OK] Elementor template 2190: add Fenster + Frontscheibe to the variation dropdowns (customers can't choose them yet)
- [ ] 4.6 [Claude, needs OK] Price note says "zzgl. MwSt." (template text widget + order panel default) → "inkl. MwSt., zzgl. Versandkosten"
- [ ] 4.4 [check · Honza] Walk through the shop yourself: model galleries/images, texts, add to cart, cart total with VAT

## Phase 5: Follow-ups (deferred on purpose)

- [ ] 5.1 [Honza] Order mode per model (Editor / Warenkorb / Anfrage) and delivery days per line
- [ ] 5.2 [Honza] Variation images for new variations / new models
- [ ] 5.3 [Honza] Publish the draft parent products once they look right
- [ ] 5.4 [Honza + Claude] Decide on products outside the Excel: "Alucontur Untereloxaldruck" (2544), "Magnete 3er Set" (13944), "3D Product View" (2853), "Test dummy product pk" (6982), "Exklusiv (Individuelle Form)" (2691) — the importer leaves them alone
- [ ] 5.5 [Honza + Claude] Later Excel updates: rerun `build_mapping.py` then `build_import.py`, upload `import.csv`, dry run, apply
- [ ] 5.6 [Honza + Claude] Merge `feature/catalog-import` into main once staging is signed off; production rollout is a separate work order
- [ ] 5.7 [Claude] Look at `UserOrders::fixEmailHeaderInlineStyles()` (repo/production): it calls `ob_start()` and never closes the buffer, which can garble branded emails
