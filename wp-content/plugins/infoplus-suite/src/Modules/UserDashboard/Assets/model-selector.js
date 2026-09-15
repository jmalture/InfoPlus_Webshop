/**
 * Infoplus Model Selector Widget JS
 * 
 * Handles model card interactions, selection, and info modals.
 */

(function($, window) {
    'use strict';

    const ModelSelector = {
        /**
         * Initialize all model selectors on page
         */
        init: function() {
            $('.infoplus-model-selector').each(function() {
                ModelSelector.initWidget($(this));
            });
        },

        /**
         * Initialize a single widget instance
         */
        initWidget: function($widget) {
            const infoBehavior = $widget.data('info-behavior') || 'modal';
            
            // Card click - select model
            $widget.on('click', '.infoplus-model-selector__card', function(e) {
                // Don't trigger if clicking info button
                if ($(e.target).closest('.infoplus-model-selector__info-btn').length) {
                    return;
                }
                
                ModelSelector.selectCard($widget, $(this));
            });
            
            // Info button click
            $widget.on('click', '.infoplus-model-selector__info-btn', function(e) {
                e.stopPropagation();
                
                const $card = $(this).closest('.infoplus-model-selector__card');
                const modelSlug = $card.data('model');
                
                if (infoBehavior === 'modal') {
                    ModelSelector.showInfoModal($card);
                }
            });
            
            // Info button hover for tooltip
            if (infoBehavior === 'tooltip') {
                $widget.on('mouseenter', '.infoplus-model-selector__info-btn', function() {
                    const $card = $(this).closest('.infoplus-model-selector__card');
                    const tooltip = $card.data('tooltip');
                    
                    if (tooltip) {
                        $(this).attr('data-tooltip', tooltip);
                    }
                });
            }
            
            // Listen for state changes
            window.infoplusProductState.on('model:changed', function(data) {
                ModelSelector.updateActiveCard($widget, data.model);
            });
            
            // Initialize with first card active if none selected
            const $activeCard = $widget.find('.infoplus-model-selector__card.is-active');
            if ($activeCard.length) {
                const initialModel = $activeCard.data('model');
                if (initialModel && window.infoplusProductState) {
                    window.infoplusProductState.setModel(initialModel);
                }
            }
        },

        /**
         * Select a model card
         */
        selectCard: function($widget, $card) {
            const modelSlug = $card.data('model');
            
            if (!modelSlug) return;
            
            // Update visual state
            $widget.find('.infoplus-model-selector__card').removeClass('is-active');
            $card.addClass('is-active');
            
            // Update global state
            if (window.infoplusProductState) {
                window.infoplusProductState.setModel(modelSlug);
            }
            
            // Scroll card into view if in scrollable container
            ModelSelector.scrollCardIntoView($widget, $card);
        },

        /**
         * Update active card based on state change
         */
        updateActiveCard: function($widget, modelSlug) {
            $widget.find('.infoplus-model-selector__card').removeClass('is-active');
            $widget.find(`.infoplus-model-selector__card[data-model="${modelSlug}"]`).addClass('is-active');
        },

        /**
         * Scroll card into view
         */
        scrollCardIntoView: function($widget, $card) {
            const $list = $widget.find('.infoplus-model-selector__list');
            
            if ($list.css('overflow-y') === 'auto' || $list.css('overflow-y') === 'scroll') {
                const cardTop = $card.position().top;
                const cardHeight = $card.outerHeight();
                const listHeight = $list.height();
                const scrollTop = $list.scrollTop();
                
                if (cardTop < 0) {
                    $list.animate({ scrollTop: scrollTop + cardTop - 10 }, 200);
                } else if (cardTop + cardHeight > listHeight) {
                    $list.animate({ scrollTop: scrollTop + cardTop + cardHeight - listHeight + 10 }, 200);
                }
            }
        },

        /**
         * Show info modal
         */
        showInfoModal: function($card) {
            const modelSlug = $card.data('model');
            const tooltip = $card.data('tooltip');
            const modalContent = $card.data('modal-content');
            const $image = $card.find('.infoplus-model-selector__image img');
            const imageSrc = $image.attr('src');
            const modelName = $card.find('.infoplus-model-selector__name').text();
            
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
                $modal.find('.infoplus-info-modal__image').html(`<img src="${imageSrc}" alt="${modelName}" />`).show();
            } else {
                $modal.find('.infoplus-info-modal__image').hide();
            }
            
            $modal.find('.infoplus-info-modal__title').text(modelName);
            $modal.find('.infoplus-info-modal__description').html(modalContent || tooltip || '');
            
            // Show modal
            $modal.fadeIn(200);
            $('body').addClass('infoplus-modal-open');
            
            // Bind close events
            $modal.off('click.closeModal').on('click.closeModal', '.infoplus-info-modal__close, .infoplus-info-modal__backdrop', function() {
                ModelSelector.hideInfoModal();
            });
            
            // Close on escape
            $(document).off('keydown.infoModal').on('keydown.infoModal', function(e) {
                if (e.key === 'Escape') {
                    ModelSelector.hideInfoModal();
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
        ModelSelector.init();
    });

    // Re-initialize for Elementor editor
    $(window).on('elementor/frontend/init', function() {
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            elementorFrontend.hooks.addAction('frontend/element_ready/infoplus-model-selector.default', function($element) {
                ModelSelector.initWidget($element.find('.infoplus-model-selector'));
            });
        }
    });

})(jQuery, window);
