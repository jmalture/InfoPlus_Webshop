/*
 * Floating Kontakt panel (template-parts/contact-panel.php).
 * Behaviour of the old Elementor snippet 14273: the tab toggles the panel,
 * a click outside closes it. Added: Escape closes it (focus goes back to the
 * tab when it was inside), focus leaving the panel closes it, aria-expanded
 * follows the state. The panel width is fixed in CSS, so nothing is measured
 * here (the snippet measured it on load and on resize).
 */
(function () {
	'use strict';

	var root = document.querySelector('[data-ip-contact]');
	if (!root) {
		return;
	}
	var tab = root.querySelector('[data-ip-contact-toggle]');
	var closeBtn = root.querySelector('[data-ip-contact-close]');
	if (!tab) {
		return;
	}

	function isOpen() {
		return root.classList.contains('is-open');
	}

	function setOpen(open, focusTab) {
		root.classList.toggle('is-open', open);
		tab.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (!open && focusTab) {
			tab.focus();
		}
	}

	tab.addEventListener('click', function () {
		setOpen(!isOpen(), false);
	});

	if (closeBtn) {
		closeBtn.addEventListener('click', function () {
			setOpen(false, true);
		});
	}

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
