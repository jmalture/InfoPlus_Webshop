(function ($) {
  function bindUpload($btn) {
    $btn.on('click', function (e) {
      e.preventDefault();
      var target = $(this).data('target');         // e.g., "#ibe_ife_image"
      var $input = $(target);
      var $wrap = $input.closest('.form-field');
      var frame = wp.media({
        title: 'Select or Upload Image',
        button: { text: 'Use this image' },
        multiple: false
      });
      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        $input.val(attachment.url);
        $wrap.find('img.infoplus-preview').remove();
        $('<img>', {
          class: 'infoplus-preview',
          src: attachment.url,
          css: { maxWidth: '100px', display: 'block', marginBottom: '10px' }
        }).insertAfter($wrap.find('label').first());
      });
      frame.open();
    });
  }

  function bindRemove($btn) {
    $btn.on('click', function (e) {
      e.preventDefault();
      var target = $(this).data('target');
      var $input = $(target);
      var $wrap = $input.closest('.form-field');
      $input.val('');
      $wrap.find('img.infoplus-preview').remove();
    });
  }

  $(function () {
    bindUpload($('.infoplus-upload'));
    bindRemove($('.infoplus-remove'));
  });
})(jQuery);
