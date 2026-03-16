# Feature: Model Image Gallery on Product Detail Page

## Summary

Upgrade the per-product model attribute override to support multiple images (a gallery) instead of a single image. On the product detail page, the main ProductImage widget shows the selected model's first (main) image as the large image, with additional model gallery images displayed as clickable thumbnails below. Clicking a thumbnail swaps it into the main image spot (preview only — does not change the selected model). When a different model is selected in ModelSelector, the gallery updates to that model's images.

## Current Behavior

### Admin (Product Edit Page)
- Each per-product model override has a single IMAGE field (`_infoplus_attr_image_{attr}_{term}`) storing one attachment ID
- Uses WordPress media uploader for single image selection
- Managed by `ProductAttributeImages.php`

### Frontend (Product Detail Page)
- **ModelSelector** shows model cards with a single image each
- **ProductImage** shows the WooCommerce product featured image as the main image, and WooCommerce gallery images as thumbnails below
- Selecting a model updates the main image to that model's single image
- No relationship between gallery thumbnails and models

## Desired Behavior

### Admin: Gallery Upload for Per-Product Model Images

- Replace the single image upload field with a **gallery upload field** for `pa_model` attribute terms (only for models, other attributes keep single image)
- Admin can upload/select multiple images per model
- Images are orderable via drag-and-drop (first image = main image)
- The first image in the gallery is marked as the "main" image — this is what shows on the ModelSelector card and as the initial large product image
- Store as: `_infoplus_attr_gallery_{attr}_{term}` → comma-separated attachment IDs (e.g., `"142,143,144"`)
- Keep the existing single `_infoplus_attr_image_{attr}_{term}` field working as fallback — if no gallery exists, use the single image (backward compatibility)
- The global term image (set on the attribute term edit page) stays as a single image — no gallery support there

### Frontend: Model Gallery in ProductImage Widget

**On page load (first model is selected by default):**
- Main large image = first (main) image from the selected model's gallery
- Thumbnail row below = remaining images from that model's gallery
- If model has only 1 image → no thumbnail row shown
- If model has no images → fall back to WooCommerce product featured image, no thumbnails

**When a model is selected in ModelSelector:**
- Main image swaps to that model's first (main) gallery image
- Thumbnail row updates to show that model's remaining gallery images
- Transition should be smooth (fade or instant swap)

**When clicking a gallery thumbnail:**
- The clicked thumbnail swaps into the main image spot (large preview)
- The thumbnail gets an "active" visual indicator (border/highlight)
- This does NOT change the selected model — it's purely a preview within the current model's gallery
- When user selects a different model, the gallery resets to that model's images (thumbnail preview resets)

**ModelSelector cards:**
- Always show only the first (main) image from the gallery — no change to card layout
- No badge or indicator for number of gallery images

## Technical Context

### Files involved

**Admin (gallery upload):**
- `src/Modules/ProductDetailSuite/Fields/ProductAttributeImages.php` — `renderTermFields()` method renders the single image upload; needs gallery version for `pa_model`
- `src/Modules/ProductDetailSuite/Assets/admin-product-attribute-images.js` — Handles media uploader; needs multi-select gallery support
- `src/Modules/ProductDetailSuite/Assets/admin-product-attribute-images.css` — Admin styles

**Frontend (gallery display):**
- `src/Modules/ProductDetailSuite/Widgets/ProductImage.php` — Elementor widget rendering the image container
- `src/Modules/ProductDetailSuite/Assets/product-image.js` — Frontend logic for image updates
- `src/Modules/ProductDetailSuite/Assets/product-state.js` — Event bus (`model:changed`)
- `src/Modules/ProductDetailSuite/Assets/product-detail-suite.css` — Widget styles
- `src/Modules/ProductDetailSuite/Module.php` — Builds `window.infoplusProductData`

### New meta key
```
_infoplus_attr_gallery_{attr}_{term}
```
Example: `_infoplus_attr_gallery_model_digitaldruck-namensschilder` → `"142,143,144"`

### Data structure change in `window.infoplusProductData.models`
Currently each model has:
```js
{ slug: "digitaldruck", name: "...", image_url: "https://..." }
```
Needs to become:
```js
{ 
  slug: "digitaldruck", 
  name: "...", 
  image_url: "https://...",           // First/main image (unchanged, for ModelSelector cards)
  gallery: [                           // NEW: full gallery array
    { id: 142, url: "https://...", thumbnail_url: "https://..." },
    { id: 143, url: "https://...", thumbnail_url: "https://..." },
    { id: 144, url: "https://...", thumbnail_url: "https://..." }
  ]
}
```

### Priority/fallback for gallery
1. Per-product gallery (`_infoplus_attr_gallery_model_{term}`) → use all images
2. Per-product single image (`_infoplus_attr_image_model_{term}`) → use as single-image gallery (backward compat)
3. Global term image (`_infoplus_term_image_id`) → use as single-image gallery
4. Nothing → fall back to WooCommerce product featured image

## Implementation Notes

### Admin gallery field (ProductAttributeImages.php)
- In `renderTermFields()`, detect if `$taxonomy === 'pa_model'` — if yes, render gallery field instead of single image
- Gallery field uses WP media frame with `multiple: true` and `library: { type: 'image' }`
- Store selected IDs as comma-separated string in hidden input
- Show thumbnails in a sortable row (jQuery UI Sortable or HTML5 drag-and-drop)
- First image gets a "Main" badge/label
- Keep Change/Remove buttons, add "Add Images" button
- In `saveMetaBox()`, handle the new `_infoplus_attr_gallery_*` keys — sanitize as comma-separated integers

### Admin gallery field (admin-product-attribute-images.js)
- New handler for gallery fields: opens media frame with `multiple: 'add'`
- On selection, updates hidden input with comma-separated IDs
- Renders sortable thumbnail preview row
- "Remove" on individual thumbnails removes from the list
- Drag to reorder updates the hidden input order

### Static API (ProductAttributeImages.php)
- Add new static method: `getTermGalleryUrls(int $product_id, string $taxonomy, string $term_slug): array`
  - Returns array of `['id' => int, 'url' => string, 'thumbnail_url' => string]`
  - Checks gallery meta first, falls back to single image, then global
- Existing `getTermImageUrl()` should still work — returns the first gallery image URL (or single image)

### Module.php — building product data
- In the method that builds `window.infoplusProductData`, when building the models array:
  - Call `ProductAttributeImages::getTermGalleryUrls()` for each model
  - Add `gallery` array to each model object
  - Keep `image_url` as the first gallery image URL (backward compat for ModelSelector)

### product-image.js
- On `model:changed` event:
  1. Get the selected model's `gallery` array from `window.infoplusProductData.models`
  2. Set main image to `gallery[0].url`
  3. Rebuild thumbnail row with `gallery[1..n].thumbnail_url`
  4. If gallery has ≤1 image, hide the thumbnail row
- On thumbnail click:
  1. Swap main image `src` to the clicked thumbnail's full-size `url`
  2. Add "active" class to clicked thumbnail, remove from others
  3. Do NOT fire any events — this is local to ProductImage widget

### CSS
- Thumbnail row styling: horizontal scroll or wrap, consistent sizing
- Active thumbnail: border in brand pink (`#e91e8c`) or subtle highlight
- First thumbnail (main) could have a subtle "main" indicator on hover
- Smooth transition on main image swap (optional: CSS transition on opacity)

## Acceptance Criteria

1. **Admin:** Model attribute overrides show gallery upload (multi-image) instead of single image
2. **Admin:** Gallery images are sortable via drag-and-drop, first image is "main"
3. **Admin:** Other attributes (Format, Befestigung, etc.) keep single image upload — unchanged
4. **Admin:** Saving works — gallery IDs persisted in `_infoplus_attr_gallery_*` meta
5. **Frontend:** On page load, main image = first gallery image of default model, thumbnails = rest
6. **Frontend:** Select different model in ModelSelector → main image and gallery thumbnails update
7. **Frontend:** Click gallery thumbnail → swaps into main image, no model change
8. **Frontend:** Model with 1 image → no thumbnail row
9. **Frontend:** Model with 0 images → WooCommerce featured image, no thumbnails
10. **Backward compat:** Products with old single-image meta still work (shown as 1-image gallery)
11. **ModelSelector cards:** Always show first/main image only — no layout change
