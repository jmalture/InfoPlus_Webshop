/**
 * Infoplus Product Attribute Images - Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Tab switching
    $(document).on('click', '.infoplus-attr-tab', function(e) {
        e.preventDefault();
        
        var $tab = $(this);
        var tabId = $tab.data('tab');
        var $wrap = $tab.closest('.infoplus-attr-images-wrap');
        
        // Update tab states
        $wrap.find('.infoplus-attr-tab').removeClass('is-active');
        $tab.addClass('is-active');
        
        // Update panel states
        $wrap.find('.infoplus-attr-panel').removeClass('is-active');
        $wrap.find('.infoplus-attr-panel[data-panel="' + tabId + '"]').addClass('is-active');
    });
    
    // Media uploader
    var mediaFrame;
    
    $(document).on('click', '.infoplus-attr-images-wrap .infoplus-upload-btn', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $card = $button.closest('.product-image-upload');
        var $input = $card.find('.infoplus-image-id');
        var $preview = $card.find('.image-preview');
        var $removeBtn = $card.find('.infoplus-remove-btn');
        var $termCard = $card.closest('.infoplus-term-image-card');
        
        // Create media frame
        mediaFrame = wp.media({
            title: 'Select Image',
            button: { text: 'Use this image' },
            multiple: false,
            library: { type: 'image' }
        });
        
        // On select
        mediaFrame.on('select', function() {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            var thumbUrl = attachment.sizes && attachment.sizes.thumbnail 
                ? attachment.sizes.thumbnail.url 
                : attachment.url;
            
            // Update input
            $input.val(attachment.id);
            
            // Update preview
            if ($preview.find('img').length) {
                $preview.find('img').attr('src', thumbUrl);
            } else {
                $preview.html('<img src="' + thumbUrl + '" alt="" />');
            }
            $preview.show();
            
            // Update button text
            $button.text('Change');
            
            // Show remove button
            $removeBtn.show();
            
            // Update card state
            $termCard.addClass('has-override');
            
            // Add badge if not exists
            if (!$termCard.find('.override-badge').length) {
                $termCard.find('.term-header').append('<span class="override-badge">Custom</span>');
            }
            
            // Hide global image if shown
            $termCard.find('.global-image').hide();
        });
        
        mediaFrame.open();
    });
    
    // Remove image
    $(document).on('click', '.infoplus-attr-images-wrap .infoplus-remove-btn', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $card = $button.closest('.product-image-upload');
        var $input = $card.find('.infoplus-image-id');
        var $preview = $card.find('.image-preview');
        var $uploadBtn = $card.find('.infoplus-upload-btn');
        var $termCard = $card.closest('.infoplus-term-image-card');
        
        // Clear input
        $input.val('');
        
        // Hide preview
        $preview.hide().find('img').attr('src', '');
        
        // Update button text
        $uploadBtn.text('Upload');
        
        // Hide remove button
        $button.hide();
        
        // Update card state
        $termCard.removeClass('has-override');
        
        // Remove badge
        $termCard.find('.override-badge').remove();
        
        // Show global image if exists
        $termCard.find('.global-image').show();
    });
});
