/* Archive bundle (shop, product categories, the Namensschilder page).

   1. On phones the category chips are one swipe row; the active chip is
      brought into view (e.g. "X-Style" at the far end). Scrolls only the
      row, never the page.

   2. Category pills filter the grid in place (inc/catalog.php). The pills
      are real links to the category archives; this enhances them: a plain
      click shows the matching cards, swaps title, intro, breadcrumb and
      count, and writes the pill's own URL with history.pushState, so the
      address is shareable and a reload renders the same state on the server.
      Back/forward restore the state. The header item "Namensschilder"
      ([data-ip-nav-plates]) keeps aria-current="page" in every state.
      Ctrl/Cmd/Shift/middle click keep the
      normal link behaviour (new tab, new window). Motion: remaining cards
      glide to their new place (FLIP), new cards fade in and rise 8 px;
      nothing moves under prefers-reduced-motion. */
(function () {
  'use strict';

  var row = document.querySelector('.ip-chips__list');

  function centerChip(chip) {
    if (!row || !chip || row.scrollWidth <= row.clientWidth) { return; }
    var item = chip.parentElement;
    var left = item.getBoundingClientRect().left - row.getBoundingClientRect().left + row.scrollLeft;
    var target = Math.max(0, left - (row.clientWidth - item.offsetWidth) / 2);
    if (row.scrollTo) { row.scrollTo({ left: target, behavior: reduced() ? 'auto' : 'smooth' }); } else { row.scrollLeft = target; }
  }

  function reduced() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  // 1. Initial active chip (no smooth scroll on load).
  (function () {
    var active = row && row.querySelector('[aria-current="page"]');
    if (!row || !active || row.scrollWidth <= row.clientWidth) { return; }
    var item = active.parentElement;
    var left = item.getBoundingClientRect().left - row.getBoundingClientRect().left + row.scrollLeft;
    row.scrollLeft = Math.max(0, left - (row.clientWidth - item.offsetWidth) / 2);
  })();

  // 2. In-place filter.
  var nav = document.querySelector('[data-ip-filter]');
  var dataEl = document.getElementById('ip-catalog-data');
  var grid = document.querySelector('.ip-shop .ip-shop-grid');
  if (!nav || !dataEl || !grid || !window.history || !history.pushState) { return; }

  var data;
  try { data = JSON.parse(dataEl.textContent); } catch (e) { return; }
  if (!data || !data.states) { return; }

  var pills = Array.prototype.slice.call(nav.querySelectorAll('[data-ip-term]'));
  var items = Array.prototype.slice.call(grid.children).filter(function (el) { return el.hasAttribute('data-ip-cats'); });
  var head = document.querySelector('[data-ip-filter-head]');
  var titleEl = document.querySelector('[data-ip-filter-title]');
  var introEl = document.querySelector('[data-ip-filter-intro]');
  var countEl = document.querySelector('[data-ip-filter-count]');
  var crumbs = document.querySelector('.ip-shop .ip-crumbs ol');
  var current = typeof data.active === 'string' ? data.active : (nav.getAttribute('data-ip-filter') || '');
  var ease = 'cubic-bezier(.22, 1, .36, 1)';

  function state(slug) { return data.states[slug || '_all'] || null; }

  function path(url) {
    var a = document.createElement('a');
    a.href = url;
    return a.pathname.replace(/\/+$/, '') || '/';
  }

  /** Slug of the state whose URL matches the current address, or null. */
  function slugFromLocation() {
    var here = path(location.href);
    var found = null;
    Object.keys(data.states).forEach(function (key) {
      if (found === null && path(data.states[key].url) === here) { found = key === '_all' ? '' : key; }
    });
    return found;
  }

  function matches(li, slug) {
    return !slug || (' ' + li.getAttribute('data-ip-cats') + ' ').indexOf(' ' + slug + ' ') !== -1;
  }

  function el(tag, text) {
    var node = document.createElement(tag);
    if (text !== undefined) { node.textContent = text; }
    return node;
  }

  function renderCrumbs(s) {
    if (!crumbs || !data.home || !data.plates) { return; }
    var list = [data.home, s.crumb ? data.plates : [data.plates[0], '']];
    if (s.crumb) { list.push([s.crumb, '']); }
    crumbs.textContent = '';
    list.forEach(function (c, i) {
      var li = el('li');
      if (i === list.length - 1) {
        var span = el('span', c[0]);
        span.setAttribute('aria-current', 'page');
        li.appendChild(span);
      } else {
        var a = el('a', c[0]);
        a.href = c[1];
        li.appendChild(a);
      }
      crumbs.appendChild(li);
    });
  }

  function renderHead(s, animate) {
    if (titleEl) {
      titleEl.textContent = s.title;
      if (s.accent) {
        titleEl.appendChild(document.createTextNode(' '));
        titleEl.appendChild(el('em', s.accent));
      }
    }
    if (introEl) {
      // Server-built HTML (esc_html / wp_kses_post in inc/catalog.php), not user input.
      introEl.innerHTML = s.intro || '';
      introEl.hidden = !s.intro;
    }
    renderCrumbs(s);
    if (s.doc) { document.title = s.doc; }
    if (animate && head && head.animate) {
      head.animate([{ opacity: 0.25, transform: 'translateY(4px)' }, { opacity: 1, transform: 'none' }], { duration: 220, easing: ease });
    }
  }

  function renderCount(n) {
    if (!countEl) { return; }
    var pill = countEl.querySelector('.ip-pill') || countEl;
    pill.textContent = (n === 1 ? data.one : data.other).replace('%d', String(n));
  }

  function renderPills(slug) {
    pills.forEach(function (p) {
      if (p.getAttribute('data-ip-term') === slug) {
        p.setAttribute('aria-current', 'page');
      } else {
        p.removeAttribute('aria-current');
      }
    });
    nav.setAttribute('data-ip-filter', slug);
  }

  function renderGrid(slug, animate) {
    var first = [];
    if (animate) {
      items.forEach(function (li) { first.push(li.hidden ? null : li.getBoundingClientRect()); });
    }
    var shown = 0;
    items.forEach(function (li) {
      var on = matches(li, slug);
      li.hidden = !on;
      if (on) { shown++; }
    });
    if (!animate || !grid.animate) { return shown; }
    var entering = 0;
    items.forEach(function (li, i) {
      if (li.hidden) { return; }
      if (li.getAnimations) { li.getAnimations().forEach(function (a) { a.cancel(); }); }
      var last = li.getBoundingClientRect();
      var was = first[i];
      if (was) {
        var dx = was.left - last.left;
        var dy = was.top - last.top;
        if (dx || dy) {
          li.animate([{ transform: 'translate(' + dx + 'px,' + dy + 'px)' }, { transform: 'none' }], { duration: 320, easing: ease });
        }
      } else {
        li.animate([{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'none' }], {
          duration: 280, delay: Math.min(entering, 8) * 40, easing: ease, fill: 'backwards'
        });
        entering++;
      }
    });
    return shown;
  }

  function apply(slug, animate) {
    var s = state(slug);
    if (!s) { return false; }
    animate = animate && !reduced();
    renderPills(slug);
    renderHead(s, animate);
    renderCount(renderGrid(slug, animate));
    centerChip(nav.querySelector('[aria-current="page"]'));
    markHeader();
    current = slug;
    return true;
  }

  // Every filter state is a Namensschilder URL: the header item stays current
  // (the server marks it on these views, ip_header_in_catalog()).
  function markHeader() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-ip-nav-plates]'), function (a) {
      a.setAttribute('aria-current', 'page');
    });
  }
  markHeader();

  // Remember the server-rendered state, so "back" to it restores it.
  try { history.replaceState(Object.assign({}, history.state, { ipCat: current }), ''); } catch (e) { /* state not writable: popstate falls back to the URL */ }

  nav.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('[data-ip-term]') : null;
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
    var slug = a.getAttribute('data-ip-term') || '';
    if (!state(slug)) { return; } // Unknown pill: let the link load the page.
    e.preventDefault();
    if (slug === current) { return; }
    apply(slug, true);
    history.pushState({ ipCat: slug }, '', state(slug).url);
  });

  window.addEventListener('popstate', function (e) {
    var slug = e.state && typeof e.state.ipCat === 'string' ? e.state.ipCat : slugFromLocation();
    if (slug === null || !state(slug)) { location.reload(); return; }
    if (slug !== current) { apply(slug, true); }
  });
})();
