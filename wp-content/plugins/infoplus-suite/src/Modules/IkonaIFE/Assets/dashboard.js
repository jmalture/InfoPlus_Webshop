/**
 * Ikona IFE Dashboard JavaScript
 * 
 * Handles product table rendering, filtering, sorting, and sync operations.
 * 
 * @package Infoplus\Suite\Modules\IkonaIFE
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Dashboard Controller
     */
    const IkonaDashboard = {
        // Data
        masterData: [],
        sortState: { key: '', dir: 'asc' },
        config: {},

        /**
         * Initialize the dashboard
         */
        init: function(config, preloadedProducts) {
            this.config = config;
            this.masterData = Array.isArray(preloadedProducts) ? preloadedProducts : [];
            
            this.bindEvents();
            this.initialRender();
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            const self = this;

            // Search
            let searchTimer;
            $('#infoplus-search').on('input', function() {
                clearTimeout(searchTimer);
                $('#infoplus-search-spinner').show();
                searchTimer = setTimeout(function() {
                    $('#infoplus-search-spinner').hide();
                    self.renderPipeline();
                }, 200);
            });

            $('#infoplus-search-clear').on('click', function() {
                $('#infoplus-search').val('');
                $('#infoplus-search-spinner').hide();
                self.renderPipeline();
            });

            // Filters
            $('#filter-type, #filter-category, #filter-status').on('change', function() {
                self.renderPipeline();
            });

            // Sorting
            $(document).on('click', '#infoplus-sync-table thead .sortable', function() {
                const key = $(this).data('key');
                if (self.sortState.key !== key) {
                    self.sortState = { key: key, dir: 'asc' };
                } else {
                    self.sortState.dir = self.sortState.dir === 'asc' ? 'desc' : 'asc';
                }
                self.setHeaderSortUI();
                self.renderPipeline();
            });

            // Toggle variants
            $(document).on('click', '.toggle-vars', function() {
                const btn = $(this);
                const pid = btn.data('pid');
                const rows = $('.variant-row[data-parent="' + pid + '"]');
                const isOpen = btn.hasClass('open');
                
                btn.toggleClass('open');
                btn.find('.dashicons')
                    .toggleClass('dashicons-arrow-down-alt2', isOpen)
                    .toggleClass('dashicons-arrow-up-alt2', !isOpen);
                rows.toggle();
            });

            // Sync All — TEMPORARILY DISABLED (would break IFE products)
            // $('#infoplus-sync-all').on('click', function() {
            //     self.handleSyncAll();
            // });
            $('#infoplus-sync-all').prop('disabled', true).attr('title', 'Sync temporarily disabled').css('opacity', '0.5');

            // Single Sync — TEMPORARILY DISABLED (would break IFE products)
            // $(document).on('click', '.infoplus-sync-single', function() {
            //     const btn = this;
            //     const id = $(btn).data('id');
            //     const isProduct = $(btn).data('sync-all') === 1 || $(btn).data('sync-all') === '1';
            //     const parentId = $(btn).data('parent-id');
            //     const syncId = isProduct ? id : (parentId || id);
            //     
            //     self.syncProduct(syncId, btn);
            // });
            $(document).on('click', '.infoplus-sync-single', function(e) { e.preventDefault(); e.stopPropagation(); });

            // Modal close
            $(document).on('click', '.infoplus-modal-close, .infoplus-modal-close-btn', function() {
                self.hideModal();
            });
            $(document).on('click', '.infoplus-modal-backdrop', function() {
                self.hideModal();
            });
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#infoplus-sync-modal').is(':visible')) {
                    self.hideModal();
                }
            });
        },

        /**
         * Initial render
         */
        initialRender: function() {
            const self = this;
            const i18n = this.config.i18n;
            
            // Show loading state immediately
            this.showLoadingState();
            
            // If we have preloaded data and not forcing async, use it
            if (this.masterData.length && !this.config.loadAsync) {
                this.afterDataReady();
                return;
            }

            // Fetch products asynchronously
            console.log('Fetching products from:', this.config.proxyUrl);
            console.log('Using nonce:', this.config.nonce);
            
            fetch(this.config.proxyUrl, { 
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                    'Content-Type': 'application/json'
                }
            })
                .then(r => r.ok ? r.json() : Promise.reject(r))
                .then(data => {
                    self.masterData = Array.isArray(data) ? data : [];
                    self.afterDataReady();
                })
                .catch((err) => {
                    console.error('Failed to load products:', err);
                    self.masterData = [];
                    self.showErrorState();
                });
        },

        /**
         * Show loading state in table
         */
        showLoadingState: function() {
            const i18n = this.config.i18n;
            const tbody = $('#infoplus-sync-table tbody');
            tbody.html(
                '<tr class="infoplus-loading-row">' +
                    '<td colspan="11" style="text-align:center;padding:40px 20px;">' +
                        '<div class="infoplus-loading-spinner"></div>' +
                        '<div style="margin-top:12px;color:#666;">' + this.esc(i18n.loadingProducts || 'Loading products...') + '</div>' +
                    '</td>' +
                '</tr>'
            );
        },

        /**
         * Show error state in table
         */
        showErrorState: function() {
            const i18n = this.config.i18n;
            const tbody = $('#infoplus-sync-table tbody');
            tbody.html(
                '<tr>' +
                    '<td colspan="11" style="text-align:center;padding:40px 20px;color:#a12828;">' +
                        '<span class="dashicons dashicons-warning" style="font-size:32px;width:32px;height:32px;"></span>' +
                        '<div style="margin-top:12px;">' + this.esc(i18n.loadError || 'Failed to load products.') + '</div>' +
                        '<button class="button" style="margin-top:12px;" onclick="location.reload();">Refresh Page</button>' +
                    '</td>' +
                '</tr>'
            );
        },

        /**
         * After data is ready
         */
        afterDataReady: function() {
            this.populateFilters();
            this.renderPipeline();
        },

        /**
         * Populate filter dropdowns
         */
        populateFilters: function() {
            const types = this.uniqueSorted(this.masterData.map(p => String(p.type || '')).filter(Boolean));
            const cats = this.uniqueSorted(this.masterData.map(p => {
                if (typeof p.category === 'string') return p.category;
                if (Array.isArray(p.category)) return p.category[0] || '';
                return '';
            }).filter(Boolean));

            const $filterType = $('#filter-type');
            const $filterCat = $('#filter-category');

            types.forEach(t => $filterType.append($('<option/>', { value: t, text: t })));
            cats.forEach(c => $filterCat.append($('<option/>', { value: c, text: c })));
        },

        /**
         * Render pipeline: filter → sort → render
         */
        renderPipeline: function() {
            const q = $('#infoplus-search').val() || '';
            const t = $('#filter-type').val() || '';
            const c = $('#filter-category').val() || '';
            const s = $('#filter-status').val() || '';

            let data = this.applyCombinedFilter(q, t, c, s);
            data = this.sortData(data);
            this.renderTable(data, q);
        },

        /**
         * Apply combined filters
         */
        applyCombinedFilter: function(q, typeVal, catVal, statusVal) {
            const qlc = (q || '').trim().toLowerCase();
            const wantQ = !!qlc;
            const wantT = !!typeVal;
            const wantC = !!catVal;
            const wantS = statusVal !== '' && statusVal !== null && statusVal !== undefined;
            const self = this;

            return this.masterData.filter(function(p) {
                if (wantQ && !self.rowSearchText(p).includes(qlc)) return false;
                if (wantT && String(p.type || '') !== typeVal) return false;
                if (wantC) {
                    const cat = (typeof p.category === 'string') ? p.category :
                        (Array.isArray(p.category) ? (p.category[0] || '') : '');
                    if (cat !== catVal) return false;
                }
                if (wantS && String(self.statusFlag(p)) !== String(statusVal)) return false;
                return true;
            });
        },

        /**
         * Sort data
         */
        sortData: function(data) {
            const key = this.sortState.key;
            const dir = this.sortState.dir;
            const self = this;

            if (!key || !dir) return data.slice();

            const isNum = $('.sortable[data-key="' + key + '"]').hasClass('num');
            const isDate = $('.sortable[data-key="' + key + '"]').hasClass('date');

            const arr = data.slice().sort(function(A, B) {
                let a = A[key], b = B[key];
                
                if (key === 'category') {
                    a = (typeof a === 'string') ? a : (Array.isArray(a) ? (a[0] || '') : '');
                    b = (typeof b === 'string') ? b : (Array.isArray(b) ? (b[0] || '') : '');
                }
                if (key === 'synchronized') {
                    a = self.statusFlag(A);
                    b = self.statusFlag(B);
                }
                if (isDate) {
                    a = Date.parse(a) || 0;
                    b = Date.parse(b) || 0;
                } else if (isNum) {
                    a = Number(a) || 0;
                    b = Number(b) || 0;
                } else {
                    a = String(a || '').toLowerCase();
                    b = String(b || '').toLowerCase();
                }
                
                if (a < b) return -1;
                if (a > b) return 1;
                return 0;
            });

            return dir === 'desc' ? arr.reverse() : arr;
        },

        /**
         * Set header sort UI
         */
        setHeaderSortUI: function() {
            $('#infoplus-sync-table thead .sortable').removeClass('sort-asc sort-desc');
            if (this.sortState.key && this.sortState.dir) {
                const $th = $('#infoplus-sync-table thead .sortable[data-key="' + this.sortState.key + '"]');
                $th.addClass(this.sortState.dir === 'asc' ? 'sort-asc' : 'sort-desc');
            }
        },

        /**
         * Render table
         */
        renderTable: function(data, q) {
            const tbody = $('#infoplus-sync-table tbody');
            const self = this;
            const i18n = this.config.i18n;

            if (!Array.isArray(data) || !data.length) {
                tbody.html('<tr><td colspan="11" style="text-align:center;">' + this.esc(i18n.noProducts) + '</td></tr>');
                return;
            }

            const rows = data.map(function(prod) {
                const hasVars = Array.isArray(prod.variants) && prod.variants.length > 0;
                const arrow = hasVars ? 
                    '<button class="toggle-vars" data-pid="' + prod.id + '" aria-label="Show variations"><span class="dashicons dashicons-arrow-down-alt2"></span></button>' : '';
                
                const catStr = (typeof prod.category === 'string' && prod.category) ? 
                    prod.category : (Array.isArray(prod.category) ? (prod.category[0] || '—') : '—');
                const price = (prod.price ?? '') !== '' ? String(prod.price) : '—';
                const type = prod.type || '—';
                const lm = prod.lastModified || '—';
                const ls = prod.lastSynchronized || prod.lastSynced || '—';
                const status = self.getBadge(prod.synchronized);

                let html = '<tr class="product-row" data-id="' + self.esc(prod.id) + '">' +
                    '<td style="text-align:center;vertical-align:middle;">' + arrow + '</td>' +
                    '<td>' + self.highlightSafe(prod.display || prod.name || '', q) + '</td>' +
                    '<td>' + self.highlightSafe(prod.printApiId || '—', q) + '</td>' +
                    '<td>' + self.highlightSafe(prod.sku || '—', q) + '</td>' +
                    '<td>' + self.highlightSafe(type, q) + '</td>' +
                    '<td>' + self.highlightSafe(price, q) + '</td>' +
                    '<td>' + self.highlightSafe(catStr, q) + '</td>' +
                    '<td>' + self.highlightSafe(lm, q) + '</td>' +
                    '<td>' + self.highlightSafe(ls, q) + '</td>' +
                    '<td>' + status + '</td>' +
                    '<td style="text-align:center;">' +
                        '<button class="button infoplus-sync-single" data-id="' + self.esc(prod.id) + '" data-sync-all="1" title="' + self.esc(i18n.syncThis) + '">' +
                            '<span class="dashicons dashicons-update"></span>' +
                        '</button>' +
                    '</td>' +
                '</tr>';

                if (hasVars) {
                    html += prod.variants.map(function(v) {
                        const vprice = (v.price ?? '') !== '' ? String(v.price) : '—';
                        const vtype = v.type || 'variation';
                        const vsku = v.sku || '—';
                        const vpid = v.printApiId || '—';
                        const vname = v.display || v.name || '—';
                        const vlm = v.lastModified || '—';
                        const vls = v.lastSynchronized || v.lastSynced || '—';
                        const vstat = self.getBadge(v.synchronized);

                        return '<tr class="variant-row" data-parent="' + self.esc(prod.id) + '" data-id="' + self.esc(v.id) + '" style="display:none;">' +
                            '<td></td>' +
                            '<td style="padding-left:2em;">' + self.highlightSafe(vname, q) + '</td>' +
                            '<td>' + self.highlightSafe(vpid, q) + '</td>' +
                            '<td>' + self.highlightSafe(vsku, q) + '</td>' +
                            '<td>' + self.highlightSafe(vtype, q) + '</td>' +
                            '<td>' + self.highlightSafe(vprice, q) + '</td>' +
                            '<td>—</td>' +
                            '<td>' + self.highlightSafe(vlm, q) + '</td>' +
                            '<td>' + self.highlightSafe(vls, q) + '</td>' +
                            '<td>' + vstat + '</td>' +
                            '<td style="text-align:center;">' +
                                '<button class="button infoplus-sync-single" data-id="' + self.esc(v.id) + '" data-parent-id="' + self.esc(prod.id) + '" data-sync-all="0" title="' + self.esc(i18n.syncVariation) + '">' +
                                    '<span class="dashicons dashicons-update"></span>' +
                                '</button>' +
                            '</td>' +
                        '</tr>';
                    }).join('');
                }

                return html;
            }).join('');

            tbody.html(rows);
        },

        /**
         * Sync a single product
         */
        syncProduct: function(productId, btn) {
            const self = this;
            const $btn = $(btn);
            const originalHtml = $btn.html();
            const i18n = this.config.i18n;

            // Set loading state
            $btn.addClass('syncing').prop('disabled', true);

            // Call the synchronization API
            $.ajax({
                url: this.config.syncApiUrl,
                method: 'GET',
                data: { productId: productId },
                dataType: 'json',
                success: function(response) {
                    const data = Array.isArray(response) ? response[0] : response;
                    let syncResult;

                    if (data.body && typeof data.body === 'string') {
                        try {
                            syncResult = JSON.parse(data.body);
                        } catch(e) {
                            syncResult = data;
                        }
                    } else if (data.body) {
                        syncResult = data.body;
                    } else {
                        syncResult = data;
                    }

                    const isSuccess = (data.response && data.response.code === 200) || 
                                     (syncResult && syncResult.productId);

                    if (isSuccess) {
                        self.handleSyncSuccess($btn, originalHtml, productId, syncResult);
                    } else {
                        // Extract error message from body if available
                        let errorMsg = '';
                        if (data.body && typeof data.body === 'string' && !data.body.startsWith('{')) {
                            // Body is a plain error string
                            errorMsg = data.body;
                        } else if (data.response && data.response.message) {
                            errorMsg = data.response.message;
                        }
                        self.handleSyncError($btn, originalHtml, { ...data, message: errorMsg }, productId);
                    }
                },
                error: function(xhr, status, error) {
                    self.handleSyncError($btn, originalHtml, { error: error, status: status }, productId);
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Handle successful sync
         */
        handleSyncSuccess: function($btn, originalHtml, productId, syncResult) {
            const self = this;
            const i18n = this.config.i18n;

            $btn.removeClass('syncing').addClass('success');
            $btn.html('<span class="dashicons dashicons-yes"></span>');

            // Save sync result to database
            $.ajax({
                url: this.config.saveSyncUrl + '/' + productId + '/sync-result',
                method: 'POST',
                contentType: 'application/json',
                headers: {
                    'X-WP-Nonce': this.config.nonce
                },
                data: JSON.stringify({
                    ifeProductId: syncResult.productId || '',
                    variants: syncResult.variants || []
                }),
                success: function(saveResponse) {
                    const $row = $btn.closest('tr');
                    const formattedDate = saveResponse.syncTime || new Date().toISOString().slice(0, 19).replace('T', ' ');

                    // Update row cells
                    $row.find('td').eq(8).text(formattedDate);
                    $row.find('td').eq(9).html('<span class="infoplus-badge ok">Synchronized</span>');

                    // Update variant rows
                    const pid = $row.data('id');
                    $('.variant-row[data-parent="' + pid + '"]').each(function() {
                        $(this).find('td').eq(8).text(formattedDate);
                        $(this).find('td').eq(9).html('<span class="infoplus-badge ok">Synchronized</span>');
                    });

                    // Update master data (use lastSynchronized to match API format)
                    const idx = self.masterData.findIndex(function(p) { return p.id == pid; });
                    if (idx !== -1) {
                        self.masterData[idx].lastSynchronized = formattedDate;
                        self.masterData[idx].synchronized = 0;
                        if (self.masterData[idx].variants) {
                            self.masterData[idx].variants.forEach(function(v) {
                                v.lastSynchronized = formattedDate;
                                v.synchronized = 0;
                            });
                        }
                    }
                },
                error: function() {
                    console.warn('Could not save sync result to database');
                }
            });

            this.showToast('success', i18n.syncSuccessful, i18n.productSynced);
            this.showSyncResultModal(productId, syncResult);

            setTimeout(function() {
                $btn.removeClass('success').html(originalHtml);
            }, 3000);
        },

        /**
         * Handle sync error
         */
        handleSyncError: function($btn, originalHtml, errorData, productId) {
            const i18n = this.config.i18n;

            $btn.removeClass('syncing').addClass('error');
            $btn.html('<span class="dashicons dashicons-no"></span>');

            const errorMsg = errorData.message || errorData.error || i18n.unknownError;
            const errorCode = errorData.response?.code || errorData.status || '';
            
            this.showToast('error', i18n.syncFailed, errorMsg);
            this.showSyncErrorModal(productId, errorMsg, errorCode, errorData);

            setTimeout(function() {
                $btn.removeClass('error').html(originalHtml);
            }, 3000);
        },

        /**
         * Handle Sync All click
         */
        handleSyncAll: function() {
            const self = this;
            const i18n = this.config.i18n;
            const productsToSync = this.masterData.filter(function(p) {
                return p.type === 'variable' || p.type === 'simple';
            });

            if (productsToSync.length === 0) {
                this.showToast('warning', i18n.noProductsSync || 'No Products', '');
                return;
            }

            if (!confirm(i18n.syncAllConfirm + ' ' + productsToSync.length + ' ' + i18n.productsConfirm)) {
                return;
            }

            // Disable sync all button
            const $syncAllBtn = $('#infoplus-sync-all');
            $syncAllBtn.prop('disabled', true).addClass('syncing');
            $syncAllBtn.find('.dashicons').addClass('spinning');

            // Show progress bar
            const $progress = $('#infoplus-sync-progress');
            const $progressBar = $('#infoplus-sync-progress-bar');
            const $progressText = $('#infoplus-sync-progress-text');
            const $progressLog = $('#infoplus-sync-log');
            
            $progress.show();
            $progressLog.show().empty();
            $progressBar.css('width', '0%');
            $progressText.text('0%');

            // Track sync state
            let syncedCount = 0;
            let errorCount = 0;
            let totalCount = productsToSync.length;
            const results = { success: [], errors: [] };

            // Add log entry
            const addLog = function(message, type) {
                const time = new Date().toLocaleTimeString();
                const color = type === 'error' ? '#a12828' : (type === 'success' ? '#137333' : '#666');
                $progressLog.prepend('<div style="color:' + color + ';margin-bottom:4px;">[' + time + '] ' + self.esc(message) + '</div>');
            };

            // Update progress
            const updateProgress = function() {
                const percent = Math.round(((syncedCount + errorCount) / totalCount) * 100);
                $progressBar.css('width', percent + '%');
                $progressText.text(percent + '% (' + (syncedCount + errorCount) + '/' + totalCount + ')');
            };

            addLog(i18n.syncAllStarting || 'Starting synchronization of ' + totalCount + ' products...', 'info');

            // Call the sync all API
            $.ajax({
                url: this.config.syncApiUrl,
                method: 'GET',
                dataType: 'json',
                timeout: 300000, // 5 minutes timeout for large syncs
                beforeSend: function() {
                    // Set all product sync buttons to syncing state
                    productsToSync.forEach(function(p) {
                        const $btn = $('.product-row[data-id="' + p.id + '"] .infoplus-sync-single');
                        $btn.addClass('syncing').prop('disabled', true);
                    });
                },
                success: function(response) {
                    // Response is an array of results for each product
                    const resultsArray = Array.isArray(response) ? response : [response];
                    
                    resultsArray.forEach(function(item, index) {
                        // Parse the body
                        let syncResult;
                        if (item.body && typeof item.body === 'string') {
                            try {
                                syncResult = JSON.parse(item.body);
                            } catch(e) {
                                syncResult = { error: item.body };
                            }
                        } else {
                            syncResult = item.body || item;
                        }

                        const isSuccess = (item.response && item.response.code === 200) && syncResult.productId;
                        
                        if (isSuccess) {
                            syncedCount++;
                            const productId = self.findProductIdByIfeId(syncResult.productId) || syncResult.variants?.[0]?.variantId;
                            
                            if (productId) {
                                // Find WooCommerce product ID from variant
                                const wooProductId = self.findWooProductIdFromVariant(syncResult.variants);
                                if (wooProductId) {
                                    self.updateRowAfterSync(wooProductId, syncResult);
                                    const product = self.masterData.find(function(p) { return p.id == wooProductId; });
                                    const productName = product ? (product.display || product.name) : '#' + wooProductId;
                                    addLog('✓ ' + productName + ' - ' + (i18n.syncSuccessful || 'Synced'), 'success');
                                    results.success.push({ id: wooProductId, name: productName });
                                }
                            }
                        } else {
                            errorCount++;
                            const errorMsg = item.body || item.response?.message || 'Unknown error';
                            addLog('✗ Error: ' + errorMsg, 'error');
                            results.errors.push({ error: errorMsg });
                        }
                        
                        updateProgress();
                    });

                    // Sync complete
                    self.onSyncAllComplete(results, syncedCount, errorCount, totalCount);
                },
                error: function(xhr, status, error) {
                    addLog('✗ ' + (i18n.syncFailed || 'Sync failed') + ': ' + error, 'error');
                    self.onSyncAllComplete(results, syncedCount, errorCount + 1, totalCount);
                },
                complete: function() {
                    // Re-enable sync all button
                    $syncAllBtn.prop('disabled', false).removeClass('syncing');
                    $syncAllBtn.find('.dashicons').removeClass('spinning');
                    
                    // Reset all product sync buttons
                    $('.infoplus-sync-single').removeClass('syncing').prop('disabled', false);
                }
            });
        },

        /**
         * Find WooCommerce product ID from variant array
         */
        findWooProductIdFromVariant: function(variants) {
            if (!variants || !variants.length) return null;
            
            const variantId = variants[0].variantId;
            if (!variantId) return null;
            
            // Search in master data for product containing this variant
            for (let i = 0; i < this.masterData.length; i++) {
                const product = this.masterData[i];
                if (product.id == variantId) return product.id;
                if (product.variants) {
                    for (let j = 0; j < product.variants.length; j++) {
                        if (product.variants[j].id == variantId) {
                            return product.id;
                        }
                    }
                }
            }
            return variantId; // Fallback to variant ID
        },

        /**
         * Find product ID by IFE product ID
         */
        findProductIdByIfeId: function(ifeProductId) {
            const product = this.masterData.find(function(p) {
                return p.printApiId === ifeProductId;
            });
            return product ? product.id : null;
        },

        /**
         * Update row after sync (reusable)
         */
        updateRowAfterSync: function(productId, syncResult) {
            const self = this;
            const $row = $('.product-row[data-id="' + productId + '"]');
            const $btn = $row.find('.infoplus-sync-single');
            const formattedDate = new Date().toISOString().slice(0, 19).replace('T', ' ');

            // Animate button
            $btn.removeClass('syncing').addClass('success');
            $btn.html('<span class="dashicons dashicons-yes"></span>');
            
            setTimeout(function() {
                $btn.removeClass('success').html('<span class="dashicons dashicons-update"></span>');
            }, 2000);

            // Update row cells
            $row.find('td').eq(8).text(formattedDate);
            $row.find('td').eq(9).html('<span class="infoplus-badge ok">Synchronized</span>');

            // Update variant rows
            $('.variant-row[data-parent="' + productId + '"]').each(function() {
                $(this).find('td').eq(8).text(formattedDate);
                $(this).find('td').eq(9).html('<span class="infoplus-badge ok">Synchronized</span>');
            });

            // Update master data
            const idx = this.masterData.findIndex(function(p) { return p.id == productId; });
            if (idx !== -1) {
                this.masterData[idx].lastSynchronized = formattedDate;
                this.masterData[idx].synchronized = 0;
                if (this.masterData[idx].variants) {
                    this.masterData[idx].variants.forEach(function(v) {
                        v.lastSynchronized = formattedDate;
                        v.synchronized = 0;
                    });
                }
            }

            // Save to database
            $.ajax({
                url: this.config.saveSyncUrl + '/' + productId + '/sync-result',
                method: 'POST',
                contentType: 'application/json',
                headers: { 'X-WP-Nonce': this.config.nonce },
                data: JSON.stringify({
                    ifeProductId: syncResult.productId || '',
                    variants: syncResult.variants || []
                })
            });
        },

        /**
         * Called when Sync All completes
         */
        onSyncAllComplete: function(results, syncedCount, errorCount, totalCount) {
            const i18n = this.config.i18n;
            
            // Show completion toast
            if (errorCount === 0) {
                this.showToast('success', 
                    i18n.syncAllComplete || 'Sync Complete', 
                    syncedCount + ' ' + (i18n.productsSynced || 'products synchronized')
                );
            } else if (syncedCount > 0) {
                this.showToast('warning', 
                    i18n.syncAllPartial || 'Sync Partially Complete', 
                    syncedCount + ' ' + (i18n.succeeded || 'succeeded') + ', ' + errorCount + ' ' + (i18n.failed || 'failed')
                );
            } else {
                this.showToast('error', 
                    i18n.syncAllFailed || 'Sync Failed', 
                    errorCount + ' ' + (i18n.errors || 'errors')
                );
            }

            // Show summary modal
            this.showSyncAllSummaryModal(results, syncedCount, errorCount, totalCount);
        },

        /**
         * Show Sync All summary modal
         */
        showSyncAllSummaryModal: function(results, syncedCount, errorCount, totalCount) {
            const i18n = this.config.i18n;
            const self = this;
            const hasErrors = errorCount > 0;
            const statusClass = hasErrors ? (syncedCount > 0 ? 'warning' : 'error') : 'success';
            const statusIcon = hasErrors ? (syncedCount > 0 ? 'dashicons-warning' : 'dashicons-dismiss') : 'dashicons-yes-alt';
            const statusTitle = hasErrors 
                ? (syncedCount > 0 ? (i18n.syncPartialTitle || 'Partial Synchronization') : (i18n.syncFailedTitle || 'Synchronization Failed'))
                : (i18n.syncCompleteTitle || 'Synchronization Complete');

            let successListHtml = '';
            if (results.success.length > 0) {
                successListHtml = '<div class="sync-variants-toggle" style="margin-top:16px;">' +
                    '<button type="button" class="sync-variants-toggle-btn" onclick="this.parentElement.classList.toggle(\'open\')">' +
                        '<span class="dashicons dashicons-arrow-right-alt2"></span>' +
                        (i18n.successfulSyncs || 'Successful') + ' (' + results.success.length + ')' +
                    '</button>' +
                    '<div class="sync-variants-list">' +
                        results.success.map(function(item) {
                            return '<div class="sync-variant-item" style="color:#137333;">' +
                                '<span class="dashicons dashicons-yes" style="margin-right:8px;"></span>' +
                                self.esc(item.name) +
                            '</div>';
                        }).join('') +
                    '</div>' +
                '</div>';
            }

            let errorListHtml = '';
            if (results.errors.length > 0) {
                errorListHtml = '<div class="sync-variants-toggle open" style="margin-top:16px;">' +
                    '<button type="button" class="sync-variants-toggle-btn" style="background:#fdecea;" onclick="this.parentElement.classList.toggle(\'open\')">' +
                        '<span class="dashicons dashicons-arrow-right-alt2"></span>' +
                        (i18n.failedSyncs || 'Failed') + ' (' + results.errors.length + ')' +
                    '</button>' +
                    '<div class="sync-variants-list">' +
                        results.errors.map(function(item) {
                            return '<div class="sync-variant-item" style="color:#a12828;">' +
                                '<span class="dashicons dashicons-no" style="margin-right:8px;"></span>' +
                                self.esc(item.error || 'Unknown error') +
                            '</div>';
                        }).join('') +
                    '</div>' +
                '</div>';
            }

            const content = '<div class="sync-result-status ' + statusClass + '">' +
                '<span class="dashicons ' + statusIcon + '"></span>' +
                '<div class="sync-result-status-text">' +
                    '<h4>' + this.esc(statusTitle) + '</h4>' +
                    '<p>' + syncedCount + '/' + totalCount + ' ' + (i18n.productsSyncedDesc || 'products synchronized successfully') + '</p>' +
                '</div>' +
            '</div>' +
            '<div class="sync-result-info">' +
                '<div class="sync-result-info-row">' +
                    '<span class="sync-result-info-label">' + (i18n.totalProducts || 'Total Products') + '</span>' +
                    '<span class="sync-result-info-value">' + totalCount + '</span>' +
                '</div>' +
                '<div class="sync-result-info-row">' +
                    '<span class="sync-result-info-label" style="color:#137333;">' + (i18n.successful || 'Successful') + '</span>' +
                    '<span class="sync-result-info-value" style="color:#137333;font-weight:600;">' + syncedCount + '</span>' +
                '</div>' +
                '<div class="sync-result-info-row">' +
                    '<span class="sync-result-info-label" style="color:#a12828;">' + (i18n.failed || 'Failed') + '</span>' +
                    '<span class="sync-result-info-value" style="color:#a12828;font-weight:600;">' + errorCount + '</span>' +
                '</div>' +
                '<div class="sync-result-info-row">' +
                    '<span class="sync-result-info-label">' + (i18n.syncTime || 'Completed At') + '</span>' +
                    '<span class="sync-result-info-value">' + new Date().toLocaleString() + '</span>' +
                '</div>' +
            '</div>' +
            successListHtml +
            errorListHtml;

            this.showModal(i18n.syncAllResult || 'Batch Synchronization Result', content);
        },

        /**
         * Show sync result modal
         */
        showSyncResultModal: function(productId, result) {
            const i18n = this.config.i18n;
            const variants = result.variants || [];
            const productIfeId = result.productId || '—';
            const self = this;

            let variantsHtml = '';
            if (variants.length > 0) {
               variantsHtml = '<div class="sync-variants-toggle">' +
                '<button type="button" class="sync-variants-toggle-btn" onclick="this.parentElement.classList.toggle(\'open\')">' +
                    '<span class="dashicons dashicons-arrow-right-alt2"></span>' +
                    i18n.synchronizedVariants + ' (' + variants.length + ')' +
                '</button>' +
                '<div class="sync-variants-list">' +
                    variants.map(function(v) {
                        // Look up variant name from master data
                        var variantName = self.getVariantName(productId, v.variantId);
                        return '<div class="sync-variant-item">' +
                            '<span class="sync-variant-id">' + self.esc(variantName || '#' + v.variantId) + '</span>' +
                            '<span class="sync-variant-ife">' + self.esc(v.variantIfeId || '—') + '</span>' +
                        '</div>';
                    }).join('') +
                '</div>' +
            '</div>';
            }

            const content = '<div class="sync-result-status success">' +
                '<span class="dashicons dashicons-yes-alt"></span>' +
                '<div class="sync-result-status-text">' +
                    '<h4>' + i18n.syncComplete + '</h4>' +
                    '<p>' + i18n.syncCompleteDesc + '</p>' +
                '</div>' +
            '</div>' +
            '<div class="sync-result-info">' +
                '<div class="sync-result-info-row">' +
                    '<span class="sync-result-info-label">' + i18n.wooProductId + '</span>' +
                    '<span class="sync-result-info-value">' + this.esc(String(productId)) + '</span>' +
                '</div>' +
                '<div class="sync-result-info-row">' +
                    '<span class="sync-result-info-label">' + i18n.ifeProductId + '</span>' +
                    '<span class="sync-result-info-value">' + this.esc(productIfeId) + '</span>' +
                '</div>' +
                '<div class="sync-result-info-row">' +
                    '<span class="sync-result-info-label">' + i18n.variantsSynced + '</span>' +
                    '<span class="sync-result-info-value">' + variants.length + '</span>' +
                '</div>' +
                '<div class="sync-result-info-row">' +
                    '<span class="sync-result-info-label">' + i18n.syncTime + '</span>' +
                    '<span class="sync-result-info-value">' + new Date().toLocaleString() + '</span>' +
                '</div>' +
            '</div>' +
            variantsHtml;

            this.showModal(i18n.syncResult, content);
        },

        /**
         * Show sync error modal
         */
        showSyncErrorModal: function(productId, errorMsg, errorCode, errorData) {
            const i18n = this.config.i18n;
            const self = this;
            
            // Get product name from master data
            const product = this.masterData.find(function(p) { return p.id == productId; });
            const productName = product ? (product.display || product.name || '#' + productId) : '#' + productId;
            
            // Build error details
            let detailsHtml = '';
            if (errorData) {
                const details = [];
                if (errorCode) details.push({ label: i18n.errorCode || 'Error Code', value: errorCode });
                if (errorData.status) details.push({ label: 'Status', value: errorData.status });
                
                if (details.length > 0) {
                    detailsHtml = '<div class="sync-result-info" style="margin-top:16px;">' +
                        details.map(function(d) {
                            return '<div class="sync-result-info-row">' +
                                '<span class="sync-result-info-label">' + self.esc(d.label) + '</span>' +
                                '<span class="sync-result-info-value">' + self.esc(String(d.value)) + '</span>' +
                            '</div>';
                        }).join('') +
                    '</div>';
                }
            }

            const content = '<div class="sync-result-status error">' +
                '<span class="dashicons dashicons-dismiss"></span>' +
                '<div class="sync-result-status-text">' +
                    '<h4>' + this.esc(i18n.syncErrorTitle || 'Synchronization Failed') + '</h4>' +
                    '<p>' + this.esc(i18n.syncErrorDesc || 'The product could not be synchronized with the IFE system.') + '</p>' +
                '</div>' +
            '</div>' +
            '<div class="sync-result-info">' +
                '<div class="sync-result-info-row">' +
                    '<span class="sync-result-info-label">' + this.esc(i18n.wooProductId || 'Product') + '</span>' +
                    '<span class="sync-result-info-value">' + this.esc(productName) + ' (#' + this.esc(String(productId)) + ')</span>' +
                '</div>' +
                '<div class="sync-result-info-row">' +
                    '<span class="sync-result-info-label">' + this.esc(i18n.errorMessage || 'Error Message') + '</span>' +
                    '<span class="sync-result-info-value" style="color:#a12828;">' + this.esc(errorMsg) + '</span>' +
                '</div>' +
                '<div class="sync-result-info-row">' +
                    '<span class="sync-result-info-label">' + this.esc(i18n.syncTime || 'Time') + '</span>' +
                    '<span class="sync-result-info-value">' + new Date().toLocaleString() + '</span>' +
                '</div>' +
            '</div>' +
            detailsHtml +
            '<div style="margin-top:20px;padding:16px;background:#fff8e5;border:1px solid #ffd699;border-radius:6px;">' +
                '<p style="margin:0;font-size:13px;color:#8a6116;">' +
                    '<span class="dashicons dashicons-info" style="margin-right:6px;"></span>' +
                    this.esc(i18n.errorHint || 'Please check the product configuration and try again. If the problem persists, contact support.') +
                '</p>' +
            '</div>';

            this.showModal(i18n.syncErrorResult || 'Synchronization Error', content);
        },

        /**
         * Show modal
         */
        showModal: function(title, content) {
            $('#infoplus-modal-title').text(title);
            $('#infoplus-modal-body').html(content);
            $('#infoplus-sync-modal').fadeIn(200);
            $('body').css('overflow', 'hidden');
        },

        /**
         * Hide modal
         */
        hideModal: function() {
            $('#infoplus-sync-modal').fadeOut(200);
            $('body').css('overflow', '');
        },

        /**
         * Show toast notification
         */
        showToast: function(type, title, message, duration) {
            duration = duration || 5000;
            const self = this;

            const iconMap = {
                success: 'dashicons-yes-alt',
                error: 'dashicons-dismiss',
                warning: 'dashicons-warning',
                info: 'dashicons-info'
            };

            const toast = $('<div class="infoplus-toast ' + type + '">' +
                '<span class="dashicons ' + (iconMap[type] || iconMap.info) + '"></span>' +
                '<div class="infoplus-toast-content">' +
                    '<div class="infoplus-toast-title">' + this.esc(title) + '</div>' +
                    (message ? '<div class="infoplus-toast-message">' + this.esc(message) + '</div>' : '') +
                '</div>' +
                '<button type="button" class="infoplus-toast-close">' +
                    '<span class="dashicons dashicons-no-alt"></span>' +
                '</button>' +
            '</div>');

            $('#infoplus-toast-container').append(toast);

            const removeToast = function() {
                toast.css('animation', 'infoplus-toastOut 0.3s ease forwards');
                setTimeout(function() { toast.remove(); }, 300);
            };

            toast.find('.infoplus-toast-close').on('click', removeToast);
            if (duration > 0) {
                setTimeout(removeToast, duration);
            }

            return toast;
        },

        // ==========================================
        // Helper Functions
        // ==========================================

        /**
         * Escape HTML
         */
        esc: function(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        },

        /**
         * Highlight search matches
         */
        highlightSafe: function(text, q) {
            if (!q) return this.esc(text);
            const escaped = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const rx = new RegExp(escaped, 'gi');
            return this.esc(text).replace(rx, function(m) {
                return '<mark class="infoplus-hl">' + m + '</mark>';
            });
        },

        /**
         * Get unique sorted array
         */
        uniqueSorted: function(arr) {
            return Array.from(new Set(arr)).filter(Boolean).sort(function(a, b) {
                return a.localeCompare(b);
            });
        },

        /**
         * Get status flag
         */
        statusFlag: function(p) {
            return Number(p.synchronized ?? 2);
        },

        /**
         * Get status badge HTML
         */
        getBadge: function(syncVal) {
            syncVal = Number(syncVal ?? 2);
            switch (syncVal) {
                case 0: return '<span class="infoplus-badge ok">Synchronized</span>';
                case 1: return '<span class="infoplus-badge changed">Modified</span>';
                case 2:
                default: return '<span class="infoplus-badge never">Never Synced</span>';
            }
        },

        /**
         * Build search text for a row
         */
        rowSearchText: function(prod) {
            const parts = [
                String(prod.id ?? ''),
                String(prod.display ?? prod.name ?? ''),
                String(prod.sku ?? ''),
                String(prod.type ?? ''),
                Array.isArray(prod.category) ? prod.category.join(' ') : String(prod.category ?? ''),
                String(prod.printApiId ?? ''),
                String(prod.price ?? ''),
                String(prod.lastModified ?? ''),
                String(prod.lastSynchronized ?? prod.lastSynced ?? '')
            ];

            if (Array.isArray(prod.variants)) {
                prod.variants.forEach(function(v) {
                    parts.push(
                        String(v.id ?? ''),
                        String(v.display ?? v.name ?? ''),
                        String(v.sku ?? ''),
                        String(v.type ?? ''),
                        String(v.printApiId ?? ''),
                        String(v.price ?? ''),
                        String(v.lastModified ?? ''),
                        String(v.lastSynchronized ?? v.lastSynced ?? '')
                    );
                });
            }

            return parts.join(' ').toLowerCase();
        },
        
                /**
         * Get variant name from master data
         */
        getVariantName: function(productId, variantId) {
            // Find the product in master data
            var product = this.masterData.find(function(p) {
                return p.id == productId;
            });
            
            if (!product || !product.variants) {
                return null;
            }
            
            // Find the variant
            var variant = product.variants.find(function(v) {
                return v.id == variantId;
            });
            
            if (variant) {
                return variant.display || variant.name || variant.sku || null;
            }
            
            return null;
        }
                
        
    };

    // Export to window
    window.IkonaDashboard = IkonaDashboard;

})(jQuery);
