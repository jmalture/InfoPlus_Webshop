/**
 * Elementor Bulk Calculator
 *
 * Fetches bulk pricing from the API and drives the qty controls,
 * per-unit / total price display, and min/max qty limits.
 * The visual bulk-price grid is now handled by the BulkPriceTable widget —
 * this script only manages data, qty, and price values.
 *
 * Listens to infoplusProductState for variation changes.
 * Uses the existing /infoplus/v1/products/prices?sku=XXX endpoint
 */
jQuery(function($) {
    'use strict';

    console.log('[BulkCalculator] Script loaded');

    let prevPieceValue = null;
    let prevTotalValue = null;
    let minQty = 1;
    let maxQty = 99999;
    let lastBreaks = [];
    let currentProductId = null;

    /** Check if the currently selected model is requestable (no pricing). */
    function isRequestableModel() {
        var ps = window.infoplusProductState;
        if (!ps || !ps.selectedModel || !ps.productData || !ps.productData.models) return false;
        var models = ps.productData.models;
        for (var i = 0; i < models.length; i++) {
            if (models[i].slug === ps.selectedModel) {
                var ot = models[i].order_type || 'editable';
                return ot === 'requestable' || ot === 'requestable_with_variants';
            }
        }
        return false;
    }

    /*
     * Element lookup. The Elementor template marks its widgets with CSS IDs
     * (#qty_value, #qty_minus, #qty_plus, #Stuckpreis_value h2,
     * #Gesamtpreis_value h2, #Mindestanzahl_value). A classic theme can use
     * the same IDs or data attributes: data-ip-qty="value|minus|plus|
     * unit-price|total-price|min" and data-ip-qty-row on the row container.
     */
    var SEL_VALUE = '#qty_value, [data-ip-qty="value"]';
    var SEL_MINUS = '#qty_minus, [data-ip-qty="minus"]';
    var SEL_PLUS  = '#qty_plus, [data-ip-qty="plus"]';
    var SEL_PIECE = '#Stuckpreis_value h2, [data-ip-qty="unit-price"]';
    var SEL_TOTAL = '#Gesamtpreis_value h2, [data-ip-qty="total-price"]';
    var SEL_MIN   = '#Mindestanzahl_value, [data-ip-qty="min"]';

    /** The qty row: [data-ip-qty-row] in a theme, else the Elementor container around #qty_value. */
    function getQtyRow() {
        var $row = $('[data-ip-qty-row]');
        if ($row.length) return $row;
        return $('#qty_value').closest('.e-con');
    }

    /** Hide the entire qty row (buttons + input). */
    function hideQtyRow() {
        var $qtyRow = getQtyRow();
        if ($qtyRow.length) $qtyRow.hide();
    }

    /** Show the qty row. */
    function showQtyRow() {
        var $qtyRow = getQtyRow();
        if ($qtyRow.length) $qtyRow.show();
    }

    // --- Animation Helpers ---
    function animateText($el, newText, prevValStore) {
        if (!$el.length) return;
        if ($el.text() === newText) return;
        $el.fadeOut(120, function() {
            $el.text(newText).fadeIn(180);
        });
        if (typeof prevValStore === 'function') prevValStore(newText);
    }

    // --- Price Formatting ---
    function formatPrice(num) {
        return num.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }

    // --- Bulk Price Calculation ---
    function getBulkPriceForQty(breaks, qty) {
        if (!Array.isArray(breaks) || !breaks.length) return null;

        for (let i = 0; i < breaks.length; i++) {
            let from = parseInt(breaks[i].from, 10);
            let to   = breaks[i].to ? parseInt(breaks[i].to, 10) : null;

            if ((qty >= from) && (to === null || to === 0 || to === '' || qty <= to)) {
                return parseFloat(String(breaks[i].value).replace(',', '.'));
            }
        }
        return null;
    }

    function updateIndividualAndTotalPrice(qty) {
        let price  = getBulkPriceForQty(lastBreaks, qty);
        let $piece = $(SEL_PIECE);
        let $total = $(SEL_TOTAL);

        console.log('[BulkCalculator] Updating prices for qty:', qty, 'price:', price);

        if (price !== null) {
            animateText($piece, formatPrice(price));
            animateText($total, formatPrice(price * qty));
        } else {
            animateText($piece, '0,00 €');
            animateText($total, '0,00 €');
        }
    }

    // --- Min/Max Logic ---
    function setQtyLimits(min, max) {
        minQty = Number.isFinite(min) ? min : 1;
        maxQty = Number.isFinite(max) ? max : 99999;

        const $val = $(SEL_VALUE);
        if (!$val.length) return;

        let current = parseInt($val.val(), 10);
        if (isNaN(current)) current = minQty;
        if (current < minQty) current = minQty;
        if (current > maxQty) current = maxQty;

        $val.val(current)
            .attr('min', minQty)
            .attr('max', maxQty)
            .attr('aria-valuemin', minQty)
            .attr('aria-valuemax', maxQty)
            .attr('aria-valuenow', current);

        // Keep native Woo qty in sync
        const $nativeQty = $('form.cart').find('input.qty');
        if ($nativeQty.length) $nativeQty.val(current).trigger('change');

        updateButtonStates(current);
    }

    function setQtyControlsEnabled(enabled) {
        const $minus = $(SEL_MINUS);
        const $plus  = $(SEL_PLUS);
        const $val   = $(SEL_VALUE);

        $minus.add($plus).prop('disabled', !enabled).attr('aria-disabled', !enabled)
              .toggleClass('qty-disabled', !enabled);
        $val.prop('disabled', !enabled).attr('aria-disabled', !enabled)
            .toggleClass('qty-disabled', !enabled);

        if (!enabled) hideQtyMessage();
    }

    // --- +/- Button State Management ---
    function updateButtonStates(qty) {
        const $minus = $(SEL_MINUS);
        const $plus  = $(SEL_PLUS);

        $minus.prop('disabled', qty <= minQty).toggleClass('qty-limit-reached', qty <= minQty);
        $plus.prop('disabled', qty >= maxQty).toggleClass('qty-limit-reached', qty >= maxQty);
    }

    // --- Qty Message / Toast ---
    function showQtyMessage(text, type) {
        type = type || 'info'; // 'info', 'warning', 'error'
        let $msg = $('#qty-limit-message');
        if (!$msg.length) {
            // Insert after the qty wrapper parent (contains -, input, +)
            var $wrapper = $(SEL_VALUE).closest('[data-ip-qty-controls], .elementor-widget-container, .qty-wrapper, [class*="qty"]');
            if (!$wrapper.length) $wrapper = $(SEL_PLUS).parent();
            $msg = $('<div id="qty-limit-message"></div>').insertAfter($wrapper);
        }
        $msg.stop(true).attr('class', 'qty-limit-msg qty-limit-msg--' + type)
            .text(text).fadeIn(200);

        clearTimeout($msg.data('hideTimer'));
        $msg.data('hideTimer', setTimeout(function() { $msg.fadeOut(300); }, 4000));
    }

    function hideQtyMessage() {
        $('#qty-limit-message').stop(true).fadeOut(200);
    }

    // --- Helper to get min bulk qty from API breaks ---
    function getMinBulkQty(breaks) {
        if (!Array.isArray(breaks) || !breaks.length) return null;
        let min = Math.min(...breaks
            .map(b => parseInt(b.from, 10))
            .filter(v => !isNaN(v))
        );
        return isFinite(min) ? min : null;
    }

    // --- Qty Wrapper Wire-up ---
    function wireQtyWrapper() {
        const $minus = $(SEL_MINUS);
        const $plus  = $(SEL_PLUS);
        const $val   = $(SEL_VALUE);

        if (!$minus.length || !$plus.length || !$val.length) {
            console.log('[BulkCalculator] Qty controls not found');
            return;
        }

        $minus.add($plus).css('cursor', 'pointer');

        function clamp(v) {
            let n = parseInt(v, 10);
            if (isNaN(n)) n = minQty;
            if (n < minQty) n = minQty;
            if (n > maxQty) n = maxQty;
            return n;
        }

        function apply(qty, sourceEvent) {
            const q = clamp(qty);
            $val.val(q).attr('aria-valuenow', q);

            // Sync native Woo qty
            const $nativeQty = $('form.cart').find('input.qty');
            if ($nativeQty.length) $nativeQty.val(q).trigger('change');

            // Update price display and button states
            updateIndividualAndTotalPrice(q);
            updateButtonStates(q);

            // Emit qty:changed so BulkPriceTable and other widgets stay in sync.
            // source='calculator' prevents the echo coming back and re-triggering apply.
            if (!sourceEvent && window.infoplusProductState) {
                window.infoplusProductState.currentQty = q;
                window.infoplusProductState.emit('qty:changed', { qty: q, source: 'calculator' });
            }
        }

        $minus.off('click.qtywrap').on('click.qtywrap', function(e) {
            e.preventDefault();
            var next = (parseInt($val.val(), 10) || minQty) - 1;
            if (next < minQty) {
                showQtyMessage('Mindestbestellmenge: ' + minQty + ' Stück', 'warning');
                return;
            }
            hideQtyMessage();
            apply(next);
        });

        $plus.off('click.qtywrap').on('click.qtywrap', function(e) {
            e.preventDefault();
            var next = (parseInt($val.val(), 10) || minQty) + 1;
            if (next > maxQty) {
                showQtyMessage('Maximale Bestellmenge: ' + maxQty + ' Stück', 'warning');
                return;
            }
            hideQtyMessage();
            apply(next);
        });

        $val.off('keydown.qtywrap input.qtywrap blur.qtywrap')
            .on('keydown.qtywrap', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); $(this).blur(); }
            })
            .on('input.qtywrap', function() {
                // Strip non-digits
                var digits = this.value.replace(/[^\d]/g, '');
                if (this.value !== digits) this.value = digits;

                // Live price update on keyup
                var typed = parseInt(digits, 10);
                if (!isNaN(typed) && typed >= 1) {
                    updateIndividualAndTotalPrice(typed);
                    updateButtonStates(clamp(typed));

                    // Notify BulkPriceTable and other widgets
                    if (window.infoplusProductState) {
                        window.infoplusProductState.currentQty = typed;
                        window.infoplusProductState.emit('qty:changed', { qty: typed, source: 'calculator' });
                    }
                }
            })
            .on('blur.qtywrap', function() {
                var raw = parseInt(this.value, 10);

                if (isNaN(raw) || raw < 1) {
                    apply(minQty);
                    showQtyMessage('Bitte Menge eingeben. Mindestmenge: ' + minQty + ' Stück', 'warning');
                    return;
                }

                if (raw < minQty) {
                    apply(minQty);
                    showQtyMessage('Mindestbestellmenge: ' + minQty + ' Stück', 'warning');
                    return;
                }

                if (raw > maxQty) {
                    apply(maxQty);
                    showQtyMessage('Maximale Bestellmenge: ' + maxQty + ' Stück. Menge wurde angepasst.', 'warning');
                    return;
                }

                hideQtyMessage();
                apply(raw);
            });

        // Listen for qty:changed from external widgets (e.g. BulkPriceTable cell click).
        // source='calculator' events originated here — skip them to prevent echo loops.
        if (window.infoplusProductState) {
            window.infoplusProductState.on('qty:changed', function(data) {
                if (data.source === 'calculator') return;
                apply(data.qty, true); // true = sourceEvent, suppresses re-emit
            });
        }

        console.log('[BulkCalculator] Qty wrapper wired');
    }

    // --- Fetch Bulk Prices from API using SKU ---
    function fetchBulkPrices(sku) {
        if (!sku) {
            console.log('[BulkCalculator] No SKU provided - skipping API call');
            return;
        }

        // Use the existing API endpoint that works with SKU
        let apiUrl = '/wp-json/infoplus/v1/products/prices?sku=' + encodeURIComponent(sku);

        console.log('[BulkCalculator] Fetching prices from:', apiUrl);

        $.getJSON(apiUrl, function(prices) {
            console.log('[BulkCalculator] API response:', prices);

            // The API returns an array directly
            lastBreaks = Array.isArray(prices) ? prices : [];
            console.log('[BulkCalculator] Processed breaks:', lastBreaks);

            // Derive min: max of (ACF mindestanzahl, first API break)
            var acfMin = 1;
            if (window.currentProduct && window.currentProduct.mindestanzahl) {
                acfMin = parseInt(window.currentProduct.mindestanzahl, 10) || 1;
            } else if (window.infoplusProductData && window.infoplusProductData.mindestanzahl) {
                acfMin = parseInt(window.infoplusProductData.mindestanzahl, 10) || 1;
            }
            const apiMin = getMinBulkQty(lastBreaks);
            const finalMin = Math.max(acfMin, apiMin || 1);

            // Derive max from last price break's 'to' value
            var finalMax = 99999;
            if (lastBreaks.length > 0) {
                var lastTo = parseInt(lastBreaks[lastBreaks.length - 1].to, 10);
                if (!isNaN(lastTo) && lastTo > 0) finalMax = lastTo;
            }

            console.log('[BulkCalculator] Qty limits — min:', finalMin, 'max:', finalMax, '(ACF:', acfMin, 'API:', apiMin, ')');

            setQtyLimits(finalMin, finalMax);
            setQtyControlsEnabled(true);

            // Notify info box (and other widgets) of the effective minimum
            if (window.infoplusProductState) {
                window.infoplusProductState.emit('minqty:changed', { minQty: finalMin });
            }

            // Set qty to min and refresh prices
            const $val = $(SEL_VALUE);
            if ($val.length) {
                $val.val(finalMin).attr('aria-valuenow', finalMin);
                updateIndividualAndTotalPrice(finalMin);
                updateButtonStates(finalMin);
            }

            // Update minimum quantity display if it exists
            $(SEL_MIN).text(finalMin);

        }).fail(function(xhr, status, error) {
            console.error('[BulkCalculator] API error:', error);
            console.error('[BulkCalculator] Response:', xhr.responseText);

            // Reset to defaults on error
            lastBreaks = [];
            setQtyLimits(1, 99999);
            if (window.infoplusProductState) {
                window.infoplusProductState.emit('minqty:changed', { minQty: 1 });
            }
        });
    }

    // --- Handle Variation Found ---
    function handleVariationFound(data) {
        console.log('[BulkCalculator] Variation found:', data);

        const variation = data.variation;
        if (!variation) {
            console.log('[BulkCalculator] No variation data');
            return;
        }

        // Get the SKU from the variation
        let sku = variation.sku;

        // If no SKU yet, try to get it from the variation ID using productData
        if (!sku && variation.id && window.infoplusProductData && window.infoplusProductData.variations) {
            const fullVariation = window.infoplusProductData.variations.find(
                v => v.id === variation.id || v.variation_id === variation.id
            );
            if (fullVariation) {
                sku = fullVariation.sku;
                console.log('[BulkCalculator] Found SKU from productData:', sku);
            }
        }

        // Also try variation_id
        if (!sku && variation.variation_id && window.infoplusProductData && window.infoplusProductData.variations) {
            const fullVariation = window.infoplusProductData.variations.find(
                v => v.id === variation.variation_id || v.variation_id === variation.variation_id
            );
            if (fullVariation) {
                sku = fullVariation.sku;
                console.log('[BulkCalculator] Found SKU from productData (via variation_id):', sku);
            }
        }

        if (!sku || isRequestableModel()) {
            console.log('[BulkCalculator] No SKU or requestable model — hiding qty row');
            lastBreaks = [];
            hideQtyRow();
            return;
        }

        showQtyRow();

        console.log('[BulkCalculator] Variation SKU:', sku);

        setQtyControlsEnabled(true);

        // Fetch bulk prices using the SKU
        fetchBulkPrices(sku);
    }

    // --- Inject Styles ---
    function injectStyles() {
        if ($('#bulk-calc-styles').length) return;
        $('<style id="bulk-calc-styles">').text(
            /* Message toast */
            '.qty-limit-msg {' +
                'display: none;' +
                'text-align: center;' +
                'font-size: 12px;' +
                'padding: 6px 14px;' +
                'border-radius: 6px;' +
                'margin-top: 8px;' +
            '}' +
            '.qty-limit-msg--warning {' +
                'background: #fff3cd;' +
                'color: #856404;' +
                'border: 1px solid #ffc107;' +
            '}' +
            '.qty-limit-msg--error {' +
                'background: #f8d7da;' +
                'color: #721c24;' +
                'border: 1px solid #f5c6cb;' +
            '}' +
            '.qty-limit-msg--info {' +
                'background: #d1ecf1;' +
                'color: #0c5460;' +
                'border: 1px solid #bee5eb;' +
            '}' +
            /* Button limit state */
            '#qty_minus.qty-limit-reached,' +
            '#qty_plus.qty-limit-reached,' +
            '[data-ip-qty].qty-limit-reached {' +
                'opacity: 0.35;' +
                'cursor: not-allowed;' +
            '}' +
            ''
        ).appendTo('head');
    }

    // --- Initialize ---
    function init() {
        console.log('[BulkCalculator] Initializing...');
        injectStyles();

        // Get product ID from global data
        if (window.infoplusProductData) {
            currentProductId = window.infoplusProductData.id;
            console.log('[BulkCalculator] Product ID:', currentProductId);
        } else if (window.currentProduct) {
            currentProductId = window.currentProduct.id;
            console.log('[BulkCalculator] Product ID (from currentProduct):', currentProductId);
        }

        // Hide native Woo fields
        $('.single_variation_wrap, .woocommerce-variation-add-to-cart, .woocommerce-variation-price').hide();

        // Initial state
        setQtyControlsEnabled(false);

        // Wire up quantity controls
        wireQtyWrapper();

        // Initial price display
        updateIndividualAndTotalPrice(1);

        // Set initial zeros
        let $piece = $(SEL_PIECE);
        let $total = $(SEL_TOTAL);
        animateText($piece, '0,00 €', v => prevPieceValue = v);
        animateText($total, '0,00 €', v => prevTotalValue = v);

        // Listen to ProductDetailSuite state events
        if (window.infoplusProductState) {
            console.log('[BulkCalculator] Binding to infoplusProductState events');

            window.infoplusProductState.on('variation:found', handleVariationFound);

            window.infoplusProductState.on('variation:reset', function() {
                console.log('[BulkCalculator] Variation reset');
                setQtyControlsEnabled(false);
                hideQtyMessage();
                lastBreaks = [];
                hideQtyRow();
                updateIndividualAndTotalPrice(1);
            });

            window.infoplusProductState.on('model:changed', function() {
                if (isRequestableModel()) {
                    hideQtyRow();
                }
            });

            // Check if there's already a selected variation
            if (window.infoplusProductState.selectedVariation) {
                console.log('[BulkCalculator] Found existing selected variation');
                handleVariationFound({ variation: window.infoplusProductState.selectedVariation });
            }
        } else {
            console.log('[BulkCalculator] infoplusProductState not found, waiting...');

            let attempts = 0;
            const maxAttempts = 20;
            const checkInterval = setInterval(function() {
                attempts++;
                if (window.infoplusProductState) {
                    clearInterval(checkInterval);
                    console.log('[BulkCalculator] infoplusProductState now available, binding...');

                    window.infoplusProductState.on('variation:found', handleVariationFound);
                    window.infoplusProductState.on('variation:reset', function() {
                        setQtyControlsEnabled(false);
                        hideQtyMessage();
                        lastBreaks = [];
                        hideQtyRow();
                    });
                    window.infoplusProductState.on('model:changed', function() {
                        if (isRequestableModel()) {
                            hideQtyRow();
                        }
                    });

                    if (window.infoplusProductState.selectedVariation) {
                        handleVariationFound({ variation: window.infoplusProductState.selectedVariation });
                    }
                } else if (attempts >= maxAttempts) {
                    clearInterval(checkInterval);
                    console.warn('[BulkCalculator] infoplusProductState not found after waiting');
                    bindNativeWooEvents();
                }
            }, 250);
        }

        if (!window.infoplusProductState) {
            bindNativeWooEvents();
        }

        console.log('[BulkCalculator] Initialization complete');
    }

    // --- Fallback: Native WooCommerce Events ---
    function bindNativeWooEvents() {
        const $form = $('form.variations_form');

        if (!$form.length) {
            console.log('[BulkCalculator] No native variations form found');
            return;
        }

        console.log('[BulkCalculator] Binding to native WooCommerce events');

        $form.on('found_variation', function(event, variation) {
            console.log('[BulkCalculator] Native found_variation triggered:', variation);
            handleVariationFound({ variation: variation });
        });

        $form.on('reset_data', function() {
            console.log('[BulkCalculator] Native reset_data triggered');
            setQtyControlsEnabled(false);
            hideQtyMessage();
            lastBreaks = [];
            updateIndividualAndTotalPrice(1);
        });
    }

    // --- Start ---
    $(document).ready(init);
});
