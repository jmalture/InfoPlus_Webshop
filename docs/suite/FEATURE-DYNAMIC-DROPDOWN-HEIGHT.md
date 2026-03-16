# Feature: Dynamic Dropdown Height Based on Term Images

## Summary

Each variation dropdown should automatically detect whether its options have images. If any option has an image → use the taller height (image-ready). If no options have images → use native/slim input height. This is per-dropdown, not global.

## Current State (partially implemented)

The CSS and Elementor controls are already wired up correctly:
- The `option_image_height` control selector targets `[data-has-images="yes"] .infoplus-dropdown__trigger`
- The global `data-show-images` toggle has already been removed
- **BUT** the `data-has-images` attribute is never actually set on any `.infoplus-dropdown` element — not in PHP and not in JS

So the taller height never activates, and all dropdowns use native slim height regardless of whether they have images.

## What Needs to Happen

### Option A: PHP-side detection (preferred if options don't rebuild dynamically)

In `VariationDropdowns.php → renderDropdowns()`, after building `$terms_data` for each attribute, check if any term has a non-empty `image_url`. If yes, add `data-has-images="yes"` to the `.infoplus-dropdown` div.

Current render:
```php
<div class="infoplus-dropdown" data-attribute="<?php echo esc_attr($attribute_name); ?>">
```

Should become:
```php
<?php
$has_images = false;
foreach ($terms_data as $term) {
    if (!empty($term['image_url'])) {
        $has_images = true;
        break;
    }
}
?>
<div class="infoplus-dropdown" 
     data-attribute="<?php echo esc_attr($attribute_name); ?>"
     data-has-images="<?php echo $has_images ? 'yes' : 'no'; ?>">
```

That's the core fix — the existing CSS selectors will then work automatically.

### Option B: JS-side detection (needed if dropdown options are rebuilt dynamically)

If `variation-dropdowns.js` rebuilds dropdown options when `model:changed` fires (options might change per model), then the `data-has-images` attribute also needs to be re-evaluated after each rebuild.

In `variation-dropdowns.js`, add a function:
```js
function updateHasImagesFlags() {
    $('.infoplus-dropdown').each(function() {
        var $dropdown = $(this);
        var hasImages = $dropdown.find('.infoplus-dropdown__option-image').length > 0;
        $dropdown.attr('data-has-images', hasImages ? 'yes' : 'no');
    });
}
```

Call this:
1. On init (after widget initialization)
2. After options are rebuilt from `model:changed` event (if applicable)

### Recommended: Do BOTH

Set `data-has-images` in PHP for the initial server-rendered state (no flash of wrong height), and also set it in JS after any dynamic option rebuilds to keep it in sync.

## Files to Modify

1. **`src/Modules/ProductDetailSuite/Widgets/VariationDropdowns.php`** — In `renderDropdowns()`, add `data-has-images` attribute to each `.infoplus-dropdown` div based on whether `$terms_data` contains any terms with non-empty `image_url`

2. **`src/Modules/ProductDetailSuite/Assets/variation-dropdowns.js`** — Add `updateHasImagesFlags()` function, call on init and after any option rebuild

3. **No CSS changes needed** — the selectors already target `[data-has-images="yes"]` correctly

## Acceptance Criteria

1. Dropdown whose terms have images → taller height matching the Image Height Elementor control
2. Dropdown whose terms have NO images → native slim height, no extra space
3. Mixed scenario works on same page: e.g. Oberfläche (with images) is tall, Format (no images) is slim
4. Image Height/Width Elementor controls still work for image-having dropdowns
5. No flash of wrong height on page load (PHP sets the attribute server-side)
6. If dropdown options rebuild after model change, the height re-evaluates correctly
