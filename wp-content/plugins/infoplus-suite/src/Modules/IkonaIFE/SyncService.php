<?php
/**
 * Sync Service
 * 
 * Handles business logic for product synchronization including
 * fetching products, caching, and saving sync results to database.
 * 
 * @package Infoplus\Suite\Modules\IkonaIFE
 * @since 1.0.0
 */

namespace Infoplus\Suite\Modules\IkonaIFE;

if (!defined('ABSPATH')) exit;

class SyncService
{
    /** @var string Transient key for products cache */
    private const TRANSIENT_KEY = 'infoplus_products_cache';
    
    /** @var int Cache TTL in seconds */
    private const CACHE_TTL = 60;
    
    /** @var string Meta key for last sync timestamp */
    public const META_LAST_SYNCED = '_infoplus_last_synced';
    
    /** @var string Meta key for sync status */
    public const META_SYNC_STATUS = '_infoplus_sync_status';
    
    /** @var string Meta key for IFE product ID */
    public const META_IFE_PRODUCT_ID = '_infoplus_ife_product_id';
    
    /** @var string Meta key for IFE variant ID */
    public const META_IFE_VARIANT_ID = '_infoplus_ife_variant_id';

    /**
     * Sync status constants
     */
    public const STATUS_SYNCHRONIZED = 0;
    public const STATUS_MODIFIED = 1;
    public const STATUS_NEVER_SYNCED = 2;

    /**
     * Fetch products from the Infoplus API with caching
     * 
     * @return array Array of products or empty array on failure
     */
    public function fetchProducts(): array
    {
        // Check transient cache first
        $cached = get_transient(self::TRANSIENT_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        // Fetch from API
        $url = rest_url('infoplus/v1/products');
        
        $args = [
            'timeout' => 15,
            'headers' => [],
        ];

        // Include Authorization if configured
        if (defined('INFOPLUS_API_TOKEN') && INFOPLUS_API_TOKEN) {
            $args['headers']['Authorization'] = 'Bearer ' . INFOPLUS_API_TOKEN;
        }

        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $this->logError('Failed to fetch products: ' . $response->get_error_message());
            return [];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->logError('Products API returned status code: ' . $code);
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            $this->logError('Products API returned invalid JSON');
            return [];
        }

        // Cache the results
        set_transient(self::TRANSIENT_KEY, $body, self::CACHE_TTL);
        
        return $body;
    }
    
        /**
     * Fetch products with sync status
     * 
     * Note: The API now returns lastSynchronized and synchronized fields directly,
     * so no additional merging is required.
     * 
     * @return array Array of products with sync status
     */
    public function fetchProductsWithSyncStatus(): array
    {
        return $this->fetchProducts();
    }
        
    
    
    
    
    
    
    
    
    

    /**
     * Save sync result to product and variant meta
     * 
     * @param int $productId WooCommerce product ID
     * @param string $ifeProductId IFE product UUID
     * @param array $variants Array of variant data
     * @return array Result with sync time and counts
     */
    public function saveSyncResult(int $productId, string $ifeProductId, array $variants): array
    {
        $product = wc_get_product($productId);
        
        if (!$product) {
            return [
                'success' => false,
                'message' => __('Product not found', 'infoplus-suite'),
            ];
        }
        
        $syncTime = current_time('mysql');
        
        // Save to parent product
        $this->saveProductMeta($productId, $ifeProductId, $syncTime);
        
        // Save to each variant
        $variantCount = 0;
        if (is_array($variants)) {
            foreach ($variants as $variant) {
                $variantId = absint($variant['variantId'] ?? 0);
                $variantIfeId = sanitize_text_field($variant['variantIfeId'] ?? '');
                
                if ($variantId) {
                    $this->saveVariantMeta($variantId, $variantIfeId, $syncTime);
                    $variantCount++;
                }
            }
        }
        
        // Clear cache so fresh data is loaded
        $this->clearCache();
        
        return [
            'success' => true,
            'syncTime' => $syncTime,
            'productId' => $productId,
            'variantCount' => $variantCount,
        ];
    }

    /**
     * Save sync meta to a product
     * 
     * @param int $productId Product ID
     * @param string $ifeProductId IFE product UUID
     * @param string $syncTime Sync timestamp
     */
    private function saveProductMeta(int $productId, string $ifeProductId, string $syncTime): void
    {
        update_post_meta($productId, self::META_LAST_SYNCED, $syncTime);
        update_post_meta($productId, self::META_SYNC_STATUS, self::STATUS_SYNCHRONIZED);
        
        if ($ifeProductId) {
            update_post_meta($productId, self::META_IFE_PRODUCT_ID, $ifeProductId);
        }
    }

    /**
     * Save sync meta to a variant
     * 
     * @param int $variantId Variant ID
     * @param string $ifeVariantId IFE variant UUID
     * @param string $syncTime Sync timestamp
     */
    private function saveVariantMeta(int $variantId, string $ifeVariantId, string $syncTime): void
    {
        update_post_meta($variantId, self::META_LAST_SYNCED, $syncTime);
        update_post_meta($variantId, self::META_SYNC_STATUS, self::STATUS_SYNCHRONIZED);
        
        if ($ifeVariantId) {
            update_post_meta($variantId, self::META_IFE_VARIANT_ID, $ifeVariantId);
        }
    }

    /**
     * Get sync status for a product
     * 
     * @param int $productId Product ID
     * @return array Sync status data
     */
    public function getProductSyncStatus(int $productId): array
    {
        return [
            'lastSynced' => get_post_meta($productId, self::META_LAST_SYNCED, true) ?: null,
            'status' => (int) get_post_meta($productId, self::META_SYNC_STATUS, true) ?: self::STATUS_NEVER_SYNCED,
            'ifeProductId' => get_post_meta($productId, self::META_IFE_PRODUCT_ID, true) ?: null,
        ];
    }

    /**
     * Clear the products cache
     */
    public function clearCache(): void
    {
        delete_transient(self::TRANSIENT_KEY);
    }

    /**
     * Mark a product as modified (needs re-sync)
     * 
     * @param int $productId Product ID
     */
    public function markAsModified(int $productId): void
    {
        $currentStatus = (int) get_post_meta($productId, self::META_SYNC_STATUS, true);
        
        // Only mark as modified if it was previously synchronized
        if ($currentStatus === self::STATUS_SYNCHRONIZED) {
            update_post_meta($productId, self::META_SYNC_STATUS, self::STATUS_MODIFIED);
        }
    }

    /**
     * Log an error message
     * 
     * @param string $message Error message
     */
    private function logError(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Infoplus IkonaIFE: ' . $message);
        }
    }
}
