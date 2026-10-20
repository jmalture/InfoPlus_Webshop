/* Home bundle: the occasions tabs ([data-ip-tabs]), WAI-ARIA tabs pattern.
   The markup is complete on the server (roles, aria-selected, hidden); this
   adds click and keyboard handling (arrows, Home, End; automatic activation).
   Without JS, home.css shows all panels and hides the tab row. */
(function () {
  'use strict';
  document.querySelectorAll('[data-ip-tabs]').forEach(function (box) {
    var tabs = Array.prototype.slice.call(box.querySelectorAll('[role="tab"]'));
    if (!tabs.length) return;

    function select(tab, focus) {
      tabs.forEach(function (t) {
        var on = t === tab;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        var panel = document.getElementById(t.getAttribute('aria-controls'));
        if (panel) panel.hidden = !on;
      });
      if (focus) {
        tab.focus();
        if (tab.scrollIntoView) tab.scrollIntoView({ block: 'nearest', inline: 'nearest' });
      }
    }

    tabs.forEach(function (tab, i) {
      tab.addEventListener('click', function () { select(tab, false); });
      tab.addEventListener('keydown', function (e) {
        var next = null;
        if (e.key === 'ArrowRight') next = tabs[(i + 1) % tabs.length];
        else if (e.key === 'ArrowLeft') next = tabs[(i - 1 + tabs.length) % tabs.length];
        else if (e.key === 'Home') next = tabs[0];
        else if (e.key === 'End') next = tabs[tabs.length - 1];
        if (next) { e.preventDefault(); select(next, true); }
      });
    });
  });
})();

/* Home bundle: the hero demo ([data-ip-demo], template-parts/hero-demo.php).
   A small timeline of steps; each step changes one thing and returns how
   long to wait before the next. One cycle: model (material look), name types
   itself, function line, logo, format (plate takes its proportions),
   fastener, Staffelpreis steps down through its tiers, "In den Warenkorb"
   ends with a tick, a pause, then the next model and name.

   Starts from the finished state the server rendered. Pauses while the
   pointer is over the demo, while focus is inside it, while it is off
   screen and while the tab is hidden; the pause button stops it until
   pressed again. Under prefers-reduced-motion nothing runs and the finished
   state stays (also when the setting changes while the page is open). */
(function () {
  'use strict';
  var root = document.querySelector('[data-ip-demo]');
  if (!root) return;
  var cfg;
  try { cfg = JSON.parse(root.getAttribute('data-ip-demo')); } catch (e) { return; }
  if (!cfg || !cfg.models || !cfg.models.length || !cfg.names || !cfg.names.length) return;

  var mq = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
  var q = function (sel) { return root.querySelector(sel); };
  var qa = function (sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); };
  var plate = q('[data-ip-demo-plate]');
  var nameEl = q('[data-ip-demo-name]');
  var roleEl = q('[data-ip-demo-role]');
  var logoEl = q('[data-ip-demo-logo]');
  var qtyEl = q('[data-ip-demo-qty]');
  var priceEl = q('[data-ip-demo-price]');
  var cartEl = q('[data-ip-demo-cart]');
  var pauseBtn = q('[data-ip-demo-pause]');
  if (!plate || !nameEl) return;

  var ease = 'cubic-bezier(.22, 1, .36, 1)';
  var cycle = 0;          // Index of the model / name shown now (0 = server state).
  var steps = [];         // Remaining steps of the current cycle.
  var timer = 0;
  var holds = {};         // Reasons to pause: user, hover, focus, offscreen, hidden.
  var running = false;

  function pick(kind, index) {
    qa('[data-ip-demo-' + kind + ']').forEach(function (chip) {
      var on = Number(chip.getAttribute('data-ip-demo-' + kind)) === index;
      if (on && !chip.classList.contains('is-on')) {
        chip.classList.add('is-pick');
        window.setTimeout(function () { chip.classList.remove('is-pick'); }, 260);
      }
      chip.classList.toggle('is-on', on);
    });
  }

  function setLook(look) {
    plate.className = plate.className.replace(/\bip-demo__plate--\S+/g, '').trim() + ' ip-demo__plate--' + look;
  }

  function swap(el, text) {
    if (!el) return;
    el.textContent = text;
    if (el.animate) el.animate([{ opacity: 0, transform: 'translateY(-6px)' }, { opacity: 1, transform: 'none' }], { duration: 260, easing: ease });
  }

  /** Steps of cycle n, as functions that return the wait in ms. */
  function build(n) {
    var m = cfg.models[n % cfg.models.length];
    var who = cfg.names[n % cfg.names.length];
    var f = cfg.formats.length ? n % cfg.formats.length : -1;
    var fa = cfg.fasteners.length ? n % cfg.fasteners.length : -1;
    var list = [];
    list.push(function () { // Reset what this cycle builds up again.
      if (logoEl) logoEl.classList.remove('is-on');
      if (roleEl) roleEl.classList.remove('is-on');
      if (cartEl) cartEl.classList.remove('is-done');
      if (cfg.prices.length) { if (qtyEl) qtyEl.textContent = cfg.prices[0].qty; if (priceEl) priceEl.textContent = cfg.prices[0].price; }
      return 120;
    });
    list.push(function () { // Model: chip, then the plate changes its material.
      pick('model', n % cfg.models.length);
      if (plate.animate) {
        plate.animate([{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'scale(.97)' }], { duration: 160, easing: 'ease-in' })
          .onfinish = function () {
            setLook(m.look);
            nameEl.textContent = '';
            plate.animate([{ opacity: 0, transform: 'scale(.97)' }, { opacity: 1, transform: 'none' }], { duration: 300, easing: ease });
          };
      } else {
        setLook(m.look);
        nameEl.textContent = '';
      }
      return 700;
    });
    list.push(function () { nameEl.textContent = ''; root.classList.add('is-typing'); return 260; });
    Array.prototype.forEach.call(who.name, function (ch, i) {
      list.push(function () {
        nameEl.textContent = who.name.slice(0, i + 1);
        return i === who.name.length - 1 ? 320 : (ch === ' ' ? 110 : 62);
      });
    });
    list.push(function () {
      root.classList.remove('is-typing');
      if (roleEl) { roleEl.textContent = who.role; roleEl.classList.add('is-on'); }
      return 420;
    });
    list.push(function () { if (logoEl) logoEl.classList.add('is-on'); return 650; });
    if (f >= 0) {
      list.push(function () {
        pick('format', f);
        plate.style.setProperty('--ip-pw', cfg.formats[f].pw);
        plate.style.setProperty('--ip-ratio', cfg.formats[f].ratio);
        return 850;
      });
    }
    if (fa >= 0) list.push(function () { pick('fastener', fa); return 750; });
    cfg.prices.forEach(function (tier, i) {
      if (i === 0) return;
      list.push(function () { swap(qtyEl, tier.qty); swap(priceEl, tier.price); return 680; });
    });
    if (cartEl) {
      list.push(function () { cartEl.classList.add('is-press'); return 180; });
      list.push(function () {
        cartEl.classList.remove('is-press');
        cartEl.classList.add('is-done');
        return 1900;
      });
    }
    return list;
  }

  function next() {
    timer = 0;
    if (!steps.length) {
      cycle++;
      steps = build(cycle);
    }
    var wait = steps.shift()();
    timer = window.setTimeout(next, wait);
  }

  function paused() {
    return Object.keys(holds).some(function (k) { return holds[k]; });
  }

  function update() {
    var should = !paused() && !(mq && mq.matches);
    if (should && !running) {
      running = true;
      timer = window.setTimeout(next, steps.length ? 400 : 1800); // First run: let the finished state stand a moment.
    } else if (!should && running) {
      running = false;
      window.clearTimeout(timer);
      timer = 0;
      root.classList.remove('is-typing');
    }
  }

  function hold(reason, on) { holds[reason] = on; update(); }

  /** Back to the server-rendered finished state (reduced motion switched on mid-way). */
  function finish() {
    steps = [];
    cycle = 0;
    var who = cfg.names[0];
    setLook(cfg.models[0].look);
    pick('model', 0);
    if (cfg.formats.length) { pick('format', 0); plate.style.setProperty('--ip-pw', cfg.formats[0].pw); plate.style.setProperty('--ip-ratio', cfg.formats[0].ratio); }
    if (cfg.fasteners.length) pick('fastener', 0);
    nameEl.textContent = who.name;
    if (roleEl) { roleEl.textContent = who.role; roleEl.classList.add('is-on'); }
    if (logoEl) logoEl.classList.add('is-on');
    if (cfg.prices.length) { if (qtyEl) qtyEl.textContent = cfg.prices[0].qty; if (priceEl) priceEl.textContent = cfg.prices[0].price; }
    if (cartEl) cartEl.classList.remove('is-done', 'is-press');
  }

  function motionSetting() {
    if (pauseBtn) pauseBtn.hidden = !!(mq && mq.matches);
    if (mq && mq.matches) finish();
    update();
  }

  // Hover and focus pause the demo until the visitor uses the button once;
  // from then on the button alone decides (else "play" would not play while
  // the pointer or focus is still on it).
  var chose = false;
  if (pauseBtn) {
    pauseBtn.addEventListener('click', function () {
      var on = pauseBtn.getAttribute('aria-pressed') !== 'true';
      pauseBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
      chose = true;
      holds.hover = false;
      holds.focus = false;
      hold('user', on);
    });
  }
  root.addEventListener('mouseenter', function () { if (!chose) hold('hover', true); });
  root.addEventListener('mouseleave', function () { hold('hover', false); });
  root.addEventListener('focusin', function () { if (!chose) hold('focus', true); });
  root.addEventListener('focusout', function (e) { if (!root.contains(e.relatedTarget)) hold('focus', false); });
  document.addEventListener('visibilitychange', function () { hold('hidden', document.hidden); });
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      hold('offscreen', !entries[0].isIntersecting);
    }).observe(root);
  }
  if (mq) {
    if (mq.addEventListener) mq.addEventListener('change', motionSetting);
    else if (mq.addListener) mq.addListener(motionSetting);
  }
  holds.hidden = document.hidden;
  motionSetting();
})();
