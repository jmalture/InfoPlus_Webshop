(function($) {
    'use strict';

    console.log('[SonderwunschPopup] Script loaded');

    var RWV_HEADING     = 'Individuelle Anfrage mit Variante';
    var RWV_DESCRIPTION = 'Sie haben bereits Ihre Wunschkonfiguration ausgewählt. Ergänzen Sie weitere Details, beschreiben Sie Sonderwünsche oder laden Sie Ihr Logo hoch. Wir melden uns innerhalb von 24 Stunden mit einem unverbindlichen Angebot.';

    var _originalHeading     = null;
    var _originalDescription = null;

    function setReadonlyField($field) {
        $field
            .prop('readonly', true)
            .css({
                'background-color': '#f5f5f5',
                'color':            '#888888',
                'border-color':     '#e0e0e0',
                'cursor':           'default'
            });
    }

    function clearReadonlyField($field) {
        $field
            .prop('readonly', false)
            .css({
                'background-color': '',
                'color':            '',
                'border-color':     '',
                'cursor':           ''
            });
    }

    // ── Reorder form fields for grid layout ───────────────────────────────────
    function reorderFormFields($popup) {
        var $wrapper = $popup.find('.elementor-form-fields-wrapper');
        if (!$wrapper.length) return;

        // Define field order - Product fields: product first, then stuck
        var productFields = ['product', 'stuck', 'modell', 'variante', 'befestigung'];
        var contactFields = ['firma', 'name', 'email', 'rufnummer'];
        var fullWidthFields = ['message', 'muster', 'file_upload'];

        var productRow = 1;
        var contactRow = 1;

        productFields.forEach(function(fieldId) {
            var $field = $wrapper.find('.elementor-field-group-' + fieldId);
            if ($field.length && $field.is(':visible')) {
                $field.css({
                    'grid-column': '1',
                    'grid-row': productRow++
                });
            }
        });

        contactFields.forEach(function(fieldId) {
            var $field = $wrapper.find('.elementor-field-group-' + fieldId);
            if ($field.length) {
                $field.css({
                    'grid-column': '2',
                    'grid-row': contactRow++
                });
            }
        });

        // Full width fields start after the tallest column
        var startRow = Math.max(productRow, contactRow);

        fullWidthFields.forEach(function(fieldId) {
            var $field = $wrapper.find('.elementor-field-group-' + fieldId);
            if ($field.length) {
                $field.css({
                    'grid-column': '1 / -1',
                    'grid-row': startRow++
                });
            }
        });

        // Submit button at the end
        var $submit = $wrapper.find('.elementor-field-type-submit');
        if ($submit.length) {
            $submit.css({
                'grid-column': '1 / -1',
                'grid-row': startRow
            });
        }

        console.log('[SonderwunschPopup] ✓ Form fields reordered');
    }

    $(document).on('elementor/popup/show', function(event, id, instance) {
        console.log('[SonderwunschPopup] Popup opened, ID:', id);

        setTimeout(function() {
            var $popup = $('.sonderwuche_popup');
            if (!$popup.length) {
                console.log('[SonderwunschPopup] Popup container not found');
                return;
            }

            // Determine order type
            var orderType = 'editable';
            if (window.infoplusProductState && window.infoplusProductData && window.infoplusProductData.models) {
                var selectedModel = window.infoplusProductState.selectedModel;
                for (var m = 0; m < window.infoplusProductData.models.length; m++) {
                    if (window.infoplusProductData.models[m].slug === selectedModel) {
                        orderType = window.infoplusProductData.models[m].order_type || 'editable';
                        break;
                    }
                }
            }
            var isVariantRequest = (orderType === 'requestable_with_variants');
            console.log('[SonderwunschPopup] Order type:', orderType, '| isVariantRequest:', isVariantRequest);

            // Heading & Description
            var $headingText     = $popup.find('#form_heading .elementor-heading-title');
            var $descriptionText = $popup.find('#form_description p').first();

            if ($headingText.length && _originalHeading === null) {
                _originalHeading = $headingText.text();
            }
            if ($descriptionText.length && _originalDescription === null) {
                _originalDescription = $descriptionText.text();
            }

            if (isVariantRequest) {
                if ($headingText.length)     $headingText.text(RWV_HEADING);
                if ($descriptionText.length) $descriptionText.text(RWV_DESCRIPTION);
            } else {
                if ($headingText.length && _originalHeading !== null)         $headingText.text(_originalHeading);
                if ($descriptionText.length && _originalDescription !== null) $descriptionText.text(_originalDescription);
            }

            // Product field
            var $productField = $popup.find('#form-field-product');
            var productName = '';
            var productSku  = '';

            if (window.infoplusProductData) {
                productName = window.infoplusProductData.name || '';
                productSku  = window.infoplusProductData.sku  || '';
            }

            if (!productName) {
                productName = $('.product_title').text().trim() || $('h1.elementor-heading-title').first().text().trim();
            }

            var productInfo = productName;
            if (productSku) productInfo += ' (SKU: ' + productSku + ')';

            if ($productField.length && productInfo) {
                $productField.val(productInfo);
                setReadonlyField($productField);
                console.log('[SonderwunschPopup] ✓ Product value injected');
            }

            // Stück field
            var $stuckField = $popup.find('#form-field-stuck');
            if ($stuckField.length) {
                var qty = 1;
                if (window.infoplusProductState && window.infoplusProductState.quantity) {
                    qty = window.infoplusProductState.quantity;
                } else {
                    var $qtyInput = $('.quantity input[type="number"], .infoplus-qty-input, input[name="quantity"]');
                    if ($qtyInput.length) {
                        qty = parseInt($qtyInput.val()) || 1;
                    }
                }
                $stuckField.val(qty);
                console.log('[SonderwunschPopup] ✓ Stück value injected:', qty);
            }

            // Befestigung dropdown
            var $befField = $popup.find('#form-field-befestigung');

            if ($befField.is('select') && window.infoplusProductData) {
                var pd = window.infoplusProductData;
                var bef_terms = [];

                if (pd.attributes && pd.attributes['pa_befestigung'] && pd.attributes['pa_befestigung'].terms) {
                    bef_terms = pd.attributes['pa_befestigung'].terms;
                }

                if (!bef_terms.length && pd.variations) {
                    var seen = {};
                    for (var v = 0; v < pd.variations.length; v++) {
                        var bef_val = pd.variations[v].attributes && pd.variations[v].attributes['attribute_pa_befestigung'];
                        if (bef_val && !seen[bef_val]) {
                            seen[bef_val] = true;
                            var bef_name = bef_val.replace(/-/g, ' ').replace(/bw/g, function(c) { return c.toUpperCase(); });
                            bef_terms.push({ slug: bef_val, name: bef_name });
                        }
                    }
                }

                if (bef_terms.length) {
                    $befField.empty();
                    $befField.append($('<option>', { value: '', text: 'Bitte wählen…' }));
                    for (var i = 0; i < bef_terms.length; i++) {
                        $befField.append($('<option>', { value: bef_terms[i].slug, text: bef_terms[i].name }));
                    }
                    console.log('[SonderwunschPopup] ✓ Befestigung populated');
                }
            }

            // Modell & Variante fields
            var $modellField   = $popup.find('#form-field-modell');
            var $varianteField = $popup.find('#form-field-variante');

            function getFieldWrapper($field) {
                return $field.closest('.elementor-field-group');
            }

            if (isVariantRequest) {
                var ps  = window.infoplusProductState || {};
                var pd2 = window.infoplusProductData  || {};

                if ($modellField.length) {
                    getFieldWrapper($modellField).show();
                    var modelName = '';
                    if (ps.selectedModel && pd2.models) {
                        for (var mi = 0; mi < pd2.models.length; mi++) {
                            if (pd2.models[mi].slug === ps.selectedModel) {
                                modelName = pd2.models[mi].name;
                                break;
                            }
                        }
                    }
                    $modellField.val(modelName);
                    setReadonlyField($modellField);
                }

                if ($varianteField.length) {
                    getFieldWrapper($varianteField).show();
                    var parts = [];

                    if (ps.selectedAttributes && pd2.attributes) {
                        for (var attrKey in ps.selectedAttributes) {
                            if (!ps.selectedAttributes.hasOwnProperty(attrKey)) continue;
                            var attrValue = ps.selectedAttributes[attrKey];
                            if (!attrValue) continue;

                            var attrData = pd2.attributes[attrKey];
                            var attrName = attrData
                                ? attrData.name
                                : attrKey.replace('pa_', '').replace(/_/g, ' ');

                            var termName = attrValue;
                            if (attrData && attrData.terms) {
                                for (var t = 0; t < attrData.terms.length; t++) {
                                    if (attrData.terms[t].slug === attrValue) {
                                        termName = attrData.terms[t].name;
                                        break;
                                    }
                                }
                            }
                            parts.push(attrName + ': ' + termName);
                        }
                    }

                    var varianteText = parts.join(', ');
                    $varianteField.val(varianteText);
                    setReadonlyField($varianteField);
                }

            } else {
                if ($modellField.length) {
                    getFieldWrapper($modellField).hide();
                    $modellField.val('');
                    clearReadonlyField($modellField);
                }
                if ($varianteField.length) {
                    getFieldWrapper($varianteField).hide();
                    $varianteField.val('');
                    clearReadonlyField($varianteField);
                }
            }

            // Reorder fields for grid layout
            reorderFormFields($popup);

        }, 100);
    });

})(jQuery);