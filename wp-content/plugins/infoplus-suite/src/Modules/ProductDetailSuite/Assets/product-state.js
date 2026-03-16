/**
 * Infoplus Product State Manager
 * 
 * Central state management for product detail widgets.
 * Handles communication between widgets via events and shared state.
 */

(function($, window) {
    'use strict';

    // Initialize global state object
    window.infoplusProductState = {
        // Current selections
        selectedModel: null,
        selectedAttributes: {},
        selectedVariation: null,
        
        // Product data (loaded from inline script or API)
        productData: window.infoplusProductData || null,
        
        // Available options based on current selection
        availableAttributes: {},
        
        // Event listeners registry
        listeners: {},

        /**
         * Initialize state from product data
         */
        init: function() {
            if (this.productData) {
                this.buildAvailableAttributes();
                
                // Pre-populate selectedAttributes with ALL variation attribute keys
                // so they always exist (prevents matching with missing keys)
                if (this.productData.attributes) {
                    Object.entries(this.productData.attributes).forEach(function(entry) {
                        var attrKey = entry[0];
                        var attr = entry[1];
                        if (attr.variation && !this.selectedAttributes.hasOwnProperty(attrKey)) {
                            this.selectedAttributes[attrKey] = null;
                        }
                    }.bind(this));
                }
            }
            
            // Listen for WooCommerce variation events
            this.bindWooCommerceEvents();

            // Pre-select the default variation if configured in admin
            this.applyDefaultVariation();

            console.log('[InfoplusState] Initialized', this.productData, 'attributes:', this.selectedAttributes);
        },

        /**
         * Pre-select the default variation stored in infoplusProductData.default_variation_id.
         * Runs the full event chain: setModel() → setAttribute() → variation:found.
         * Silently skipped if the variation no longer exists or is out of stock.
         */
        applyDefaultVariation: function() {
            if (!this.productData) return;

            var defaultId = this.productData.default_variation_id;
            if (!defaultId) return;

            var variations = this.productData.variations || [];
            var defaultVar = null;

            for (var i = 0; i < variations.length; i++) {
                if (variations[i].id == defaultId) {
                    defaultVar = variations[i];
                    break;
                }
            }

            // Silently skip if variation not found or out of stock
            if (!defaultVar || !defaultVar.is_in_stock) {
                console.log('[InfoplusState] Default variation', defaultId, 'skipped (not found or out of stock)');
                return;
            }

            console.log('[InfoplusState] Applying default variation', defaultId, defaultVar);

            // attrs keys use WC convention: 'attribute_pa_model', 'attribute_pa_format', …
            var attrs = defaultVar.attributes || {};

            // 1) Set model first if pa_model is a variation attribute
            //    setModel() resets all other attributes, so it must come first.
            var modelValue = attrs['attribute_pa_model'];
            if (modelValue) {
                this.setModel(modelValue);
            }

            // 2) Set each remaining variation attribute
            for (var attrKey in attrs) {
                var bareKey = attrKey.replace('attribute_', ''); // 'pa_format', …
                if (bareKey === 'pa_model') continue;            // already handled above
                var attrValue = attrs[attrKey];
                if (attrValue) {
                    this.setAttribute(bareKey, attrValue);
                }
            }
        },

        /**
         * Set selected model and update available options
         * @param {string} modelSlug - The model slug to select
         * @param {object} options - Optional settings
         * @param {string} options.modelImage - URL of the model image
         * @param {boolean} options.syncImage - Whether to sync the product image widget
         */
        setModel: function(modelSlug, options) {
            options = options || {};
            
            if (this.selectedModel === modelSlug) return;
            
            this.selectedModel = modelSlug;
            this.selectedAttributes['pa_model'] = modelSlug;
            
            // Reset other selections when model changes
            Object.keys(this.selectedAttributes).forEach(key => {
                if (key !== 'pa_model') {
                    this.selectedAttributes[key] = null;
                }
            });
            
            this.selectedVariation = null;
            
            // Update available options
            this.buildAvailableAttributes();
            
            // Emit event with model image if provided
            this.emit('model:changed', { 
                model: modelSlug,
                modelImage: options.modelImage || null,
                syncImage: options.syncImage !== false, // Default to true
                availableAttributes: this.availableAttributes 
            });
            
            // Try to find matching variation
            this.findMatchingVariation();
            
            // Sync with native WooCommerce
            this.syncToWooCommerce();
        },

        /**
         * Set attribute value
         */
        setAttribute: function(attribute, value) {
            this.selectedAttributes[attribute] = value;
            
            // Update available options for other attributes
            this.buildAvailableAttributes();
            
            // Emit event
            this.emit('attribute:changed', {
                attribute: attribute,
                value: value,
                selectedAttributes: this.selectedAttributes,
                availableAttributes: this.availableAttributes
            });
            
            // Try to find matching variation
            this.findMatchingVariation();
            
            // Sync with native WooCommerce
            this.syncToWooCommerce();
        },

        /**
         * Build available attribute options based on current selections
         */
        buildAvailableAttributes: function() {
            if (!this.productData || !this.productData.variations) {
                this.availableAttributes = {};
                return;
            }

            const variations = this.productData.variations;
            const currentSelections = this.selectedAttributes;
            
            // Get all attribute keys
            const attributeKeys = new Set();
            variations.forEach(v => {
                Object.keys(v.attributes || {}).forEach(key => attributeKeys.add(key));
            });
            
            this.availableAttributes = {};
            
            attributeKeys.forEach(attrKey => {
                // Find which values are available for this attribute
                // given the current selections of other attributes
                const availableValues = new Set();
                
                variations.forEach(variation => {
                    // Check if this variation matches all other selected attributes
                    let matches = true;
                    
                    Object.entries(currentSelections).forEach(([selectedAttr, selectedVal]) => {
                        if (selectedAttr === attrKey) return; // Skip the attribute we're checking
                        if (!selectedVal) return; // Skip unselected attributes
                        
                        const variationVal = variation.attributes[selectedAttr];
                        if (variationVal && variationVal !== selectedVal) {
                            matches = false;
                        }
                    });
                    
                    if (matches && variation.attributes[attrKey]) {
                        availableValues.add(variation.attributes[attrKey]);
                    }
                });
                
                this.availableAttributes[attrKey] = Array.from(availableValues);
            });
        },

        /**
         * Find matching variation based on current selections
         */
        findMatchingVariation: function() {
            if (!this.productData || !this.productData.variations) {
                this.selectedVariation = null;
                return;
            }

            const variations = this.productData.variations;
            const selections = this.selectedAttributes;

            // Check if any attributes are selected at all
            const selectedCount = Object.values(selections).filter(v => v).length;
            if (selectedCount === 0) {
                this.selectedVariation = null;
                return;
            }

            // Bug fix: require ALL variation-type attributes to be explicitly selected
            // before treating a variation as "found". Prevents premature editor button
            // enable right after model selection (when other dropdowns are still blank).
            if (this.productData.attributes) {
                const allVariationAttrsSelected = Object.entries(this.productData.attributes).every(([attrKey, attr]) => {
                    if (!attr.variation) return true; // non-variation attributes are optional
                    return !!selections[attrKey];
                });
                if (!allVariationAttrsSelected) {
                    this.selectedVariation = null;
                    return;
                }
            }

            // Find matching variation.
            // Iterate the VARIATION's attributes (not our selections) to ensure every
            // attribute the variation specifies is matched by a user selection.
            // This prevents false matches when selectedAttributes is missing keys.
            const matchingVariation = variations.find(variation => {
                return Object.entries(variation.attributes).every(([varKey, varValue]) => {
                    if (!varValue) return true; // empty string in variation = "any", matches all
                    // Normalize key: 'attribute_pa_model' → 'pa_model'
                    const attrKey = varKey.replace('attribute_', '');
                    const selectedValue = selections[attrKey];
                    return selectedValue === varValue;
                });
            });

            if (matchingVariation) {
                this.selectedVariation = matchingVariation;
                this.emit('variation:found', { variation: matchingVariation });
            } else {
                this.selectedVariation = null;
                this.emit('variation:notfound', { selections: selections });
            }
        },

        /**
         * Sync selections to native WooCommerce form
         */
        syncToWooCommerce: function() {
            const $form = $('form.variations_form');
            if (!$form.length) return;
            
            Object.entries(this.selectedAttributes).forEach(([attr, value]) => {
                const selectName = attr.replace('pa_', 'attribute_pa_');
                const $select = $form.find(`select[name="${selectName}"]`);
                
                if ($select.length && value) {
                    $select.val(value).trigger('change');
                }
            });
        },

        /**
         * Bind to native WooCommerce variation events
         */
        bindWooCommerceEvents: function() {
            const self = this;
            const $form = $('form.variations_form');
            
            if (!$form.length) return;
            
            // Listen for WooCommerce found_variation
            $form.on('found_variation', function(e, variation) {
                console.log('[InfoplusState] WooCommerce found_variation', variation);
                
                // Guard: only emit variation:found if ALL variation attributes are selected.
                // WooCommerce can auto-match when remaining attributes have only one option,
                // but we require explicit user selection of every dropdown.
                if (variation && self.productData && self.productData.attributes) {
                    var allSelected = Object.entries(self.productData.attributes).every(function(entry) {
                        var attrKey = entry[0];
                        var attr = entry[1];
                        if (!attr.variation) return true;
                        return !!self.selectedAttributes[attrKey];
                    });
                    
                    if (!allSelected) {
                        console.log('[InfoplusState] WooCommerce found_variation ignored — not all attributes selected');
                        return;
                    }
                }
                
                // Update our state from WooCommerce
                if (variation) {
                    self.selectedVariation = variation;
                    self.emit('variation:found', { variation: variation, source: 'woocommerce' });
                }
            });
            
            // Listen for WooCommerce reset
            $form.on('reset_data', function() {
                console.log('[InfoplusState] WooCommerce reset_data');
                self.selectedVariation = null;
                self.emit('variation:reset', {});
            });
        },

        /**
         * Subscribe to an event
         */
        on: function(event, callback) {
            if (!this.listeners[event]) {
                this.listeners[event] = [];
            }
            this.listeners[event].push(callback);
            return this;
        },

        /**
         * Unsubscribe from an event
         */
        off: function(event, callback) {
            if (!this.listeners[event]) return this;
            
            if (callback) {
                this.listeners[event] = this.listeners[event].filter(cb => cb !== callback);
            } else {
                this.listeners[event] = [];
            }
            return this;
        },

        /**
         * Emit an event
         */
        emit: function(event, data) {
            console.log('[InfoplusState] Event:', event, data);
            
            // Call registered listeners
            if (this.listeners[event]) {
                this.listeners[event].forEach(callback => {
                    try {
                        callback(data);
                    } catch (e) {
                        console.error('[InfoplusState] Listener error:', e);
                    }
                });
            }
            
            // Also dispatch DOM event for external integrations
            document.dispatchEvent(new CustomEvent('infoplus:' + event, { detail: data }));
        },

        /**
         * Get model data by slug
         */
        getModelData: function(modelSlug) {
            if (!this.productData || !this.productData.models) return null;
            return this.productData.models.find(m => m.slug === modelSlug);
        },

        /**
         * Check if an attribute value is available
         */
        isAttributeValueAvailable: function(attribute, value) {
            const available = this.availableAttributes[attribute];
            return available && available.includes(value);
        }
    };

    // Initialize on document ready
    $(function() {
        window.infoplusProductState.init();

        // Make Anfrage form product field readonly (handles Elementor popups too)
        function makeProductFieldReadonly() {
            var $productField = $('#form-field-product');
            if ($productField.length && !$productField.attr('readonly')) {
                $productField.attr('readonly', true);
            }
        }

        // Try immediately (for inline forms)
        makeProductFieldReadonly();

        // Re-check when Elementor popup opens
        $(document).on('elementor/popup/show', function() {
            setTimeout(makeProductFieldReadonly, 100);
        });

        // Fallback: MutationObserver for any dynamically added form fields
        if (window.MutationObserver) {
            var observer = new MutationObserver(function(mutations) {
                for (var i = 0; i < mutations.length; i++) {
                    if (mutations[i].addedNodes.length) {
                        makeProductFieldReadonly();
                    }
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    });

})(jQuery, window);
