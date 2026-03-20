/**
 * Infoplus Product Attribute Images - Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // =========================================================================
    // TOP-LEVEL SUITE TAB SWITCHING
    // =========================================================================

    $(document).on('click', '.infoplus-suite-tab', function(e) {
        e.preventDefault();

        var $tab   = $(this);
        var panel  = $tab.data('suite-panel');
        var $wrap  = $tab.closest('.infoplus-suite-wrap');

        $wrap.find('.infoplus-suite-tab').removeClass('is-active');
        $tab.addClass('is-active');

        $wrap.find('.infoplus-suite-panel').removeClass('is-active');
        $wrap.find('.infoplus-suite-panel[data-suite-panel="' + panel + '"]').addClass('is-active');
    });

    // =========================================================================
    // ATTRIBUTE OVERRIDES — inner tab switching
    // =========================================================================

    $(document).on('click', '.infoplus-attr-tab', function(e) {
        e.preventDefault();

        var $tab  = $(this);
        var tabId = $tab.data('tab');
        var $wrap = $tab.closest('.infoplus-attr-images-wrap');

        // Update tab states
        $wrap.find('.infoplus-attr-tab').removeClass('is-active');
        $tab.addClass('is-active');

        // Update panel states
        $wrap.find('.infoplus-attr-panel').removeClass('is-active');
        $wrap.find('.infoplus-attr-panel[data-panel="' + tabId + '"]').addClass('is-active');
    });

    // =========================================================================
    // HIDE CHECKBOX FUNCTIONALITY
    // =========================================================================

    // Master "Hide All" checkbox
    $(document).on('change', '.infoplus-hide-all-checkbox', function() {
        var $checkbox = $(this);
        var isChecked = $checkbox.is(':checked');
        var $card     = $checkbox.closest('.infoplus-term-card');

        // Check/uncheck all individual hide checkboxes
        $card.find('.infoplus-hide-checkbox').each(function() {
            $(this).prop('checked', isChecked).trigger('change');
        });

        // Update card state
        updateCardOverrideState($card);
    });

    // Individual hide checkbox
    $(document).on('change', '.infoplus-hide-checkbox', function() {
        var $checkbox     = $(this);
        var isChecked     = $checkbox.is(':checked');
        var $fieldGroup   = $checkbox.closest('.field-group');
        var $fieldContent = $fieldGroup.find('.field-content');
        var $card         = $checkbox.closest('.infoplus-term-card');

        // Toggle disabled state on field content
        if (isChecked) {
            $fieldContent.addClass('is-disabled');
            $fieldContent.find('input, textarea, button').prop('disabled', true);
            $fieldGroup.find('.global-preview').addClass('is-hidden-preview');
        } else {
            $fieldContent.removeClass('is-disabled');
            $fieldContent.find('input, textarea, button').prop('disabled', false);
            $fieldGroup.find('.global-preview').removeClass('is-hidden-preview');
        }

        // Update master checkbox state
        updateMasterCheckbox($card);

        // Update card state
        updateCardOverrideState($card);
    });

    // Update master checkbox based on individual checkboxes
    function updateMasterCheckbox($card) {
        var $masterCheckbox      = $card.find('.infoplus-hide-all-checkbox');
        if (!$masterCheckbox.length) return;

        var $individualCheckboxes = $card.find('.infoplus-hide-checkbox');
        var totalCount            = $individualCheckboxes.length;
        var checkedCount          = $individualCheckboxes.filter(':checked').length;

        if (totalCount === 0) {
            $masterCheckbox.prop('checked', false).prop('indeterminate', false);
        } else if (checkedCount === 0) {
            $masterCheckbox.prop('checked', false).prop('indeterminate', false);
        } else if (checkedCount === totalCount) {
            $masterCheckbox.prop('checked', true).prop('indeterminate', false);
        } else {
            $masterCheckbox.prop('checked', false).prop('indeterminate', true);
        }
    }

    // Update card override badge
    function updateCardOverrideState($card) {
        var hasOverride = false;

        // Check for single image upload
        $card.find('.infoplus-image-id').each(function() {
            if ($(this).val() && $(this).val() !== '0') {
                hasOverride = true;
                return false;
            }
        });

        // Check for gallery image upload
        if (!hasOverride) {
            $card.find('.infoplus-gallery-ids').each(function() {
                if ($(this).val() && $(this).val() !== '') {
                    hasOverride = true;
                    return false;
                }
            });
        }

        // Check for text/textarea overrides
        if (!hasOverride) {
            $card.find('.field-tooltip input[type="text"], .field-modal textarea').each(function() {
                if ($(this).val().trim() !== '') {
                    hasOverride = true;
                    return false;
                }
            });
        }

        // Check for hide checkboxes
        if (!hasOverride) {
            if ($card.find('.infoplus-hide-checkbox:checked').length > 0) {
                hasOverride = true;
            }
        }

        // Update card state
        if (hasOverride) {
            $card.addClass('has-override');
            if (!$card.find('.override-badge').length) {
                $card.find('.term-header').append('<span class="override-badge">Custom</span>');
            }
        } else {
            $card.removeClass('has-override');
            $card.find('.override-badge').remove();
        }
    }

    // =========================================================================
    // MEDIA UPLOADER
    // =========================================================================

    var mediaFrame;

    $(document).on('click', '.infoplus-attr-images-wrap .infoplus-upload-btn', function(e) {
        e.preventDefault();

        var $button = $(this);

        // Don't proceed if button is disabled
        if ($button.prop('disabled')) return;

        var $field     = $button.closest('.image-upload-field');
        var $input     = $field.find('.infoplus-image-id');
        var $preview   = $field.find('.image-preview');
        var $removeBtn = $field.find('.infoplus-remove-btn');
        var $termCard  = $button.closest('.infoplus-term-card');

        console.log('[InfoplusAttrImages] Upload button clicked');

        // Create media frame
        mediaFrame = wp.media({
            title:    'Select Image',
            button:   { text: 'Use this image' },
            multiple: false,
            library:  { type: 'image' }
        });

        // On select
        mediaFrame.on('select', function() {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            var thumbUrl   = attachment.sizes && attachment.sizes.thumbnail
                ? attachment.sizes.thumbnail.url
                : attachment.url;

            console.log('[InfoplusAttrImages] Image selected:', attachment.id, thumbUrl);

            // Update input
            $input.val(attachment.id);

            // Update preview
            if ($preview.find('img').length) {
                $preview.find('img').attr('src', thumbUrl);
            } else {
                $preview.html('<img src="' + thumbUrl + '" alt="" />');
            }
            $preview.show();

            // Update button text
            $button.text('Change');

            // Show remove button
            $removeBtn.show();

            // Update card state
            updateCardOverrideState($termCard);

            // Hide global preview if shown
            $termCard.find('.field-image .global-preview').hide();
        });

        mediaFrame.open();
    });

    // Remove image
    $(document).on('click', '.infoplus-attr-images-wrap .infoplus-remove-btn', function(e) {
        e.preventDefault();

        var $button    = $(this);

        // Don't proceed if button is disabled
        if ($button.prop('disabled')) return;

        var $field     = $button.closest('.image-upload-field');
        var $input     = $field.find('.infoplus-image-id');
        var $preview   = $field.find('.image-preview');
        var $uploadBtn = $field.find('.infoplus-upload-btn');
        var $termCard  = $button.closest('.infoplus-term-card');

        console.log('[InfoplusAttrImages] Remove button clicked');

        // Clear input
        $input.val('');

        // Hide preview
        $preview.hide().find('img').attr('src', '');

        // Update button text
        $uploadBtn.text('Upload');

        // Hide remove button
        $button.hide();

        // Update card state
        updateCardOverrideState($termCard);

        // Show global preview if exists
        $termCard.find('.field-image .global-preview').show();
    });

    // =========================================================================
    // TEXT FIELD CHANGES
    // =========================================================================

    // Listen for changes on text/textarea fields
    $(document).on('input change', '.infoplus-term-card .field-tooltip input, .infoplus-term-card .field-modal textarea', function() {
        var $card = $(this).closest('.infoplus-term-card');
        updateCardOverrideState($card);
    });

    // =========================================================================
    // GALLERY UPLOAD
    // =========================================================================

    function initGalleryFields() {
        // Open media picker for gallery
        $(document).on('click', '.infoplus-gallery-add-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            if ($btn.prop('disabled')) return;

            var $field = $btn.closest('.gallery-upload-field');
            var frame  = wp.media({
                title:    'Bilder auswählen',
                multiple: 'add',
                library:  { type: 'image' },
                button:   { text: 'Bilder verwenden' }
            });

            frame.on('select', function() {
                frame.state().get('selection').each(function(attachment) {
                    var sizes    = attachment.attributes.sizes;
                    var thumbUrl = (sizes && sizes.thumbnail) ? sizes.thumbnail.url : attachment.attributes.url;
                    appendGalleryItem($field, attachment.id, thumbUrl);
                });
                updateGalleryInput($field);
            });

            frame.open();
        });

        // Clear all gallery images
        $(document).on('click', '.infoplus-gallery-clear-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            if ($btn.prop('disabled')) return;

            var $field = $btn.closest('.gallery-upload-field');
            $field.find('.gallery-preview-row').empty();
            updateGalleryInput($field);
        });

        // Remove individual gallery image
        $(document).on('click', '.gallery-remove-btn', function(e) {
            e.preventDefault();
            var $item  = $(this).closest('.gallery-thumb-item');
            var $field = $item.closest('.gallery-upload-field');
            $item.remove();
            rebadgeRow($field.find('.gallery-preview-row'));
            updateGalleryInput($field);
        });
    }

    function appendGalleryItem($field, id, thumbUrl) {
        var $row    = $field.find('.gallery-preview-row');
        var isFirst = $row.find('.gallery-thumb-item').length === 0;
        var mainBadge = isFirst ? '<span class="gallery-main-badge">Main</span>' : '';
        var $item = $(
            '<div class="gallery-thumb-item" data-id="' + id + '">' +
            '<img src="' + thumbUrl + '" alt="" />' +
            mainBadge +
            '<button type="button" class="gallery-remove-btn" title="Remove">&#215;</button>' +
            '</div>'
        );
        $row.append($item);
        // Init sortable once first item is added
        if ($row.data('sortable-init') !== true) {
            initRowSortable($row);
            $row.data('sortable-init', true);
        }
    }

    function initRowSortable($row) {
        $row.sortable({
            update: function() {
                rebadgeRow($row);
                updateGalleryInput($row.closest('.gallery-upload-field'));
            }
        });
        $row.data('sortable-init', true);
    }

    function rebadgeRow($row) {
        $row.find('.gallery-main-badge').remove();
        $row.find('.gallery-thumb-item:first-child').prepend('<span class="gallery-main-badge">Main</span>');
    }

    function updateGalleryInput($field) {
        var ids = [];
        $field.find('.gallery-thumb-item').each(function() {
            ids.push($(this).data('id'));
        });
        $field.find('.infoplus-gallery-ids').val(ids.join(','));
        $field.find('.infoplus-gallery-add-btn').text(ids.length > 0 ? 'Add More' : 'Add Images');
        $field.find('.infoplus-gallery-clear-btn').toggle(ids.length > 0);
        updateCardOverrideState($field.closest('.infoplus-term-card'));
    }

    // =========================================================================
    // DEFAULT VARIATION — cascading dropdown logic
    // =========================================================================

    // Stores the full original option list per model section per attribute,
    // so we can rebuild selects after filtering without losing any options.
    var modelDefaultAllOptions = {};

    /**
     * Capture the original <option> elements for every model-default select on
     * page load (before any filtering removes options).
     */
    function initModelDefaultAttrOptions() {
        $('.infoplus-model-default-section').each(function() {
            var $section  = $(this);
            var modelSlug = $section.data('model-slug');
            modelDefaultAllOptions[modelSlug] = {};

            $section.find('.infoplus-model-default-attr-select').each(function() {
                var $select = $(this);
                var attr    = $select.data('attr');
                var opts    = [];
                $select.find('option').each(function() {
                    opts.push({ val: $(this).val(), text: $(this).text() });
                });
                modelDefaultAllOptions[modelSlug][attr] = opts;
            });
        });
    }

    /**
     * Rebuild each select in a model section so it only shows attribute values
     * that actually exist in at least one variation for this model, given the
     * current values selected in the other selects.
     * If a previously selected value is no longer valid it is silently cleared.
     * @param {jQuery} $section - the .infoplus-model-default-section element
     */
    function filterModelDefaultSelects($section) {
        var modelSlug  = $section.data('model-slug');
        var variations = (window.infoplusAdminData && window.infoplusAdminData.variations) || [];
        var allOptions = modelDefaultAllOptions[modelSlug] || {};

        // Narrow to variations belonging to this model
        var modelVars = variations.filter(function(v) {
            return (v.attributes['attribute_pa_model'] || '') === modelSlug;
        });

        var $selects = $section.find('.infoplus-model-default-attr-select');

        $selects.each(function() {
            var $thisSelect = $(this);
            var thisAttr    = $thisSelect.data('attr');
            var currentVal  = $thisSelect.val();

            // Collect the currently selected values of all OTHER selects
            var otherSels = {};
            $selects.each(function() {
                var $other    = $(this);
                var otherAttr = $other.data('attr');
                if (otherAttr === thisAttr) return;
                var v = $other.val();
                if (v) otherSels[otherAttr] = v;
            });

            // Determine which values for thisAttr are reachable given otherSels
            var valid = {};
            modelVars.forEach(function(v) {
                var ok = true;
                for (var attr in otherSels) {
                    var varVal = v.attributes['attribute_' + attr] || '';
                    if (varVal && varVal !== otherSels[attr]) { ok = false; break; }
                }
                if (ok) {
                    var val = v.attributes['attribute_' + thisAttr];
                    if (val) valid[val] = true;
                }
            });

            // Rebuild from stored originals — only keep valid options
            var origOpts = allOptions[thisAttr] || [];
            $thisSelect.empty();
            origOpts.forEach(function(opt) {
                if (!opt.val || valid[opt.val]) {
                    $thisSelect.append($('<option>', { value: opt.val, text: opt.text }));
                }
            });

            // Restore previous value if still valid, otherwise clear
            if (currentVal && valid[currentVal]) {
                $thisSelect.val(currentVal);
            } else {
                $thisSelect.val('');
            }
        });
    }

    /**
     * Called whenever a dropdown changes inside a model default section.
     * Finds a matching variation (scoped to that model) and updates the
     * hidden input + status badge for that section.
     * @param {jQuery} $section - the .infoplus-model-default-section element
     */
    function onModelDefaultAttrChange($section) {
        var modelSlug  = $section.data('model-slug');
        var selections = { pa_model: modelSlug };
        var allSelected = true;

        $section.find('.infoplus-model-default-attr-select').each(function() {
            var attr = $(this).data('attr');
            var val  = $(this).val();
            selections[attr] = val;
            if (!val) allSelected = false;
        });

        var $statusEl    = $section.find('.infoplus-model-variation-status');
        var $hiddenInput = $section.find('.infoplus-model-default-variation-id');

        if (!allSelected) {
            $hiddenInput.val('');
            showModelVariationStatus($statusEl, 'none');
            return;
        }

        var variations = (window.infoplusAdminData && window.infoplusAdminData.variations) || [];
        var match = null;

        for (var i = 0; i < variations.length; i++) {
            var v        = variations[i];
            var allMatch = true;

            for (var attr in selections) {
                var attrKey = 'attribute_' + attr;
                if ((v.attributes[attrKey] || '') !== selections[attr]) {
                    allMatch = false;
                    break;
                }
            }

            if (allMatch) {
                match = v;
                break;
            }
        }

        if (match) {
            $hiddenInput.val(match.id);
            showModelVariationStatus($statusEl, 'found', match);
        } else {
            $hiddenInput.val('');
            showModelVariationStatus($statusEl, 'notfound');
        }
    }

    /**
     * Update the status badge inside a model default section.
     * @param {jQuery} $el    - the .infoplus-model-variation-status element
     * @param {string} state  - 'found' | 'notfound' | 'none'
     * @param {object} [variation]
     */
    function showModelVariationStatus($el, state, variation) {
        if (state === 'found') {
            $el.html(
                '<span class="status-found">&#10003; Variation #' + variation.id +
                ' (SKU: ' + (variation.sku || '—') + ')</span>'
            );
        } else if (state === 'notfound') {
            $el.html('<span class="status-notfound">&#10007; Keine passende Variation gefunden</span>');
        } else {
            $el.html('<span class="status-none" style="color:#888;">Kein Standard gesetzt</span>');
        }
    }

    /**
     * On page load: pre-populate each model section's dropdowns from the stored
     * modelDefaultVariations map and show the initial status badges.
     */
    function initModelDefaultVariationDropdowns() {
        var data = window.infoplusAdminData;
        if (!data || !data.modelDefaultVariations) return;

        var defaults   = data.modelDefaultVariations;
        var variations = data.variations || [];

        $('.infoplus-model-default-section').each(function() {
            var $section  = $(this);
            var modelSlug = $section.data('model-slug');
            var savedId   = defaults[modelSlug];

            if (!savedId) return;

            // Find the saved variation object
            var savedVar = null;
            for (var i = 0; i < variations.length; i++) {
                if (variations[i].id === savedId) {
                    savedVar = variations[i];
                    break;
                }
            }

            if (!savedVar) return;

            // Pre-populate each select
            $section.find('.infoplus-model-default-attr-select').each(function() {
                var $select = $(this);
                var attrKey = 'attribute_' + $select.data('attr');
                var val     = savedVar.attributes[attrKey] || '';
                if (val) $select.val(val);
            });

            // Filter options based on saved selection, then resolve status badge
            filterModelDefaultSelects($section);
            onModelDefaultAttrChange($section);
        });
    }

    // Live: filter available options then resolve variation whenever a dropdown changes
    $(document).on('change', '.infoplus-model-default-attr-select', function() {
        var $section = $(this).closest('.infoplus-model-default-section');
        filterModelDefaultSelects($section);
        onModelDefaultAttrChange($section);
    });

    // =========================================================================
    // INITIALIZATION
    // =========================================================================

    // Initialize master checkbox states on page load
    $('.infoplus-term-card').each(function() {
        var $card = $(this);
        updateMasterCheckbox($card);
    });

    // Initialize sortable on pre-populated gallery rows (PHP-rendered existing images)
    $('.gallery-preview-row').each(function() {
        var $row = $(this);
        if ($row.find('.gallery-thumb-item').length > 0) {
            initRowSortable($row);
        }
    });

    // Initialize gallery field event handlers
    initGalleryFields();

    // Capture original option lists BEFORE any filtering removes options
    initModelDefaultAttrOptions();

    // Initialize per-model Default Variation dropdowns from stored data
    initModelDefaultVariationDropdowns();
});
