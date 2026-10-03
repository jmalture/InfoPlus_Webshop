# Data Import Plan: SKUs, Variations and Staffelpreise

> **Status (2026-10-03):** superseded. The import was built and run on staging on 2026-09-26 with option 2a (rebuild from the Excel) and 3a (WDP rules), see `PLUGIN-DOCUMENTATION.md` Section 12a and `import-mapping/WORK-ORDER.md`. Answers to Section 7: the Excel is the complete catalog, site SKUs not in it were deleted; prices are net and shown incl. MwSt; Händlerrabatt stays; order type and delivery days per line are deferred. The text below is kept as the original reasoning.
>
> **Original status:** proposal, 2026-09-18. Nothing has been imported or changed on the site.
> **Inputs:** `Produktübersicht_Namensschilder_Shop.xlsx` (dropdown structure per product family, one messy sheet), `SKU_Namensschilder_mit_Varianten_und_Staffelpreisen.xlsx` (2324 shop SKUs with tier prices). Both live in `C:\Data\Work\Alture\Projekty\Infoplus\`.
> **First artefact (superseded by `import-mapping/build_mapping.py`, not committed):** `tools/excel_to_catalog.py` turns the SKU workbook into `catalog.csv` (one row per shop SKU, clean columns). Generated copy at `C:\Data\Work\Alture\Projekty\Infoplus\catalog.csv`.

## 1. What the Excel contains

| Fact | Value |
|------|-------|
| Shop SKUs (`Nr.+Variantencode`) | 2324, all unique |
| Base article numbers (`Nr.`) | 1952. When an article has window variants (Variantencode 8D, 10D, 12D, HF, HF-DI, ND, OAW, OF, ST) the shop SKU is `Nr-Variantencode` and the plain `Nr` does not exist as its own SKU. |
| Product lines (`Beschreibung`) | 48, for example "Atlantis \| Classic - Namensschild". Maps to one WooCommerce product (family, "Atlantis") plus one `pa_model` term (line, "Classic"). |
| Product groups (`Produktgruppencode`) | 21 (NAT-SHOP, NLI-SHOP, NMA-SHOP, ...). One per family. |
| Tier layouts | 1055 SKUs priced from 1 piece (1/5/10/20/50/100/250/500): Comfort, Exklusiv, Event, DesignStudio, that is the fully printed lines. 1267 SKUs priced from 10 pieces (10/20/50/100/250/500): Classic, Professional, PC and all the small S/M products, that is self labelling. 2 SKUs without any price (`NIA403T00-D9-I`, `NIA403T00-D9-IP`). |
| Distinct price tables | 628 across 2322 priced SKUs. Prices differ by Befestigung (A and K share, P, I, IP differ) and by Flächenfarbe (D1 white, D2 silver), not by Trägerfarbe. |
| SKU code structure | `NIA 4 01 C12 - D1 - A - 12D`: family (NIA Atlantis, NIN Woodline, NIG Materia, NIC AluContur, NIL LaserContur, NIP Papyrus, NNA/NNE/NNS/NNP self-labelling lines, U.. small products), line digit (2 Comfort, 3 Exklusiv, 4 Classic, 5 Professional), format (01 78x32, 04 68x22, 05 68x28), Trägerfarbe (C12 coating silber, C42 coating gold, E12 chrom matt, G10 weiss, G21 lichtgrau, G23 anthrazit, G32 dunkelblau, G60 rot, G80 schwarz, T00 klar, T10 weiss transluzent, T30 blau transparent, T60 rot transparent), Flächenfarbe or print (D1 weiss, D2 silber, D9, E1 Untereloxaldruck, L1 Lasergravur), Befestigung (A Anstecknadel, P Promag, I Infomag, IP Infomag Plus, K Kombi Clip, DP Duomag Plus, M1, AC, MC, MCF), window variant. The `Suchbegriff` column spells the same out in words but with inconsistent separators. |

The Produktübersicht sheet is the legend for the shop side: per family it lists which lines exist (SB, LD, KB, SK), the dropdown values for Format, Oberfläche or Farbe, Fenster and Befestigung, the print indicator codes (-D1-, -E1-, -L1-), and the mapping from marketing names to lines (Komplette Selbstbeschriftung = PC, Logo gedruckt / Name zur Selbstbeschriftung = Classic, Logo gedruckt & Sonderkontur = Professional, Logo / Name gedruckt = Comfort, Logo / Name gedruckt & Sonderkontur = Exklusiv).

## 2. What the site contains today (navajowhite-rhinoceros-679020.hostingersite.com)

| Fact | Value |
|------|-------|
| Products | 26. Published: Atlantis Namensschild (1398 variations), Alucontur Namensschild (140). Draft: 24, among them Woodline (1120), Materia (560), LaserContur (272), Papyrus (60) and 18 small ones. |
| Variations | 3727. Only 1108 have a SKU. 2619 have no SKU at all (all drafts, plus 430 Atlantis rows). |
| Prices on variations | 2340 variations have `_price` = 1 (placeholder), 1228 have none. Only Alucontur has real base prices (6.35). |
| Excel SKUs present on the site | 305 of 2324 (Atlantis Classic 146, Atlantis Professional 118, AluContur 41). 2019 Excel SKUs have no variation. |
| Site SKUs not in the Excel | 803: Atlantis 8D and 10D window variants (446), NNA (180), Alucontur ST and NU (99), legacy NIA200-99.x (38) and others. |
| Attribute model | `pa_model` is used for two different things at once: production lines (Druck, Lasergravur, Druck & Sonderkontur, Digitaldruck Namensschilder, ...) and order modes (Logo gedruckt / Name zur Selbstbeschriftung, Komplette Selbstbeschriftung, ...). Atlantis uses five values (Druck, Druck & Sonderkontur, Komplette Selbstbeschriftung, Logo gedruckt..., Logo gedruckt & Sonderkontur...) which correspond to Comfort, Exklusiv, PC, Classic, Professional. Format, Befestigung, Trägerfarbe, Flächenfarbe, Holzart, Farbe, Oberfläche exist as taxonomies with the values the Produktübersicht lists. |
| Pricing rules (Advanced Dynamic Pricing 4.12.0) | 64 live rules (63 enabled), 53 soft deleted. All are "bulk, fixed price per item, filter by SKU list". Most were created 2026-03-21 to 03-23 with the plugin's own "Import rules (CSV)" tool (title pattern `<SKU> - Bulk`, the tool merges identical price tables into one rule and joins SKUs). They cover about 770 SKUs. |
| Are the current rule prices the new Excel prices? | No. Of the 264 SKUs that appear in both a live rule and the Excel, 0 have the same tiers. Example `NIA501T00-D2-A-12D`: rule 13.05 / 9.75 / 7.25 / 5.15 / 4.62 / 4.23, Excel 14.60 / 10.40 / 7.80 / 5.40 / 4.85 / 4.39. The Excel is a new price list. |

## 3. Who consumes the prices (from the code, see PIPELINE.md)

1. **Product page widgets**: `ProductDetailSuite::getBulkPricesBySku()` reads `wp_wdp_rules` directly, keyed by SKU, and serves `GET /infoplus/v1/products/prices`. Cached 24 h per SKU.
2. **Cart**: the WDP plugin applies the fixed tier price for `buyable` products. Editor products get their price from the editor (`ife_price`).
3. **Editor sync** (InfoplusAPI `get_variants_for_product_internal_by_id`): reads `wp_wdp_rules` per SKU and pushes the tiers as `QuantityV1..n` modifiers to editor.infoplus.de. Sync buttons are currently disabled.
4. **Model cards "ab X €"**: `buildModelsData()` uses the variation `_price`, which is 1 today, so the cards are wrong until base prices are set.

So there are three readers of the same table and one writer (the WDP admin UI or its CSV import). Whatever we do, all three readers must see the new data.

## 4. The real task

The Excel is not "new prices for existing products". It is the intended shop catalog: which SKUs exist, which attributes they carry, and what they cost. Three quarters of it has no counterpart on the site, and the site's draft products have attribute combinations but no SKUs. Prices are keyed by SKU, so the SKU on the variation must exist before any price import can do anything.

Steps, in dependency order:

1. **Decode the SKU into attributes** (mapping table, one row per code segment per family). The Excel plus the Produktübersicht give about 90 percent of it. The rest needs the client (Section 7).
2. **Create or align the variations**: for every Excel SKU there must be exactly one variation with that SKU, the right parent product, the right attribute terms and the right base price (tier 1, or tier 10 for self labelling lines).
3. **Load the tier prices** into whatever the three readers use.
4. **Set per line metadata** the Suite needs: order type per `pa_model` term (`editable`, `requestable`, `buyable`), minimum quantity (already handled by the first tier `from`), delivery days.
5. **Repeat** next time the client sends a new Excel. This is the reason to script it rather than click.

## 5. Options for step 2 (variations)

| Option | How | Pros | Cons |
|--------|-----|------|------|
| **2a. Rebuild from the Excel** | Script generates a WooCommerce product CSV (parent by SKU, one row per variation with `Attribute n name/value`, `SKU`, `Regular price`, `Weight`, custom `Meta:` columns). Delete the current draft variations, import with the core WooCommerce importer or the installed WebToffee importer. Matching by SKU makes re-runs idempotent. | One script, one file, standard tooling, repeatable. Removes the 2619 SKU-less rows. | Atlantis is live: its 1398 variations must be replaced carefully (the 305 matching SKUs keep their IDs if we update by SKU; the 803 site-only SKUs need a decision: delete, or keep unpriced). Draft products lose per-variation edits made by hand (images are on term meta, not variations, so those survive). |
| **2b. Assign SKUs to existing variations** | Script matches existing variations by attribute combination and writes the SKU (WP-CLI `wc product_variation update` through the site MCP, or a small admin tool in the Suite). | Keeps every existing variation ID. | Only works where the site already has the right combination; 2019 SKUs are missing anyway, so 2a is needed on top. Two code paths instead of one. |

Recommendation: **2a**, with the site-only Atlantis SKUs (8D, 10D, NNA, legacy) kept but set to "not purchasable" until the client confirms whether they belong in the shop.

## 6. Options for step 3 (tier prices)

| Option | How | Pros | Cons |
|--------|-----|------|------|
| **3a. Keep WDP, feed it by CSV** | Script writes the WDP "Import rules (CSV)" format (header `FilterType,Sku` / `DiscountType,FixedCost` / `Filter,DiscountedPrice,FromQty,ToQty`, one block per SKU). Upload in WDP → Tools → Import rules (CSV) with option "reset" (delete all rules, then import). WDP merges identical tables itself. | No code. All three readers keep working unchanged. Already proven on this site in March. | 628 rules (WDP UI becomes unusable for manual edits, which is fine if the Excel is the master). Rule titles truncated to 20 characters. Cart performance with 628 SKU rules is unverified. The 24 h price cache in the Suite must be cleared after each import (R-14). "Reset" also deletes the rules that are not bulk rules (rule 17 "Händlerrabatt" has no SKU filter and looks like a role discount; it would have to be re-created or excluded). |
| **3b. Tiers on the variation** | The same product CSV from 2a carries a `Meta: _infoplus_bulk_prices` column (JSON `[{"from":1,"to":4,"value":13.55},...]`). The Suite reads the meta first and falls back to `wp_wdp_rules`; a small hook in the Suite sets the cart unit price from the tiers (`woocommerce_before_calculate_totals`); the merged InfoplusAPI sync reads the same meta. WDP stays only for role discounts or is removed. | One file, one import, no rule limit, tiers visible on the variation edit screen, no SKU-to-rule mapping to maintain. Fits the planned InfoplusAPI merge. | Code: about 150 lines in the Suite (reader, cart hook, cache clear) plus the sync change. Needs a test that cart totals match WDP's behaviour (fixed price per item by quantity). |

Recommendation: **3a now, 3b after the InfoplusAPI merge**. 3a gets the new prices live with zero code once the variations exist. 3b is the durable design and removes a plugin, but should not be mixed into the same change as the catalog rebuild.

## 7. Questions for the client (or for Honza)

1. **Scope of the Excel.** Is it the complete intended catalog? What happens to site SKUs that are not in it (Atlantis 8D and 10D window variants, NNA self-labelling Atlantis, Alucontur ST and NU, the NIA200-99.x legacy rows)? Delete, hide, or keep with old prices?
2. **Variantencode legend.** HF, HF-DI, ND, OAW, OF, ST: which dropdown value (Fenster or other) does each one correspond to? 8D, 10D, 12D are presumably the 8, 10 and 12 mm window. The Produktübersicht mentions "8 mm durchgehend / halbe Fläche / voll", so HF is probably "halbe Fläche"; ND, OF, ST, OAW are unknown.
3. **Befestigung codes.** A Anstecknadel, P Promag, I Infomag, IP Infomag Plus, K Kombi Clip, DP Duomag Plus are readable from the Suchbegriff. M1, AC, MC, MCF, and the Alucontur codes on the site (D, DP, MB, S, NU) need confirmation.
4. **D9.** 94 Atlantis SKUs carry `-D9-` with Suchbegriff "SONDER". Special colour on request? Should they be in the shop (they have prices)?
5. **Net or gross.** Are the Excel prices net (B2B) or including 19 percent MwSt? WooCommerce tax settings decide how WDP fixed prices are interpreted. The current rules and the Excel differ by roughly 10 percent, which does not match a tax difference, so they are simply new prices, but the base must be confirmed.
6. **Order type per line.** Which lines are `editable` (editor), which `buyable` (self labelling, cart), which `requestable` (Anfrage only)? The Produktübersicht marks lines SB, LD, KB, SK; a mapping to the three order types is needed once.
7. **Delivery days per line** (`_infoplus_delivery_days`) if they differ from the default 15.
8. **WDP rule 17 "Händlerrabatt"**: is it in use? A "reset" import would delete it.
9. **Attribute clean-up.** Should `pa_model` be split so that the production line (Druck, Lasergravur, Untereloxaldruck) and the order mode (Classic, Comfort, ...) are separate attributes, or keep the current five-value scheme per family? This affects the SKU mapping table and the model selector widget.

## 8. Proposed build (after the answers)

1. `tools/excel_to_catalog.py` (done): Excel → `catalog.csv`.
2. `tools/mapping/*.csv`: hand-maintained code legends (family → WooCommerce product, line digit → `pa_model`, format code → `pa_format`, colour code → `pa_tragerfarbe` or `pa_farbe` or `pa_holzart`, Flächenfarbe code, Befestigung code, Variantencode → `pa_frontscheibe` or window attribute, line → order type and delivery days).
3. `tools/catalog_to_woocommerce_csv.py`: `catalog.csv` + mappings → WooCommerce product import CSV (variations with SKU, attributes, base price, weight, `Meta: _infoplus_bulk_prices`). Also prints a report of unmapped codes so nothing is imported silently wrong.
4. `tools/catalog_to_wdp_csv.py`: `catalog.csv` → WDP "Import rules (CSV)" file (option 3a).
5. Dry run on a staging copy (Hostinger has staging), then production: import variations, import WDP rules with "reset", clear the Suite price transients, spot check five products in the browser.
6. Later (3b): Suite reads `_infoplus_bulk_prices`, cart hook, sync reads meta, remove WDP.

Effort estimate: mapping tables and both generators about one day once the legend questions are answered, plus half a day for the staging run and checks. 3b is a separate one to two days.
