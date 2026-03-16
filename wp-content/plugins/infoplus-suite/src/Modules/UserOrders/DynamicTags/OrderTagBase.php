<?php
namespace Infoplus\Suite\Modules\UserOrders\DynamicTags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module as DynamicTagsModule;

if (!defined('ABSPATH')) exit;

/**
 * Base class for Order dynamic tags
 * Provides common functionality for retrieving order data in Loop context
 */
abstract class OrderTagBase extends Tag
{
    /**
     * Get the current order object from the loop context
     * 
     * @return \WC_Order|null
     */
    protected function getCurrentOrder(): ?\WC_Order
    {
        // Get the current post ID from the loop context
        $post_id = get_the_ID();
        
        if (!$post_id) {
            return null;
        }
        
        // Try to get the WooCommerce order
        $order = wc_get_order($post_id);
        
        if (!$order || !is_a($order, 'WC_Order')) {
            return null;
        }
        
        return $order;
    }

    /**
     * Get dynamic tag groups
     */
    public function get_group(): string
    {
        return 'infoplus-orders';
    }

    /**
     * Get supported categories
     */
    public function get_categories(): array
    {
        return [DynamicTagsModule::TEXT_CATEGORY];
    }
}
