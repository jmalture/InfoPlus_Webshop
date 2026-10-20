<!--
Elementor Custom Code snippet (post_type elementor_snippet) exported read-only from staging
post_id: 13799
title: dashboard filter
status: publish
location (_elementor_location): elementor_body_end
priority (_elementor_priority): 1
conditions (_elementor_conditions): ["include/singular/page/12466"]
conditions raw: a:1:{i:0;s:27:"include/singular/page/12466";}
extra options (_elementor_extra_options): ["ensure_jquery"]
code bytes (LENGTH(_elementor_code)): 8237
Everything below the END HEADER line is the _elementor_code meta value verbatim (post_content is empty for these posts).
-->
<!-- END HEADER -->
<script>
jQuery(function($) {
    // Configuration for each filter instance
    const filterConfigs = {
        orders: {
            searchInput: '#orderSearch',
            statusFilter: '#statusFilter',
            priceMin: '#priceMin',
            priceMax: '#priceMax',
            clearBtn: '#clearFilters',
            resultsInfo: '#resultsInfo',
            gridId: '#order_grid',
            cardSelector: '.order-card',
            attributes: ['data-order-number', 'data-products', 'data-status', 'data-date'],
            priceAttribute: 'data-price'
        },
        projects: {
            searchInput: '#projectSearch',
            statusFilter: '#projectStatusFilter',
            clearBtn: '#clearProjectFilters',
            resultsInfo: '#projectResultsInfo',
            gridId: '#project_grid',
            cardSelector: '.project-card',
            attributes: ['data-project-name', 'data-format', 'data-menge', 'data-created', 'data-modified']
        }
    };

    // Initialize filter for a specific type (orders or projects)
    function initFilter(config) {
        const $searchInput = $(config.searchInput);
        const $statusFilter = $(config.statusFilter);
        const $priceMin = $(config.priceMin);
        const $priceMax = $(config.priceMax);
        const $clearBtn = $(config.clearBtn);
        const $resultsInfo = $(config.resultsInfo);
        const $grid = $(config.gridId);

        if (!$searchInput.length || !$grid.length) {
            console.warn('Filter elements not found:', config);
            return;
        }

        let filterState = {
            search: '',
            status: '',
            priceMin: '',
            priceMax: ''
        };

        let filterTimeout;

        // Search input handler (debounced)
        $searchInput.on('input', function(e) {
            clearTimeout(filterTimeout);
            filterState.search = $(this).val();
            
            filterTimeout = setTimeout(function() {
                performFilter();
            }, 300);
        });

        // Status filter handler
        if ($statusFilter.length) {
            $statusFilter.on('change', function(e) {
                filterState.status = $(this).val();
                performFilter();
            });
        }

        // Price filter handlers (orders only)
        if ($priceMin.length) {
            $priceMin.on('input', function(e) {
                clearTimeout(filterTimeout);
                filterState.priceMin = $(this).val();
                
                filterTimeout = setTimeout(function() {
                    performFilter();
                }, 300);
            });
        }

        if ($priceMax.length) {
            $priceMax.on('input', function(e) {
                clearTimeout(filterTimeout);
                filterState.priceMax = $(this).val();
                
                filterTimeout = setTimeout(function() {
                    performFilter();
                }, 300);
            });
        }

        // Clear filters handler
        if ($clearBtn.length) {
            $clearBtn.on('click', function() {
                $searchInput.val('');
                if ($statusFilter.length) {
                    $statusFilter.val('');
                }
                if ($priceMin.length) {
                    $priceMin.val('');
                }
                if ($priceMax.length) {
                    $priceMax.val('');
                }
                filterState = { search: '', status: '', priceMin: '', priceMax: '' };
                $resultsInfo.removeClass('active');
                performFilter();
            });
        }

        // Enter key handler
        $searchInput.on('keypress', function(e) {
            if (e.key === 'Enter') {
                clearTimeout(filterTimeout);
                performFilter();
            }
        });

        // Main filter function
        function performFilter() {
            const $cards = $grid.find(config.cardSelector);
            const query = filterState.search.toLowerCase().trim();
            const statusValue = filterState.status.toLowerCase();
            const minPrice = parseFloat(filterState.priceMin) || 0;
            const maxPrice = parseFloat(filterState.priceMax) || Infinity;
            
            let visibleCount = 0;

            $cards.each(function() {
                const $card = $(this);
                let matches = true;

                // Text search filter
                if (query) {
                    let searchableText = '';
                    
                    // Collect text from all specified attributes
                    config.attributes.forEach(function(attr) {
                        const value = $card.attr(attr) || '';
                        searchableText += value + ' ';
                    });
                    
                    searchableText = searchableText.toLowerCase();
                    
                    if (!searchableText.includes(query)) {
                        matches = false;
                    }
                }

                // Status filter
                if (matches && statusValue && $statusFilter.length) {
                    const cardStatus = ($card.attr('data-status') || '').toLowerCase();
                    if (cardStatus !== statusValue) {
                        matches = false;
                    }
                }

                // Price range filter (orders only)
                if (matches && config.priceAttribute) {
                    const priceStr = $card.attr(config.priceAttribute) || '0';
                    const cardPrice = parseFloat(priceStr);
                    
                    if (filterState.priceMin && cardPrice < minPrice) {
                        matches = false;
                    }
                    if (filterState.priceMax && cardPrice > maxPrice) {
                        matches = false;
                    }
                }

                // Show/hide card ONLY (not children)
                if (matches) {
                    if ($card.hasClass('filtered-hidden')) {
                        $card.removeClass('filtered-hidden');
                        // Remove any inline display:none that might be on children
                        $card.find('.filtered-hidden').removeClass('filtered-hidden').css('display', '');
                        $card.fadeIn(300);
                    }
                    visibleCount++;
                } else {
                    if (!$card.hasClass('filtered-hidden')) {
                        $card.fadeOut(300, function() {
                            $card.addClass('filtered-hidden');
                        });
                    }
                }
            });

            // Update results info
            const hasFilters = query || statusValue || filterState.priceMin || filterState.priceMax;
            
            if (hasFilters) {
                $resultsInfo.addClass('active');
                $resultsInfo.find('.results-count').text(visibleCount);
            } else {
                $resultsInfo.removeClass('active');
            }

            // Dispatch custom event
            const event = new CustomEvent('filterChange', {
                detail: {
                    type: config.gridId,
                    filterState: filterState,
                    visibleCount: visibleCount
                }
            });
            document.dispatchEvent(event);

            console.log('Filter applied:', {
                type: config.gridId,
                filters: filterState,
                visible: visibleCount + '/' + $cards.length
            });
        }

        // Initial state - make sure all children are visible
        $grid.find('.filtered-hidden').removeClass('filtered-hidden').css('display', '');
        performFilter();
    }

    // Initialize both filters
    initFilter(filterConfigs.orders);
    initFilter(filterConfigs.projects);
});
	</script>