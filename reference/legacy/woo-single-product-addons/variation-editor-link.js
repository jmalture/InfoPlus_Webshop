/**
 * Variation → Editor link handler
 * Depends on jQuery and window.allWooVariations
 * Target button: #product_gestalten_btn (Elementor Button widget)
 * Puts href on the inner anchor and toggles disabled state on the outer wrapper.
 */
(function ($, w, d) {
  'use strict';

  var BTN_WRAP_SEL = '#product_gestalten_btn';

  // Build a quick lookup map: variation_id -> url
  var urlMap = (function buildUrlMap() {
    var map = {};
    if (Array.isArray(w.allWooVariations)) {
      w.allWooVariations.forEach(function (row) {
        if (row && row.variation_id) {
          map[String(row.variation_id)] = row.ibe_ife_url || '';
        }
      });
    }
    return map;
  })();

  function isValidUrl(u) {
    if (!u || typeof u !== 'string') return false;
    try { new URL(u); return true; } catch (e) { return false; }
  }

  function getUrlForVariation(variationId) {
    return urlMap[String(variationId)] || '';
  }

  function setButtonUrl(url) {
    var $wrap   = $(BTN_WRAP_SEL);                  // Elementor widget wrapper (div)
    var $anchor = $wrap.find('a.elementor-button'); // The real clickable element

    if (isValidUrl(url)) {
      $anchor.attr({ href: url, target: '_blank', rel: 'noopener' });
      $wrap.removeAttr('disabled')
           .attr('aria-disabled', 'false')
           .removeClass('is-disabled');
    } else {
      $anchor.removeAttr('href');
      $wrap.attr('disabled', 'disabled')
           .attr('aria-disabled', 'true')
           .addClass('is-disabled');
    }
  }

  // Safety: block clicks when disabled (in case CSS/attrs aren’t enough)
  $(d).on('click', BTN_WRAP_SEL + ' a.elementor-button', function (e) {
    var disabled = $(this).closest(BTN_WRAP_SEL).is('[disabled]');
    if (disabled) e.preventDefault();
  });

  // For each variations form on the page
  function bindForm($form) {
    // Woo fires this when a valid combination is selected
    $form.on('found_variation', function (_e, variation) {
      var vid = variation && variation.variation_id ? variation.variation_id : null;
      var url = vid ? getUrlForVariation(vid) : '';
      setButtonUrl(url);
    });

    // Fired when the selection is cleared/reset
    $form.on('reset_data', function () {
      setButtonUrl('');
    });

    // Handle the case where a variation is already selected on load
    // (e.g., default attributes). Woo puts the id into a hidden input. 
    var tryInitial = function () {
      var vid = $form.find('input.variation_id').val();
      if (vid) {
        setButtonUrl(getUrlForVariation(vid));
      } else {
        setButtonUrl('');
      }
    };

    // Try on ready and also when the hidden input changes
    tryInitial();
    $form.on('change', 'input.variation_id', tryInitial);
  }

  $(function () {
    // If there is no button on the page, bail early
    if (!$(BTN_WRAP_SEL).length) return;

    // Bind to all variation forms on the page
    $('form.variations_form').each(function () {
      bindForm($(this));
    });
  });

})(jQuery, window, document);
