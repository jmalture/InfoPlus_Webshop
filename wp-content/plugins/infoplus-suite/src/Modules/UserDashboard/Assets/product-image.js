/**
 * Infoplus Product Image Widget JS
 * 
 * Handles zoom, lightbox, gallery navigation, and variation image updates.
 */

(function($, window) {
    'use strict';

    const ProductImage = {
        /**
         * Initialize all product image widgets on page
         */
        init: function() {
            $('.infoplus-product-image').each(function() {
                ProductImage.initWidget($(this));
            });
        },

        /**
         * Initialize a single widget instance
         */
        initWidget: function($widget) {
            const enableZoom = $widget.data('enable-zoom') === 'yes';
            const zoomScale = parseFloat($widget.data('zoom-scale')) || 2;
            const enableLightbox = $widget.data('enable-lightbox') === 'yes';
            
            // Store settings on widget
            $widget.data('settings', { enableZoom, zoomScale, enableLightbox });
            
            // Initialize zoom
            if (enableZoom) {
                this.initZoom($widget);
            }
            
            // Initialize lightbox
            if (enableLightbox) {
                this.initLightbox($widget);
            }
            
            // Initialize gallery
            this.initGallery($widget);
            
            // Listen for variation changes
            if (window.infoplusProductState) {
                window.infoplusProductState.on('variation:found', function(data) {
                    ProductImage.updateImage($widget, data.variation);
                });
                
                window.infoplusProductState.on('model:changed', function(data) {
                    console.log('[InfoplusProductImage] Model changed:', data.model);
                    
                    // Get model data from the global product data
                    if (window.infoplusProductData && window.infoplusProductData.models) {
                        const modelData = window.infoplusProductData.models.find(m => m.slug === data.model);
                        console.log('[InfoplusProductImage] Model data:', modelData);
                        
                        if (modelData && modelData.image_url) {
                            console.log('[InfoplusProductImage] Updating to model image:', modelData.image_url);
                            ProductImage.setMainImage($widget, modelData.image_url, modelData.image_url);
                        }
                    }
                });
            }
        },

        /**
         * Initialize zoom functionality
         */
        initZoom: function($widget) {
            const $main = $widget.find('.infoplus-product-image__main');
            const $img = $main.find('.infoplus-product-image__img');
            const $lens = $main.find('.infoplus-product-image__zoom-lens');
            const settings = $widget.data('settings');
            
            if (!$lens.length) return;
            
            // Set initial background
            const fullSrc = $main.data('full-src') || $img.attr('src');
            $lens.css({
                'background-image': `url(${fullSrc})`,
                'background-size': `${settings.zoomScale * 100}%`
            });
            
            // Mouse move handler
            $main.on('mousemove', function(e) {
                const rect = this.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                // Calculate percentage position
                const xPercent = (x / rect.width) * 100;
                const yPercent = (y / rect.height) * 100;
                
                // Update lens background position
                $lens.css('background-position', `${xPercent}% ${yPercent}%`);
            });
            
            // Mouse enter/leave
            $main.on('mouseenter', function() {
                $lens.css('opacity', 1);
                $img.css('opacity', 0);
            });
            
            $main.on('mouseleave', function() {
                $lens.css('opacity', 0);
                $img.css('opacity', 1);
            });
        },

        /**
         * Initialize lightbox functionality
         */
        initLightbox: function($widget) {
            const $main = $widget.find('.infoplus-product-image__main');
            const $fullscreenBtn = $widget.find('.infoplus-product-image__fullscreen');
            
            // Build gallery array for lightbox
            const buildGallery = function() {
                const gallery = [];
                
                // Main image
                const mainSrc = $main.data('full-src') || $main.find('img').attr('src');
                if (mainSrc) gallery.push(mainSrc);
                
                // Gallery images
                $widget.find('.infoplus-product-image__thumb').each(function() {
                    const src = $(this).data('full-url');
                    if (src && !gallery.includes(src)) {
                        gallery.push(src);
                    }
                });
                
                return gallery;
            };
            
            // Open lightbox
            const openLightbox = function(startIndex) {
                const gallery = buildGallery();
                if (!gallery.length) return;
                
                let currentIndex = startIndex || 0;
                
                let $lightbox = $widget.find('.infoplus-lightbox');
                if (!$lightbox.length) {
                    $lightbox = $widget.next('.infoplus-lightbox');
                }
                
                if (!$lightbox.length) return;
                
                const $lightboxImg = $lightbox.find('.infoplus-lightbox__image');
                
                const showImage = function(index) {
                    currentIndex = index;
                    if (currentIndex < 0) currentIndex = gallery.length - 1;
                    if (currentIndex >= gallery.length) currentIndex = 0;
                    
                    $lightboxImg.attr('src', gallery[currentIndex]);
                    
                    // Show/hide nav based on gallery size
                    $lightbox.find('.infoplus-lightbox__nav').toggle(gallery.length > 1);
                };
                
                // Show lightbox
                $lightbox.fadeIn(200);
                $('body').addClass('infoplus-lightbox-open');
                showImage(currentIndex);
                
                // Navigation
                $lightbox.find('.infoplus-lightbox__prev').off('click').on('click', function() {
                    showImage(currentIndex - 1);
                });
                
                $lightbox.find('.infoplus-lightbox__next').off('click').on('click', function() {
                    showImage(currentIndex + 1);
                });
                
                // Close handlers
                $lightbox.find('.infoplus-lightbox__close, .infoplus-lightbox__backdrop').off('click').on('click', function() {
                    $lightbox.fadeOut(200);
                    $('body').removeClass('infoplus-lightbox-open');
                });
                
                // Keyboard navigation
                $(document).off('keydown.lightbox').on('keydown.lightbox', function(e) {
                    if (e.key === 'Escape') {
                        $lightbox.fadeOut(200);
                        $('body').removeClass('infoplus-lightbox-open');
                        $(document).off('keydown.lightbox');
                    } else if (e.key === 'ArrowLeft') {
                        showImage(currentIndex - 1);
                    } else if (e.key === 'ArrowRight') {
                        showImage(currentIndex + 1);
                    }
                });
            };
            
            // Fullscreen button click
            $fullscreenBtn.on('click', function(e) {
                e.stopPropagation();
                openLightbox(0);
            });
            
            // Main image click (optional - can also open lightbox)
            $main.on('click', function(e) {
                // Only if not clicking fullscreen button
                if (!$(e.target).closest('.infoplus-product-image__fullscreen').length) {
                    openLightbox(0);
                }
            });
        },

        /**
         * Initialize gallery thumbnails
         */
        initGallery: function($widget) {
            const self = this;
            
            $widget.on('click', '.infoplus-product-image__thumb', function() {
                const $thumb = $(this);
                const imageUrl = $thumb.data('image-url');
                const fullUrl = $thumb.data('full-url');
                
                // Update active state
                $widget.find('.infoplus-product-image__thumb').removeClass('is-active');
                $thumb.addClass('is-active');
                
                // Update main image
                self.setMainImage($widget, imageUrl, fullUrl);
            });
        },

        /**
         * Set main image
         */
        setMainImage: function($widget, imageUrl, fullUrl) {
            const $main = $widget.find('.infoplus-product-image__main');
            const $img = $main.find('.infoplus-product-image__img');
            const $lens = $main.find('.infoplus-product-image__zoom-lens');
            const settings = $widget.data('settings') || {};
            
            // Update image with fade
            $img.fadeOut(150, function() {
                $img.attr('src', imageUrl).fadeIn(150);
            });
            
            // Update full src for lightbox
            $main.data('full-src', fullUrl || imageUrl);
            
            // Update zoom lens background
            if ($lens.length && settings.enableZoom) {
                $lens.css('background-image', `url(${fullUrl || imageUrl})`);
            }
        },

        /**
         * Update image from variation data
         */
        updateImage: function($widget, variation) {
            if (!variation) return;
            
            // Check for variation image
            let imageUrl = null;
            let fullUrl = null;
            
            if (variation.image && variation.image.url) {
                imageUrl = variation.image.url;
                fullUrl = variation.image.full_src || variation.image.url;
            } else if (variation.infoplus_model_image_url) {
                imageUrl = variation.infoplus_model_image_url;
                fullUrl = variation.infoplus_model_image_url;
            }
            
            if (imageUrl) {
                this.setMainImage($widget, imageUrl, fullUrl);
                
                // Also update gallery active state if image matches a thumbnail
                $widget.find('.infoplus-product-image__thumb').each(function() {
                    const $thumb = $(this);
                    const thumbUrl = $thumb.data('image-url');
                    
                    if (thumbUrl === imageUrl) {
                        $widget.find('.infoplus-product-image__thumb').removeClass('is-active');
                        $thumb.addClass('is-active');
                        return false; // break loop
                    }
                });
            }
        }
    };

    // Initialize on document ready
    $(function() {
        ProductImage.init();
    });

    // Re-initialize for Elementor editor
    $(window).on('elementor/frontend/init', function() {
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            elementorFrontend.hooks.addAction('frontend/element_ready/infoplus-product-image.default', function($element) {
                ProductImage.initWidget($element.find('.infoplus-product-image'));
            });
        }
    });

})(jQuery, window);
