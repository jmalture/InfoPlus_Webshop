<?php
namespace Infoplus\Suite\Modules\UserOrders\DynamicTags;

use Elementor\Controls_Manager;

if (!defined('ABSPATH')) exit;

/**
 * Order Total Dynamic Tag
 * Displays the order total with configurable formatting
 */
class OrderTotal extends OrderTagBase
{
    public function get_name(): string
    {
        return 'infoplus-order-total';
    }

    public function get_title(): string
    {
        return __('Order Total', 'infoplus-suite');
    }

    protected function register_controls(): void
    {
        $this->add_control(
            'format',
            [
                'label' => __('Format', 'infoplus-suite'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'formatted' => __('Formatted (with currency symbol)', 'infoplus-suite'),
                    'raw' => __('Raw number only', 'infoplus-suite'),
                ],
                'default' => 'formatted',
            ]
        );
    }

    public function render(): void
    {
        $order = $this->getCurrentOrder();
        
        if (!$order) {
            return;
        }

        $format = $this->get_settings('format');
        
        if ($format === 'raw') {
            echo esc_html($order->get_total());
        } else {
            echo wp_kses_post($order->get_formatted_order_total());
        }
    }
}
