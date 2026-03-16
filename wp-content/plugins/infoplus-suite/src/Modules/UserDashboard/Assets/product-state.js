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
            }
            
            // Listen for WooCommerce variation events
            this.bindWooCommerceEvents();
            
            console.log('[InfoplusState] Initialized', this.productData);
        },

        /**
         * Set selected model and update available options
         */
        setModel: function(modelSlug) {
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
            
            // Emit event
            this.emit('model:changed', { 
                model: modelSlug,
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
            
            // Check if all required attributes are selected
            const selectedCount = Object.values(selections).filter(v => v).length;
            if (selectedCount === 0) {
                this.selectedVariation = null;
                return;
            }
            
            // Find matching variation
            const matchingVariation = variations.find(variation => {
                return Object.entries(selections).every(([attr, value]) => {
                    if (!value) return true; // Unselected attributes match anything
                    const varValue = variation.attributes[attr];
                    return !varValue || varValue === value;
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
    });

})(jQuery, window);
