/* Product page: configurator steps, summary card, the three order types, the
   WooCommerce buy form and the sticky action bar for phones and tablets.
   Vanilla, deferred.

   One source of truth: snapshot() reads the Suite product state
   (window.infoplusProductState) and the values the Suite wrote into the page
   (unit price and total of the bulk calculator) once per animation frame.
   render() then writes that snapshot into
   - the step headers ([data-ip-step], template-parts/product/summary.php):
     data-ip-step-state="open|done|locked|error" plus a check and the chosen
     value in [data-ip-step-status];
   - the summary card ([data-ip-summary], order-summary.php): model, options,
     quantity, unit price, total, and one polite announcement per change;
   - the phone bar ([data-ip-actionbar], action-bar.php).

   Order type of the selected model (Infoplus Suite, per product and model):
   - editable:    #editor ("Produkt gestalten", external editor) + #anfrage.
   - buyable:     the buy form (template-parts/product/actions.php), a real
                  WooCommerce add-to-cart form, + #anfrage (Suite 0.10.28).
                  This file keeps its hidden fields (variation_id, attribute_*,
                  quantity) in sync with the Suite state and enables the submit
                  button once a variation is found. The POST is plain WooCommerce.
   - requestable: #anfrage only, as the primary button
                  (requestable_with_variants counts as requestable here).
   The Suite's variation-editor-link.js shows / hides #editor and #anfrage and
   publishes the resolved type as infoplusProductState.orderType (event
   "ordertype:changed", Suite 0.10.27). Without it the type is read from
   infoplusProductData.models[].order_type. This file writes the type to
   data-ip-order-type on [data-ip-actions] and the matching hint text.

   Unavailable options: the Suite hides dropdown options that do not combine
   with the current selection (class is-disabled, display none). Options that
   exist for the selected model are shown again here, faded, aria-disabled and
   with a short reason; clicks and Enter on them are stopped before the Suite
   sees them. Options of other models stay hidden (they are not choices of this
   model). The model cards and dropdown options also get the roles, names and
   focus handling the Suite markup lacks (radio group, listbox options).

   After a buy form POST that Woo answers with this page (error notice, or
   success without the cart redirect) the form carries data-ip-posted, and the
   posted selection and quantity are restored once the Suite is ready.

   product-state.js mirrors its events on document as "infoplus:<event>" after
   its own listeners ran; the Suite scripts run on jQuery ready, after this
   deferred file. */
(function () {
  'use strict';
  var doc = document;
  var win = window;
  var actions = doc.querySelector('[data-ip-actions]');
  if (!actions) return;

  var config = doc.querySelector('[data-ip-config]');
  var T = {};
  try { T = JSON.parse(config ? config.getAttribute('data-ip-texts') || '{}' : '{}'); } catch (e) { T = {}; }

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

  var steps = {};
  Array.prototype.forEach.call(doc.querySelectorAll('[data-ip-step]'), function (el) {
    steps[el.getAttribute('data-ip-step')] = el;
  });
  var modelWidget = doc.querySelector('.ip-c-model-selector');
  var dropdowns = doc.querySelector('.ip-c-variation-dropdowns');

  var summary = doc.querySelector('[data-ip-summary]');
  var sum = {};
  if (summary) {
    Array.prototype.forEach.call(summary.querySelectorAll('[data-ip-sum]'), function (el) {
      sum[el.getAttribute('data-ip-sum')] = el;
    });
  }
  var live = summary ? summary.querySelector('[data-ip-sum-live]') : null;

  var bar = doc.querySelector('[data-ip-actionbar]');
  var barBtn = bar ? bar.querySelector('[data-ip-actionbar-btn]') : null;
  var barLabel = bar ? bar.querySelector('[data-ip-actionbar-label]') : null;
  var barSum = bar ? bar.querySelector('[data-ip-actionbar-sum]') : null;
  var barTotal = bar ? bar.querySelector('[data-ip-actionbar-total]') : null;
  var barMeta = bar ? bar.querySelector('[data-ip-actionbar-meta]') : null;

  var unitEl = doc.querySelector('[data-ip-qty="unit-price"]');
  var total = doc.querySelector('[data-ip-qty="total-price"]');
  var qtyRow = doc.querySelector('[data-ip-qty-row]');
  var tiers = doc.querySelector('.ip-c-bulk-price-table');
  var unitLabel = T.pieces || (tiers ? (tiers.getAttribute('data-stueck-label') || '') : ''); // "Stück"
  var zeroText = total ? total.textContent.trim() : '';

  var current = null;   // the button the bar stands for
  var queued = false;
  var submitting = false;
  var lastType = null;
  var lastCanBuy = false;
  var liveReady = false;
  var liveText = '';
  var liveTimer = null;

  /* ── Suite state ──────────────────────────────────────────────────────── */

  function state() {
    return win.infoplusProductState || null;
  }

  function productData() {
    var ps = state();
    return (ps && ps.productData) || win.infoplusProductData || {};
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
    var models = productData().models || [];
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

  function quantity() {
    var q = qtyInput ? parseInt(qtyInput.value, 10) : NaN;
    if (isNaN(q) || q < 1) {
      var ps = state();
      q = ps && ps.currentQty ? parseInt(ps.currentQty, 10) : 1;
    }
    return q > 0 ? q : 1;
  }

  function text(el) {
    return el ? el.textContent.replace(/\s+/g, ' ').trim() : '';
  }

  function dropdownRows() {
    return dropdowns ? Array.prototype.slice.call(dropdowns.querySelectorAll('.infoplus-dropdown[data-attribute]')) : [];
  }

  function cssEscape(value) {
    return win.CSS && win.CSS.escape ? win.CSS.escape(value) : String(value).replace(/["\\]/g, '\\$&');
  }

  /**
   * Everything the steps, the summary card and the bar show, read once.
   * Prices are the Suite's own output (bulk calculator), never recomputed here.
   */
  function snapshot() {
    var ps = state();
    var slug = ps ? ps.selectedModel : null;
    var modelName = '';
    (productData().models || []).forEach(function (m) {
      if (m.slug === slug) modelName = m.name || '';
    });
    if (!modelName && slug && modelWidget) {
      modelName = text(modelWidget.querySelector('[data-model="' + cssEscape(slug) + '"] .ip-c-model-selector__label'));
    }

    var locked = !!(dropdowns && dropdowns.classList.contains('is-requestable-locked'));
    var lockText = locked ? text(dropdowns.querySelector('.infoplus-dropdowns-lock__message')) : '';
    var selected = (ps && ps.selectedAttributes) || {};
    var rows = dropdownRows();
    var options = [];
    var allSet = rows.length > 0;
    rows.forEach(function (row) {
      var attr = row.getAttribute('data-attribute');
      var value = selected[attr];
      if (!value) { allSet = false; return; }
      var opt = row.querySelector('.infoplus-dropdown__option[data-value="' + cssEscape(value) + '"] .infoplus-dropdown__option-text');
      options.push({ label: text(row.querySelector('.infoplus-dropdown__label')), value: opt ? text(opt) : value });
    });

    var found = variationId() > 0;
    var errorEl = actions.querySelector('.infoplus-btn-error-msg');
    var qtyShown = !!qtyRow && qtyRow.style.display !== 'none';
    var totalText = text(total);
    var hasPrice = qtyShown && found && totalText !== '' && totalText !== zeroText;

    return {
      type: orderType(),
      model: slug,
      modelName: modelName,
      locked: locked,
      lockText: lockText,
      options: options,
      found: found,
      notFound: !found && allSet && !locked,
      errorText: text(errorEl),
      qtyShown: qtyShown,
      qty: quantity(),
      unit: text(unitEl),
      total: totalText,
      hasPrice: hasPrice
    };
  }

  /* ── Buy form ─────────────────────────────────────────────────────────── */

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

  /** The first step that is not done yet (options, else model, else the configurator). */
  function scrollToOptions() {
    var target = null;
    ['model', 'options', 'qty'].some(function (key) {
      var el = steps[key];
      if (el && !el.hidden && el.getAttribute('data-ip-step-state') !== 'done') { target = el; return true; }
      return false;
    });
    target = target || steps.options || config;
    if (target) target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
  }

  function setHint(value) {
    if (hint && hint.textContent !== value) hint.textContent = value;
  }

  /** Order type, buy form and hint, from the current Suite state. */
  function applyType(type) {
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

  /* ── Steps ────────────────────────────────────────────────────────────── */

  function setStep(key, stepState, value) {
    var el = steps[key];
    if (!el) return;
    el.setAttribute('data-ip-step-state', stepState);
    var slot = el.querySelector('[data-ip-step-status]');
    if (!slot) return;
    var sig = stepState + '|' + value;
    if (slot.getAttribute('data-sig') === sig) return;
    slot.setAttribute('data-sig', sig);
    slot.textContent = '';
    var sr = doc.createElement('span');
    sr.className = 'ip-sr';
    if (stepState === 'done') {
      sr.textContent = (T.done || '') + ' ';
      slot.appendChild(sr);
    } else if (stepState === 'open' || !value) {
      sr.textContent = T.open || '';
      slot.appendChild(sr);
      return;
    }
    var span = doc.createElement('span');
    span.className = 'ip-step__value';
    span.textContent = value;
    slot.appendChild(span);
  }

  function renderSteps(s) {
    setStep('model', s.model ? 'done' : 'open', s.modelName);

    var values = s.options.map(function (o) { return o.value; }).join(' · ');
    if (s.locked) setStep('options', 'locked', s.lockText);
    else if (s.found) setStep('options', 'done', values);
    else if (s.notFound && s.errorText) setStep('options', 'error', s.errorText);
    else setStep('options', 'open', '');

    if (steps.qty) {
      // Requestable models have no quantity or price (the Suite hides the row): no step.
      // Otherwise the step stays in the sequence and waits for a complete selection.
      steps.qty.hidden = s.type === 'requestable' && !s.qtyShown;
      steps.qty.classList.toggle('is-waiting', !(s.found && s.qtyShown));
      setStep('qty', s.hasPrice ? 'done' : 'open', s.qty + (unitLabel ? ' ' + unitLabel : ''));
    }
  }

  /* ── Summary card ─────────────────────────────────────────────────────── */

  function setText(el, value) {
    if (el && el.textContent !== value) el.textContent = value;
  }

  function renderSummary(s) {
    if (!summary) return;
    setText(sum.model, s.modelName || T.empty || '');
    setText(sum.variant, s.locked ? '' : s.options.map(function (o) { return o.value; }).join(' · '));
    if (sum.price) sum.price.hidden = !s.hasPrice;
    if (s.hasPrice) {
      setText(sum.qty, s.qty + (unitLabel ? ' ' + unitLabel : ''));
      setText(sum.unit, s.unit);
      setText(sum.total, s.total);
    }
    summary.setAttribute('data-ip-order-type', s.type);
    announce(s);
  }

  /** One polite announcement after the selection settles (not on page load). */
  function announce(s) {
    if (!live) return;
    var parts = [];
    if (s.modelName) parts.push(s.modelName);
    s.options.forEach(function (o) { parts.push(o.label ? o.label + ' ' + o.value : o.value); });
    var msg = (T.live ? T.live + ' ' : '') + parts.join(', ') + '.';
    if (s.hasPrice) msg += ' ' + s.qty + (unitLabel ? ' ' + unitLabel : '') + ', ' + (T.total || '') + ' ' + s.total + '.';
    if (msg === liveText) return;
    liveText = msg;
    if (!liveReady) return;
    win.clearTimeout(liveTimer);
    liveTimer = win.setTimeout(function () { live.textContent = liveText; }, 900);
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
  function target(type) {
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

  function renderBar(s) {
    if (!bar) return;
    current = target(s.type);
    bar.classList.toggle('is-empty', !current);
    if (current) {
      var lbl = current.querySelector('[data-ip-label]');
      setText(barLabel, text(lbl || current));
      // Full name incl. screen reader text (editor: "opens in a new tab").
      barBtn.setAttribute('aria-label', text(current));
      barBtn.setAttribute('data-ip-kind', kind(current));
      var off = disabled(current);
      barBtn.classList.toggle('is-disabled', off);
      barBtn.setAttribute('aria-disabled', off ? 'true' : 'false');
    }
    // Same data as the summary card: quantity and tier price, total.
    var meta = s.hasPrice ? s.qty + (unitLabel ? ' ' + unitLabel : '') + ' ' + (T.unit || '') + ' ' + s.unit : s.modelName;
    setText(barMeta, meta);
    barTotal.parentNode.hidden = !s.hasPrice;
    if (s.hasPrice) setText(barTotal, s.total);
    barSum.hidden = !meta && !s.hasPrice;
  }

  /* ── Unavailable options and accessibility of the Suite controls ────── */

  /** Values each attribute has in at least one variation of the selected model. */
  function modelValues() {
    var ps = state();
    var vars = productData().variations || [];
    if (!vars.length || !ps) return null;
    var model = ps.selectedModel;
    var out = {};
    vars.forEach(function (v) {
      var a = v.attributes || {};
      if (model && a.attribute_pa_model && a.attribute_pa_model !== model) return;
      Object.keys(a).forEach(function (k) {
        var key = k.replace(/^attribute_/, '');
        out[key] = out[key] || {};
        out[key][a[k] || '*'] = true;
      });
    });
    return out;
  }

  function renderOptions() {
    if (!dropdowns) return;
    var ps = state();
    var values = modelValues();
    var selected = (ps && ps.selectedAttributes) || {};
    dropdownRows().forEach(function (row) {
      var attr = row.getAttribute('data-attribute');
      var known = values ? values[attr] || {} : null;
      Array.prototype.forEach.call(row.querySelectorAll('.infoplus-dropdown__option'), function (opt) {
        var value = opt.getAttribute('data-value');
        var off = opt.classList.contains('is-disabled');
        var ofModel = !!known && (known[value] || known['*']);
        var faded = off && ofModel;
        if (faded) {
          if (opt.style.display === 'none') opt.style.display = '';
          if (!opt.querySelector('.ip-opt-reason')) {
            var reason = doc.createElement('span');
            reason.className = 'ip-opt-reason';
            reason.textContent = T.unavailable || '';
            opt.appendChild(reason);
          }
        }
        opt.classList.toggle('ip-opt-off', faded);
        if (faded) opt.setAttribute('aria-disabled', 'true');
        else opt.removeAttribute('aria-disabled');
        opt.setAttribute('aria-selected', selected[attr] === value ? 'true' : 'false');
      });
    });
  }

  function guardFaded(e) {
    var opt = e.target.closest ? e.target.closest('.ip-opt-off') : null;
    if (!opt || e.target.closest('.infoplus-dropdown__option-info')) return;
    if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
    e.preventDefault();
    e.stopPropagation(); // capture phase: the Suite's option handlers never see it
  }

  function enhanceDropdowns() {
    if (!dropdowns) return;
    dropdowns.addEventListener('click', guardFaded, true);
    dropdowns.addEventListener('keydown', guardFaded, true);
    dropdownRows().forEach(function (row, i) {
      var base = 'ip-vd-' + i;
      var label = row.querySelector('.infoplus-dropdown__label');
      var trigger = row.querySelector('.infoplus-dropdown__trigger');
      var value = row.querySelector('.infoplus-dropdown__selected-text');
      var list = row.querySelector('.infoplus-dropdown__options');
      if (!label || !trigger || !list) return;
      label.id = label.id || base + '-label';
      if (value) value.id = value.id || base + '-value';
      list.id = list.id || base + '-list';
      trigger.setAttribute('aria-labelledby', label.id + (value ? ' ' + value.id : ''));
      trigger.setAttribute('aria-haspopup', 'listbox');
      trigger.setAttribute('aria-controls', list.id);
      list.setAttribute('aria-labelledby', label.id);
      // Focusable options, so the Suite's arrow key handling can move focus to them.
      Array.prototype.forEach.call(list.querySelectorAll('.infoplus-dropdown__option'), function (opt) {
        opt.setAttribute('tabindex', '-1');
      });
      // A click on the label opens the list like a native select.
      label.addEventListener('click', function () { trigger.focus(); });
    });
  }

  function modelCards() {
    return modelWidget ? Array.prototype.slice.call(modelWidget.querySelectorAll('.ip-c-model-selector__option')) : [];
  }

  function renderModels() {
    var ps = state();
    modelCards().forEach(function (card) {
      var on = !!ps && card.getAttribute('data-model') === ps.selectedModel;
      card.setAttribute('aria-checked', on ? 'true' : 'false');
      card.setAttribute('tabindex', on ? '0' : '-1');
    });
    var cards = modelCards();
    if (cards.length && !cards.some(function (c) { return c.getAttribute('tabindex') === '0'; })) {
      cards[0].setAttribute('tabindex', '0');
    }
  }

  /** Model cards as a radio group: Tab to the group, arrows move and choose, Enter / Space choose. */
  function enhanceModels() {
    var cards = modelCards();
    if (!cards.length) return;
    var list = modelWidget.querySelector('.ip-c-model-selector__options');
    list.setAttribute('role', 'radiogroup');
    if (steps.model) list.setAttribute('aria-labelledby', steps.model.getAttribute('aria-labelledby'));
    cards.forEach(function (card, i) {
      var name = card.querySelector('.ip-c-model-selector__label');
      card.setAttribute('role', 'radio');
      if (name) {
        name.id = name.id || 'ip-ms-' + i;
        card.setAttribute('aria-labelledby', name.id);
      }
      card.addEventListener('keydown', function (e) {
        if (e.target !== card) return; // the info button handles its own keys
        var next = null;
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') next = cards[(i + 1) % cards.length];
        else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') next = cards[(i - 1 + cards.length) % cards.length];
        else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); card.click(); return; }
        if (!next) return;
        e.preventDefault();
        next.focus();
        next.click();
      });
    });
    renderModels();
  }

  /* ── Render loop ──────────────────────────────────────────────────────── */

  function sync() {
    queued = false;
    var s = snapshot();
    applyType(s.type);
    renderSteps(s);
    renderSummary(s);
    renderBar(s);
    renderOptions();
    renderModels();
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
    // Prices written by the bulk calculator, the row it hides, the error line after the buttons,
    // the requestable lock and the option filtering of the dropdowns.
    if (total) mo.observe(total, { childList: true, subtree: true, characterData: true });
    if (unitEl) mo.observe(unitEl, { childList: true, subtree: true, characterData: true });
    if (qtyRow) mo.observe(qtyRow, { attributes: true, attributeFilter: ['style'] });
    mo.observe(actions, { childList: true });
    if (buyForm) mo.observe(buyForm.parentNode, { childList: true });
    if (dropdowns) mo.observe(dropdowns, { attributes: true, attributeFilter: ['class'] });
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
        // Options are not complete yet: take the visitor to the open step.
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
      enhanceDropdowns();
      enhanceModels();
      restorePosted();
      queue();
      // Announce changes from now on, not the defaults applied on load.
      win.setTimeout(function () { liveReady = true; }, 1500);
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
