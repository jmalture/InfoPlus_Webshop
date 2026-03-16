<?php
/**
 * Ikona IFE Module
 * 
 * Handles product synchronization between WooCommerce and the Ikona IFE/IBE system.
 * 
 * @package Infoplus\Suite\Modules\IkonaIFE
 * @since 1.0.0
 */

namespace Infoplus\Suite\Modules\IkonaIFE;

use Infoplus\Suite\Contracts\ModuleInterface;

if (!defined('ABSPATH')) exit;

final class Module implements ModuleInterface
{
    /** @var string Module version */
    public const VERSION = '1.0.4';
    
    /** @var SyncController */
    private SyncController $controller;
    
    /** @var SyncService */
    private SyncService $service;

    /**
     * Get module slug
     */
    public function slug(): string 
    { 
        return 'ikona-ife'; 
    }
    
    /**
     * Get module label
     */
    public function label(): string 
    { 
        return __('IFE Sync', 'infoplus-suite'); 
    }

    /**
     * Register module hooks and dependencies
     */
    public function register(): void
    {
        // Initialize dependencies
        $this->service = new SyncService();
        $this->controller = new SyncController($this->service);
        
        // Register dashboard view
        add_action('infoplus/module/settings/' . $this->slug(), [$this, 'renderDashboard']);
        
        // Register REST API routes
        add_action('rest_api_init', [$this->controller, 'registerRoutes']);
        
        // Register AJAX handlers (for legacy support)
        add_action('wp_ajax_infoplus_sync_all', [$this->controller, 'handleAjaxSyncAll']);
        add_action('wp_ajax_infoplus_sync_product', [$this->controller, 'handleAjaxSyncProduct']);
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    /**
     * Render the sync dashboard
     */
    public function renderDashboard(): void
    {
        // Don't preload products - let JavaScript fetch them asynchronously
        // This makes the page load instantly
        $productsJson = '[]';
        
        // Generate nonce for API calls
        $nonce = wp_create_nonce('wp_rest');
        
        // API URLs
        $config = [
            'proxyUrl' => rest_url('infoplus-suite/v1/products'),
            'syncApiUrl' => rest_url('infoplus/v1/products/synchronization'),
            'saveSyncUrl' => rest_url('infoplus-suite/v1/products'),
            'nonce' => $nonce,
            'loadAsync' => true, // Flag to tell JS to load products
            'i18n' => [
                'noProducts' => __('No products found.', 'infoplus-suite'),
                'loadingProducts' => __('Loading products...', 'infoplus-suite'),
                'syncSuccessful' => __('Sync Successful', 'infoplus-suite'),
                'syncFailed' => __('Sync Failed', 'infoplus-suite'),
                'productSynced' => __('Product synchronized with IFE', 'infoplus-suite'),
                'unknownError' => __('Unknown error', 'infoplus-suite'),
                'syncResult' => __('Synchronization Result', 'infoplus-suite'),
                'syncComplete' => __('Synchronization Complete', 'infoplus-suite'),
                'syncCompleteDesc' => __('Product data has been synced with the IFE system', 'infoplus-suite'),
                'wooProductId' => __('WooCommerce Product ID', 'infoplus-suite'),
                'ifeProductId' => __('IFE Product ID', 'infoplus-suite'),
                'variantsSynced' => __('Variants Synced', 'infoplus-suite'),
                'syncTime' => __('Sync Time', 'infoplus-suite'),
                'synchronizedVariants' => __('Synchronized Variants', 'infoplus-suite'),
                'close' => __('Close', 'infoplus-suite'),
                'syncThis' => __('Sync this product', 'infoplus-suite'),
                'syncVariation' => __('Sync this variation', 'infoplus-suite'),
                'noProductsSync' => __('No products found to sync', 'infoplus-suite'),
                'syncAllConfirm' => __('Sync all', 'infoplus-suite'),
                'productsConfirm' => __('products? This may take a while.', 'infoplus-suite'),
                'comingSoon' => __('Coming Soon', 'infoplus-suite'),
                'batchSyncSoon' => __('Batch sync will be available in a future update', 'infoplus-suite'),
                'loadError' => __('Failed to load products. Please refresh the page.', 'infoplus-suite'),
                // Error modal strings
                'syncErrorTitle' => __('Synchronization Failed', 'infoplus-suite'),
                'syncErrorDesc' => __('The product could not be synchronized with the IFE system.', 'infoplus-suite'),
                'syncErrorResult' => __('Synchronization Error', 'infoplus-suite'),
                'errorMessage' => __('Error Message', 'infoplus-suite'),
                'errorCode' => __('Error Code', 'infoplus-suite'),
                'errorHint' => __('Please check the product configuration and try again. If the problem persists, contact support.', 'infoplus-suite'),
                // Sync All strings
                'syncAllStarting' => __('Starting synchronization...', 'infoplus-suite'),
                'syncAllComplete' => __('Sync Complete', 'infoplus-suite'),
                'syncAllPartial' => __('Sync Partially Complete', 'infoplus-suite'),
                'syncAllFailed' => __('Sync Failed', 'infoplus-suite'),
                'syncAllResult' => __('Batch Synchronization Result', 'infoplus-suite'),
                'productsSynced' => __('products synchronized', 'infoplus-suite'),
                'productsSyncedDesc' => __('products synchronized successfully', 'infoplus-suite'),
                'succeeded' => __('succeeded', 'infoplus-suite'),
                'failed' => __('failed', 'infoplus-suite'),
                'errors' => __('errors', 'infoplus-suite'),
                'totalProducts' => __('Total Products', 'infoplus-suite'),
                'successful' => __('Successful', 'infoplus-suite'),
                'successfulSyncs' => __('Successful', 'infoplus-suite'),
                'failedSyncs' => __('Failed', 'infoplus-suite'),
                'syncCompleteTitle' => __('Synchronization Complete', 'infoplus-suite'),
                'syncPartialTitle' => __('Partial Synchronization', 'infoplus-suite'),
                'syncFailedTitle' => __('Synchronization Failed', 'infoplus-suite'),
            ],
        ];
        $configJson = wp_json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        // Include the view
        include __DIR__ . '/Views/dashboard.php';
    }

    /**
     * Enqueue admin assets for the dashboard
     */
    public function enqueueAdminAssets(string $hook): void
    {
        // Only load on our plugin page
        if (strpos($hook, 'infoplus-suite') === false) {
            return;
        }
        
        $assetUrl = plugin_dir_url(__FILE__) . 'Assets/';
        $version = self::VERSION;
        
        // Enqueue CSS
        wp_enqueue_style(
            'infoplus-ikona-dashboard',
            $assetUrl . 'dashboard.css',
            [],
            $version
        );
        
        // Enqueue JS
        wp_enqueue_script(
            'infoplus-ikona-dashboard',
            $assetUrl . 'dashboard.js',
            ['jquery'],
            $version,
            true
        );
    }

    /**
     * Get the asset URL for this module
     */
    public static function assetUrl(string $path = ''): string
    {
        return plugin_dir_url(__FILE__) . 'Assets/' . ltrim($path, '/');
    }
}
