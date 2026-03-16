<?php
namespace Infoplus\Suite\Modules\UserOrders\DynamicTags;

if (!defined('ABSPATH')) exit;

/**
 * Order Items Count Dynamic Tag
 * Returns the number of line items in the order
 */
class OrderItemsCount extends OrderTagBase
{
    public function get_name(): string
    {
        return 'infoplus-order-items-count';
    }

    public function get_title(): string
    {
        return __('Order Items Count', 'infoplus-suite');
    }

    public function render(): void
    {
        $order = $this->getCurrentOrder();
        
        if (!$order) {
            return;
        }

        echo esc_html(count($order->get_items()));
    }
}
