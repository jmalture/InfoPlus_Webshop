<?php
namespace Infoplus\Suite\Modules\UserOrders\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Border;

if (!defined('ABSPATH')) exit;

/**
 * User Orders Grid Widget
 * 
 * Displays WooCommerce orders for the logged-in user in a filterable grid.
 */
class UserOrdersGrid extends Widget_Base
{
    public function get_name(): string
    {
        return 'infoplus-user-orders-grid';
    }

    public function get_title(): string
    {
        return __('User Orders Grid', 'infoplus-suite');
    }

    public function get_icon(): string
    {
        return 'eicon-products';
    }

    public function get_categories(): array
    {
        return ['general', 'woocommerce-elements'];
    }

    public function get_keywords(): array
    {
        return ['orders', 'woocommerce', 'user', 'dashboard', 'infoplus'];
    }

    protected function register_controls(): void
    {
        // Content Section
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_filter',
            [
                'label' => __('Show Filter Bar', 'infoplus-suite'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_search',
            [
                'label' => __('Show Search', 'infoplus-suite'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => ['show_filter' => 'yes'],
            ]
        );

        $this->add_control(
            'show_status_filter',
            [
                'label' => __('Show Status Filter', 'infoplus-suite'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => ['show_filter' => 'yes'],
            ]
        );

        $this->add_control(
            'show_results_count',
            [
                'label' => __('Show Results Count', 'infoplus-suite'),
                'type' => Controls_Manager::SWITCHER,
                'default' => '',
                'condition' => ['show_filter' => 'yes'],
            ]
        );

        $this->add_control(
            'search_placeholder',
            [
                'label' => __('Search Placeholder', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Suche nach Bestellnummer, Produkt oder Datum...',
                'condition' => ['show_search' => 'yes'],
            ]
        );

        $this->add_control(
            'columns',
            [
                'label' => __('Columns', 'infoplus-suite'),
                'type' => Controls_Manager::SELECT,
                'default' => '3',
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                ],
            ]
        );

        $this->add_control(
            'max_items_per_order',
            [
                'label' => __('Max Items Shown per Order', 'infoplus-suite'),
                'type' => Controls_Manager::NUMBER,
                'default' => 2,
                'min' => 1,
                'max' => 10,
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
            'empty_message',
            [
                'label' => __('No Orders Message', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Sie haben noch keine Bestellungen.',
            ]
        );

        $this->end_controls_section();

        // Pagination / Load More Section
        $this->start_controls_section(
            'section_pagination',
            [
                'label' => __('Pagination', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'orders_per_page',
            [
                'label' => __('Initial Orders to Show', 'infoplus-suite'),
                'type' => Controls_Manager::NUMBER,
                'default' => 6,
                'min' => 1,
                'max' => 50,
                'description' => __('Number of orders to display initially', 'infoplus-suite'),
            ]
        );

        $this->add_control(
            'show_load_more',
            [
                'label' => __('Show Load More Button', 'infoplus-suite'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'load_more_count',
            [
                'label' => __('Orders to Load on Click', 'infoplus-suite'),
                'type' => Controls_Manager::NUMBER,
                'default' => 3,
                'min' => 1,
                'max' => 20,
                'condition' => ['show_load_more' => 'yes'],
                'description' => __('Number of additional orders to show when clicking Load More', 'infoplus-suite'),
            ]
        );

        $this->add_control(
            'load_more_text',
            [
                'label' => __('Load More Button Text', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Mehr laden',
                'condition' => ['show_load_more' => 'yes'],
            ]
        );

        $this->add_control(
            'load_more_showing_text',
            [
                'label' => __('Showing Count Text', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Zeige {current} von {total} Bestellungen',
                'condition' => ['show_load_more' => 'yes'],
                'description' => __('Use {current} and {total} as placeholders', 'infoplus-suite'),
            ]
        );

        $this->end_controls_section();

        // Buttons Section
        $this->start_controls_section(
            'section_buttons',
            [
                'label' => __('Order Buttons', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'view_button_text',
            [
                'label' => __('View Order Button', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Bestellung ansehen',
            ]
        );

        // Invoice button controls — hidden for now, feature not yet complete
        // $this->add_control(
        //     'invoice_button_text',
        //     [
        //         'label' => __('Invoice Button', 'infoplus-suite'),
        //         'type' => Controls_Manager::TEXT,
        //         'default' => 'Rechnung',
        //     ]
        // );

        // $this->add_control(
        //     'show_invoice_button',
        //     [
        //         'label' => __('Show Invoice Button', 'infoplus-suite'),
        //         'type' => Controls_Manager::SWITCHER,
        //         'default' => 'yes',
        //     ]
        // );

        $this->add_control(
            'open_in_new_tab',
            [
                'label' => __('Open in New Tab', 'infoplus-suite'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'description' => __('Open order details in a new browser tab', 'infoplus-suite'),
            ]
        );

        $this->add_control(
            'order_details_page',
            [
                'label' => __('Order Details Page URL', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => '/order/',
                'description' => __('Base URL for order details (e.g., /order/ for /order/3202/)', 'infoplus-suite'),
            ]
        );

        $this->end_controls_section();

        // Filter Style Section
        $this->start_controls_section(
            'section_filter_style',
            [
                'label' => __('Filter Bar Style', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'filter_background',
            [
                'label' => __('Background Color', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-orders-filter-container' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'filter_border_radius',
            [
                'label' => __('Border Radius', 'infoplus-suite'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 16,
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-orders-filter-container' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'filter_padding',
            [
                'label' => __('Padding', 'infoplus-suite'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem'],
                'default' => [
                    'top' => 2,
                    'right' => 2.5,
                    'bottom' => 2,
                    'left' => 2.5,
                    'unit' => 'rem',
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-orders-filter-container' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Card Style Section
        $this->start_controls_section(
            'section_card_style',
            [
                'label' => __('Card Style', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'card_background',
            [
                'label' => __('Background Color', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-order-card' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'card_border_radius',
            [
                'label' => __('Border Radius', 'infoplus-suite'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => 12,
                    'right' => 12,
                    'bottom' => 12,
                    'left' => 12,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-order-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'card_shadow',
                'selector' => '{{WRAPPER}} .infoplus-order-card',
            ]
        );

        $this->add_control(
            'card_padding',
            [
                'label' => __('Padding', 'infoplus-suite'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default' => [
                    'top' => 24,
                    'right' => 24,
                    'bottom' => 24,
                    'left' => 24,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-order-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'card_gap',
            [
                'label' => __('Gap Between Cards', 'infoplus-suite'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 10,
                        'max' => 60,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 24,
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-orders-grid' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Typography Section
        $this->start_controls_section(
            'section_typography_style',
            [
                'label' => __('Typography', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'order_number_color',
            [
                'label' => __('Order Number Color', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#e91e8c',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-order-number' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'order_number_typography',
                'label' => __('Order Number Typography', 'infoplus-suite'),
                'selector' => '{{WRAPPER}} .infoplus-order-number',
            ]
        );

        $this->add_control(
            'total_color',
            [
                'label' => __('Total Color', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#e91e8c',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-order-total-value' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'total_typography',
                'label' => __('Total Typography', 'infoplus-suite'),
                'selector' => '{{WRAPPER}} .infoplus-order-total-value',
            ]
        );

        $this->end_controls_section();

        // Status Colors Section
        $this->start_controls_section(
            'section_status_colors',
            [
                'label' => __('Status Colors', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'status_pending_color',
            [
                'label' => __('Pending / On Hold', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#f59e0b',
            ]
        );

        $this->add_control(
            'status_processing_color',
            [
                'label' => __('Processing', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#3b82f6',
            ]
        );

        $this->add_control(
            'status_completed_color',
            [
                'label' => __('Completed', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#22c55e',
            ]
        );

        $this->add_control(
            'status_cancelled_color',
            [
                'label' => __('Cancelled / Failed', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ef4444',
            ]
        );

        $this->end_controls_section();

        // Button Style Section
        $this->start_controls_section(
            'section_button_style',
            [
                'label' => __('Order Button Style', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'primary_button_bg',
            [
                'label' => __('Primary Button Background', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#e91e8c',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-btn-primary' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'primary_button_color',
            [
                'label' => __('Primary Button Text', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-btn-primary' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'secondary_button_color',
            [
                'label' => __('Secondary Button Color', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#e91e8c',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-btn-secondary' => 'color: {{VALUE}}; border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_border_radius',
            [
                'label' => __('Button Border Radius', 'infoplus-suite'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 6,
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Load More Button Style Section
        $this->start_controls_section(
            'section_load_more_style',
            [
                'label' => __('Load More Button Style', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => ['show_load_more' => 'yes'],
            ]
        );

        $this->add_control(
            'load_more_bg',
            [
                'label' => __('Background Color', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#e91e8c',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-load-more-btn' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'load_more_bg_hover',
            [
                'label' => __('Background Hover', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#d11a7d',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-load-more-btn:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'load_more_color',
            [
                'label' => __('Text Color', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-load-more-btn' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'load_more_typography',
                'label' => __('Typography', 'infoplus-suite'),
                'selector' => '{{WRAPPER}} .infoplus-load-more-btn',
            ]
        );

        $this->add_control(
            'load_more_border_radius',
            [
                'label' => __('Border Radius', 'infoplus-suite'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 50,
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-load-more-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'load_more_padding',
            [
                'label' => __('Padding', 'infoplus-suite'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default' => [
                    'top' => 14,
                    'right' => 32,
                    'bottom' => 14,
                    'left' => 32,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-load-more-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'load_more_count_color',
            [
                'label' => __('Count Text Color', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#666666',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-load-more-count' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            echo '<div class="infoplus-orders-login-required">';
            echo '<p>' . esc_html__('Bitte melden Sie sich an, um Ihre Bestellungen zu sehen.', 'infoplus-suite') . '</p>';
            echo '</div>';
            return;
        }

        $user_id = get_current_user_id();
        
        // Get ALL orders (we'll handle pagination with JS)
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        // Get status colors from settings
        $status_colors = [
            'pending' => $settings['status_pending_color'],
            'on-hold' => $settings['status_pending_color'],
            'processing' => $settings['status_processing_color'],
            'completed' => $settings['status_completed_color'],
            'cancelled' => $settings['status_cancelled_color'],
            'failed' => $settings['status_cancelled_color'],
            'refunded' => $settings['status_cancelled_color'],
        ];

        // German status labels
        $status_labels = [
            'pending' => 'Ausstehend',
            'processing' => 'In Bearbeitung',
            'on-hold' => 'In Wartestellung',
            'completed' => 'Abgeschlossen',
            'cancelled' => 'Storniert',
            'refunded' => 'Erstattet',
            'failed' => 'Fehlgeschlagen',
        ];

        $columns = $settings['columns'];
        $max_items = (int) $settings['max_items_per_order'];
        $show_images = $settings['show_images'] === 'yes';
        $total_orders = count($orders);
        $orders_per_page = (int) $settings['orders_per_page'];
        $load_more_count = (int) ($settings['load_more_count'] ?? 3);
        $show_load_more = $settings['show_load_more'] === 'yes';
        $open_in_new_tab = $settings['open_in_new_tab'] === 'yes';
        $order_details_base = $settings['order_details_page'] ?? '/order/';
        $target_attr = $open_in_new_tab ? ' target="_blank"' : '';
        ?>
        
        <div class="infoplus-user-orders-widget" 
             data-orders-count="<?php echo $total_orders; ?>"
             data-per-page="<?php echo $orders_per_page; ?>"
             data-load-more-count="<?php echo $load_more_count; ?>">
            
            <?php if ($settings['show_filter'] === 'yes'): ?>
            <div class="infoplus-orders-filter-container">
                <div class="infoplus-orders-filter">
                    <?php if ($settings['show_search'] === 'yes'): ?>
                    <div class="infoplus-search-input-group">
                        <input type="text" 
                               class="infoplus-order-search" 
                               placeholder="<?php echo esc_attr($settings['search_placeholder']); ?>"
                               autocomplete="off">
                    </div>
                    <?php endif; ?>
                    
                    <div class="infoplus-filter-group">
                        <?php if ($settings['show_status_filter'] === 'yes'): ?>
                        <select class="infoplus-status-filter">
                            <option value="">Alle Status</option>
                            <option value="on-hold">In Wartestellung</option>
                            <option value="completed">Abgeschlossen</option>
                            <option value="processing">In Bearbeitung</option>
                        </select>
                        <?php endif; ?>
                        
                        <button class="infoplus-filter-reset">Zurücksetzen</button>
                    </div>
                </div>
                
                <?php if ($settings['show_results_count'] === 'yes'): ?>
                <div class="infoplus-results-info">
                    <span class="infoplus-results-count"><?php echo $total_orders; ?></span> Bestellungen gefunden
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if (empty($orders)): ?>
            <div class="infoplus-orders-empty">
                <p><?php echo esc_html($settings['empty_message']); ?></p>
            </div>
            <?php else: ?>
            <div class="infoplus-orders-grid columns-<?php echo esc_attr($columns); ?>">
                <?php 
                $order_index = 0;
                foreach ($orders as $order): 
                    $status = $order->get_status();
                    $status_color = $status_colors[$status] ?? '#666';
                    $status_label = $status_labels[$status] ?? ucfirst($status);
                    $items = $order->get_items();
                    $items_array = is_array($items) ? $items : iterator_to_array($items);
                    $items_count = count($items_array);
                    $displayed_items = array_slice($items_array, 0, $max_items);
                    $remaining_items = $items_count - $max_items;
                    
                    // Determine if this card should be hidden initially
                    $is_hidden = $order_index >= $orders_per_page;
                ?>
                <div class="infoplus-order-card<?php echo $is_hidden ? ' infoplus-hidden' : ''; ?>" 
                     data-order-id="<?php echo esc_attr($order->get_id()); ?>"
                     data-order-status="<?php echo esc_attr($status); ?>"
                     data-order-number="<?php echo esc_attr($order->get_order_number()); ?>"
                     data-order-date="<?php echo esc_attr($order->get_date_created()->date('Y-m-d')); ?>"
                     data-order-index="<?php echo $order_index; ?>">
                    
                    <div class="infoplus-order-header">
                        <span class="infoplus-order-number">#<?php echo esc_html($order->get_order_number()); ?></span>
                        <span class="infoplus-order-status" style="color: <?php echo esc_attr($status_color); ?>">
                            <?php echo esc_html($status_label); ?>
                        </span>
                    </div>
                    
                    <div class="infoplus-order-date">
                        <?php echo esc_html($order->get_date_created()->date_i18n('j. F Y')); ?>
                    </div>
                    
                    <div class="infoplus-order-items">
                        <?php foreach ($displayed_items as $item): 
                            $product = $item->get_product();
                            $image_url = '';
                            if ($show_images && $product) {
                                $image_id = $product->get_image_id();
                                if ($image_id) {
                                    $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                                }
                            }
                        ?>
                        <div class="infoplus-order-item">
                            <?php if ($show_images && $image_url): ?>
                            <img src="<?php echo esc_url($image_url); ?>" 
                                 alt="" 
                                 class="infoplus-order-item-image">
                            <?php endif; ?>
                            <div class="infoplus-order-item-details">
                                <span class="infoplus-order-item-name"><?php echo esc_html($item->get_name()); ?></span>
                                <span class="infoplus-order-item-qty"><?php echo esc_html($item->get_quantity()); ?>x</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if ($remaining_items > 0): ?>
                        <div class="infoplus-order-items-more">
                            + <?php echo esc_html($remaining_items); ?> weitere Artikel
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="infoplus-order-footer">
                        <div class="infoplus-order-total">
                            <span class="infoplus-order-total-label">GESAMT</span>
                            <span class="infoplus-order-total-value"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></span>
                        </div>
                    </div>
                    
                    <div class="infoplus-order-actions">
                        <?php 
                        // Build custom order details URL
                        $order_url = home_url($order_details_base . $order->get_order_number() . '/');
                        
                        // Invoice URL build — kept for future use when feature is complete
                        // $invoice_url = add_query_arg([
                        //     'action'          => 'generate_wpo_wcpdf',
                        //     'document_type'   => 'invoice',
                        //     'order_ids'       => $order->get_id(),
                        //     'my_account_pdf'  => 'true',
                        //     '_wpnonce'        => wp_create_nonce('generate_wpo_wcpdf'),
                        // ], admin_url('admin-ajax.php'));
                        ?>
                        <a href="<?php echo esc_url($order_url); ?>"
                           class="infoplus-btn infoplus-btn-primary"<?php echo $target_attr; ?>>
                            <?php echo esc_html($settings['view_button_text']); ?>
                        </a>
                        <?php /* Invoice button — hidden for now, feature not yet complete
                        <?php if ($settings['show_invoice_button'] === 'yes'): ?>
                        <a href="<?php echo esc_url($invoice_url); ?>"
                           class="infoplus-btn infoplus-btn-secondary"
                           target="_blank"
                           data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                            <?php echo esc_html($settings['invoice_button_text']); ?>
                        </a>
                        <?php endif; ?>
                        */ ?>
                    </div>
                </div>
                <?php 
                    $order_index++;
                endforeach; 
                ?>
            </div>
            
            <?php if ($show_load_more && $total_orders > $orders_per_page): ?>
            <div class="infoplus-load-more-container">
                <div class="infoplus-load-more-count">
                    <?php 
                    $showing_text = $settings['load_more_showing_text'];
                    $initial_showing = min($orders_per_page, $total_orders);
                    $showing_text = str_replace('{current}', '<span class="infoplus-current-count">' . $initial_showing . '</span>', $showing_text);
                    $showing_text = str_replace('{total}', '<span class="infoplus-total-count">' . $total_orders . '</span>', $showing_text);
                    echo $showing_text;
                    ?>
                </div>
                <button class="infoplus-load-more-btn" data-load-count="<?php echo $load_more_count; ?>">
                    <?php echo esc_html($settings['load_more_text']); ?>
                </button>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
            
            <div class="infoplus-no-results" style="display:none;">
                <p>Keine Bestellungen gefunden.</p>
                <button class="infoplus-filter-reset infoplus-btn infoplus-btn-primary">Filter zurücksetzen</button>
            </div>
        </div>
        
        <style>
        /* Filter Container */
        .infoplus-orders-filter-container {
            background: white;
            padding: 2rem 2.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
        }
        
        .infoplus-orders-filter {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .infoplus-search-input-group {
            flex: 1;
            min-width: 300px;
            position: relative;
        }
        
        .infoplus-order-search {
            width: 100%;
            padding: 1rem;
            border: 2px solid #f0f0f0;
            border-radius: 50px;
            font-size: 1rem;
            transition: all 0.3s ease;
            outline: none;
            background: #f8f8f8;
            box-sizing: border-box;
        }
        
        .infoplus-order-search:focus {
            border-color: #e91e8c;
            background: white;
        }
        
        .infoplus-order-search::placeholder {
            color: #999;
        }
        
        .infoplus-filter-group {
            display: flex;
            gap: 0.8rem;
            align-items: center;
        }
        
        .infoplus-status-filter {
            padding: 0.9rem 2.5rem 0.9rem 1.2rem;
            border: 2px solid #f0f0f0;
            border-radius: 50px;
            font-size: 0.95rem;
            background: #f8f8f8;
            cursor: pointer;
            transition: all 0.3s ease;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            color: #666;
            font-weight: 500;
        }
        
        .infoplus-status-filter:hover {
            border-color: #e0e0e0;
            background-color: white;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
        }
        
        .infoplus-status-filter:focus {
            border-color: #e91e8c;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(233, 30, 140, 0.1);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
        }
        
        .infoplus-filter-reset {
            padding: 0.9rem 1.5rem;
            border: none;
            border-radius: 50px;
            background: transparent;
            color: #e91e8c;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .infoplus-filter-reset:hover {
            background: rgba(233, 30, 140, 0.08);
        }
        
        .infoplus-results-info {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid #f0f0f0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .infoplus-results-count {
            color: #e91e8c;
            font-weight: 600;
        }
        
        /* Grid */
        .infoplus-orders-grid {
            display: grid;
            gap: 24px;
        }
        .infoplus-orders-grid.columns-1 { grid-template-columns: 1fr; }
        .infoplus-orders-grid.columns-2 { grid-template-columns: repeat(2, 1fr); }
        .infoplus-orders-grid.columns-3 { grid-template-columns: repeat(3, 1fr); }
        .infoplus-orders-grid.columns-4 { grid-template-columns: repeat(4, 1fr); }
        
        @media (max-width: 1024px) {
            .infoplus-orders-grid.columns-3,
            .infoplus-orders-grid.columns-4 { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .infoplus-orders-filter-container {
                padding: 1.5rem;
            }
            
            .infoplus-orders-filter {
                flex-direction: column;
            }
            
            .infoplus-search-input-group {
                min-width: 100%;
                width: 100%;
            }
            
            .infoplus-filter-group {
                width: 100%;
                flex-direction: column;
            }
            
            .infoplus-status-filter {
                width: 100%;
            }
            
            .infoplus-orders-grid { 
                grid-template-columns: 1fr !important; 
            }
        }
        
        /* Hidden cards (for pagination) */
        .infoplus-order-card.infoplus-hidden {
            display: none;
        }
        
        /* Order Cards */
        .infoplus-order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 4px;
        }
        .infoplus-order-number {
            font-size: 20px;
            font-weight: 600;
        }
        .infoplus-order-status {
            font-size: 14px;
            font-weight: 500;
        }
        .infoplus-order-date {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .infoplus-order-items {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }
        .infoplus-order-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .infoplus-order-item-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #eee;
        }
        .infoplus-order-item-name {
            font-size: 14px;
            color: #333;
        }
        .infoplus-order-item-qty {
            font-size: 12px;
            color: #666;
        }
        .infoplus-order-items-more {
            font-size: 13px;
            color: #e91e8c;
            font-weight: 500;
        }
        .infoplus-order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid #f0f0f0;
        }
        .infoplus-order-total-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
        }
        .infoplus-order-total-value {
            font-size: 24px;
            font-weight: 600;
        }
        .infoplus-order-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        .infoplus-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .infoplus-btn-primary {
            background: #e91e8c;
            color: white;
            border: none;
        }
        .infoplus-btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .infoplus-btn-secondary {
            background: transparent;
            color: #e91e8c;
            border: 1px solid #e91e8c;
        }
        .infoplus-btn-secondary:hover {
            background: rgba(233, 30, 140, 0.05);
        }
        
        /* Load More */
        .infoplus-load-more-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1rem;
        }
        
        .infoplus-load-more-count {
            font-size: 0.9rem;
            color: #666;
        }
        
        .infoplus-load-more-btn {
            padding: 14px 32px;
            border: none;
            border-radius: 50px;
            background: #e91e8c;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .infoplus-load-more-btn:hover {
            background: #d11a7d;
            transform: translateY(-2px);
        }
        
        .infoplus-load-more-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .infoplus-orders-empty,
        .infoplus-orders-login-required {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .infoplus-no-results {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        </style>
        <?php
    }
}
