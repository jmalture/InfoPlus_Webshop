<?php
/**
 * Theme functions and definitions.
 *
 * For additional information on potential customization options,
 * read the developers' documentation:
 *
 * https://developers.elementor.com/docs/hello-elementor-theme/
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HELLO_ELEMENTOR_CHILD_VERSION', '2.0.0' );

/**
 * Load child theme scripts & styles.
 *
 * @return void
 */
function hello_elementor_child_scripts_styles() {

	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		[
			'hello-elementor-theme-style',
		],
		HELLO_ELEMENTOR_CHILD_VERSION
	);

}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_scripts_styles', 20 );

// Custom logout / redirect for the dashboard logout button
function custom_logout_shortcode($atts){
	
	//redirect to login page
	$atts = shortcode_atts(array('redirect' => home_url('/')),$atts);
	$logout_url = wp_logout_url( esc_url($atts['redirect']) );
	return esc_url($logout_url) ;	
}
add_shortcode('custom_logout', 'custom_logout_shortcode');

add_filter( 'pre_get_posts', function( $query ) {
    if ( $query->is_search() && ! is_admin() && $query->is_main_query() ) {
        $query->set( 'post_type', array( 'product' ) );
    }
});

add_filter( 'gettext', function( $translated, $original, $domain ) {
    if ( $original === 'No products in the cart.' ) {
        return 'Keine Produkte im Warenkorb.';
    }
    return $translated;
}, 10, 3 );

 add_action('wp_footer', 'translate_checkout_strings_js', 999);
  function translate_checkout_strings_js() {
      if (is_cart() || is_checkout()) {
          ?>
          <script>
          jQuery(document).ready(function($) {
              function translateStrings() {
                  // Cart page translations
                  $('body').find('*').contents().filter(function() {
                      return this.nodeType === 3 && this.nodeValue.trim() === 'Add coupons';
                  }).each(function() {
                      this.nodeValue = 'Gutschein hinzufügen';
                  });

                  $('body').find('*').contents().filter(function() {
                      return this.nodeType === 3 && this.nodeValue.trim() === 'Estimated total';
                  }).each(function() {
                      this.nodeValue = 'Geschätzte Gesamtsumme';
                  });

                  $('label[for="wc-block-components-totals-coupon__input-coupon"]').text('Code eingeben');
                  $('#wc-block-components-totals-coupon__input-coupon').attr('aria-label', 'Code eingeben');

                  $('.wc-block-components-totals-footer-item-tax').contents().filter(function() {
                      return this.nodeType === 3 && this.nodeValue.includes('Including');
                  }).each(function() {
                      this.nodeValue = this.nodeValue.replace('Including', 'Inkl.');
                  });

                  // Checkout page translations
                  $('body').find('*').contents().filter(function() {
                      return this.nodeType === 3 && this.nodeValue.trim() === 'Contact information';
                  }).each(function() {
                      this.nodeValue = 'Kontaktinformationen';
                  });

                  $('body').find('*').contents().filter(function() {
                      return this.nodeType === 3 && this.nodeValue.trim() === 'Add a note to your order';
                  }).each(function() {
                      this.nodeValue = 'Notiz zur Bestellung hinzufügen';
                  });

                  $('body').find('*').contents().filter(function() {
                      return this.nodeType === 3 && this.nodeValue.trim() === 'View Cart';
                  }).each(function() {
                      this.nodeValue = 'Warenkorb';
                  });

                  $('.wc-block-cart__submit-container a[href*="cart"]').each(function() {
                  if ($(this).text().trim() === 'View Cart' || $(this).text().trim() === 'Warenkorb anzeigen') {
                      $(this).text('Warenkorb');
                  }
                  });

                  // Elementor Menu Cart — "Warenkorb anzeigen" → "Warenkorb"
                  $('.elementor-menu-cart__view-cart-button, .elementor-menu-cart__footer-buttons a').each(function() {
                      var $el = $(this);
                      if ($el.text().trim() === 'View Cart' || $el.text().trim() === 'Warenkorb anzeigen') {
                          $el.text('Warenkorb');
                      }
                  });

                  $('body').find('*').contents().filter(function() {
                      return this.nodeType === 3 && this.nodeValue.trim() === 'Return to Cart';
                  }).each(function() {
                      this.nodeValue = 'Zurück zum Warenkorb';
                  });

                  $('body').find('*').contents().filter(function() {
                      return this.nodeType === 3 && this.nodeValue.includes('By proceeding with your purchase you agree to our');
                  }).each(function() {
                      this.nodeValue = this.nodeValue
                          .replace('By proceeding with your purchase you agree to our', 'Mit Ihrer Bestellung stimmen Sie unseren')
                          .replace('Terms and Conditions', 'AGB')
                          .replace('and', 'und')
                          .replace('Privacy Policy', 'Datenschutzbestimmungen');
                  });

                  $('body').find('*').contents().filter(function() {
                      return this.nodeType === 3 && this.nodeValue.trim() === 'Payment options';
                  }).each(function() {
                      this.nodeValue = 'Zahlungsoptionen';
                  });

                  $('body').find('*').contents().filter(function() {
                      return this.nodeType === 3 && this.nodeValue.trim() === 'Billing address';
                  }).each(function() {
                      this.nodeValue = 'Rechnungsadresse';
                  });

                  $('body').find('*').contents().filter(function() {
                      return this.nodeType === 3 && this.nodeValue.trim() === 'Shipping address';
                  }).each(function() {
                      this.nodeValue = 'Lieferadresse';
                  });

                  $('body').find('*').contents().filter(function() {
                      return this.nodeType === 3 && this.nodeValue.trim() === 'Order summary';
                  }).each(function() {
                      this.nodeValue = 'Bestellübersicht';
                  });

                  // Translate "Cash on delivery" if present
                  $('body').find('*').contents().filter(function() {
                      return this.nodeType === 3 && this.nodeValue.trim() === 'Cash on delivery';
                  }).each(function() {
                      this.nodeValue = 'Barzahlung bei Lieferung';
                  });

                  // "You are currently checking out as a guest."
                  $('body').find('*').contents().filter(function() {
                      return this.nodeType === 3 && this.nodeValue.trim() === 'You are currently checking out as a guest.';
                  }).each(function() {
                      this.nodeValue = 'Sie bestellen gerade als Gast.';
                  });

                  // "+ Add apartment/Wohnung…" button — the "Add" prefix stays English while the rest is translated
                  $('button, a').contents().filter(function() {
                      return this.nodeType === 3 && /^\+\s*Add\b/.test(this.nodeValue.trim());
                  }).each(function() {
                      this.nodeValue = this.nodeValue.replace(/^\+\s*Add\b/, '+ Hinzufügen:');
                  });

                  // "Pay with cash upon delivery." — payment method description
                  $('body').find('*').contents().filter(function() {
                      return this.nodeType === 3 && this.nodeValue.trim() === 'Pay with cash upon delivery.';
                  }).each(function() {
                      this.nodeValue = 'Bezahlung bei Liefereingang (direkt beim Kurier).';
                  });

                  // "Notes about your order." — order notes textarea placeholder
                  $('textarea[name="order_comments"], textarea[id*="order-note"], textarea[id*="order_comments"]').each(function() {
                      if ($(this).attr('placeholder') === 'Notes about your order.') {
                          $(this).attr('placeholder', 'Hinweise zur Bestellung');
                      }
                  });
                  $('body').find('*').contents().filter(function() {
                      return this.nodeType === 3 && this.nodeValue.trim() === 'Notes about your order.';
                  }).each(function() {
                      this.nodeValue = 'Hinweise zur Bestellung';
                  });

                  // Translate link texts "Terms and Conditions" and "Privacy Policy"
                  // inside the consent/T&C sentence
                  $('a').each(function() {
                      var $a = $(this);
                      var text = $a.text().trim();
                      if (text === 'Terms and Conditions') {
                          $a.text('Allgemeinen Geschäftsbedingungen');
                      } else if (text === 'Privacy Policy') {
                          $a.text('Datenschutzerklärung');
                      }
                  });
              }

              // Run immediately
              translateStrings();

              // Run after WooCommerce updates
              $(document.body).on('updated_cart_totals updated_checkout payment_method_selected', function() {
                  setTimeout(translateStrings, 100);
              });

              // Watch for DOM changes
              const observer = new MutationObserver(function() {
                  translateStrings();
              });
              observer.observe(document.body, { childList: true, subtree: true });
          });
          </script>
          <?php
      }
  }

add_action('wp_footer', 'infoplus_sync_elementor_minicart', 999);
function infoplus_sync_elementor_minicart() {
    if (!is_cart()) return;
    ?>
    <script>
    jQuery(document).ready(function($) {
        if (typeof wp === 'undefined' || !wp.data) return;

        const cartStore = wp.data.select('wc/store/cart');
        if (!cartStore) return;

        let lastCount = null;

        wp.data.subscribe(function() {
            const cart = cartStore.getCartData();
            if (!cart) return;

            const currentCount = cart.items_count;

            if (lastCount === null) {
                lastCount = currentCount;
                return;
            }

            if (currentCount !== lastCount) {
                lastCount = currentCount;

                // Fetch fresh WC fragments so Elementor mini-cart re-renders
                $.get('/?wc-ajax=get_refreshed_fragments', function(data) {
                    if (!data || !data.fragments) return;

                    $.each(data.fragments, function(key, value) {
                        $(key).replaceWith(value);
                    });

                    $(document.body).trigger('wc_fragments_refreshed');
                });
            }
        });
    });
    </script>
    <?php
}




add_action('wp_enqueue_scripts', function() {
    if (is_cart() || is_checkout()) {
        wp_dequeue_script('woo-variation-gallery');
        wp_dequeue_script('woo-variation-gallery-slick');
        wp_dequeue_style('woo-variation-gallery');
    }
}, 99);