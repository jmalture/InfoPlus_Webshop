/* Namensschild-Berater (page-berater.php, inc/finder.php).

   Data: #ip-finder-data, built by the Infoplus Suite (module Finder):
   candidates (one per product family and model) with option (self/logo/print),
   fastenings, base price, Staffelpreise, minimum quantity, order type, image,
   link and placeholder traits (material, look, use cases).

   Flow: one question per screen. A click on a card picks and moves on; arrow
   keys only pick (the "Weiter" button moves on). Every answer updates the
   live preview (look of the current favourite, insert, logo, contour,
   fastening, quantity stack), the counter of fitting variants and the URL
   (?use=…&change=…), so a reload or a shared link shows the same result.

   Scoring is rule based and transparent: every rule that adds points also
   adds its sentence to the "Warum" list of the result card.
   Hard filters: fastening, minimum quantity, Serienfertigung only for 250+,
   material and Sonderkontur. When nothing is left, material and contour
   become soft and the results say so. */
(function () {
  'use strict';

  var doc = document;
  var win = window;
  var root = doc.querySelector('[data-ip-finder]');
  var dataEl = doc.getElementById('ip-finder-data');
  if (!root || !dataEl) return;

  var DATA;
  try { DATA = JSON.parse(dataEl.textContent || '{}'); } catch (e) { return; }
  var CANDS = DATA.candidates || [];
  var QS = DATA.questions || [];
  var KEYS = QS.map(function (q) { return q.key; });
  var META = DATA.meta || {};
  if (!CANDS.length || !QS.length) return;

  var $ = function (sel, ctx) { return (ctx || root).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); };
  var reduced = win.matchMedia && win.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var form = $('[data-ip-finder-form]');
  var sets = {};
  $$('[data-ip-fq]').forEach(function (fs) { sets[fs.getAttribute('data-ip-fq')] = fs; });
  var dots = {};
  $$('[data-ip-finder-dot]').forEach(function (d) { dots[d.getAttribute('data-ip-finder-dot')] = d; });
  var done = $('[data-ip-finder-done]');
  var answersList = $('[data-ip-finder-answers]');
  var btnBack = $('[data-ip-finder-back]');
  var btnNext = $('[data-ip-finder-next]');
  var btnShow = $('[data-ip-finder-show]');
  var btnRestart = $('[data-ip-finder-restart]');
  var badge = $('[data-ip-finder-badge]');
  var stack = $('[data-ip-finder-stack]');
  var nameEl = $('[data-ip-finder-name]');
  var nameInput = $('[data-ip-finder-name-input]');
  var countEl = $('[data-ip-finder-count]');
  var countLabel = $('[data-ip-finder-count-label]');
  var chipsEl = $('[data-ip-finder-chips]');
  var sceneEl = $('[data-ip-finder-scene]');
  var favEl = $('[data-ip-finder-fav]');
  var qtyEl = $('[data-ip-finder-qty]');
  var results = $('[data-ip-finder-results]');
  var picksEl = $('[data-ip-finder-picks]');
  var moreBox = $('[data-ip-finder-more]');
  var moreBtn = $('[data-ip-finder-more-toggle]');
  var moreList = $('[data-ip-finder-list]');
  var resultsSub = $('[data-ip-finder-results-sub]');

  var answers = {};
  var step = 0;            // index into KEYS; KEYS.length = done
  var keyPick = false;     // the last change came from the keyboard
  var lastLook = '';

  /* ── Texts ───────────────────────────────────────────────────────────── */

  var OPTION_OF = { often: 'self', sometimes: 'logo', rarely: 'print' };
  var WEG = { self: 'often', logo: 'sometimes', print: 'rarely' };
  var R = {
    option: {
      self: 'Namen ändern sich oft: neue Einleger drucken Sie jederzeit selbst.',
      logo: 'Ihr Logo drucken wir dauerhaft, die Namen tauschen Sie selbst.',
      print: 'Fertig mit Logo und Name: auspacken und tragen.'
    },
    material: {
      metal: 'Metall, wie gewünscht: edel und langlebig.',
      acrylic: 'Acrylglas, wie gewünscht: modern und glänzend.',
      wood: 'Holz, wie gewünscht: natürlich und nachhaltig.',
      plastic: 'Kunststoff, wie gewünscht: leicht und günstig.',
      writable: 'Tafel-Optik, wie gewünscht.'
    },
    use: {
      staff: 'Bewährt im täglichen Einsatz im Team.',
      event: 'Ideal für Messen und Events.',
      hospitality: 'Macht am Empfang und im Service eine gute Figur.',
      care: 'Passt in Praxis und Pflege.',
      industry: 'Gemacht für Werkstatt und Lager.'
    },
    fastener: {
      magnet: 'Mit Magnet erhältlich: keine Löcher in der Kleidung.',
      pin: 'Mit Nadel erhältlich: klassisch und günstig.',
      clip: 'Mit Clip erhältlich: schnell angeklemmt.'
    }
  };
  var FAST_LABEL = { magnet: 'Magnet', pin: 'Nadel', clip: 'Clip' };
  var ORDER_CTA = {
    editable: 'Online gestalten',
    buyable: 'Auswählen und bestellen',
    requestable: 'Anfrage stellen',
    requestable_with_variants: 'Anfrage stellen'
  };
  var ICONS = {
    staff: '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18"/>',
    event: '<path d="M4 7a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v3a2 2 0 0 0 0 4v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a2 2 0 0 0 0-4z"/>',
    hospitality: '<path d="M4 17h16M6 17a6 6 0 0 1 12 0M12 8V6M10 6h4"/><path d="M3 20h18"/>',
    care: '<path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z"/>',
    industry: '<path d="M14.5 5.5a4 4 0 0 0 4.9 5L21 12l-9 9-3-3 9-9-1.5-1.5a4 4 0 0 0-5-4.9l2.6 2.6-2 2-2.6-2.6"/>',
    magnet: '<path d="M6 4v8a6 6 0 0 0 12 0V4h-4v8a2 2 0 0 1-4 0V4z"/><path d="M6 8h4M14 8h4"/>',
    pin: '<path d="M4 20L17 7"/><circle cx="18.5" cy="5.5" r="2"/>',
    clip: '<path d="M8 4v11a4 4 0 0 0 8 0V6a2.5 2.5 0 0 0-5 0v9a1 1 0 0 0 2 0V7"/>',
    check: '<path d="M5 12.5l4.5 4.5L19 7"/>',
    arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>'
  };
  function icon(name) {
    return '<svg class="ip-icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + (ICONS[name] || '') + '</svg>';
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  var money = (function () {
    var nf;
    try { nf = new Intl.NumberFormat('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); } catch (e) { nf = null; }
    return function (n) {
      var v = nf ? nf.format(n) : (Math.round(n * 100) / 100).toFixed(2).replace('.', ',');
      return v + ' ' + (META.currency || '€');
    };
  })();
  function label(key, value) {
    var q = QS.filter(function (x) { return x.key === key; })[0];
    var o = q && q.options.filter(function (x) { return x.value === value; })[0];
    return o ? o.label : value;
  }
  function title(key) {
    var q = QS.filter(function (x) { return x.key === key; })[0];
    return q ? q.title : key;
  }

  /* ── Prices ──────────────────────────────────────────────────────────── */

  var BAND_MAX = { 5: 9, 25: 49, 100: 249, 300: Infinity };
  function qty() { return answers.qty ? parseInt(answers.qty, 10) : 0; }
  /** Unit price for a quantity: the matching Staffelpreis, else the base price; null below the first tier. */
  function unitPrice(c, q) {
    if (!q) return c.base;
    var tiers = c.tiers || [];
    for (var i = 0; i < tiers.length; i++) {
      if (q >= tiers[i].from && (!tiers[i].to || q <= tiers[i].to)) return tiers[i].price;
    }
    if (tiers.length && q < tiers[0].from) return null;
    if (tiers.length && q > (tiers[tiers.length - 1].to || Infinity)) return tiers[tiers.length - 1].price;
    return c.base;
  }
  function lowest(c) {
    var p = [c.base].concat((c.tiers || []).map(function (t) { return t.price; })).filter(function (v) { return v > 0; });
    return p.length ? Math.min.apply(null, p) : 0;
  }

  /* ── Filter and score ────────────────────────────────────────────────── */

  function passes(c, a, soft) {
    var q = a.qty ? parseInt(a.qty, 10) : 0;
    if (c.series && !(q >= 250)) return false;
    if (a.fastener && a.fastener !== 'any' && c.fasteners.indexOf(a.fastener) === -1) return false;
    if (q && c.minQty > (BAND_MAX[q] || Infinity)) return false;
    if (!soft && a.material && a.material !== 'any' && c.traits.material !== a.material) return false;
    if (!soft && a.logo === 'shape' && !c.contour) return false;
    return true;
  }

  function score(c, a) {
    var s = 0;
    var why = [];
    var t = c.traits || {};
    if (a.change) {
      var want = OPTION_OF[a.change];
      if (c.option === want) { s += 40; why.push([3, R.option[want]]); }
      else if (want === 'logo' || c.option === 'logo') s += 12;
    }
    if (a.logo === 'shape') {
      if (c.ownContour) { s += 30; why.push([4, 'Sonderkontur: das Schild in der Form Ihres Logos.']); }
      else if (c.contour) { s += 24; why.push([4, 'Kontur nach Ihrem Logo möglich.']); }
      else s -= 15;
    } else if (a.logo === 'logo') {
      if (c.option !== 'self') s += 8;
      if (c.ownContour) s -= 6;
    } else if (a.logo === 'none') {
      if (c.option === 'self' || c.engraved) s += 6;
      if (c.option === 'logo') s -= 8;
      if (c.ownContour) s -= 10;
    }
    if (a.material && a.material !== 'any') {
      if (t.material === a.material) { s += 25; why.push([2, R.material[a.material]]); } else s -= 10;
    }
    if (a.use) {
      if ((t.uses || []).indexOf(a.use) !== -1) { s += 15; why.push([1, R.use[a.use]]); }
      if (a.use === 'care' && t.hygienic >= 3) { s += 10; why.push([2, 'Glatte, gut abwischbare Oberfläche.']); }
      if (a.use === 'industry' && t.robust >= 3) { s += 10; why.push([2, 'Besonders robust im harten Alltag.']); }
      if (a.use === 'event' && c.option === 'self') s += 6;
      if (a.use === 'hospitality') s += ((t.premium || 2) - 2) * 6;
    }
    if (a.fastener && a.fastener !== 'any' && c.fasteners.indexOf(a.fastener) !== -1) { s += 4; why.push([0, R.fastener[a.fastener]]); }
    var q = qty();
    if (q >= 250 && c.series) { s += 15; why.push([3, 'Serienfertigung: der beste Preis für große Mengen.']); }
    if (t.eco) why.push([0, 'Aus nachwachsendem Rohstoff.']);
    s += Math.min(c.variants || 0, 40) / 40 * 3;  // more choice inside the model, tiny tie-breaker
    why.sort(function (x, y) { return y[0] - x[0]; });
    return { c: c, score: s, why: why.map(function (w) { return w[1]; }) };
  }

  function rank(a) {
    var hard = CANDS.filter(function (c) { return passes(c, a, false); });
    var relaxed = false;
    if (!hard.length) {
      hard = CANDS.filter(function (c) { return passes(c, a, true); });
      relaxed = true;
    }
    var q = qty();
    var list = hard.map(function (c) {
      var r = score(c, a);
      r.unit = unitPrice(c, q);
      return r;
    }).sort(function (x, y) { return y.score - x.score || lowest(x.c) - lowest(y.c); });
    return { list: list, relaxed: relaxed };
  }

  /** Unit price at the chosen quantity, else the lowest price. */
  function priceOf(r) { return r.unit != null ? r.unit : lowest(r.c); }

  /**
   * Beste Wahl, Preis-Tipp, Premium: three families where possible.
   * The labels must be true: a Preis-Tipp is cheaper than the Beste Wahl, a
   * Premium is finer (premium trait) than it. When no candidate qualifies,
   * the slot goes to the next best family, labelled "Alternative".
   */
  function picks(list) {
    if (!list.length) return [];
    var best = list[0];
    var floor = best.score > 0 ? best.score * 0.6 : -Infinity;
    var good = list.filter(function (r) { return r.score >= floor; });
    var used = {};
    used[best.c.pid] = true;
    function pickFrom(arr, better, ok) {
      var found = null;
      arr.forEach(function (r) {
        if (used[r.c.pid] || !ok(r)) return;
        if (!found || better(r, found)) found = r;
      });
      return found;
    }
    var prem = function (r) { return r.c.traits.premium || 0; };
    var cheaper = function (r, o) { return priceOf(r) < priceOf(o) || (priceOf(r) === priceOf(o) && r.score > o.score); };
    var finer = function (r, o) { return prem(r) > prem(o) || (prem(r) === prem(o) && r.score > o.score); };
    var isCheaper = function (r) { return priceOf(r) < priceOf(best); };
    var isFiner = function (r) { return prem(r) > prem(best); };

    var out = [{ kind: 'best', r: best }];
    var price = pickFrom(good, cheaper, isCheaper) || pickFrom(list, cheaper, isCheaper);
    if (price) { used[price.c.pid] = true; out.push({ kind: 'price', r: price }); }
    var premium = pickFrom(good, finer, isFiner) || pickFrom(list, finer, isFiner);
    if (premium) { used[premium.c.pid] = true; out.push({ kind: 'premium', r: premium }); }
    for (var i = 0; out.length < 3 && i < list.length; i++) {
      if (!used[list[i].c.pid]) { used[list[i].c.pid] = true; out.push({ kind: 'alt', r: list[i] }); }
    }
    // Fewer than three families left: other models of the same families.
    var shown = out.map(function (p) { return p.r.c.id; });
    for (var j = 0; out.length < 3 && j < list.length; j++) {
      if (shown.indexOf(list[j].c.id) !== -1) continue;
      shown.push(list[j].c.id);
      var kind = !price && isCheaper(list[j]) ? 'price' : 'alt';
      if (kind === 'price') price = list[j];
      out.push({ kind: kind, r: list[j] });
    }
    return out;
  }

  /* ── URL state ───────────────────────────────────────────────────────── */

  function readUrl() {
    var p = new URLSearchParams(win.location.search);
    KEYS.forEach(function (k) {
      var v = p.get(k);
      if (v && sets[k] && sets[k].querySelector('input[value="' + (win.CSS && CSS.escape ? CSS.escape(v) : v) + '"]')) answers[k] = v;
    });
    var weg = p.get('weg');
    if (weg && WEG[weg] && !answers.change) answers.change = WEG[weg];
  }
  function writeUrl() {
    if (!win.history || !history.replaceState) return;
    var p = new URLSearchParams(win.location.search);
    p.delete('weg');
    KEYS.forEach(function (k) { if (answers[k]) p.set(k, answers[k]); else p.delete(k); });
    var qs = p.toString();
    history.replaceState(null, '', win.location.pathname + (qs ? '?' + qs : '') + win.location.hash);
  }

  /* ── Steps ───────────────────────────────────────────────────────────── */

  function firstOpen() {
    for (var i = 0; i < KEYS.length; i++) if (!answers[KEYS[i]]) return i;
    return KEYS.length;
  }

  function sync() {
    KEYS.forEach(function (k, i) {
      var d = dots[k];
      if (d) {
        d.classList.toggle('is-done', !!answers[k] && i !== step);
        d.classList.toggle('is-current', i === step);
      }
      $$('input', sets[k]).forEach(function (inp) { inp.checked = answers[k] === inp.value; });
    });
    var isDone = step >= KEYS.length;
    btnBack.hidden = step === 0;
    btnNext.hidden = isDone || !answers[KEYS[step]];
    btnShow.hidden = true;
    btnRestart.hidden = !Object.keys(answers).length;
  }

  function go(i, opts) {
    opts = opts || {};
    var back = i < step;
    step = Math.max(0, Math.min(i, KEYS.length));
    KEYS.forEach(function (k, n) {
      var fs = sets[k];
      fs.hidden = n !== step;
      fs.classList.remove('is-in', 'is-in-back');
      if (n === step && !opts.instant) { void fs.offsetWidth; fs.classList.add(back ? 'is-in-back' : 'is-in'); }
    });
    done.hidden = step < KEYS.length;
    if (step >= KEYS.length) {
      renderAnswers();
      showResults(!opts.instant);
    } else {
      results.hidden = true;
      var legend = sets[KEYS[step]].querySelector('legend');
      if (legend && opts.focus !== false) {
        legend.focus({ preventScroll: true });
        var top = form.getBoundingClientRect().top;
        if (top < 0 || top > win.innerHeight * 0.6) form.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
      }
    }
    sync();
  }

  function renderAnswers() {
    answersList.textContent = '';
    KEYS.forEach(function (k, i) {
      if (!answers[k]) return;
      var li = doc.createElement('li');
      li.className = 'ip-finder__answer';
      li.innerHTML = '<span><span class="ip-finder__answer-q">' + esc(title(k)) + '</span><br><span class="ip-finder__answer-a">' + esc(label(k, answers[k])) + '</span></span>' +
        '<button type="button" data-ip-finder-edit="' + i + '">Ändern<span class="ip-sr">: ' + esc(title(k)) + '</span></button>';
      answersList.appendChild(li);
    });
  }

  /* ── Preview ─────────────────────────────────────────────────────────── */

  function chip(text, value, isNew) {
    return '<span class="ip-fchip' + (isNew ? ' is-new' : '') + '">' + (value ? '<b>' + esc(text) + ':</b> ' + esc(value) : esc(text)) + '</span>';
  }
  var countTimer = null;

  function preview(changedKey) {
    var a = answers;
    var ranked = rank(a);
    var fav = ranked.list[0] ? ranked.list[0].c : null;

    // Counter
    // Counter: what is left after the filters. Nothing fits exactly: the
    // nearest alternatives are counted and the label says so.
    var n = ranked.list.length;
    if (countLabel) countLabel.textContent = ranked.relaxed ? 'ähnliche Varianten' : 'passende Varianten';
    if (countEl && countEl.textContent !== String(n)) {
      animateCount(countEl, parseInt(countEl.textContent, 10) || 0, n);
    }

    // Badge look: the favourite's material look (the answer wins when given)
    var look = fav ? (fav.traits.look || 'plain') : 'atlantis';
    if (!Object.keys(a).length) look = 'atlantis';
    if (look !== lastLook) {
      badge.className = badge.className.replace(/\bip-fbadge--[a-z]+\b/g, '').trim() + ' ip-fbadge--' + look;
      if (lastLook && !reduced) { badge.classList.remove('is-swap'); void badge.offsetWidth; badge.classList.add('is-swap'); }
      lastLook = look;
    }
    badge.setAttribute('data-option', a.change ? OPTION_OF[a.change] : '');
    badge.setAttribute('data-logo', a.logo || 'logo');

    // Fastening corner
    var fast = stack.querySelector('.ip-fbadge__fast');
    if (!fast) { fast = doc.createElement('span'); fast.className = 'ip-fbadge__fast'; fast.setAttribute('aria-hidden', 'true'); stack.appendChild(fast); }
    if (a.fastener && a.fastener !== 'any') { fast.innerHTML = icon(a.fastener); fast.classList.add('is-on'); } else fast.classList.remove('is-on');

    // Quantity stack
    var q = qty();
    stack.setAttribute('data-n', q >= 300 ? 4 : q >= 100 ? 3 : q >= 25 ? 2 : 1);
    if (q) { qtyEl.hidden = false; qtyEl.textContent = q >= 300 ? '× 250+' : '× ' + label('qty', a.qty).replace(/\s*bis\s*/, '–'); } else qtyEl.hidden = true;

    // Scene
    if (a.use) { sceneEl.hidden = false; sceneEl.innerHTML = icon(a.use) + esc(label('use', a.use)); } else sceneEl.hidden = true;

    // Chips: what is chosen so far
    var chips = [];
    if (a.change) chips.push(['change', chip('Beschriftung', { often: 'Selbst', sometimes: 'Logo von uns', rarely: 'Fertig von uns' }[a.change])]);
    if (a.logo) chips.push(['logo', chip('Logo', label('logo', a.logo).replace(/^(Ja|Nein), /, ''))]);
    if (a.material) chips.push(['material', chip('Optik', label('material', a.material))]);
    if (a.fastener) chips.push(['fastener', chip('Halt', label('fastener', a.fastener))]);
    chipsEl.innerHTML = chips.map(function (c) { return c[0] === changedKey ? c[1].replace('ip-fchip"', 'ip-fchip is-new"') : c[1]; }).join('');

    // Favourite
    if (fav && Object.keys(a).length) {
      favEl.innerHTML = 'Aktueller Favorit: <b>' + esc(fav.family) + '</b>, ' + esc(fav.label);
    } else {
      favEl.textContent = 'Beantworten Sie die Fragen, die Vorschau passt sich an.';
    }
  }

  function animateCount(el, from, to) {
    win.clearInterval(countTimer);
    el.classList.remove('is-tick'); void el.offsetWidth; el.classList.add('is-tick');
    if (reduced || Math.abs(to - from) < 2) { el.textContent = to; return; }
    var steps = 12, i = 0;
    countTimer = win.setInterval(function () {
      i++;
      el.textContent = Math.round(from + (to - from) * (i / steps));
      if (i >= steps) { win.clearInterval(countTimer); el.textContent = to; }
    }, 28);
  }

  /* ── Results ─────────────────────────────────────────────────────────── */

  var TAGS = { best: 'Beste Wahl', price: 'Preis-Tipp', premium: 'Premium', alt: 'Alternative' };

  function linkFor(c) {
    var q = qty();
    var url = c.url + (c.url.indexOf('?') === -1 ? '?' : '&') + 'menge=' + encodeURIComponent(q > 9 ? q : (c.minQty || 1));
    if (answers.fastener && answers.fastener !== 'any') url += '&befestigung=' + encodeURIComponent(answers.fastener);
    return url;
  }

  function pickCard(p) {
    var r = p.r, c = r.c;
    var q = qty();
    var unit = r.unit;
    var priceHtml;
    if (unit != null && q) {
      priceHtml = '<dd>' + esc(money(unit)) + ' <small>pro Stück bei ' + esc(q >= 300 ? '250+' : q) + ' Stück</small></dd>';
    } else {
      priceHtml = '<dd><small>ab</small> ' + esc(money(lowest(c))) + ' <small>pro Stück</small></dd>';
    }
    var why = r.why.slice(0, 3);
    var bestR = current.picks[0] ? current.picks[0].r : null;
    if (p.kind === 'price' && bestR) {
      why = ['Günstiger als die Beste Wahl: ' + money(priceOf(r)) + ' statt ' + money(priceOf(bestR)) + ' pro Stück.'].concat(why).slice(0, 3);
    }
    if (p.kind === 'premium') why = ['Hochwertiger in Material und Anmutung.'].concat(why).slice(0, 3);
    if (!why.length) why = ['Passt zu Ihren Angaben.'];
    var total = unit != null && q ? '<div class="ip-fpick__fact"><dt>Gesamt ca.</dt><dd>' + esc(money(unit * (q >= 300 ? 250 : q))) + '</dd></div>' : '';
    var fasts = c.fasteners.map(function (f) { return FAST_LABEL[f]; }).filter(Boolean).join(', ');
    var media = c.image
      ? '<img src="' + esc(c.image) + '" alt="" loading="lazy" decoding="async">'
      : '<span class="ip-fplate ip-fplate--lg ip-fplate--' + esc(c.traits.look) + ' ip-fplate--opt-' + esc(c.option) + '" aria-hidden="true"><span class="ip-fplate__logo"></span><span class="ip-fplate__lines"><span class="ip-fplate__name"></span><span class="ip-fplate__role"></span></span></span>';
    var request = c.orderType === 'requestable' || c.orderType === 'requestable_with_variants';
    var cta = request
      ? '<button type="button" class="ip-btn ip-btn--lg" data-ip-finder-ask="request" data-cid="' + esc(c.id) + '">' + esc(ORDER_CTA[c.orderType]) + '</button>'
      : '<a class="ip-btn ip-btn--lg" href="' + esc(linkFor(c)) + '">' + esc(ORDER_CTA[c.orderType] || 'Zum Modell') + icon('arrow') + '</a>';
    return '<li><article class="ip-fpick ip-card ip-fpick--' + p.kind + '">' +
      '<span class="ip-fpick__tag">' + esc(TAGS[p.kind]) + '</span>' +
      '<div class="ip-fpick__media">' + media + '</div>' +
      '<div class="ip-fpick__body">' +
        '<p class="ip-fpick__model">' + esc(c.label) + '</p>' +
        '<h3 class="ip-fpick__title">' + esc(c.family) + '</h3>' +
        (c.traits.claim ? '<p class="ip-fpick__claim">' + esc(c.traits.claim) + '</p>' : '') +
        '<ul class="ip-fpick__why" role="list"><li class="ip-fpick__why-title" aria-hidden="true">Warum</li>' +
          why.map(function (w) { return '<li>' + icon('check') + '<span>' + esc(w) + '</span></li>'; }).join('') + '</ul>' +
        '<dl class="ip-fpick__facts">' +
          '<div class="ip-fpick__fact ip-fpick__fact--price"><dt>Preis</dt>' + priceHtml + '</div>' +
          total +
          (fasts ? '<div class="ip-fpick__fact"><dt>Befestigung</dt><dd>' + esc(fasts) + '</dd></div>' : '') +
          (c.days ? '<div class="ip-fpick__fact"><dt>Fertigung</dt><dd>ca. ' + esc(c.days) + ' Arbeitstage</dd></div>' : '') +
          (c.minQty > 1 ? '<div class="ip-fpick__fact"><dt>Mindestmenge</dt><dd>' + esc(c.minQty) + ' Stück</dd></div>' : '') +
        '</dl>' +
        '<p class="ip-fpick__note">' + esc(META.tax || '') + ', zzgl. Versand. Preise laut Shop, Stand heute.</p>' +
      '</div>' +
      '<div class="ip-fpick__actions">' + cta +
        '<button type="button" class="ip-btn ip-btn--ghost" data-ip-finder-ask="sample" data-cid="' + esc(c.id) + '">Kostenloses Muster anfordern</button>' +
      '</div>' +
    '</article></li>';
  }

  var current = { picks: [], list: [], relaxed: false };

  function showResults(scroll) {
    var ranked = rank(answers);
    var ps = picks(ranked.list);
    current = { picks: ps, list: ranked.list, relaxed: ranked.relaxed };
    picksEl.innerHTML = ps.map(pickCard).join('');
    resultsSub.textContent = ranked.relaxed
      ? 'Für genau diese Kombination haben wir kein Modell. Hier sind die nächstbesten, mit anderem Material oder ohne Sonderkontur.'
      : (ranked.list.length === 1
        ? 'Genau eine Variante passt zu Ihren Angaben.'
        : ranked.list.length + ' Varianten passen zu Ihren Angaben. ' + (ps.length >= 3 ? 'Das sind die drei, die wir Ihnen empfehlen.' : 'Das ist unsere Empfehlung.'));

    // Preview: the best pick
    if (ps[0]) {
      var c = ps[0].r.c;
      favEl.innerHTML = 'Unsere Empfehlung: <b>' + esc(c.family) + '</b>, ' + esc(c.label);
    }

    // More
    var used = ps.map(function (p) { return p.r.c.id; });
    var rest = ranked.list.filter(function (r) { return used.indexOf(r.c.id) === -1; });
    moreBox.hidden = !rest.length;
    moreList.hidden = true;
    moreBtn.setAttribute('aria-expanded', 'false');
    moreBtn.textContent = 'Alle ' + rest.length + ' weiteren passenden Varianten';
    moreList.innerHTML = rest.slice(0, 24).map(function (r) {
      var unit = r.unit != null ? r.unit : lowest(r.c);
      return '<li><a class="ip-finder__row" href="' + esc(linkFor(r.c)) + '"><span class="ip-finder__row-name">' + esc(r.c.family) + '</span>' +
        '<span class="ip-finder__row-price">' + (r.unit == null ? 'ab ' : '') + esc(money(unit)) + '</span>' +
        '<span class="ip-finder__row-model">' + esc(r.c.label) + '</span></a></li>';
    }).join('');

    results.hidden = false;
    if (scroll) {
      var h = results.querySelector('h2');
      win.setTimeout(function () {
        results.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
        if (h) h.focus({ preventScroll: true });
      }, 120);
    }
  }

  /* ── Inquiry (Muster, Beratung, Anfrage) ─────────────────────────────── */

  function summaryText(c) {
    var lines = ['Über den Namensschild-Berater:'];
    KEYS.forEach(function (k) { if (answers[k]) lines.push('- ' + title(k) + ' ' + label(k, answers[k])); });
    if (c) lines.push('Empfehlung: ' + c.family + ', ' + c.label);
    return lines.join('\n');
  }

  function ask(kind, cid) {
    var api = win.InfoplusInquiry;
    var c = null;
    if (cid) c = CANDS.filter(function (x) { return x.id === cid; })[0] || null;
    if (!c && current.picks[0]) c = current.picks[0].r.c;
    if (!api || typeof api.open !== 'function') {
      win.location.href = '/kontakt/?produkt=' + encodeURIComponent(c ? c.product : '') + '#anfrage';
      return;
    }
    var q = qty();
    var ctx = { variant: 'product' };
    if (c) {
      ctx.product_id = c.pid;
      ctx.product_name = c.product;
      ctx.image = c.image || '';
      ctx.model = c.model;
      ctx.model_name = c.label;
      ctx.labels = answers.fastener && answers.fastener !== 'any' ? [{ label: 'Befestigung', value: FAST_LABEL[answers.fastener] }] : [];
      if (q) ctx.quantity = Math.max(q >= 300 ? 250 : q, c.minQty || 1);
    }
    api.open(ctx);
    var f = doc.querySelector('#ip-inquiry form') || doc.querySelector('[data-ip-inquiry-drawer] form');
    if (!f) return;
    var msg = f.elements.message;
    var samples = f.elements.samples;
    if (msg && !msg.value) {
      msg.value = (kind === 'sample' || kind === 'samples' ? 'Bitte senden Sie mir kostenlose Muster.\n\n' : '') + summaryText(c);
      msg.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (samples) {
      samples.checked = kind === 'sample' || kind === 'samples';
      samples.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  function toast(text) {
    var t = doc.createElement('p');
    t.className = 'ip-finder__toast';
    t.setAttribute('role', 'status');
    t.textContent = text;
    doc.body.appendChild(t);
    win.setTimeout(function () { t.remove(); }, 2400);
  }

  /* ── Events ──────────────────────────────────────────────────────────── */

  form.addEventListener('keydown', function (e) {
    if (/^Arrow/.test(e.key)) keyPick = true;
    if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('.ip-fopt__input')) {
      e.preventDefault();
      e.target.checked = true;
      keyPick = false;
      e.target.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });
  form.addEventListener('pointerdown', function () { keyPick = false; });
  form.addEventListener('submit', function (e) { e.preventDefault(); });

  form.addEventListener('change', function (e) {
    var inp = e.target;
    if (!inp.matches('.ip-fopt__input')) return;
    var key = inp.name;
    answers[key] = inp.value;
    writeUrl();
    preview(key);
    sync();
    if (keyPick) { keyPick = false; return; } // arrow keys: pick only, "Weiter" moves on
    var idx = KEYS.indexOf(key);
    win.setTimeout(function () {
      if (idx !== step) return;
      go(firstOpenAfter(idx));
    }, reduced ? 0 : 320);
  });
  function firstOpenAfter(idx) {
    for (var i = idx + 1; i < KEYS.length; i++) if (!answers[KEYS[i]]) return i;
    return KEYS.length;
  }

  btnNext.addEventListener('click', function () { go(firstOpenAfter(step)); });
  btnBack.addEventListener('click', function () { go(step - 1); });
  btnRestart.addEventListener('click', function () {
    answers = {};
    writeUrl();
    lastLook = '';
    preview();
    go(0);
  });
  answersList.addEventListener('click', function (e) {
    var b = e.target.closest('[data-ip-finder-edit]');
    if (b) go(parseInt(b.getAttribute('data-ip-finder-edit'), 10));
  });
  moreBtn.addEventListener('click', function () {
    var open = moreList.hidden;
    moreList.hidden = !open;
    moreBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-ip-finder-ask]');
    if (b) { ask(b.getAttribute('data-ip-finder-ask'), b.getAttribute('data-cid')); return; }
    if (e.target.closest('[data-ip-finder-share]')) {
      var url = win.location.href;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function () { toast('Link kopiert'); }, function () { toast(url); });
      } else toast(url);
    }
  });
  if (nameInput) {
    nameInput.addEventListener('input', function () {
      var v = nameInput.value.trim();
      nameEl.textContent = v || nameInput.getAttribute('placeholder') || '';
    });
  }

  /* ── Start ───────────────────────────────────────────────────────────── */

  readUrl();
  var start = firstOpen();
  preview();
  go(start, { instant: true, focus: false });
  writeUrl();
})();
