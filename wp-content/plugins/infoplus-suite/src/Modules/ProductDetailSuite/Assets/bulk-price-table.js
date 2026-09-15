/**
 * Bulk Price Table Widget
 *
 * Drives all .infoplus-bulk-price-table elements on the page:
 *   - Listens to variation:found  → fetches prices for the new SKU → renders grid
 *   - Listens to variation:reset / variation:notfound → shows loading skeleton
 *   - Listens to qty:changed (source='panel') → updates the active-tier highlight
 *   - Cell click → calls infoplusProductState.setQty(from) to sync the qty selector
 *
 * Edit-mode tables are rendered statically by PHP and this script leaves them alone.
 *
 * Depends on: jQuery, infoplusProductState (infoplus-product-state)
 */
(function ($, w, d) {
    'use strict';

    // ── Module state ──────────────────────────────────────────────────────────

    var prices     = [];   // [{from, to, value}] – current SKU's price tiers
    var currentQty = 1;    // mirrors infoplusProductState.currentQty

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Check if the currently selected model is a requestable type (no pricing). */
    function isRequestableModel() {
        var ps = w.infoplusProductState;
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

    /** Hide the entire table widget (no skeleton, no grid, no overlay). */
    function hideTable($table) {
        $table.find('.ibpt-skeleton').hide();
        $table.find('.ibpt-grid').hide().empty();
        $table.find('.ibpt-no-prices-lock').remove();
        $table.removeClass('ibpt--has-overlay');
    }

    /**
     * Show a frosted-glass overlay with placeholder cells and an informational
     * message when a variation *should* have bulk prices but none exist yet.
     */
    function showNoPricesOverlay($table) {
        $table.find('.ibpt-skeleton').hide();
        $table.find('.ibpt-no-prices-lock').remove();

        // Build placeholder cells so there is something to blur behind the overlay
        var $grid = $table.find('.ibpt-grid');
        $grid.empty();
        for (var i = 0; i < 6; i++) {
            $grid.append('<div class="ibpt-cell ibpt-placeholder-cell">' +
                '<span class="ibpt-cell__label">ab</span>' +
                '<span class="ibpt-cell__qty">—</span>' +
                '<span class="ibpt-cell__label">Stück</span>' +
                '<span class="ibpt-cell__price">— €</span>' +
                '</div>');
        }
        $grid.show();

        // Frosted overlay
        var $overlay = $('<div class="ibpt-no-prices-lock">' +
            '<span class="ibpt-no-prices-lock__message">' +
                'Für diese Variante sind noch keine Staffelpreise hinterlegt' +
            '</span>' +
            '</div>');
        $table.addClass('ibpt--has-overlay');
        $table.append($overlay);
    }

    function isEditMode() {
        return !!(w.elementorFrontend &&
                  w.elementorFrontend.isEditMode &&
                  w.elementorFrontend.isEditMode());
    }

    function formatPrice(amount) {
        return new Intl.NumberFormat('de-DE', {
            style:    'currency',
            currency: 'EUR',
        }).format(amount);
    }

    /** All live (non-preview) table elements on the page */
    function getTables() {
        return $('.infoplus-bulk-price-table').not('.ibpt-grid--preview').closest('.infoplus-bulk-price-table');
    }

    // ── Skeleton ──────────────────────────────────────────────────────────────

    function showSkeleton($table) {
        $table.find('.ibpt-no-prices-lock').remove();
        $table.removeClass('ibpt--has-overlay');
        $table.find('.ibpt-skeleton').show();
        $table.find('.ibpt-grid').hide().empty();
    }

    // ── Grid rendering ────────────────────────────────────────────────────────

    /**
     * Build the price-tier grid inside $table from the given tiers array.
     * Each tier becomes one .ibpt-cell; the active tier is highlighted immediately.
     */
    function renderGrid($table, tiers) {
        var abLabel     = $table.data('ab-label')     || 'ab';
        var stueckLabel = $table.data('stueck-label') || 'Stück';
        var $grid       = $table.find('.ibpt-grid');

        $grid.empty();

        $.each(tiers, function (i, tier) {
            var from  = parseInt(tier.from, 10) || 0;
            var to    = (tier.to !== '' && tier.to !== null && tier.to !== undefined)
                            ? parseInt(tier.to, 10)
                            : null;
            var price = parseFloat(tier.value);

            // Last tier (no upper bound) → show "500+"
            var qtyDisplay = (to === null) ? (from + '+') : String(from);

            var $cell = $('<div class="ibpt-cell" role="button" tabindex="0"></div>')
                .attr('data-from', from)
                .attr('data-to',   to !== null ? to : '')
                .append($('<span class="ibpt-cell__label"></span>').text(abLabel))
                .append($('<span class="ibpt-cell__qty"></span>').text(qtyDisplay))
                .append($('<span class="ibpt-cell__label"></span>').text(stueckLabel))
                .append($('<span class="ibpt-cell__price"></span>').text(formatPrice(price)));

            $grid.append($cell);
        });

        $table.find('.ibpt-skeleton').hide();
        $grid.show();

        updateActiveCell($table);
    }

    // ── Active-tier highlight ─────────────────────────────────────────────────

    /**
     * Mark the cell whose price tier applies to currentQty.
     * Active tier = last tier where qty >= from (standard tiered-pricing logic).
     */
    function updateActiveCell($table) {
        var $cells = $table.find('.ibpt-cell');
        $cells.removeClass('ibpt-cell--active');

        if (!prices.length) return;

        var activeTier = -1;
        for (var i = 0; i < prices.length; i++) {
            if (currentQty >= (parseInt(prices[i].from, 10) || 0)) {
                activeTier = i;
            }
        }

        if (activeTier >= 0) {
            $cells.eq(activeTier).addClass('ibpt-cell--active');
        }
    }

    function updateAllActiveCells() {
        getTables().each(function () {
            updateActiveCell($(this));
        });
    }

    // ── API fetch ─────────────────────────────────────────────────────────────

    /**
     * Fetch price tiers for sku and re-render all table widgets.
     * Shows skeleton while the request is in flight.
     */
    function fetchAndRender(sku) {
        // Requestable models have no pricing — hide the table entirely.
        if (isRequestableModel()) {
            prices = [];
            getTables().each(function () { hideTable($(this)); });
            return;
        }

        // Variation is selected but carries no SKU — pricing is not yet configured.
        if (!sku) {
            prices = [];
            getTables().each(function () { showNoPricesOverlay($(this)); });
            return;
        }

        // Show skeleton in all tables while loading
        getTables().each(function () { showSkeleton($(this)); });

        var apiUrl = '/wp-json/infoplus/v1/products/prices?sku=' + encodeURIComponent(sku);

        $.getJSON(apiUrl, function (data) {
            if (data && data.length) {
                prices = data;
                getTables().each(function () {
                    var $t = $(this);
                    $t.find('.ibpt-no-prices-lock').remove();
                    $t.removeClass('ibpt--has-overlay');
                    renderGrid($t, data);
                });
            } else {
                // Variation should have prices but none exist yet — show overlay
                prices = [];
                getTables().each(function () { showNoPricesOverlay($(this)); });
            }
        }).fail(function () {
            prices = [];
            getTables().each(function () { showNoPricesOverlay($(this)); });
        });
    }

    // ── Cell interaction ──────────────────────────────────────────────────────

    /**
     * Clicking a price cell sets the quantity to that tier's minimum (from).
     * ProductOrderPanel listens to qty:changed and updates its input accordingly.
     */
    function onCellClick($cell) {
        var from = parseInt($cell.data('from'), 10);
        if (isNaN(from) || from < 1) return;

        if (w.infoplusProductState) {
            w.infoplusProductState.setQty(from); // source defaults to 'external'
        }
    }

    // ── Initialisation ────────────────────────────────────────────────────────

    function init() {
        if (isEditMode()) return; // PHP renders a static demo — nothing to do

        var ps = w.infoplusProductState;
        if (!ps) return;

        // Sync starting qty
        currentQty = ps.currentQty || 1;

        // ── Event listeners ───────────────────────────────────────────────────

        ps.on('variation:found', function (data) {
            var sku = data.variation && data.variation.sku;
            fetchAndRender(sku || null);
        });

        ps.on('variation:reset', function () {
            prices = [];
            getTables().each(function () { hideTable($(this)); });
        });

        ps.on('variation:notfound', function () {
            prices = [];
            getTables().each(function () { hideTable($(this)); });
        });

        // When model changes, immediately hide for requestable models
        ps.on('model:changed', function () {
            if (isRequestableModel()) {
                prices = [];
                getTables().each(function () { hideTable($(this)); });
            }
        });

        // qty:changed from ProductOrderPanel (source='panel') or from another cell click
        ps.on('qty:changed', function (data) {
            currentQty = data.qty;
            updateAllActiveCells();
        });

        // ── Cell click (delegated) ─────────────────────────────────────────────

        $(d).on('click', '.infoplus-bulk-price-table .ibpt-cell', function () {
            onCellClick($(this));
        });

        $(d).on('keypress', '.infoplus-bulk-price-table .ibpt-cell', function (e) {
            if (e.which === 13) onCellClick($(this)); // Enter key
        });

        // ── Replay current state ──────────────────────────────────────────────
        // product-state.js may have already fired variation:found (via applyDefaultVariation)
        // before this widget registered its listener — catch up now.
        if (ps.selectedVariation) {
            fetchAndRender(ps.selectedVariation.sku || null);
        }
    }

    // ── Boot ──────────────────────────────────────────────────────────────────

    $(d).ready(function () {
        init();
    });

}(jQuery, window, document));
