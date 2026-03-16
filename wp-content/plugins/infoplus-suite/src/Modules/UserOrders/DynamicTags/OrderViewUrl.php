<?php
namespace Infoplus\Suite\Modules\UserOrders\DynamicTags;

use Elementor\Modules\DynamicTags\Module as DynamicTagsModule;

if (!defined('ABSPATH')) exit;

/**
 * Order View URL Dynamic Tag
 * Returns the URL to view the order details
 */
class OrderViewUrl extends OrderTagBase
{
    public function get_name(): string
    {
        return 'infoplus-order-view-url';
    }

    public function get_title(): string
    {
        return __('Order View URL', 'infoplus-suite');
    }

    public function get_categories(): array
    {
        return [DynamicTagsModule::URL_CATEGORY];
    }

    public function render(): void
    {
        $order = $this->getCurrentOrder();
        
        if (!$order) {
            return;
        }

        echo esc_url($order->get_view_order_url());
    }
}
