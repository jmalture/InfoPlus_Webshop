/**
 * Infoplus Product Info Box
 *
 * Updates SKU, min order, and delivery time reactively
 * based on infoplusProductState events.
 */
(function ($) {
    'use strict';

    function setSku($widget, sku) {
        var $el = $widget.find('.ip-info-box__sku-value');
        if (sku) {
            $el.text(sku).removeClass('ip-info-box__sku-value--no-sku');
        } else {
            $el.text('Kein SKU').addClass('ip-info-box__sku-value--no-sku');
        }
    }

    function init() {
        var $widget = $('.ip-info-box');
        if (!$widget.length) return;

        var ps = window.infoplusProductState;
        if (!ps) return;

        // Update SKU when variation is found
        ps.on('variation:found', function (data) {
            var sku = data.variation ? (data.variation.sku || '') : '';
            setSku($widget, sku);
        });

        // Clear SKU when variation is lost
        ps.on('variation:notfound', function () {
            $widget.find('.ip-info-box__sku-value').text('—').removeClass('ip-info-box__sku-value--no-sku');
        });

        ps.on('variation:reset', function () {
            $widget.find('.ip-info-box__sku-value').text('—').removeClass('ip-info-box__sku-value--no-sku');
        });

        // Update min quantity when bulk prices change the effective minimum
        ps.on('minqty:changed', function (data) {
            $widget.find('.ip-info-box__min-qty').text(data.minQty);
        });

        // If variation is already selected (e.g. from default), apply now
        if (ps.selectedVariation) {
            setSku($widget, ps.selectedVariation.sku || '');
        }

        // Also update when deferred variations finish loading
        ps.on('variations:loaded', function () {
            if (ps.selectedVariation) {
                setSku($widget, ps.selectedVariation.sku || '');
            }
        });
    }

    // Initialize when DOM is ready
    $(init);

    // Re-initialize for Elementor editor
    $(window).on('elementor/frontend/init', function () {
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            elementorFrontend.hooks.addAction('frontend/element_ready/infoplus-product-info-box.default', function () {
                init();
            });
        }
    });
})(jQuery);
