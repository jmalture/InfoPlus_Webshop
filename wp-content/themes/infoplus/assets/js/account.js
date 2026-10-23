/* Account bundle (login page and My Account). Vanilla, deferred.
   - Orders list: quick search over the listed orders (number, reference,
     date, status, total), with a spoken result count.
   - Order view: "Drucken" prints the page (order.css has the print styles).
   - Account details: profile picture upload through the Infoplus Suite REST
     route infoplus/v1/user/avatar (window.ipAccount from inc/account.php).
   The controls are marked .ip-js-only, so without JS they are not shown. */
(function () {
  'use strict';

  var cfg = window.ipAccount || {};
  var t = cfg.i18n || {};

  /* ── Orders search ─────────────────────────────────────────────────── */
  var search = document.querySelector('[data-ip-orders-search]');
  if (search) {
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-ip-order-row]'));
    var none = document.querySelector('[data-ip-orders-none]');
    var live = document.querySelector('[data-ip-orders-result]');
    var index = rows.map(function (row) { return (row.textContent || '').replace(/\s+/g, ' ').toLowerCase(); });
    var timer = 0;

    var apply = function () {
      var q = search.value.trim().toLowerCase();
      var shown = 0;
      rows.forEach(function (row, i) {
        var hit = !q || index[i].indexOf(q) !== -1;
        row.hidden = !hit;
        if (hit) { shown++; }
      });
      if (none) { none.hidden = shown !== 0; }
      if (live) {
        live.textContent = q ? (t.count || '%d').replace('%d', String(shown)) : '';
      }
    };
    search.addEventListener('input', function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(apply, 120);
    });
    search.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && search.value) { search.value = ''; apply(); }
    });
  }

  /* ── Menu: keep the current item in view in the phone swipe row ─────── */
  var current = document.querySelector('.ip-acc-nav__link[aria-current="page"]');
  var row = current && current.closest('.ip-acc-nav__list');
  if (row && row.scrollWidth > row.clientWidth) {
    row.scrollLeft += current.getBoundingClientRect().left - row.getBoundingClientRect().left - 16;
  }

  /* ── Print ─────────────────────────────────────────────────────────── */
  document.querySelectorAll('[data-ip-print]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.print(); });
  });

  /* ── Profile picture ───────────────────────────────────────────────── */
  var input = document.querySelector('[data-ip-avatar-input]');
  var status = document.querySelector('[data-ip-avatar-status]');
  if (input && cfg.avatarUrl && window.fetch && window.FormData) {
    var say = function (msg, isError) {
      if (!status) { return; }
      status.textContent = msg;
      status.classList.toggle('ip-muted', !isError);
      status.style.color = isError ? 'var(--c-secondary)' : '';
    };
    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file) { return; }
      if (['image/jpeg', 'image/png', 'image/gif'].indexOf(file.type) === -1) { say(t.type, true); input.value = ''; return; }
      if (file.size > 5 * 1024 * 1024) { say(t.size, true); input.value = ''; return; }

      var data = new FormData();
      data.append('avatar', file);
      input.disabled = true;
      say(t.uploading, false);

      fetch(cfg.avatarUrl, {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': cfg.nonce || '' }
      })
        .then(function (res) {
          return res.json().catch(function () { return {}; }).then(function (json) { return { ok: res.ok, json: json }; });
        })
        .then(function (r) {
          if (!r.ok || !r.json || !r.json.success || !r.json.avatar_url) {
            throw new Error((r.json && r.json.message) || t.failed);
          }
          // Show the new picture in every avatar on the page (aside and this block).
          document.querySelectorAll('[data-ip-avatar]').forEach(function (box) {
            var img = box.querySelector('img');
            if (!img) {
              img = document.createElement('img');
              img.className = 'ip-avatar__img';
              img.alt = '';
              box.textContent = '';
              box.classList.remove('ip-avatar--initials');
              box.appendChild(img);
            }
            img.removeAttribute('srcset');
            img.src = r.json.avatar_url;
          });
          say(t.done, false);
        })
        .catch(function (err) { say(err && err.message ? err.message : t.failed, true); })
        .then(function () { input.disabled = false; input.value = ''; });
    });
  }
})();
