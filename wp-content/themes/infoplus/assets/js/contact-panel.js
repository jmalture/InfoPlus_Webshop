/*
 * Floating Kontakt panel (template-parts/contact-panel.php).
 * Old behaviour (Elementor snippet 14273): the tab toggles the panel, a click
 * outside closes it. Added: Escape and the close button close it and return
 * focus to the tab; focus leaving the panel closes it; aria-expanded follows
 * the state. On phones the panel is a bottom sheet covering part of the page,
 * so opening moves focus into it; on desktop focus stays on the tab
 * (disclosure pattern, the panel follows it in tab order).
 */
(function () {
	'use strict';

	var root = document.querySelector('[data-ip-contact]');
	if (!root) {
		return;
	}
	var tab = root.querySelector('[data-ip-contact-toggle]');
	var panel = tab && document.getElementById(tab.getAttribute('aria-controls'));
	if (!tab || !panel) {
		return;
	}
	var sheet = window.matchMedia('(max-width: 767px)');

	function isOpen() {
		return root.classList.contains('is-open');
	}

	/*
	 * Desktop transitions are enabled only around a toggle (.is-anim), so the
	 * closed tab never animates on load, on resize or in full-page screenshots.
	 */
	var animTimer = 0;
	function setOpen(open, focusTab) {
		if (root.classList.contains('is-open') !== open) {
			root.classList.add('is-anim');
			clearTimeout(animTimer);
			animTimer = setTimeout(function () {
				root.classList.remove('is-anim');
			}, 400);
		}
		root.classList.toggle('is-open', open);
		tab.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (open && sheet.matches) {
			panel.focus({ preventScroll: true });
		}
		if (!open && focusTab) {
			tab.focus();
		}
	}

	tab.addEventListener('click', function () {
		setOpen(!isOpen(), false);
	});

	root.querySelectorAll('[data-ip-contact-close]').forEach(function (el) {
		el.addEventListener('click', function () {
			setOpen(false, true);
		});
	});

	document.addEventListener('click', function (event) {
		if (isOpen() && !root.contains(event.target)) {
			setOpen(false, false);
		}
	});

	document.addEventListener('keydown', function (event) {
		if ((event.key === 'Escape' || event.key === 'Esc') && isOpen()) {
			setOpen(false, root.contains(document.activeElement));
		}
	});

	// Keyboard users tabbing past the panel: close it behind them.
	root.addEventListener('focusout', function (event) {
		if (isOpen() && event.relatedTarget && !root.contains(event.relatedTarget)) {
			setOpen(false, false);
		}
	});
})();
