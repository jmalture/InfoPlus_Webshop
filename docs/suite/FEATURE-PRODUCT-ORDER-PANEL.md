# Feature: ProductOrderPanel — Unified Product Order Widget

## Summary

Create a single Elementor widget ("ProductOrderPanel") that replaces the current scattered product ordering elements with one unified, fully controlled block. It contains: product info fields (min quantity, delivery time), bulk pricing table, quantity selector with live price calculation, and dynamic action buttons — all driven by model selection and variation state via the existing `product-state.js` event bus.

This widget also fixes two existing bugs:
1. Editor button is clickable before all variation options are selected
2. "Warenkorb-Button nicht gefunden" error when clicking add-to-cart

## What This Widget Replaces

| Current Element | Source | Replaced By |
|----------------|--------|-------------|
| Mindestanzahl / Arbeitstage info | ACF fields + custom HTML | Widget section: Product Info (own post_meta) |
| Bulk pricing table | `elementor-bulk-calculator.js` + HTML | Widget section: Bulk Price Table |
| Quantity selector (−/+) | WooCommerce or custom | Widget section: Quantity Selector |
| Einzelpreis / Gesamtpreis | `elementor-bulk-calculator.js` | Widget section: Price Display |
| "Produkt gestalten" button | Elementor button + `variation-editor-link.js` | Widget section: Action Buttons |
| "Individuelle gewünscht?" button | Elementor button | Widget section: Action Buttons |
| "In den Warenkorb" button | WooCommerce/custom | Widget section: Action Buttons |

After implementation, the old scattered elements can be removed from the Elementor layout and replaced with this single widget.

## Widget Sections (top to bottom)

### 1. Product Info

Two info lines with icons:
```
🟢 Mindestanzahl: {min_quantity} Stück
🕐 max. {delivery_days} Arbeitstage
```

- Values from new custom post_meta fields (see below)
- Hidden if no values are set on the product
- Each line independently hideable (if only one field is filled, show only that one)

### 2. Bulk Price Table

A 2-column grid (or 3-column on wider screens) showing price breaks:
```
ab 10  Stück  38,95 €    ab 49   Stück  31,25 €
bis 99 Stück  24,45 €    ab 249  Stück  18,05 €
ab 499 Stück  16,28 €    ab 500+ Stück  14,92 €
```

- Fetched from existing API: `GET /infoplus/v1/products/prices?sku={variation_sku}`
- Updates when `variation:found` fires (new SKU → new prices)
- Hidden until a valid variation is found
- Shows loading state while fetching
- If API returns empty/error → hide the table section

### 3. Quantity Selector

Horizontal layout: `[ − ]  [ input ]  [ + ]`

- Default value: product's `min_quantity` meta (or 1 if not set)
- Minimum value: product's `min_quantity` meta (or 1)
- Step: 1 (or configurable via Elementor control)
- Plus/minus buttons increment/decrement
- Manual input allowed (validated on blur — clamp to min)
- Quantity changes trigger live price recalculation (section 4)

### 4. Price Display

```
Einzelpreis          38,95 €
Gesamtpreis*        389,50 €
Alle Preise zzgl. MwSt., zzgl. Versandkosten
```

- **Einzelpreis** (unit price): from bulk pricing table, the matching tier for current quantity
- **Gesamtpreis** (total): Einzelpreis × quantity
- Updates live when quantity changes or when new bulk prices are fetched
- Prices formatted in German locale (comma decimal, dot thousands)
- The disclaimer text "Alle Preise zzgl. MwSt., zzgl. Versandkosten" is static
- Hidden until a valid variation with prices is available

### 5. Action Buttons

Three buttons, visibility controlled by the selected model's `order_type` (from FEATURE-ORDER-TYPE-BUTTONS.md):

| Button | Text | Action | Visible When | Enabled When |
|--------|------|--------|-------------|-------------|
| Editor | "Produkt gestalten →" | Navigate to editor URL | `order_type === 'editable'` | Valid variation selected (all options picked + variation exists) |
| Anfrage | "Individuelle gewünscht? ↓" | Smooth scroll to `#anfrage` | `order_type === 'editable'` OR `'requestable'` | Model with editable/requestable type is selected (no full selection needed) |
| Cart | "In den Warenkorb →" | AJAX add-to-cart | `order_type === 'buyable'` | Valid variation selected (all options picked + variation exists) |

**Disabled state:** button visible, opacity 0.5, not clickable, no error text yet.

**Invalid combination** (all options picked but no matching variation):
- Editor/Cart buttons: visible, disabled, show "Kombination nicht verfügbar" below button
- Anfrage button: unaffected

**Button styling:** Match current design — editor button has pink (#e91e8c) background, anfrage has outline/secondary style, cart button has pink background.

## New Product Meta Fields

Add to a new meta box or to an existing WooCommerce product panel:

| Meta Key | Label | Type | Default | Description |
|----------|-------|------|---------|-------------|
| `_infoplus_min_quantity` | Mindestanzahl | number | 1 | Minimum order quantity |
| `_infoplus_delivery_days` | Lieferzeit (Arbeitstage) | number | *(empty)* | Maximum delivery days |

### Where to add in admin

Option A: Add a small "Infoplus: Product Settings" meta box on the product edit page (simple, separate from attribute overrides).

Option B: Add fields to the existing "IBE/IFE" WooCommerce product data tab (already created by `WooProductVariationFields`).

**Use Option B** — add the two fields to the existing IBE/IFE tab in `WooProductVariationFields/Module.php`. These are simple product-level fields, not per-variation.

## Technical Context

### Files to CREATE

| File | Purpose |
|------|---------|
| `src/Modules/ProductDetailSuite/Widgets/ProductOrderPanel.php` | Elementor widget: renders all 5 sections, Elementor controls for styling |
| `src/Modules/ProductDetailSuite/Assets/product-order-panel.js` | Frontend JS: event listeners, API calls, quantity logic, button logic, AJAX cart |
| `src/Modules/ProductDetailSuite/Assets/product-order-panel.css` | Widget styles |

### Files to MODIFY

| File | Change |
|------|--------|
| `src/Modules/ProductDetailSuite/Module.php` | Register new widget, enqueue new assets |
| `src/Modules/WooProductVariationFields/Module.php` | Add `_infoplus_min_quantity` and `_infoplus_delivery_days` fields to IBE/IFE tab |
| `src/Modules/ProductDetailSuite/Module.php` | Add `min_quantity` and `delivery_days` to `window.infoplusProductData` |

### Files that become DEPRECATED (don't delete yet, but widget replaces them)

| File | Replaced By |
|------|-------------|
| `WooSingleProductAddons/Assets/elementor-bulk-calculator.js` | ProductOrderPanel widget (bulk table + price calc) |
| `WooSingleProductAddons/Assets/variation-editor-link.js` | ProductOrderPanel widget (button logic) |

### Data dependencies

The widget reads from `window.infoplusProductData`:
```js
{
  id: 123,
  // ... existing fields
  min_quantity: 10,          // NEW — from _infoplus_min_quantity
  delivery_days: 15,         // NEW — from _infoplus_delivery_days
  models: [
    {
      slug: "digitaldruck",
      order_type: "editable",  // From FEATURE-ORDER-TYPE-BUTTONS
      // ...
    }
  ]
}
```

### Event bus integration (product-state.js)

Widget listens to:
- `model:changed` → update button visibility based on `order_type`, reset price table
- `variation:found` → fetch bulk prices for new SKU, enable editor/cart buttons, update prices
- `attribute:changed` → if variation becomes invalid (partial selection), disable editor/cart buttons

Widget does NOT fire events — it's a consumer only.

### AJAX Add-to-Cart

When cart button is clicked:
```js
const response = await fetch(woocommerceParams.ajax_url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
        action: 'woocommerce_add_to_cart',  // or use REST: POST /wc/store/v1/cart/add-item
        product_id: productId,
        variation_id: variationId,
        quantity: currentQuantity,
    })
});
```

Alternative — use WooCommerce Store API (more reliable):
```js
const response = await fetch('/wp-json/wc/store/v1/cart/add-item', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Nonce': wcStoreApiNonce
    },
    body: JSON.stringify({
        id: variationId,
        quantity: currentQuantity
    })
});
```

On success: show brief "✓ Hinzugefügt" feedback on the button (green flash), then reset. Optionally update mini-cart count if visible.

On error: show error message below button.

### Editor URL

Built from `window.infoplusEditorSettings` — same as current `variation-editor-link.js` does. The widget takes over this logic:
```js
const editorUrl = `${settings.baseUrl}/${settings.themeId}/${productUuid}?cartEntryPath=${settings.cartEntryPath}&backPath=${encodeURIComponent(settings.backPath)}`;
```

## Elementor Widget Controls

### Style tab controls (for the entire widget)

- **Spacing:** gap between sections
- **Button colors:** primary (editor/cart), secondary (anfrage) — with defaults matching brand
- **Font sizes:** info text, price text, table text
- **Border radius:** buttons, quantity input
- **Table columns:** 2 or 3 column layout for bulk prices

### Content tab controls

- **Show Product Info:** toggle (default yes)
- **Show Bulk Table:** toggle (default yes)
- **Price suffix text:** editable (default "Alle Preise zzgl. MwSt., zzgl. Versandkosten")
- **Editor button text:** editable (default "Produkt gestalten →")
- **Anfrage button text:** editable (default "Individuelle gewünscht? ↓")
- **Cart button text:** editable (default "In den Warenkorb →")

## Implementation Notes

### Widget HTML structure

```html
<div class="infoplus-order-panel" data-product-id="123">
    
    <!-- Section 1: Product Info -->
    <div class="infoplus-order-panel__info">
        <div class="infoplus-order-panel__info-item">
            <span class="info-icon info-icon--qty">🟢</span>
            <span>Mindestanzahl: <strong>10 Stück</strong></span>
        </div>
        <div class="infoplus-order-panel__info-item">
            <span class="info-icon info-icon--delivery">🕐</span>
            <span>max. <strong>15 Arbeitstage</strong></span>
        </div>
    </div>

    <!-- Section 2: Bulk Price Table -->
    <div class="infoplus-order-panel__bulk" style="display:none;">
        <div class="infoplus-order-panel__bulk-grid">
            <!-- Populated by JS -->
        </div>
    </div>

    <!-- Section 3: Quantity Selector -->
    <div class="infoplus-order-panel__quantity">
        <button class="qty-btn qty-minus">−</button>
        <input type="number" class="qty-input" value="10" min="1" />
        <button class="qty-btn qty-plus">+</button>
    </div>

    <!-- Section 4: Price Display -->
    <div class="infoplus-order-panel__prices" style="display:none;">
        <div class="price-row">
            <span>Einzelpreis</span>
            <span class="price-value price-unit">38,95 €</span>
        </div>
        <div class="price-row price-row--total">
            <span>Gesamtpreis*</span>
            <span class="price-value price-total">389,50 €</span>
        </div>
        <div class="price-suffix">Alle Preise zzgl. MwSt., zzgl. Versandkosten</div>
    </div>

    <!-- Section 5: Action Buttons -->
    <div class="infoplus-order-panel__actions">
        <a href="#" class="infoplus-btn infoplus-btn--editor infoplus-btn--disabled" data-action="editor">
            Produkt gestalten →
        </a>
        <a href="#anfrage" class="infoplus-btn infoplus-btn--anfrage infoplus-btn--hidden" data-action="anfrage">
            Individuelle gewünscht? ↓
        </a>
        <button class="infoplus-btn infoplus-btn--cart infoplus-btn--hidden" data-action="cart">
            In den Warenkorb →
        </button>
        <div class="infoplus-btn-error" style="display:none;">Kombination nicht verfügbar</div>
    </div>
</div>
```

### Price formatting helper

```js
function formatPrice(amount) {
    return new Intl.NumberFormat('de-DE', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount);
}
```

### Quantity → price tier matching

From the bulk prices array (sorted by min_quantity), find the tier where `currentQty >= tier.min_quantity`. Use the last matching tier's price.

### Bug fixes included

1. **Editor button not disabled:** The widget owns the button completely — it starts as `infoplus-btn--disabled` and only removes that class when `variation:found` fires with a valid variation. No external JS can interfere.

2. **"Warenkorb-Button nicht gefunden" error:** The old `variation-editor-link.js` was looking for a WooCommerce cart button that doesn't exist on the page. The new widget handles cart submission internally via AJAX — no dependency on WooCommerce's native button.

### Deprecation plan for old scripts

After the widget is placed in Elementor and confirmed working:
1. Remove the old Elementor buttons from the page layout
2. Remove the old bulk calculator HTML element
3. In `WooSingleProductAddons/Module.php`, stop enqueueing `elementor-bulk-calculator.js` and `variation-editor-link.js` on pages that use the new widget (or remove entirely if all products use it)
4. This is a separate cleanup step — not part of this feature implementation

## Acceptance Criteria

1. **Admin:** `_infoplus_min_quantity` and `_infoplus_delivery_days` fields visible on IBE/IFE product tab
2. **Admin:** Values save and load correctly
3. **Widget:** Registers in Elementor under "Infoplus Product" category
4. **Widget:** Shows product info (min qty, delivery days) from product meta
5. **Widget:** Bulk price table loads from API when variation is found
6. **Widget:** Bulk table updates when variation changes (different SKU)
7. **Widget:** Quantity selector works (−/+, manual input, respects min)
8. **Widget:** Einzelpreis updates based on quantity tier
9. **Widget:** Gesamtpreis = Einzelpreis × quantity, updates live
10. **Widget:** Editor button visible only for editable models, disabled until valid variation
11. **Widget:** Anfrage button visible for editable + requestable models, enabled on model select
12. **Widget:** Cart button visible only for buyable models, disabled until valid variation
13. **Widget:** Cart button adds to cart via AJAX with success/error feedback
14. **Widget:** Editor button navigates to correct editor URL
15. **Widget:** Anfrage button smooth-scrolls to #anfrage
16. **Widget:** Invalid variation combo → disabled buttons + "Kombination nicht verfügbar"
17. **Widget:** All prices formatted in German locale (comma decimals)
18. **Widget:** Elementor editor mode shows all sections for preview
19. **Bug fix:** Editor button NOT clickable before all options selected
20. **Bug fix:** No "Warenkorb-Button nicht gefunden" error
