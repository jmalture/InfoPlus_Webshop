<?php
namespace Infoplus\Suite\Modules\UserOrders\DynamicTags;

if (!defined('ABSPATH')) exit;

/**
 * Order Number Dynamic Tag
 * Displays the order number (e.g., #3202)
 */
class OrderNumber extends OrderTagBase
{
    public function get_name(): string
    {
        return 'infoplus-order-number';
    }

    public function get_title(): string
    {
        return __('Order Number', 'infoplus-suite');
    }

    public function render(): void
    {
        $order = $this->getCurrentOrder();
        
        if (!$order) {
            return;
        }
        
        echo '#' . esc_html($order->get_order_number());
    }
}
