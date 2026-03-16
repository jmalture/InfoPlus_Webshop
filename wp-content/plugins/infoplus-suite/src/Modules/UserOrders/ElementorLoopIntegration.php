<?php
namespace Infoplus\Suite\Modules\UserOrders;

if (!defined('ABSPATH')) exit;

/**
 * Elementor Loop Grid Integration for WooCommerce Orders
 * 
 * Registers "User Orders" as a query source in Elementor's Loop Grid widget.
 * 
 * Usage:
 * 1. In Elementor, add a Loop Grid widget
 * 2. Under Layout > Choose template type: Select "Posts"
 * 3. Under Query > Source: Select "Products" (or any post type)
 * 4. Set Query ID to: infoplus_user_orders
 * 5. The query will be overridden to show user's orders
 */
class ElementorLoopIntegration
{
    /**
     * Query ID that triggers our custom query
     */
    const QUERY_ID = 'infoplus_user_orders';

    /**
     * Initialize the integration
     */
    public function init(): void
    {
        // Only proceed if Elementor Pro is active
        if (!defined('ELEMENTOR_PRO_VERSION')) {
            return;
        }

        // Hook into Elementor's query system using our Query ID
        add_action('elementor/query/' . self::QUERY_ID, [$this, 'modifyQuery'], 10, 2);
        
        // Also hook into pre_get_posts as a fallback
        add_action('pre_get_posts', [$this, 'maybeModifyQuery'], 999);
        
        // Add shop_order to allowed post types for Loop Grid
        add_filter('elementor/query/query_args', [$this, 'allowOrdersPostType'], 10, 2);
    }

    /**
     * Modify the query when our Query ID is used
     * This is the main hook that Elementor Pro uses
     * 
     * @param \WP_Query $query The WordPress query object
     * @param \Elementor\Widget_Base $widget The Elementor widget instance
     */
    public function modifyQuery($query, $widget): void
    {
        // Only for logged-in users
        if (!is_user_logged_in()) {
            // Return empty results for non-logged-in users
            $query->set('post__in', [0]);
            return;
        }

        $user_id = get_current_user_id();
        
        // Get the user's order IDs
        $order_ids = $this->getUserOrderIds($user_id);
        
        if (empty($order_ids)) {
            // No orders found - return empty results
            $query->set('post__in', [0]);
            return;
        }

        // Override the query to show orders
        $query->set('post_type', 'shop_order');
        $query->set('post__in', $order_ids);
        $query->set('orderby', 'date');
        $query->set('order', 'DESC');
        $query->set('post_status', $this->getVisibleOrderStatuses());
        
        // Remove any existing meta queries that might interfere
        $query->set('meta_query', []);
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Infoplus UserOrders: Query modified for user ' . $user_id . ' with ' . count($order_ids) . ' orders');
        }
    }

    /**
     * Allow orders post type in query args
     */
    public function allowOrdersPostType($query_args, $widget): array
    {
        $settings = $widget->get_settings_for_display();
        $query_id = $settings['query_id'] ?? '';
        
        // Only modify if our query ID is used
        if ($query_id === self::QUERY_ID) {
            $query_args['post_type'] = 'shop_order';
            $query_args['post_status'] = $this->getVisibleOrderStatuses();
            
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $order_ids = $this->getUserOrderIds($user_id);
                
                if (!empty($order_ids)) {
                    $query_args['post__in'] = $order_ids;
                    $query_args['orderby'] = 'date';
                    $query_args['order'] = 'DESC';
                } else {
                    $query_args['post__in'] = [0];
                }
            } else {
                $query_args['post__in'] = [0];
            }
        }
        
        return $query_args;
    }

    /**
     * Fallback: Modify query via pre_get_posts
     */
    public function maybeModifyQuery(\WP_Query $query): void
    {
        // Check if this query has our custom query var
        if ($query->get('elementor_query_id') === self::QUERY_ID) {
            $this->modifyQuery($query, null);
        }
    }

    /**
     * Get order IDs for a specific user
     * Works with both HPOS and legacy order storage
     */
    private function getUserOrderIds(int $user_id): array
    {
        // Use WooCommerce's order query which handles HPOS automatically
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'limit' => -1,
            'return' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => ['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'],
        ]);

        return $orders ?: [];
    }

    /**
     * Get order statuses visible to customers
     */
    private function getVisibleOrderStatuses(): array
    {
        return [
            'wc-pending',
            'wc-processing', 
            'wc-on-hold',
            'wc-completed',
            'wc-cancelled',
            'wc-refunded',
            'wc-failed',
        ];
    }
}
