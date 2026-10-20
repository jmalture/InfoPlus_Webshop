/* Product page: folding configurator steps, summary card, the three order
   types, the WooCommerce buy form and the sticky action bar for phones and
   tablets. Vanilla, deferred.

   One source of truth: snapshot() reads the Suite product state
   (window.infoplusProductState) and the values the Suite wrote into the page
   (unit price and total of the bulk calculator) once per animation frame.
   render() then writes that snapshot into
   - the steps ([data-ip-step], template-parts/product/summary.php):
     data-ip-step-state="open|done|locked|error" plus a check and the chosen
     value in [data-ip-step-status];
   - the step bar ([data-ip-stepnav]): check when done, aria-current on the
     expanded step;
   - the summary card ([data-ip-summary], order-summary.php): model, options,
     quantity, unit price, total, and one polite announcement per change;
   - the phone bar ([data-ip-actionbar], action-bar.php).

   Folding steps (owner decision "Folding steps"). Steps: 1 Modell, one step per
   variation attribute (each holds its own Suite variation-dropdowns component,
   limited to that attribute), then Menge. Exactly one step is expanded
   (class is-current); the others fold into one line: check, name, chosen value
   and an "Ändern" button. The theme never decides what is selectable or
   selected: the model cards and attribute options are the Suite's own
   elements, clicked by the visitor (or by this file for arrow keys), and the
   Suite's state and infoplus:* events say what is chosen. This file only
   - shows the Suite options of an expanded step inline: tiles (up to 8 options)
     or a compact chip list (more), instead of the Suite's dropdown panel;
   - ticks a step when the visitor chooses in it: a click, Enter or Space on a
     model card or option, the highlighted default included. The tick stays
     while that value stays; when the Suite changes it (a model change resets
     the options to the model's defaults) the tick goes. Steps with a single
     option for the chosen model are ticked on their own;
   - moves on after a choice: the next step below without a tick, else the
     first one above, else Menge (stays open). Focus goes to that step's
     heading, the page scrolls only when the step is out of view. Arrow keys
     change the choice without ticking or moving on. A quantity set by the
     visitor (stepper, input, tier) ticks Menge.
   Initial state: the Suite's defaults stay preselected (price shown at once)
   but nothing is ticked ("preset": value, no tick); Modell is open. After a
   buy form POST (Woo renders this page again) the posted selection counts as
   chosen: everything ticked, Menge open.
   Staffelpreise are a strip above the summary card (purchase.php, part
   "tiers"); the Menge step holds the quantity only.

   Order type of the selected model (Infoplus Suite, per product and model):
   - editable:    #editor ("Produkt gestalten", external editor) + #anfrage.
   - buyable:     the buy form (template-parts/product/actions.php), a real
                  WooCommerce add-to-cart form, + #anfrage (Suite 0.10.28).
                  This file keeps its hidden fields (variation_id, attribute_*,
                  quantity) in sync with the Suite state and enables the submit
                  button once a variation is found. The POST is plain WooCommerce.
   - requestable: #anfrage only, as the primary button
                  (requestable_with_variants counts as requestable here).
                  For "requestable" the Suite locks the option components; the
                  attribute steps are hidden then and the Suite's lock message
                  shows under Modell ([data-ip-step-lock]).
   The Suite's variation-editor-link.js shows / hides #editor and #anfrage and
   publishes the resolved type as infoplusProductState.orderType (event
   "ordertype:changed", Suite 0.10.27). Without it the type is read from
   infoplusProductData.models[].order_type. This file writes the type to
   data-ip-order-type on [data-ip-actions] and the matching hint text.

   Unavailable options: the Suite hides options that do not combine with the
   current selection (class is-disabled, display none). Options that exist for
   the selected model are shown again here, faded, aria-disabled and with a
   short reason; clicks and Enter on them are stopped before the Suite sees
   them. Options of other models stay hidden (they are not choices of this
   model). A chosen value that no longer combines marks its step "error".
   Model cards and option tiles are radio groups (arrow keys move and choose,
   Enter / Space choose and move on).

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
  var placeholderSrc = config ? config.getAttribute('data-ip-placeholder') || '' : '';

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
  var order = []; // step keys in page order: model, attribute taxonomies, qty
  Array.prototype.forEach.call(doc.querySelectorAll('[data-ip-step]'), function (el) {
    var key = el.getAttribute('data-ip-step');
    steps[key] = el;
    order.push(key);
  });
  var folding = order.length > 1;
  var nav = doc.querySelector('[data-ip-stepnav]');
  var navBtns = {};
  if (nav) {
    Array.prototype.forEach.call(nav.querySelectorAll('[data-ip-goto]'), function (b) {
      navBtns[b.getAttribute('data-ip-goto')] = b;
    });
  }
  var lockNote = doc.querySelector('[data-ip-step-lock]');
  var modelWidget = doc.querySelector('.ip-c-model-selector');
  var widgets = Array.prototype.slice.call(doc.querySelectorAll('.ip-c-variation-dropdowns'));

  var summary = doc.querySelector('[data-ip-summary]');
  var summaryTitle = summary ? summary.querySelector('.ip-summary__title') : null;
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

  var openKey = null;      // the expanded step
  var settled = false;     // initial step decided
  var silentPick = false;  // a selection made by arrow keys: no "move on"
  var stepInfo = {};       // key -> { done, hidden } from the last render
  var lastChoice = null;   // the step of the visitor's last choice
  var ticks = {};          // key -> value the visitor ticked the step with (see renderSteps)
  var editPending = false; // "Auswahl ändern" in the inquiry drawer, waiting for it to close

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

  /** "Druck &amp; Sonderkontur" -> "Druck & Sonderkontur" (inert, never inserted as HTML). */
  function decodeEntities(value) {
    var ta = doc.createElement('textarea');
    ta.innerHTML = value;
    return ta.value;
  }

  function dropdownRows() {
    var rows = [];
    widgets.forEach(function (w) {
      Array.prototype.push.apply(rows, w.querySelectorAll('.infoplus-dropdown[data-attribute]'));
    });
    return rows;
  }

  function cssEscape(value) {
    return win.CSS && win.CSS.escape ? win.CSS.escape(value) : String(value).replace(/["\\]/g, '\\$&');
  }

  function optionText(row, value) {
    var opt = row.querySelector('.infoplus-dropdown__option[data-value="' + cssEscape(value) + '"] .infoplus-dropdown__option-text');
    return opt ? text(opt) : value;
  }

  /**
   * Everything the steps, the summary card and the bar show, read once.
   * Prices are the Suite's own output (bulk calculator), never recomputed here.
   */
  function snapshot() {
    var ps = state();
    var slug = ps ? ps.selectedModel : null;
    // The rendered card label first: the names in the product data are stored
    // HTML-escaped (WordPress term names, "Druck &amp; Sonderkontur").
    var modelName = '';
    if (slug && modelWidget) {
      modelName = text(modelWidget.querySelector('[data-model="' + cssEscape(slug) + '"] .ip-c-model-selector__label'));
    }
    if (!modelName) {
      (productData().models || []).forEach(function (m) {
        if (m.slug === slug) modelName = decodeEntities(m.name || '');
      });
    }

    var lockedWidget = null;
    widgets.forEach(function (w) { if (!lockedWidget && w.classList.contains('is-requestable-locked')) lockedWidget = w; });
    var locked = !!lockedWidget;
    var lockText = locked ? text(lockedWidget.querySelector('.infoplus-dropdowns-lock__message')) : '';
    var selected = (ps && ps.selectedAttributes) || {};
    var avail = (ps && ps.availableAttributes) || {};
    var options = [];
    var attrs = {};
    var rows = dropdownRows();
    var allSet = rows.length > 0;
    rows.forEach(function (row) {
      var attr = row.getAttribute('data-attribute');
      var value = selected[attr];
      var label = text(row.querySelector('.infoplus-dropdown__label'));
      // Only one option for the chosen model (shown options are the model's, faded or not).
      var single = shownOptions(row).length === 1;
      if (!value) {
        allSet = false;
        attrs[attr] = { set: false, conflict: false, value: '', raw: '', single: false };
        return;
      }
      var shown = optionText(row, value);
      // The chosen value no longer combines with the other choices (Suite availability).
      var list = avail[attr];
      var conflict = !!(list && list.length && list.indexOf(value) === -1);
      attrs[attr] = { set: true, conflict: conflict, value: shown, raw: value, single: single };
      options.push({ label: label, value: shown });
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
      attrs: attrs,
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

  /** Open the first step that is not done yet (from a disabled main button). */
  function scrollToOptions() {
    var key = nextStep(null);
    if (key && steps[key]) {
      openStep(key, { focus: true, reveal: true });
      return;
    }
    var target = steps[order[0]] || config;
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

  function postedSelection() {
    var raw = buyForm ? buyForm.getAttribute('data-ip-posted') : '';
    if (!raw) return null;
    try {
      var posted = JSON.parse(raw);
      return posted && typeof posted === 'object' ? posted : null;
    } catch (e) {
      return null;
    }
  }

  /** Runs once the Suite has its variations (deferred ones included). */
  function restorePosted() {
    var posted = postedSelection();
    var ps = state();
    if (!posted || !ps || typeof ps.setModel !== 'function') return;
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

  /* ── Step states ──────────────────────────────────────────────────────── */

  function setStep(key, stepState, value, reason) {
    var el = steps[key];
    if (!el) return;
    el.setAttribute('data-ip-step-state', stepState);
    var edit = el.querySelector('[data-ip-step-edit-label]');
    if (edit) setText(edit, value && stepState !== 'open' ? (T.edit || '') : (T.choose || T.edit || ''));
    var slot = el.querySelector('[data-ip-step-status]');
    if (!slot) return;
    var sig = stepState + '|' + value + '|' + (reason || '');
    if (slot.getAttribute('data-sig') === sig) return;
    slot.setAttribute('data-sig', sig);
    slot.textContent = '';
    var sr = doc.createElement('span');
    sr.className = 'ip-sr';
    if (stepState === 'done' || stepState === 'preset') {
      sr.textContent = (stepState === 'done' ? T.done || '' : T.preset || '') + ' ';
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
    if (reason) {
      var why = doc.createElement('span');
      why.className = 'ip-step__reason';
      why.textContent = reason;
      slot.appendChild(why);
    }
  }

  /** What a tick stands for: the value chosen when the visitor ticked the step. */
  function stepSig(key, s) {
    if (key === 'model') return s.model || '';
    if (key === 'qty') return 'qty';
    var a = s.attrs[key];
    return a && a.set ? a.raw : '';
  }

  /**
   * Step states. "done" (tick) only for steps the visitor ticked by a choice
   * (ticks[key], dropped again when the Suite changes that value, e.g. after a
   * model change) and for steps with a single option for the chosen model.
   * The Suite's preselected values show as "preset": value, no tick.
   */
  function renderSteps(s) {
    stepInfo = {};
    order.forEach(function (key) {
      var el = steps[key];
      var info = { done: false, hidden: false, auto: false };
      var sig = stepSig(key, s);
      if (ticks[key] !== undefined && ticks[key] !== sig) delete ticks[key];
      var ticked = ticks[key] !== undefined && sig !== '';
      if (key === 'model') {
        info.auto = !!s.model && modelCards().length === 1;
        info.done = ticked || info.auto;
        setStep(key, info.done ? 'done' : (s.model ? 'preset' : 'open'), s.modelName);
      } else if (key === 'qty') {
        // Requestable models have no quantity or price (the Suite hides the row): no step.
        // Otherwise the step stays in the sequence and waits for a complete selection.
        info.hidden = s.type === 'requestable' && !s.qtyShown;
        el.classList.toggle('is-waiting', !(s.found && s.qtyShown));
        info.done = ticked && s.hasPrice;
        setStep(key, info.done ? 'done' : (s.hasPrice ? 'preset' : 'open'), s.qty + (unitLabel ? ' ' + unitLabel : ''));
      } else {
        var a = s.attrs[key] || { set: false, conflict: false, value: '', single: false };
        // The Suite locks the options of models that are only requestable: no attribute steps then.
        info.hidden = s.locked;
        info.auto = a.set && a.single && !a.conflict;
        info.done = (ticked || info.auto) && !a.conflict;
        if (s.locked) setStep(key, 'locked', '');
        else if (a.conflict) setStep(key, 'error', a.value, T.unavailable || '');
        else if (info.done) setStep(key, 'done', a.value);
        else if (a.set) setStep(key, 'preset', a.value);
        else setStep(key, 'open', '');
      }
      el.classList.toggle('is-auto', info.auto);
      if (el.hidden !== info.hidden) el.hidden = info.hidden;
      stepInfo[key] = info;
    });
    // A complete selection without a variation and no single culprit: the Suite's error line.
    if (s.notFound && s.errorText && !order.some(function (k) { return s.attrs[k] && s.attrs[k].conflict; })) {
      var last = order.filter(function (k) { return s.attrs[k]; }).pop();
      if (last) {
        setStep(last, 'error', s.attrs[last].value, s.errorText);
        stepInfo[last].done = false;
      }
    }
    if (lockNote) {
      setText(lockNote, s.locked ? s.lockText : '');
      lockNote.hidden = !s.locked || !s.lockText;
    }
    if (folding && settled && openKey && stepInfo[openKey] && stepInfo[openKey].hidden) {
      // The open step went away (a requestable model): move on without stealing focus.
      openStep(nextStep(null), {});
    }
    renderNav();
  }

  function renderNav() {
    if (!nav) return;
    var shownItems = 0;
    order.forEach(function (key) {
      var b = navBtns[key];
      if (!b) return;
      var info = stepInfo[key] || {};
      var item = b.parentNode;
      if (item.hidden !== !!info.hidden) item.hidden = !!info.hidden;
      if (!info.hidden) shownItems++;
      var st = steps[key].getAttribute('data-ip-step-state');
      item.classList.toggle('is-done', st === 'done');
      item.classList.toggle('is-error', st === 'error');
      item.classList.toggle('is-current', key === openKey);
      if (key === openKey) b.setAttribute('aria-current', 'step');
      else b.removeAttribute('aria-current');
      setText(b.querySelector('[data-ip-stepnav-state]'), ', ' + (st === 'done' ? (T.done || '').replace(/:$/, '') : (st === 'error' ? (T.unavailable || '') : (T.open || ''))));
    });
    // A single step (a requestable model: Modell only) needs no step bar.
    var off = shownItems < 2;
    if (nav.hidden !== off) nav.hidden = off;
  }

  /* ── Folding ──────────────────────────────────────────────────────────── */

  function visibleSteps() {
    return order.filter(function (k) { return !(stepInfo[k] && stepInfo[k].hidden) && !steps[k].hidden; });
  }

  function isDone(key) {
    return !!(stepInfo[key] && stepInfo[key].done);
  }

  /**
   * The step to open after a choice in "from" (null: from the start): the next
   * visible step below it without a tick (single-option steps carry one, so
   * they are skipped), else the first one above it, else Menge (stays open
   * once everything is ticked), else none.
   */
  function nextStep(from) {
    var list = visibleSteps();
    var i = from ? list.indexOf(from) : -1;
    var after = list.slice(i + 1).concat(i >= 0 ? list.slice(0, i) : []);
    for (var j = 0; j < after.length; j++) {
      if (after[j] !== 'qty' && !isDone(after[j])) return after[j];
    }
    if (list.indexOf('qty') !== -1) return 'qty';
    return null;
  }

  /** Bottom of the sticky header, so a revealed step is not hidden under it. */
  function headerOffset() {
    var h = parseFloat(win.getComputedStyle(doc.documentElement).getPropertyValue('--header-h')) || 0;
    var header = doc.querySelector('.ip-header, body > header');
    if (header) {
      var pos = win.getComputedStyle(header).position;
      if (pos === 'sticky' || pos === 'fixed') h = Math.max(h, header.getBoundingClientRect().bottom);
    }
    return h;
  }

  /** Scroll only when the step's head is out of view (or its first lines would sit below the fold). */
  function reveal(el) {
    if (!el) return;
    var top = headerOffset() + 12;
    var r = el.getBoundingClientRect();
    var barH = bar && bar.classList.contains('is-visible') ? bar.offsetHeight : 0;
    var bottom = win.innerHeight - barH;
    if (r.top >= top && r.top + Math.min(r.height, 200) <= bottom) return;
    win.scrollBy({ top: r.top - top, behavior: reduceMotion ? 'auto' : 'smooth' });
  }

  function animate(body, frames, done) {
    if (body._ipAnim) body._ipAnim.cancel();
    body.style.overflow = 'hidden';
    var a = body.animate(frames, { duration: 200, easing: 'cubic-bezier(.22, 1, .36, 1)' });
    body._ipAnim = a;
    var end = function () {
      if (body._ipAnim !== a) return;
      body._ipAnim = null;
      body.style.overflow = '';
      if (done) done();
    };
    a.onfinish = end;
    a.oncancel = end;
  }

  function setExpanded(key, on, smooth) {
    var el = steps[key];
    var body = el.querySelector('.ip-step__body');
    var edit = el.querySelector('[data-ip-step-edit]');
    el.classList.toggle('is-current', on);
    el.classList.toggle('is-folded', !on);
    if (edit) edit.setAttribute('aria-expanded', on ? 'true' : 'false');
    if (!body || body.hidden === !on) return;
    if (!smooth || reduceMotion || typeof body.animate !== 'function') {
      if (body._ipAnim) body._ipAnim.cancel();
      body.hidden = !on;
      return;
    }
    if (on) {
      body.hidden = false;
      animate(body, [{ height: '0px', opacity: 0 }, { height: body.scrollHeight + 'px', opacity: 1 }]);
    } else {
      var h = body.offsetHeight;
      animate(body, [{ height: h + 'px', opacity: 1 }, { height: '0px', opacity: 0 }], function () {
        if (!el.classList.contains('is-current')) body.hidden = true;
      });
    }
  }

  /**
   * Expand one step (null: none), fold all others. opts.focus moves focus to
   * its heading (or to the summary card when no step is left), opts.reveal
   * scrolls it into view when needed, opts.instant skips the animation.
   */
  function openStep(key, opts) {
    if (!folding) return;
    opts = opts || {};
    var changed = key !== openKey;
    openKey = key;
    order.forEach(function (k) { setExpanded(k, k === key, !opts.instant && settled); });
    renderNav();
    if (nav && key && navBtns[key]) {
      // Keep the current chip in view in the horizontally scrolling bar (phones), without moving the page.
      var item = navBtns[key].parentNode;
      var list = nav.querySelector('.ip-stepnav__list');
      if (list && list.scrollWidth > list.clientWidth + 1) {
        var l = item.offsetLeft - list.offsetLeft;
        if (l < list.scrollLeft || l + item.offsetWidth > list.scrollLeft + list.clientWidth) {
          list.scrollTo({ left: Math.max(0, l - 24), behavior: reduceMotion || opts.instant ? 'auto' : 'smooth' });
        }
      }
    }
    if (opts.focus) {
      var target = key ? steps[key].querySelector('.ip-step__head') : summaryTitle;
      if (target) target.focus({ preventScroll: true });
    }
    if (opts.reveal) {
      var el = key ? steps[key] : summary;
      win.setTimeout(function () { reveal(el); }, changed && !reduceMotion ? 220 : 0);
    }
  }

  /** Tick "key" with its current value (the Suite has already applied the choice). */
  function tick(key) {
    var sig = stepSig(key, snapshot());
    if (sig !== '') ticks[key] = sig;
  }

  /** After a choice in "key": tick it and move on once the Suite has updated its state. */
  function moveOn(key) {
    win.setTimeout(function () {
      tick(key);
      sync();
      openStep(nextStep(key), { focus: true, reveal: true });
    }, 0);
  }

  /**
   * Initial step, once the Suite has applied its defaults: nothing ticked,
   * Modell open (or the first step with a choice, when there is one model).
   * After a buy form POST the posted selection counts as chosen: all ticked, Menge open.
   */
  function settle() {
    if (settled || !folding) return;
    sync();
    var key;
    if (postedSelection() && visibleSteps().indexOf('qty') !== -1) {
      var s = snapshot();
      visibleSteps().forEach(function (k) { var sig = stepSig(k, s); if (sig !== '') ticks[k] = sig; });
      sync();
      key = 'qty';
    } else {
      key = nextStep(null);
    }
    openStep(key, { instant: true });
    settled = true;
  }

  /** A quantity set by the visitor (stepper, input, tier) ticks Menge; it stays open. */
  function qtyTouched() {
    lastChoice = 'qty';
    win.setTimeout(function () { tick('qty'); queue(); }, 0);
  }

  function enhanceSteps() {
    order.forEach(function (key) {
      var el = steps[key];
      var barEl = el.querySelector('.ip-step__bar');
      // The whole folded line reopens the step; the "Ändern" button is the keyboard control.
      if (barEl) {
        barEl.addEventListener('click', function () {
          if (key !== openKey) openStep(key, { focus: true, reveal: true });
        });
      }
      // A choice made by pointer, Enter or Space moves on. Capture phase: the Suite's
      // handlers stop propagation. Faded options and info buttons are left alone.
      el.addEventListener('click', function (e) {
        if (!e.target.closest || key !== openKey) return;
        if (e.target.closest('.ip-c-model-selector__info, .infoplus-dropdown__option-info, .ip-opt-off')) return;
        if (!e.target.closest('.ip-c-model-selector__option, .infoplus-dropdown__option')) return;
        lastChoice = key;
        if (!silentPick) moveOn(key);
      }, true);
    });
    Object.keys(navBtns).forEach(function (key) {
      navBtns[key].addEventListener('click', function () {
        openStep(key, { focus: true, reveal: true });
      });
    });
    if (qtyInput) qtyInput.addEventListener('change', qtyTouched);
    if (qtyRow) {
      qtyRow.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('[data-ip-qty="minus"], [data-ip-qty="plus"]')) qtyTouched();
      }, true);
    }
    if (tiers) {
      tiers.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('.ibpt-cell')) qtyTouched();
      }, true);
      tiers.addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.closest && e.target.closest('.ibpt-cell')) qtyTouched();
      }, true);
    }
  }

  /**
   * "Auswahl ändern" in the inquiry drawer (assets/js/inquiry.js): it fires the
   * cancelable infoplus:inquiry-edit-selection and closes its modal dialog. This
   * file takes over the focus: once the drawer is closed (infoplus:inquiry-close,
   * focus cannot leave a modal dialog before), it opens the step of the last
   * choice, else the first step that is not done, else Modell, and focuses its heading.
   */
  function editTarget() {
    var list = visibleSteps();
    if (lastChoice && list.indexOf(lastChoice) !== -1) return lastChoice;
    var next = nextStep(null);
    return next && next !== 'qty' ? next : (list[0] || null);
  }

  function editSelection() {
    if (!editPending) return;
    editPending = false;
    sync();
    var key = editTarget();
    if (key) openStep(key, { focus: true, reveal: true });
  }

  if (folding) {
    doc.addEventListener('infoplus:inquiry-edit-selection', function (e) {
      e.preventDefault();
      editPending = true;
      win.setTimeout(editSelection, 400); // in case the close event does not come
    });
    doc.addEventListener('infoplus:inquiry-close', function () {
      win.setTimeout(editSelection, 0);
    });
  }

  /* ── Summary card ─────────────────────────────────────────────────────── */

  function setText(el, value) {
    if (el && el.textContent !== value) el.textContent = value;
  }

  /** The chosen options, one nowrap span each with its separator, so a line never starts with "·". */
  function renderVariant(values) {
    var el = sum.variant;
    if (!el) return;
    var sig = values.join('\u0001');
    if (el.getAttribute('data-sig') === sig) return;
    el.setAttribute('data-sig', sig);
    el.textContent = '';
    values.forEach(function (v, i) {
      var span = doc.createElement('span');
      span.className = 'ip-summary__opt';
      span.textContent = v + (i < values.length - 1 ? ' ·' : '');
      el.appendChild(span);
      if (i < values.length - 1) el.appendChild(doc.createTextNode(' '));
    });
  }

  function renderSummary(s) {
    if (!summary) return;
    setText(sum.model, s.modelName || T.empty || '');
    renderVariant(s.locked ? [] : s.options.map(function (o) { return o.value; }));
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

  /* ── Options: inline tiles, unavailable options, radio group ─────────── */

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

  /** Options of a row that are shown (the model's options, faded or not). */
  function shownOptions(row) {
    return Array.prototype.filter.call(row.querySelectorAll('.infoplus-dropdown__option'), function (o) {
      return o.style.display !== 'none';
    });
  }

  function renderOptions() {
    if (!widgets.length) return;
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
        var checked = selected[attr] === value ? 'true' : 'false';
        if (opt.getAttribute('aria-checked') !== checked) opt.setAttribute('aria-checked', checked);
      });
      // Up to 8 options: tiles with the term image. More: a compact chip list.
      var shownList = shownOptions(row);
      var layout = shownList.length > 8 ? 'list' : 'tiles';
      if (row.getAttribute('data-ip-layout') !== layout) row.setAttribute('data-ip-layout', layout);
      // Roving tabindex: the chosen option, else the first selectable one.
      var stop = null;
      shownList.forEach(function (o) { if (!stop && o.getAttribute('aria-checked') === 'true') stop = o; });
      if (!stop) shownList.forEach(function (o) { if (!stop && !o.classList.contains('ip-opt-off')) stop = o; });
      Array.prototype.forEach.call(row.querySelectorAll('.infoplus-dropdown__option'), function (o) {
        var ti = o === stop ? '0' : '-1';
        if (o.getAttribute('tabindex') !== ti) o.setAttribute('tabindex', ti);
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

  /**
   * Keys in an option group (capture phase, so the Suite's listbox keys do not
   * run): arrows, Home and End move and choose without moving on, Enter and
   * Space choose and move on. Keys on an info button only open its info.
   */
  function optionKeys(e) {
    var t = e.target;
    if (!t.closest) return;
    if (t.closest('.infoplus-dropdown__option-info')) {
      if (e.key === 'Enter' || e.key === ' ') e.stopPropagation(); // the button opens its info, nothing else
      return;
    }
    var opt = t.closest('.infoplus-dropdown__option');
    if (!opt || opt !== t) return;
    var row = opt.closest('.infoplus-dropdown');
    var list = shownOptions(row).filter(function (o) { return !o.classList.contains('ip-opt-off'); });
    var i = list.indexOf(opt);
    var next = null;
    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') next = list[(i + 1) % list.length];
    else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') next = list[(i - 1 + list.length) % list.length];
    else if (e.key === 'Home') next = list[0];
    else if (e.key === 'End') next = list[list.length - 1];
    else if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      e.stopPropagation();
      if (!opt.classList.contains('ip-opt-off')) opt.click(); // the Suite selects, the step moves on
      return;
    } else if (e.key === 'Escape') {
      e.stopPropagation();
      return;
    } else {
      return;
    }
    e.preventDefault();
    e.stopPropagation();
    if (!next || next === opt) return;
    next.focus();
    silentPick = true;
    next.click();
    silentPick = false;
  }

  /* ── Colour swatches ─────────────────────────────────────────────────────
     A small block before the option name in colour attributes. Per term:
     1. the term image (the Suite's option image) wins;
     2. else the colour set on the term ("Farbe (Hex)", Suite 0.10.31: data-color);
     3. else a colour guessed from the German term name (swatchFor());
     4. else no swatch.
     Colour attributes: slug or label containing "farbe", "color" or "colour",
     or any term with a colour set. Attributes whose terms only have images
     (Format, Befestigung: product photos) keep their images and get no guessed swatch. */

  var METALS = {
    silber: ['#f4f5f7', '#c3c7cc', '#eceef0', '#9fa4ab'],
    gold: ['#f7e7a1', '#d4af37', '#f3dc8a', '#b08a1e'],
    chrom: ['#ffffff', '#b4b9bf', '#f4f5f6', '#868b92'],
    messing: ['#efdc9a', '#b5a642', '#dcc872', '#9a8a2e'],
    kupfer: ['#f3bd96', '#b87333', '#dc9563', '#8f5424']
  };
  var COLOURS = [ // most specific first
    ['dunkelblau', '#1f2f5c'], ['hellblau', '#8cb8e0'], ['blau', '#2e6db4'],
    ['lichtgrau', '#d3d5d9'], ['hellgrau', '#d3d5d9'], ['dunkelgrau', '#55595f'], ['grau', '#8d9096'],
    ['anthrazit', '#3b3f44'], ['schwarz', '#1b1b1b'], ['weiss', '#ffffff'], ['weiß', '#ffffff'],
    ['dunkelrot', '#8b1424'], ['rot', '#c8102e'], ['dunkelgrün', '#1f5c3a'], ['grün', '#2e8b57'], ['gruen', '#2e8b57'],
    ['gelb', '#f2c200'], ['orange', '#ee7f00'], ['braun', '#6b4226'], ['beige', '#e3d3b4'], ['pink', '#e6007e'], ['magenta', '#e6007e']
  ];
  var CHECKER = 'repeating-conic-gradient(#dfe0e6 0 25%, #ffffff 0 50%) 0 0 / 8px 8px';

  function rgba(hex, a) {
    var n = parseInt(hex.replace('#', ''), 16);
    return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
  }

  /** CSS background for a colour named in German ("Coating silber", "Rot transparent"), '' when unknown. */
  function swatchFor(name) {
    var n = ' ' + String(name || '').toLowerCase().replace(/[()/,&-]/g, ' ').replace(/\s+/g, ' ') + ' ';
    var has = function (w) { return n.indexOf(w) !== -1; };
    var clear = has('transparent') || has('klar') || has('transluzent');
    var matt = has(' matt');
    var bg = '';
    Object.keys(METALS).some(function (m) {
      if (!has(m)) return false;
      var c = METALS[m];
      // Brushed metal; matt flattens the highlights.
      bg = matt ? 'linear-gradient(135deg, ' + c[1] + ', ' + c[2] + ' 60%, ' + c[1] + ')'
        : 'linear-gradient(135deg, ' + c[0] + ' 0%, ' + c[1] + ' 38%, ' + c[2] + ' 58%, ' + c[3] + ' 100%)';
      return true;
    });
    if (!bg && has('holz')) bg = 'repeating-linear-gradient(100deg, #c69c6d 0 4px, #b0844f 4px 6px, #cfa676 6px 10px)';
    if (!bg) {
      var hex = '';
      COLOURS.some(function (c) { if (has(c[0])) { hex = c[1]; return true; } return false; });
      if (hex && clear) {
        var a = has('transluzent') ? 0.7 : 0.45;
        bg = 'linear-gradient(' + rgba(hex, a) + ', ' + rgba(hex, a) + '), ' + CHECKER;
      } else if (hex) {
        bg = has('glänzend') || has('glaenzend')
          ? 'linear-gradient(160deg, rgba(255,255,255,.45), rgba(255,255,255,0) 45%), ' + hex
          : hex;
      } else if (clear) {
        bg = 'linear-gradient(rgba(220,232,240,.35), rgba(220,232,240,.35)), ' + CHECKER; // klar transparent
      }
    }
    return bg;
  }

  function isColourAttribute(row) {
    var key = (row.getAttribute('data-attribute') || '') + ' ' + text(row.querySelector('.infoplus-dropdown__label'));
    if (/farbe|colou?r/i.test(key)) return true;
    return !!row.querySelector('.infoplus-dropdown__option[data-color]');
  }

  function addSwatches(row) {
    if (!isColourAttribute(row)) return;
    row.classList.add('ip-vd-colour');
    Array.prototype.forEach.call(row.querySelectorAll('.infoplus-dropdown__option'), function (opt) {
      if (opt.querySelector('.infoplus-dropdown__option-image, .ip-swatch')) return; // 1. term image
      var colour = opt.getAttribute('data-color') || '';
      var bg = /^#[0-9a-f]{3,8}$/i.test(colour) ? colour : swatchFor(text(opt.querySelector('.infoplus-dropdown__option-text')));
      if (!bg) return;
      var sw = doc.createElement('span');
      sw.className = 'ip-swatch';
      sw.setAttribute('aria-hidden', 'true');
      sw.style.background = bg;
      opt.insertBefore(sw, opt.firstChild);
    });
  }

  function enhanceDropdowns() {
    widgets.forEach(function (w) {
      w.addEventListener('click', guardFaded, true);
      w.addEventListener('keydown', guardFaded, true);
      if (folding) w.addEventListener('keydown', optionKeys, true);
    });
    if (!folding) return;
    dropdownRows().forEach(function (row, i) {
      var base = 'ip-vd-' + i;
      var label = row.querySelector('.infoplus-dropdown__label');
      var list = row.querySelector('.infoplus-dropdown__options');
      var step = row.closest('[data-ip-step]');
      if (!label || !list) return;
      list.id = list.id || base + '-list';
      // Options inline as a radio group named by the step heading (the field label
      // repeats the heading; product.css hides it in the step layout).
      row.classList.add('ip-vd-inline');
      list.setAttribute('role', 'radiogroup');
      var head = step ? step.querySelector('.ip-step__head') : null;
      if (head && head.id) list.setAttribute('aria-labelledby', head.id);
      Array.prototype.forEach.call(list.querySelectorAll('.infoplus-dropdown__option'), function (opt, j) {
        opt.setAttribute('role', 'radio');
        opt.setAttribute('tabindex', '-1');
        var name = opt.querySelector('.infoplus-dropdown__option-text');
        if (name) {
          name.id = name.id || base + '-o' + j;
          opt.setAttribute('aria-labelledby', name.id);
        }
      });
      // The combobox trigger is not used in the inline layout (product.css hides it).
      var trigger = row.querySelector('.infoplus-dropdown__trigger');
      if (trigger) trigger.setAttribute('tabindex', '-1');
      addSwatches(row);
    });
  }

  function modelCards() {
    return modelWidget ? Array.prototype.slice.call(modelWidget.querySelectorAll('.ip-c-model-selector__option')) : [];
  }

  /** A model without picture (the Suite falls back to the WooCommerce placeholder): text-only card. */
  function hasNoImage(card) {
    var src = card.getAttribute('data-model-image') || '';
    if (!src) return true;
    if (placeholderSrc && src === placeholderSrc) return true;
    return /woocommerce-placeholder/.test(src);
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

  /** Model cards as a radio group: Tab to the group, arrows move and choose, Enter / Space choose and move on. */
  function enhanceModels() {
    var cards = modelCards();
    if (!cards.length) return;
    var list = modelWidget.querySelector('.ip-c-model-selector__options');
    list.setAttribute('role', 'radiogroup');
    if (steps.model) list.setAttribute('aria-labelledby', steps.model.getAttribute('aria-labelledby'));
    cards.forEach(function (card, i) {
      var name = card.querySelector('.ip-c-model-selector__label');
      card.setAttribute('role', 'radio');
      card.classList.toggle('ip-ms-noimg', hasNoImage(card));
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
        silentPick = true;
        next.click();
        silentPick = false;
      });
    });
    renderModels();
  }

  /* ── Render loop ──────────────────────────────────────────────────────── */

  /**
   * The quantity the visitor set stays in the shared state too, whatever the
   * Menge step shows (folded, or its input disabled by the bulk calculator while
   * a variation loads). The inquiry drawer reads [data-ip-qty="value"], then
   * infoplusProductState.currentQty. Plain assignment, no event: the Suite owns qty:changed.
   */
  function keepQty() {
    var ps = state();
    var q = qtyInput ? parseInt(qtyInput.value, 10) : NaN;
    if (ps && q > 0 && parseInt(ps.currentQty, 10) !== q) ps.currentQty = q;
  }

  /**
   * The bulk calculator inserts its minimum quantity note (#qty-limit-message)
   * after the first element matching [class*="qty"] it finds upward from the
   * input, which is the input itself, so the note lands inside the stepper.
   * Move it below the stepper; the calculator finds it again by its id.
   */
  function placeQtyNote() {
    var note = doc.getElementById('qty-limit-message');
    var stepper = qtyInput ? qtyInput.closest('[data-ip-qty-controls]') : null;
    if (note && stepper && stepper.contains(note)) stepper.parentNode.insertBefore(note, stepper.nextSibling);
  }

  function sync() {
    queued = false;
    placeQtyNote();
    keepQty();
    renderOptions();
    var s = snapshot();
    applyType(s.type);
    renderSteps(s);
    renderSummary(s);
    renderBar(s);
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
    var qtyControls = qtyInput ? qtyInput.closest('[data-ip-qty-controls]') : null;
    if (qtyControls) mo.observe(qtyControls, { childList: true }); // the minimum quantity note
    mo.observe(actions, { childList: true });
    if (buyForm) mo.observe(buyForm.parentNode, { childList: true });
    widgets.forEach(function (w) { mo.observe(w, { attributes: true, attributeFilter: ['class'] }); });
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

  // First paint: the folding applies right away, so nothing jumps when the Suite loads.
  // Modell open when there is a choice of models, else the first attribute step;
  // settle() decides again once the Suite has applied its defaults.
  if (folding) {
    enhanceSteps();
    sync();
    openStep(order[0], { instant: true });
  } else {
    sync();
  }

  // After the Suite has initialised (its handlers run on jQuery ready).
  var ready = function () {
    win.setTimeout(function () {
      enhanceDropdowns();
      enhanceModels();
      var data = productData();
      var finish = function () {
        restorePosted();
        settle();
        queue();
      };
      if (data && data._variationsDeferred && !(data.variations && data.variations.length)) {
        // Deferred variations: the Suite applies its defaults right after this event.
        var onLoaded = function () {
          doc.removeEventListener('infoplus:variations:loaded', onLoaded);
          win.setTimeout(finish, 0);
        };
        doc.addEventListener('infoplus:variations:loaded', onLoaded);
      } else {
        finish();
      }
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

/* Reveal of the "Individuelle Anfrage" panel ([data-ip-reveal],
   template-parts/product/details.php): cards rise in and the Sonderkontur
   drawing draws itself once, when the panel scrolls into view. Only a panel
   that starts below the fold is hidden first (.is-pending), so nothing that
   is already on screen flashes. No IntersectionObserver or reduced motion:
   nothing is hidden, the drawing shows finished (product.css). */
(function () {
  'use strict';
  var els = document.querySelectorAll('[data-ip-reveal]');
  if (!els.length || !('IntersectionObserver' in window)) { return; }
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) { return; }
      entry.target.classList.add('is-in');
      entry.target.classList.remove('is-pending');
      io.unobserve(entry.target);
    });
  }, { threshold: 0.2 });
  Array.prototype.forEach.call(els, function (el) {
    var r = el.getBoundingClientRect();
    if (r.top < window.innerHeight && r.bottom > 0) { return; } // On screen already: leave it as it is.
    el.classList.add('is-pending');
    io.observe(el);
  });
})();
