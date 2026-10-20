/* Inquiry drawer ("Individuelle Anfrage"), vanilla, deferred.

   Markup: template-parts/inquiry-drawer.php (<dialog id="ip-inquiry">) around the
   Infoplus Suite form (templates/inquiry-form.php, form[data-ip-inquiry]). The
   form's data-ip-inquiry-config holds the REST base, limits and German messages.

   Opening:
   - a click on [data-ip-action="inquiry"] (the product page button, also when the
     phone action bar forwards its click), [data-ip-inquiry-link], [data-ip-inquiry-open]
     (value "contact" for the general variant) or a link to #anfrage
   - document.dispatchEvent(new CustomEvent('infoplus:inquiry-open', { detail: context }))
   - window.InfoplusInquiry.open(context)
   - the hash #anfrage on page load (Kontakt page with ?produkt=, the no-JS fallback)

   Context (all optional): { variant: 'product'|'contact', product_id, product_name,
   image, model, model_name, attributes: { pa_x: slug }, labels: [{ label, value }],
   variation_id, quantity }. On product pages anything missing is read from the
   Suite's window.infoplusProductData / window.infoplusProductState.

   Events on document: infoplus:inquiry-open (detail.source = 'drawer', fired after
   opening; the Suite marks a product field readonly on it), infoplus:inquiry-close,
   infoplus:inquiry-sent ({ reference }), and the cancelable
   infoplus:inquiry-edit-selection when "Auswahl ändern" is used (cancel it to
   move focus yourself; otherwise the first configurator control gets focus).

   The server checks everything again; validation here is for a quick answer. */
(function () {
  'use strict';
  var doc = document;
  var win = window;
  var dialog = doc.getElementById('ip-inquiry');
  var form = dialog && dialog.querySelector('[data-ip-inquiry]');
  if (!form || typeof dialog.showModal !== 'function') return;

  var cfg = {};
  try { cfg = JSON.parse(form.getAttribute('data-ip-inquiry-config') || '{}'); } catch (e) { cfg = {}; }
  var msg = cfg.messages || {};
  var exts = cfg.extensions || ['pdf', 'ai', 'eps', 'svg', 'png', 'jpg', 'jpeg', 'webp'];

  var TRIGGERS = '[data-ip-action="inquiry"], [data-ip-inquiry-link], [data-ip-inquiry-open], a[href$="#anfrage"]';
  var TOKEN_TTL = 5 * 3600 * 1000;

  function $(sel, root) { return (root || form).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || form).querySelectorAll(sel)); }

  var body = $('[data-ip-inq-body]');
  var summary = $('[data-ip-inq-summary]');
  var steps = $$('[data-ip-inq-step]');
  var done = $('[data-ip-inq-done]');
  var btnBack = $('[data-ip-inq-back]');
  var btnNext = $('[data-ip-inq-next]');
  var btnSubmit = $('[data-ip-inq-submit]');
  var btnClose = $('[data-ip-inq-close]');
  var progress = $$('[data-ip-inq-progress]');
  var fileInput = $('[data-ip-inq-file-input]');
  var drop = $('[data-ip-inq-drop]');
  var fileList = $('[data-ip-inq-file-list]');
  var productBox = $('[data-ip-inq-product]');
  var chips = $('[data-ip-inq-chips]');
  var title = doc.getElementById('ip-inquiry-title');

  var live = doc.createElement('p');
  live.className = 'ip-sr';
  live.setAttribute('aria-live', 'polite');
  form.appendChild(live);

  var state = {
    step: 1,
    variant: dialog.getAttribute('data-variant') || 'product',
    context: {},
    token: '',
    tokenAt: 0,
    tokenReq: null,
    files: [],
    opener: null,
    sending: false,
    sent: false,
    editSelection: false
  };

  /* ── Helpers ─────────────────────────────────────────────────────────── */

  function fmt(str) {
    var args = Array.prototype.slice.call(arguments, 1);
    var i = 0;
    return String(str || '').replace(/%(?:(\d)\$)?([sd])/g, function (m, pos) {
      var v = pos ? args[pos - 1] : args[i++];
      return v === undefined ? '' : String(v);
    });
  }

  function size(bytes) {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1).replace(/\.0$/, '').replace('.', ',') + ' MB';
    return Math.max(1, Math.round(bytes / 1024)) + ' KB';
  }

  function announce(text) {
    live.textContent = '';
    win.setTimeout(function () { live.textContent = text; }, 30);
  }

  function emit(name, detail, cancelable) {
    var ev = new CustomEvent('infoplus:' + name, { detail: detail || {}, cancelable: !!cancelable });
    doc.dispatchEvent(ev);
    return ev;
  }

  function field(name) { return form.elements[name]; }

  function today() {
    var d = new Date();
    var m = d.getMonth() + 1;
    var day = d.getDate();
    return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (day < 10 ? '0' : '') + day;
  }

  function headers(extra) {
    var h = extra || {};
    if (cfg.nonce) h['X-WP-Nonce'] = cfg.nonce;
    if (state.token) h['X-IP-Inquiry-Token'] = state.token;
    return h;
  }

  function url(route) { return (cfg.rest || '/wp-json/infoplus/v1/') + route; }

  /* ── Token (fresh per drawer session, see the Suite's Token class) ─── */

  function ensureToken() {
    if (state.token && Date.now() - state.tokenAt < TOKEN_TTL) return Promise.resolve(state.token);
    if (state.tokenReq) return state.tokenReq;
    state.tokenReq = fetch(url(cfg.routes ? cfg.routes.session : 'inquiries/session'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: cfg.nonce ? { 'X-WP-Nonce': cfg.nonce } : {}
    }).then(function (r) {
      if (!r.ok) throw new Error('session');
      return r.json();
    }).then(function (data) {
      state.token = data.token || '';
      state.tokenAt = Date.now();
      state.tokenReq = null;
      return state.token;
    }, function (err) {
      state.tokenReq = null;
      throw err;
    });
    return state.tokenReq;
  }

  /* ── Variant and product context ─────────────────────────────────────── */

  function applyVariant(variant) {
    state.variant = variant === 'contact' ? 'contact' : 'product';
    dialog.setAttribute('data-variant', state.variant);
    form.setAttribute('data-variant', state.variant);
    $$('[data-ip-inq-only]', dialog).forEach(function (el) {
      var on = el.getAttribute('data-ip-inq-only') === state.variant;
      el.hidden = !on;
      $$('input, select, textarea, button', el).forEach(function (c) { c.disabled = !on; });
      if (/^(INPUT|SELECT|TEXTAREA|BUTTON)$/.test(el.tagName)) el.disabled = !on;
    });
    var msgField = field('message');
    if (msgField) {
      msgField.placeholder = msgField.getAttribute('data-placeholder-' + state.variant) || '';
      msgField.required = state.variant === 'contact';
    }
  }

  function termName(pd, key, slug) {
    var attr = pd.attributes && pd.attributes[key];
    if (!attr || !attr.terms) return slug;
    for (var i = 0; i < attr.terms.length; i++) {
      if (attr.terms[i].slug === slug) return attr.terms[i].name;
    }
    return slug;
  }

  /* Read the configurator: the Suite's product data and shared state. */
  function readProduct() {
    var pd = win.infoplusProductData;
    var st = win.infoplusProductState || {};
    if (!pd || !pd.id) return {};
    var ctx = {
      product_id: pd.id,
      product_name: pd.name,
      image: pd.image || '',
      model: '',
      model_name: '',
      attributes: {},
      labels: [],
      variation_id: 0,
      quantity: null
    };
    var slug = st.selectedModel;
    if (slug) {
      ctx.model = slug;
      ctx.model_name = termName(pd, 'pa_model', slug);
      (pd.models || []).forEach(function (m) {
        if (m.slug === slug) {
          ctx.model_name = m.name || ctx.model_name;
          if (m.image_url) ctx.image = m.image_url;
        }
      });
    }
    var sel = st.selectedAttributes || {};
    Object.keys(sel).forEach(function (key) {
      var k = key.replace(/^attribute_/, '');
      if (k === 'pa_model' || !sel[key]) return;
      ctx.attributes[k] = sel[key];
      var attr = pd.attributes && pd.attributes[k];
      ctx.labels.push({ label: attr ? attr.name : k, value: termName(pd, k, sel[key]) });
    });
    var v = st.selectedVariation;
    if (v) ctx.variation_id = v.id || v.variation_id || 0;
    var qtyEl = doc.querySelector('[data-ip-qty="value"], #qty_value');
    var qty = qtyEl ? parseInt(qtyEl.value, 10) : NaN;
    if (!(qty > 0)) qty = parseInt(st.currentQty, 10);
    if (qty > 0) ctx.quantity = qty;
    return ctx;
  }

  function renderContext(ctx) {
    if (!productBox) return;
    var hasProduct = !!(ctx.product_name || ctx.product_id);
    productBox.hidden = !hasProduct;
    if (!hasProduct) return;
    var name = $('[data-ip-inq-product-name]');
    var img = $('[data-ip-inq-product-image]');
    if (name && ctx.product_name) name.textContent = ctx.product_name;
    if (img) {
      if (ctx.image) { img.src = ctx.image; img.hidden = false; } else { img.hidden = true; }
    }
    if (chips) {
      chips.textContent = '';
      var items = [];
      if (ctx.model_name) items.push({ label: 'Modell', value: ctx.model_name });
      (ctx.labels || []).forEach(function (l) { items.push(l); });
      items.forEach(function (it) {
        var li = doc.createElement('li');
        li.className = 'ip-inq__chip';
        var l = doc.createElement('span');
        l.className = 'ip-inq__chip-label';
        l.textContent = it.label + ':';
        li.appendChild(l);
        li.appendChild(doc.createTextNode(' ' + it.value));
        chips.appendChild(li);
      });
      chips.hidden = !items.length;
    }
    var change = $('[data-ip-inq-change]');
    if (change) change.hidden = state.variant !== 'product' || !win.infoplusProductData;
    if (ctx.quantity && field('quantity')) field('quantity').value = ctx.quantity;
  }

  /* ── Steps ───────────────────────────────────────────────────────────── */

  function showStep(n, animate) {
    var back = n < state.step;
    state.step = n;
    steps.forEach(function (s) {
      var on = +s.getAttribute('data-ip-inq-step') === n;
      s.hidden = !on;
      s.classList.remove('is-in-next', 'is-in-back');
      if (on && animate) {
        void s.offsetWidth; // restart the entry animation
        s.classList.add(back ? 'is-in-back' : 'is-in-next');
      }
    });
    done.hidden = true;
    progress.forEach(function (p) {
      var i = +p.getAttribute('data-ip-inq-progress');
      p.classList.toggle('is-current', i === n);
      p.classList.toggle('is-done', i < n);
      if (i === n) p.setAttribute('aria-current', 'step'); else p.removeAttribute('aria-current');
    });
    btnBack.hidden = n === 1;
    btnNext.hidden = n !== 1;
    btnSubmit.hidden = n !== 2;
    btnClose.hidden = true;
    form.classList.remove('is-done');
    if (body) body.scrollTop = 0;
  }

  function focusStep(n) {
    var t = $('[data-ip-inq-step="' + n + '"] .ip-inq__step-title');
    if (t) t.focus({ preventScroll: true });
  }

  /* ── Validation ──────────────────────────────────────────────────────── */

  function setError(name, text) {
    var box = $('[data-ip-inq-error="' + name + '"]');
    var el = field(name);
    if (box) { box.textContent = text; box.hidden = false; }
    if (el && el.setAttribute) {
      el.setAttribute('aria-invalid', 'true');
      var wrap = el.closest('.ip-inq__field');
      if (wrap) wrap.classList.add('is-invalid');
    }
    if (name === 'files' && drop) drop.classList.add('is-invalid');
  }

  function clearError(name) {
    var box = $('[data-ip-inq-error="' + name + '"]');
    var el = field(name);
    if (box) { box.textContent = ''; box.hidden = true; }
    if (el && el.removeAttribute) {
      el.removeAttribute('aria-invalid');
      var wrap = el.closest('.ip-inq__field');
      if (wrap) wrap.classList.remove('is-invalid');
    }
    if (name === 'files' && drop) drop.classList.remove('is-invalid');
  }

  function checkField(name) {
    var el = field(name);
    if (!el || el.disabled) return '';
    var v = (el.type === 'checkbox') ? el.checked : String(el.value || '').trim();
    switch (name) {
      case 'quantity':
        return (/^\d+$/.test(v) && +v >= 1 && +v <= 1000000) ? '' : msg.quantity;
      case 'wish_date':
        if (!v) return '';
        if (!/^\d{4}-\d{2}-\d{2}$/.test(v)) return msg.wish_date_invalid;
        return v < today() ? msg.wish_date : '';
      case 'message':
        return (state.variant === 'contact' && !v) ? msg.message : '';
      case 'first_name':
        return v ? '' : msg.required_first_name;
      case 'last_name':
        return v ? '' : msg.required_last_name;
      case 'email':
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) ? '' : msg.email;
      case 'phone':
        return (!v || /^[0-9 +()\/.\-]{4,40}$/.test(v)) ? '' : msg.phone;
      case 'postcode':
        return (!v || /^[A-Za-z0-9 \-]{3,12}$/.test(v)) ? '' : msg.postcode;
      case 'privacy':
        return v ? '' : msg.privacy;
    }
    return '';
  }

  var STEP_FIELDS = {
    1: ['quantity', 'wish_date', 'message'],
    2: ['first_name', 'last_name', 'email', 'phone', 'postcode', 'privacy']
  };

  function checkFiles() {
    var busy = state.files.some(function (f) { return f.status === 'uploading'; });
    var failed = state.files.some(function (f) { return f.status === 'error'; });
    return busy ? msg.file_busy : (failed ? msg.file_failed : '');
  }

  function showSummary(count, text) {
    if (!count && !text) { summary.hidden = true; summary.textContent = ''; return; }
    summary.textContent = text || (count === 1 ? msg.summary_one : fmt(msg.summary_many, count));
    summary.hidden = false;
  }

  /* Validate one step; mark errors, announce, focus the first one. */
  function validateStep(n, focus) {
    var bad = [];
    STEP_FIELDS[n].forEach(function (name) {
      var err = checkField(name);
      if (err) { setError(name, err); bad.push(name); } else { clearError(name); }
    });
    if (n === 1) {
      var ferr = checkFiles();
      // Notes about rejected files were announced when they happened; they do not block the step.
      if (ferr) { setError('files', ferr); bad.push('files'); } else { clearError('files'); }
    }
    showSummary(bad.length);
    if (bad.length && focus !== false) {
      var first = bad[0] === 'files' ? (fileInput || drop) : field(bad[0]);
      if (first && first.focus) first.focus();
    }
    return bad.length === 0;
  }

  form.addEventListener('input', function (e) {
    var name = e.target.name;
    if (name && e.target.getAttribute('aria-invalid') === 'true') {
      var err = checkField(name);
      if (!err) clearError(name);
    }
  });
  form.addEventListener('change', function (e) {
    var name = e.target.name;
    if (name && e.target.getAttribute('aria-invalid') === 'true' && !checkField(name)) clearError(name);
  });
  form.addEventListener('focusout', function (e) {
    var name = e.target.name;
    // Format checks once the visitor leaves a filled field; empty required fields wait for "Weiter".
    if (['email', 'phone', 'postcode', 'wish_date', 'quantity'].indexOf(name) > -1 && String(e.target.value || '').trim() !== '') {
      var err = checkField(name);
      if (err) setError(name, err); else clearError(name);
    }
  });

  /* ── Files: drag and drop, picker, one request per file with progress ── */

  function totalBytes() {
    return state.files.reduce(function (sum, f) { return f.status === 'error' ? sum : sum + f.size; }, 0);
  }

  function addFiles(list) {
    var errors = [];
    Array.prototype.forEach.call(list || [], function (file) {
      var ext = (file.name.split('.').pop() || '').toLowerCase();
      var count = state.files.filter(function (f) { return f.status !== 'error'; }).length;
      if (exts.indexOf(ext) < 0 || file.name.indexOf('.') < 0) {
        errors.push(fmt(msg.file_type, file.name));
      } else if (count >= (cfg.maxFiles || 10)) {
        errors.push(fmt(msg.file_count, cfg.maxFiles || 10));
      } else if (totalBytes() + file.size > (cfg.maxBytes || 20971520) || file.size > (cfg.maxFileBytes || cfg.maxBytes || 20971520)) {
        errors.push(fmt(msg.file_size, file.name, size(cfg.maxBytes || 20971520)));
      } else {
        upload(addItem(file));
      }
    });
    if (errors.length) {
      setError('files', errors.join(' '));
      announce(errors.join(' '));
    } else {
      clearError('files');
    }
  }

  function addItem(file) {
    var ext = (file.name.split('.').pop() || '').toUpperCase().slice(0, 4);
    var li = doc.createElement('li');
    li.className = 'ip-inq__file is-uploading';
    li.innerHTML =
      '<span class="ip-inq__file-type" aria-hidden="true"></span>' +
      '<span class="ip-inq__file-body"><span class="ip-inq__file-name"></span>' +
      '<span class="ip-inq__file-meta"><span class="ip-inq__file-size"></span> · <span class="ip-inq__file-status"></span></span>' +
      '<span class="ip-inq__file-bar" aria-hidden="true"><span></span></span></span>' +
      '<button type="button" class="ip-inq__file-remove"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg></button>';
    li.querySelector('.ip-inq__file-type').textContent = ext;
    li.querySelector('.ip-inq__file-name').textContent = file.name;
    li.querySelector('.ip-inq__file-size').textContent = size(file.size);
    li.querySelector('.ip-inq__file-status').textContent = msg.uploading;
    var rm = li.querySelector('.ip-inq__file-remove');
    rm.setAttribute('aria-label', (msg.remove || 'Entfernen') + ': ' + file.name);
    var item = { file: file, name: file.name, size: file.size, status: 'uploading', id: '', el: li, xhr: null };
    rm.addEventListener('click', function () { removeItem(item); });
    fileList.appendChild(li);
    state.files.push(item);
    return item;
  }

  function setProgress(item, ratio) {
    var bar = item.el.querySelector('.ip-inq__file-bar > span');
    if (bar) bar.style.transform = 'scaleX(' + Math.max(0.02, Math.min(1, ratio)) + ')';
    var st = item.el.querySelector('.ip-inq__file-status');
    if (st && item.status === 'uploading') st.textContent = msg.uploading + ' ' + Math.round(ratio * 100) + ' %';
  }

  function finishItem(item, ok, text) {
    item.status = ok ? 'done' : 'error';
    item.el.classList.remove('is-uploading');
    item.el.classList.add(ok ? 'is-done' : 'is-error');
    var st = item.el.querySelector('.ip-inq__file-status');
    if (st) st.textContent = ok ? msg.uploaded : (text || msg.file_error);
    if (ok) setProgress(item, 1);
    announce(item.name + ': ' + (ok ? msg.uploaded : (text || msg.file_error)));
    if (!checkFiles()) clearError('files');
  }

  function upload(item) {
    ensureToken().then(function () {
      if (item.status !== 'uploading') return; // removed meanwhile
      var xhr = new XMLHttpRequest();
      item.xhr = xhr;
      xhr.open('POST', url(cfg.routes ? cfg.routes.files : 'inquiries/files'));
      var h = headers();
      Object.keys(h).forEach(function (k) { xhr.setRequestHeader(k, h[k]); });
      xhr.upload.addEventListener('progress', function (e) {
        if (e.lengthComputable) setProgress(item, e.loaded / e.total * 0.95);
      });
      xhr.addEventListener('load', function () {
        var data = {};
        try { data = JSON.parse(xhr.responseText || '{}'); } catch (e) { data = {}; }
        if (xhr.status === 201 && data.id) {
          item.id = data.id;
          finishItem(item, true);
        } else {
          finishItem(item, false, data.message);
        }
      });
      xhr.addEventListener('error', function () { finishItem(item, false); });
      var fd = new FormData();
      fd.append('file', item.file, item.name);
      xhr.send(fd);
    }, function () { finishItem(item, false, msg.network); });
  }

  function removeItem(item) {
    var idx = state.files.indexOf(item);
    if (idx < 0) return;
    if (item.xhr && item.status === 'uploading') item.xhr.abort();
    item.status = 'removed';
    if (item.id) {
      fetch(url((cfg.routes ? cfg.routes.files : 'inquiries/files') + '/' + item.id), { method: 'DELETE', credentials: 'same-origin', headers: headers() }).catch(function () {});
    }
    state.files.splice(idx, 1);
    var next = item.el.nextElementSibling || item.el.previousElementSibling;
    item.el.classList.add('is-leaving');
    var gone = function () { if (item.el.parentNode) item.el.parentNode.removeChild(item.el); };
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) gone(); else win.setTimeout(gone, 200);
    announce(item.name + ': ' + (msg.remove || 'Entfernen'));
    var target = next && next.querySelector ? next.querySelector('.ip-inq__file-remove') : null;
    (target || fileInput).focus();
    if (!checkFiles()) clearError('files');
  }

  if (fileInput) {
    fileInput.addEventListener('change', function () {
      addFiles(fileInput.files);
      fileInput.value = '';
    });
  }
  if (drop) {
    var depth = 0;
    drop.addEventListener('dragenter', function (e) { e.preventDefault(); depth++; drop.classList.add('is-over'); });
    drop.addEventListener('dragover', function (e) { e.preventDefault(); if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy'; });
    drop.addEventListener('dragleave', function () { depth = Math.max(0, depth - 1); if (!depth) drop.classList.remove('is-over'); });
    drop.addEventListener('drop', function (e) {
      e.preventDefault();
      depth = 0;
      drop.classList.remove('is-over');
      if (e.dataTransfer && e.dataTransfer.files) addFiles(e.dataTransfer.files);
    });
  }
  // A file dropped next to the zone must not replace the page.
  ['dragover', 'drop'].forEach(function (t) {
    dialog.addEventListener(t, function (e) { if (!drop || !drop.contains(e.target)) e.preventDefault(); });
  });

  /* ── Submit ──────────────────────────────────────────────────────────── */

  function payload() {
    var c = state.context || {};
    var data = {
      variant: state.variant,
      context: {
        product_id: c.product_id || 0,
        product_name: c.product_name || '',
        model: c.model || '',
        attributes: c.attributes || {},
        variation_id: c.variation_id || 0
      },
      files: state.files.filter(function (f) { return f.status === 'done' && f.id; }).map(function (f) { return f.id; }),
      source_url: win.location.href.split('#')[0]
    };
    ['quantity', 'wish_date', 'message', 'company', 'first_name', 'last_name', 'email', 'phone', 'postcode', 'country', 'ip_extra'].forEach(function (n) {
      var el = field(n);
      if (el && !el.disabled) data[n] = el.value;
    });
    ['recurring', 'samples', 'privacy'].forEach(function (n) {
      var el = field(n);
      data[n] = !!(el && !el.disabled && el.checked);
    });
    return data;
  }

  function busy(on) {
    state.sending = on;
    btnSubmit.classList.toggle('is-busy', on);
    btnSubmit.setAttribute('aria-disabled', on ? 'true' : 'false');
    btnBack.disabled = on;
    var label = btnSubmit.querySelector('[data-ip-label]');
    if (label) {
      if (!label.getAttribute('data-idle')) label.setAttribute('data-idle', label.textContent);
      label.textContent = on ? msg.sending : label.getAttribute('data-idle');
    }
  }

  function send() {
    if (state.sending) return;
    if (!validateStep(1, false)) { showStep(1, true); validateStep(1); return; }
    if (!validateStep(2)) return;
    busy(true);
    showSummary(0);
    ensureToken().then(function () {
      return fetch(url(cfg.routes ? cfg.routes.submit : 'inquiries'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(payload())
      });
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (data) { return { ok: r.ok, status: r.status, data: data }; });
    }).then(function (res) {
      busy(false);
      if (res.ok && res.data.reference) { success(res.data); return; }
      var fields = res.data && res.data.data && res.data.data.fields;
      if (res.status === 422 && fields) {
        var names = Object.keys(fields);
        var inFirst = names.some(function (n) { return STEP_FIELDS[1].indexOf(n) > -1; });
        if (inFirst && state.step !== 1) showStep(1, true);
        names.forEach(function (n) { setError(n, fields[n]); });
        showSummary(names.length);
        var first = names.filter(function (n) { return STEP_FIELDS[state.step].indexOf(n) > -1; })[0];
        if (first && field(first)) field(first).focus();
        return;
      }
      if (res.status === 403) { state.token = ''; }
      showSummary(1, (res.data && res.data.message) || msg.network);
      summary.focus();
    }).catch(function () {
      busy(false);
      showSummary(1, msg.network);
      summary.focus();
    });
  }

  function success(data) {
    state.sent = true;
    steps.forEach(function (s) { s.hidden = true; });
    $('[data-ip-inq-ref]').textContent = data.reference;
    var mailP = $('[data-ip-inq-done-mail]');
    if (mailP && data.email) {
      $('[data-ip-inq-done-email]').textContent = data.email;
      mailP.hidden = false;
    }
    if (data.message) $('[data-ip-inq-next-text]').textContent = data.message;
    done.hidden = false;
    done.classList.remove('is-in');
    void done.offsetWidth;
    done.classList.add('is-in');
    form.classList.add('is-done');
    progress.forEach(function (p) { p.classList.remove('is-current'); p.classList.add('is-done'); p.removeAttribute('aria-current'); });
    btnBack.hidden = true;
    btnNext.hidden = true;
    btnSubmit.hidden = true;
    btnClose.hidden = false;
    if (body) body.scrollTop = 0;
    $('.ip-inq__done-title').focus({ preventScroll: true });
    emit('inquiry-sent', { reference: data.reference });
  }

  /* After a sent request the next opening starts clean (contact data stays as prefilled). */
  function reset() {
    form.reset();
    state.files.forEach(function (f) { if (f.el.parentNode) f.el.parentNode.removeChild(f.el); });
    state.files = [];
    state.token = '';
    state.sent = false;
    $$('[data-ip-inq-error]').forEach(function (b) { clearError(b.getAttribute('data-ip-inq-error')); });
    showSummary(0);
    var mailP = $('[data-ip-inq-done-mail]');
    if (mailP) mailP.hidden = true;
    done.classList.remove('is-in');
    showStep(1, false);
  }

  btnNext.addEventListener('click', function () {
    if (validateStep(1)) {
      showSummary(0);
      showStep(2, true);
      focusStep(2);
    }
  });
  btnBack.addEventListener('click', function () {
    showSummary(0);
    showStep(1, true);
    focusStep(1);
  });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (state.sent) return;
    if (state.step === 1) { btnNext.click(); return; }
    send();
  });
  btnClose.addEventListener('click', function () { dialog.close(); });

  /* ── Open / close ────────────────────────────────────────────────────── */

  function open(ctx, opener) {
    ctx = ctx || {};
    if (dialog.open) return;
    if (state.sent) reset();
    var variant = ctx.variant || (win.infoplusProductData ? 'product' : (dialog.getAttribute('data-variant') || 'product'));
    applyVariant(variant);
    var base = variant === 'product' ? readProduct() : {};
    Object.keys(ctx).forEach(function (k) { if (ctx[k] !== undefined && ctx[k] !== null && ctx[k] !== '') base[k] = ctx[k]; });
    state.context = base;
    renderContext(base);
    var date = field('wish_date');
    if (date) date.min = today();
    showStep(1, false);
    showSummary(0);
    state.opener = opener || doc.activeElement;
    dialog.showModal();
    doc.body.classList.add('ip-locked');
    if (title) title.focus({ preventScroll: true });
    ensureToken().catch(function () {});
    emit('inquiry-open', { source: 'drawer', context: base });
  }

  function close() { if (dialog.open) dialog.close(); }

  dialog.addEventListener('close', function () {
    doc.body.classList.remove('ip-locked');
    var o = state.opener;
    state.opener = null;
    if (state.editSelection) {
      state.editSelection = false;
      focusConfigurator();
    } else if (o && o.isConnected && o.focus) {
      o.focus({ preventScroll: true });
    }
    emit('inquiry-close', {});
  });

  // The header close button. A click on the backdrop and Escape close the
  // dialog through site.js and the browser.
  dialog.addEventListener('click', function (e) {
    var t = e.target;
    if (t.closest && t.closest('[data-ip-inq-dismiss]')) { close(); return; }
    // Links that open a new tab (privacy page) must not close the drawer:
    // site.js closes a dialog on any link click inside it.
    var a = t.closest && t.closest('a[target="_blank"]');
    if (a) e.stopPropagation();
  });

  /* "Auswahl ändern": back to the configurator. */
  var changeBtn = $('[data-ip-inq-change]');
  if (changeBtn) {
    changeBtn.addEventListener('click', function () {
      var ev = emit('inquiry-edit-selection', { context: state.context }, true);
      state.editSelection = !ev.defaultPrevented;
      state.opener = null;
      close();
    });
  }

  function focusConfigurator() {
    // Theme configurator (template-parts/product/summary.php): the first step. Its "Ändern"
    // button when the step is folded, else its heading. Fallback: the Suite's model selector.
    var step = doc.querySelector('[data-ip-step]');
    var root = step || doc.querySelector('[data-ip-component="model-selector"], .ip-c-model-selector, [data-ip-component="variation-dropdowns"]');
    if (!root) return;
    var shown = function (el) { return el && el.getClientRects().length > 0; };
    var candidates = step
      ? [step.getAttribute('data-ip-step-state') !== 'open' ? step.querySelector('[data-ip-step-edit]') : null, step.querySelector('h2[tabindex], h3[tabindex], [tabindex="-1"]')]
      : [root.querySelector('.is-active, [aria-checked="true"], [aria-pressed="true"]'), root.querySelector('button, [tabindex="0"], select, input')];
    var target = candidates.filter(shown)[0];
    root.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    if (target && target.focus) target.focus({ preventScroll: true });
  }

  /* Triggers: capture phase, so the drawer wins over the link's own navigation. */
  doc.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button > 0 || e.metaKey || e.ctrlKey || e.shiftKey) return;
    var t = e.target.closest ? e.target.closest(TRIGGERS) : null;
    if (!t || dialog.contains(t)) return;
    e.preventDefault();
    var variant = t.getAttribute('data-ip-inquiry-open');
    // A click forwarded by the phone action bar is not trusted: return focus to the bar button.
    var opener = e.isTrusted ? t : (doc.activeElement && doc.activeElement !== doc.body ? doc.activeElement : t);
    open(variant === 'contact' || variant === 'product' ? { variant: variant } : null, opener);
  }, true);

  doc.addEventListener('infoplus:inquiry-open', function (e) {
    if (e.detail && e.detail.source === 'drawer') return;
    open(e.detail || null);
  });

  function openFromHash() {
    if (win.location.hash !== '#anfrage') return;
    var params = new URLSearchParams(win.location.search);
    var name = params.get('produkt');
    var ctx = win.infoplusProductData ? null : { variant: 'contact', product_name: name || '' };
    // Wait a moment on product pages so the configurator has applied its defaults.
    win.setTimeout(function () { open(ctx); }, win.infoplusProductData ? 400 : 0);
  }
  win.addEventListener('hashchange', openFromHash);
  openFromHash();

  win.InfoplusInquiry = {
    open: function (ctx) { open(ctx || null); },
    close: close,
    context: function () { return readProduct(); },
    isOpen: function () { return dialog.open; }
  };
})();
