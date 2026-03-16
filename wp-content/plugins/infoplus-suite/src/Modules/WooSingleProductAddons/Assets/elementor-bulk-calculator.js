/**
 * Elementor Bulk Calculator
 * 
 * Integrates with ProductDetailSuite widgets to display bulk pricing.
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

    // --- Bulk Table Update ---
    function updateBulkGridFromAPI(breaks) {
        console.log('[BulkCalculator] Updating bulk grid with:', breaks);
        let $rows = $('#bulk_table .bulk_item');
        
        if (!$rows.length) {
            console.warn('[BulkCalculator] No bulk_item rows found in #bulk_table');
            return;
        }
        
        $rows.each(function(i) {
            let $row   = $(this);
            let $count = $row.find('.bulk_count p');
            let $price = $row.find('.bulk_price p');
            let data   = breaks[i];

            let valid =
                data &&
                (typeof data.from === "string" || typeof data.from === "number") &&
                (data.to === "" || typeof data.to === "string" || typeof data.to === "number") &&
                !isNaN(parseFloat(data.value));

            if (valid) {
                // Show the "to" value if present, otherwise show "from+"
                let countText = data.to && data.to !== "" && data.to !== 0 ? String(data.to) : (data.from + "+");
                let priceText = Number(data.value).toLocaleString('de-DE', {minimumFractionDigits: 2}) + " €";
                animateText($count, countText);
                animateText($price, priceText);
                $row.show();
            } else {
                animateText($price, "–");
                $row.show();
            }
        });
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
        let $piece = $('#Stuckpreis_value h2');
        let $total = $('#Gesamtpreis_value h2');
        
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

        const $val = $('#qty_value');
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
        const $minus = $('#qty_minus');
        const $plus  = $('#qty_plus');
        const $val   = $('#qty_value');

        $minus.add($plus).prop('disabled', !enabled).attr('aria-disabled', !enabled)
              .toggleClass('qty-disabled', !enabled);
        $val.prop('disabled', !enabled).attr('aria-disabled', !enabled)
            .toggleClass('qty-disabled', !enabled);

        if (!enabled) hideQtyMessage();
    }

    // --- +/- Button State Management ---
    function updateButtonStates(qty) {
        const $minus = $('#qty_minus');
        const $plus  = $('#qty_plus');

        $minus.prop('disabled', qty <= minQty).toggleClass('qty-limit-reached', qty <= minQty);
        $plus.prop('disabled', qty >= maxQty).toggleClass('qty-limit-reached', qty >= maxQty);
    }

    // --- Qty Message / Toast ---
    function showQtyMessage(text, type) {
        type = type || 'info'; // 'info', 'warning', 'error'
        let $msg = $('#qty-limit-message');
        if (!$msg.length) {
            // Insert after the qty wrapper parent (contains -, input, +)
            var $wrapper = $('#qty_value').closest('.elementor-widget-container, .qty-wrapper, [class*="qty"]');
            if (!$wrapper.length) $wrapper = $('#qty_plus').parent();
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

    // --- Active Price Tier Highlight ---
    function highlightActiveTier(qty) {
        var $rows = $('#bulk_table .bulk_item');
        if (!$rows.length || !lastBreaks.length) return;

        $rows.removeClass('bulk_item--active');
        for (var i = 0; i < lastBreaks.length; i++) {
            var from = parseInt(lastBreaks[i].from, 10);
            var to   = parseInt(lastBreaks[i].to, 10);
            if (qty >= from && qty <= to) {
                $rows.eq(i).addClass('bulk_item--active');
                break;
            }
        }
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
        const $minus = $('#qty_minus');
        const $plus  = $('#qty_plus');
        const $val   = $('#qty_value');

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

        function apply(qty) {
            const q = clamp(qty);
            $val.val(q).attr('aria-valuenow', q);

            // Sync native Woo qty
            const $nativeQty = $('form.cart').find('input.qty');
            if ($nativeQty.length) $nativeQty.val(q).trigger('change');

            // Update price display, button states, active tier
            updateIndividualAndTotalPrice(q);
            updateButtonStates(q);
            highlightActiveTier(q);
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
                    highlightActiveTier(typed);
                    updateButtonStates(clamp(typed));
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
            
            updateBulkGridFromAPI(lastBreaks);

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
            
            // Set qty to min and refresh prices
            const $val = $('#qty_value');
            if ($val.length) {
                $val.val(finalMin).attr('aria-valuenow', finalMin);
                updateIndividualAndTotalPrice(finalMin);
                highlightActiveTier(finalMin);
                updateButtonStates(finalMin);
            }

            // Update minimum quantity display if it exists
            $('#Mindestanzahl_value').text(finalMin);
            
        }).fail(function(xhr, status, error) {
            console.error('[BulkCalculator] API error:', error);
            console.error('[BulkCalculator] Response:', xhr.responseText);
            
            // Reset to defaults on error
            lastBreaks = [];
            updateBulkGridFromAPI([]);
            setQtyLimits(1, 99999);
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
        // SKU can be in different places depending on the source:
        // - Our ProductDetailSuite: variation.sku
        // - Native WooCommerce: variation.sku (but sometimes not present)
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
        
        if (!sku) {
            console.log('[BulkCalculator] No SKU found in variation data:', variation);
            // Reset pricing since we can't fetch without SKU
            lastBreaks = [];
            updateBulkGridFromAPI([]);
            return;
        }
        
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
            '#qty_plus.qty-limit-reached {' +
                'opacity: 0.35;' +
                'cursor: not-allowed;' +
            '}' +
            /* Active price tier */
            '.bulk_item--active {' +
                'background: rgba(233, 30, 140, 0.08);' +
                'border-radius: 6px;' +
                'transition: background 0.2s ease;' +
            '}' +
            '.bulk_item--active .bulk_price p {' +
                'font-weight: 700;' +
                'color: #e91e8c;' +
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
        let $piece = $('#Stuckpreis_value h2');
        let $total = $('#Gesamtpreis_value h2');
        animateText($piece, '0,00 €', v => prevPieceValue = v);
        animateText($total, '0,00 €', v => prevTotalValue = v);
        
        // Listen to ProductDetailSuite state events
        if (window.infoplusProductState) {
            console.log('[BulkCalculator] Binding to infoplusProductState events');
            
            // When a variation is found (all attributes selected)
            window.infoplusProductState.on('variation:found', handleVariationFound);
            
            // When variation is reset
            window.infoplusProductState.on('variation:reset', function() {
                console.log('[BulkCalculator] Variation reset');
                setQtyControlsEnabled(false);
                hideQtyMessage();
                lastBreaks = [];
                updateBulkGridFromAPI([]);
                $('#bulk_table .bulk_item').removeClass('bulk_item--active');
                updateIndividualAndTotalPrice(1);
            });
            
            // Check if there's already a selected variation
            if (window.infoplusProductState.selectedVariation) {
                console.log('[BulkCalculator] Found existing selected variation');
                handleVariationFound({ variation: window.infoplusProductState.selectedVariation });
            }
        } else {
            console.log('[BulkCalculator] infoplusProductState not found, waiting...');
            
            // Wait for it to be available
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
                        updateBulkGridFromAPI([]);
                        $('#bulk_table .bulk_item').removeClass('bulk_item--active');
                    });
                    
                    if (window.infoplusProductState.selectedVariation) {
                        handleVariationFound({ variation: window.infoplusProductState.selectedVariation });
                    }
                } else if (attempts >= maxAttempts) {
                    clearInterval(checkInterval);
                    console.warn('[BulkCalculator] infoplusProductState not found after waiting');
                    
                    // Fallback: try native WooCommerce events
                    bindNativeWooEvents();
                }
            }, 250);
        }
        
        // Only bind to native WooCommerce events if infoplusProductState is NOT available
        // (avoids double-firing and premature variation:found when not all attributes selected)
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
            updateBulkGridFromAPI([]);
            $('#bulk_table .bulk_item').removeClass('bulk_item--active');
            updateIndividualAndTotalPrice(1);
        });
    }

    // --- Start ---
    $(document).ready(init);
});
