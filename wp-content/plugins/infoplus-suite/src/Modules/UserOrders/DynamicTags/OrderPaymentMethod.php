<?php
namespace Infoplus\Suite\Modules\UserOrders\DynamicTags;

use Elementor\Controls_Manager;

if (!defined('ABSPATH')) exit;

/**
 * Order Payment Method Dynamic Tag
 * Displays the payment method used for the order
 */
class OrderPaymentMethod extends OrderTagBase
{
    public function get_name(): string
    {
        return 'infoplus-order-payment-method';
    }

    public function get_title(): string
    {
        return __('Order Payment Method', 'infoplus-suite');
    }

    protected function register_controls(): void
    {
        $this->add_control(
            'display_type',
            [
                'label' => __('Display Type', 'infoplus-suite'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'title' => __('Title (e.g., Direct Bank Transfer)', 'infoplus-suite'),
                    'id' => __('ID (e.g., bacs)', 'infoplus-suite'),
                ],
                'default' => 'title',
            ]
        );
    }

    public function render(): void
    {
        $order = $this->getCurrentOrder();
        
        if (!$order) {
            return;
        }

        $display_type = $this->get_settings('display_type');
        
        if ($display_type === 'id') {
            echo esc_html($order->get_payment_method());
        } else {
            echo esc_html($order->get_payment_method_title());
        }
    }
}
