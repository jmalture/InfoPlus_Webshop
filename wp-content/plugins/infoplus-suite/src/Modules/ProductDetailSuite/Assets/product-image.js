/**
 * Infoplus Product Image Widget JS
 * 
 * Handles zoom, lightbox, gallery navigation, and variation image updates.
 */

(function($, window) {
    'use strict';

    const ProductImage = {
        // Track if we should skip the next variation:found event (because model just changed)
        skipNextVariationUpdate: false,
        // Store the current model image URL to prevent overwriting
        currentModelImageUrl: null,
        // Prevent double-fade when the same image URL is requested twice in rapid succession
        _pendingImageUrl: null,

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
                // Model changed — update main image and rebuild gallery thumbnails
                window.infoplusProductState.on('model:changed', function(data) {
                    console.log('[InfoplusProductImage] Model changed:', data.model, 'syncImage:', data.syncImage);

                    // Only update images if sync is enabled (from Model Selector widget setting)
                    if (!data.syncImage) {
                        console.log('[InfoplusProductImage] Image sync disabled, skipping');
                        ProductImage.currentModelImageUrl = null;
                        return;
                    }

                    // Set flag to skip the next variation:found event
                    // (because it fires right after model:changed and would overwrite our image)
                    ProductImage.skipNextVariationUpdate = true;

                    // Get model data from the global product data
                    if (window.infoplusProductData && window.infoplusProductData.models) {
                        var modelData = null;
                        var models = window.infoplusProductData.models;
                        for (var i = 0; i < models.length; i++) {
                            if (models[i].slug === data.model) {
                                modelData = models[i];
                                break;
                            }
                        }
                        console.log('[InfoplusProductImage] Model data:', modelData);

                        if (modelData) {
                            var gallery = modelData.gallery || [];
                            var mainUrl = gallery.length > 0 ? gallery[0].url : (modelData.image_url || '');

                            if (mainUrl) {
                                console.log('[InfoplusProductImage] Updating to gallery main image:', mainUrl);
                                ProductImage.currentModelImageUrl = mainUrl;
                                ProductImage.setMainImage($widget, mainUrl, mainUrl);
                            } else {
                                ProductImage.currentModelImageUrl = null;
                            }

                            // Rebuild gallery thumbnail row from gallery[1..n]
                            ProductImage.buildModelGallery($widget, gallery);
                        }
                    }
                });

                // Variation found - only update if we're not skipping due to model change
                window.infoplusProductState.on('variation:found', function(data) {
                    // Check if we should skip this update (model just changed)
                    if (ProductImage.skipNextVariationUpdate) {
                        console.log('[InfoplusProductImage] Skipping variation update (model just changed)');
                        ProductImage.skipNextVariationUpdate = false;
                        return;
                    }
                    
                    // If we have a current model image and sync is active, don't override with variation image
                    if (ProductImage.currentModelImageUrl) {
                        console.log('[InfoplusProductImage] Keeping model image, not updating from variation');
                        return;
                    }
                    
                    ProductImage.updateImage($widget, data.variation);
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
         * Initialize gallery thumbnails.
         * Thumbnail clicks swap the main image (preview only — no model change).
         */
        initGallery: function($widget) {
            var self = this;

            $widget.on('click', '.infoplus-product-image__thumb', function() {
                var $thumb = $(this);
                var imageUrl = $thumb.data('image-url');
                var fullUrl = $thumb.data('full-url');

                // Update active state (local preview, no model selection change)
                $widget.find('.infoplus-product-image__thumb').removeClass('is-active');
                $thumb.addClass('is-active');

                // Swap main image
                self.setMainImage($widget, imageUrl, fullUrl);
            });
        },

        /**
         * Rebuild the gallery thumbnail row from a model's gallery array.
         * Shows gallery[1..n] as thumbnails; hides the row when gallery has ≤1 image.
         *
         * @param {jQuery} $widget
         * @param {Array}  gallery  Full gallery array from infoplusProductData.models[n].gallery
         */
        buildModelGallery: function($widget, gallery) {
            var $galleryEl = $widget.find('.infoplus-product-image__gallery');
            if (!$galleryEl.length) return;

            // Remove all existing model-managed thumbnails
            $galleryEl.find('.infoplus-product-image__thumb').remove();

            // gallery[1..n] become thumbnails (gallery[0] is the main image)
            var thumbs = gallery ? gallery.slice(1) : [];

            if (thumbs.length === 0) {
                $galleryEl.hide();
                return;
            }

            $galleryEl.show();

            $.each(thumbs, function(i, img) {
                var $btn = $('<button type="button" class="infoplus-product-image__thumb"></button>')
                    .attr('data-image-url', img.url)
                    .attr('data-full-url', img.url);
                $('<img loading="lazy" alt="" />')
                    .attr('src', img.thumbnail_url || img.url)
                    .appendTo($btn);
                $galleryEl.append($btn);
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

            console.log('[InfoplusProductImage] setMainImage called with:', imageUrl);

            // Guard: skip if this URL is already being animated in (prevents double-fade
            // when gallery click fires setModel which re-triggers this via model:changed)
            if (ProductImage._pendingImageUrl === imageUrl) {
                console.log('[InfoplusProductImage] Skipping duplicate setMainImage for:', imageUrl);
                return;
            }

            // Stop any ongoing animations to prevent race conditions
            $img.stop(true, true);

            // Track the pending URL until the animation completes
            ProductImage._pendingImageUrl = imageUrl;

            // Update image with fade
            $img.fadeOut(150, function() {
                ProductImage._pendingImageUrl = null;
                $(this).attr('src', imageUrl).fadeIn(150);
                console.log('[InfoplusProductImage] Image src updated to:', imageUrl);
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
        if (window.elementorFrontend) {
            elementorFrontend.hooks.addAction('frontend/element_ready/infoplus-product-image.default', function($element) {
                ProductImage.initWidget($element.find('.infoplus-product-image'));
            });
        }
    });

})(jQuery, window);
