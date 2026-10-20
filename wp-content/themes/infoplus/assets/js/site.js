/* Shared behaviour, vanilla, deferred: dialogs (mobile drawer and any other
   [data-ip-open] dialog), drawer submenus, the Namensschilder mega menu
   disclosure and the sticky header state. One IIFE: helper names must stay
   unique inside it. jQuery is on the page for WooCommerce; not used here. */
(function () {
  'use strict';
  var doc = document;
  var desktop = window.matchMedia('(min-width: 1101px)');

  /* ── Dialogs: native <dialog> + showModal (focus trap, Escape, inert page) ── */
  var dialogOpener = null;

  function openDialog(id, trigger) {
    var d = doc.getElementById(id);
    if (!d || d.open || typeof d.showModal !== 'function') return;
    dialogOpener = trigger || doc.activeElement;
    d.showModal();
    doc.body.classList.add('ip-locked');
  }

  doc.querySelectorAll('dialog').forEach(function (d) {
    d.addEventListener('close', function () {
      doc.body.classList.remove('ip-locked');
      if (dialogOpener && dialogOpener.focus) dialogOpener.focus();
      dialogOpener = null;
    });
  });

  doc.addEventListener('click', function (e) {
    var t = e.target;
    var open = t.closest('[data-ip-open]');
    if (open) { e.preventDefault(); openDialog(open.getAttribute('data-ip-open'), open); return; }

    var close = t.closest('[data-ip-close]');
    if (close) { var cd = close.closest('dialog'); if (cd) cd.close(); return; }

    // Drawer submenu disclosure.
    var sub = t.closest('.ip-drawer__sub-toggle');
    if (sub) {
      var item = sub.closest('.ip-drawer__item');
      var isOpen = item.classList.toggle('is-open');
      sub.setAttribute('aria-expanded', String(isOpen));
      return;
    }

    // A link inside a dialog closes it, so in-page anchors and back/forward
    // cache restores never leave the drawer open. Navigation still happens.
    var link = t.closest('dialog a[href]');
    if (link && !e.defaultPrevented) {
      dialogOpener = null; // do not pull focus back to the burger while navigating
      link.closest('dialog').close();
      return;
    }

    // A click on the backdrop (outside the dialog box) closes it.
    if (t.tagName === 'DIALOG' && t.open) {
      var r = t.getBoundingClientRect();
      if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) t.close();
    }
  });

  // The drawer only exists below 1101 px; close it if the viewport grows past that.
  desktop.addEventListener('change', function (e) {
    var drawer = doc.getElementById('ip-mobile-menu');
    if (e.matches && drawer && drawer.open) drawer.close();
  });

  /* ── Mega menu: disclosure button, hover intent, Escape, click outside ── */
  var mega = doc.querySelector('[data-ip-mega]');
  var megaBtn = mega && mega.querySelector('[aria-controls]');
  var megaPanel = megaBtn && doc.getElementById(megaBtn.getAttribute('aria-controls'));

  if (megaPanel) {
    var megaTimer = 0;
    var hoverOpenedAt = 0;
    var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
    var isMegaOpen = function () { return mega.classList.contains('is-open'); };

    // Align the panel with its item (clamped into the bar) and aim the caret at it.
    var placeMega = function () {
      var bar = megaPanel.offsetParent;
      if (!bar) return;
      var barBox = bar.getBoundingClientRect();
      var itemBox = mega.getBoundingClientRect();
      var pad = parseFloat(getComputedStyle(bar).paddingLeft) || 0;
      var left = itemBox.left - barBox.left - 24;
      left = Math.max(pad, Math.min(left, barBox.width - pad - megaPanel.offsetWidth));
      megaPanel.style.setProperty('--ip-mega-left', left + 'px');
      var caret = itemBox.left + Math.min(itemBox.width, 140) / 2 - barBox.left - left - 7;
      megaPanel.style.setProperty('--ip-caret-x', Math.max(20, caret) + 'px');
    };

    var setMega = function (open) {
      clearTimeout(megaTimer);
      if (open === isMegaOpen()) return;
      if (open) placeMega();
      mega.classList.toggle('is-open', open);
      megaBtn.setAttribute('aria-expanded', String(open));
    };

    megaBtn.addEventListener('click', function () {
      // A click right after hover opened the panel should not shut it again.
      if (isMegaOpen() && Date.now() - hoverOpenedAt < 400) return;
      setMega(!isMegaOpen());
    });

    // Hover intent on the item (the panel is inside it, so moving into the panel keeps it open).
    mega.addEventListener('pointerenter', function (e) {
      if (e.pointerType !== 'mouse' || !finePointer.matches) return;
      clearTimeout(megaTimer);
      megaTimer = setTimeout(function () {
        if (!isMegaOpen()) hoverOpenedAt = Date.now();
        setMega(true);
      }, 80);
    });
    mega.addEventListener('pointerleave', function (e) {
      if (e.pointerType !== 'mouse') return;
      clearTimeout(megaTimer);
      megaTimer = setTimeout(function () { setMega(false); }, 250);
    });

    // Keyboard: leaving the item and its panel closes it.
    mega.addEventListener('focusout', function (e) {
      if (e.relatedTarget && !mega.contains(e.relatedTarget)) setMega(false);
    });

    doc.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || !isMegaOpen()) return;
      var focusInside = mega.contains(doc.activeElement);
      setMega(false);
      if (focusInside) megaBtn.focus();
    });

    doc.addEventListener('click', function (e) {
      if (isMegaOpen() && !mega.contains(e.target)) setMega(false);
    });

    window.addEventListener('resize', function () { if (isMegaOpen()) placeMega(); }, { passive: true });
    desktop.addEventListener('change', function (e) { if (!e.matches) setMega(false); });
  }

  /* ── Sticky header state: .is-stuck once the page has scrolled ── */
  var header = doc.querySelector('[data-ip-header]');
  if (header) {
    var ticking = false;
    var updateStuck = function () {
      ticking = false;
      header.classList.toggle('is-stuck', window.scrollY > 4);
    };
    window.addEventListener('scroll', function () {
      if (!ticking) { ticking = true; window.requestAnimationFrame(updateStuck); }
    }, { passive: true });
    updateStuck();
  }

  /* ── Swipe rows (.ip-swipe[data-ip-swipe]): dots under the row on phones ── */
  doc.querySelectorAll('[data-ip-swipe]').forEach(function (row) {
    var cards = row.children;
    if (cards.length < 2) return;
    var dots = doc.createElement('div');
    dots.className = 'ip-swipe-dots';
    var buttons = [];
    Array.prototype.forEach.call(cards, function (card, i) {
      var b = doc.createElement('button');
      b.type = 'button';
      b.setAttribute('aria-label', (i + 1) + ' / ' + cards.length);
      b.addEventListener('click', function () {
        row.scrollTo({ left: card.offsetLeft - row.offsetLeft - parseFloat(getComputedStyle(row).scrollPaddingLeft || 0), behavior: 'smooth' });
      });
      dots.appendChild(b);
      buttons.push(b);
    });
    row.after(dots);
    var swipeFrame = 0;
    var markDot = function () {
      swipeFrame = 0;
      var max = row.scrollWidth - row.clientWidth;
      var idx = max > 0 ? Math.round((row.scrollLeft / max) * (cards.length - 1)) : 0;
      buttons.forEach(function (b, i) { b.setAttribute('aria-current', i === idx ? 'true' : 'false'); });
    };
    row.addEventListener('scroll', function () {
      if (!swipeFrame) swipeFrame = window.requestAnimationFrame(markDot);
    }, { passive: true });
    markDot();
  });
})();
