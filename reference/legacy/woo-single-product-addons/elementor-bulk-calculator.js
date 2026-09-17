jQuery(function($) {
    let prevPieceValue = null;
    let prevTotalValue = null;

    // --- Animation Helpers ---
    function animateText($el, newText, prevValStore) {
        if ($el.text() === newText) return;
        $el.fadeOut(120, function() {
            $el.text(newText).fadeIn(180);
        });
        if (typeof prevValStore === 'function') prevValStore(newText);
    }

    function shake($el) {
        $el.addClass('shake');
        setTimeout(function() {
            $el.removeClass('shake');
        }, 500);
    }

    // --- Bulk Table ---
	function updateBulkGridFromAPI(breaks) {
		let $rows = $('#bulk_table .bulk_item');
		$rows.each(function(i) {
			let $row = $(this);
			let $count = $row.find('.bulk_count p');
			let $price = $row.find('.bulk_price p');
			let data = breaks[i];

			// --- Data checks ---
			let valid =
				data &&
				// Only accept numbers as string or number
				(typeof data.from === "string" || typeof data.from === "number") &&
				(data.to === "" || typeof data.to === "string" || typeof data.to === "number") &&
				!isNaN(parseFloat(data.value));

			if (valid) {
				let countText = data.to && data.to !== "" ? data.to : (data.from + "+");
				let priceText = Number(data.value).toLocaleString('de-DE', {minimumFractionDigits: 2}) + " €";
				animateText($count, countText);
				animateText($price, priceText);
				$row.show();
			} else {
				// If invalid: keep count as is, show "–" as price, show row
				animateText($price, "–");
				$row.show();
			}
		});
	}

    // --- Bulk Price Calculation ---
    function getBulkPriceForQty(breaks, qty) {
        for (let i = 0; i < breaks.length; i++) {
            let from = parseInt(breaks[i].from, 10);
            let to = breaks[i].to ? parseInt(breaks[i].to, 10) : null;
            if ((qty >= from) && (to === null || qty <= to)) {
                return parseFloat(breaks[i].value.replace(',', '.'));
            }
        }
        return null;
    }
    function formatPrice(num) {
        return num.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }
    let lastBreaks = [];

    function updateIndividualAndTotalPrice(qty) {
        let price = getBulkPriceForQty(lastBreaks, qty);
        let $piece = $('#Stuckpreis_value h2');
        let $total = $('#Gesamtpreis_value h2');
        if (price !== null) {
            animateText($piece, formatPrice(price));
            animateText($total, formatPrice(price * qty));
        } else {
            animateText($piece, '0,00 €');
            animateText($total, '0,00 €');
        }
    }

    // --- Custom Qty Input UI ---
function renderCustomQtyInput() {
    // Prevent double insert by checking for qty-value (or plus/minus)
    if ($('#custom_qty_container .qty-value').length) return;

    let html = `
        <button type="button" id="#custom_qty_input" class="qty-btn minus" aria-label="Menge verringern">–</button>
        <span class="qty-value" tabindex="0" contenteditable="true" role="spinbutton" aria-valuemin="1" aria-valuenow="1" aria-valuemax="99999">1</span>
        <button type="button" class="qty-btn plus" aria-label="Menge erhöhen">+</button>
    `;

    $('#custom_qty_container').prepend(html);
}

    // --- Min/Max Logic ---
    let minQty = 1, maxQty = 99999;
    function setQtyLimits(min, max) {
        minQty = min;
        maxQty = max || 99999;
        let $input = $('#custom_qty_container .qty-value');
        let current = parseInt($input.val(), 10) || minQty;
        if (current < minQty) current = minQty; 
        if (current > maxQty) current = maxQty;
        $input.val(current).attr('aria-valuemin', minQty)
            .attr('aria-valuemax', maxQty)  
            .attr('aria-valuenow', current);
    }
	
	function setQtyControlsEnabled(enabled) {
    let $input = $('#custom_qty_input');
    let $btn = $('#product_gestalten_btn');
    $input.prop('disabled', !enabled)
        .attr('aria-disabled', !enabled)
        .toggleClass('qty-disabled', !enabled);
    $btn.prop('disabled', !enabled)
        .attr('aria-disabled', !enabled)
        .toggleClass('qty-disabled', !enabled);
    if (!enabled) {
        $input.val(''); // Clear
    }
}

    // --- Qty Input Handlers ---
	function bindCustomQtyListener() {
		let $val = $('#custom_qty_container .qty-value');
		let $container = $('#custom_qty_container');

        function clampAndSetValue(v) {
        let min = minQty, max = maxQty;
        let clamped = v;
        if (v < min) {
            clamped = min;
            shake($val); // 👈 Only shakes if below min
        }
        if (v > max) clamped = max;
        $val.text(clamped);
        $val.attr('aria-valuenow', clamped);
        $val.attr('aria-valuemin', min);
        $val.attr('aria-valuemax', max);
        updateIndividualAndTotalPrice(clamped);
    }   
		// plus/minus Functionality
		$container.off('click.bulkprice').on('click.bulkprice', '.qty-btn', function() {
            let min = minQty, max = maxQty;
			let v = parseInt($val.text(), 10) || min;
			if ($(this).hasClass('plus') && v < max) v++;
			if ($(this).hasClass('minus') && v > min) v--;
            clampAndSetValue(v);
		});

		// Typing (on blur or enter)
		$val.off('blur.bulkprice keydown.bulkprice').on('blur.bulkprice', function() {
            let min = minQty;
			let v = parseInt($val.text(), 10) || min;
            clampAndSetValue(v);

		}).on('keydown.bulkprice', function(e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				$(this).blur();
			}
    });
}

    // --- Helper to get min bulk qty from API breaks ---
    function getMinBulkQty(breaks) {
        if (!Array.isArray(breaks) || !breaks.length) return null;
        let min = Math.min(...breaks
            .map(b => parseInt(b.from, 10))
            .filter(v => !isNaN(v))
        );
        return isFinite(min) ? min : null;
    }

    //get gestalten button
    let $btn = $('#product_gestalten_btn');

    function lookupUrlByVariationId(variationId) {
    if (!window.allWooVariations) return '';
    const row = window.allWooVariations.find(v => String(v.variation_id) === String(variationId));
    return row && row.ibe_ife_url ? row.ibe_ife_url : '';
  }
    // --- Woo Events ---
 $('form.variations_form').on('found_variation', function(event, variation) {
    
    const url = lookupUrlByVariationId(variation && variation.variation_id);
    if (url) {
        $btn.removeAttr('disabled')
        .removeClass('is-disabled')
        .attr('href', url)            // if it's an <a>
        .data('ife-url', url);        // or keep it on data for later use
    } else {
        $btn.attr('href', '#')
          .addClass('is-disabled')
          .attr('disabled', true);
    }


    setQtyControlsEnabled(true);

    let sku = variation && variation.sku;
    let min = 1, max = 99999;
    let wooMin = variation && variation.min_qty ? parseInt(variation.min_qty, 10) : 1;
    let wooMax = variation && variation.max_qty ? parseInt(variation.max_qty, 10) : 99999;
    setQtyLimits(min, max);

    if (sku) {
        let apiUrl = 'https://navajowhite-rhinoceros-679020.hostingersite.com/wp-json/infoplus/v1/products/prices?sku=' + encodeURIComponent(sku);
        $.getJSON(apiUrl, function(prices) {
            lastBreaks = prices || [];
            updateBulkGridFromAPI(prices);

             // ---- Get minimums ----
                let apiMin = getMinBulkQty(prices);
                let finalMin = Math.max(wooMin, apiMin || 1);
                let finalMax = wooMax; // You could check for a "max" in the breaks array if needed

             // Set min/max on the input
             setQtyLimits(finalMin, finalMax);


           $('#custom_qty_container .qty-value').text(finalMin).attr('aria-valuenow', finalMin);
            updateIndividualAndTotalPrice(finalMin);

            // Update "Mindestanzahl" value
            $('#Mindestanzahl_value').text(finalMin);
        });
    }
});


	$('form.variations_form').on('reset_data', function() {
    setQtyControlsEnabled(false);
     $btn.attr('href', '#').addClass('is-disabled').attr('disabled', true);
});

    // --- On Document Ready ---
    $(document).ready(function() {
        // Hide Woo fields
        $('.single_variation_wrap, .woocommerce-variation-add-to-cart, .woocommerce-variation-price').hide();
		setQtyControlsEnabled(false);

        // Render UI
        renderCustomQtyInput();
        bindCustomQtyListener();
        updateIndividualAndTotalPrice(1); // Initial price update

        // Initial zeroes
        let $piece = $('#Stuckpreis_value h2');
        let $total = $('#Gesamtpreis_value h2');
        animateText($piece, '0,00 €', v => prevPieceValue = v);
        animateText($total, '0,00 €', v => prevTotalValue = v);

        // Initial min/max
        setQtyLimits(1, 99999);
    });

    // re-bind qty field (if page is reloaded/ loaded with AJAX)
    $(document).on('DOMNodeInserted', function(e) {
        if ($(e.target).is('#custom_qty_input')) {
            bindCustomQtyListener();
        }
    });

    // Sync prices if qty is changed
    $('#custom_qty_input').on('input change', function() {
        let qty = parseInt($(this).val(), 10) || minQty;
        updateIndividualAndTotalPrice(qty);
    });
});
