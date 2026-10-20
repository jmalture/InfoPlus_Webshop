/* Product page: the three order types, the WooCommerce buy form and the
   sticky action bar for phones and tablets. Vanilla, deferred.

   Order type of the selected model (Infoplus Suite, per product and model):
   - editable:    #editor ("Produkt gestalten", external editor) + #anfrage.
   - buyable:     the buy form (template-parts/product/actions.php), a real
                  WooCommerce add-to-cart form. This file keeps its hidden
                  fields (variation_id, attribute_*, quantity) in sync with the
                  Suite state and enables the submit button once a variation
                  is found. The POST itself is plain WooCommerce.
   - requestable: #anfrage only, as the primary button
                  (requestable_with_variants counts as requestable here).
   The Suite's variation-editor-link.js shows / hides #editor and #anfrage and
   publishes the resolved type as infoplusProductState.orderType (event
   "ordertype:changed", Suite 0.10.27). Without it the type is read from
   infoplusProductData.models[].order_type. This file writes the type to
   data-ip-order-type on [data-ip-actions] and the matching hint text.

   After a buy form POST that Woo answers with this page (error notice, or
   success without the cart redirect) the form carries data-ip-posted, and the
   posted selection and quantity are restored once the Suite is ready.

   The sticky bar mirrors the active button and forwards its click to it.

   product-state.js mirrors its events on document as "infoplus:<event>"; the
   Suite scripts run on jQuery ready, after this deferred file. */
(function () {
  'use strict';
  var doc = document;
  var win = window;
  var actions = doc.querySelector('[data-ip-actions]');
  if (!actions) return;

  var editorBtn = actions.querySelector('[data-ip-action="editor"]');
  var inquiryBtn = actions.querySelector('[data-ip-action="inquiry"]');
  var buyForm = actions.querySelector('form[data-ip-buy-form]');
  var cartBtn = buyForm ? buyForm.querySelector('[data-ip-action="cart"]') : null;
  var cartLabel = cartBtn ? cartBtn.querySelector('[data-ip-label]') : null;
  var cartText = cartLabel ? cartLabel.textContent.trim() : '';
  var hint = actions.querySelector('[data-ip-order-hint]');
  var hints = {};
  try { hints = JSON.parse(hint ? hint.getAttribute('data-ip-hints') || '{}' : '{}'); } catch (e) { hints = {}; }
  var qtyInput = doc.querySelector('[data-ip-qty="value"]');
  var reduceMotion = win.matchMedia && win.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var bar = doc.querySelector('[data-ip-actionbar]');
  var barBtn = bar ? bar.querySelector('[data-ip-actionbar-btn]') : null;
  var barLabel = bar ? bar.querySelector('[data-ip-actionbar-label]') : null;
  var barSum = bar ? bar.querySelector('[data-ip-actionbar-sum]') : null;
  var barTotal = bar ? bar.querySelector('[data-ip-actionbar-total]') : null;
  var barQty = bar ? bar.querySelector('[data-ip-actionbar-qty]') : null;
  var total = doc.querySelector('[data-ip-qty="total-price"]');
  var qtyRow = doc.querySelector('[data-ip-qty-row]');
  var tiers = doc.querySelector('.ip-c-bulk-price-table');
  var unitLabel = tiers ? (tiers.getAttribute('data-stueck-label') || '') : ''; // "Stück"
  var zeroText = barTotal ? barTotal.textContent.trim() : '';

  var current = null;   // the button the bar stands for
  var queued = false;
  var submitting = false;
  var lastType = null;
  var lastCanBuy = false;

  /* ── Suite state ──────────────────────────────────────────────────────── */

  function state() {
    return win.infoplusProductState || null;
  }

  function normalizeType(type) {
    if (type === 'requestable' || type === 'requestable_with_variants') return 'requestable';
    if (type === 'buyable') return 'buyable';
    return 'editable';
  }

  /** Order type of the selected model: the Suite's resolved value, else the model data. */
  function orderType() {
    var ps = state();
    if (!ps) return 'editable';
    if (ps.orderType) return normalizeType(ps.orderType);
    var data = ps.productData || win.infoplusProductData;
    var models = data && data.models ? data.models : [];
    for (var i = 0; i < models.length; i++) {
      if (models[i].slug === ps.selectedModel) return normalizeType(models[i].order_type);
    }
    return 'editable';
  }

  function variationId() {
    var ps = state();
    var v = ps && ps.selectedVariation;
    return v ? parseInt(v.id || v.variation_id, 10) || 0 : 0;
  }

  /* ── Buy form ─────────────────────────────────────────────────────────── */

  function quantity() {
    var q = qtyInput ? parseInt(qtyInput.value, 10) : NaN;
    if (isNaN(q) || q < 1) {
      var ps = state();
      q = ps && ps.currentQty ? parseInt(ps.currentQty, 10) : 1;
    }
    return q > 0 ? q : 1;
  }

  /** Copy model, attributes, variation and quantity into the hidden fields. */
  function fillForm() {
    if (!buyForm) return;
    var ps = state();
    var v = ps && ps.selectedVariation;
    var selected = ps && ps.selectedAttributes ? ps.selectedAttributes : {};
    var vAttrs = v && v.attributes ? v.attributes : {};
    buyForm.querySelector('[data-ip-buy="variation"]').value = String(variationId());
    buyForm.querySelector('[data-ip-buy="qty"]').value = String(quantity());
    var inputs = buyForm.querySelectorAll('[data-ip-buy-attr]');
    for (var i = 0; i < inputs.length; i++) {
      var key = inputs[i].getAttribute('data-ip-buy-attr');
      // The selection first: a variation set to "any" for an attribute has '' there.
      inputs[i].value = selected[key] || vAttrs['attribute_' + key] || '';
    }
  }

  function canBuy() {
    return !!buyForm && orderType() === 'buyable' && variationId() > 0;
  }

  function setDisabled(el, off) {
    el.classList.toggle('infoplus-btn-disabled', off);
    el.setAttribute('aria-disabled', off ? 'true' : 'false');
  }

  function pulse(el) {
    if (!el || reduceMotion) return;
    el.classList.remove('infoplus-anfrage-pulse--long');
    void el.offsetWidth; // restart the animation
    el.classList.add('infoplus-anfrage-pulse--long');
    win.setTimeout(function () { el.classList.remove('infoplus-anfrage-pulse--long'); }, 3100);
  }

  function scrollToOptions() {
    var step = doc.querySelector('.ip-product__step--options') || doc.querySelector('.ip-product__config');
    if (step) step.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
  }

  function setHint(text) {
    if (hint && hint.textContent !== text) hint.textContent = text;
  }

  /** Order type, buy form and hint, from the current Suite state. */
  function applyType() {
    var type = orderType();
    actions.setAttribute('data-ip-order-type', type);
    if (buyForm) {
      buyForm.hidden = type !== 'buyable';
      fillForm();
      var ok = canBuy();
      if (!submitting) setDisabled(cartBtn, !ok);
      if (ok && !lastCanBuy) {
        pulse(cartBtn);
        setHint(hints[type] || ''); // replaces a "choose all options" note
      }
      lastCanBuy = ok;
    }
    if (type !== lastType) {
      setHint(hints[type] || '');
      lastType = type;
    }
  }

  if (buyForm) {
    buyForm.addEventListener('submit', function (e) {
      fillForm();
      if (submitting) {
        e.preventDefault();
        return;
      }
      if (!canBuy()) {
        // Options not complete (the button is aria-disabled, not disabled, so it stays focusable).
        e.preventDefault();
        setHint(buyForm.getAttribute('data-ip-choose-text') || hints.buyable || '');
        scrollToOptions();
        return;
      }
      submitting = true;
      cartBtn.setAttribute('aria-busy', 'true');
      cartBtn.classList.add('is-busy');
      if (cartLabel) cartLabel.textContent = buyForm.getAttribute('data-ip-busy-label') || cartText;
    });

    // Back / forward cache: the page comes back with the busy state still on.
    win.addEventListener('pageshow', function (e) {
      if (!e.persisted) return;
      submitting = false;
      cartBtn.removeAttribute('aria-busy');
      cartBtn.classList.remove('is-busy');
      if (cartLabel) cartLabel.textContent = cartText;
      queue();
    });
  }

  /* ── Restore a posted selection (page rendered from a buy form POST) ──── */

  function restorePosted() {
    var raw = buyForm ? buyForm.getAttribute('data-ip-posted') : '';
    var ps = state();
    if (!raw || !ps || typeof ps.setModel !== 'function') return;
    var posted;
    try { posted = JSON.parse(raw); } catch (e) { return; }
    if (!posted || typeof posted !== 'object') return;

    function apply() {
      if (posted.model && ps.selectedModel !== posted.model) ps.setModel(posted.model);
      var attrs = posted.attributes || {};
      Object.keys(attrs).forEach(function (key) {
        if (key === 'pa_model' || !attrs[key]) return;
        if (ps.selectedAttributes && ps.selectedAttributes[key] === attrs[key]) return;
        ps.setAttribute(key, attrs[key]);
      });
      var qty = parseInt(posted.qty, 10) || 0;
      if (qty > 1 && typeof ps.setQty === 'function') {
        // The bulk calculator sets the quantity to the minimum each time the
        // tiers of a variation arrive (minqty:changed). Set ours after the last one.
        var timer = null;
        var stop = Date.now() + 6000;
        var onMin = function () {
          win.clearTimeout(timer);
          if (Date.now() > stop) { doc.removeEventListener('infoplus:minqty:changed', onMin); return; }
          timer = win.setTimeout(function () { ps.setQty(qty, 'external'); queue(); }, 250);
        };
        doc.addEventListener('infoplus:minqty:changed', onMin);
        win.setTimeout(function () { doc.removeEventListener('infoplus:minqty:changed', onMin); }, 6500);
      }
      queue();
    }

    var data = ps.productData;
    if (data && data._variationsDeferred && !(data.variations && data.variations.length)) {
      // Deferred variations: the Suite applies its defaults right after this event.
      var onLoaded = function () {
        doc.removeEventListener('infoplus:variations:loaded', onLoaded);
        win.setTimeout(apply, 0);
      };
      doc.addEventListener('infoplus:variations:loaded', onLoaded);
    } else {
      apply();
    }
  }

  /* ── Sticky action bar ────────────────────────────────────────────────── */

  function shown(el) {
    if (!el || el.classList.contains('infoplus-btn-hidden')) return false;
    var form = el.closest('form');
    return !(form && form.hidden);
  }

  function disabled(el) {
    return el.classList.contains('infoplus-btn-disabled') || el.getAttribute('aria-disabled') === 'true';
  }

  /** The button the bar stands for: the one of the current order type. */
  function target() {
    var type = orderType();
    if (type === 'buyable' && shown(cartBtn)) return cartBtn;
    if (type === 'requestable' && shown(inquiryBtn)) return inquiryBtn;
    if (shown(editorBtn)) return editorBtn;
    if (shown(cartBtn)) return cartBtn;
    if (shown(inquiryBtn)) return inquiryBtn;
    return null;
  }

  function kind(el) {
    return el === cartBtn ? 'cart' : (el === inquiryBtn ? 'inquiry' : 'editor');
  }

  function syncBar() {
    if (!bar) return;
    current = target();
    bar.classList.toggle('is-empty', !current);
    if (current) {
      var lbl = current.querySelector('[data-ip-label]');
      barLabel.textContent = (lbl ? lbl.textContent : current.textContent).trim();
      // Full name incl. screen reader text (editor: "opens in a new tab").
      barBtn.setAttribute('aria-label', current.textContent.replace(/\s+/g, ' ').trim());
      barBtn.setAttribute('data-ip-kind', kind(current));
      var off = disabled(current);
      barBtn.classList.toggle('is-disabled', off);
      barBtn.setAttribute('aria-disabled', off ? 'true' : 'false');
    }
    var rowHidden = !qtyRow || qtyRow.style.display === 'none';
    var totalText = total ? total.textContent.trim() : '';
    var hasTotal = !rowHidden && totalText !== '' && totalText !== zeroText;
    barSum.hidden = !hasTotal;
    if (hasTotal) {
      barTotal.textContent = totalText;
      var q = qtyInput ? parseInt(qtyInput.value, 10) : NaN;
      barQty.textContent = isNaN(q) ? '' : '· ' + q + (unitLabel ? ' ' + unitLabel : '');
    }
  }

  function sync() {
    queued = false;
    applyType();
    syncBar();
  }

  function queue() {
    if (queued) return;
    queued = true;
    win.requestAnimationFrame(sync);
  }

  if (win.MutationObserver) {
    var mo = new MutationObserver(queue);
    [editorBtn, inquiryBtn].forEach(function (el) {
      if (el) mo.observe(el, { attributes: true, attributeFilter: ['class', 'aria-disabled', 'href'], childList: true, subtree: true, characterData: true });
    });
    if (total) mo.observe(total, { childList: true, subtree: true, characterData: true });
    if (qtyRow) mo.observe(qtyRow, { attributes: true, attributeFilter: ['style'] });
  }
  ['model:changed', 'attribute:changed', 'variation:found', 'variation:notfound', 'variation:reset',
    'variations:loaded', 'qty:changed', 'minqty:changed', 'ordertype:changed'].forEach(function (name) {
    doc.addEventListener('infoplus:' + name, queue);
  });
  if (qtyInput) qtyInput.addEventListener('input', queue);

  if (bar) {
    bar.hidden = false;

    barBtn.addEventListener('click', function () {
      if (!current) return;
      if (disabled(current)) {
        // Options are not complete yet: take the visitor to the configurator.
        scrollToOptions();
        return;
      }
      current.click(); // Same handlers as a click on the real button (editor link, cart form, inquiry).
    });

    /* Show the bar only while the real buttons are off screen. */
    var setVisible = function (on) {
      bar.classList.toggle('is-visible', on);
      bar.inert = !on;
      bar.setAttribute('aria-hidden', on ? 'false' : 'true');
      doc.body.classList.toggle('ip-actionbar-on', on);
    };

    if ('IntersectionObserver' in win) {
      new IntersectionObserver(function (entries) {
        setVisible(!entries[0].isIntersecting);
      }).observe(actions);
    } else {
      setVisible(true);
    }
  }

  sync();

  // After the Suite has initialised (its handlers run on jQuery ready).
  var ready = function () {
    win.setTimeout(function () {
      restorePosted();
      queue();
    }, 0);
  };
  if (win.jQuery) {
    win.jQuery(ready);
  } else if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', ready);
  } else {
    ready();
  }
})();
