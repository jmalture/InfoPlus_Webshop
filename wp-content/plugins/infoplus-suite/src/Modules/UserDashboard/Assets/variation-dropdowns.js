/**
 * Infoplus Variation Dropdowns Widget JS
 * 
 * Handles custom dropdown interactions, filtering, and state sync.
 */

(function($, window) {
    'use strict';
    
    // Flag to prevent document click from firing right after trigger click
    var justOpened = false;

    const VariationDropdowns = {
        initialized: false,
        
        /**
         * Initialize all dropdown widgets on page
         */
        init: function() {
            if (this.initialized) {
                console.log('[InfoplusDropdowns] Already initialized, skipping');
                return;
            }
            
            console.log('[InfoplusDropdowns] Initializing...');
            
            // Initialize each widget
            $('.infoplus-variation-dropdowns').each(function() {
                VariationDropdowns.initWidget($(this));
            });
            
            // Set up document-level click handler ONCE
            $(document).off('click.infoplusDropdown');
            $(document).on('click.infoplusDropdown', function(e) {
                // If we just opened a dropdown, ignore this click
                if (justOpened) {
                    console.log('[InfoplusDropdowns] Ignoring document click - just opened');
                    justOpened = false;
                    return;
                }
                
                // Don't close if clicking inside a dropdown
                if ($(e.target).closest('.infoplus-dropdown').length) {
                    console.log('[InfoplusDropdowns] Click inside dropdown, not closing');
                    return;
                }
                
                console.log('[InfoplusDropdowns] Document click - closing all dropdowns');
                VariationDropdowns.closeAllDropdowns();
            });
            
            this.initialized = true;
            console.log('[InfoplusDropdowns] Initialization complete');
        },

        /**
         * Initialize a single widget instance
         */
        initWidget: function($widget) {
            // Check if already initialized
            if ($widget.data('infoplus-initialized')) {
                console.log('[InfoplusDropdowns] Widget already initialized, skipping');
                return;
            }
            
            console.log('[InfoplusDropdowns] Initializing widget');
            
            const showImages = $widget.data('show-images') === 'yes';
            const showInfo = $widget.data('show-info') === 'yes';
            const infoBehavior = $widget.data('info-behavior') || 'tooltip';
            
            // Dropdown trigger click - use mousedown to beat the document click
            $widget.find('.infoplus-dropdown__trigger').on('mousedown', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $trigger = $(this);
                var $dropdown = $trigger.closest('.infoplus-dropdown');
                var isCurrentlyOpen = $dropdown.hasClass('is-open');
                
                console.log('[InfoplusDropdowns] Trigger mousedown, isOpen:', isCurrentlyOpen);
                
                // Close all other dropdowns first
                VariationDropdowns.closeAllDropdowns();
                
                // Toggle this one
                if (!isCurrentlyOpen) {
                    justOpened = true;
                    VariationDropdowns.openDropdown($dropdown);
                    
                    // Reset the flag after a short delay
                    setTimeout(function() {
                        justOpened = false;
                    }, 100);
                }
            });
            
            // Prevent click from bubbling (backup)
            $widget.find('.infoplus-dropdown__trigger').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
            
            // Keyboard navigation on trigger
            $widget.find('.infoplus-dropdown__trigger').on('keydown', function(e) {
                const $dropdown = $(this).closest('.infoplus-dropdown');
                
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    VariationDropdowns.toggleDropdown($dropdown);
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    VariationDropdowns.openDropdown($dropdown);
                    $dropdown.find('.infoplus-dropdown__option:visible:first').focus();
                } else if (e.key === 'Escape') {
                    VariationDropdowns.closeDropdown($dropdown);
                }
            });
            
            // Option click
            $widget.find('.infoplus-dropdown__option').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Don't select if clicking info button
                if ($(e.target).closest('.infoplus-dropdown__option-info').length) {
                    return;
                }
                
                console.log('[InfoplusDropdowns] Option clicked');
                VariationDropdowns.selectOption($(this));
            });
            
            // Option keyboard navigation
            $widget.find('.infoplus-dropdown__option').on('keydown', function(e) {
                const $option = $(this);
                const $dropdown = $option.closest('.infoplus-dropdown');
                
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    VariationDropdowns.selectOption($option);
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    $option.nextAll('.infoplus-dropdown__option:visible:first').focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    const $prev = $option.prevAll('.infoplus-dropdown__option:visible:first');
                    if ($prev.length) {
                        $prev.focus();
                    } else {
                        $dropdown.find('.infoplus-dropdown__trigger').focus();
                    }
                } else if (e.key === 'Escape') {
                    VariationDropdowns.closeDropdown($dropdown);
                    $dropdown.find('.infoplus-dropdown__trigger').focus();
                }
            });
            
            // Info button click (for modal behavior)
            if (infoBehavior === 'modal') {
                $widget.find('.infoplus-dropdown__option-info').on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const $option = $(this).closest('.infoplus-dropdown__option');
                    VariationDropdowns.showInfoModal($option);
                });
            }
            
            // Listen for state changes
            if (window.infoplusProductState) {
                window.infoplusProductState.on('model:changed', function(data) {
                    VariationDropdowns.updateAvailableOptions($widget, data.availableAttributes);
                    VariationDropdowns.resetSelections($widget);
                });
                
                window.infoplusProductState.on('attribute:changed', function(data) {
                    VariationDropdowns.updateAvailableOptions($widget, data.availableAttributes);
                });
            }
            
            // Mark as initialized
            $widget.data('infoplus-initialized', true);
        },

        /**
         * Toggle dropdown open/close
         */
        toggleDropdown: function($dropdown) {
            if ($dropdown.hasClass('is-open')) {
                this.closeDropdown($dropdown);
            } else {
                this.closeAllDropdowns();
                this.openDropdown($dropdown);
            }
        },

        /**
         * Open dropdown
         */
        openDropdown: function($dropdown) {
            console.log('[InfoplusDropdowns] Opening dropdown');
            $dropdown.addClass('is-open');
            $dropdown.find('.infoplus-dropdown__trigger').attr('aria-expanded', 'true');
            $dropdown.find('.infoplus-dropdown__options').stop(true, true).slideDown(200);
        },

        /**
         * Close dropdown
         */
        closeDropdown: function($dropdown) {
            console.log('[InfoplusDropdowns] Closing dropdown');
            $dropdown.removeClass('is-open');
            $dropdown.find('.infoplus-dropdown__trigger').attr('aria-expanded', 'false');
            $dropdown.find('.infoplus-dropdown__options').stop(true, true).slideUp(150);
        },

        /**
         * Close all dropdowns
         */
        closeAllDropdowns: function() {
            var self = this;
            $('.infoplus-dropdown.is-open').each(function() {
                self.closeDropdown($(this));
            });
        },

        /**
         * Select an option
         */
        selectOption: function($option) {
            const $dropdown = $option.closest('.infoplus-dropdown');
            const attribute = $dropdown.data('attribute');
            const value = $option.data('value');
            const text = $option.find('.infoplus-dropdown__option-text').text();
            const $image = $option.find('.infoplus-dropdown__option-image');
            const imageSrc = $image.length ? $image.attr('src') : null;
            
            console.log('[InfoplusDropdowns] Selecting option:', value);
            
            // Update visual state
            $dropdown.find('.infoplus-dropdown__option').removeClass('is-selected');
            $option.addClass('is-selected');
            
            // Update trigger display
            const $selected = $dropdown.find('.infoplus-dropdown__selected');
            $selected.find('.infoplus-dropdown__selected-text').text(text);
            
            if (imageSrc) {
                let $selectedImage = $selected.find('img.infoplus-dropdown__selected-image');
                
                // If no img element exists, create one (replace any span placeholder)
                if (!$selectedImage.length) {
                    // Remove any span placeholder
                    $selected.find('span.infoplus-dropdown__selected-image').remove();
                    
                    // Create proper img element
                    $selectedImage = $('<img class="infoplus-dropdown__selected-image" alt="" />');
                    $selected.prepend($selectedImage);
                }
                
                $selectedImage.attr('src', imageSrc).show();
            } else {
                $selected.find('.infoplus-dropdown__selected-image').hide();
            }
            
            // Update native select
            $dropdown.find('.infoplus-dropdown__native').val(value);
            
            // Close dropdown
            this.closeDropdown($dropdown);
            
            // Update global state
            if (window.infoplusProductState && attribute) {
                window.infoplusProductState.setAttribute(attribute, value);
            }
        },

        /**
         * Update available options based on state
         */
        updateAvailableOptions: function($widget, availableAttributes) {
            if (!availableAttributes) return;
            
            $widget.find('.infoplus-dropdown').each(function() {
                const $dropdown = $(this);
                const attribute = $dropdown.data('attribute');
                const available = availableAttributes[attribute] || [];
                
                $dropdown.find('.infoplus-dropdown__option').each(function() {
                    const $option = $(this);
                    const value = $option.data('value');
                    
                    if (available.length === 0 || available.includes(value)) {
                        $option.removeClass('is-disabled').show();
                    } else {
                        $option.addClass('is-disabled').hide();
                    }
                });
            });
        },

        /**
         * Reset all selections
         */
        resetSelections: function($widget) {
            const placeholder = window.infoplusProductConfig?.i18n?.selectOption || 'Choose an option';
            
            $widget.find('.infoplus-dropdown').each(function() {
                const $dropdown = $(this);
                
                // Reset visual state
                $dropdown.find('.infoplus-dropdown__option').removeClass('is-selected');
                $dropdown.find('.infoplus-dropdown__selected-text').text(placeholder);
                $dropdown.find('.infoplus-dropdown__selected-image').hide();
                $dropdown.find('.infoplus-dropdown__native').val('');
            });
        },

        /**
         * Show info modal
         */
        showInfoModal: function($option) {
            const text = $option.find('.infoplus-dropdown__option-text').text();
            const tooltip = $option.data('tooltip');
            const modalContent = $option.data('modal-content');
            const $image = $option.find('.infoplus-dropdown__option-image');
            const imageSrc = $image.length ? $image.attr('src') : null;
            
            // Find or create modal
            let $modal = $('.infoplus-info-modal');
            
            if (!$modal.length) {
                $modal = $(`
                    <div class="infoplus-info-modal" style="display: none;">
                        <div class="infoplus-info-modal__backdrop"></div>
                        <div class="infoplus-info-modal__content">
                            <button class="infoplus-info-modal__close" type="button">&times;</button>
                            <div class="infoplus-info-modal__image"></div>
                            <h4 class="infoplus-info-modal__title"></h4>
                            <div class="infoplus-info-modal__description"></div>
                        </div>
                    </div>
                `);
                $('body').append($modal);
            }
            
            // Populate modal
            if (imageSrc) {
                $modal.find('.infoplus-info-modal__image').html(`<img src="${imageSrc}" alt="${text}" />`).show();
            } else {
                $modal.find('.infoplus-info-modal__image').hide();
            }
            
            $modal.find('.infoplus-info-modal__title').text(text);
            $modal.find('.infoplus-info-modal__description').html(modalContent || tooltip || '');
            
            // Show modal
            $modal.fadeIn(200);
            $('body').addClass('infoplus-modal-open');
            
            // Bind close events
            $modal.off('click.closeModal').on('click.closeModal', '.infoplus-info-modal__close, .infoplus-info-modal__backdrop', function() {
                VariationDropdowns.hideInfoModal();
            });
            
            $(document).off('keydown.infoModal').on('keydown.infoModal', function(e) {
                if (e.key === 'Escape') {
                    VariationDropdowns.hideInfoModal();
                }
            });
        },

        /**
         * Hide info modal
         */
        hideInfoModal: function() {
            $('.infoplus-info-modal').fadeOut(200);
            $('body').removeClass('infoplus-modal-open');
            $(document).off('keydown.infoModal');
        }
    };

    // Initialize on document ready
    $(function() {
        VariationDropdowns.init();
    });

    // Re-initialize for Elementor editor
    $(window).on('elementor/frontend/init', function() {
        if (window.elementorFrontend) {
            elementorFrontend.hooks.addAction('frontend/element_ready/infoplus-variation-dropdowns.default', function($element) {
                VariationDropdowns.initWidget($element.find('.infoplus-variation-dropdowns'));
            });
        }
    });

    // Expose for debugging
    window.InfoplusDropdowns = VariationDropdowns;

})(jQuery, window);
