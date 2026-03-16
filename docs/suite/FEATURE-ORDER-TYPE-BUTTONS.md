# Feature: Model Order Type — Dynamic Button Visibility & Behavior

## Summary

Add a per-product, per-model "Order Type" dropdown to the attribute overrides admin panel. This controls which action buttons are visible on the product detail page and what they do. The three order types are: **editable** (opens IFE editor — default), **requestable** (scrolls to inquiry form), and **buyable** (adds to cart). Editable models also show the requestable button as a secondary action.

## Order Types

| Type | Value | Primary Button | Secondary Button | Description |
|------|-------|---------------|-----------------|-------------|
| Editable | `editable` | "Produkt gestalten →" → links to editor (`#editor`) | "Individuelle gewünscht? ↓" → scrolls to `#anfrage` | Product can be customized in IFE editor AND requested |
| Requestable | `requestable` | "Individuelle gewünscht? ↓" → scrolls to `#anfrage` | *(none)* | Product can only be requested via inquiry form |
| Buyable | `buyable` | "In den Warenkorb" → adds to cart | *(none)* | Standard WooCommerce add-to-cart |

## Current Behavior

- Two buttons exist on product pages: "Produkt gestalten" (editor link) and "Individuelle gewünscht?" (inquiry)
- Both are always visible regardless of model
- No per-model control over which buttons show
- Editor link is dynamically built from `_ife_uuid` and EditorLinkSettings
- Add-to-cart functionality exists in WooCommerce but is not tied to model selection

## Desired Behavior

### Admin: Order Type Dropdown

- New dropdown field in the per-product attribute override panel (`ProductAttributeImages.php`)
- Only shown for `pa_model` terms (same as gallery — other attributes don't get this)
- Placed at the TOP of each model's term card (before Image, Tooltip, Modal Content)
- Options: "Editable (Editor)" (default), "Requestable (Anfrage)", "Buyable (Warenkorb)"
- Stored as: `_infoplus_attr_order_type_{attr}_{term}` → value: `editable` | `requestable` | `buyable`
- Default value when no selection is saved: `editable`

### Frontend: Button Visibility Rules

**Before any model is selected (initial page load with default model):**
- Read the default model's order type
- Show buttons according to that model's type (see table above)

**When model is selected in ModelSelector:**
- Read the selected model's `order_type` from `window.infoplusProductData.models`
- Immediately update button visibility:
  - **Editable:** Show editor button (disabled until valid variation) + show requestable button (enabled immediately)
  - **Requestable:** Show only requestable button (enabled immediately)
  - **Buyable:** Show only add-to-cart button (disabled until valid variation)

**Button enabled/disabled states:**

| Button | Enabled when... |
|--------|----------------|
| Editor ("Produkt gestalten →") | All variation options are picked AND a valid variation exists |
| Requestable ("Individuelle gewünscht? ↓") | A model with `editable` or `requestable` type is selected (no need for all options picked) |
| Add to Cart ("In den Warenkorb") | All variation options are picked AND a valid variation exists |

**Invalid combination (all options picked but no matching variation):**
- Editor button: visible but disabled, show error text "Kombination nicht verfügbar"
- Add to Cart button: visible but disabled, show error text "Kombination nicht verfügbar"
- Requestable button: unaffected (still enabled)

### Frontend: Button Actions

| Button | Action |
|--------|--------|
| Editor | Navigate to editor URL (already built dynamically from `window.infoplusEditorSettings`) |
| Requestable | Smooth scroll to `#anfrage` section on the page |
| Add to Cart | Standard WooCommerce add-to-cart behavior (form submit or AJAX) |

## Technical Context

### Files involved

**Admin:**
- `src/Modules/ProductDetailSuite/Fields/ProductAttributeImages.php` — Add order type dropdown to `renderTermFields()` for `pa_model` terms, handle save in `saveMetaBox()`
- `src/Modules/ProductDetailSuite/Assets/admin-product-attribute-images.css` — Style the dropdown

**Frontend data:**
- `src/Modules/ProductDetailSuite/Module.php` — Add `order_type` to model data in `window.infoplusProductData.models`

**Frontend buttons:**
- `src/Modules/ProductDetailSuite/Assets/product-state.js` — Event bus, may need new event or existing `model:changed` is sufficient
- `src/Modules/WooSingleProductAddons/Module.php` — Currently injects editor button settings; may need to inject order type data
- `src/Modules/WooSingleProductAddons/Assets/variation-editor-link.js` — Currently handles editor link button; needs to handle all button types
- `src/Modules/ProductDetailSuite/Assets/product-detail-suite.css` — Button styling, disabled states

### New meta key
```
_infoplus_attr_order_type_{attr}_{term}
```
Example: `_infoplus_attr_order_type_model_digitaldruck-namensschilder` → `"editable"`

### Data structure change in `window.infoplusProductData.models`
Add `order_type` to each model:
```js
{
  slug: "digitaldruck",
  name: "Digitaldruck Namensschilder",
  image_url: "https://...",
  order_type: "editable",    // NEW — "editable" | "requestable" | "buyable"
  // ... other existing fields
}
```

### New static API method (ProductAttributeImages.php)
```php
public static function getTermOrderType(int $product_id, string $taxonomy, string $term_slug): string
```
Returns `"editable"` (default), `"requestable"`, or `"buyable"`.

## Implementation Notes

### Admin (ProductAttributeImages.php)

In `renderTermFields()`, when `$taxonomy === 'pa_model'`:
- Add a `<select>` dropdown BEFORE the image field
- Field name: `_infoplus_attr_order_type_{attr}_{term}`
- Options:
  ```html
  <option value="editable">Editable (Editor)</option>
  <option value="requestable">Requestable (Anfrage)</option>
  <option value="buyable">Buyable (Warenkorb)</option>
  ```
- Style: full-width select matching the existing field styling
- Label: "Bestelltyp" (German for "Order Type")

In `saveMetaBox()`:
- Handle `_infoplus_attr_order_type_*` keys — sanitize value against whitelist `['editable', 'requestable', 'buyable']`, default to `editable`

### Module.php — building product data

In the method that builds models for `window.infoplusProductData`:
- Call `ProductAttributeImages::getTermOrderType($product_id, 'pa_model', $term_slug)` for each model
- Add `order_type` field to the model object

### Frontend button management (variation-editor-link.js or new dedicated file)

The button logic should:
1. Listen for `model:changed` event from `product-state.js`
2. On model change:
   - Get the model's `order_type` from `window.infoplusProductData.models`
   - Show/hide buttons based on type:
     - `editable`: show `.infoplus-btn-editor` + `.infoplus-btn-anfrage`, hide `.infoplus-btn-cart`
     - `requestable`: show `.infoplus-btn-anfrage`, hide `.infoplus-btn-editor` + `.infoplus-btn-cart`
     - `buyable`: show `.infoplus-btn-cart`, hide `.infoplus-btn-editor` + `.infoplus-btn-anfrage`
3. Editor button: disabled by default, enabled when `variation:found` fires with valid variation
4. Cart button: disabled by default, enabled when `variation:found` fires with valid variation
5. Anfrage button: enabled immediately when a model with `editable` or `requestable` type is selected

### Button HTML structure (expected on the page)

The buttons are likely placed via Elementor. The JS needs to target them by class or data attribute. Recommended approach:
- Add `data-infoplus-action="editor"` to the editor button
- Add `data-infoplus-action="anfrage"` to the requestable button  
- Add `data-infoplus-action="cart"` to the add-to-cart button
- OR use existing classes — check current HTML structure first

### CSS states

```css
/* Disabled state for all action buttons */
.infoplus-btn-disabled {
    opacity: 0.5;
    pointer-events: none;
    cursor: not-allowed;
}

/* Error message below disabled button */
.infoplus-btn-error-msg {
    color: #a12828;
    font-size: 13px;
    text-align: center;
    margin-top: 4px;
}

/* Hidden button */
.infoplus-btn-hidden {
    display: none !important;
}
```

### Edge cases

- **Model with no order type saved:** Defaults to `editable` — the current behavior is preserved
- **Product has no models:** No buttons shown? Or fall back to WooCommerce default? — Check current behavior and maintain it
- **Switching between models rapidly:** Debounce or cancel pending transitions to avoid flicker
- **Elementor editor mode:** Buttons should all be visible for preview purposes — detect `elementorFrontend.isEditMode()` and skip visibility logic
- **Mobile:** Button stacking should work naturally since they're in a vertical layout

## Acceptance Criteria

1. **Admin:** Order type dropdown visible on each model term card in attribute overrides
2. **Admin:** Default value is "Editable (Editor)" when nothing is saved
3. **Admin:** Saved value persists after page reload
4. **Frontend:** Editable model → editor button + anfrage button visible
5. **Frontend:** Requestable model → only anfrage button visible
6. **Frontend:** Buyable model → only cart button visible
7. **Frontend:** Editor button disabled until all options picked and valid variation found
8. **Frontend:** Cart button disabled until all options picked and valid variation found
9. **Frontend:** Anfrage button enabled as soon as a requestable/editable model is selected (no need for full selection)
10. **Frontend:** Invalid variation combo → buttons visible but disabled with "Kombination nicht verfügbar" text
11. **Frontend:** Switching models updates button visibility immediately
12. **Frontend:** Elementor editor mode shows all buttons for preview
