/* Product page: the sticky action bar for phones and tablets
   (template-parts/product/action-bar.php). Vanilla, deferred.

   The bar mirrors the real buttons (#editor / #anfrage, data-ip-action) that
   the Infoplus Suite drives: variation-editor-link.js hides or shows them per
   model order type (class infoplus-btn-hidden), disables the editor link until
   a variation is found (infoplus-btn-disabled, aria-disabled) and relabels it
   for "buyable" models. elementor-bulk-calculator.js writes the Gesamtpreis
   ([data-ip-qty="total-price"]) and hides the quantity row for requestable
   models. Nothing here changes Suite state: the bar copies what it sees and
   forwards its click to the button that is currently shown.

   The Suite scripts run on jQuery ready, after this deferred file; the
   MutationObservers below pick up every later change. */
(function () {
  'use strict';
  var doc = document;
  var bar = doc.querySelector('[data-ip-actionbar]');
  var actions = doc.querySelector('[data-ip-actions]');
  if (!bar || !actions) return;

  var editorBtn = actions.querySelector('[data-ip-action="editor"]');
  var inquiryBtn = actions.querySelector('[data-ip-action="inquiry"]');
  var barBtn = bar.querySelector('[data-ip-actionbar-btn]');
  var barLabel = bar.querySelector('[data-ip-actionbar-label]');
  var barSum = bar.querySelector('[data-ip-actionbar-sum]');
  var barTotal = bar.querySelector('[data-ip-actionbar-total]');
  var barQty = bar.querySelector('[data-ip-actionbar-qty]');
  var total = doc.querySelector('[data-ip-qty="total-price"]');
  var qtyInput = doc.querySelector('[data-ip-qty="value"]');
  var qtyRow = doc.querySelector('[data-ip-qty-row]');
  var tiers = doc.querySelector('.ip-c-bulk-price-table');
  var unitLabel = tiers ? (tiers.getAttribute('data-stueck-label') || '') : ''; // "Stück"
  var zeroText = barTotal ? barTotal.textContent.trim() : '';
  var current = null;
  var queued = false;

  bar.hidden = false;

  function shown(el) {
    return !!el && !el.classList.contains('infoplus-btn-hidden');
  }

  function disabled(el) {
    return el.classList.contains('infoplus-btn-disabled') || el.getAttribute('aria-disabled') === 'true';
  }

  /** The button the bar stands for: the editor / cart link, else the inquiry. */
  function target() {
    if (shown(editorBtn)) return editorBtn;
    if (shown(inquiryBtn)) return inquiryBtn;
    return null;
  }

  function sync() {
    queued = false;
    current = target();
    bar.classList.toggle('is-empty', !current);
    if (current) {
      var lbl = current.querySelector('[data-ip-label]');
      barLabel.textContent = (lbl ? lbl.textContent : current.textContent).trim();
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

  function queue() {
    if (queued) return;
    queued = true;
    window.requestAnimationFrame(sync);
  }

  if (window.MutationObserver) {
    var mo = new MutationObserver(queue);
    [editorBtn, inquiryBtn].forEach(function (el) {
      if (el) mo.observe(el, { attributes: true, attributeFilter: ['class', 'aria-disabled', 'href'], childList: true, subtree: true, characterData: true });
    });
    if (total) mo.observe(total, { childList: true, subtree: true, characterData: true });
    if (qtyRow) mo.observe(qtyRow, { attributes: true, attributeFilter: ['style'] });
  }
  // product-state.js mirrors its events on document as "infoplus:<event>".
  doc.addEventListener('infoplus:qty:changed', queue);
  if (qtyInput) qtyInput.addEventListener('input', queue);

  barBtn.addEventListener('click', function () {
    if (!current) return;
    if (disabled(current)) {
      // Options are not complete yet: take the visitor to the configurator.
      var step = doc.querySelector('.ip-product__step--options') || doc.querySelector('.ip-product__config');
      if (step) {
        step.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
      }
      return;
    }
    current.click(); // Same handlers as a click on the real button (editor link, cart, inquiry).
  });

  /* Show the bar only while the real buttons are off screen. */
  function setVisible(on) {
    bar.classList.toggle('is-visible', on);
    bar.inert = !on;
    bar.setAttribute('aria-hidden', on ? 'false' : 'true');
    doc.body.classList.toggle('ip-actionbar-on', on);
  }

  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      setVisible(!entries[0].isIntersecting);
    }).observe(actions);
  } else {
    setVisible(true);
  }

  sync();
})();
