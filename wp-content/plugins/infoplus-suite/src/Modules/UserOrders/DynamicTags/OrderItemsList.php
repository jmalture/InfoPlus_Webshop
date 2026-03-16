<?php
namespace Infoplus\Suite\Modules\UserOrders\DynamicTags;

use Elementor\Controls_Manager;

if (!defined('ABSPATH')) exit;

/**
 * Order Items List Dynamic Tag
 * Displays a formatted list of order items
 */
class OrderItemsList extends OrderTagBase
{
    public function get_name(): string
    {
        return 'infoplus-order-items-list';
    }

    public function get_title(): string
    {
        return __('Order Items List', 'infoplus-suite');
    }

    protected function register_controls(): void
    {
        $this->add_control(
            'max_items',
            [
                'label' => __('Max Items to Show', 'infoplus-suite'),
                'type' => Controls_Manager::NUMBER,
                'default' => 2,
                'min' => 1,
                'max' => 10,
                'description' => __('Number of items to display before showing "more" link', 'infoplus-suite'),
            ]
        );

        $this->add_control(
            'show_quantity',
            [
                'label' => __('Show Quantity', 'infoplus-suite'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_images',
            [
                'label' => __('Show Product Images', 'infoplus-suite'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'more_text',
            [
                'label' => __('More Items Text', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => '+ {count} weitere Artikel',
                'description' => __('Use {count} as placeholder for remaining items count', 'infoplus-suite'),
            ]
        );
    }

    public function render(): void
    {
        $order = $this->getCurrentOrder();
        
        if (!$order) {
            return;
        }

        $items = $order->get_items();
        $max_items = (int) $this->get_settings('max_items');
        $show_quantity = $this->get_settings('show_quantity') === 'yes';
        $show_images = $this->get_settings('show_images') === 'yes';
        $more_text = $this->get_settings('more_text');
        
        $total_items = count($items);
        $displayed = 0;
        
        echo '<div class="infoplus-order-items">';
        
        foreach ($items as $item) {
            if ($displayed >= $max_items) {
                break;
            }
            
            $product = $item->get_product();
            $image_url = '';
            
            if ($show_images && $product) {
                $image_id = $product->get_image_id();
                if ($image_id) {
                    $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                }
            }
            
            echo '<div class="infoplus-order-item">';
            
            if ($show_images && $image_url) {
                echo '<img src="' . esc_url($image_url) . '" alt="" class="infoplus-order-item-image" />';
            }
            
            echo '<div class="infoplus-order-item-details">';
            echo '<span class="infoplus-order-item-name">' . esc_html($item->get_name()) . '</span>';
            
            if ($show_quantity) {
                echo '<span class="infoplus-order-item-qty"> ' . esc_html($item->get_quantity()) . 'x</span>';
            }
            
            echo '</div>';
            echo '</div>';
            
            $displayed++;
        }
        
        // Show "more items" indicator
        $remaining = $total_items - $displayed;
        if ($remaining > 0 && $more_text) {
            $more_label = str_replace('{count}', $remaining, $more_text);
            echo '<div class="infoplus-order-items-more">' . esc_html($more_label) . '</div>';
        }
        
        echo '</div>';
    }
}
