<?php
namespace Infoplus\Suite\Modules\UserOrders\DynamicTags;

use Elementor\Controls_Manager;

if (!defined('ABSPATH')) exit;

/**
 * Order Status Dynamic Tag
 * Displays the order status with German translations
 */
class OrderStatus extends OrderTagBase
{
    /**
     * German status translations
     */
    private array $german_statuses = [
        'pending' => 'Ausstehend',
        'processing' => 'In Bearbeitung',
        'on-hold' => 'In Wartestellung',
        'completed' => 'Abgeschlossen',
        'cancelled' => 'Storniert',
        'refunded' => 'Erstattet',
        'failed' => 'Fehlgeschlagen',
        'checkout-draft' => 'Entwurf',
    ];

    public function get_name(): string
    {
        return 'infoplus-order-status';
    }

    public function get_title(): string
    {
        return __('Order Status', 'infoplus-suite');
    }

    protected function register_controls(): void
    {
        $this->add_control(
            'display_type',
            [
                'label' => __('Display Type', 'infoplus-suite'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'label' => __('Label (translated)', 'infoplus-suite'),
                    'slug' => __('Slug (e.g., processing)', 'infoplus-suite'),
                ],
                'default' => 'label',
            ]
        );

        $this->add_control(
            'use_german',
            [
                'label' => __('Use German Labels', 'infoplus-suite'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'display_type' => 'label',
                ],
            ]
        );
    }

    public function render(): void
    {
        $order = $this->getCurrentOrder();
        
        if (!$order) {
            return;
        }

        $status = $order->get_status();
        $display_type = $this->get_settings('display_type');
        
        if ($display_type === 'slug') {
            echo esc_html($status);
            return;
        }
        
        // Get label
        $use_german = $this->get_settings('use_german') === 'yes';
        
        if ($use_german && isset($this->german_statuses[$status])) {
            echo esc_html($this->german_statuses[$status]);
        } else {
            echo esc_html(wc_get_order_status_name($status));
        }
    }

    /**
     * Get the status slug for use in conditional styling
     * Can be accessed via data attribute in CSS selectors
     */
    public function getStatusSlug(): string
    {
        $order = $this->getCurrentOrder();
        return $order ? $order->get_status() : '';
    }
}
