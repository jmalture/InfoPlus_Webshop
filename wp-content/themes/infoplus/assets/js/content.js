/* Content bundle (Unternehmen, Kontakt, FAQ, info pages).

   Kontakt: the animated example conversation ([data-ip-cdemo],
   template-parts/contact-demo.php). One cycle per example: the thread
   clears, the question appears, the team types, the answer appears, then
   "Ihr Angebot, per E-Mail gesendet" with a tick, and it rests.

   Starts from the finished first example the server rendered. Pauses while
   the pointer is over it, while focus is inside it, off screen and in a
   hidden tab; the pause button stops it until pressed again. Under
   prefers-reduced-motion nothing runs and the finished first example stays
   (also when the setting changes while the page is open). Same control
   logic as the front page demo (home.js). */
(function () {
  'use strict';
  var root = document.querySelector('[data-ip-cdemo]');
  if (!root) return;
  var chats;
  try { chats = JSON.parse(root.getAttribute('data-ip-cdemo')); } catch (e) { return; }
  if (!chats || !chats.length) return;

  var mq = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
  var thread = root.querySelector('[data-ip-cdemo-thread]');
  var qEl = root.querySelector('[data-ip-cdemo-q]');
  var aEl = root.querySelector('[data-ip-cdemo-a]');
  var offer = root.querySelector('[data-ip-cdemo-offer]');
  var pauseBtn = root.querySelector('[data-ip-cdemo-pause]');
  if (!thread || !qEl || !aEl) return;

  var cycle = 0;
  var steps = [];
  var timer = 0;
  var holds = {};
  var running = false;

  /** Reading time for a message: about 55 ms per character, 1.6 to 4 s. */
  function readTime(text) { return Math.max(1600, Math.min(4000, text.length * 55)); }

  function build(n) {
    var c = chats[n % chats.length];
    return [
      function () { thread.classList.add('is-clearing'); return 300; },
      function () {
        thread.classList.remove('is-clearing');
        [qEl, aEl, offer].forEach(function (el) { if (el) el.classList.remove('is-on'); });
        qEl.textContent = c.q;
        aEl.textContent = c.a;
        return 350;
      },
      function () { qEl.classList.add('is-on'); return 900 + readTime(c.q) / 2; },
      function () { root.classList.add('is-typing'); return 1500; },
      function () { root.classList.remove('is-typing'); aEl.classList.add('is-on'); return readTime(c.a); },
      function () { if (offer) offer.classList.add('is-on'); return 2600; }
    ];
  }

  function next() {
    timer = 0;
    if (!steps.length) {
      cycle++;
      steps = build(cycle);
    }
    timer = window.setTimeout(next, steps.shift()());
  }

  function paused() {
    return Object.keys(holds).some(function (k) { return holds[k]; });
  }

  function update() {
    var should = !paused() && !(mq && mq.matches);
    if (should && !running) {
      running = true;
      timer = window.setTimeout(next, steps.length ? 400 : 2400); // First run: let the finished example stand a moment.
    } else if (!should && running) {
      running = false;
      window.clearTimeout(timer);
      timer = 0;
      root.classList.remove('is-typing');
    }
  }

  function hold(reason, on) { holds[reason] = on; update(); }

  /** Back to the server-rendered first example (reduced motion switched on mid-way). */
  function finish() {
    steps = [];
    cycle = 0;
    root.classList.remove('is-typing');
    thread.classList.remove('is-clearing');
    qEl.textContent = chats[0].q;
    aEl.textContent = chats[0].a;
    [qEl, aEl, offer].forEach(function (el) { if (el) el.classList.add('is-on'); });
  }

  function motionSetting() {
    if (pauseBtn) pauseBtn.hidden = !!(mq && mq.matches);
    if (mq && mq.matches) finish();
    update();
  }

  // Hover and focus pause it until the visitor uses the button once; from
  // then on the button alone decides.
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

/* Unternehmen (page-unternehmen.php): rise in once ([data-ip-rv]), count
   the numbers up ([data-ip-count]) and the milestones timeline
   ([data-ip-miles]: WAI-ARIA tabs; hover with a mouse previews a year,
   click, tap or the arrow keys choose one; the line draws itself once when
   it scrolls into view). Without IntersectionObserver or under
   prefers-reduced-motion everything is shown finished. */
(function () {
  'use strict';
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var canWatch = 'IntersectionObserver' in window && !reduce;

  /* Rise in. */
  var rv = document.querySelectorAll('[data-ip-rv]');
  if (rv.length) {
    if (!canWatch) {
      Array.prototype.forEach.call(rv, function (el) { el.classList.add('is-in'); });
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (!e.isIntersecting) return;
          e.target.classList.add('is-in');
          io.unobserve(e.target);
        });
      }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
      Array.prototype.forEach.call(rv, function (el) { io.observe(el); });
    }
  }

  /* Count up: the number inside the text ("über 2.000") runs from 0, the
     words around it stay. German thousands dots are kept. */
  var counts = document.querySelectorAll('[data-ip-count]');
  if (counts.length && canWatch) {
    var fmt = function (n, dots) { var s = String(Math.round(n)); return dots ? s.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : s; };
    var run = function (el) {
      var text = el.getAttribute('data-ip-count-text');
      var m = text.match(/(\d[\d.]*)/);
      if (!m) return;
      var target = Number(m[1].replace(/\./g, ''));
      var dots = m[1].indexOf('.') !== -1;
      var before = text.slice(0, m.index), after = text.slice(m.index + m[1].length);
      var t0 = 0, dur = 1400;
      var step = function (t) {
        if (!t0) t0 = t;
        var p = Math.min(1, (t - t0) / dur);
        var eased = 1 - Math.pow(1 - p, 3);
        el.textContent = before + fmt(target * eased, dots) + after;
        if (p < 1) window.requestAnimationFrame(step);
        else el.textContent = text;
      };
      window.requestAnimationFrame(step);
    };
    var cio = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        cio.unobserve(e.target);
        run(e.target);
      });
    }, { threshold: 0.6 });
    Array.prototype.forEach.call(counts, function (el) {
      el.setAttribute('data-ip-count-text', el.textContent.trim());
      var r = el.getBoundingClientRect();
      if (r.top < window.innerHeight && r.bottom > 0) return; // Already on screen: leave the final number.
      cio.observe(el);
    });
  }

  /* Milestones timeline. */
  var box = document.querySelector('[data-ip-miles]');
  if (!box) return;
  var tabs = Array.prototype.slice.call(box.querySelectorAll('[role="tab"]'));
  if (!tabs.length) return;
  var fine = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  var hoverTimer = 0;

  function select(tab, focus) {
    if (tab.getAttribute('aria-selected') === 'true') { if (focus) tab.focus(); return; }
    tabs.forEach(function (t) {
      var on = t === tab;
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.tabIndex = on ? 0 : -1;
      t.classList.toggle('is-on', on);
      var panel = document.getElementById(t.getAttribute('aria-controls'));
      if (!panel) return;
      panel.hidden = !on;
      panel.classList.remove('is-in');
      if (on && !reduce) { void panel.offsetWidth; panel.classList.add('is-in'); }
    });
    if (focus) tab.focus();
  }

  tabs.forEach(function (tab, i) {
    tab.addEventListener('click', function () { select(tab, false); });
    if (fine) {
      tab.addEventListener('mouseenter', function () {
        window.clearTimeout(hoverTimer);
        hoverTimer = window.setTimeout(function () { select(tab, false); }, 90);
      });
      tab.addEventListener('mouseleave', function () { window.clearTimeout(hoverTimer); });
    }
    tab.addEventListener('keydown', function (e) {
      var next = null;
      if (e.key === 'ArrowRight' || e.key === 'ArrowDown') next = tabs[(i + 1) % tabs.length];
      else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') next = tabs[(i - 1 + tabs.length) % tabs.length];
      else if (e.key === 'Home') next = tabs[0];
      else if (e.key === 'End') next = tabs[tabs.length - 1];
      if (next) { e.preventDefault(); select(next, true); }
    });
  });

  if (canWatch) {
    var r = box.getBoundingClientRect();
    if (!(r.top < window.innerHeight && r.bottom > 0)) {
      box.classList.add('is-armed');
      var mio = new IntersectionObserver(function (entries) {
        if (!entries[0].isIntersecting) return;
        mio.disconnect();
        box.classList.add('is-drawn');
        window.setTimeout(function () { box.classList.remove('is-armed'); }, 2600);
      }, { threshold: 0.35 });
      mio.observe(box);
    }
  }
})();
