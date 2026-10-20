/* Archive bundle: on phones the category chips are one swipe row; bring the
   active chip into view on a category page (e.g. "X-Style" at the far end).
   Scrolls only the row, never the page. */
(function () {
  'use strict';
  var row = document.querySelector('.ip-chips__list');
  var active = row && row.querySelector('[aria-current="page"]');
  if (!row || !active || row.scrollWidth <= row.clientWidth) { return; }
  var item = active.parentElement;
  var left = item.getBoundingClientRect().left - row.getBoundingClientRect().left + row.scrollLeft;
  row.scrollLeft = Math.max(0, left - (row.clientWidth - item.offsetWidth) / 2);
})();
