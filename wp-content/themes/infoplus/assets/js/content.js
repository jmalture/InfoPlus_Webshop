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
