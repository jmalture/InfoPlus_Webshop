/**
 * Infoplus Variation Fields - Admin JS
 * 
 * Handles media uploader for model images in WooCommerce variation forms.
 */

(function($) {
    'use strict';

    const VariationFieldsAdmin = {
        mediaFrame: null,

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            // Upload button click
            $(document).on('click', '.infoplus-upload-image-btn', function(e) {
                e.preventDefault();
                self.openMediaUploader($(this));
            });

            // Remove button click
            $(document).on('click', '.infoplus-remove-image-btn', function(e) {
                e.preventDefault();
                self.removeImage($(this));
            });

            // Re-init when variations are loaded (WooCommerce AJAX)
            $(document).on('woocommerce_variations_loaded', function() {
                console.log('[InfoplusVariationFields] Variations loaded');
            });
        },

        /**
         * Open WordPress media uploader
         */
        openMediaUploader: function($button) {
            const self = this;
            const $wrapper = $button.closest('.infoplus-image-upload-wrapper');
            const $imageInput = $wrapper.find('.infoplus-model-image-id');
            const $preview = $wrapper.find('.infoplus-image-preview');
            const $removeBtn = $wrapper.find('.infoplus-remove-image-btn');

            // Create media frame if not exists
            if (this.mediaFrame) {
                this.mediaFrame.off('select');
            }

            this.mediaFrame = wp.media({
                title: infoplusVariationFieldsConfig.i18n.chooseImage || 'Choose Model Image',
                button: {
                    text: infoplusVariationFieldsConfig.i18n.useImage || 'Use this image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            // On select
            this.mediaFrame.on('select', function() {
                const attachment = self.mediaFrame.state().get('selection').first().toJSON();
                
                // Update hidden input
                $imageInput.val(attachment.id);
                
                // Update preview
                const thumbnailUrl = attachment.sizes && attachment.sizes.thumbnail 
                    ? attachment.sizes.thumbnail.url 
                    : attachment.url;
                
                $preview.find('img').attr('src', thumbnailUrl);
                $preview.show();
                
                // Update button text
                $button.text(infoplusVariationFieldsConfig.i18n.selectImage || 'Change Image');
                
                // Show remove button
                $removeBtn.show();
                
                // Mark variations as changed
                self.markVariationsChanged();
            });

            this.mediaFrame.open();
        },

        /**
         * Remove selected image
         */
        removeImage: function($button) {
            const $wrapper = $button.closest('.infoplus-image-upload-wrapper');
            const $imageInput = $wrapper.find('.infoplus-model-image-id');
            const $preview = $wrapper.find('.infoplus-image-preview');
            const $uploadBtn = $wrapper.find('.infoplus-upload-image-btn');

            // Clear input
            $imageInput.val('');
            
            // Hide preview
            $preview.hide().find('img').attr('src', '');
            
            // Update button text
            $uploadBtn.text(infoplusVariationFieldsConfig.i18n.selectImage || 'Upload Image');
            
            // Hide remove button
            $button.hide();
            
            // Mark variations as changed
            this.markVariationsChanged();
        },

        /**
         * Mark WooCommerce variations as changed (enables save button)
         */
        markVariationsChanged: function() {
            // Trigger WooCommerce's change detection
            $('#variable_product_options').trigger('woocommerce_variations_input_changed');
            
            // Also trigger on the form
            $('form#post').trigger('change');
        }
    };

    // Initialize on document ready
    $(function() {
        VariationFieldsAdmin.init();
    });

})(jQuery);
