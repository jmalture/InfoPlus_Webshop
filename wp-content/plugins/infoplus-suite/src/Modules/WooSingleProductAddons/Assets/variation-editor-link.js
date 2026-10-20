/**
 * Order Type Button Manager
 *
 * Controls visibility and enabled/disabled state of action buttons (#editor, #anfrage)
 * based on the per-model order_type value and WooCommerce variation selection state.
 *
 * Order types (from window.infoplusProductData.models[n].order_type):
 *   editable    → show #editor (editor link) + #anfrage; editor disabled until variation found
 *   requestable → show #anfrage only; #editor hidden
 *   buyable     → show #editor (as add-to-cart trigger) only; #anfrage hidden; disabled until variation found
 *
 * Theme buy form (since Plugin::VERSION 0.10.27): when the page renders
 * form[data-ip-buy-form] (classic theme, template-parts/product/actions.php),
 * "buyable" does NOT turn #editor into the admin-ajax infoplus_add_to_cart
 * trigger. #editor is hidden and the theme shows its own WooCommerce
 * add-to-cart form (standard POST, WC_Form_Handler). Pages without that form
 * (the Elementor template) keep the ajax behaviour below.
 *
 * Since Plugin::VERSION 0.10.28 #anfrage stays visible for "buyable" models on
 * pages with the buy form, so the inquiry is always available there (the theme
 * shows it as a secondary button). The Elementor template still hides it.
 *
 * The resolved order type is published as infoplusProductState.orderType and
 * emitted as 'ordertype:changed' ({ orderType, model }), so the theme does not
 * have to repeat the lookup (including the Sonderkontur slug fallback).
 *
 * Depends on: jQuery, window.infoplusEditorSettings, window.infoplusProductState (optional)
 */
(function ($, w, d) {
  'use strict';

  // Elementor template: Button widgets with CSS ID editor / anfrage (the ID sits
  // on the widget wrapper, the link is .elementor-button inside it).
  // Classic theme: the link itself carries data-ip-action="editor" / "inquiry"
  // and its label is the child [data-ip-label].
  var EDITOR_WRAP_SEL   = '#editor, [data-ip-action="editor"]';
  var EDITOR_ANCHOR_SEL = '#editor .elementor-button, a[data-ip-action="editor"]';
  var ANFRAGE_SEL       = '#anfrage, [data-ip-action="inquiry"]';
  var LABEL_SEL         = '.elementor-button-text, [data-ip-label]';
  var QTY_SEL           = '#qty_value, [data-ip-qty="value"]';
  var BUY_FORM_SEL      = 'form[data-ip-buy-form]';
  var ERROR_CLASS       = 'infoplus-btn-error-msg';
  var DISABLED_CLASS    = 'infoplus-btn-disabled';
  var HIDDEN_CLASS      = 'infoplus-btn-hidden';
  var KOMBINATION_TEXT  = 'Kombination nicht verfügbar';

  // Tracked state
  var currentOrderType  = 'editable';
  var variationIsValid  = false;   // true after variation:found
  var variationNotFound = false;   // true when all options picked but no match

  // ── Helpers ──────────────────────────────────────────────────────────────

  function isEditMode() {
    return !!(w.elementorFrontend && w.elementorFrontend.isEditMode && w.elementorFrontend.isEditMode());
  }

  /** Classic theme page with its own add-to-cart form for "buyable" models. */
  function hasBuyForm() {
    return $(BUY_FORM_SEL).length > 0;
  }

  function getEditorSettings() {
    return w.infoplusEditorSettings || {};
  }

  function generateEditorUrl() {
    var s = getEditorSettings();
    if (!s.baseUrl || !s.themeId || !s.productUuid) return null;
    var url = s.baseUrl +
              '?product=' + encodeURIComponent(s.productUuid) +
              '&theme='   + encodeURIComponent(s.themeId);
    if (s.cartEntryPath) url += '&cartEntryPath=' + encodeURIComponent(s.cartEntryPath);
    if (s.backPath)      url += '&backPath='      + encodeURIComponent(s.backPath);
    return url;
  }

  /** Detect Sonderkontur by model slug (backward-compat fallback) */
  function isSonderkonturBySlug() {
    var $sel = $('select[name^="attribute_pa_model"]');
    if (!$sel.length) return false;
    var v = ($sel.val() || '').toLowerCase();
    var t = ($sel.find('option:selected').text() || '').toLowerCase();
    return v.indexOf('sonderkontur') !== -1 || t.indexOf('sonderkontur') !== -1;
  }

  // ── Pulse animation helper ───────────────────────────────────────────────

  function pulseButton($wrap) {
    var $btn = $wrap.find('.elementor-button').add($wrap.filter('[data-ip-action]'));
    if (!$btn.length) return;
    var cls = 'infoplus-anfrage-pulse--long';
    $btn.removeClass('infoplus-anfrage-pulse infoplus-anfrage-pulse--long');
    $btn.each(function () { void this.offsetWidth; }); // restart animation
    $btn.addClass(cls);
    setTimeout(function () { $btn.removeClass(cls); }, 3100);
  }

  // ── Error message helpers ─────────────────────────────────────────────────

  function showError($wrap, msg) {
    $wrap.nextAll('.' + ERROR_CLASS).remove();
    $wrap.after('<span class="' + ERROR_CLASS + '">' + msg + '</span>');
  }

  function removeError($wrap) {
    $wrap.nextAll('.' + ERROR_CLASS).remove();
  }

  // ── Editor button state helpers ───────────────────────────────────────────

  function disableEditorBtn() {
    var $wrap   = $(EDITOR_WRAP_SEL);
    var $anchor = $(EDITOR_ANCHOR_SEL);
    if (!$wrap.length) return;
    $anchor.off('click.ordertype');
    $anchor.attr('href', '#').removeAttr('target rel');
    $wrap.addClass(DISABLED_CLASS).attr('aria-disabled', 'true');
    $anchor.addClass(DISABLED_CLASS);
  }

  function enableEditorUrl(url) {
    var $wrap   = $(EDITOR_WRAP_SEL);
    var $anchor = $(EDITOR_ANCHOR_SEL);
    if (!$wrap.length) return;
    $anchor.off('click.ordertype');
    $anchor.attr({ href: url, target: '_blank', rel: 'noopener' });
    $wrap.removeClass(DISABLED_CLASS).attr('aria-disabled', 'false');
    $anchor.removeClass(DISABLED_CLASS);
    // Restore original text if it was overridden
    var $text = $anchor.find(LABEL_SEL);
    if ($text.length && $text.data('original-text')) {
      $text.text($text.data('original-text'));
      $text.removeData('original-text');
    }
  }

  function setEditorAsCartLabel() {
    var $anchor = $(EDITOR_ANCHOR_SEL);
    if (!$anchor.length) return;
    var $text = $anchor.find(LABEL_SEL);
    if ($text.length) {
      if (!$text.data('original-text')) {
        $text.data('original-text', $text.text());
      }
      $text.text('In den Warenkorb');
    }
  }

  function restoreEditorLabel() {
    var $anchor = $(EDITOR_ANCHOR_SEL);
    if (!$anchor.length) return;
    var $text = $anchor.find(LABEL_SEL);
    if ($text.length && $text.data('original-text')) {
      $text.text($text.data('original-text'));
      $text.removeData('original-text');
    }
  }

  function enableEditorAsCart() {
    var $wrap   = $(EDITOR_WRAP_SEL);
    var $anchor = $(EDITOR_ANCHOR_SEL);
    if (!$wrap.length) return;
    $anchor.off('click.ordertype').on('click.ordertype', triggerAddToCart);
    $anchor.attr('href', '#add-to-cart').removeAttr('target rel');
    $wrap.removeClass(DISABLED_CLASS).attr('aria-disabled', 'false');
    $anchor.removeClass(DISABLED_CLASS);
    var $text = $anchor.find(LABEL_SEL);
    if ($text.length) {
      if (!$text.data('original-text')) {
        $text.data('original-text', $text.text());
      }
      $text.text('In den Warenkorb');
    }
  }

  function triggerAddToCart(e) {
    e.preventDefault();

    var $anchor = $(EDITOR_ANCHOR_SEL);

    // Get variation ID from infoplusProductState (primary) or WC form (fallback)
    var variationId = null;
    if (w.infoplusProductState && w.infoplusProductState.selectedVariation) {
      var sv = w.infoplusProductState.selectedVariation;
      variationId = sv.variation_id || sv.id || null;
      if (variationId) variationId = String(variationId);
    }
    if (!variationId) {
      var $form = $('form.variations_form');
      variationId = $form.find('input.variation_id').val() || null;
    }

    if (!variationId) {
      alert('Bitte wählen Sie zuerst alle Produktoptionen');
      return;
    }

    // Get product ID
    var productId = null;
    if (w.infoplusProductData && w.infoplusProductData.id) {
      productId = w.infoplusProductData.id;
    } else {
      var $form = $('form.variations_form');
      productId = $form.find('input[name="product_id"]').val() || null;
    }

    if (!productId) {
      console.error('[OrderTypeButtons] Product ID not found');
      return;
    }

    // Get quantity (bulk calculator uses #qty_value, WooCommerce uses input.qty)
    var qty = parseInt($(QTY_SEL).first().val()) || parseInt($('input.qty, input[name="quantity"]').val()) || 1;

    // Disable button while processing
    var $text = $anchor.find(LABEL_SEL);
    var origText = $text.text();
    $text.text('Wird hinzugefügt...');
    $anchor.addClass(DISABLED_CLASS);

    // Get model image URL for cart display
    var modelImageUrl = '';
    if (w.infoplusProductState && w.infoplusProductState.selectedModel && w.infoplusProductData && w.infoplusProductData.models) {
      var models = w.infoplusProductData.models;
      for (var i = 0; i < models.length; i++) {
        if (models[i].slug === w.infoplusProductState.selectedModel) {
          modelImageUrl = models[i].image_url || '';
          break;
        }
      }
    }

    // AJAX add to cart via our lightweight handler
    var ajaxUrl = w.woocommerce_params ? w.woocommerce_params.ajax_url : '/wp-admin/admin-ajax.php';
    $.post(ajaxUrl, {
      action: 'infoplus_add_to_cart',
      product_id: productId,
      variation_id: variationId,
      quantity: qty,
      model_image_url: modelImageUrl
    }).done(function (response) {
      console.log('[OrderTypeButtons] Add to cart response:', response);
      if (!response.success) {
        console.error('[OrderTypeButtons] Add to cart error:', response);
        $text.text(response.data && response.data.message ? response.data.message : 'Fehler');
        $anchor.removeClass(DISABLED_CLASS);
        setTimeout(function () { $text.text(origText); }, 2000);
        return;
      }
      // Success
      $text.text('✓ Hinzugefügt!');
      $anchor.removeClass(DISABLED_CLASS);
      // Update cart fragments (mini-cart, count badges, etc.)
      if (response.data && response.data.fragments) {
        $.each(response.data.fragments, function (key, value) {
          $(key).replaceWith(value);
        });
      }
      $(d.body).trigger('wc_fragment_refresh');
      setTimeout(function () { $text.text(origText); }, 2000);
    }).fail(function (jqXHR, textStatus, errorThrown) {
      console.error('[OrderTypeButtons] Add to cart failed:', textStatus, errorThrown, jqXHR.responseText);
      $text.text('Fehler');
      $anchor.removeClass(DISABLED_CLASS);
      setTimeout(function () { $text.text(origText); }, 2000);
    });
  }

  /**
   * Fallback: add to cart via direct POST to WooCommerce's native endpoint
   */
  function addToCartDirect(productId, variationId, qty, $anchor, $text, origText) {
    var formData = new FormData();
    formData.append('product_id', productId);
    formData.append('variation_id', variationId);
    formData.append('quantity', qty);
    formData.append('add-to-cart', productId);

    // Get selected attributes for WC validation
    if (w.infoplusProductState && w.infoplusProductState.selectedVariation) {
      var attrs = w.infoplusProductState.selectedVariation.attributes || {};
      for (var key in attrs) {
        if (attrs.hasOwnProperty(key)) {
          formData.append('attribute_' + key.replace('attribute_', ''), attrs[key]);
        }
      }
    }

    fetch(w.location.href, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    }).then(function (res) {
      if (res.ok || res.redirected) {
        $text.text('✓ Hinzugefügt!');
        $anchor.removeClass(DISABLED_CLASS);
        $(d.body).trigger('wc_fragment_refresh');
        setTimeout(function () { $text.text(origText); }, 2000);
      } else {
        $text.text('Fehler');
        $anchor.removeClass(DISABLED_CLASS);
        setTimeout(function () { $text.text(origText); }, 2000);
      }
    }).catch(function () {
      $text.text('Fehler');
      $anchor.removeClass(DISABLED_CLASS);
      setTimeout(function () { $text.text(origText); }, 2000);
    });
  }

  // ── Order type visibility logic ───────────────────────────────────────────

  function publishOrderType(orderType) {
    var ps = w.infoplusProductState;
    if (!ps) return;
    ps.orderType = orderType;
    if (typeof ps.emit === 'function') {
      ps.emit('ordertype:changed', { orderType: orderType, model: ps.selectedModel || null });
    }
  }

  function applyOrderType(orderType) {
    if (isEditMode()) return;

    currentOrderType = orderType;
    publishOrderType(orderType);

    var $editor  = $(EDITOR_WRAP_SEL);
    var $anfrage = $(ANFRAGE_SEL);
    var $buyForm = $(BUY_FORM_SEL);

    if ($editor.length) removeError($editor);
    if ($buyForm.length) removeError($buyForm);

    if (orderType === 'editable') {
      $editor.removeClass(HIDDEN_CLASS);
      $anfrage.removeClass(HIDDEN_CLASS);
      // Editor disabled until variation found, restore original label
      disableEditorBtn();
      restoreEditorLabel();
      // If a valid variation is already selected, re-enable + pulse
      if (variationIsValid && !variationNotFound) {
        var url = generateEditorUrl();
        if (url) {
          enableEditorUrl(url);
          pulseButton($editor);
        }
      } else if (variationNotFound && $editor.length) {
        showError($editor, KOMBINATION_TEXT);
      }

    } else if (orderType === 'requestable' || orderType === 'requestable_with_variants') {
      $editor.addClass(HIDDEN_CLASS);
      $anfrage.removeClass(HIDDEN_CLASS);
      pulseButton($anfrage);

    } else if (orderType === 'buyable' && $buyForm.length) {
      // Theme page: the theme's add-to-cart form is the action. Leave the
      // editor link as a plain (disabled, hidden) editor link.
      // The inquiry stays available next to the buy form (0.10.28).
      $editor.addClass(HIDDEN_CLASS);
      $anfrage.removeClass(HIDDEN_CLASS);
      disableEditorBtn();
      restoreEditorLabel();
      if (variationNotFound) showError($buyForm, KOMBINATION_TEXT);

    } else if (orderType === 'buyable') {
      $editor.removeClass(HIDDEN_CLASS);
      $anfrage.addClass(HIDDEN_CLASS);
      // Show cart label immediately (disabled until variation found)
      disableEditorBtn();
      setEditorAsCartLabel();
      if (variationIsValid && !variationNotFound) {
        enableEditorAsCart();
        pulseButton($editor);
      } else if (variationNotFound && $editor.length) {
        showError($editor, KOMBINATION_TEXT);
      }
    }
  }

  // ── Variation event handlers ──────────────────────────────────────────────

  function onVariationFound() {
    variationIsValid  = true;
    variationNotFound = false;
    var $editor = $(EDITOR_WRAP_SEL);
    if ($editor.length) removeError($editor);

    if (currentOrderType === 'editable') {
      var url = generateEditorUrl();
      if (url) {
        enableEditorUrl(url);
        pulseButton($editor);
      }
    } else if (currentOrderType === 'buyable') {
      if (hasBuyForm()) {
        removeError($(BUY_FORM_SEL)); // The theme enables its own submit button.
      } else {
        enableEditorAsCart();
        pulseButton($editor);
      }
    }
    // requestable: nothing to do for #editor
  }

  function onVariationNotFound() {
    variationIsValid  = false;
    variationNotFound = true;
    var $editor = $(EDITOR_WRAP_SEL);

    if (currentOrderType === 'buyable' && hasBuyForm()) {
      showError($(BUY_FORM_SEL), KOMBINATION_TEXT);
    } else if (currentOrderType === 'editable' || currentOrderType === 'buyable') {
      disableEditorBtn();
      if ($editor.length) showError($editor, KOMBINATION_TEXT);
    }
  }

  function onVariationReset() {
    variationIsValid  = false;
    variationNotFound = false;
    var $editor = $(EDITOR_WRAP_SEL);
    if ($editor.length) removeError($editor);
    if (hasBuyForm()) removeError($(BUY_FORM_SEL));

    if (currentOrderType === 'editable' || currentOrderType === 'buyable') {
      disableEditorBtn();
    }
  }

  // ── Click guard (prevent navigation when disabled) ────────────────────────

  $(d).on('click', EDITOR_ANCHOR_SEL, function (e) {
    var href = $(this).attr('href');
    // Allow cart handler
    if (href === '#add-to-cart') return;
    var $wrap = $(EDITOR_WRAP_SEL);
    if ($wrap.hasClass(DISABLED_CLASS) || $wrap.attr('aria-disabled') === 'true' || href === '#') {
      e.preventDefault();
    }
  });

  // ── Initialization ────────────────────────────────────────────────────────

  function init() {
    // In Elementor editor mode: ensure all buttons visible, do nothing else
    if (isEditMode()) {
      $(EDITOR_WRAP_SEL).removeClass(HIDDEN_CLASS + ' ' + DISABLED_CLASS);
      $(ANFRAGE_SEL).removeClass(HIDDEN_CLASS);
      return;
    }

    // No action buttons on page → bail
    if (!$(EDITOR_WRAP_SEL).length && !$(ANFRAGE_SEL).length) return;

    // Set initial disabled state for editor button
    disableEditorBtn();

    // ── Use infoplusProductState event system ─────────────────────────────
    if (w.infoplusProductState) {

      w.infoplusProductState.on('model:changed', function (data) {
        var modelSlug = data.model;
        var orderType = 'editable';

        // Read order_type from product data
        if (w.infoplusProductData && w.infoplusProductData.models) {
          for (var i = 0; i < w.infoplusProductData.models.length; i++) {
            if (w.infoplusProductData.models[i].slug === modelSlug) {
              orderType = w.infoplusProductData.models[i].order_type || 'editable';
              break;
            }
          }
        }

        // Backward-compat: Sonderkontur slug detection when order_type not set in admin
        if (orderType === 'editable' && isSonderkonturBySlug()) {
          orderType = 'buyable';
        }

        // Reset variation state on model change
        variationIsValid  = false;
        variationNotFound = false;

        applyOrderType(orderType);
      });

      w.infoplusProductState.on('variation:found', function () {
        onVariationFound();
      });

      w.infoplusProductState.on('variation:notfound', function () {
        onVariationNotFound();
      });

      w.infoplusProductState.on('variation:reset', function () {
        onVariationReset();
      });

      // Replay: apply state already set by applyDefaultVariation before this script registered listeners
      if (w.infoplusProductState.selectedModel) {
        var modelSlug = w.infoplusProductState.selectedModel;
        var replayOrderType = 'editable';
        if (w.infoplusProductData && w.infoplusProductData.models) {
          for (var i = 0; i < w.infoplusProductData.models.length; i++) {
            if (w.infoplusProductData.models[i].slug === modelSlug) {
              replayOrderType = w.infoplusProductData.models[i].order_type || 'editable';
              break;
            }
          }
        }
        if (replayOrderType === 'editable' && isSonderkonturBySlug()) {
          replayOrderType = 'buyable';
        }
        variationIsValid  = false;
        variationNotFound = false;
        applyOrderType(replayOrderType);
        if (w.infoplusProductState.selectedVariation) {
          onVariationFound();
        }
      }

      return; // Using new event system; skip WooCommerce fallback below
    }

    // ── Fallback: WooCommerce form events ─────────────────────────────────
    // (for products without ProductDetailSuite widgets)
    var editorUrl = generateEditorUrl();

    $('form.variations_form').each(function () {
      var $form = $(this);

      $form.on('found_variation', function () {
        variationIsValid  = true;
        variationNotFound = false;
        var $editor = $(EDITOR_WRAP_SEL);
        if ($editor.length) removeError($editor);
        if (isSonderkonturBySlug() && !hasBuyForm()) {
          enableEditorAsCart();
        } else if (editorUrl) {
          enableEditorUrl(editorUrl);
        }
      });

      $form.on('reset_data', function () {
        variationIsValid  = false;
        variationNotFound = false;
        var $editor = $(EDITOR_WRAP_SEL);
        if ($editor.length) removeError($editor);
        disableEditorBtn();
      });

      // Check initial state
      setTimeout(function () {
        var variationId = $form.find('input.variation_id').val();
        if (variationId) {
          variationIsValid = true;
          if (isSonderkonturBySlug() && !hasBuyForm()) {
            enableEditorAsCart();
          } else if (editorUrl) {
            enableEditorUrl(editorUrl);
          }
        }
      }, 100);
    });

    if (!$('form.variations_form').length) {
      // Simple product — enable immediately
      if (editorUrl) enableEditorUrl(editorUrl);
    }
  }

  $(init);

})(jQuery, window, document);
