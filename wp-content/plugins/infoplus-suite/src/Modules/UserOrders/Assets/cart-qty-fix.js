/**
 * Cart & Checkout Quantity Display Fix
 *
 * Injects a visible "× qty" badge into product names on:
 *   - Cart page:     .wc-block-cart-items__row  → a.wc-block-components-product-name
 *   - Checkout page: .wc-block-components-order-summary-item → .wc-block-components-order-summary-item__name
 *
 * Uses wp.data store subscription to stay reactive (updates on qty change / item removal).
 * Skips injection if the native quantity selector is already visible (cart page).
 *
 * Temporary solution — replace with proper block config when possible.
 */
(function () {
    'use strict';

    var BADGE_CLASS = 'ip-cart-qty-badge';

    function formatQty(qty) {
        return '× ' + qty;
    }

    function getCartItems() {
        if (window.wp && wp.data && wp.data.select('wc/store/cart')) {
            var cartData = wp.data.select('wc/store/cart').getCartData();
            if (cartData && cartData.items && cartData.items.length) {
                return cartData.items;
            }
        }
        return null;
    }

    /**
     * Inject or update a qty badge inside a name element.
     */
    function applyBadge(nameEl, qty) {
        var badge = nameEl.querySelector('.' + BADGE_CLASS);
        if (badge) {
            if (badge.dataset.qty !== String(qty)) {
                badge.textContent = ' ' + formatQty(qty);
                badge.dataset.qty = String(qty);
            }
            return;
        }

        badge = document.createElement('span');
        badge.className = BADGE_CLASS;
        badge.textContent = ' ' + formatQty(qty);
        badge.dataset.qty = String(qty);
        nameEl.appendChild(badge);
    }

    /**
     * Cart page: inject badges into each cart line item row.
     */
    function updateCartBadges(items) {
        var rows = document.querySelectorAll('.wc-block-cart-items__row');
        rows.forEach(function (row, index) {
            var item = items[index];
            if (!item) return;

            // Skip if native quantity selector is visible
            var nativeQty = row.querySelector('.wc-block-components-quantity-selector');
            if (nativeQty && nativeQty.offsetParent !== null) return;

            var nameLink = row.querySelector('a.wc-block-components-product-name');
            if (nameLink) applyBadge(nameLink, item.quantity);
        });
    }

    /**
     * Checkout page: inject badges into order summary item names.
     */
    function updateCheckoutBadges(items) {
        var summaryItems = document.querySelectorAll('.wc-block-components-order-summary-item');
        summaryItems.forEach(function (el, index) {
            var item = items[index];
            if (!item) return;

            var nameEl = el.querySelector('.wc-block-components-product-name');
            if (nameEl) applyBadge(nameEl, item.quantity);
        });
    }

    function updateBadges() {
        var items = getCartItems();
        if (!items) return;

        updateCartBadges(items);
        updateCheckoutBadges(items);
    }

    function init() {
        var isCart = !!document.querySelector('.wc-block-cart');
        var isCheckout = !!document.querySelector('.wc-block-checkout');
        if (!isCart && !isCheckout) return;

        updateBadges();

        // Subscribe to store changes
        if (window.wp && wp.data && wp.data.subscribe) {
            var prevItemsJson = '';
            wp.data.subscribe(function () {
                var items = getCartItems();
                var json = items ? JSON.stringify(items.map(function (i) { return i.key + ':' + i.quantity; })) : '';
                if (json !== prevItemsJson) {
                    prevItemsJson = json;
                    updateBadges();
                }
            });
        }

        // Observe DOM changes (React re-renders can replace nodes)
        var target = document.querySelector('.wc-block-cart') || document.querySelector('.wc-block-checkout');
        if (target) {
            new MutationObserver(function () {
                updateBadges();
            }).observe(target, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
