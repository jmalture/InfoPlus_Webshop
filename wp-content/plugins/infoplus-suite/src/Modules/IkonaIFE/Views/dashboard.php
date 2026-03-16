<?php
/**
 * Ikona IFE Dashboard View
 * 
 * Renders the sync dashboard HTML structure.
 * 
 * @package Infoplus\Suite\Modules\IkonaIFE
 * @since 1.0.0
 * 
 * Variables available:
 * @var string $productsJson - JSON encoded products array
 * @var string $configJson - JSON encoded configuration
 */

if (!defined('ABSPATH')) exit;
?>

<div class="infoplus-sync-dashboard">
    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <div>
            <h2 style="margin:0;"><?php echo esc_html__('Product Sync', 'infoplus-suite'); ?></h2>
            <p class="description">
                <?php echo esc_html__('Sync WooCommerce products with Ikona IFE/IBE system.', 'infoplus-suite'); ?>
            </p>
        </div>
        <button id="infoplus-sync-all" class="button button-primary" style="display:flex;align-items:center;gap:8px;">
            <span class="dashicons dashicons-update"></span>
            <?php echo esc_html__('Sync All', 'infoplus-suite'); ?>
        </button>
    </div>

    <!-- Toolbar -->
    <div class="infoplus-toolbar">
        <div class="infoplus-search-wrap">
            <input id="infoplus-search" type="search" 
                   placeholder="<?php echo esc_attr__('Search products… (name, sku, id, category)', 'infoplus-suite'); ?>" 
                   style="min-width:320px;">
            <span id="infoplus-search-spinner" class="infoplus-spinner" aria-hidden="true" style="display:none;"></span>
        </div>
        <button id="infoplus-search-clear" class="button" type="button">
            <?php echo esc_html__('Clear', 'infoplus-suite'); ?>
        </button>
    </div>

    <!-- Products Table -->
    <div class="infoplus-table-wrap">
        <table class="widefat striped" id="infoplus-sync-table">
            <thead>
                <tr>
                    <th style="width:28px;"></th>
                    <th data-key="display" class="sortable">
                        <?php echo esc_html__('Product Name', 'infoplus-suite'); ?>
                    </th>
                    <th data-key="printApiId" class="sortable">
                        <?php echo esc_html__('Print API ID', 'infoplus-suite'); ?>
                    </th>
                    <th data-key="sku" class="sortable">
                        <?php echo esc_html__('SKU', 'infoplus-suite'); ?>
                    </th>
                    <th data-key="type" class="sortable">
                        <?php echo esc_html__('Type', 'infoplus-suite'); ?>
                    </th>
                    <th data-key="price" class="sortable num">
                        <?php echo esc_html__('Price', 'infoplus-suite'); ?>
                    </th>
                    <th data-key="category" class="sortable">
                        <?php echo esc_html__('Category', 'infoplus-suite'); ?>
                    </th>
                    <th data-key="lastModified" class="sortable date">
                        <?php echo esc_html__('Last Modified', 'infoplus-suite'); ?>
                    </th>
                    <th data-key="lastSynchronized" class="sortable date">
                        <?php echo esc_html__('Last Synchronized', 'infoplus-suite'); ?>
                    </th>
                    <th data-key="synchronized" class="sortable num">
                        <?php echo esc_html__('Status', 'infoplus-suite'); ?>
                    </th>
                    <th style="width:70px;text-align:center;">
                        <?php echo esc_html__('Sync', 'infoplus-suite'); ?>
                    </th>
                </tr>
                <!-- Filter row -->
                <tr class="infoplus-filter-row">
                    <th></th>
                    <th colspan="2"></th>
                    <th></th>
                    <th>
                        <select id="filter-type">
                            <option value=""><?php echo esc_html__('All types', 'infoplus-suite'); ?></option>
                        </select>
                    </th>
                    <th></th>
                    <th>
                        <select id="filter-category">
                            <option value=""><?php echo esc_html__('All categories', 'infoplus-suite'); ?></option>
                        </select>
                    </th>
                    <th></th>
                    <th></th>
                    <th>
                        <select id="filter-status" title="<?php echo esc_attr__('Filter by sync status', 'infoplus-suite'); ?>">
                            <option value=""><?php echo esc_html__('All statuses', 'infoplus-suite'); ?></option>
                            <option value="0">✔ <?php echo esc_html__('Synchronized', 'infoplus-suite'); ?></option>
                            <option value="1">⚠ <?php echo esc_html__('Modified', 'infoplus-suite'); ?></option>
                            <option value="2">⏸ <?php echo esc_html__('Never Synced', 'infoplus-suite'); ?></option>
                        </select>
                    </th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="infoplus-sync-tbody">
                <tr>
                    <td colspan="11" style="text-align:center;">
                        <?php echo esc_html__('Loading products...', 'infoplus-suite'); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Progress Bar (for batch sync) -->
    <div id="infoplus-sync-progress" style="display:none;margin-top:20px;">
        <div class="infoplus-progress-bar-wrap">
            <div id="infoplus-sync-progress-bar">
                <span id="infoplus-sync-progress-text">0%</span>
            </div>
        </div>
        <div id="infoplus-sync-log" style="display:none;"></div>
    </div>
</div>

<!-- Sync Result Modal -->
<div id="infoplus-sync-modal" class="infoplus-modal" style="display:none;">
    <div class="infoplus-modal-backdrop"></div>
    <div class="infoplus-modal-content">
        <div class="infoplus-modal-header">
            <h3 id="infoplus-modal-title"><?php echo esc_html__('Synchronization Result', 'infoplus-suite'); ?></h3>
            <button type="button" class="infoplus-modal-close" aria-label="<?php echo esc_attr__('Close', 'infoplus-suite'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="infoplus-modal-body" id="infoplus-modal-body">
            <!-- Content injected by JavaScript -->
        </div>
        <div class="infoplus-modal-footer">
            <button type="button" class="button button-primary infoplus-modal-close-btn">
                <?php echo esc_html__('Close', 'infoplus-suite'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Toast Notifications Container -->
<div id="infoplus-toast-container"></div>

<!-- Initialize Dashboard -->
<script>
jQuery(function($) {
    // Configuration passed from PHP
    var config = <?php echo $configJson; ?>;
    var preloadedProducts = <?php echo $productsJson ?: '[]'; ?>;
    
    // Initialize the dashboard
    if (window.IkonaDashboard) {
        window.IkonaDashboard.init(config, preloadedProducts);
    }
});
</script>
