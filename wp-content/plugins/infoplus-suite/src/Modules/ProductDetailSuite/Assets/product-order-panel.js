/**
 * Product Order Panel Widget
 *
 * Handles all interactive logic for the ProductOrderPanel Elementor widget:
 *   - Bulk price table: fetched from API on variation:found, hidden until data loads
 *   - Quantity selector: +/− buttons, manual input, clamped to min_quantity
 *   - Price display: Einzelpreis (matching tier) + Gesamtpreis (tier × qty), live updates
 *   - Action buttons: visibility and enabled state driven by order_type + variation state
 *   - AJAX add-to-cart via WooCommerce Store API
 *   - Editor URL built from window.infoplusEditorSettings
 *
 * Consumer only — never fires events on infoplusProductState.
 *
 * Depends on: jQuery, infoplusProductState, infoplusProductData, infoplusEditorSettings (optional)
 */
(function ($, w, d) {
    'use strict';

    // ── State ─────────────────────────────────────────────────────────────────

    var state = {
        currentOrderType:   'editable',   // editable | requestable | buyable
        variationIsValid:   false,        // true after variation:found
        variationNotFound:  false,        // true when all picked but no match
        currentVariation:   null,         // variation object from variation:found
        currentSku:         null,         // current variation SKU
        bulkPrices:         [],           // [{from, to, value}] loaded from API
        currentQty:         1,            // current quantity (clamped to minQty)
        minQty:             1,            // effective min (may be raised by bulk price first tier)
        productMinQty:      1,            // product-level min (from meta / data attribute)
    };

    // ── Helpers ───────────────────────────────────────────────────────────────

    function isEditMode() {
        return !!(w.elementorFrontend && w.elementorFrontend.isEditMode && w.elementorFrontend.isEditMode());
    }

    function formatPrice(amount) {
        return new Intl.NumberFormat('de-DE', {
            style:    'currency',
            currency: 'EUR'
        }).format(amount);
    }

    /**
     * Find the bulk price tier for a given quantity.
     * Returns the price of the last tier where qty >= from.
     */
    function getPriceForQty(breaks, qty) {
        if (!breaks || !breaks.length) return null;

        var matched = null;
        for (var i = 0; i < breaks.length; i++) {
            var b = breaks[i];
            var from = parseInt(b.from, 10) || 0;
            if (qty >= from) {
                matched = parseFloat(b.value);
            }
        }
        return matched;
    }

    function getEditorUrl() {
        var s = w.infoplusEditorSettings || {};
        if (!s.baseUrl || !s.themeId || !s.productUuid) return null;
        var url = s.baseUrl +
                  '?product=' + encodeURIComponent(s.productUuid) +
                  '&theme='   + encodeURIComponent(s.themeId);
        if (s.cartEntryPath) url += '&cartEntryPath=' + encodeURIComponent(s.cartEntryPath);
        if (s.backPath)      url += '&backPath='      + encodeURIComponent(s.backPath);
        return url;
    }

    // ── DOM helpers ───────────────────────────────────────────────────────────

    function getPanel() {
        return $('.infoplus-order-panel');
    }

    // ── Bulk Price Table ──────────────────────────────────────────────────────

    function showBulkLoading($panel) {
        $panel.find('.infoplus-order-panel__bulk-loading').show();
        $panel.find('.infoplus-order-panel__bulk-grid').hide();
    }

    function hideBulkSection($panel) {
        $panel.find('.infoplus-order-panel__bulk').hide();
        $panel.find('.infoplus-order-panel__bulk-loading').hide();
    }

    function buildBulkGrid($panel, breaks, cols) {
        var $grid = $panel.find('.infoplus-order-panel__bulk-grid');
        $grid.empty();

        if (!breaks || !breaks.length) {
            hideBulkSection($panel);
            return;
        }

        $.each(breaks, function (i, b) {
            var from = parseInt(b.from, 10) || 0;
            var to   = b.to ? parseInt(b.to, 10) : null;
            var price = parseFloat(b.value);

            var qtyLabel = to
                ? 'ab ' + from + ' bis ' + to + ' Stück'
                : 'ab ' + from + ' Stück';

            var $row = $('<div class="infoplus-bulk-row" role="button" tabindex="0"></div>');
            $row.attr('data-from', from);
            $row.append('<span class="infoplus-bulk-qty">' + qtyLabel + '</span>');
            $row.append('<span class="infoplus-bulk-price">' + formatPrice(price) + '</span>');
            $grid.append($row);
        });

        // Apply active highlight to matching tier
        highlightActiveTier($panel);

        $panel.find('.infoplus-order-panel__bulk-loading').hide();
        $panel.find('.infoplus-order-panel__bulk-grid').show();
        $panel.find('.infoplus-order-panel__bulk').show();
    }

    /**
     * Raise minQty to the first bulk-price tier's "from" value when it is
     * higher than the product-level minimum, and re-clamp the current qty.
     */
    function applyBulkMinQty($panel, tiers) {
        var firstFrom = parseInt(tiers[0].from, 10) || 1;
        var effective = Math.max(state.productMinQty, firstFrom);
        if (effective !== state.minQty) {
            state.minQty = effective;
            if (state.currentQty < effective) {
                var $input = $panel.find('.infoplus-qty-input');
                state.currentQty = effective;
                $input.val(effective);
                $panel.find('.infoplus-qty-minus').prop('disabled', true);
                if (w.infoplusProductState) {
                    w.infoplusProductState.currentQty = effective;
                    w.infoplusProductState.emit('qty:changed', { qty: effective, source: 'panel' });
                }
            }
        }
        // Notify info box of the effective min qty
        if (w.infoplusProductState) {
            w.infoplusProductState.emit('minqty:changed', { minQty: effective });
        }
    }

    /** Reset minQty back to the product-level value (when no bulk prices). */
    function resetMinQty($panel) {
        state.minQty = state.productMinQty;
        if (w.infoplusProductState) {
            w.infoplusProductState.emit('minqty:changed', { minQty: state.productMinQty });
        }
    }

    function fetchBulkPrices($panel, sku) {
        if (!sku) {
            state.bulkPrices = [];
            hideBulkSection($panel);
            hidePriceSection($panel);
            return;
        }

        var shouldShow = $panel.data('show-bulk') !== false;
        if (!shouldShow) return;

        showBulkLoading($panel);
        $panel.find('.infoplus-order-panel__bulk').show();

        var apiUrl = '/wp-json/infoplus/v1/products/prices?sku=' + encodeURIComponent(sku);

        $.getJSON(apiUrl, function (data) {
            if (data && data.length) {
                state.bulkPrices = data;
                applyBulkMinQty($panel, data);
                var cols = parseInt($panel.find('.infoplus-order-panel__bulk-grid').data('cols'), 10) || 2;
                buildBulkGrid($panel, data, cols);
                updatePrices($panel);
            } else {
                state.bulkPrices = [];
                resetMinQty($panel);
                hideBulkSection($panel);
                hidePriceSection($panel);
            }
        }).fail(function () {
            state.bulkPrices = [];
            resetMinQty($panel);
            hideBulkSection($panel);
            hidePriceSection($panel);
        });
    }

    // ── Price Display ─────────────────────────────────────────────────────────

    function hidePriceSection($panel) {
        $panel.find('.infoplus-order-panel__prices').hide();
    }

    function updatePrices($panel) {
        if (!state.bulkPrices.length) {
            hidePriceSection($panel);
            return;
        }

        var unitPrice = getPriceForQty(state.bulkPrices, state.currentQty);
        if (unitPrice === null) {
            hidePriceSection($panel);
            return;
        }

        var total = unitPrice * state.currentQty;
        $panel.find('.infoplus-price-unit').text(formatPrice(unitPrice));
        $panel.find('.infoplus-price-total').text(formatPrice(total));
        $panel.find('.infoplus-order-panel__prices').show();

        highlightActiveTier($panel);
    }

    function highlightActiveTier($panel) {
        var $rows = $panel.find('.infoplus-bulk-row');
        $rows.removeClass('is-active');

        if (!state.bulkPrices.length) return;

        var activeTier = -1;
        for (var i = 0; i < state.bulkPrices.length; i++) {
            if (state.currentQty >= (parseInt(state.bulkPrices[i].from, 10) || 0)) {
                activeTier = i;
            }
        }
        if (activeTier >= 0) {
            $rows.eq(activeTier).addClass('is-active');
        }
    }

    // ── Quantity Selector ─────────────────────────────────────────────────────

    function initQuantitySelector($panel) {
        var $input = $panel.find('.infoplus-qty-input');
        var $minus = $panel.find('.infoplus-qty-minus');
        var $plus  = $panel.find('.infoplus-qty-plus');

        function clampQty(val) {
            val = parseInt(val, 10);
            if (isNaN(val) || val < state.minQty) val = state.minQty;
            return val;
        }

        function setQty(val) {
            val = clampQty(val);
            state.currentQty = val;
            $input.val(val);
            $minus.prop('disabled', val <= state.minQty);
            updatePrices($panel);
            highlightActiveTier($panel);
            // Publish to shared state so BulkPriceTable (and other widgets) can react.
            // source='panel' prevents the echo coming back and re-triggering setQty.
            if (w.infoplusProductState) {
                w.infoplusProductState.currentQty = val;
                w.infoplusProductState.emit('qty:changed', { qty: val, source: 'panel' });
            }
        }

        $minus.on('click', function () {
            setQty(state.currentQty - 1);
        });

        $plus.on('click', function () {
            setQty(state.currentQty + 1);
        });

        $input.on('change blur', function () {
            setQty($(this).val());
        });

        $input.on('input', function () {
            // Live update while typing (no clamp yet — blur handles that).
            // We DO notify infoplusProductState so BulkPriceTable updates its
            // active-cell highlight in real time as the user types.
            var v = parseInt($(this).val(), 10);
            if (!isNaN(v) && v >= state.minQty) {
                state.currentQty = v;
                updatePrices($panel);
                highlightActiveTier($panel);
                if (w.infoplusProductState) {
                    w.infoplusProductState.currentQty = v;
                    w.infoplusProductState.emit('qty:changed', { qty: v, source: 'panel' });
                }
            }
        });

        // Init
        setQty(state.minQty);

        // Listen for qty changes requested by other widgets (e.g. BulkPriceTable cell click).
        // source='panel' events originated here — skip them to avoid an echo loop.
        // NOTE: we intentionally do NOT guard on data.qty !== state.currentQty because
        // the source check already prevents the echo. The equality guard would block
        // legitimate updates when the user clicks the lowest-tier row whose 'from'
        // happens to equal the current minQty.
        if (w.infoplusProductState) {
            w.infoplusProductState.on('qty:changed', function (data) {
                if (data.source !== 'panel') {
                    setQty(data.qty);
                }
            });
        }
    }

    // ── Action Buttons ────────────────────────────────────────────────────────

    function resetButtonVisibility($panel) {
        $panel.find('.infoplus-btn--editor').addClass('infoplus-btn--hidden');
        $panel.find('.infoplus-btn--anfrage').addClass('infoplus-btn--hidden');
        $panel.find('.infoplus-btn--cart').addClass('infoplus-btn--hidden');
        $panel.find('.infoplus-btn').removeClass('infoplus-btn--pulsing');
        $panel.find('.infoplus-btn-error').hide();
    }

    function applyOrderType($panel, orderType) {
        resetButtonVisibility($panel);

        if (orderType === 'editable') {
            $panel.find('.infoplus-btn--editor')
                .removeClass('infoplus-btn--hidden')
                .addClass('infoplus-btn--pulsing');
            $panel.find('.infoplus-btn--anfrage').removeClass('infoplus-btn--hidden');

        } else if (orderType === 'requestable' || orderType === 'requestable_with_variants') {
            $panel.find('.infoplus-btn--anfrage')
                .removeClass('infoplus-btn--hidden')
                .addClass('infoplus-btn--pulsing');

        } else if (orderType === 'buyable') {
            $panel.find('.infoplus-btn--cart')
                .removeClass('infoplus-btn--hidden')
                .addClass('infoplus-btn--pulsing');
        }

        // Re-apply enabled/disabled state based on current variation validity
        applyVariationState($panel);
    }

    function applyVariationState($panel) {
        var orderType = state.currentOrderType;

        if (orderType === 'editable') {
            var $editor = $panel.find('.infoplus-btn--editor');
            if (state.variationIsValid && !state.variationNotFound) {
                var url = getEditorUrl();
                if (url) {
                    $editor
                        .attr('href', url)
                        .attr('target', '_blank')
                        .attr('rel', 'noopener')
                        .removeClass('infoplus-btn--disabled')
                        .removeAttr('aria-disabled')
                        .addClass('infoplus-btn--pulsing');
                    $panel.find('.infoplus-btn-error').hide();
                }
            } else {
                $editor
                    .attr('href', '#')
                    .removeAttr('target rel')
                    .addClass('infoplus-btn--disabled')
                    .removeClass('infoplus-btn--pulsing')
                    .attr('aria-disabled', 'true');
                if (state.variationNotFound) {
                    $panel.find('.infoplus-btn-error').show();
                } else {
                    $panel.find('.infoplus-btn-error').hide();
                }
            }

        } else if (orderType === 'requestable' || orderType === 'requestable_with_variants') {
            // Re-enforce Anfrage visible + pulsing — these types need no variation
            // to be actionable, so downstream events must not hide the button.
            $panel.find('.infoplus-btn--anfrage')
                .removeClass('infoplus-btn--hidden')
                .addClass('infoplus-btn--pulsing');
            $panel.find('.infoplus-btn-error').hide();

        } else if (orderType === 'buyable') {
            var $cart = $panel.find('.infoplus-btn--cart');
            if (state.variationIsValid && !state.variationNotFound) {
                $cart
                    .removeClass('infoplus-btn--disabled')
                    .addClass('infoplus-btn--pulsing')
                    .prop('disabled', false);
                $panel.find('.infoplus-btn-error').hide();
            } else {
                $cart
                    .addClass('infoplus-btn--disabled')
                    .removeClass('infoplus-btn--pulsing')
                    .prop('disabled', true);
                if (state.variationNotFound) {
                    $panel.find('.infoplus-btn-error').show();
                } else {
                    $panel.find('.infoplus-btn-error').hide();
                }
            }
        }
    }

    // ── AJAX Add-to-Cart ──────────────────────────────────────────────────────

    function handleCartClick($panel, $btn) {
        if ($btn.hasClass('infoplus-btn--disabled')) return;

        var variationId = null;
        if (w.infoplusProductState && w.infoplusProductState.selectedVariation) {
            var sv = w.infoplusProductState.selectedVariation;
            variationId = sv.variation_id || sv.id || null;
        }
        // Fallback: read from WooCommerce hidden input
        if (!variationId) {
            variationId = $('form.variations_form').find('input.variation_id').val() || null;
        }

        if (!variationId) return;

        var productId = parseInt($panel.data('product-id'), 10);
        var qty       = state.currentQty;

        $btn.addClass('infoplus-btn--loading').prop('disabled', true);

        // Use WooCommerce Store API
        var nonce = (w.infoplusProductConfig && w.infoplusProductConfig.nonce) ? w.infoplusProductConfig.nonce : '';
        // Try wcStoreApiNonce first (WC block nonce), fall back to our nonce
        var storeNonce = w.wcStoreApiNonce || nonce;

        fetch('/wp-json/wc/store/v1/cart/add-item', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Nonce': storeNonce
            },
            body: JSON.stringify({
                id:       parseInt(variationId, 10),
                quantity: qty
            })
        }).then(function (response) {
            if (!response.ok) {
                return response.json().then(function (err) { throw err; });
            }
            return response.json();
        }).then(function () {
            // Success feedback
            var originalText = $btn.text();
            $btn
                .removeClass('infoplus-btn--loading')
                .addClass('infoplus-btn--success')
                .text('✓ Hinzugefügt')
                .prop('disabled', false);

            // Trigger WooCommerce mini-cart refresh
            $(d.body).trigger('wc_fragment_refresh');

            setTimeout(function () {
                $btn
                    .removeClass('infoplus-btn--success')
                    .text(originalText);
            }, 5000);

        }).catch(function (err) {
            $btn
                .removeClass('infoplus-btn--loading')
                .prop('disabled', false);

            var msg = (err && err.message) ? err.message : 'Fehler beim Hinzufügen';
            $panel.find('.infoplus-btn-error').text(msg).show();

            console.error('[ProductOrderPanel] Add to cart error:', err);
        });
    }

    // ── Editor Button Click ───────────────────────────────────────────────────

    function handleEditorClick(e, $btn) {
        if ($btn.hasClass('infoplus-btn--disabled') || $btn.attr('href') === '#') {
            e.preventDefault();
        }
    }

    // ── Anfrage Button Click ──────────────────────────────────────────────────

    function handleAnfrageClick(e, $btn) {
        e.preventDefault();
        var target = d.querySelector('#anfrage');
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // ── Event Bus Listeners ───────────────────────────────────────────────────

    function bindProductStateEvents($panel) {
        if (!w.infoplusProductState) return;

        w.infoplusProductState.on('model:changed', function (data) {
            var modelSlug = data.model;
            var orderType = 'editable';

            if (w.infoplusProductData && w.infoplusProductData.models) {
                for (var i = 0; i < w.infoplusProductData.models.length; i++) {
                    if (w.infoplusProductData.models[i].slug === modelSlug) {
                        orderType = w.infoplusProductData.models[i].order_type || 'editable';
                        break;
                    }
                }
            }

            state.currentOrderType  = orderType;
            state.variationIsValid  = false;
            state.variationNotFound = false;
            state.currentVariation  = null;
            state.currentSku        = null;
            state.bulkPrices        = [];

            // Reset bulk table and prices
            $panel.find('.infoplus-order-panel__bulk').hide();
            $panel.find('.infoplus-order-panel__bulk-grid').empty();
            hidePriceSection($panel);

            applyOrderType($panel, orderType);
        });

        w.infoplusProductState.on('variation:found', function (data) {
            var variation = data.variation || {};
            state.variationIsValid  = true;
            state.variationNotFound = false;
            state.currentVariation  = variation;
            state.currentSku        = variation.sku || null;

            $panel.find('.infoplus-btn-error').hide();
            applyVariationState($panel);

            // Fetch bulk prices for new SKU
            if (state.currentSku) {
                fetchBulkPrices($panel, state.currentSku);
            }
        });

        w.infoplusProductState.on('variation:notfound', function () {
            state.variationIsValid  = false;
            state.variationNotFound = true;
            state.currentVariation  = null;
            state.currentSku        = null;
            state.bulkPrices        = [];

            hideBulkSection($panel);
            hidePriceSection($panel);
            applyVariationState($panel);
        });

        w.infoplusProductState.on('variation:reset', function () {
            state.variationIsValid  = false;
            state.variationNotFound = false;
            state.currentVariation  = null;
            state.currentSku        = null;
            state.bulkPrices        = [];

            hideBulkSection($panel);
            hidePriceSection($panel);
            applyVariationState($panel);
            $panel.find('.infoplus-btn-error').hide();
        });

        w.infoplusProductState.on('attribute:changed', function () {
            // If variation becomes invalid (partial selection) after a change, reflect that
            if (!state.variationIsValid) {
                $panel.find('.infoplus-btn-error').hide();
                applyVariationState($panel);
            }
        });
    }

    // ── State Replay ──────────────────────────────────────────────────────────

    /**
     * Called once after bindProductStateEvents() to apply any state that was already
     * set by product-state.js before this widget's $(document).ready ran.
     */
    function replayCurrentState($panel) {
        var ps = w.infoplusProductState;
        if (!ps) return;

        // Apply order type from already-selected model
        if (ps.selectedModel) {
            var orderType = 'editable';
            if (w.infoplusProductData && w.infoplusProductData.models) {
                for (var i = 0; i < w.infoplusProductData.models.length; i++) {
                    if (w.infoplusProductData.models[i].slug === ps.selectedModel) {
                        orderType = w.infoplusProductData.models[i].order_type || 'editable';
                        break;
                    }
                }
            }
            state.currentOrderType  = orderType;
            state.variationIsValid  = false;
            state.variationNotFound = false;
            applyOrderType($panel, orderType);
        }

        // Apply already-found variation
        if (ps.selectedVariation) {
            var variation = ps.selectedVariation;
            state.variationIsValid  = true;
            state.variationNotFound = false;
            state.currentVariation  = variation;
            state.currentSku        = variation.sku || null;
            $panel.find('.infoplus-btn-error').hide();
            applyVariationState($panel);
            if (state.currentSku) {
                fetchBulkPrices($panel, state.currentSku);
            }
        }
    }

    // ── Per-panel Initialization ──────────────────────────────────────────────

    function initPanel($panel) {
        // Read product-level min_qty from data attribute or productData
        var minQty = parseInt($panel.data('min-qty'), 10) || 1;
        if (w.infoplusProductData && w.infoplusProductData.min_quantity) {
            minQty = Math.max(1, parseInt(w.infoplusProductData.min_quantity, 10));
        }
        state.productMinQty = minQty;
        state.minQty        = minQty;
        state.currentQty    = minQty;

        initQuantitySelector($panel);
        bindProductStateEvents($panel);
        replayCurrentState($panel);

        // Click on a bulk-price row → jump qty to that tier's minimum.
        // Delegates through infoplusProductState.setQty so BulkPriceTable widget
        // (if present) also updates its active-cell highlight at the same time.
        $panel.on('click keypress', '.infoplus-bulk-row', function (e) {
            if (e.type === 'keypress' && e.which !== 13) return;
            var from = parseInt($(this).data('from'), 10);
            if (!isNaN(from) && from >= 1 && w.infoplusProductState) {
                w.infoplusProductState.setQty(from);
            }
        });

        // Click handlers
        $panel.on('click', '.infoplus-btn--cart', function (e) {
            handleCartClick($panel, $(this));
        });

        $panel.on('click', '.infoplus-btn--editor', function (e) {
            handleEditorClick(e, $(this));
        });

        $panel.on('click', '.infoplus-btn--anfrage', function (e) {
            handleAnfrageClick(e, $(this));
        });
    }

    // ── Elementor frontend widget init ────────────────────────────────────────

    function initWidget($scope) {
        var $panel = $scope.find('.infoplus-order-panel');
        if (!$panel.length) return;

        if (isEditMode()) {
            // In editor: show all buttons without disabled state for preview
            $panel.find('.infoplus-btn').removeClass('infoplus-btn--hidden infoplus-btn--disabled');
            $panel.find('.infoplus-order-panel__bulk').show();
            $panel.find('.infoplus-order-panel__prices').show();
            return;
        }

        initPanel($panel);
    }

    // ── Boot ──────────────────────────────────────────────────────────────────

    $(function () {
        // Elementor frontend hook
        if (w.elementorFrontend && w.elementorFrontend.hooks) {
            w.elementorFrontend.hooks.addAction(
                'frontend/element_ready/infoplus-product-order-panel.default',
                initWidget
            );
        } else {
            // Non-Elementor or plain page: init directly
            $('.infoplus-order-panel').each(function () {
                if (!isEditMode()) {
                    initPanel($(this));
                }
            });
        }
    });

})(jQuery, window, document);
