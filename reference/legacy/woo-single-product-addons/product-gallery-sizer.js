jQuery(function($){

  function setGalleryHeightFromInfo(){
    const $info  = $('#product_info');               // right column wrapper
    const $wrap  = $('#product_gallery_wrap');       // gallery widget (with CSS ID)
    if (!$info.length || !$wrap.length) return;

    const h = $info.outerHeight(true);               // full height of right side
    $wrap[0].style.setProperty('--pd-equal-h', h + 'px');
  }

  // Initial
  $(window).on('load', setGalleryHeightFromInfo);

  // Resize (debounced)
  let rAF;
  $(window).on('resize', function(){
    cancelAnimationFrame(rAF);
    rAF = requestAnimationFrame(setGalleryHeightFromInfo);
  });

  // Woo variation changes (images/price area height changes)
  $('form.variations_form')
    .on('found_variation reset_data', function(){
      setTimeout(setGalleryHeightFromInfo, 60);
    });

  // Qty widget may change the right column height (totals line)
  $('#custom_qty_container')
    .on('click input blur', '.qty-btn, .qty-value', function(){
      setTimeout(setGalleryHeightFromInfo, 20);
    });

  // If your bulk table updates the right column height
  $(document).on('bulkGridUpdated', function(){
    setTimeout(setGalleryHeightFromInfo, 80);
  });

});
