/* Shared behaviour, vanilla, deferred: dialogs (mobile menu drawer and any
   other [data-ip-open] dialog), drawer submenus, the Namensschilder mega menu
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

    // Drawer submenu disclosure (Namensschilder in the mobile menu).
    var sub = t.closest('.ip-drawer__sub-toggle');
    if (sub) {
      var li = sub.parentElement;
      var isOpen = li.classList.toggle('is-open');
      sub.setAttribute('aria-expanded', String(isOpen));
      return;
    }

    // A link inside a dialog closes it, so in-page anchors and back/forward
    // cache restores never leave the drawer open. Navigation still happens.
    var link = t.closest('dialog a[href]');
    if (link && !e.defaultPrevented) {
      var ld = link.closest('dialog');
      dialogOpener = null; // do not pull focus back to the burger while navigating
      ld.close();
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

    var setMega = function (open) {
      clearTimeout(megaTimer);
      if (open === !megaPanel.hidden) return;
      megaPanel.hidden = !open;
      megaBtn.setAttribute('aria-expanded', String(open));
      mega.classList.toggle('is-open', open);
    };

    megaBtn.addEventListener('click', function () {
      // A click right after hover opened the panel should not shut it again.
      if (!megaPanel.hidden && Date.now() - hoverOpenedAt < 400) return;
      setMega(megaPanel.hidden);
    });

    mega.addEventListener('pointerenter', function (e) {
      if (e.pointerType !== 'mouse' || !finePointer.matches) return;
      clearTimeout(megaTimer);
      megaTimer = setTimeout(function () {
        if (megaPanel.hidden) hoverOpenedAt = Date.now();
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
      if (e.key !== 'Escape' || megaPanel.hidden) return;
      var focusInside = mega.contains(doc.activeElement);
      setMega(false);
      if (focusInside) megaBtn.focus();
    });

    doc.addEventListener('click', function (e) {
      if (!megaPanel.hidden && !mega.contains(e.target)) setMega(false);
    });

    desktop.addEventListener('change', function (e) { if (!e.matches) setMega(false); });
  }

  /* ── Sticky header state (Elementor's .elementor-sticky--effects) ── */
  var header = doc.querySelector('[data-ip-header]');
  if (header) {
    var ticking = false;
    var updateStuck = function () {
      ticking = false;
      header.classList.toggle('is-stuck', window.scrollY > 0);
    };
    window.addEventListener('scroll', function () {
      if (!ticking) { ticking = true; window.requestAnimationFrame(updateStuck); }
    }, { passive: true });
    updateStuck();
  }
})();
