/**
 * Infoplus Variation Dropdowns Widget JS
 * 
 * Handles custom dropdown interactions, filtering, and state sync.
 */

(function ($, window) {
    'use strict';

    // Flag to prevent document click from firing right after trigger click
    var justOpened = false;

    const VariationDropdowns = {
        initialized: false,

        /**
         * Initialize all dropdown widgets on page
         */
        init: function () {
            if (this.initialized) {
                console.log('[InfoplusDropdowns] Already initialized, skipping');
                return;
            }

            console.log('[InfoplusDropdowns] Initializing...');

            // Initialize each widget
            $('.infoplus-variation-dropdowns').each(function () {
                VariationDropdowns.initWidget($(this));
            });

            // Set up document-level click handler ONCE
            $(document).off('click.infoplusDropdown');
            $(document).on('click.infoplusDropdown', function (e) {
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
        initWidget: function ($widget) {
            // Check if already initialized
            if ($widget.data('infoplus-initialized')) {
                console.log('[InfoplusDropdowns] Widget already initialized, skipping');
                return;
            }

            console.log('[InfoplusDropdowns] Initializing widget');

            const showInfo = $widget.data('show-info') === 'yes';
            const infoBehavior = $widget.data('info-behavior') || 'tooltip';

            // Dropdown trigger click - use mousedown to beat the document click
            $widget.find('.infoplus-dropdown__trigger').on('mousedown', function (e) {
                // Don't trigger dropdown if clicking on the info button
                if ($(e.target).closest('.infoplus-dropdown__selected-info').length) {
                    return;
                }

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
                    setTimeout(function () {
                        justOpened = false;
                    }, 100);
                }
            });

            // Prevent click from bubbling (backup)
            $widget.find('.infoplus-dropdown__trigger').on('click', function (e) {
                // Don't prevent default if clicking on the info button
                if ($(e.target).closest('.infoplus-dropdown__selected-info').length) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
            });

            // Keyboard navigation on trigger
            $widget.find('.infoplus-dropdown__trigger').on('keydown', function (e) {
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
            $widget.find('.infoplus-dropdown__option').on('click', function (e) {
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
            $widget.find('.infoplus-dropdown__option').on('keydown', function (e) {
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

            // Info button behavior
            if (showInfo) {
                if (infoBehavior === 'modal') {
                    // Modal mode: show modal on click (option info buttons)
                    $widget.find('.infoplus-dropdown__option-info').on('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const $option = $(this).closest('.infoplus-dropdown__option');
                        VariationDropdowns.showInfoModal($option);
                    });

                    // Modal mode: show modal on click (selected info button)
                    $widget.find('.infoplus-dropdown__selected-info').on('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        VariationDropdowns.showInfoModalFromButton($(this));
                    });
                } else if (infoBehavior === 'tooltip') {
                    // Tooltip mode: prevent click from selecting option
                    $widget.find('.infoplus-dropdown__option-info').on('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                    });

                    // Show tooltip on hover (option info buttons)
                    $widget.find('.infoplus-dropdown__option-info').on('mouseenter', function () {
                        const tooltip = $(this).data('tooltip') || $(this).closest('.infoplus-dropdown__option').data('tooltip');
                        if (tooltip) {
                            VariationDropdowns.showTooltip($(this), tooltip);
                        }
                    });

                    $widget.find('.infoplus-dropdown__option-info').on('mouseleave', function () {
                        VariationDropdowns.hideTooltip();
                    });

                    // Show tooltip on hover (selected info button)
                    $widget.find('.infoplus-dropdown__selected-info').on('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                    });

                    $widget.find('.infoplus-dropdown__selected-info').on('mouseenter', function () {
                        const tooltip = $(this).data('tooltip');
                        if (tooltip) {
                            VariationDropdowns.showTooltip($(this), tooltip);
                        }
                    });

                    $widget.find('.infoplus-dropdown__selected-info').on('mouseleave', function () {
                        VariationDropdowns.hideTooltip();
                    });
                }
            }

            // Detect per-dropdown image availability and set data-has-images attribute
            VariationDropdowns.detectDropdownImages($widget);

            // Listen for state changes
            if (window.infoplusProductState) {
                window.infoplusProductState.on('model:changed', function (data) {
                    var isRequestable = VariationDropdowns.isModelRequestable(data.model);
                    if (isRequestable) {
                        VariationDropdowns.lockForRequestable($widget, true);
                        VariationDropdowns.detectDropdownImages($widget);
                        // Do NOT reset or update selections — keep them visible under overlay
                    } else {
                        VariationDropdowns.lockForRequestable($widget, false);
                        VariationDropdowns.updateAvailableOptions($widget, data.availableAttributes);
                        VariationDropdowns.resetSelections($widget);
                        VariationDropdowns.detectDropdownImages($widget);
                    }
                    VariationDropdowns.pulseAnfrageButton(true);
                });

                window.infoplusProductState.on('attribute:changed', function (data) {
                    VariationDropdowns.updateAvailableOptions($widget, data.availableAttributes);
                });
            }

            // Mark as initialized
            $widget.data('infoplus-initialized', true);

            // Replay current state if product-state.js already fired events before this widget loaded
            VariationDropdowns.syncWithCurrentState($widget);
        },

        /**
         * Toggle dropdown open/close
         */
        toggleDropdown: function ($dropdown) {
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
        openDropdown: function ($dropdown) {
            console.log('[InfoplusDropdowns] Opening dropdown');
            $dropdown.addClass('is-open');
            $dropdown.find('.infoplus-dropdown__trigger').attr('aria-expanded', 'true');
            $dropdown.find('.infoplus-dropdown__options').stop(true, true).slideDown(200);
        },

        /**
         * Close dropdown
         */
        closeDropdown: function ($dropdown) {
            console.log('[InfoplusDropdowns] Closing dropdown');
            $dropdown.removeClass('is-open');
            $dropdown.find('.infoplus-dropdown__trigger').attr('aria-expanded', 'false');
            $dropdown.find('.infoplus-dropdown__options').stop(true, true).slideUp(150);
        },

        /**
         * Close all dropdowns
         */
        closeAllDropdowns: function () {
            var self = this;
            $('.infoplus-dropdown.is-open').each(function () {
                self.closeDropdown($(this));
            });
        },

        /**
         * Select an option
         */
        selectOption: function ($option) {
            const $dropdown = $option.closest('.infoplus-dropdown');
            const attribute = $dropdown.data('attribute');
            const value = $option.data('value');
            const text = $option.find('.infoplus-dropdown__option-text').text();
            const $image = $option.find('.infoplus-dropdown__option-image');
            const imageSrc = $image.length ? $image.attr('src') : null;
            const tooltip = $option.data('tooltip');
            const modalContent = $option.data('modal-content');

            console.log('[InfoplusDropdowns] Selecting option:', value);

            // Update visual state
            $dropdown.find('.infoplus-dropdown__option').removeClass('is-selected');
            $option.addClass('is-selected');
            $dropdown.addClass('has-selection');

            // Update trigger display
            const $selected = $dropdown.find('.infoplus-dropdown__selected');
            $selected.find('.infoplus-dropdown__selected-text').text(text);

            // Update image
            if (imageSrc) {
                let $selectedImage = $selected.find('img.infoplus-dropdown__selected-image');
                if ($selectedImage.length) {
                    $selectedImage.attr('src', imageSrc);
                }
            } else {
                $selected.find('.infoplus-dropdown__selected-image').attr('src', '');
            }

            // Store tooltip/modal data on the info button for later use
            const $selectedInfo = $selected.find('.infoplus-dropdown__selected-info');
            if ($selectedInfo.length) {
                $selectedInfo.data('tooltip', tooltip || '');
                $selectedInfo.data('modal-content', modalContent || '');
                $selectedInfo.data('option-text', text);
                $selectedInfo.data('option-image', imageSrc || '');
                
                // Show info button ONLY if there's modal content (not just tooltip)
                const hasModalContent = !!(modalContent && modalContent.trim());
                if (hasModalContent) {
                    $dropdown.addClass('has-info');
                } else {
                    $dropdown.removeClass('has-info');
                }
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
        updateAvailableOptions: function ($widget, availableAttributes) {
            if (!availableAttributes) return;

            $widget.find('.infoplus-dropdown').each(function () {
                const $dropdown = $(this);
                const attribute = $dropdown.data('attribute');
                const available = availableAttributes[attribute] || [];

                $dropdown.find('.infoplus-dropdown__option').each(function () {
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
        resetSelections: function ($widget) {
            const placeholder = $widget.data('placeholder') || window.infoplusProductConfig?.i18n?.selectOption || 'Wähle eine Option';

            $widget.find('.infoplus-dropdown').each(function () {
                const $dropdown = $(this);
                const $selected = $dropdown.find('.infoplus-dropdown__selected');

                // Reset visual state
                $dropdown.find('.infoplus-dropdown__option').removeClass('is-selected');
                $dropdown.removeClass('has-selection has-info');
                $dropdown.find('.infoplus-dropdown__selected-text').text(placeholder);
                $dropdown.find('.infoplus-dropdown__native').val('');

                // Clear image src (CSS will hide it when empty)
                $selected.find('.infoplus-dropdown__selected-image').attr('src', '');

                // Clear info button data
                const $selectedInfo = $selected.find('.infoplus-dropdown__selected-info');
                if ($selectedInfo.length) {
                    $selectedInfo.removeData('tooltip modal-content option-text option-image');
                }
            });
        },

        /**
         * Show info modal
         */
        showInfoModal: function ($option) {
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
            $modal.off('click.closeModal').on('click.closeModal', '.infoplus-info-modal__close, .infoplus-info-modal__backdrop', function () {
                VariationDropdowns.hideInfoModal();
            });

            $(document).off('keydown.infoModal').on('keydown.infoModal', function (e) {
                if (e.key === 'Escape') {
                    VariationDropdowns.hideInfoModal();
                }
            });
        },

        /**
         * Hide info modal
         */
        hideInfoModal: function () {
            $('.infoplus-info-modal').fadeOut(200);
            $('body').removeClass('infoplus-modal-open');
            $(document).off('keydown.infoModal');
        },

        /**
         * Show info modal from selected info button (uses stored data)
         */
        showInfoModalFromButton: function ($button) {
            const text = $button.data('option-text') || '';
            const tooltip = $button.data('tooltip') || '';
            const modalContent = $button.data('modal-content') || '';
            const imageSrc = $button.data('option-image') || null;

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
            $modal.off('click.closeModal').on('click.closeModal', '.infoplus-info-modal__close, .infoplus-info-modal__backdrop', function () {
                VariationDropdowns.hideInfoModal();
            });

            $(document).off('keydown.infoModal').on('keydown.infoModal', function (e) {
                if (e.key === 'Escape') {
                    VariationDropdowns.hideInfoModal();
                }
            });
        },

        /**
         * Show tooltip near an element
         */
        showTooltip: function ($element, text) {
            // Create tooltip if doesn't exist
            let $tooltip = $('.infoplus-tooltip');
            if (!$tooltip.length) {
                $tooltip = $('<div class="infoplus-tooltip"></div>');
                $('body').append($tooltip);
            }

            // Set text and show
            $tooltip.text(text);

            // Position tooltip above the element
            const rect = $element[0].getBoundingClientRect();
            const tooltipWidth = $tooltip.outerWidth();
            const tooltipHeight = $tooltip.outerHeight();

            let left = rect.left + (rect.width / 2) - (tooltipWidth / 2);
            let top = rect.top - tooltipHeight - 10;

            // Keep tooltip within viewport
            if (left < 10) left = 10;
            if (left + tooltipWidth > window.innerWidth - 10) {
                left = window.innerWidth - tooltipWidth - 10;
            }
            if (top < 10) {
                // Show below if not enough space above
                top = rect.bottom + 10;
                $tooltip.addClass('is-below');
            } else {
                $tooltip.removeClass('is-below');
            }

            $tooltip.css({ left: left, top: top }).addClass('is-visible');
        },

        /**
         * Hide tooltip
         */
        hideTooltip: function () {
            $('.infoplus-tooltip').removeClass('is-visible');
        },

        /**
         * Detect per-dropdown image availability and set data-has-images attribute.
         * Checks window.infoplusProductData.attributes for each dropdown's attribute.
         * Dropdowns with at least one term image get data-has-images="yes" (taller height).
         * Dropdowns with no term images get data-has-images="no" (native height).
         */
        detectDropdownImages: function ($widget) {
            $widget.find('.infoplus-dropdown').each(function () {
                var $dropdown = $(this);
                var hasImages = $dropdown.find('.infoplus-dropdown__option-image').length > 0;
                $dropdown.attr('data-has-images', hasImages ? 'yes' : 'no');
            });
        },

        /**
         * Apply an option's selection visually to its dropdown without triggering
         * infoplusProductState.setAttribute (no event re-fire).
         * Used by syncWithCurrentState to replay already-set state on late init.
         */
        applyOptionToUI: function ($dropdown, $option) {
            var text         = $option.find('.infoplus-dropdown__option-text').text();
            var $img         = $option.find('.infoplus-dropdown__option-image');
            var imageSrc     = $img.length ? $img.attr('src') : null;
            var tooltip      = $option.data('tooltip');
            var modalContent = $option.data('modal-content');

            $dropdown.find('.infoplus-dropdown__option').removeClass('is-selected');
            $option.addClass('is-selected');
            $dropdown.addClass('has-selection');

            var $selected = $dropdown.find('.infoplus-dropdown__selected');
            $selected.find('.infoplus-dropdown__selected-text').text(text);

            if (imageSrc) {
                var $selImg = $selected.find('img.infoplus-dropdown__selected-image');
                if ($selImg.length) { $selImg.attr('src', imageSrc); }
            } else {
                $selected.find('.infoplus-dropdown__selected-image').attr('src', '');
            }

            var $selInfo = $selected.find('.infoplus-dropdown__selected-info');
            if ($selInfo.length) {
                $selInfo.data('tooltip',       tooltip      || '');
                $selInfo.data('modal-content', modalContent || '');
                $selInfo.data('option-text',   text);
                $selInfo.data('option-image',  imageSrc || '');
                var hasModal = !!(modalContent && modalContent.trim());
                if (hasModal) { $dropdown.addClass('has-info'); }
                else          { $dropdown.removeClass('has-info'); }
            }

            $dropdown.find('.infoplus-dropdown__native').val($option.data('value'));
        },

        /**
         * Return true if the given model slug has order_type === 'requestable'
         */
        isModelRequestable: function (modelSlug) {
            if (!modelSlug || !window.infoplusProductData || !window.infoplusProductData.models) return false;
            for (var i = 0; i < window.infoplusProductData.models.length; i++) {
                if (window.infoplusProductData.models[i].slug === modelSlug) {
                    return window.infoplusProductData.models[i].order_type === 'requestable';
                }
            }
            return false;
        },

        /**
         * Show or hide the frosted-glass overlay that blocks variation dropdowns
         * for a requestable model.
         */
        lockForRequestable: function ($widget, locked) {
            if (locked) {
                $widget.addClass('is-requestable-locked');
                if (!$widget.find('.infoplus-dropdowns-lock').length) {
                    var $overlay = $('<div class="infoplus-dropdowns-lock">' +
                        '<span class="infoplus-dropdowns-lock__message">Dieses Modell ist nicht konfigurierbar</span>' +
                        '</div>');
                    $overlay.on('click', function () {
                        VariationDropdowns.pulseAnfrageButton();
                    });
                    $widget.append($overlay);
                } else {
                    $widget.find('.infoplus-dropdowns-lock').show();
                }
            } else {
                $widget.removeClass('is-requestable-locked');
                $widget.find('.infoplus-dropdowns-lock').hide();
            }
        },

        /**
         * Pulse the Anfrage button with a brand-pink glow to draw attention.
         * @param {boolean} [long] - true for a 3-second repeating pulse (model selected),
         *                           false/omitted for a single short pulse (overlay click).
         */
        pulseAnfrageButton: function (long) {
            var $anfrage = $('#anfrage .elementor-button, .infoplus-btn--anfrage');
            if (!$anfrage.length) return;
            var cls = long ? 'infoplus-anfrage-pulse--long' : 'infoplus-anfrage-pulse';
            $anfrage.removeClass('infoplus-anfrage-pulse infoplus-anfrage-pulse--long');
            $anfrage.each(function () { void this.offsetWidth; }); // restart animation
            $anfrage.addClass(cls);
            setTimeout(function () {
                $anfrage.removeClass(cls);
            }, long ? 3100 : 800);
        },

        /**
         * Sync the widget's visual state from infoplusProductState without re-emitting events.
         * Called once at the end of initWidget to handle the case where product-state.js
         * already fired all events before this widget's $(document).ready ran.
         */
        syncWithCurrentState: function ($widget) {
            var ps = window.infoplusProductState;
            if (!ps) return;

            // If the currently active model is requestable, lock and bail out
            if (ps.selectedModel && VariationDropdowns.isModelRequestable(ps.selectedModel)) {
                VariationDropdowns.lockForRequestable($widget, true);
                return; // don't apply dropdown selections
            }

            // Re-apply available-option filtering based on current selections
            if (ps.availableAttributes && Object.keys(ps.availableAttributes).length) {
                VariationDropdowns.updateAvailableOptions($widget, ps.availableAttributes);
            }

            // Silently mark the currently selected value in each dropdown
            var selections = ps.selectedAttributes || {};
            $widget.find('.infoplus-dropdown').each(function () {
                var $dropdown = $(this);
                var attribute = $dropdown.data('attribute'); // e.g. 'pa_format'
                var value     = selections[attribute];
                if (!value) return;

                var $option = $dropdown.find(
                    '.infoplus-dropdown__option[data-value="' + value + '"]'
                );
                if ($option.length) {
                    VariationDropdowns.applyOptionToUI($dropdown, $option);
                }
            });
        }
    };

    // Initialize on document ready
    $(function () {
        VariationDropdowns.init();
    });

    // Re-initialize for Elementor editor
    $(window).on('elementor/frontend/init', function () {
        if (window.elementorFrontend) {
            elementorFrontend.hooks.addAction('frontend/element_ready/infoplus-variation-dropdowns.default', function ($element) {
                VariationDropdowns.initWidget($element.find('.infoplus-variation-dropdowns'));
            });
        }
    });

    // Expose for debugging
    window.InfoplusDropdowns = VariationDropdowns;

})(jQuery, window);
