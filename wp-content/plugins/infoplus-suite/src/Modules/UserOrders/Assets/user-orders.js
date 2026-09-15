/**
 * User Orders Frontend Handler
 * Handles order filtering and pagination for the User Orders Grid widget
 */
(function($) {
    'use strict';

    const UserOrders = {
        initialized: false,
        
        init: function() {
            if (this.initialized) {
                return;
            }
            this.initialized = true;
            
            console.log('Infoplus UserOrders: Initializing...');
            
            this.bindFilterEvents();
            this.bindSearchEvents();
            this.bindResetEvents();
            this.bindLoadMoreEvents();
        },

        /**
         * Bind status filter events
         */
        bindFilterEvents: function() {
            const self = this;
            
            $(document).on('change', '.infoplus-status-filter', function() {
                const status = $(this).val();
                const $widget = $(this).closest('.infoplus-user-orders-widget');
                console.log('Infoplus UserOrders: Status filter changed:', status);
                self.filterOrders($widget);
            });
        },

        /**
         * Bind search input events with debounce
         */
        bindSearchEvents: function() {
            const self = this;
            let searchTimeout;
            
            $(document).on('input', '.infoplus-order-search', function() {
                clearTimeout(searchTimeout);
                const $widget = $(this).closest('.infoplus-user-orders-widget');
                
                searchTimeout = setTimeout(function() {
                    self.filterOrders($widget);
                }, 300);
            });
        },

        /**
         * Bind reset button events
         */
        bindResetEvents: function() {
            const self = this;
            
            $(document).on('click', '.infoplus-filter-reset', function(e) {
                e.preventDefault();
                const $widget = $(this).closest('.infoplus-user-orders-widget');
                
                // Reset inputs
                $widget.find('.infoplus-order-search').val('');
                $widget.find('.infoplus-status-filter').val('');
                
                // Reset pagination state
                self.resetPagination($widget);
                
                // Show grid and hide no results
                $widget.find('.infoplus-orders-grid').show();
                $widget.find('.infoplus-no-results').hide();
                
                // Update count
                const totalOrders = $widget.data('orders-count');
                $widget.find('.infoplus-results-count').text(totalOrders);
                
                console.log('Infoplus UserOrders: Filters reset');
            });
        },

        /**
         * Bind Load More button events
         */
        bindLoadMoreEvents: function() {
            const self = this;
            
            $(document).on('click', '.infoplus-load-more-btn', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const $widget = $btn.closest('.infoplus-user-orders-widget');
                
                self.loadMore($widget, $btn);
            });
        },

        /**
         * Load more orders
         */
        loadMore: function($widget, $btn) {
            const loadCount = parseInt($btn.data('load-count')) || 3;
            const $cards = $widget.find('.infoplus-order-card.infoplus-hidden:not(.infoplus-filtered-out)');
            
            // Show next batch of cards
            let shown = 0;
            $cards.each(function() {
                if (shown >= loadCount) {
                    return false; // Break the loop
                }
                $(this).removeClass('infoplus-hidden');
                shown++;
            });
            
            // Update the count display
            this.updateLoadMoreCount($widget);
            
            // Check if we need to hide the button
            const remainingHidden = $widget.find('.infoplus-order-card.infoplus-hidden:not(.infoplus-filtered-out)').length;
            if (remainingHidden === 0) {
                $widget.find('.infoplus-load-more-container').hide();
            }
            
            console.log('Infoplus UserOrders: Loaded', shown, 'more orders,', remainingHidden, 'remaining');
        },

        /**
         * Update the "Showing X of Y" count
         */
        updateLoadMoreCount: function($widget) {
            const $visibleCards = $widget.find('.infoplus-order-card:not(.infoplus-hidden):not(.infoplus-filtered-out)');
            const $allCards = $widget.find('.infoplus-order-card:not(.infoplus-filtered-out)');
            
            const currentCount = $visibleCards.length;
            const totalCount = $allCards.length;
            
            $widget.find('.infoplus-current-count').text(currentCount);
            $widget.find('.infoplus-total-count').text(totalCount);
        },

        /**
         * Reset pagination to initial state
         */
        resetPagination: function($widget) {
            const perPage = parseInt($widget.data('per-page')) || 6;
            const $cards = $widget.find('.infoplus-order-card');
            
            // Remove all filter and hidden classes
            $cards.removeClass('infoplus-filtered-out');
            
            // Re-apply initial pagination
            $cards.each(function(index) {
                const $card = $(this);
                if (index >= perPage) {
                    $card.addClass('infoplus-hidden');
                } else {
                    $card.removeClass('infoplus-hidden');
                }
            });
            
            // Update count and show/hide load more
            this.updateLoadMoreCount($widget);
            
            const totalCards = $cards.length;
            if (totalCards > perPage) {
                $widget.find('.infoplus-load-more-container').show();
            } else {
                $widget.find('.infoplus-load-more-container').hide();
            }
        },

        /**
         * Filter orders based on current search and status values
         */
        filterOrders: function($widget) {
            const searchTerm = $widget.find('.infoplus-order-search').val().toLowerCase().trim();
            const statusFilter = $widget.find('.infoplus-status-filter').val();
            const perPage = parseInt($widget.data('per-page')) || 6;
            
            const $cards = $widget.find('.infoplus-order-card');
            let matchingCount = 0;
            let visibleCount = 0;
            
            // First, determine which cards match the filter
            $cards.each(function() {
                const $card = $(this);
                const cardStatus = $card.data('order-status');
                const cardNumber = String($card.data('order-number')).toLowerCase();
                const cardDate = String($card.data('order-date')).toLowerCase();
                const cardText = $card.text().toLowerCase();
                
                // Check status filter
                let statusMatch = true;
                if (statusFilter && statusFilter !== '') {
                    statusMatch = (cardStatus === statusFilter);
                }
                
                // Check search filter
                let searchMatch = true;
                if (searchTerm && searchTerm !== '') {
                    searchMatch = cardText.includes(searchTerm) || 
                                  cardNumber.includes(searchTerm) ||
                                  cardDate.includes(searchTerm);
                }
                
                // Apply filtering
                if (statusMatch && searchMatch) {
                    $card.removeClass('infoplus-filtered-out');
                    
                    // Apply pagination within matching cards
                    if (matchingCount < perPage) {
                        $card.removeClass('infoplus-hidden');
                        visibleCount++;
                    } else {
                        $card.addClass('infoplus-hidden');
                    }
                    matchingCount++;
                } else {
                    $card.addClass('infoplus-filtered-out').addClass('infoplus-hidden');
                }
            });
            
            // Update results count
            $widget.find('.infoplus-results-count').text(matchingCount);
            
            // Update load more count
            $widget.find('.infoplus-current-count').text(visibleCount);
            $widget.find('.infoplus-total-count').text(matchingCount);
            
            // Show/hide load more button based on remaining hidden matching cards
            const remainingHidden = $widget.find('.infoplus-order-card.infoplus-hidden:not(.infoplus-filtered-out)').length;
            if (remainingHidden > 0) {
                $widget.find('.infoplus-load-more-container').show();
            } else {
                $widget.find('.infoplus-load-more-container').hide();
            }
            
            // Show/hide no results message
            if (matchingCount === 0) {
                $widget.find('.infoplus-orders-grid').hide();
                $widget.find('.infoplus-load-more-container').hide();
                $widget.find('.infoplus-no-results').show();
            } else {
                $widget.find('.infoplus-orders-grid').show();
                $widget.find('.infoplus-no-results').hide();
            }
            
            console.log('Infoplus UserOrders: Filtered, showing', visibleCount, 'of', matchingCount, 'matching orders');
        },

        /**
         * Load orders from REST API (for dynamic updates)
         */
        loadOrdersFromAPI: function(callback) {
            if (typeof infoplusUserOrders === 'undefined') {
                console.warn('Infoplus UserOrders: API config not found');
                return;
            }
            
            $.ajax({
                url: infoplusUserOrders.restUrl + 'orders',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', infoplusUserOrders.nonce);
                },
                success: function(response) {
                    if (response.success && response.data) {
                        if (typeof callback === 'function') {
                            callback(response.data);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Infoplus UserOrders: API Error:', error);
                }
            });
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Small delay to ensure everything is loaded
        setTimeout(function() {
            UserOrders.init();
        }, 100);
    });

    // Handle Elementor frontend init (for preview mode)
    $(window).on('elementor/frontend/init', function() {
        if (typeof elementorFrontend !== 'undefined' && elementorFrontend.hooks) {
            elementorFrontend.hooks.addAction('frontend/element_ready/infoplus-user-orders-grid.default', function($element) {
                UserOrders.initialized = false;
                UserOrders.init();
            });
        }
    });

    // Make globally accessible
    window.InfoplusUserOrders = UserOrders;

})(jQuery);
