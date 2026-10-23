/* Shared behaviour, vanilla, deferred: dialogs (mobile drawer and any other
   [data-ip-open] dialog), drawer submenus, the Namensschilder mega menu
   disclosure, the account quick menu and the sticky header state. One IIFE: helper names must stay
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
      if (open) {
        // One floating panel at a time: close the account menu.
        var acctItem = doc.querySelector('[data-ip-account].is-open');
        if (acctItem) { acctItem.classList.remove('is-open'); var ab = acctItem.querySelector('[aria-expanded]'); if (ab) ab.setAttribute('aria-expanded', 'false'); }
        var cartItem = doc.querySelector('[data-ip-cartbox].is-open');
        if (cartItem) cartItem.classList.remove('is-open');
      }
      mega.classList.toggle('is-open', open);
      megaBtn.setAttribute('aria-expanded', String(open));
    };

    megaBtn.addEventListener('click', function () {
      // A click right after hover opened the panel should not shut it again.
      if (isMegaOpen() && Date.now() - hoverOpenedAt < 400) return;
      setMega(!isMegaOpen());
    });

    // Hover intent, desktop with a mouse only (1101 px and up, hover: hover, pointer: fine);
    // touch and pen keep click only. The panel is inside the item, so the pointer can
    // travel from the link or caret into the panel: it opens after HOVER_OPEN ms on the
    // item and closes HOVER_CLOSE ms after the pointer has left both. Crossing the nav
    // quickly opens nothing.
    var HOVER_OPEN = 120;
    var HOVER_CLOSE = 250;
    var canHover = function (e) { return e.pointerType === 'mouse' && finePointer.matches && desktop.matches; };
    mega.addEventListener('pointerenter', function (e) {
      if (!canHover(e)) return;
      clearTimeout(megaTimer);
      if (isMegaOpen()) return; // back inside before the close delay ran out
      megaTimer = setTimeout(function () {
        hoverOpenedAt = Date.now();
        setMega(true);
      }, HOVER_OPEN);
    });
    mega.addEventListener('pointerleave', function (e) {
      if (!canHover(e)) return;
      clearTimeout(megaTimer);
      if (!isMegaOpen()) return;
      // Focus on a card inside the panel (keyboard) keeps it open until focus leaves (focusout below).
      megaTimer = setTimeout(function () {
        if (!megaPanel.contains(doc.activeElement)) setMega(false);
      }, HOVER_CLOSE);
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

  /* ── Account quick menu (inc/account-menu.php): disclosure button; click or tap on any
     device, mouse hover on desktop with the mega menu's intent delay. Escape closes and
     returns focus to the button; a click outside or focus leaving closes it. ── */
  var acct = doc.querySelector('[data-ip-account]');
  var acctBtn = acct && acct.querySelector('button[aria-controls]');
  var acctPanel = acctBtn && doc.getElementById(acctBtn.getAttribute('aria-controls'));

  if (acctPanel) {
    var acctTimer = 0;
    var acctHoverAt = 0;
    var acctFine = window.matchMedia('(hover: hover) and (pointer: fine)');
    var acctOpen = function () { return acct.classList.contains('is-open'); };
    var setAcct = function (open) {
      clearTimeout(acctTimer);
      if (open === acctOpen()) return;
      if (open) {
        // One floating panel at a time: close the mega menu.
        var megaItem = doc.querySelector('[data-ip-mega].is-open');
        if (megaItem) { megaItem.classList.remove('is-open'); var mb = megaItem.querySelector('[aria-expanded]'); if (mb) mb.setAttribute('aria-expanded', 'false'); }
        var cartOpen = doc.querySelector('[data-ip-cartbox].is-open');
        if (cartOpen) cartOpen.classList.remove('is-open');
      }
      acct.classList.toggle('is-open', open);
      acctBtn.setAttribute('aria-expanded', String(open));
    };
    var acctHover = function (e) { return e.pointerType === 'mouse' && acctFine.matches && desktop.matches; };

    acctBtn.addEventListener('click', function () {
      if (acctOpen() && Date.now() - acctHoverAt < 400) return; // hover just opened it
      setAcct(!acctOpen());
    });
    acct.addEventListener('pointerenter', function (e) {
      if (!acctHover(e)) return;
      clearTimeout(acctTimer);
      if (acctOpen()) return;
      acctTimer = setTimeout(function () { acctHoverAt = Date.now(); setAcct(true); }, 120);
    });
    acct.addEventListener('pointerleave', function (e) {
      if (!acctHover(e)) return;
      clearTimeout(acctTimer);
      if (!acctOpen()) return;
      acctTimer = setTimeout(function () {
        if (!acctPanel.contains(doc.activeElement)) setAcct(false);
      }, 250);
    });
    acct.addEventListener('focusout', function (e) {
      if (e.relatedTarget && !acct.contains(e.relatedTarget)) setAcct(false);
    });
    doc.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || !acctOpen()) return;
      var inside = acct.contains(doc.activeElement);
      setAcct(false);
      if (inside) acctBtn.focus();
    });
    doc.addEventListener('click', function (e) {
      if (acctOpen() && !acct.contains(e.target)) setAcct(false);
    });
    desktop.addEventListener('change', function (e) { if (!e.matches) setAcct(false); });
  }

  /* ── Cart preview (ip_minicart_html()): the icon stays a link to the Warenkorb. Mouse
     hover on desktop opens the panel with the account menu's intent delay; keyboard focus
     shows it through CSS (:focus-visible). The first opening asks WooCommerce for fresh
     cart fragments, so a page from the page cache never shows an old cart. ── */
  var cartbox = doc.querySelector('[data-ip-cartbox]');
  if (cartbox) {
    var cartTimer = 0;
    var cartFresh = false;
    var cartFine = window.matchMedia('(hover: hover) and (pointer: fine)');
    var setCart = function (open) {
      clearTimeout(cartTimer);
      if (open === cartbox.classList.contains('is-open')) return;
      if (open) {
        // One floating panel at a time.
        doc.querySelectorAll('[data-ip-account].is-open, [data-ip-mega].is-open').forEach(function (el) {
          el.classList.remove('is-open');
          var b = el.querySelector('[aria-expanded]');
          if (b) b.setAttribute('aria-expanded', 'false');
        });
        refreshCart();
      }
      cartbox.classList.toggle('is-open', open);
    };
    var refreshCart = function () {
      var url = cartbox.getAttribute('data-ip-fragments');
      if (cartFresh || !url || !window.fetch) return;
      cartFresh = true;
      var box = cartbox.querySelector('[data-ip-minicart]');
      if (box) box.classList.add('is-loading');
      fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          var frags = data && data.fragments;
          if (!frags) return;
          Object.keys(frags).forEach(function (sel) {
            if (sel !== '[data-ip-minicart]' && sel !== '[data-ip-cart-count]') return;
            doc.querySelectorAll(sel).forEach(function (el) {
              var tmp = doc.createElement('div');
              tmp.innerHTML = frags[sel];
              if (tmp.firstElementChild) el.replaceWith(tmp.firstElementChild);
            });
          });
        })
        .catch(function () { cartFresh = false; })
        .then(function () {
          var b = cartbox.querySelector('[data-ip-minicart]');
          if (b) b.classList.remove('is-loading');
        });
    };
    var cartHover = function (e) { return e.pointerType === 'mouse' && cartFine.matches && desktop.matches; };
    cartbox.addEventListener('pointerenter', function (e) {
      if (!cartHover(e)) return;
      clearTimeout(cartTimer);
      cartTimer = setTimeout(function () { setCart(true); }, 120);
    });
    cartbox.addEventListener('pointerleave', function (e) {
      if (!cartHover(e)) return;
      clearTimeout(cartTimer);
      cartTimer = setTimeout(function () { setCart(false); }, 250);
    });
    cartbox.addEventListener('focusin', function () { if (desktop.matches) refreshCart(); });
    doc.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && cartbox.classList.contains('is-open')) setCart(false);
    });
    desktop.addEventListener('change', function (e) { if (!e.matches) setCart(false); });
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
