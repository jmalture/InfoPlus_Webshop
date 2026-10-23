/* Cart and checkout bundle.
   Keeps the header cart count in step with the Cart/Checkout blocks: they
   change the cart through the Store API, which does not send the classic
   cart fragments the badge listens to. Replaces the child theme's
   infoplus_sync_elementor_minicart (which refetched fragments) by reading
   the count straight from the blocks' data store. */
(function () {
  'use strict';

  function ipBadgeSync() {
    var wp = window.wp;
    if (!wp || !wp.data || !wp.data.select || !wp.data.select('wc/store/cart')) { return false; }
    var last = null;
    wp.data.subscribe(function () {
      var cart = wp.data.select('wc/store/cart').getCartData();
      var count = cart && typeof cart.itemsCount === 'number' ? cart.itemsCount
        : (cart && typeof cart.items_count === 'number' ? cart.items_count : null);
      if (count === null || count === last) { return; }
      last = count;
      document.querySelectorAll('[data-ip-cart-count]').forEach(function (el) {
        el.textContent = String(count);
        el.classList.toggle('is-empty', count === 0);
      });
    });
    return true;
  }

  // Block scripts run before this deferred file; retry once after load for late stores.
  if (!ipBadgeSync()) {
    window.addEventListener('load', ipBadgeSync, { once: true });
  }
})();
