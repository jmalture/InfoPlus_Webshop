<?php
namespace Infoplus\Suite\Modules\UserOrders\DynamicTags;

use Elementor\Controls_Manager;

if (!defined('ABSPATH')) exit;

/**
 * Order Date Dynamic Tag
 * Displays the order date with configurable format
 */
class OrderDate extends OrderTagBase
{
    public function get_name(): string
    {
        return 'infoplus-order-date';
    }

    public function get_title(): string
    {
        return __('Order Date', 'infoplus-suite');
    }

    protected function register_controls(): void
    {
        $this->add_control(
            'date_format',
            [
                'label' => __('Date Format', 'infoplus-suite'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'F j, Y' => date('F j, Y'), // January 1, 2025
                    'j. F Y' => date('j. F Y'), // 1. Januar 2025
                    'd.m.Y' => date('d.m.Y'),   // 01.01.2025
                    'Y-m-d' => date('Y-m-d'),   // 2025-01-01
                    'custom' => __('Custom', 'infoplus-suite'),
                ],
                'default' => 'F j, Y',
            ]
        );

        $this->add_control(
            'custom_format',
            [
                'label' => __('Custom Format', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'F j, Y',
                'condition' => [
                    'date_format' => 'custom',
                ],
                'description' => __('PHP date format string', 'infoplus-suite'),
            ]
        );
    }

    public function render(): void
    {
        $order = $this->getCurrentOrder();
        
        if (!$order) {
            return;
        }

        $date_created = $order->get_date_created();
        
        if (!$date_created) {
            return;
        }

        $format = $this->get_settings('date_format');
        
        if ($format === 'custom') {
            $format = $this->get_settings('custom_format');
        }
        
        echo esc_html($date_created->date_i18n($format));
    }
}
